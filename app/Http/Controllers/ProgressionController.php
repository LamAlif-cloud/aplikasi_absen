<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\AcademicPeriod;
use App\Services\YearOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Administrasi kenaikan kelas dan kelulusan massal dengan pelestarian riwayat. */
class ProgressionController
{
    public function index(Request $request): View
    {
        $year = YearOptions::selected($request->query('academic_year'));
        $targetYear = sprintf('%04d/%04d', (int) substr($year, 0, 4) + 1, (int) substr($year, 5, 4) + 1);
        $classes = Classroom::forYear($year)->orderBy('class_name')->get();
        $targetClasses = Classroom::forYear($targetYear)->orderBy('class_name')->get();
        $classId = (int) $request->query('class_id', 0);
        if (!$classes->contains('id', $classId)) $classId = (int) ($classes->first()?->id ?? 0);
        $students = $classId ? Student::where('class_id', $classId)->where('active', true)->orderBy('full_name')->get() : collect();
        $history = DB::table('student_class_history as h')->join('students as s', 's.id', '=', 'h.student_id')
            ->join('classes as c1', 'c1.id', '=', 'h.old_class_id')->join('classes as c2', 'c2.id', '=', 'h.new_class_id')
            ->where('h.academic_year_from', $year)->select('s.full_name','c1.class_name as old_class','c2.class_name as new_class','h.academic_year_to','h.created_at')
            ->orderByDesc('h.id')->limit(40)->get();
        $statuses = DB::table('student_status_history as h')->join('students as s', 's.id', '=', 'h.student_id')
            ->whereIn('s.class_id', $classes->pluck('id'))->select('s.full_name','h.old_status','h.new_status','h.effective_date','h.note')
            ->orderByDesc('h.id')->limit(40)->get();
        return view('progression.index', compact('year','targetYear','classes','targetClasses','classId','students','history','statuses') + ['years' => YearOptions::all()]);
    }

    public function promote(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_class_id' => ['required','integer',Rule::exists('classes','id')],
            'target_class_id' => ['required','integer',Rule::exists('classes','id')],
            'student_ids' => ['required','array','min:1','max:500'],
            'student_ids.*' => ['required','integer','distinct',Rule::exists('students','id')],
            'confirm' => ['accepted'],
        ]);
        $count = DB::transaction(function () use ($data, $request): int {
            $source = Classroom::findOrFail($data['source_class_id']);
            $target = Classroom::findOrFail($data['target_class_id']);
            if ((int) substr($target->academic_year, 0, 4) !== (int) substr($source->academic_year, 0, 4) + 1) {
                throw ValidationException::withMessages(['target_class_id' => 'Kelas tujuan wajib tahun pelajaran berikutnya.']);
            }
            $rows = Student::whereIn('id', $data['student_ids'])->lockForUpdate()->get();
            if ($rows->count() !== count($data['student_ids']) || $rows->contains(fn ($s) => (int) $s->class_id !== (int) $source->id || !$s->active || $s->student_status !== 'Aktif')) {
                throw ValidationException::withMessages(['student_ids' => 'Semua siswa harus aktif dan masih terdaftar pada kelas asal. Muat ulang halaman.']);
            }
            foreach ($rows as $s) {
                // Keep old absences attached to the old class; update current enrollment only.
                DB::table('student_class_history')->insert([
                    'student_id' => $s->id, 'old_class_id' => $source->id, 'new_class_id' => $target->id,
                    'academic_year_from' => $source->academic_year, 'academic_year_to' => $target->academic_year,
                    'changed_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $s->update(['class_id' => $target->id]);
            }
            AuditLog::create(['user_id' => Auth::id(),'action' => 'promote_bulk','detail' => 'Dari '.$source->class_name.' ke '.$target->class_name.'; '.$rows->count().' siswa','ip_address' => $request->ip()]);
            return $rows->count();
        }, 3);
        return back()->with('success', $count.' siswa dipindah ke kelas tujuan. Riwayat kelas dan absensi tahun sebelumnya dipertahankan.');
    }

    public function status(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_class_id' => ['required','integer',Rule::exists('classes','id')],
            'student_ids' => ['required','array','min:1','max:500'],
            'student_ids.*' => ['required','integer','distinct',Rule::exists('students','id')],
            'status' => ['required', Rule::in(['Lulus','Mutasi','Berhenti'])],
            'effective_date' => ['required','date_format:Y-m-d'],
            'note' => ['nullable','string','max:255'],
            'confirm' => ['accepted'],
        ]);
        $count = DB::transaction(function () use ($data,$request): int {
            $origin = Classroom::findOrFail($data['source_class_id']);
            if (AcademicPeriod::yearForDate(CarbonImmutable::parse($data['effective_date'])) !== $origin->academic_year) {
                throw ValidationException::withMessages(['effective_date'=>'Tanggal perubahan status harus berada pada tahun pelajaran kelas asal.']);
            }
            $rows = Student::whereIn('id', $data['student_ids'])->lockForUpdate()->get();
            if ($rows->count() !== count($data['student_ids']) || $rows->contains(fn ($s) => (int) $s->class_id !== (int) $data['source_class_id'] || !$s->active || $s->student_status !== 'Aktif')) {
                throw ValidationException::withMessages(['student_ids' => 'Pilihan siswa harus aktif pada kelas asal yang dipilih.']);
            }
            foreach ($rows as $s) {
                DB::table('student_status_history')->insert([
                    'student_id' => $s->id, 'old_status' => $s->student_status,
                    'new_status' => $data['status'], 'effective_date' => $data['effective_date'],
                    'note' => $data['note'] ?? null,'changed_by' => Auth::id(),
                    'created_at' => now(),'updated_at' => now(),
                ]);
                $s->update(['student_status'=>$data['status'],'active'=>false,'status_date'=>$data['effective_date'],'status_note'=>$data['note']??null]);
            }
            AuditLog::create(['user_id'=>Auth::id(),'action'=>'bulk_student_status','detail'=>$data['status'].' '.$rows->count().' siswa','ip_address'=>$request->ip()]);
            return $rows->count();
        }, 3);
        return back()->with('success', $count.' siswa ditetapkan '.$data['status'].'. Riwayat JP tetap dapat dilaporkan.');
    }
}
