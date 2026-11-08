<?php
namespace App\Services;

final class Discipline
{
    public const JP_PER_DAY = 8;
    public const WARNING_JP = 24;
    public const BP_JP = 48;
    public const PARENTS_JP = 72;
    public const FILTERS = [
        'all' => 'Semua siswa',
        'action' => 'Semua yang perlu tindakan (24 JP ke atas)',
        'wali' => 'Peringatan Wali Kelas (24–47 JP)',
        'bp' => 'Penanganan BP (48–71 JP)',
        'parents' => 'Pemanggilan Orang Tua (72 JP ke atas)',
    ];
    public static function level(int $alpaJp): int
    {
        return $alpaJp >= self::PARENTS_JP ? 3 : ($alpaJp >= self::BP_JP ? 2 : ($alpaJp >= self::WARNING_JP ? 1 : 0));
    }
    public static function label(int $alpaJp): string { return self::levelLabel(self::level($alpaJp)); }
    public static function levelLabel(int $level): string
    {
        return match ($level) {
            1 => 'Peringatan Wali Kelas', 2 => 'Penanganan BP', 3 => 'Pemanggilan Orang Tua', default => 'Pemantauan',
        };
    }
    public static function days(int $jp): float { return $jp / self::JP_PER_DAY; }
}
