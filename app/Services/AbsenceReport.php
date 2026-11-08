<?php
namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AbsenceReport
{
    /** Exactly one aggregation path shared by dashboard, report, export and letter eligibility. */
    public static function query(string $year, string $period, ?int $classId = null, ?string $week = null, ?string $month = null, string $action = 'all'): Builder
    {
        [$from, $to] = self::bounds($year, $period, $week, $month);
        $action = array_key_exists($action, Discipline::FILTERS) ? $action : 'all';
        $short = in_array($period, ['Mingguan', 'Bulanan'], true);
        $entryFrom = $from;
        if ($action !== 'all' && $short) {
            [$entryFrom] = AcademicPeriod::bounds($year, (int) substr($from, 5, 2) >= 7 ? 'Ganjil' : 'Genap');
        }
        $q = AbsenceEntries::query()
            ->join('students as s', 's.id', '=', 'entries.student_id')
            ->join('classes as c', 'c.id', '=', 'entries.class_id')
            ->where('entries.academic_year', $year)
            ->whereBetween('entries.entry_date', [$entryFrom, $to]);
        if ($classId !== null) $q->where('entries.class_id', $classId);
        $classExpr = DB::getDriverName() === 'sqlite'
            ? "GROUP_CONCAT(DISTINCT c.class_name)"
            : "GROUP_CONCAT(DISTINCT c.class_name ORDER BY c.class_name SEPARATOR ', ')";
        $q->selectRaw("s.id AS student_id, s.nis, s.full_name, {$classExpr} AS class_label");
        $q->selectRaw('SUM(CASE WHEN entries.entry_date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS period_entry_count', [$from, $to]);
        foreach (['alpa_jp', 'izin_jp', 'sakit_jp'] as $field) {
            $q->selectRaw("SUM(CASE WHEN entries.entry_date BETWEEN ? AND ? THEN entries.{$field} ELSE 0 END) AS {$field}", [$from, $to]);
        }
        if (in_array($period, ['Mingguan', 'Bulanan'], true)) {
            // The report total covers only the chosen week/month; disciplinary status is
            // calculated independently from the semester start up to that period.
            $semester = (int) substr($from, 5, 2) >= 7 ? 'Ganjil' : 'Genap';
            [$semesterFrom] = AcademicPeriod::bounds($year, $semester);
            $semesterSubquery = AbsenceEntries::query('semester_entries')
                ->selectRaw('COALESCE(SUM(semester_entries.alpa_jp), 0)')
                ->whereColumn('semester_entries.student_id', 's.id')
                ->where('semester_entries.academic_year', $year)
                ->whereBetween('semester_entries.entry_date', [$semesterFrom, $to]);
            $q->selectSub($semesterSubquery, 'semester_alpa_jp');
        }
        if ($period === 'Tahunan') {
            [$gFrom, $gTo] = AcademicPeriod::bounds($year, 'Ganjil');
            [$eFrom, $eTo] = AcademicPeriod::bounds($year, 'Genap');
            $q->selectRaw('SUM(CASE WHEN entries.entry_date BETWEEN ? AND ? THEN entries.alpa_jp ELSE 0 END) AS alpa_ganjil_jp', [$gFrom, $gTo]);
            $q->selectRaw('SUM(CASE WHEN entries.entry_date BETWEEN ? AND ? THEN entries.alpa_jp ELSE 0 END) AS alpa_genap_jp', [$eFrom, $eTo]);
        }
        $q->groupBy('s.id', 's.nis', 's.full_name');
        if ($action === 'all') return $q->orderBy('s.full_name');
        $filtered = DB::query()->fromSub($q, 'report');
        $columns = $period === 'Tahunan' ? ['alpa_ganjil_jp', 'alpa_genap_jp'] : [$short ? 'semester_alpa_jp' : 'alpa_jp'];
        [$min, $max] = match ($action) {
            'wali' => [Discipline::WARNING_JP, Discipline::BP_JP - 1],
            'bp' => [Discipline::BP_JP, Discipline::PARENTS_JP - 1],
            'parents' => [Discipline::PARENTS_JP, null],
            default => [Discipline::WARNING_JP, null],
        };
        $filtered->where(function ($query) use ($columns, $min, $max) {
            foreach ($columns as $column) {
                $query->orWhere(function ($condition) use ($column, $min, $max) {
                    $condition->where($column, '>=', $min);
                    if ($max !== null) $condition->where($column, '<=', $max);
                });
            }
        });
        return $filtered->orderBy('full_name');
    }

    /** One Monday defines one input week. Monthly grouping uses Monday's month. */
    public static function bounds(string $year, string $period, ?string $week = null, ?string $month = null): array
    {
        if ($period === 'Mingguan') {
            if (!$week || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)) throw new \InvalidArgumentException('Tanggal minggu wajib YYYY-MM-DD.');
            $d = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $week);
            if (!$d || $d->format('Y-m-d') !== $week || !$d->isMonday() || AcademicPeriod::yearForDate($d) !== $year) throw new \InvalidArgumentException('Minggu harus dimulai Senin dalam tahun pelajaran.');
            return [$week, $week];
        }
        if ($period === 'Bulanan') {
            if (!$month || !preg_match('/^\d{4}-\d{2}$/', $month)) throw new \InvalidArgumentException('Bulan wajib YYYY-MM.');
            $d = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');
            if (!$d || $d->format('Y-m') !== $month || AcademicPeriod::yearForDate($d) !== $year) throw new \InvalidArgumentException('Bulan di luar tahun pelajaran.');
            return [$d->startOfMonth()->toDateString(), $d->endOfMonth()->toDateString()];
        }
        return AcademicPeriod::bounds($year, $period);
    }

    public static function studentAlpa(int $studentId, string $year, string $semester): int
    {
        [$from, $to] = AcademicPeriod::bounds($year, $semester);
        return (int) AbsenceEntries::query()
            ->where('student_id', $studentId)->where('academic_year', $year)
            ->whereBetween('entry_date', [$from, $to])->sum('alpa_jp');
    }
}
