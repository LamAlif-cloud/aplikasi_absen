<?php
namespace App\Services;

use InvalidArgumentException;

/**
 * Parser BUKAN executor SQL. Hanya memahami format backup yang dibuat LamAlif.
 * Semua baris data kelak dimasukkan kembali memakai prepared statements.
 */
final class LamalifSqlReader
{
    public static function inspect(string $sql): array
    {
        if (!str_starts_with(ltrim($sql, "\xEF\xBB\xBF \t\r\n"), '-- Backup LamAlif MySQL;')) {
            throw new InvalidArgumentException('File ini bukan backup SQL yang dibuat LamAlif.');
        }
        $tables = [];
        $ddls = [];
        $counts = [];
        $total = 0;
        $sawDisable = false;
        $sawEnable = false;
        foreach (self::statements($sql) as $statement) {
            $item = self::decode($statement);
            if ($item['type'] === 'create') {
                $table = $item['table'];
                if (isset($tables[$table])) throw new InvalidArgumentException('Struktur tabel '.$table.' terdapat lebih dari sekali.');
                $tables[$table] = true;
                $ddls[$table] = $statement;
                $counts[$table] = 0;
            } elseif ($item['type'] === 'insert') {
                if (!isset($tables[$item['table']])) throw new InvalidArgumentException('Data tabel tidak didahului struktur tabel: '.$item['table']);
                $counts[$item['table']]++;
                if (++$total > 250000) throw new InvalidArgumentException('Jumlah baris melebihi batas pemulihan 250.000.');
            } elseif ($item['type'] === 'fk_off') {
                $sawDisable = true;
            } elseif ($item['type'] === 'fk_on') {
                $sawEnable = true;
            }
        }
        if (!$tables || !$sawDisable || !$sawEnable || !isset($tables['users']) || !isset($tables['migrations'])) {
            throw new InvalidArgumentException('Backup tidak lengkap atau tidak memuat struktur penting LamAlif.');
        }
        return ['tables' => $tables, 'ddls' => $ddls, 'counts' => $counts, 'rows' => $total];
    }

    public static function decode(string $statement): array
    {
        if (preg_match('/\ASET\s+NAMES\s+utf8mb4\z/i', $statement)) return ['type' => 'encoding'];
        if (preg_match('/\ASET\s+FOREIGN_KEY_CHECKS\s*=\s*0\z/i', $statement)) return ['type' => 'fk_off'];
        if (preg_match('/\ASET\s+FOREIGN_KEY_CHECKS\s*=\s*1\z/i', $statement)) return ['type' => 'fk_on'];
        if (preg_match('/\ADROP TABLE IF EXISTS `([a-zA-Z0-9_]+)`\z/i', $statement, $m)) return ['type' => 'drop', 'table' => $m[1]];
        if (preg_match('/\ACREATE TABLE `([a-zA-Z0-9_]+)`\s*\(/is', $statement, $m)) return ['type' => 'create', 'table' => $m[1]];
        if (preg_match('/\AINSERT INTO `([a-zA-Z0-9_]+)`\s*\((`[a-zA-Z0-9_]+`(?:\s*,\s*`[a-zA-Z0-9_]+`)*)\)\s*VALUES\s*\((.*)\)\z/is', $statement, $m)) {
            $columns = array_map(static fn ($s) => trim($s, " `\t\r\n"), explode(',', $m[2]));
            if (count(array_unique($columns)) !== count($columns)) throw new InvalidArgumentException('Kolom ganda pada file backup.');
            $values = self::parseValues($m[3]);
            if (count($values) !== count($columns)) throw new InvalidArgumentException('Jumlah kolom dan nilai SQL berbeda.');
            return ['type' => 'insert', 'table' => $m[1], 'columns' => $columns, 'values' => $values];
        }
        throw new InvalidArgumentException('Backup berisi perintah SQL di luar format yang diizinkan.');
    }

    /** Memisah perintah berdasarkan titik-koma di LUAR string; komentar dibuang. */
    public static function statements(string $sql): \Generator
    {
        if (str_starts_with($sql, "\xEF\xBB\xBF")) $sql = substr($sql, 3);
        $buffer = '';
        $quote = null;
        $lineStart = true;
        $size = strlen($sql);
        for ($i = 0; $i < $size; ++$i) {
            $ch = $sql[$i];
            if ($quote === null && $lineStart && $ch === '-' && substr($sql, $i, 3) === '-- ') {
                while ($i < $size && $sql[$i] !== "\n") ++$i;
                $lineStart = true;
                $buffer .= ' ';
                continue;
            }
            if ($quote !== null) {
                $buffer .= $ch;
                if ($quote === "'" && $ch === '\\' && $i + 1 < $size) {
                    $buffer .= $sql[++$i];
                } elseif ($ch === $quote) {
                    if ($i + 1 < $size && $sql[$i + 1] === $quote) {
                        $buffer .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }
            } elseif ($ch === "'" || $ch === '`') {
                $quote = $ch;
                $buffer .= $ch;
            } elseif ($ch === ';') {
                $statement = trim($buffer);
                if ($statement !== '') yield $statement;
                $buffer = '';
            } else {
                $buffer .= $ch;
            }
            if ($ch === "\n") $lineStart = true;
            elseif ($lineStart && !ctype_space($ch)) $lineStart = false;
        }
        if ($quote !== null || trim($buffer) !== '') {
            throw new InvalidArgumentException('Backup SQL terpotong atau perintah terakhir tidak diakhiri titik-koma.');
        }
    }

    /** Nilai dibaca sebagai data murni; tidak pernah dievaluasi sebagai SQL. */
    private static function parseValues(string $source): array
    {
        $values = [];
        $i = 0;
        $length = strlen($source);
        while (true) {
            while ($i < $length && ctype_space($source[$i])) ++$i;
            if ($i >= $length) break;
            if (substr_compare($source, 'NULL', $i, 4, true) === 0) {
                $i += 4;
                $value = null;
            } elseif ($source[$i] === "'") {
                ++$i;
                $value = '';
                $closed = false;
                while ($i < $length) {
                    $ch = $source[$i++];
                    if ($ch === '\\') {
                        if ($i >= $length) throw new InvalidArgumentException('Escape SQL tidak lengkap.');
                        $escape = $source[$i++];
                        $value .= match ($escape) {
                            '0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'Z' => "\x1A",
                            "'" => "'", '"' => '"', '\\' => '\\',
                            default => throw new InvalidArgumentException('Jenis escape SQL tidak didukung.'),
                        };
                        continue;
                    }
                    if ($ch === "'") {
                        if ($i < $length && $source[$i] === "'") { $value .= "'"; ++$i; continue; }
                        $closed = true;
                        break;
                    }
                    $value .= $ch;
                }
                if (!$closed) throw new InvalidArgumentException('String SQL tidak ditutup.');
            } else {
                throw new InvalidArgumentException('Backup memiliki nilai SQL yang bukan string atau NULL.');
            }
            $values[] = $value;
            while ($i < $length && ctype_space($source[$i])) ++$i;
            if ($i >= $length) break;
            if ($source[$i] !== ',') throw new InvalidArgumentException('Pemisah data SQL tidak valid.');
            ++$i;
            if ($i >= $length) throw new InvalidArgumentException('Data SQL diakhiri koma.');
        }
        return $values;
    }
}
