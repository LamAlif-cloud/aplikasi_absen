<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

final class YearOptions
{
    public static function all(): array
    {
        $values = array_merge(
            DB::table('classes')->distinct()->pluck('academic_year')->all(),
            DB::table('weekly_absence')->distinct()->pluck('academic_year')
                ->merge(DB::table('monthly_absence')->distinct()->pluck('academic_year'))
                ->merge(DB::table('opening_absence')->distinct()->pluck('academic_year'))->all(),
            [AcademicPeriod::currentYear()]
        );
        $valid = array_values(array_unique(array_filter($values, fn ($v) => is_string($v) && AcademicPeriod::isValidYear($v))));
        rsort($valid);
        return $valid;
    }

    /** Explicit year wins; otherwise prefer a year with enrolled students over an empty current year. */
    public static function selected(mixed $requested): string
    {
        if (is_string($requested) && AcademicPeriod::isValidYear($requested)) return $requested;
        $year = AcademicPeriod::currentYear();
        if (DB::table('students as s')->join('classes as c', 'c.id', '=', 's.class_id')->where('c.academic_year', $year)->exists()) return $year;
        $withStudents = DB::table('students as s')->join('classes as c', 'c.id', '=', 's.class_id')
            ->where('s.active', true)->orderByDesc('c.academic_year')->value('c.academic_year');
        if (is_string($withStudents) && AcademicPeriod::isValidYear($withStudents)) return $withStudents;
        if (DB::table('classes')->where('academic_year', $year)->exists()) return $year;
        $withClasses = DB::table('classes')->orderByDesc('academic_year')->value('academic_year');
        return is_string($withClasses) && AcademicPeriod::isValidYear($withClasses) ? $withClasses : $year;
    }
}
