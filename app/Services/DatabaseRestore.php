<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Pemulihan hanya DATA pada skema MySQL LamAlif yang sama, tidak mengeksekusi DDL unggahan. */
final class DatabaseRestore
{
    public const MAX_SQL_BYTES = 25 * 1024 * 1024;

    public static function inspectAgainstDatabase(string $sql): array
    {
        self::assertLocalMysql();
        $info = LamalifSqlReader::inspect($sql);
        $pdo = DB::connection()->getPdo();
        $live = [];
        $status = $pdo->query('SHOW TABLE STATUS');
        while ($row = $status->fetch(\PDO::FETCH_ASSOC)) {
            $table = (string) $row['Name'];
            if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) throw new RuntimeException('Nama tabel MySQL tidak didukung.');
            if (($row['Engine'] ?? '') !== 'InnoDB') throw new RuntimeException('Tabel '.$table.' tidak menggunakan InnoDB; rollback aman tidak dapat dijamin.');
            $live[$table] = true;
        }
        $expected = array_keys($live);
        $given = array_keys($info['tables']);
        sort($expected);
        sort($given);
        if ($given !== $expected) {
            throw new RuntimeException('Daftar tabel backup berbeda dengan database saat ini. Gunakan versi skema yang cocok; data belum diubah.');
        }
        foreach ($info['ddls'] as $table => $ddl) {
            $saved = self::canonicalCreateTable($ddl);
            $current = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(\PDO::FETCH_NUM)[1] ?? '';
            if ($saved !== self::canonicalCreateTable($current)) {
                throw new RuntimeException('Struktur tabel '.$table.' berbeda dari backup. Pemulihan dibatalkan sebelum mengubah data.');
            }
        }
        $columnsCache = [];
        foreach (LamalifSqlReader::statements($sql) as $statement) {
            $item = LamalifSqlReader::decode($statement);
            if ($item['type'] !== 'insert') continue;
            $table = $item['table'];
            if (!isset($live[$table])) throw new RuntimeException('Ada tabel yang tidak terdaftar pada database aktif.');
            if (!isset($columnsCache[$table])) {
                $columnsCache[$table] = array_map(static fn ($row) => $row['Field'], $pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(\PDO::FETCH_ASSOC));
                sort($columnsCache[$table]);
            }
            $columns = $item['columns'];
            sort($columns);
            if ($columns !== $columnsCache[$table]) {
                throw new RuntimeException('Kolom data tabel '.$table.' tidak cocok dengan versi skema sekarang.');
            }
        }
        return ['tables' => count($info['tables']), 'rows' => $info['rows'], 'counts' => $info['counts']];
    }

    private static function canonicalCreateTable(string $ddl): string
    {
        $ddl = preg_replace('/\s+AUTO_INCREMENT=\d+\b/i', '', $ddl);
        $ddl = preg_replace('/\s+/', ' ', $ddl);
        return trim($ddl);
    }

    public static function restore(string $sql): array
    {
        // All contents are validated again immediately before the first write.
        $summary = self::inspectAgainstDatabase($sql);
        $pdo = DB::connection()->getPdo();
        $tables = array_keys(LamalifSqlReader::inspect($sql)['tables']);
        $count = 0;
        $begun = false;
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $pdo->beginTransaction();
            $begun = true;
            // DELETE (bukan TRUNCATE) agar operasi tetap transactional di InnoDB.
            foreach ($tables as $table) $pdo->exec('DELETE FROM `'.$table.'`');
            $stmts = [];
            foreach (LamalifSqlReader::statements($sql) as $statement) {
                $item = LamalifSqlReader::decode($statement);
                if ($item['type'] !== 'insert') continue;
                $table = $item['table'];
                $key = $table.'|'.implode(',', $item['columns']);
                if (!isset($stmts[$key])) {
                    $fields = implode(',', array_map(static fn ($column) => '`'.$column.'`', $item['columns']));
                    $params = implode(',', array_fill(0, count($item['values']), '?'));
                    $stmts[$key] = $pdo->prepare('INSERT INTO `'.$table.'` ('.$fields.') VALUES ('.$params.')');
                }
                $stmts[$key]->execute($item['values']);
                ++$count;
            }
            $pdo->commit();
            $begun = false;
        } catch (\Throwable $e) {
            if ($begun && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        if ($count !== $summary['rows']) throw new RuntimeException('Jumlah pemulihan berbeda dari pratinjau. Periksa database sebelum melanjutkan.');
        return $summary;
    }

    private static function assertLocalMysql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') throw new RuntimeException('Restore hanya tersedia pada MySQL.');
        if (!app()->environment('local')) throw new RuntimeException('Pemulihan melalui web hanya diizinkan di lingkungan LOCAL; untuk server online gunakan prosedur pemulihan khusus.');
    }
}
