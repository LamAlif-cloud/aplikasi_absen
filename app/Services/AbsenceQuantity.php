<?php
namespace App\Services;

/**
 * Semua catatan absensi disimpan sebagai JP bulat, 8 JP = 1 hari.
 * Kelas ini hanya mengonversi angka (tidak mengubah aturan tata tertib).
 */
final class AbsenceQuantity
{
    public const JP_PER_DAY = 8;

    /** Display remaining lesson hours as "jam": 25 JP => 3 hari 1 jam. */
    public static function readable(int $jp): string
    {
        if ($jp < 0) throw new \InvalidArgumentException('Jumlah JP tidak boleh negatif.');
        $days = intdiv($jp, self::JP_PER_DAY);
        $remainder = $jp % self::JP_PER_DAY;
        if ($days === 0) return $remainder.' jam';
        return $days.' hari'.($remainder ? ' '.$remainder.' jam' : '');
    }

    /** Exact because all JP/day fractions are multiples of 0.125. */
    public static function decimalDays(int $jp): string
    {
        if ($jp < 0) throw new \InvalidArgumentException('Jumlah JP tidak boleh negatif.');
        return rtrim(rtrim(number_format($jp / self::JP_PER_DAY, 3, ',', '.'), '0'), ',');
    }

    /**
     * Parse each S / I / A based on a unit chosen explicitly on the import form.
     * The same unit applies to whole and fractional values throughout the file.
     * Separator dot or comma is interpreted as DECIMAL, never a thousands separator.
     * Days must be multiples of 1/8; fractional JP are rejected, never rounded.
     */
    public static function parseFields(array $row, int $maxJp, string $unit = 'jp'): array
    {
        if (!in_array($unit, ['jp', 'days'], true)) throw new \InvalidArgumentException('Pilih satuan JP atau Hari. Mode Campuran tidak didukung.');
        $result = [];
        foreach (['sakit_jp' => 'Sakit', 'izin_jp' => 'Izin', 'alpa_jp' => 'Alpa'] as $key => $label) {
            $raw = trim((string) ($row[$key] ?? ''));
            if ($raw === '') throw new \InvalidArgumentException($label.' kosong; isi 0 jika tidak ada ketidakhadiran.');
            if (!preg_match('/^\d{1,8}(?:[,.]\d{1,3})?$/D', $raw)) {
                throw new \InvalidArgumentException($label.': angka "'.$raw.'" tidak valid (tanpa tanda ribuan, maksimal 3 desimal).');
            }
            $pieces = preg_split('/[,.]/', $raw);
            $whole = (int) $pieces[0];
            $fraction = isset($pieces[1]) ? (int) str_pad($pieces[1], 3, '0') : 0;
            $valueUnit = $unit;
            if ($valueUnit === 'jp') {
                if ($fraction !== 0) {
                    throw new \InvalidArgumentException($label.': '.$raw.' JP bukan bilangan bulat. Jika satuannya HARI, pilih Hari pada formulir impor.');
                }
                $jp = $whole;
            } else {
                // Convert thousandths of a day to JP using integer arithmetic, no float rounding.
                $thousandths = $whole * 1000 + $fraction;
                $jpScaled = $thousandths * self::JP_PER_DAY;
                if ($jpScaled % 1000 === 0) {
                    $jp = intdiv($jpScaled, 1000);
                } else {
                    throw new \InvalidArgumentException($label.': '.$raw.' hari bukan kelipatan 0,125 hari (1 JP).');
                }
            }
            if ($jp > $maxJp) {
                throw new \InvalidArgumentException($label.': '.$raw.($valueUnit === 'days' ? ' hari = '.$jp.' JP' : ' JP').' melebihi batas '.$maxJp.' JP.');
            }
            $result[$key] = $jp;
        }
        if (array_sum($result) > $maxJp) {
            throw new \InvalidArgumentException('Jumlah S+I+A '.array_sum($result).' JP melebihi batas '.$maxJp.' JP.');
        }
        return $result;
    }
}
