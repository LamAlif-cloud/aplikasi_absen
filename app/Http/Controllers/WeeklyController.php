<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\WeeklyAbsence;
use App\Services\AcademicPeriod;
use App\Services\AbsenceEntries;
use App\Services\YearOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WeeklyController
{
    public function index(Request $request): View
    {
        $year = YearOptions::selected($request->query('academic_year'));
        $classes = Classroom::forYear($year)->withCount([
            'students', 'students as active_students_count' => fn ($q) => $q->where('active', true),
        ])->orderBy('class_name')->get();
        $requestedId = (int) $request->query('class_id', 0);
        $classId = $classes->contains('id', $requestedId) ? $requestedId : 0;
        if (!$classId) {
            // Display an enrolled class rather than an arbitrarily empty first class.
            $classId = (int) ($classes->firstWhere('active_students_count', '>', 0)?->id ?? $classes->first()?->id ?? 0);
        }
        $week = (string) $request->query('week_start', AcademicPeriod::defaultMondayForYear($year));
        try {
            $w = CarbonImmutable::createFromFormat('!Y-m-d', $week);
            if (!$w || $w->format('Y-m-d') !== $week || !$w->isMonday() || AcademicPeriod::yearForDate($w) !== $year) throw new \InvalidArgumentException();
        } catch (\Throwable) {
            $week = AcademicPeriod::defaultMondayForYear($year);
        }
        $students = $classId ? Student::where('class_id', $classId)->where('active', true)->orderBy('full_name')->get() : collect();
        $data = $classId ? WeeklyAbsence::where('class_id', $classId)->where('academic_year', $year)->whereDate('week_start', $week)->get()->keyBy('student_id') : collect();
        $monthlyConflictIds = $classId ? DB::table('monthly_absence')
            ->where('academic_year', $year)->whereDate('month_start', substr($week,0,7).'-01')
            ->whereIn('student_id', $students->pluck('id'))
            ->pluck('student_id')->all() : [];
        $selectedClass = $classes->firstWhere('id', $classId);
        $allStudentsInClass = $selectedClass ? (int) $selectedClass->students_count : 0;
        $inactiveInClass = max(0, $allStudentsInClass - $students->count());
        return view('weekly.index', compact('year', 'classes', 'classId', 'week', 'students', 'data', 'selectedClass', 'allStudentsInClass', 'inactiveInClass', 'monthlyConflictIds') + ['years' => YearOptions::all()]);
    }
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'academic_year' => ['required', 'string', 'regex:/^[0-9]{4}\/[0-9]{4}$/'],
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')],
            'week_start' => ['required', 'date_format:Y-m-d'],
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.student_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'rows.*.alpa_jp' => ['required', 'integer', 'between:0,80'],
            'rows.*.izin_jp' => ['required', 'integer', 'between:0,80'],
            'rows.*.sakit_jp' => ['required', 'integer', 'between:0,80'],
            'rows.*.note' => ['nullable', 'string', 'max:255'],
        ]);
        $week = CarbonImmutable::createFromFormat('!Y-m-d', $data['week_start']);
        if (!$week || !$week->isMonday() || AcademicPeriod::yearForDate($week) !== $data['academic_year']) {
            throw ValidationException::withMessages(['week_start' => 'Tanggal harus Senin dan termasuk tahun pelajaran yang dipilih.']);
        }
        DB::transaction(function () use ($data, $request): void {
            $class = Classroom::whereKey($data['class_id'])->lockForUpdate()->firstOrFail();
            if ($class->academic_year !== $data['academic_year']) throw ValidationException::withMessages(['class_id' => 'Kelas bukan dari tahun pelajaran ini.']);
            $valid = Student::where('class_id', $class->id)->where('active', true)->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $provided = array_map(fn ($row) => (int) $row['student_id'], $data['rows']);
            sort($provided);
            if ($valid !== $provided) throw ValidationException::withMessages(['rows' => 'Daftar siswa berubah atau ada ID ganda. Muat ulang halaman.']);
            // Never silently move an already-recorded week to another classroom.
            $conflicting = WeeklyAbsence::where('academic_year', $data['academic_year'])
                ->whereDate('week_start', $data['week_start'])
                ->whereIn('student_id', $provided)
                ->where('class_id', '!=', $class->id)->exists();
            if ($conflicting) throw ValidationException::withMessages(['rows' => 'Ada siswa dengan catatan minggu ini pada kelas berbeda. Periksa riwayat perpindahan kelas.']);
            foreach ($data['rows'] as $row) {
                AbsenceEntries::ensureMonthAvailable((int) $row['student_id'], $data['academic_year'], substr($data['week_start'], 0, 7), 'Mingguan');
                WeeklyAbsence::updateOrCreate([
                    'student_id' => $row['student_id'], 'academic_year' => $data['academic_year'], 'week_start' => $data['week_start'],
                ], [
                    'class_id' => $class->id, 'alpa_jp' => $row['alpa_jp'], 'izin_jp' => $row['izin_jp'], 'sakit_jp' => $row['sakit_jp'],
                    'note' => $row['note'] ?? null, 'created_by' => Auth::id(),
                ]);
            }
            AuditLog::create(['user_id' => Auth::id(), 'action' => 'weekly_save', 'detail' => $data['academic_year'].' kelas '.$class->id.' minggu '.$data['week_start'], 'ip_address' => $request->ip()]);
        }, 3);
        return redirect()->route('weekly.index', ['academic_year' => $data['academic_year'], 'class_id' => $data['class_id'], 'week_start' => $data['week_start']])->with('success', 'Rekap JP mingguan berhasil disimpan dan disinkronkan.');
    }
}
