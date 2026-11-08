<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DisciplinaryLetter;
use App\Models\Student;
use App\Services\AbsenceReport;
use App\Services\AcademicPeriod;
use App\Services\Discipline;
use App\Services\YearOptions;
use App\Services\LetterDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LetterController
{
    public function index(Request $request): View
    {
        $year = YearOptions::selected($request->query('academic_year'));
        $semester = (string) $request->query('semester', AcademicPeriod::currentSemester());
        if (!in_array($semester, ['Ganjil', 'Genap'], true)) $semester = AcademicPeriod::currentSemester();
        $eligible = AbsenceReport::query($year, $semester)->get()
            ->map(fn ($r) => (object) array_merge((array) $r, ['level' => Discipline::level((int) $r->alpa_jp)]))
            ->filter(fn ($r) => $r->level > 0)->values();
        $letters = DisciplinaryLetter::with('student')->where('academic_year', $year)->where('semester', $semester)->latest()->get();
        $letterValidity = [];
        foreach ($letters as $letter) {
            $jp = AbsenceReport::studentAlpa((int) $letter->student_id, $letter->academic_year, $letter->semester);
            $letterValidity[$letter->id] = Discipline::level($jp) >= (int) $letter->level;
        }
        return view('letters.index', compact('year', 'semester', 'eligible', 'letters', 'letterValidity') + ['years' => YearOptions::all(), 'schoolSettings' => DB::table('school_settings')->pluck('value', 'key')->all()]);
    }
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'academic_year' => ['required', 'string'], 'semester' => ['required', Rule::in(['Ganjil', 'Genap'])],
            'level' => ['required', 'integer', Rule::in([1, 2, 3])],
            'letter_number' => ['required', 'string', 'max:100'], 'letter_date' => ['required', 'date_format:Y-m-d'],
            'meeting_date' => ['nullable', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (!AcademicPeriod::isValidYear($data['academic_year'])) throw ValidationException::withMessages(['academic_year' => 'Tahun pelajaran tidak valid.']);
        DB::transaction(function () use ($data, $request): void {
            Student::whereKey($data['student_id'])->lockForUpdate()->firstOrFail();
            $jp = AbsenceReport::studentAlpa((int) $data['student_id'], $data['academic_year'], $data['semester']);
            if (Discipline::level($jp) < $data['level']) throw ValidationException::withMessages(['level' => 'Ambang Alpa semester belum memenuhi tingkat surat ini.']);
            if (DisciplinaryLetter::where('student_id', $data['student_id'])->where('academic_year', $data['academic_year'])->where('semester', $data['semester'])->where('level', $data['level'])->exists()) {
                throw ValidationException::withMessages(['level' => 'Surat tingkat ini sudah ada. Lihat riwayat surat.']);
            }
            $letter = DisciplinaryLetter::create($data + ['created_by' => Auth::id(), 'status' => 'draft']);
            AuditLog::create(['user_id' => Auth::id(), 'action' => 'surat_draft', 'detail' => 'Surat #'.$letter->id.' tingkat '.$letter->level, 'ip_address' => $request->ip()]);
        });
        return redirect()->route('letters.index', ['academic_year' => $data['academic_year'], 'semester' => $data['semester']])->with('success', 'Surat berhasil disimpan. Periksa isi dan terbitkan melalui Riwayat Surat.');
    }
    public function print(DisciplinaryLetter $letter): View
    {
        $this->ensureEligibleForDocument($letter);
        return view('letters.print', LetterDocument::prepare($letter) + ['pdfMode' => false]);
    }

    /** Unduh PDF A4 menggunakan isi yang sama dengan tombol Print. */
    public function pdf(DisciplinaryLetter $letter): \Symfony\Component\HttpFoundation\Response
    {
        $this->ensureEligibleForDocument($letter);
        if (!class_exists(Dompdf::class)) {
            abort(503, 'Mesin PDF belum terpasang. Jalankan composer require dompdf/dompdf dari folder proyek Aplikasi Absensi Siswa.');
        }
        $values = LetterDocument::prepare($letter) + ['pdfMode' => true];
        $html = view('letters.print', $values)->render();
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Serif');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        $file = 'Surat_Tatib_'.$letter->id.'_'.preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $letter->letter_number).'.pdf';
        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$file.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
    public function status(Request $request, DisciplinaryLetter $letter): RedirectResponse
    {
        $data = $request->validate(['status'=>['required',Rule::in(['issued','completed'])]]);
        DB::transaction(function () use ($letter,$data,$request):void {
            $locked = DisciplinaryLetter::whereKey($letter->id)->lockForUpdate()->firstOrFail();
            if (($locked->status === 'draft' && $data['status'] !== 'issued') ||
                ($locked->status === 'issued' && $data['status'] !== 'completed') ||
                $locked->status === 'completed') {
                throw ValidationException::withMessages(['status'=>'Surat harus diterbitkan dahulu sebelum diselesaikan; surat selesai tidak dapat diulang.']);
            }
            $totalAlpa = AbsenceReport::studentAlpa((int) $locked->student_id, $locked->academic_year, $locked->semester);
            if (Discipline::level($totalAlpa) < (int) $locked->level) {
                throw ValidationException::withMessages(['status' => 'SURAT DIBLOKIR: siswa tidak memenuhi ambang tindakan berdasarkan Alpa semester saat ini ('.$totalAlpa.' JP). Periksa kembali data dan surat lama.']);
            }
            $locked->update(['status'=>$data['status']]);
            AuditLog::create(['user_id'=>Auth::id(),'action'=>'letter_status',
                'detail'=>'Surat #'.$letter->id.' '.$data['status'],'ip_address'=>$request->ip()]);
        },3);
        return back()->with('success','Status surat berhasil diperbarui.');
    }

    /** Check again just before printing/downloading; old drafts can become invalid. */
    private function ensureEligibleForDocument(DisciplinaryLetter $letter): void
    {
        $jp = AbsenceReport::studentAlpa((int) $letter->student_id, $letter->academic_year, $letter->semester);
        if (Discipline::level($jp) < (int) $letter->level) {
            abort(422, 'SURAT DIBLOKIR: Alpa siswa hanya '.$jp.' JP pada semester surat, tidak memenuhi batas jenis surat tersebut. Data harus diverifikasi dahulu.');
        }
    }

}
