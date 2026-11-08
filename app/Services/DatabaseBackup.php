<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use ZipArchive;

/** Backup SQL MySQL lengkap untuk skema tabel dan isinya; tanpa memakai shell/mysqldump. */
final class DatabaseBackup
{
    public static function createArchive(): string
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Fitur backup membutuhkan database MySQL.');
        }
        $pdo = DB::connection()->getPdo();
        $sql = tempnam(sys_get_temp_dir(), 'lamalif_sql_');
        $zip = tempnam(sys_get_temp_dir(), 'lamalif_zip_');
        if (!$sql || !$zip) throw new RuntimeException('Tidak dapat membuat berkas sementara untuk backup.');
        $file = fopen($sql, 'wb');
        if (!$file) throw new RuntimeException('Tidak dapat menulis file backup.');
        try {
            fwrite($file, "-- Backup LamAlif MySQL; dibuat ".date('c')."\n");
            fwrite($file, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            $tables = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
            foreach ($tables as $row) {
                [$name, $kind] = $row;
                if ($kind !== 'BASE TABLE') continue;
                // Names are retrieved from SHOW TABLES, never from any user input.
                if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name)) throw new RuntimeException('Nama tabel tidak didukung untuk backup.');
                $table = '`'.$name.'`';
                $create = $pdo->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM);
                if (!isset($create[1])) throw new RuntimeException('Gagal membaca struktur tabel '.$name);
                fwrite($file, "\n-- Struktur ".$name."\nDROP TABLE IF EXISTS ".$table.";\n".$create[1].";\n");
                $rows = $pdo->query('SELECT * FROM '.$table);
                while ($record = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $fields = array_keys($record);
                    $columns = implode(',', array_map(fn ($c) => '`'.$c.'`', $fields));
                    $values = implode(',', array_map(static function ($v) use ($pdo): string {
                        return $v === null ? 'NULL' : $pdo->quote((string) $v);
                    }, array_values($record)));
                    fwrite($file, 'INSERT INTO '.$table.' ('.$columns.') VALUES ('.$values.");\n");
                }
            }
            fwrite($file, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($file);
            $archive = new ZipArchive();
            if ($archive->open($zip, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Tidak dapat membuat arsip backup ZIP.');
            $archive->addFile($sql, 'lamalif_database.sql');
            $archive->addFromString('PETUNJUK_BACKUP.txt',
                "Backup Aplikasi Absensi Siswa MySQL: struktur tabel + data.\n".
                "Rahasiakan file ZIP karena berisi data siswa dan hash password akun.\n".
                "Jangan impor ke database yang masih berisi data tanpa membuat cadangan terlebih dahulu.\n".
                "APP_KEY / file .env dan berkas logo tidak disertakan. Simpan secara terpisah.\n".
                "Untuk pemulihan pada Laragon lokal, buka menu Backup & Restore; unggah ZIP atau SQL, periksa pratinjau, lalu konfirmasi. Restore membutuhkan skema yang sama dan password admin. Backup lama di server online harus dipulihkan melalui administrator server.\n");
            if (!$archive->close()) throw new RuntimeException('Gagal menutup file ZIP backup.');
            return $zip;
        } catch (\Throwable $e) {
            if (is_resource($file)) fclose($file);
            @unlink($zip);
            throw $e;
        } finally {
            @unlink($sql);
        }
    }
}
