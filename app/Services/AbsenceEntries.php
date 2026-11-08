<?php
namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Unified JP flow for weekly, monthly and verified opening balances. */
final class AbsenceEntries
{
    public static function query(string $alias = 'entries'): Builder
    {
        $weekly = DB::table('weekly_absence')->select([
            'student_id', 'class_id', 'academic_year', DB::raw('week_start as entry_date'),
            'alpa_jp', 'izin_jp', 'sakit_jp', DB::raw("'Mingguan' as entry_mode"),
        ]);
        $monthly = DB::table('monthly_absence')->select([
            'student_id', 'class_id', 'academic_year', DB::raw('month_start as entry_date'),
            'alpa_jp', 'izin_jp', 'sakit_jp', DB::raw("'Bulanan' as entry_mode"),
        ]);
        $opening = DB::table('opening_absence')->select([
            'student_id', 'class_id', 'academic_year', DB::raw('as_of_date as entry_date'),
            'alpa_jp', 'izin_jp', 'sakit_jp', DB::raw("'Saldo Awal' as entry_mode"),
        ]);
        return DB::query()->fromSub($weekly->unionAll($monthly)->unionAll($opening), $alias);
    }

    /** Prevent overlap with all JP already covered by an approved opening balance. */
    public static function ensureMonthAvailable(int $studentId, string $year, string $month, string $incomingMode): void
    {
        $first = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');
        if (!$first || $first->format('Y-m') !== $month || AcademicPeriod::yearForDate($first) !== $year) {
            throw ValidationException::withMessages(['month' => 'Bulan tidak sesuai tahun pelajaran.']);
        }
        $opening = DB::table('opening_absence')
            ->where('student_id', $studentId)->where('academic_year', $year)
            ->whereDate('as_of_date', '>=', $first->toDateString())
            ->whereDate('as_of_date', '<=', $first->month >= 7
                ? sprintf('%04d-12-31', (int) substr($year, 0, 4))
                : sprintf('%04d-06-30', (int) substr($year, 0, 4) + 1))
            ->exists();
        if ($opening) {
            throw ValidationException::withMessages(['rows' => 'Bulan '.$month.' sudah tercakup dalam Saldo Awal Semester yang diverifikasi. Jangan input ulang; lanjutkan ke bulan setelah tanggal saldo awal.']);
        }
        if ($incomingMode === 'Bulanan') {
            $conflict = DB::table('weekly_absence')->where('student_id', $studentId)->where('academic_year', $year)
                ->whereBetween('week_start', [$first->startOfMonth()->toDateString(), $first->endOfMonth()->toDateString()])->exists();
        } else {
            $conflict = DB::table('monthly_absence')->where('student_id', $studentId)->where('academic_year', $year)
                ->whereDate('month_start', $first->toDateString())->exists();
        }
        if ($conflict) {
            throw ValidationException::withMessages(['rows' => 'Data siswa sudah diisi secara '.($incomingMode === 'Bulanan' ? 'mingguan' : 'bulanan').' pada '.$month.'. Data tidak boleh terhitung dua kali.']);
        }
    }
}
