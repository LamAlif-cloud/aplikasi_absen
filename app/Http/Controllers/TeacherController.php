<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Services\YearOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Wali kelas disimpan sebagai penugasan pada kelas, bukan entitas pengguna login. */
class TeacherController
{
    public function index(Request $request): View
    {
        $year = YearOptions::selected($request->query('academic_year'));
        return view('teachers.index', [
            'year' => $year, 'years' => YearOptions::all(),
            'classes' => Classroom::forYear($year)->withCount('students')->orderBy('class_name')->get(),
        ]);
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $data = $request->validate(['homeroom_teacher' => ['required', 'string', 'max:120']]);
        $teacher = trim($data['homeroom_teacher']);
        if ($teacher === '') return back()->withErrors(['homeroom_teacher' => 'Nama wali kelas tidak boleh kosong.']);
        $classroom->update(['homeroom_teacher' => $teacher]);
        $this->log($request, 'wali_kelas_edit', $classroom->class_name.' / '.$teacher);
        return redirect()->route('teachers.index', ['academic_year' => $classroom->academic_year])->with('success', 'Penugasan wali kelas berhasil disimpan.');
    }

    public function destroy(Request $request, Classroom $classroom): RedirectResponse
    {
        $request->validate(['confirm_delete' => ['accepted']]);
        $old = $classroom->homeroom_teacher;
        $classroom->update(['homeroom_teacher' => null]);
        $this->log($request, 'wali_kelas_hapus', $classroom->class_name.' / '.($old ?: '-'));
        return redirect()->route('teachers.index', ['academic_year' => $classroom->academic_year])->with('success', 'Penugasan wali kelas dikosongkan. Kelas dan siswa tetap ada.');
    }

    private function log(Request $request, string $action, string $detail): void
    {
        AuditLog::create(['user_id' => Auth::id(), 'action' => $action, 'detail' => $detail, 'ip_address' => $request->ip()]);
    }
}
