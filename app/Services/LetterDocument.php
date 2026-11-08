<?php
namespace App\Services;

use App\Models\DisciplinaryLetter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Menyediakan isi yang SAMA untuk pratinjau/cetak browser dan PDF. */
final class LetterDocument
{
    public static function prepare(DisciplinaryLetter $letter): array
    {
        $letter->loadMissing('student.classroom');
        $school = DB::table('school_settings')->pluck('value', 'key')->all();
        [$from, $to] = AcademicPeriod::bounds($letter->academic_year, $letter->semester);
        $totals = AbsenceEntries::query()
            ->where('student_id', $letter->student_id)
            ->where('academic_year', $letter->academic_year)
            ->whereBetween('entry_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(sakit_jp),0) AS sakit_jp, COALESCE(SUM(izin_jp),0) AS izin_jp, COALESCE(SUM(alpa_jp),0) AS alpa_jp')
            ->first();
        // Riwayat kelas berasal dari kelas PADA ABSENSI dalam semester surat,
        // bukan hanya kelas aktif setelah siswa naik kelas.
        $semesterReport = AbsenceReport::query($letter->academic_year, $letter->semester)
            ->where('s.id', $letter->student_id)->first();
        $classLabel = trim((string) ($semesterReport?->class_label ?? ''));
        if ($classLabel === '') $classLabel = (string) ($letter->student?->classroom?->class_name ?? '—');

        $level = (int) $letter->level;
        $recipient = match ($level) {
            1 => 'Wali Kelas '.$classLabel,
            2 => 'Bagian Bimbingan dan Penyuluhan (BP)',
            3 => 'Orang Tua/Wali Murid '.$letter->student->full_name,
            default => '—',
        };
        $subject = match ($level) {
            1 => 'Pemberitahuan Peringatan Kehadiran Siswa',
            2 => 'Permohonan Penanganan dan Pembinaan Siswa',
            3 => 'Pemanggilan Orang Tua/Wali Murid',
            default => 'Tindak Lanjut Absensi',
        };
        $senderTitle = $level === 3 ? 'Petugas / Koordinator BP' : 'Waka Kesiswaan';
        $senderName = trim((string) ($school[$level === 3 ? 'bp_name' : 'vice_principal_name'] ?? ''));
        $principalName = trim((string) ($school['principal_name'] ?? ''));
        $logoData = null;
        $logoPath = (string) ($school['logo_path'] ?? '');
        if ($logoPath !== '' && Storage::disk('public')->exists($logoPath)) {
            $path = Storage::disk('public')->path($logoPath);
            $size = @filesize($path);
            // Logo PNG/JPG/WEBP kecil yang diunggah melalui Profil Madrasah.
            if ($size !== false && $size > 0 && $size <= 2 * 1024 * 1024) {
                $mime = @mime_content_type($path);
                if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
                    // Dompdf tidak selalu mendukung WEBP secara langsung; konversi di memori.
                    $im = @imagecreatefromwebp($path);
                    if ($im !== false) {
                        ob_start(); imagepng($im); $png = ob_get_clean(); imagedestroy($im);
                        if (is_string($png)) $logoData = 'data:image/png;base64,'.base64_encode($png);
                    }
                } elseif (in_array($mime, ['image/png', 'image/jpeg'], true)) {
                    $contents = file_get_contents($path);
                    if ($contents !== false) $logoData = 'data:'.$mime.';base64,'.base64_encode($contents);
                }
            }
        }
        return [
            'letter' => $letter,
            'school' => $school,
            'logoData' => $logoData,
            'recipient' => $recipient,
            'subject' => $subject,
            'senderTitle' => $senderTitle,
            'senderName' => $senderName,
            'principalName' => $principalName,
            'classLabel' => $classLabel,
            'sakitJp' => (int) ($totals->sakit_jp ?? 0),
            'izinJp' => (int) ($totals->izin_jp ?? 0),
            'alpaJp' => (int) ($totals->alpa_jp ?? 0),
            'level' => $level,
        ];
    }
}
