<?php
namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Services\AbsenceReport;
use App\Services\AbsenceEntries;
use App\Services\AcademicPeriod;
use App\Services\Discipline;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController
{
    public function index(): View
    {
        $year = AcademicPeriod::currentYear();
        $semester = AcademicPeriod::currentSemester();
        $currentWeek = CarbonImmutable::today()->startOfWeek()->toDateString();
        $previousWeek = CarbonImmutable::today()->subWeek()->startOfWeek()->toDateString();
        $rows = AbsenceEntries::query()
            ->join('students as s', 's.id', '=', 'entries.student_id')
            ->join('classes as c', 'c.id', '=', 'entries.class_id')
            ->where('entries.academic_year', $year)
            ->whereIn('entries.entry_date', [$currentWeek, $previousWeek])
            ->where('entries.entry_mode','Mingguan')
            ->whereRaw('(entries.alpa_jp + entries.izin_jp + entries.sakit_jp) > 0')
            ->select('s.full_name', 'c.class_name', 'entries.entry_date as week_start', 'entries.alpa_jp', 'entries.izin_jp', 'entries.sakit_jp')
            ->orderBy('c.class_name')->orderBy('s.full_name')->get();
        $currentMonth = CarbonImmutable::today()->format('Y-m');
        $monthlyRows = DB::table('monthly_absence as m')->join('students as s','s.id','=','m.student_id')
            ->join('classes as c','c.id','=','m.class_id')->where('m.academic_year',$year)
            ->whereDate('m.month_start',$currentMonth.'-01')
            ->whereRaw('(m.alpa_jp + m.izin_jp + m.sakit_jp) > 0')
            ->select('s.full_name','c.class_name','m.alpa_jp','m.izin_jp','m.sakit_jp')
            ->orderBy('c.class_name')->orderBy('s.full_name')->get();
        $warnings = AbsenceReport::query($year, $semester)->get()
            ->map(fn ($row) => (object) array_merge((array) $row, ['level' => Discipline::level((int) $row->alpa_jp), 'label' => Discipline::label((int) $row->alpa_jp)]))
            ->filter(fn ($row) => $row->level > 0)->sortByDesc('level')->values();
        return view('dashboard', [
            'year' => $year, 'semester' => $semester, 'currentWeek' => $currentWeek, 'previousWeek' => $previousWeek,
            'thisWeek' => $rows->where('week_start', $currentWeek), 'lastWeek' => $rows->where('week_start', $previousWeek),
            'currentMonth' => $currentMonth, 'monthlyRows' => $monthlyRows, 'openingSummary' => DB::table('opening_absence')->where('academic_year', $year)->where('semester', $semester)->selectRaw('COUNT(*) as siswa, COALESCE(SUM(sakit_jp),0) as sakit, COALESCE(SUM(izin_jp),0) as izin, COALESCE(SUM(alpa_jp),0) as alpa')->first(), 'totalClasses' => Classroom::forYear($year)->count(), 'totalStudents' => Student::where('active', true)->whereHas('classroom', fn ($q) => $q->where('academic_year', $year))->count(), 'warnings' => $warnings,
        ]);
    }
}
