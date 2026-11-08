<?php
namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Services\AbsenceReport;
use App\Services\AbsenceQuantity;
use App\Services\AcademicPeriod;
use App\Services\Discipline;
use App\Services\ExcelWorkbook;
use App\Services\YearOptions;
use App\Services\ClassAttendancePrint;
use Dompdf\Dompdf;
use Dompdf\Options;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController
{
    private function context(Request $request): array
    {
        $year = YearOptions::selected($request->query('academic_year'));
        $period = (string) $request->query('period', AcademicPeriod::currentSemester());
        if (!in_array($period, ['Mingguan', 'Bulanan', 'Ganjil', 'Genap', 'Tahunan'], true)) $period = AcademicPeriod::currentSemester();
        $classes = Classroom::forYear($year)->orderBy('class_name')->get();
        $classId = (int) $request->query('class_id', 0);
        if (!$classes->contains('id', $classId)) $classId = 0;
        $week = (string) $request->query('week_start', AcademicPeriod::defaultMondayForYear($year));
        $month = (string) $request->query('month', AcademicPeriod::defaultMonthForYear($year));
        $filterNotice = null;
        $action = (string) $request->query('action', 'all');
        if (!array_key_exists($action, Discipline::FILTERS)) $action = 'all';
        $actionLabel = Discipline::FILTERS[$action];
        try { AbsenceReport::bounds($year, $period, $week, $month); }
        catch (\Throwable) {
            // Preserve requested report type; use a valid interval in the selected year.
            $week = AcademicPeriod::defaultMondayForYear($year);
            $month = AcademicPeriod::defaultMonthForYear($year);
            $filterNotice = 'Tanggal pilihan di luar tahun pelajaran atau bukan Senin. Periode disesuaikan ke awal tahun pelajaran yang dipilih.';
        }
        // Warn clearly when an imported opening balance is included in a selected report.
        [$from, $to] = AbsenceReport::bounds($year, $period, $week, $month);
        $hasOpening = \Illuminate\Support\Facades\DB::table('opening_absence')
            ->where('academic_year', $year)
            ->whereBetween('as_of_date', [$from, $to])
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->exists();
        $openingNotice = $hasOpening
            ? 'PERHATIAN: Laporan ini mencakup SALDO AWAL rekap Juli–Oktober 2026 yang dicatat per 31 Oktober, bukan seluruhnya ketidakhadiran Oktober. Rincian mingguan sebelum saldo awal tidak tersedia.'
            : null;
        return compact('year', 'period', 'classes', 'classId', 'week', 'month', 'filterNotice', 'openingNotice', 'action', 'actionLabel') + ['years' => YearOptions::all()];
    }
    public function index(Request $request): View
    {
        $context = $this->context($request);
        $context['rows'] = AbsenceReport::query($context['year'], $context['period'], $context['classId'] ?: null, $context['week'], $context['month'], $context['action'])->get();
        return view('reports.index', $context);
    }
    public function excel(Request $request): StreamedResponse
    {
        $ctx = $this->context($request);
        $rows = AbsenceReport::query($ctx['year'], $ctx['period'], $ctx['classId'] ?: null, $ctx['week'], $ctx['month'], $ctx['action'])->get();
        $annual = $ctx['period'] === 'Tahunan';
        $headers = ['NIS', 'Nama Siswa', 'Kelas (Riwayat)', 'Sakit (JP)', 'Izin (JP)', 'Alpa (JP)', 'Alpa (Hari)', 'Alpa (Hari + Sisa Jam)'];
        if ($annual) array_push($headers, 'Alpa Ganjil (JP)', 'Tata Tertib Ganjil', 'Alpa Genap (JP)', 'Tata Tertib Genap');
        elseif (in_array($ctx['period'], ['Mingguan', 'Bulanan'], true)) array_push($headers, 'Alpa Semester s.d. Periode (JP)', 'Tata Tertib Semester');
        else $headers[] = 'Tata Tertib Semester';
        array_push($headers, 'Sakit (Hari)', 'Sakit (Hari + Sisa Jam)', 'Izin (Hari)', 'Izin (Hari + Sisa Jam)', 'Status Input Periode');
        $values = $rows->map(function ($r) use ($annual, $ctx) {
            $recorded = (int) $r->period_entry_count > 0;
            $base = [(string) $r->nis, (string) $r->full_name, (string) $r->class_label];
            foreach (['sakit_jp', 'izin_jp', 'alpa_jp'] as $field) $base[] = $recorded ? (int) $r->{$field} : null;
            $base[] = $recorded ? (int) $r->alpa_jp / AbsenceQuantity::JP_PER_DAY : null;
            $base[] = $recorded ? AbsenceQuantity::readable((int) $r->alpa_jp) : null;
            if ($annual) $status = [(int) $r->alpa_ganjil_jp, Discipline::label((int) $r->alpa_ganjil_jp), (int) $r->alpa_genap_jp, Discipline::label((int) $r->alpa_genap_jp)];
            elseif (in_array($ctx['period'], ['Mingguan', 'Bulanan'], true)) $status = [(int) $r->semester_alpa_jp, Discipline::label((int) $r->semester_alpa_jp)];
            else $status = [Discipline::label((int) $r->alpa_jp)];
            $durations = [];
            foreach (['sakit_jp', 'izin_jp'] as $field) {
                $durations[] = $recorded ? (int) $r->{$field} / AbsenceQuantity::JP_PER_DAY : null;
                $durations[] = $recorded ? AbsenceQuantity::readable((int) $r->{$field}) : null;
            }
            return array_merge($base, $status, $durations, [$recorded ? 'Sudah diinput' : 'Belum diinput; tindakan dari akumulasi semester']);
        });
        $suffix = str_replace('/', '-', $ctx['year']).'-'.strtolower($ctx['period']);
        return ExcelWorkbook::download('rekap-absensi-'.$suffix.'.xlsx', $headers, $values, 'REKAP ABSENSI MADRASAH',
            $ctx['year'].' | '.$ctx['period'].' | '.$ctx['actionLabel'].' | Tata tertib per semester; 8 JP = 1 hari');
    }
    /** Operator selects the reporting period and the destination wali kelas. */
    public function printSetup(Request $request): View
    {
        return view('reports.print-setup', $this->context($request));
    }

    /** Identical data and document layout as PDF, for browser printing. */
    public function printReport(Request $request): View
    {
        return view('reports.print', ClassAttendancePrint::prepare($this->context($request)) + ['pdfMode' => false]);
    }

    /** Download printable report for one or all classes in A4 landscape. */
    public function printPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        if (!class_exists(Dompdf::class)) {
            abort(503, 'Pembuatan PDF belum tersedia. Jalankan composer require dompdf/dompdf di folder proyek Aplikasi Absensi Siswa.');
        }
        $values = ClassAttendancePrint::prepare($this->context($request)) + ['pdfMode' => true];
        $html = view('reports.print', $values)->render();
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->setPaper('a4', 'landscape');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        $file = 'Laporan_Absensi_'.preg_replace('/[^A-Za-z0-9_-]/', '-', $values['year']).'_'.strtolower($values['period']).'_'.($values['classId'] ? 'kelas-'.$values['classId'] : 'semua-kelas').'.pdf';
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$file.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

}
