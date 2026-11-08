<?php
namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Services\AcademicPeriod;
use App\Models\MonthlyAbsence;
use App\Services\ExcelWorkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Templates and master-data exports, in Excel format only. */
class ExcelController
{
    private const LABELS = [
        'classes' => 'Data Kelas dan Wali Kelas', 'teachers' => 'Wali Kelas', 'students' => 'Data Siswa', 'weekly' => 'Input Absensi Mingguan', 'monthly' => 'Input Absensi Bulanan', 'opening' => 'Saldo Awal Semester',
    ];

    /** Header yang mengikuti format administrasi sekolah. Urutan ini sama pada impor dan ekspor. */
    public static function displayHeaders(string $type): array
    {
        return match ($type) {
            'classes', 'teachers' => ['KELAS', 'WALI KELAS'],
            'students' => ['NOMOR', 'NIS', 'NISN', 'NAMA', 'JENIS KELAMIN', 'KELAS', 'STATUS PONDOK', 'NAMA PONDOK'],
            'weekly' => ['NIS', 'NISN', 'NAMA', 'KELAS', 'MINGGU DIMULAI', 'SAKIT JP', 'IZIN JP', 'ALPA JP', 'CATATAN'],
            'monthly' => ['NIS', 'NISN', 'NAMA', 'KELAS', 'BULAN', 'SAKIT JP', 'IZIN JP', 'ALPA JP', 'CATATAN'],
            'opening' => ['NIS', 'NISN', 'NAMA', 'KELAS', 'TAHUN PELAJARAN', 'SEMESTER', 'SALDO S.D. TANGGAL', 'SAKIT JP', 'IZIN JP', 'ALPA JP', 'KETERANGAN'],
        };
    }

    public function template(Request $request): StreamedResponse
    {
        $type = $request->validate(['type' => ['required', Rule::in(ImportController::TYPES)]])['type'];
        $headers = self::displayHeaders($type);
        $samples = match ($type) {
            'classes' => [['VII A', 'Nama Wali Kelas']],
            'teachers' => [['VII A', 'Nama Wali Kelas']],
            'students' => [[1, '000123', '0012345678', 'CONTOH SISWA', 'L', 'VII A', 'Mondok', 'CONTOH PONDOK']],
            'weekly' => [['000123', '0012345678', 'CONTOH SISWA', 'VII A', '2026-10-19', 0, 0, 24, 'Tanggal Senin']],
            'monthly' => [['', '0012345678', 'CONTOH SISWA', 'VII A', '2026-10', 0, 0, 24, 'Contoh bulan']],
        };
        $description = match ($type) {
            'classes', 'teachers' => 'Masukkan KELAS beserta nama WALI KELAS. Impor menggunakan tahun pelajaran yang dipilih di aplikasi.',
            'students' => 'NIS opsional. Enam kolom pertama sesuai format sekolah. STATUS PONDOK dan NAMA PONDOK opsional. Hapus baris contoh.',
            'weekly' => 'SAKIT/IZIN/ALPA dalam JP bilangan bulat. Jika sumber dalam satuan HARI, pilih opsi Hari saat impor. Minggu dimulai Senin.',
            'monthly' => 'SAKIT/IZIN/ALPA dalam JP bilangan bulat. Jika sumber dalam satuan HARI, pilih opsi Hari saat impor. Jangan campur satuan.',
        };
        return ExcelWorkbook::download('template-'.$type.'.xlsx', $headers, $samples,
            'TEMPLATE '.mb_strtoupper(self::LABELS[$type]), $description);
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_merge(ImportController::TYPES, ['opening']))],
            'academic_year' => ['required', 'string'],
            'class_id' => ['nullable', 'integer', 'min:0'],
            'week_start' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $year = trim($data['academic_year']);
        if (!AcademicPeriod::isValidYear($year)) abort(422, 'Tahun pelajaran tidak valid.');
        $type = $data['type'];
        $classId = (int) ($data['class_id'] ?? 0);
        if ($classId && !Classroom::forYear($year)->whereKey($classId)->exists()) abort(422, 'Kelas bukan dari tahun pelajaran tersebut.');
        $headers = self::displayHeaders($type);
        $rows = match ($type) {
            'classes' => Classroom::forYear($year)->orderBy('class_name')->get()->map(fn ($c) => [$c->class_name, $c->homeroom_teacher ?? '']),
            'teachers' => Classroom::forYear($year)->orderBy('class_name')->get()->map(fn ($c) => [$c->class_name, $c->homeroom_teacher ?? '']),
            'students' => Student::with('classroom')->whereHas('classroom', fn ($q) => $q->where('academic_year', $year))
                ->when($classId, fn ($q) => $q->where('class_id', $classId))->orderBy('full_name')->get()
                ->values()->map(fn ($s, $i) => [$i + 1, $s->nis, $s->nisn ?? '', $s->full_name, $s->gender, $s->classroom->class_name, $s->boarding_status ?? 'Tidak', $s->boarding_school ?? '']),
            'weekly' => DB::table('weekly_absence as w')->join('students as s', 's.id', '=', 'w.student_id')
                ->join('classes as c', 'c.id', '=', 'w.class_id')->where('w.academic_year', $year)
                ->when($classId, fn ($q) => $q->where('w.class_id', $classId))
                ->when(!empty($data['week_start']), fn ($q) => $q->whereDate('w.week_start', $data['week_start']))
                ->orderBy('w.week_start')->orderBy('c.class_name')->orderBy('s.full_name')
                ->get()->map(fn ($r) => [$r->nis, $r->nisn, $r->full_name, $r->class_name, $r->week_start, (int) $r->sakit_jp, (int) $r->izin_jp, (int) $r->alpa_jp, $r->note ?? '']),
            'opening' => DB::table('opening_absence as w')->join('students as s','s.id','=','w.student_id')->join('classes as c','c.id','=','w.class_id')
                ->where('w.academic_year', $year)->when($classId,fn ($q)=>$q->where('w.class_id',$classId))
                ->orderBy('c.class_name')->orderBy('s.full_name')->get()
                ->map(fn($r)=>[$r->nis,$r->nisn,$r->full_name,$r->class_name,$r->academic_year,$r->semester,$r->as_of_date,
                    (int)$r->sakit_jp,(int)$r->izin_jp,(int)$r->alpa_jp,$r->note??'']),
            'monthly' => DB::table('monthly_absence as w')->join('students as s','s.id','=','w.student_id')->join('classes as c','c.id','=','w.class_id')
                ->where('w.academic_year',$year)->when($classId,fn ($q)=>$q->where('w.class_id',$classId))
                ->when(!empty($data['month']),fn($q)=>$q->whereDate('w.month_start',$data['month'].'-01'))
                ->orderBy('w.month_start')->orderBy('c.class_name')->orderBy('s.full_name')->get()
                ->map(fn($r)=>[$r->nis,$r->nisn,$r->full_name,$r->class_name,substr($r->month_start,0,7),(int)$r->sakit_jp,(int)$r->izin_jp,(int)$r->alpa_jp,$r->note??'']),
        };
        return ExcelWorkbook::download('ekspor-'.$type.'-'.str_replace('/', '-', $year).'.xlsx', $headers, $rows,
            'EKSPOR '.mb_strtoupper(self::LABELS[$type]), 'Tahun Pelajaran '.$year.' | Data bersumber langsung dari database aplikasi.');
    }
}
