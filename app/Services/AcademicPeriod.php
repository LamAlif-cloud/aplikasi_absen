<?php
namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class AcademicPeriod
{
    public static function yearForDate(CarbonImmutable $date): string
    {
        $start = $date->month >= 7 ? $date->year : $date->year - 1;
        return $start.'/'.($start + 1);
    }

    /** Valid Monday inside the selected school year, even for past/future years. */
    public static function defaultMondayForYear(string $year): string
    {
        $current = CarbonImmutable::today()->startOfWeek();
        if (self::yearForDate($current) === $year) return $current->toDateString();
        $first = CarbonImmutable::create((int) substr($year, 0, 4), 7, 1);
        while (!$first->isMonday()) $first = $first->addDay();
        return $first->toDateString();
    }

    public static function defaultMonthForYear(string $year): string
    {
        return self::currentYear() === $year ? CarbonImmutable::today()->format('Y-m') : substr($year, 0, 4).'-07';
    }

    public static function currentYear(): string { return self::yearForDate(CarbonImmutable::now()); }
    public static function currentSemester(): string { return CarbonImmutable::now()->month >= 7 ? 'Ganjil' : 'Genap'; }

    public static function isValidYear(string $year): bool
    {
        if (!preg_match('/^([0-9]{4})\/([0-9]{4})$/', $year, $m)) return false;
        return (int)$m[2] === (int)$m[1] + 1;
    }

    /** Semester defined by the Monday week_start date; never by entered/updated timestamp. */
    public static function bounds(string $year, string $period): array
    {
        if (!self::isValidYear($year)) throw ValidationException::withMessages(['academic_year' => 'Format tahun pelajaran harus YYYY/YYYY berurutan.']);
        $y = (int) substr($year, 0, 4);
        return match ($period) {
            'Ganjil' => [sprintf('%04d-07-01', $y), sprintf('%04d-12-31', $y)],
            'Genap' => [sprintf('%04d-01-01', $y + 1), sprintf('%04d-06-30', $y + 1)],
            'Tahunan' => [sprintf('%04d-07-01', $y), sprintf('%04d-06-30', $y + 1)],
            default => throw ValidationException::withMessages(['semester' => 'Periode tidak dikenal.']),
        };
    }
    public static function semesterForWeek(CarbonImmutable $week): string { return $week->month >= 7 ? 'Ganjil' : 'Genap'; }
}
