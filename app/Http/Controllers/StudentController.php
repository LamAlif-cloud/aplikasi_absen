<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\YearOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentController
{
    public function index(Request $request): View
    {
        $year = YearOptions::selected($request->query('academic_year'));
        $classes = Classroom::forYear($year)->orderBy('class_name')->get();
        $classId = (int) $request->query('class_id', 0);
        if ($classId && !$classes->contains('id', $classId)) $classId = 0;
        $search = trim((string) $request->query('q', ''));
        $boarding = (string) $request->query('boarding', 'all');
        if (!in_array($boarding, ['all', 'Mondok', 'Tidak'], true)) $boarding = 'all';
        $pondok = trim((string) $request->query('pondok', ''));
        $query = Student::with('classroom')->whereHas('classroom', fn ($q) => $q->where('academic_year', $year));
        if ($classId) $query->where('class_id', $classId);
        if ($boarding !== 'all') $query->where('boarding_status', $boarding);
        if ($pondok !== '') $query->where('boarding_school', 'like', '%'.$pondok.'%');
        if ($search !== '') $query->where(fn ($q) => $q->where('full_name', 'like', '%'.$search.'%')->orWhere('nis', 'like', '%'.$search.'%')->orWhere('nisn', 'like', '%'.$search.'%'));
        $edit = null;
        if (Auth::user()->role === 'admin' && $request->filled('edit_student')) {
            $edit = Student::whereHas('classroom', fn ($q) => $q->where('academic_year', $year))->find($request->integer('edit_student'));
        }
        return view('students.index', [
            'year' => $year, 'years' => YearOptions::all(), 'classes' => $classes, 'classId' => $classId,
            'search' => $search, 'boarding' => $boarding, 'pondok' => $pondok, 'students' => $query->orderBy('full_name')->paginate(40)->withQueryString(), 'edit' => $edit,
        ]);
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'nis' => ['nullable', 'string', 'max:40', Rule::unique('students', 'nis')->ignore($student?->id)],
            'nisn' => ['nullable', 'string', 'max:30'],
            'full_name' => ['required', 'string', 'max:150'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')],
            'student_status' => ['required', Rule::in(['Aktif', 'Mutasi', 'Berhenti', 'Lulus'])],
            'active' => ['required', 'boolean'],
            'boarding_status' => ['nullable', Rule::in(['Mondok','Tidak'])],
            'boarding_school' => ['nullable','string','max:120'],
            'status_date' => ['nullable','date_format:Y-m-d'],
            'status_note' => ['nullable','string','max:255'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $student = Student::create($this->clean($data));
        $class = Classroom::findOrFail($student->class_id);
        $this->log($request, 'siswa_baru', $student->nis.' / '.$class->class_name);
        return redirect()->route('students.index', ['academic_year' => $class->academic_year, 'class_id' => $class->id])
            ->with('success', 'Siswa berhasil ditambahkan ke '.$class->class_name.'. Periksa di Input Rekap Absen pada tahun yang sama.');
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $this->clean($this->validated($request, $student));
        $newClass = Classroom::findOrFail($data['class_id']);
        DB::transaction(function () use ($request, $student, $newClass, $data): void {
            $locked = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            if ((int)$locked->class_id !== (int)$newClass->id) {
                $oldClass = Classroom::findOrFail($locked->class_id);
                DB::table('student_class_history')->insert([
                    'student_id' => $locked->id, 'old_class_id' => $oldClass->id, 'new_class_id' => $newClass->id,
                    'academic_year_from' => $oldClass->academic_year, 'academic_year_to' => $newClass->academic_year,
                    'changed_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($locked->student_status !== $data['student_status']) {
                DB::table('student_status_history')->insert([
                    'student_id' => $locked->id, 'old_status' => $locked->student_status,
                    'new_status' => $data['student_status'], 'effective_date' => $data['status_date'] ?? now()->toDateString(),
                    'note' => $data['status_note'] ?? null, 'changed_by' => Auth::id(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $locked->update($data);
            $this->log($request, 'siswa_edit', $locked->nis.' / kelas '.$newClass->class_name);
        });
        return redirect()->route('students.index', ['academic_year' => $newClass->academic_year, 'class_id' => $newClass->id])
            ->with('success', 'Data siswa berhasil diperbarui; perubahan kelas dicatat dalam riwayat.');
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        $request->validate(['confirm_delete' => ['accepted']]);
        $class = $student->classroom;
        DB::transaction(function () use ($student, $request): void {
            $locked = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $hasWeekly = DB::table('weekly_absence')->where('student_id', $locked->id)->exists() || DB::table('monthly_absence')->where('student_id', $locked->id)->exists() || DB::table('opening_absence')->where('student_id', $locked->id)->exists();
            $hasLetters = DB::table('disciplinary_letters')->where('student_id', $locked->id)->exists();
            $hasHistory = DB::table('student_class_history')->where('student_id', $locked->id)->exists() || DB::table('student_status_history')->where('student_id', $locked->id)->exists() || DB::table('disciplinary_actions')->where('student_id', $locked->id)->exists();
            if ($hasWeekly || $hasLetters || $hasHistory) {
                throw ValidationException::withMessages(['student' => 'Siswa memiliki absensi, surat, atau riwayat kelas. Untuk menjaga laporan historis, ubah status menjadi tidak aktif; jangan hapus permanen.']);
            }
            $nis = $locked->nis;
            $locked->delete();
            $this->log($request, 'siswa_hapus', $nis);
        });
        return redirect()->route('students.index', ['academic_year' => $class->academic_year, 'class_id' => $class->id])->with('success', 'Siswa tanpa riwayat transaksi berhasil dihapus.');
    }

    private function clean(array $data): array
    {
        $data['nis'] = trim((string) ($data['nis'] ?? '')) ?: null;
        $data['nisn'] = trim($data['nisn'] ?? '') ?: null;
        $data['boarding_status'] = $data['boarding_status'] ?? 'Tidak';
        $data['boarding_school'] = $data['boarding_status'] === 'Mondok' ? (trim($data['boarding_school'] ?? '') ?: null) : null;
        $data['status_note'] = trim($data['status_note'] ?? '') ?: null;
        $data['full_name'] = trim($data['full_name']);
        $data['active'] = (bool) $data['active'];
        if ($data['student_status'] !== 'Aktif') $data['active'] = false;
        return $data;
    }

    private function log(Request $request, string $action, string $detail): void
    {
        AuditLog::create(['user_id' => Auth::id(), 'action' => $action, 'detail' => $detail, 'ip_address' => $request->ip()]);
    }
}
