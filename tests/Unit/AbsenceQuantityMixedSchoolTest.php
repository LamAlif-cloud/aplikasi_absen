<?php
namespace Tests\Unit;

use App\Services\AbsenceQuantity as Quantity;
use PHPUnit\Framework\TestCase;

final class AbsenceQuantityMixedSchoolTest extends TestCase
{
    public function test_whole_and_fractional_days_keep_the_same_unit(): void
    {
        foreach (['12,00' => 96, '11,25' => 90, '12' => 96, '11.25' => 90, '0.125' => 1] as $raw => $expected) {
            $result = Quantity::parseFields(['sakit_jp' => '0', 'izin_jp' => '0', 'alpa_jp' => (string) $raw], 248, 'days');
            self::assertSame($expected, $result['alpa_jp']);
        }
    }

    public function test_zero_decimal_jp_is_still_an_integer(): void
    {
        self::assertSame(['sakit_jp' => 0, 'izin_jp' => 0, 'alpa_jp' => 12], Quantity::parseFields([
            'sakit_jp' => '0', 'izin_jp' => '0', 'alpa_jp' => '12,00',
        ], 248, 'jp'));
    }

    public function test_fractional_jp_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Quantity::parseFields(['sakit_jp' => '0', 'izin_jp' => '0', 'alpa_jp' => '11,25'], 248, 'jp');
    }

    public function test_ambiguous_mixed_unit_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Quantity::parseFields(['sakit_jp' => '0', 'izin_jp' => '0', 'alpa_jp' => '12,00'], 248, 'mixed');
    }

    public function test_fractional_days_are_not_silently_rounded(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Quantity::parseFields(['sakit_jp' => '0', 'izin_jp' => '0', 'alpa_jp' => '0,13'], 248, 'days');
    }
}
