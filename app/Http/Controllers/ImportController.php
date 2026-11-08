<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ImportIssue;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\WeeklyAbsence;
use App\Services\AcademicPeriod;
use App\Services\AbsenceEntries;
use App\Models\MonthlyAbsence;
use App\Services\ExcelWorkbook;
use App\Services\YearOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ImportController
{
    // Attendance imports always use one explicitly selected unit for the entire file.
    public const TYPES = ['classes', 'teachers', 'students', 'weekly', 'monthly']; // teachers: backward compatibility for prior templates

    public static function headers(string $type): array
    {
        return match ($type) {
            'classes' => ['class_name', 'homeroom_teacher'],
            'teachers' => ['class_name', 'homeroom_teacher'],
            'students' => ['full_name', 'gender', 'class_name'],
            'weekly' => ['class_name', 'week_start', 'alpa_jp', 'izin_jp', 'sakit_jp'],
            'monthly' => ['class_name', 'month', 'alpa_jp', 'izin_jp', 'sakit_jp'],
            default => throw new \InvalidArgumentException('Jenis impor tidak tersedia.'),
        };
    }

    public function index(): View
    {
        return view('imports.index', ['years' => YearOptions::all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'academic_year' => ['required', 'string'],
            // The MIME detector can label a genuine OOXML workbook as application/zip,
            // notably for test workbooks created in temporary paths. Check the file
            // extension here, then verify the REAL spreadsheet format before reading.
            'file' => ['required', 'file', 'extensions:xlsx,xls', 'max:5120'],
            'existing_action' => ['required', Rule::in(['update', 'skip'])],
            'input_unit' => ['required_if:type,weekly,monthly', 'nullable', Rule::in(['jp', 'days'])],
        ]);
        $year = trim($data['academic_year']);
        $inputUnit = $data['input_unit'] ?? 'jp'; // Explicit units only. Ambiguous mixed conversion is disabled for safety.
        if (!AcademicPeriod::isValidYear($year)) return back()->withErrors(['academic_year' => 'Tahun pelajaran harus YYYY/YYYY berurutan.']);
        try {
            $rows = ExcelWorkbook::read($request->file('file')->getRealPath(), self::headers($data['type']));
        } catch (\Throwable $e) {
            $reason = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Berkas Excel gagal dibaca. Pastikan format benar dan tidak rusak.';
            return back()->withErrors(['file' => $reason]);
        }
        $summary = ['baru' => 0, 'diperbarui' => 0, 'dilewati' => 0, 'gagal' => 0, 'errors' => []];
        $seenWithoutId = [];
        foreach ($rows as $line) {
            if (!empty($line['error'])) {
                $this->saveIssue($request, $data, $line, $year, $inputUnit, $line['error']);
                $summary['gagal']++;
                if (count($summary['errors']) < 100) $summary['errors'][] = $line['error'];
                continue;
            }
            if ($line['empty']) { $summary['dilewati']++; continue; }
            if ($data['type'] === 'students') {
                $r = $line['data'];
                if (trim((string)($r['nis'] ?? '')) === '' && trim((string)($r['nisn'] ?? '')) === '') {
                    $signature = mb_strtoupper(trim((string)($r['class_name'] ?? ''))).'|'.mb_strtoupper(trim((string)($r['full_name'] ?? ''))).'|'.mb_strtoupper(trim((string)($r['gender'] ?? '')));
                    if (isset($seenWithoutId[$signature])) {
                        $this->saveIssue($request, $data, $line, $year, $inputUnit, 'Identitas tanpa NIS/NISN berulang. Lengkapi identitas unik sebelum menyimpan ulang.');
                        $summary['gagal']++;
                        if (count($summary['errors']) < 100) $summary['errors'][] = 'Baris '.$line['line'].': identitas tanpa NIS/NISN berulang dengan baris '.$seenWithoutId[$signature].'. Lengkapi NISN agar dua siswa berbeda tidak digabung.';
                        continue;
                    }
                    $seenWithoutId[$signature] = $line['line'];
                }
            }
            try {
                // Isolate each row transaction; failure in one row does not roll back other valid rows.
                $result = DB::transaction(function () use ($data, $line, $year, $inputUnit): string {
                    $r = $line['data'];
                    return match ($data['type']) {
                        'classes' => $this->importClass($r, $year, $data['existing_action']),
                        'teachers' => $this->importTeacher($r, $year, $data['existing_action']),
                        'students' => $this->importStudent($r, $year, $data['existing_action']),
                        'weekly' => $this->importWeekly($r, $year, $data['existing_action'], $inputUnit),
                        'monthly' => $this->importMonthly($r, $year, $data['existing_action'], $inputUnit),
                    };
                }, 2);
                $summary[$result]++;
            } catch (\Throwable $e) {
                // Log technical details server-side; never expose SQL or paths on production pages.
                Log::error('Excel import row failed', [
                    'type' => $data['type'],
                    'row' => $line['line'],
                    'exception' => $e,
                ]);
                $summary['gagal']++;
                $this->saveIssue($request, $data, $line, $year, $inputUnit, $this->reason($e));
                if (count($summary['errors']) < 100) $summary['errors'][] = 'Baris '.$line['line'].': '.$this->reason($e);
            }
        }
        AuditLog::create([
            'user_id' => Auth::id(), 'action' => 'excel_import_'.$data['type'],
            'detail' => 'Tahun '.$year.'; satuan '.$inputUnit.'; baru '.$summary['baru'].'; diperbarui '.$summary['diperbarui'].'; dilewati '.$summary['dilewati'].'; gagal '.$summary['gagal'],
            'ip_address' => $request->ip(),
        ]);
        return back()->with('import_summary', $summary)->with('notice', 'Impor Excel selesai. Periksa jumlah dan rincian kesalahan.');
    }

    private function importClass(array $r, string $year, string $action): string
    {
        $name = $this->className($r['class_name'] ?? '');
        $teacher = trim((string) ($r['homeroom_teacher'] ?? ''));
        if (mb_strlen($teacher) > 120) throw new \InvalidArgumentException('Nama wali kelas terlalu panjang.');
        $existing = Classroom::where('class_name', $name)->where('academic_year', $year)->lockForUpdate()->first();
        if (!$existing) {
            Classroom::create(['class_name' => $name, 'academic_year' => $year, 'homeroom_teacher' => $teacher ?: null]);
            return 'baru';
        }
        if ($action === 'skip' || $teacher === '' || ($existing->homeroom_teacher ?? '') === $teacher) return 'dilewati';
        $existing->update(['homeroom_teacher' => $teacher ?: null]);
        return 'diperbarui';
    }

    private function importTeacher(array $r, string $year, string $action): string
    {
        $name = $this->className($r['class_name'] ?? '');
        $teacher = trim((string) ($r['homeroom_teacher'] ?? ''));
        if ($teacher === '' || mb_strlen($teacher) > 120) throw new \InvalidArgumentException('Nama wali kelas kosong atau terlalu panjang.');
        $class = Classroom::where('class_name', $name)->where('academic_year', $year)->lockForUpdate()->first();
        if (!$class) throw new \InvalidArgumentException('Kelas '.$name.' belum ditemukan pada tahun '.$year.'. Impor kelas terlebih dahulu.');
        if ($action === 'skip' || $class->homeroom_teacher === $teacher) return 'dilewati';
        $class->update(['homeroom_teacher' => $teacher]);
        return 'diperbarui';
    }

    private function importStudent(array $r, string $year, string $action): string
    {
        $nis = trim((string) ($r['nis'] ?? '')) ?: null;
        $nisn = trim((string) ($r['nisn'] ?? '')) ?: null;
        $name = trim((string) ($r['full_name'] ?? ''));
        $genderRaw = mb_strtoupper(trim((string) ($r['gender'] ?? '')));
        $gender = match ($genderRaw) {
            'L', 'LAKI-LAKI', 'LAKI LAKI', 'PRIA' => 'L',
            'P', 'PEREMPUAN', 'WANITA' => 'P',
            default => $genderRaw,
        };
        $className = $this->className($r['class_name'] ?? '');
        if ($nis !== null && mb_strlen($nis) > 40) throw new \InvalidArgumentException('NIS melebihi 40 karakter.');
        if ($nisn !== null && mb_strlen($nisn) > 30) throw new \InvalidArgumentException('NISN terlalu panjang.');
        if ($name === '' || mb_strlen($name) > 150) throw new \InvalidArgumentException('Nama siswa kosong atau terlalu panjang.');
        if (!in_array($gender, ['L', 'P'], true)) throw new \InvalidArgumentException('Jenis kelamin harus L/P atau Laki-laki/Perempuan.');
        $class = Classroom::where('academic_year', $year)->where('class_name', $className)->first();
        if (!$class) throw new \InvalidArgumentException('Kelas '.$className.' tidak ditemukan pada '.$year.'.');
        // The fallback is allowed only for an unambiguous same-name/gender/class match.
        // Never randomly attach transactions to another student when NIS is blank.
        $byNis = $nis ? Student::where('nis', $nis)->lockForUpdate()->first() : null;
        $byNisn = $nisn ? Student::where('nisn', $nisn)->lockForUpdate()->get() : collect();
        if ($byNisn->count() > 1) throw new \InvalidArgumentException('NISN ganda di database. Perbaiki dahulu identitas siswa.');
        $matchedNisn = $byNisn->first();
        if ($byNis && $matchedNisn && $byNis->id !== $matchedNisn->id) throw new \InvalidArgumentException('NIS dan NISN menunjuk dua siswa berbeda.');
        $existing = $byNis ?? $matchedNisn;
        if (!$existing) {
            $matches = Student::where('class_id', $class->id)->where('full_name', $name)->where('gender', $gender)->lockForUpdate()->get();
            if ($matches->count() > 1) throw new \InvalidArgumentException('Ada lebih dari satu siswa bernama sama tanpa identitas unik. Lengkapi NIS/NISN untuk membedakan.');
            $existing = $matches->first();
            if ($existing && (($nis && $existing->nis && $existing->nis !== $nis) || ($nisn && $existing->nisn && $existing->nisn !== $nisn))) {
                throw new \InvalidArgumentException('Nama sama dengan siswa beridentitas berbeda. Periksa NIS/NISN dan gunakan pengeditan manual.');
            }
        }
        // Pondok bersifat opsional agar template 6 kolom lama tetap diterima.
        // Kolom kosong saat memperbarui siswa yang sudah ada TIDAK menghapus data pondok.
        $pondok = $this->boardingFields($r, $existing);
        $payload = ['nis' => $nis ?? $existing?->nis, 'nisn' => $nisn ?? $existing?->nisn, 'full_name' => $name, 'gender' => $gender, 'class_id' => $class->id] + $pondok;
        if (!$existing) { Student::create($payload + ['active' => true, 'student_status' => 'Aktif']); return 'baru'; }
        if ((int) $existing->class_id !== (int) $class->id) throw new \InvalidArgumentException('Siswa sudah tercatat di kelas berbeda. Gunakan menu Kenaikan/Mutasi Kelas, bukan impor.');
        if ($action === 'skip') return 'dilewati';
        if (!collect($payload)->contains(fn ($v, $k) => $existing->{$k} != $v)) return 'dilewati';
        $existing->update($payload);
        return 'diperbarui';
    }

    /**
     * Pemetaan pondok untuk impor siswa. Kolom baru opsional dan tidak
     * mengosongkan data yang sebelumnya sudah terisi secara tidak sengaja.
     */
    private function boardingFields(array $row, ?Student $existing): array
    {
        $rawStatus = trim((string) ($row['boarding_status'] ?? ''));
        $rawName = trim((string) ($row['boarding_school'] ?? ''));
        if ($rawStatus === '' && $rawName === '') {
            return $existing ? [] : ['boarding_status' => 'Tidak', 'boarding_school' => null];
        }
        if (mb_strlen($rawName) > 120) {
            throw new \InvalidArgumentException('Nama pondok maksimal 120 karakter.');
        }
        $status = mb_strtoupper($rawStatus, 'UTF-8');
        $normalized = match ($status) {
            '', 'MONDOK', 'YA', 'Y', 'PONDOK' => 'Mondok',
            'TIDAK', 'TIDAK MONDOK', 'NON MONDOK', 'NON-MONDOK', 'T', 'N' => 'Tidak',
            default => throw new \InvalidArgumentException('STATUS PONDOK harus Mondok atau Tidak.'),
        };
        if ($normalized === 'Tidak' && $rawName !== '') {
            throw new \InvalidArgumentException('NAMA PONDOK harus kosong jika STATUS PONDOK = Tidak.');
        }
        return [
            'boarding_status' => $normalized,
            'boarding_school' => $normalized === 'Mondok' ? ($rawName !== '' ? $rawName : ($existing?->boarding_status === 'Mondok' ? $existing->boarding_school : null)) : null,
        ];
    }

    /** Identifier: NIS first, NISN second, otherwise exact student name+class (unique only). */
    private function resolveStudent(array $r, Classroom $class): Student
    {
        $nis = trim((string) ($r['nis'] ?? ''));
        $nisn = trim((string) ($r['nisn'] ?? ''));
        $name = trim((string) ($r['full_name'] ?? ''));
        $q = Student::where('class_id', $class->id);
        if ($nis !== '') $q->where('nis', $nis);
        elseif ($nisn !== '') $q->where('nisn', $nisn);
        elseif ($name !== '') $q->where('full_name', $name);
        else throw new \InvalidArgumentException('Isi NIS, NISN, atau NAMA untuk mengenali siswa.');
        $matches = $q->lockForUpdate()->limit(2)->get();
        if ($matches->count() !== 1) throw new \InvalidArgumentException('Siswa tidak ditemukan atau identitas ambigu di kelas '.$class->class_name.'. Lengkapi NIS/NISN.');
        return $matches->first();
    }

    /** Konversi JP/Hari dipusatkan agar impor dan laporan konsisten. */
    private function parseJp(array $r, int $limit, string $unit = 'jp'): array
    {
        return \App\Services\AbsenceQuantity::parseFields($r, $limit, $unit);
    }

    private function importWeekly(array $r, string $year, string $action, string $unit = 'jp'): string
    {
        $className = $this->className($r['class_name'] ?? '');
        $weekRaw = trim((string) ($r['week_start'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekRaw)) throw new \InvalidArgumentException('Minggu harus tanggal Senin YYYY-MM-DD.');
        $week = CarbonImmutable::createFromFormat('!Y-m-d', $weekRaw);
        if (!$week || $week->format('Y-m-d') !== $weekRaw || !$week->isMonday() || AcademicPeriod::yearForDate($week) !== $year) throw new \InvalidArgumentException('Tanggal harus Senin dalam tahun pelajaran '.$year.'.');
        $class = Classroom::where('academic_year', $year)->where('class_name', $className)->lockForUpdate()->first();
        if (!$class) throw new \InvalidArgumentException('Kelas '.$className.' tidak ditemukan pada '.$year.'.');
        $student = $this->resolveStudent($r, $class);
        AbsenceEntries::ensureMonthAvailable($student->id, $year, substr($weekRaw, 0, 7), 'Mingguan');
        $jp = $this->parseJp($r, 80, $unit);
        $note = trim((string) ($r['note'] ?? ''));
        if (mb_strlen($note) > 255) throw new \InvalidArgumentException('Catatan maksimal 255 karakter.');
        $existing = WeeklyAbsence::where('student_id', $student->id)->where('academic_year', $year)->whereDate('week_start', $weekRaw)->lockForUpdate()->first();
        if ($existing && (int) $existing->class_id !== $class->id) throw new \InvalidArgumentException('Terdapat catatan minggu ini pada kelas berbeda.');
        $payload = ['class_id' => $class->id] + $jp + ['note' => $note ?: null, 'created_by' => Auth::id()];
        if (!$existing) { WeeklyAbsence::create(['student_id' => $student->id, 'academic_year' => $year, 'week_start' => $weekRaw] + $payload); return 'baru'; }
        if ($action === 'skip') return 'dilewati';
        $existing->update($payload); return 'diperbarui';
    }

    private function importMonthly(array $r, string $year, string $action, string $unit = 'jp'): string
    {
        $className = $this->className($r['class_name'] ?? '');
        $month = trim((string) ($r['month'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) throw new \InvalidArgumentException('Bulan harus YYYY-MM.');
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');
        if (!$date || $date->format('Y-m') !== $month || AcademicPeriod::yearForDate($date) !== $year) throw new \InvalidArgumentException('Bulan tidak sesuai tahun pelajaran.');
        $class = Classroom::where('academic_year', $year)->where('class_name', $className)->lockForUpdate()->first();
        if (!$class) throw new \InvalidArgumentException('Kelas '.$className.' tidak ditemukan.');
        $student = $this->resolveStudent($r, $class);
        AbsenceEntries::ensureMonthAvailable($student->id, $year, $month, 'Bulanan');
        $jp = $this->parseJp($r, 248, $unit);
        $note = trim((string) ($r['note'] ?? ''));
        if (mb_strlen($note) > 255) throw new \InvalidArgumentException('Catatan maksimal 255 karakter.');
        $existing = MonthlyAbsence::where('student_id', $student->id)->where('academic_year', $year)->whereDate('month_start', $month.'-01')->lockForUpdate()->first();
        if ($existing && (int) $existing->class_id !== $class->id) throw new \InvalidArgumentException('Bulan ini sudah diinput pada kelas berbeda.');
        $payload = ['class_id' => $class->id] + $jp + ['note' => $note ?: null, 'created_by' => Auth::id()];
        if (!$existing) { MonthlyAbsence::create(['student_id' => $student->id, 'academic_year' => $year, 'month_start' => $month.'-01'] + $payload); return 'baru'; }
        if ($action === 'skip') return 'dilewati';
        $existing->update($payload); return 'diperbarui';
    }

    /** Normalize class labels consistently with manual class entry and Excel imports. */
    private function className(mixed $raw): string
    {
        $name = trim((string) ($raw ?? ''));
        $name = preg_replace('/\s+/u', ' ', $name);
        if ($name === null || $name === '') {
            throw new \InvalidArgumentException('Nama kelas kosong. Isi kolom KELAS.');
        }
        $name = mb_strtoupper($name, 'UTF-8');
        if (mb_strlen($name, 'UTF-8') > 60) {
            throw new \InvalidArgumentException('Nama kelas terlalu panjang (maksimum 60 karakter).');
        }
        return $name;
    }

    /** Human-readable import failure, without exposing SQL statements in production. */
    private function reason(\Throwable $e): string
    {
        if ($e instanceof \InvalidArgumentException) return $e->getMessage();
        if ($e instanceof \Illuminate\Validation\ValidationException) return implode(' ', $e->validator->errors()->all());
        // Tests can show the real exception to pinpoint regressions. Production users
        // see a safe message; the full exception is recorded in storage/logs/laravel.log.
        if (app()->environment('testing')) return get_class($e).': '.$e->getMessage();
        return 'Penyimpanan database gagal. Detail teknis telah dicatat di log administrator.';
    }

    private function saveIssue(Request $request, array $data, array $line, string $year, string $unit, string $error): void
    {
        ImportIssue::create(['type' => $data['type'], 'academic_year' => $year, 'input_unit' => $unit,
            'filename' => mb_substr($request->file('file')->getClientOriginalName(), 0, 255),
            'row_number' => $line['line'], 'row_data' => $line['data'] ?? [], 'error' => $error]);
    }

    public function issues(Request $request): View
    {
        $resolved = $request->query('status') === 'resolved';
        $issues = ImportIssue::query()->when($resolved, fn ($q) => $q->whereNotNull('resolved_at'), fn ($q) => $q->whereNull('resolved_at'))
            ->orderByDesc('id')->paginate(30)->withQueryString();
        return view('imports.issues', compact('issues', 'resolved'));
    }

    public function editIssue(ImportIssue $issue): View
    {
        return view('imports.issue-edit', compact('issue'));
    }

    public function updateIssue(Request $request, ImportIssue $issue): RedirectResponse
    {
        $data = $request->validate(['row' => ['required', 'array'], 'row.*' => ['nullable', 'string', 'max:500'],
            'input_unit' => ['required', Rule::in(['jp', 'days'])]]);
        try {
            DB::transaction(function () use ($issue, $data, $request) {
                $locked = ImportIssue::whereKey($issue->id)->lockForUpdate()->firstOrFail();
                if ($locked->resolved_at) throw new \InvalidArgumentException('Masalah ini sudah diperbaiki.');
                $row = array_intersect_key($data['row'], $locked->row_data);
                $year = $locked->academic_year;
                $unit = $data['input_unit'];
                match ($locked->type) {
                    'classes' => $this->importClass($row, $year, 'update'),
                    'teachers' => $this->importTeacher($row, $year, 'update'),
                    'students' => $this->importStudent($row, $year, 'update'),
                    'weekly' => $this->importWeekly($row, $year, 'update', $unit),
                    'monthly' => $this->importMonthly($row, $year, 'update', $unit),
                    default => throw new \InvalidArgumentException('Jenis impor tidak tersedia.'),
                };
                $locked->update(['row_data' => $row, 'input_unit' => $unit, 'resolved_at' => now()]);
                AuditLog::create(['user_id' => Auth::id(), 'action' => 'import_issue_resolved',
                    'detail' => 'Masalah impor #'.$locked->id.'; baris '.$locked->row_number.'; tahun '.$year.'; satuan '.$unit,
                    'ip_address' => $request->ip()]);
            });
        } catch (\InvalidArgumentException|\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors(['row' => $this->reason($e)]);
        }
        return redirect()->route('import-issues.index')->with('success', 'Perbaikan tersimpan ke data utama dan masalah ditandai selesai. Rekap menggunakan angka terbaru.');
    }
}
