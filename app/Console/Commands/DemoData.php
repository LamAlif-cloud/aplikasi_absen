<?php
namespace App\Console\Commands;

use App\Models\Classroom;
use App\Models\DisciplinaryLetter;
use App\Models\Student;
use App\Models\WeeklyAbsence;
use App\Services\AcademicPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoData extends Command
{
    protected $signature = 'madrasah:demo {--clear : Hapus hanya data DEMO-* dan catatan JP-nya}';
    protected $description = 'Buat/hapus data contoh untuk menguji ambang 23,24,47,48,71,72,80 JP';
    public function handle(): int
    {
        if (app()->environment('production')) { $this->error('Data demo tidak boleh digunakan di server production.'); return self::FAILURE; }
        if ($this->option('clear')) {
            if (!$this->confirm('Hapus siswa DEMO-* beserta input JP-nya?', false)) return self::FAILURE;
            DB::transaction(function (): void {
                $ids = Student::where('nis', 'like', 'DEMO-%')->pluck('id');
                if ($ids->isNotEmpty()) {
                    DisciplinaryLetter::whereIn('student_id', $ids)->delete();
                    DB::table('student_class_history')->whereIn('student_id', $ids)->delete();
                    WeeklyAbsence::whereIn('student_id', $ids)->delete();
                    Student::whereIn('id', $ids)->delete();
                }
            });
            $this->info('Data contoh dibersihkan. Data lain tidak disentuh.'); return self::SUCCESS;
        }
        if (!$this->confirm('Tambahkan 7 siswa contoh untuk uji tata tertib?', true)) return self::SUCCESS;
        $year = AcademicPeriod::currentYear();
        $week = CarbonImmutable::today()->startOfWeek()->toDateString();
        $values = [23, 24, 47, 48, 71, 72, 80];
        DB::transaction(function () use ($year, $week, $values): void {
            $class = Classroom::firstOrCreate(['class_name' => 'DEMO A', 'academic_year' => $year], ['homeroom_teacher' => 'Wali Kelas Demo']);
            foreach ($values as $v) {
                $student = Student::updateOrCreate(['nis' => 'DEMO-'.$v], ['nisn' => null, 'full_name' => 'SISWA UJI ALPA '.$v.' JP', 'class_id' => $class->id, 'gender' => 'L', 'active' => true]);
                WeeklyAbsence::updateOrCreate(['student_id' => $student->id, 'academic_year' => $year, 'week_start' => $week],
                    ['class_id' => $class->id, 'alpa_jp' => $v, 'izin_jp' => 1, 'sakit_jp' => 2, 'note' => 'DATA DEMO – bukan absensi sebenarnya']);
            }
        });
        $this->info('7 siswa DEMO berhasil dibuat. Buka Dashboard dan Rekap Semester.');
        return self::SUCCESS;
    }
}
