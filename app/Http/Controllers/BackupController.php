<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\DatabaseBackup;
use App\Services\DatabaseRestore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class BackupController
{
    public function index(Request $request): View
    {
        $pending = null;
        $id = $request->session()->get('restore_pending_id');
        if (is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id)) {
            $path = $this->pendingPath($id);
            if (is_file($path) && filemtime($path) > time() - 1800) {
                $pending = $request->session()->get('restore_pending_summary');
            }
        }
        return view('settings.backup', [
            'database' => \DB::connection()->getDatabaseName(),
            'tables' => \DB::connection()->getDriverName() === 'mysql' ? \DB::select('SHOW FULL TABLES') : [],
            'restoreEnabled' => app()->environment('local') && \DB::connection()->getDriverName() === 'mysql',
            'pendingRestore' => $pending,
            'recoveryFiles' => $this->recoveryFiles(),
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        if (!Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password administrator tidak cocok. Backup tidak dibuat.']);
        }
        $archive = DatabaseBackup::createArchive();
        AuditLog::create(['user_id' => Auth::id(), 'action' => 'database_backup_download', 'detail' => 'Backup database lengkap diminta oleh administrator', 'ip_address' => $request->ip()]);
        return response()->download($archive, 'Aplikasi_Absensi_Siswa_Backup_'.date('Ymd_His').'.zip', [
            'Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store', 'Pragma' => 'no-cache',
        ])->deleteFileAfterSend(true);
    }

    /** Langkah 1: unggah dan periksa struktur tanpa mengeksekusi SQL. */
    public function preview(Request $request)
    {
        $this->ensureLocal();
        $data = $request->validate([
            'restore_file' => ['required', 'file', 'max:25600'],
        ]);
        try {
            $sql = $this->readBackupUpload($data['restore_file']);
            $summary = DatabaseRestore::inspectAgainstDatabase($sql);
        } catch (\Throwable $e) {
            Log::warning('SQL restore preview rejected', ['reason' => $e->getMessage()]);
            throw ValidationException::withMessages(['restore_file' => 'Backup tidak bisa dipulihkan: '.$e->getMessage()]);
        }
        $id = bin2hex(random_bytes(16));
        $directory = storage_path('app/private/restore_pending');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) abort(500, 'Tidak dapat menyiapkan direktori upload privat.');
        $path = $this->pendingPath($id);
        if (file_put_contents($path, $sql, LOCK_EX) === false) abort(500, 'Gagal menyimpan berkas sementara.');
        @chmod($path, 0600);
        $request->session()->put([
            'restore_pending_id' => $id,
            'restore_pending_hash' => hash('sha256', $sql),
            'restore_pending_summary' => $summary,
            'restore_pending_admin' => (int) $request->user()->id,
        ]);
        return redirect()->route('backup.index')->with('notice', 'Backup diperiksa dan cocok dengan struktur database. BELUM ADA DATA YANG DIUBAH. Konfirmasikan secara terpisah bila ingin memulihkan.');
    }

    /** Langkah 2: backup otomatis, password, konfirmasi eksplisit, baru memulihkan. */
    public function restore(Request $request)
    {
        $this->ensureLocal();
        $input = $request->validate([
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:PULIHKAN DATABASE'],
            'understand_replace' => ['accepted'],
        ]);
        if (!Hash::check($input['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password administrator salah. Tidak ada data yang diubah.']);
        }
        $id = $request->session()->get('restore_pending_id');
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id) || (int) $request->session()->get('restore_pending_admin') !== (int) $request->user()->id) {
            throw ValidationException::withMessages(['restore_file' => 'Pratinjau pemulihan tidak ditemukan. Unggah ulang backup.']);
        }
        $path = $this->pendingPath($id);
        if (!is_file($path) || filemtime($path) < time() - 1800) {
            throw ValidationException::withMessages(['restore_file' => 'Pratinjau kedaluwarsa (30 menit). Unggah ulang backup.']);
        }
        $sql = file_get_contents($path);
        if ($sql === false || !hash_equals((string) $request->session()->get('restore_pending_hash'), hash('sha256', $sql))) {
            throw ValidationException::withMessages(['restore_file' => 'Berkas berubah setelah pratinjau. Unggah ulang.']);
        }
        // Jika pemulihan gagal, DB transaction dirollback; cadangan pra-restore tetap disimpan.
        try {
            DatabaseRestore::inspectAgainstDatabase($sql);
            $backup = DatabaseBackup::createArchive();
            $directory = storage_path('app/private/pre_restore');
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Direktori cadangan privat tidak dapat dibuat.');
            $name = 'pra_restore_'.bin2hex(random_bytes(16)).'.zip';
            if (!rename($backup, $directory.DIRECTORY_SEPARATOR.$name)) throw new \RuntimeException('Gagal menyimpan backup otomatis sebelum restore.');
            DatabaseRestore::restore($sql);
            try {
                AuditLog::create(['user_id' => null, 'action' => 'database_restore', 'detail' => 'Pemulihan backup diselesaikan. Cadangan sebelumnya: '.$name, 'ip_address' => $request->ip()]);
            } catch (\Throwable $auditError) {
                // Audit tidak boleh membuat transaksi restore yang sudah commit terlapor gagal.
                Log::warning('Audit after restore could not be written', ['exception' => $auditError]);
            }
        } catch (\Throwable $e) {
            Log::error('Restore database failed', ['exception' => $e]);
            throw ValidationException::withMessages(['restore_file' => 'Pemulihan dibatalkan karena gagal memproses data. Database transaksi dibatalkan jika belum commit. Simpan backup sebelumnya; rincian teknis ada di log Laravel.']);
        }
        @unlink($path);
        $request->session()->forget(['restore_pending_id', 'restore_pending_hash', 'restore_pending_summary', 'restore_pending_admin']);
        // User yang di-restore bisa berbeda; sesi lama harus ditutup.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('notice', 'Pemulihan database selesai. Masuk kembali menggunakan akun yang tersimpan dalam backup. Backup kondisi sebelum pemulihan disimpan di storage/app/private/pre_restore.');
    }

    /** Unduh cadangan otomatis dari sebelum restore; tidak disediakan untuk pengguna non-admin. */
    public function recovery(string $filename): BinaryFileResponse
    {
        $this->ensureLocal();
        abort_unless(preg_match('/^pra_restore_[a-f0-9]{32}\.zip$/D', $filename), 404);
        $path = storage_path('app/private/pre_restore/'.$filename);
        abort_unless(is_file($path), 404);
        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function recoveryFiles(): array
    {
        $all = glob(storage_path('app/private/pre_restore/pra_restore_*.zip')) ?: [];
        $all = array_filter($all, static fn ($path) => preg_match('/^pra_restore_[a-f0-9]{32}\.zip$/D', basename($path)));
        usort($all, static fn ($left, $right) => filemtime($right) <=> filemtime($left));
        return array_map(static fn ($path) => ['name' => basename($path), 'date' => date('d-m-Y H:i:s', filemtime($path)), 'bytes' => filesize($path)], array_slice($all, 0, 10));
    }

    private function readBackupUpload($file): string
    {
        $name = strtolower($file->getClientOriginalName());
        if (str_ends_with($name, '.sql')) {
            $sql = file_get_contents($file->getRealPath());
        } elseif (str_ends_with($name, '.zip')) {
            $zip = new ZipArchive();
            if ($zip->open($file->getRealPath()) !== true) throw new \RuntimeException('ZIP tidak dapat dibuka.');
            try {
                if ($zip->numFiles > 3) throw new \RuntimeException('ZIP tidak sesuai struktur backup Aplikasi Absensi Siswa.');
                $entry = $zip->statName('lamalif_database.sql');
                if (!$entry || (int) $entry['size'] > DatabaseRestore::MAX_SQL_BYTES) throw new \RuntimeException('File SQL tidak ditemukan atau terlalu besar dalam ZIP.');
                for ($i = 0; $i < $zip->numFiles; ++$i) {
                    $member = $zip->getNameIndex($i);
                    if (!in_array($member, ['lamalif_database.sql', 'PETUNJUK_BACKUP.txt'], true)) throw new \RuntimeException('ZIP mengandung berkas yang tidak sesuai format backup.');
                }
                $sql = $zip->getFromName('lamalif_database.sql');
            } finally { $zip->close(); }
        } else {
            throw new \RuntimeException('File harus .sql atau .zip dari menu Backup Aplikasi Absensi Siswa.');
        }
        if (!is_string($sql) || strlen($sql) === 0 || strlen($sql) > DatabaseRestore::MAX_SQL_BYTES) {
            throw new \RuntimeException('SQL kosong atau melebihi batas 25 MB.');
        }
        return $sql;
    }

    private function ensureLocal(): void
    {
        abort_unless(app()->environment('local') && \DB::connection()->getDriverName() === 'mysql', 403, 'Restore melalui web hanya tersedia di Laragon lokal.');
    }

    private function pendingPath(string $id): string
    {
        return storage_path('app/private/restore_pending/'.$id.'.sql');
    }
}
