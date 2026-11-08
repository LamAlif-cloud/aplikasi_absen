<?php
namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** XLSX only for outputs. Treat all imported fields as data, never as PHP/SQL/Excel formulas. */
final class ExcelWorkbook
{
    /** @param list<string> $headers @param iterable<array<int,mixed>> $rows */
    public static function download(string $name, array $headers, iterable $rows, string $title, string $description = ''): StreamedResponse
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Data');
        $n = count($headers);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);
        $sheet->mergeCells('A1:'.$lastCol.'1');
        $sheet->setCellValueExplicit('A1', $title, DataType::TYPE_STRING);
        $sheet->mergeCells('A2:'.$lastCol.'2');
        $sheet->setCellValueExplicit('A2', $description, DataType::TYPE_STRING);
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setSize(16)->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:'.$lastCol.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('164D47');
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getRowDimension(2)->setRowHeight(28);
        $sheet->getStyle('A2:'.$lastCol.'2')->getFont()->getColor()->setRGB('546F69');
        foreach ($headers as $col => $label) self::writeCell($sheet, $col + 1, 4, $label, false);
        $sheet->getStyle('A4:'.$lastCol.'4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A4:'.$lastCol.'4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('287267');
        $sheet->getRowDimension(4)->setRowHeight(25);
        $i = 5;
        foreach ($rows as $values) {
            if ($i > 50004) throw new \RuntimeException('Ekspor melebihi 50.000 baris. Gunakan filter yang lebih sempit.');
            foreach (array_values($values) as $col => $value) {
                if ($col < $n) self::writeCell($sheet, $col + 1, $i, $value, is_int($value) || is_float($value));
            }
            if ($i % 2 === 0) {
                $sheet->getStyle('A'.$i.':'.$lastCol.$i)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F8F6');
            }
            ++$i;
        }
        // NIS dan NISN sebaiknya berupa TEKS agar angka nol depan tidak hilang saat diisi.
        foreach ($headers as $column => $header) {
            if (in_array($header, ['NIS', 'NISN'], true)) {
                $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column + 1);
                $sheet->getStyle($letter.'5:'.$letter.'5004')->getNumberFormat()->setFormatCode('@');
            }
        }
        $sheet->freezePane('A5');
        $sheet->setAutoFilter('A4:'.$lastCol.max(4, $i - 1));
        for ($c = 1; $c <= $n; ++$c) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $preferred = match ($headers[$c - 1]) {
                'NOMOR' => 10, 'NIS' => 20, 'NISN' => 21, 'NAMA' => 37,
                'JENIS KELAMIN' => 19, 'KELAS' => 16, 'WALI KELAS' => 37,
                'STATUS PONDOK' => 19, 'NAMA PONDOK' => 38,
                default => min(38, max(18, mb_strlen($headers[$c - 1]) + 6)),
            };
            $sheet->getColumnDimension($letter)->setWidth($preferred);
        }
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(4, 4);
        $sheet->getStyle('A4:'.$lastCol.max(4, $i - 1))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $name);
        return response()->streamDownload(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store']);
    }

    private static function writeCell($sheet, int $col, int $row, mixed $value, bool $numeric): void
    {
        $cell = $sheet->getCell([ $col, $row ]);
        if ($numeric) $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
        else $cell->setValueExplicit((string) ($value ?? ''), DataType::TYPE_STRING);
    }

    /** Reads first worksheet, skipping empty lines, preserving strings (including NIS/NISN zeros). */
    public static function read(string $path, array $required): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        if (method_exists($reader, 'setReadEmptyCells')) $reader->setReadEmptyCells(false);
        $book = $reader->load($path);
        try {
            $sheet = $book->getSheet(0);
            $maxRow = $sheet->getHighestDataRow();
            $maxColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            if ($maxRow > 5001) throw new \InvalidArgumentException('Maksimum 5.000 baris data per berkas.');
            if ($maxColumn > 24) throw new \InvalidArgumentException('Jumlah kolom melebihi batas 24. Gunakan template resmi.');
            if ($maxRow < 1) throw new \InvalidArgumentException('Berkas Excel kosong.');
            // Standard worksheet accepts headers on row 1; our formatted templates use row 4.
            $headerRow = 1;
            $headers = self::readHeader($sheet, 1, $maxColumn);
            if (count(array_intersect($required, $headers)) !== count($required) && $maxRow >= 4) {
                $candidate = self::readHeader($sheet, 4, $maxColumn);
                if (count(array_intersect($required, $candidate)) === count($required)) {
                    $headerRow = 4;
                    $headers = $candidate;
                }
            }
            if (count(array_unique($headers)) !== count($headers)) throw new \InvalidArgumentException('Nama kolom Excel ganda.');
            foreach ($required as $name) if (!in_array($name, $headers, true)) throw new \InvalidArgumentException('Kolom wajib tidak ditemukan: '.$name);
            $rows = [];
            for ($row = $headerRow + 1; $row <= $maxRow; ++$row) {
                $values = [];
                $rowError = null;
                for ($col = 1; $col <= $maxColumn; ++$col) {
                    $cell = $sheet->getCell([$col, $row]);
                    $raw = $cell->getValue();
                    if ($cell->isFormula()) {
                        $rowError = 'Baris '.$row.': berisi rumus Excel. Ubah rumus menjadi nilai biasa.';
                        $values[] = '';
                        continue;
                    }
                    $header = $headers[$col - 1] ?? '';
                    if ($raw !== null && is_numeric($raw) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                        // Excel stores dates as serial numbers. Preserve the intended monthly/weekly format.
                        $dateFormat = $header === 'month' ? 'Y-m' : 'Y-m-d';
                        $values[] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw)->format($dateFormat);
                    } elseif ($raw === null) {
                        $values[] = '';
                    } elseif (is_int($raw) || is_float($raw)) {
                        // IMPORTANT: getFormattedValue() changes accounting zero into a dash,
                        // and can turn integer 4 into '4.00'. Such displays MUST NOT be parsed as JP.
                        // Only IDs use display formatting to preserve custom leading zeros.
                        $values[] = in_array($header, ['nis', 'nisn'], true)
                            ? trim((string) $cell->getFormattedValue())
                            : trim((string) $raw);
                    } else {
                        $values[] = trim((string) $raw);
                    }
                }
                $rows[] = ['line' => $row, 'data' => array_combine($headers, $values), 'empty' => count(array_filter($values, fn ($v) => $v !== '')) === 0 && $rowError === null, 'error' => $rowError];
            }
            return $rows;
        } finally { $book->disconnectWorksheets(); }
    }
    /** Menerima template berbahasa Indonesia serta header teknis versi terdahulu. */
    private static function canonicalHeader(string $name): string
    {
        $key = mb_strtolower(trim(str_replace("\xC2\xA0", ' ', $name)));
        $key = preg_replace('/\s+/u', ' ', $key);
        return match ($key) {
            'nomor', 'no', 'no.', 'no urut', 'nomor urut' => 'number',
            'nis' => 'nis',
            'nisn' => 'nisn',
            'nama', 'nama siswa', 'nama lengkap', 'full_name' => 'full_name',
            'jenis kelamin', 'jenis_kelamin', 'jk', 'l/p', 'gender' => 'gender',
            'kelas', 'nama kelas', 'class_name' => 'class_name',
            'status pondok', 'status mondok', 'boarding_status' => 'boarding_status',
            'nama pondok', 'pondok', 'pesantren', 'nama pesantren', 'boarding_school' => 'boarding_school',
            'wali kelas', 'nama wali kelas', 'homeroom_teacher' => 'homeroom_teacher',
            'minggu dimulai', 'minggu dimulai (senin)', 'tanggal minggu', 'week_start' => 'week_start',
            'alpa jp', 'alpa_jp' => 'alpa_jp',
            'izin jp', 'izin_jp' => 'izin_jp',
            'sakit jp', 'sakit_jp' => 'sakit_jp',
            'bulan', 'month' => 'month',
            'catatan', 'note' => 'note',
            default => $key,
        };
    }

    private static function readHeader($sheet, int $row, int $maxColumn): array
    {
        $headers = [];
        for ($col = 1; $col <= $maxColumn; $col++) {
            $value = trim((string) $sheet->getCell([$col, $row])->getFormattedValue());
            $headers[] = self::canonicalHeader($value);
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        return $headers;
    }

}
