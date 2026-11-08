<?php
namespace Tests\Unit;

use App\Services\Discipline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DisciplineTest extends TestCase
{
    public static function thresholds(): array
    {
        return [[0,0], [8,0], [23,0], [24,1], [25,1], [47,1], [48,2], [49,2], [71,2], [72,3], [80,3]];
    }
    #[DataProvider('thresholds')]
    public function test_levels(int $jp, int $expected): void { self::assertSame($expected, Discipline::level($jp)); }
    public function test_conversion(): void { self::assertSame(3.5, Discipline::days(28)); }
}
