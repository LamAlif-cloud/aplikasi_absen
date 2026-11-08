<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\MonthlyAbsence;
use App\Models\Student;
use App\Services\AbsenceEntries;
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

class MonthlyController
{
    public function index(Request $request): View
    {
        $year = YearOptions::selected($request->query('academic_year'));
        $classes = Classroom::forYear($year)->withCount(['students', 'students as active_students_count' => fn ($q) => $q->where('active', true)])->orderBy('class_name')->get();
        $asked = (int) $request->query('class_id', 0);
        $classId = $classes->contains('id', $asked) ? $asked : (int) ($classes->firstWhere('active_students_count', '>', 0)?->id ?? $classes->first()?->id ?? 0);
        $month = (string) $request->query('month', AcademicPeriod::defaultMonthForYear($year));
        try {
            $d = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');
            if (!$d || $d->format('Y-m') !== $month || AcademicPeriod::yearForDate($d) !== $year) throw new \InvalidArgumentException();
        } catch (\Throwable) { $month = AcademicPeriod::defaultMonthForYear($year); }
        $students = $classId ? Student::where('class_id', $classId)->where('active', true)->orderBy('full_name')->get() : collect();
        $data = $classId ? MonthlyAbsence::where('class_id', $classId)->where('academic_year', $year)->whereDate('month_start', $month.'-01')->get()->keyBy('student_id') : collect();
        $weeklyStudentIds = $classId ? DB::table('weekly_absence')->where('academic_year', $year)->whereBetween('week_start', [$month.'-01', CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString()])->whereIn('student_id', $students->pluck('id'))->distinct()->pluck('student_id')->map(fn ($id) => (int) $id)->all() : [];
        $openingCount = $classId ? DB::table('opening_absence')->where('class_id', $classId)
            ->where('academic_year', $year)->whereDate('as_of_date', '>=', $month.'-01')
            ->whereDate('as_of_date', '<=', CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString())
            ->count() : 0;
        return view('monthly.index', compact('year','classes','classId','month','students','data','weeklyStudentIds','openingCount') + ['years' => YearOptions::all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'academic_year' => ['required','string','regex:/^[0-9]{4}\/[0-9]{4}$/'],
            'class_id' => ['required','integer',Rule::exists('classes','id')],
            'month' => ['required','date_format:Y-m'],
            'rows' => ['required','array','min:1','max:500'],
            'rows.*.student_id' => ['required','integer',Rule::exists('students','id')],
            'rows.*.alpa_jp' => ['required','integer','between:0,248'],
            'rows.*.izin_jp' => ['required','integer','between:0,248'],
            'rows.*.sakit_jp' => ['required','integer','between:0,248'],
            'rows.*.note' => ['nullable','string','max:255'],
        ]);
        if (!AcademicPeriod::isValidYear($data['academic_year'])) throw ValidationException::withMessages(['academic_year' => 'Tahun pelajaran tidak valid.']);
        $d = CarbonImmutable::createFromFormat('!Y-m-d', $data['month'].'-01');
        if (!$d || $d->format('Y-m') !== $data['month'] || AcademicPeriod::yearForDate($d) !== $data['academic_year']) {
            throw ValidationException::withMessages(['month' => 'Bulan tidak sesuai tahun pelajaran.']);
        }
        DB::transaction(function () use ($data, $request): void {
            $class = Classroom::whereKey($data['class_id'])->lockForUpdate()->firstOrFail();
            if ($class->academic_year !== $data['academic_year']) throw ValidationException::withMessages(['class_id' => 'Tahun pelajaran kelas tidak sesuai.']);
            $valid = Student::where('class_id', $class->id)->where('active', true)->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $provided = array_map(fn ($r) => (int) $r['student_id'], $data['rows']); sort($provided);
            if ($valid !== $provided) throw ValidationException::withMessages(['rows' => 'Data siswa berubah atau ada duplikasi ID. Muat ulang form.']);
            foreach ($data['rows'] as $r) {
                if ($r['alpa_jp'] + $r['izin_jp'] + $r['sakit_jp'] > 248) throw ValidationException::withMessages(['rows' => 'Jumlah total JP satu siswa melebihi 248 pada bulan tersebut.']);
                AbsenceEntries::ensureMonthAvailable((int) $r['student_id'], $data['academic_year'], $data['month'], 'Bulanan');
                $other = MonthlyAbsence::where('student_id', $r['student_id'])->where('academic_year', $data['academic_year'])->whereDate('month_start', $data['month'].'-01')->where('class_id', '!=', $class->id)->exists();
                if ($other) throw ValidationException::withMessages(['rows' => 'Siswa sudah memiliki catatan bulan ini pada kelas berbeda.']);
                MonthlyAbsence::updateOrCreate(['student_id' => $r['student_id'], 'academic_year' => $data['academic_year'], 'month_start' => $data['month'].'-01'], [
                    'class_id' => $class->id, 'alpa_jp' => $r['alpa_jp'], 'izin_jp' => $r['izin_jp'], 'sakit_jp' => $r['sakit_jp'],
                    'note' => $r['note'] ?? null, 'created_by' => Auth::id(),
                ]);
            }
            AuditLog::create(['user_id' => Auth::id(), 'action' => 'monthly_save', 'detail' => $data['academic_year'].' kelas '.$class->id.' bulan '.$data['month'], 'ip_address' => $request->ip()]);
        }, 3);
        return redirect()->route('monthly.index', ['academic_year' => $data['academic_year'], 'class_id' => $data['class_id'], 'month' => $data['month']])->with('success', 'Rekap bulanan tersimpan dan otomatis masuk ke laporan serta tata tertib semester.');
    }
}
