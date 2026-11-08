<?php
namespace Tests\Unit;

use App\Services\AcademicPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AcademicPeriodTest extends TestCase
{
    public function test_year_boundary(): void
    {
        self::assertSame('2026/2027', AcademicPeriod::yearForDate(CarbonImmutable::parse('2026-07-01')));
        self::assertSame('2025/2026', AcademicPeriod::yearForDate(CarbonImmutable::parse('2026-06-30')));
    }
    public function test_semester_ranges(): void
    {
        self::assertSame(['2026-07-01', '2026-12-31'], AcademicPeriod::bounds('2026/2027', 'Ganjil'));
        self::assertSame(['2027-01-01', '2027-06-30'], AcademicPeriod::bounds('2026/2027', 'Genap'));
        self::assertSame(['2026-07-01', '2027-06-30'], AcademicPeriod::bounds('2026/2027', 'Tahunan'));
    }
}
