<?php
namespace Tests\Unit;

use App\Services\LamalifSqlReader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LamalifSqlReaderTest extends TestCase
{
    private const HEADER = "-- Backup LamAlif MySQL; dibuat 2026-10-21\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";

    public function test_reader_accepts_generated_backup_and_decodes_quoted_text_as_data(): void
    {
        $sql = self::HEADER
            . "-- Struktur users\nDROP TABLE IF EXISTS `users`;\nCREATE TABLE `users` (`id` int, `full_name` varchar(100));\n"
            . "INSERT INTO `users` (`id`,`full_name`) VALUES ('1','O\\'Brien; baris\\nbaru');\n"
            . "-- Struktur migrations\nDROP TABLE IF EXISTS `migrations`;\nCREATE TABLE `migrations` (`id` int);\n"
            . "SET FOREIGN_KEY_CHECKS=1;\n";
        $info = LamalifSqlReader::inspect($sql);
        self::assertSame(2, count($info['tables']));
        self::assertSame(1, $info['rows']);
        $statements = iterator_to_array(LamalifSqlReader::statements($sql));
        $item = collectDecodedInsert($statements);
        self::assertSame(['1', "O'Brien; baris\nbaru"], $item['values']);
    }

    public function test_reader_rejects_any_unapproved_sql_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LamalifSqlReader::inspect(self::HEADER."DELETE FROM `students`;\nSET FOREIGN_KEY_CHECKS=1;\n");
    }

    public function test_reader_rejects_old_native_php_backup(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LamalifSqlReader::inspect('-- Backup Rekap Absensi; dibuat ...');
    }
}

function collectDecodedInsert(array $statements): array
{
    foreach ($statements as $statement) {
        $item = LamalifSqlReader::decode($statement);
        if ($item['type'] === 'insert') return $item;
    }
    throw new \RuntimeException('INSERT not found');
}
