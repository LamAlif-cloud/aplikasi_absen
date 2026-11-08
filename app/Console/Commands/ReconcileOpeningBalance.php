<?php
namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** One-time repair of the verified Jul–Oct 2026 opening balance; dry-run is the default. */
final class ReconcileOpeningBalance extends Command
{
    protected $signature = 'madrasah:saldo-awal-audit
        {--apply : Apply the fully validated one-time conversion}
        {--confirm= : Required phrase PINDAHKAN_SALDO_149}
        {--backup-verified : Administrator has downloaded and preserved a recent complete SQL backup}';
    protected $description = 'Audit 149 verified Jul–Oct 2026 absences and optionally migrate old October totals to an opening balance';

    public function handle(): int
    {
        if (!app()->environment('local') || DB::getDriverName() !== 'mysql') {
            $this->error('Perintah ini hanya untuk Laravel lokal dengan MySQL. Jangan gunakan pada server online.');
            return self::FAILURE;
        }
        $path = base_path('database/reconciliation/saldo_awal_2026_2027.json');
        $manifest = json_decode((string) @file_get_contents($path), true);
        if (!is_array($manifest) || count($manifest['entries'] ?? []) !== 149
            || ($manifest['academic_year'] ?? '') !== '2026/2027'
            || ($manifest['as_of_date'] ?? '') !== '2026-10-31'
            || ($manifest['legacy_month'] ?? '') !== '2026-10-01') {
            $this->error('Manifest perbandingan tidak valid; tidak ada data yang diubah.');
            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        if ($apply && ($this->option('confirm') !== 'PINDAHKAN_SALDO_149' || !$this->option('backup-verified'))) {
            $this->error('Butuh --confirm=PINDAHKAN_SALDO_149 DAN --backup-verified, setelah Anda menyimpan backup terbaru.');
            return self::FAILURE;
        }
        try {
            $report = DB::transaction(function () use ($manifest, $apply): array {
                // All preconditions are rechecked while the affected rows are locked.
                $report = $this->audit($manifest, true);
                if ($report['problems']) {
                    throw new \RuntimeException('Audit gagal: '.implode(' | ', array_slice($report['problems'], 0, 6)));
                }
                if ($apply) {
                    $now = now();
                    foreach ($manifest['entries'] as $entry) {
                        DB::table('opening_absence')->insert([
                            'student_id' => $entry['student_id'],
                            'class_id' => $report['class_ids'][$entry['student_id']],
                            'academic_year' => '2026/2027', 'semester' => 'Ganjil',
                            'as_of_date' => '2026-10-31',
                            'sakit_jp' => (int) $entry['correct']['sakit_jp'],
                            'izin_jp' => (int) $entry['correct']['izin_jp'],
                            'alpa_jp' => (int) $entry['correct']['alpa_jp'],
                            'note' => 'SALDO AWAL Juli–Oktober 2026 dari JP sumber terverifikasi; bukan absensi Oktober saja',
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                        $deleted = DB::table('monthly_absence')
                            ->where('id', $report['monthly_ids'][$entry['student_id']])
                            ->where('student_id', $entry['student_id'])
                            ->where('academic_year', '2026/2027')->whereDate('month_start', '2026-10-01')
                            ->delete();
                        if ($deleted !== 1) throw new \RuntimeException('Record lama berubah pada saat koreksi. Rollback seluruh transaksi.');
                    }
                    AuditLog::create([
                        'user_id' => null, 'action' => 'saldo_awal_2026_rekonsiliasi',
                        'detail' => 'Memindahkan 149 rekap gabungan Oktober menjadi saldo awal Ganjil Juli–Oktober 2026; 121 transaksi berbeda dikoreksi berdasarkan JP sumber.',
                        'ip_address' => null,
                    ]);
                }
                return $report;
            }, 1);
        } catch (\Throwable $e) {
            $this->error('TIDAK ADA PERUBAHAN / ROLLBACK: '.$e->getMessage());
            return self::FAILURE;
        }
        $this->info('Data dicocokkan: '.$report['checked'].' siswa; saldo awal 2026/2027 Ganjil.');
        $this->line('Perbedaan angka ditemukan: '.$report['mismatched'].' siswa; risiko salah tahap tata tertib: '.$report['level_mismatches'].' siswa.');
        $this->table(['Jenis', 'Database lama (JP)', 'Sumber benar (JP)'], [
            ['Sakit', $report['old'][0], $report['correct'][0]],
            ['Izin', $report['old'][1], $report['correct'][1]],
            ['Alpa', $report['old'][2], $report['correct'][2]],
        ]);
        if (!$apply) {
            $this->warn('MODE PEMERIKSAAN: database tidak diubah. Pastikan cocok dengan workbook audit sebelum menjalankan perbaikan.');
        } else {
            $this->info('BERHASIL: 149 saldo awal dibuat, 149 transaksi Oktober gabungan yang salah dihapus secara atomik.');
            $this->warn('Cek Rekap Ganjil, Surat, Dashboard, PDF, Ekspor Excel. Buat backup baru setelah semua hasil sesuai.');
        }
        return self::SUCCESS;
    }

    private function audit(array $manifest, bool $lock): array
    {
        $report = ['checked' => 0, 'mismatched' => 0, 'level_mismatches' => 0,
            'old' => [0, 0, 0], 'correct' => [0, 0, 0], 'problems' => [],
            'class_ids' => [], 'monthly_ids' => []];
        $ids = array_column($manifest['entries'], 'student_id');
        if (count($ids) !== count(array_unique($ids))) $report['problems'][] = 'ID siswa manifest duplikat.';
        $query = DB::table('monthly_absence')->where('academic_year', '2026/2027')->whereDate('month_start', '2026-10-01');
        if ($query->count() !== 149) $report['problems'][] = 'Jumlah transaksi Oktober aktif bukan 149; ada perubahan sejak backup, hentikan koreksi.';
        if (DB::table('opening_absence')->where('academic_year', '2026/2027')->where('semester', 'Ganjil')->exists()) {
            $report['problems'][] = 'Saldo awal Ganjil sudah ada. Jangan pindahkan dua kali.';
        }
        $classIds = DB::table('classes')->where('academic_year', '2026/2027')->pluck('id', 'class_name')->all();
        foreach ($manifest['entries'] as $item) {
            $id = (int) $item['student_id'];
            $cname = $item['class_name'];
            $cid = (int) ($classIds[$cname] ?? 0);
            if (!$cid) { $report['problems'][] = 'Kelas '.$cname.' tidak ada.'; continue; }
            $sq = DB::table('students')->where('id', $id);
            if ($lock) $sq->lockForUpdate();
            $student = $sq->first();
            if (!$student || (int) $student->class_id !== $cid
                || mb_strtoupper(trim((string) $student->full_name)) !== mb_strtoupper(trim((string) $item['full_name']))) {
                $report['problems'][] = 'Identitas/kelas siswa ID '.$id.' telah berubah; dilarang menimpa.';
                continue;
            }
            $rq = DB::table('monthly_absence')->where('student_id', $id)->where('academic_year', '2026/2027')->whereDate('month_start', '2026-10-01');
            if ($lock) $rq->lockForUpdate();
            $record = $rq->first();
            if (!$record || (int) $record->class_id !== $cid) {
                $report['problems'][] = 'Record Oktober siswa ID '.$id.' hilang atau kelas berbeda.';
                continue;
            }
            $old = array_map('intval', [(int) $record->sakit_jp, (int) $record->izin_jp, (int) $record->alpa_jp]);
            $was = [(int) $item['old']['sakit_jp'], (int) $item['old']['izin_jp'], (int) $item['old']['alpa_jp']];
            $correct = [(int) $item['correct']['sakit_jp'], (int) $item['correct']['izin_jp'], (int) $item['correct']['alpa_jp']];
            if ($old !== $was) {
                $report['problems'][] = 'JP siswa ID '.$id.' sudah berubah sejak backup. Koreksi harus dihentikan.';
                continue;
            }
            if (DB::table('weekly_absence')->where('student_id', $id)->where('academic_year', '2026/2027')->whereBetween('week_start', ['2026-07-01', '2026-10-31'])->exists()
                || DB::table('monthly_absence')->where('student_id', $id)->where('academic_year', '2026/2027')->whereBetween('month_start', ['2026-07-01', '2026-09-01'])->exists()) {
                $report['problems'][] = 'Siswa ID '.$id.' memiliki catatan lain dalam cakupan saldo awal; dapat terhitung ganda.';
                continue;
            }
            $report['checked']++;
            $report['monthly_ids'][$id] = (int) $record->id;
            $report['class_ids'][$id] = $cid;
            if ($old !== $correct) $report['mismatched']++;
            $oldLevel = $this->level($old[2]);
            $correctLevel = $this->level($correct[2]);
            if ($oldLevel !== $correctLevel) $report['level_mismatches']++;
            foreach ([0, 1, 2] as $i) { $report['old'][$i] += $old[$i]; $report['correct'][$i] += $correct[$i]; }
        }
        if ($report['checked'] !== 149) $report['problems'][] = 'Hanya '.$report['checked'].' dari 149 siswa cocok.';
        if ($report['mismatched'] !== 121 || $report['level_mismatches'] !== 10) {
            $report['problems'][] = 'Jumlah perbedaan tidak sama dengan hasil audit 121 dan 10. Data mungkin sudah berubah.';
        }
        if ($report['correct'] !== [4786, 830, 2736] || $report['old'] !== [663, 291, 2043]) {
            $report['problems'][] = 'Checksum total JP tidak cocok dengan audit sumber.';
        }
        return $report;
    }

    private function level(int $alpa): int
    {
        return $alpa >= 72 ? 3 : ($alpa >= 48 ? 2 : ($alpa >= 24 ? 1 : 0));
    }
}
