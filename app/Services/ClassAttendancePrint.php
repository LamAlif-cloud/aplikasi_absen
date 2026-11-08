<?php
namespace App\Services;

use App\Models\Classroom;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Read-only dokumen penyerahan rekap JP operator kepada wali kelas. */
final class ClassAttendancePrint
{
    /** @param array<string,mixed> $context Disaring oleh ReportController::context() */
    public static function prepare(array $context): array
    {
        $year = $context['year'];
        $period = $context['period'];
        $classes = $context['classes'];
        $selected = $context['classId'] ? $classes->where('id', $context['classId'])->values() : $classes;
        [$from, $to] = AbsenceReport::bounds($year, $period, $context['week'], $context['month']);
        $rangeLabel = self::periodLabel($year, $period, $from, $to);
        $isAnnual = $period === 'Tahunan';
        $isShort = in_array($period, ['Mingguan', 'Bulanan'], true);
        $action = $context['action'] ?? 'all';
        $sections = [];
        $school = DB::table('school_settings')->pluck('value', 'key')->all();
        $totalSakit = $totalIzin = $totalAlpa = $recordedCount = 0;

        foreach ($selected as $class) {
            // Ambil riwayat melalui kelas PADA TRANSAKSI, bukan kelas siswa saat ini.
            $recorded = AbsenceReport::query($year, $period, (int) $class->id, $context['week'], $context['month'], $action)->get();
            if ($action !== 'all' && $recorded->isEmpty()) continue;
            $byStudent = [];
            foreach ($recorded as $entry) {
                $entry->recorded = true;
                $byStudent[(int) $entry->student_id] = $entry;
            }

            // Sertakan siswa aktif yang BELUM punya input; jangan tampilkan 0 JP palsu.
            // Siswa yang sudah berpindah kelas tetap ikut jika memiliki catatan historis di kelas ini.
            $roster = $action === 'all' ? Student::query()->where('class_id', $class->id)->where('active', true)
                ->orderBy('full_name')->get(['id', 'nis', 'full_name']) : collect();
            foreach ($roster as $student) {
                if (isset($byStudent[$student->id])) continue;
                $byStudent[$student->id] = (object) [
                    'student_id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'recorded' => false,
                ];
            }
            $rows = array_values($byStudent);
            usort($rows, static fn ($a, $b) => strnatcasecmp((string) $a->full_name, (string) $b->full_name));
            $sakit = $izin = $alpa = 0;
            foreach ($recorded as $row) {
                $sakit += (int) $row->sakit_jp;
                $izin += (int) $row->izin_jp;
                $alpa += (int) $row->alpa_jp;
            }
            $totalSakit += $sakit;
            $totalIzin += $izin;
            $totalAlpa += $alpa;
            $periodRecorded = $recorded->filter(fn ($row) => (int) $row->period_entry_count > 0)->count();
            $recordedCount += $periodRecorded;
            $sections[] = [
                'class' => $class,
                'rows' => $rows,
                'operators' => self::operators($school, Student::whereIn('id', array_keys($byStudent))->distinct()->pluck('gender')->all()),
                'recordedCount' => $periodRecorded,
                'missingCount' => count($rows) - $periodRecorded,
                'totals' => ['sakit' => $sakit, 'izin' => $izin, 'alpa' => $alpa],
            ];
        }

        return $context + [
            'sections' => $sections,
            'school' => $school,
            'logoData' => self::logoData((string) ($school['logo_path'] ?? '')),
            'rangeLabel' => $rangeLabel,
            'isAnnual' => $isAnnual,
            'isShort' => $isShort,
            'operatorName' => (string) (Auth::user()?->full_name ?? ''),
            'printDate' => CarbonImmutable::now()->locale('id')->translatedFormat('d F Y'),
            'printTimestamp' => CarbonImmutable::now()->format('Y-m-d H:i'),
            'grandTotals' => ['sakit' => $totalSakit, 'izin' => $totalIzin, 'alpa' => $totalAlpa],
            'recordedCount' => $recordedCount,
        ];
    }

    private static function periodLabel(string $year, string $period, string $from, string $to): string
    {
        $start = CarbonImmutable::parse($from)->locale('id');
        $end = CarbonImmutable::parse($to)->locale('id');
        return match ($period) {
            // Catatan JP mingguan direkam menurut hari Senin, bukan absensi harian.
            'Mingguan' => 'Minggu '.$start->translatedFormat('d F Y').' s.d. '.$start->addDays(6)->translatedFormat('d F Y'),
            'Bulanan' => 'Bulan '.$start->translatedFormat('F Y'),
            'Ganjil' => 'Semester Ganjil ('.$start->translatedFormat('d F Y').' – '.$end->translatedFormat('d F Y').')',
            'Genap' => 'Semester Genap ('.$start->translatedFormat('d F Y').' – '.$end->translatedFormat('d F Y').')',
            default => 'Tahun Pelajaran '.$year.' ('.$start->translatedFormat('d F Y').' – '.$end->translatedFormat('d F Y').')',
        };
    }

    private static function operators(array $school, array $genders): array
    {
        $male = trim((string) ($school['operator_male_name'] ?? ''));
        $female = trim((string) ($school['operator_female_name'] ?? ''));
        $fallback = $male ?: ($female ?: (string) (Auth::user()?->full_name ?? ''));
        if (!$male || !$female || $male === $female || $genders === []) {
            return [['label' => 'Petugas Input Absensi', 'name' => $fallback]];
        }
        $operators = [];
        foreach (['L' => ['Putra', $male], 'P' => ['Putri', $female]] as $gender => [$label, $name]) {
            if (in_array($gender, $genders, true)) $operators[] = ['label' => 'Petugas Input Siswa '.$label, 'name' => $name];
        }
        return $operators;
    }

    private static function logoData(string $logoPath): ?string
    {
        if ($logoPath === '' || !Storage::disk('public')->exists($logoPath)) return null;
        $path = Storage::disk('public')->path($logoPath);
        $size = @filesize($path);
        if ($size === false || $size <= 0 || $size > 2 * 1024 * 1024) return null;
        $mime = @mime_content_type($path);
        if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $image = @imagecreatefromwebp($path);
            if (!$image) return null;
            ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
            return is_string($png) ? 'data:image/png;base64,'.base64_encode($png) : null;
        }
        if (!in_array($mime, ['image/png', 'image/jpeg'], true)) return null;
        $contents = @file_get_contents($path);
        return $contents === false ? null : 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
