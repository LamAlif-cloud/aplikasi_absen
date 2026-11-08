<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Services\AcademicPeriod;
use App\Services\YearOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassController
{
    public function index(Request $request): View
    {
        $year = YearOptions::selected($request->query('academic_year'));
        $classes = Classroom::forYear($year)->withCount('students')->orderBy('class_name')->get();
        $edit = null;
        if (Auth::user()->role === 'admin' && $request->filled('edit_class')) {
            $edit = Classroom::forYear($year)->find($request->integer('edit_class'));
        }
        return view('classes.index', compact('year', 'classes', 'edit') + ['years' => YearOptions::all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_name' => ['required', 'string', 'max:60'],
            'academic_year' => ['required', 'regex:/^[0-9]{4}\/[0-9]{4}$/'],
            'homeroom_teacher' => ['nullable', 'string', 'max:120'],
        ]);
        if (!AcademicPeriod::isValidYear($data['academic_year'])) {
            throw ValidationException::withMessages(['academic_year' => 'Tahun pelajaran harus dua tahun berurutan.']);
        }
        $data['class_name'] = mb_strtoupper(trim($data['class_name']));
        if ($data['class_name'] === '') throw ValidationException::withMessages(['class_name' => 'Nama kelas tidak boleh kosong.']);
        $c = Classroom::firstOrCreate(
            ['class_name' => $data['class_name'], 'academic_year' => $data['academic_year']],
            ['homeroom_teacher' => trim($data['homeroom_teacher'] ?? '') ?: null]
        );
        if (!$c->wasRecentlyCreated) return back()->with('notice', 'Kelas tersebut sudah terdaftar untuk tahun pelajaran yang sama.');
        $this->log($request, 'kelas_baru', $c->class_name.' / '.$c->academic_year);
        return redirect()->route('classes.index', ['academic_year' => $c->academic_year])->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $data = $request->validate([
            'class_name' => ['required', 'string', 'max:60'],
            'homeroom_teacher' => ['nullable', 'string', 'max:120'],
        ]);
        $name = mb_strtoupper(trim($data['class_name']));
        if ($name === '') throw ValidationException::withMessages(['class_name' => 'Nama kelas tidak boleh kosong.']);
        $duplicate = Classroom::forYear($classroom->academic_year)->where('class_name', $name)->where('id', '!=', $classroom->id)->exists();
        if ($duplicate) throw ValidationException::withMessages(['class_name' => 'Nama kelas sudah dipakai pada tahun pelajaran ini.']);
        $classroom->update(['class_name' => $name, 'homeroom_teacher' => trim($data['homeroom_teacher'] ?? '') ?: null]);
        $this->log($request, 'kelas_edit', $classroom->id.' / '.$name);
        return redirect()->route('classes.index', ['academic_year' => $classroom->academic_year])->with('success', 'Data kelas dan wali kelas berhasil diperbarui.');
    }

    public function destroy(Request $request, Classroom $classroom): RedirectResponse
    {
        $request->validate(['confirm_delete' => ['accepted']]);
        DB::transaction(function () use ($classroom, $request): void {
            $locked = Classroom::whereKey($classroom->id)->lockForUpdate()->firstOrFail();
            $links = [
                'siswa' => DB::table('students')->where('class_id', $locked->id)->exists(),
                'absensi JP' => DB::table('weekly_absence')->where('class_id', $locked->id)->exists() || DB::table('monthly_absence')->where('class_id', $locked->id)->exists() || DB::table('opening_absence')->where('class_id', $locked->id)->exists(),
                'riwayat kelas' => DB::table('student_class_history')->where('old_class_id', $locked->id)->orWhere('new_class_id', $locked->id)->exists(),
            ];
            $blocked = array_keys(array_filter($links));
            if ($blocked) throw ValidationException::withMessages(['classroom' => 'Kelas tidak boleh dihapus karena memiliki '.implode(', ', $blocked).'. Pindahkan data terkait terlebih dahulu.']);
            $description = $locked->class_name.' / '.$locked->academic_year;
            $locked->delete();
            $this->log($request, 'kelas_hapus', $description);
        });
        return redirect()->route('classes.index', ['academic_year' => $classroom->academic_year])->with('success', 'Kelas kosong berhasil dihapus.');
    }

    private function log(Request $request, string $action, string $detail): void
    {
        AuditLog::create(['user_id' => Auth::id(), 'action' => $action, 'detail' => $detail, 'ip_address' => $request->ip()]);
    }
}
