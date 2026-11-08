<?php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Show the actual validation errors and import summary when an Excel test fails. */
    protected function assertImportCounts(int $expectedNew, int $expectedFailed): void
    {
        $summary = session('import_summary');
        $errors = session('errors');
        $validationErrors = $errors instanceof \Illuminate\Support\ViewErrorBag
            ? $errors->all() : [];
        $details = json_encode([
            'ringkasan' => $summary,
            'validasi' => $validationErrors,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        self::assertIsArray($summary, 'Impor tidak memberikan ringkasan. Detail: '.$details);
        self::assertSame($expectedNew, $summary['baru'] ?? null, 'Jumlah data baru tidak sesuai. Detail: '.$details);
        self::assertSame($expectedFailed, $summary['gagal'] ?? null, 'Jumlah data gagal tidak sesuai. Detail: '.$details);
    }
}
