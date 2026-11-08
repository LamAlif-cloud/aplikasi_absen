<?php
namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\WeeklyAbsence;
use App\Services\AbsenceEntries;
use App\Services\AbsenceReport;
use App\Services\Discipline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaldoAwalSemesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_saldo_awal_is_counted_once_and_november_weekly_is_added(): void
    {
        $class = Classroom::create(['class_name'=>'VII A', 'academic_year'=>'2026/2027']);
        $student = Student::create(['full_name'=>'MOH. MIFTA UJI', 'gender'=>'L', 'class_id'=>$class->id]);
        DB::table('opening_absence')->insert([
            'student_id'=>$student->id,'class_id'=>$class->id,'academic_year'=>'2026/2027','semester'=>'Ganjil',
            'as_of_date'=>'2026-10-31','sakit_jp'=>48,'izin_jp'=>4,'alpa_jp'=>96,
        ]);
        self::assertSame(96, AbsenceReport::studentAlpa($student->id, '2026/2027', 'Ganjil'));
        self::assertSame(3, Discipline::level(96));
        self::assertSame(96, (int) AbsenceReport::query('2026/2027', 'Bulanan', $class->id, null, '2026-10')->first()->alpa_jp);
        self::assertCount(0, AbsenceReport::query('2026/2027', 'Mingguan', $class->id, '2026-10-26')->get());
        WeeklyAbsence::create(['student_id'=>$student->id,'class_id'=>$class->id,'academic_year'=>'2026/2027','week_start'=>'2026-11-02','alpa_jp'=>8]);
        self::assertSame(104, AbsenceReport::studentAlpa($student->id,'2026/2027','Ganjil'));
        self::assertSame(104, (int) AbsenceReport::query('2026/2027','Ganjil',$class->id)->first()->alpa_jp);
        self::assertSame(104, (int) AbsenceReport::query('2026/2027','Tahunan',$class->id)->first()->alpa_ganjil_jp);
    }

    public function test_opening_rejects_duplicate_october_but_new_school_year_can_start_in_july(): void
    {
        $class = Classroom::create(['class_name'=>'VII A','academic_year'=>'2026/2027']);
        $student = Student::create(['full_name'=>'TEST BATAS TAHUN','gender'=>'P','class_id'=>$class->id]);
        DB::table('opening_absence')->insert([
            'student_id'=>$student->id,'class_id'=>$class->id,'academic_year'=>'2026/2027','semester'=>'Ganjil',
            'as_of_date'=>'2026-10-31','sakit_jp'=>0,'izin_jp'=>0,'alpa_jp'=>24,
        ]);
        try {
            AbsenceEntries::ensureMonthAvailable($student->id,'2026/2027','2026-10','Bulanan');
            self::fail('Oktober seharusnya dikunci oleh saldo awal');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('rows', $e->errors());
        }
        AbsenceEntries::ensureMonthAvailable($student->id,'2026/2027','2026-11','Mingguan');
        AbsenceEntries::ensureMonthAvailable($student->id,'2027/2028','2027-07','Mingguan');
        $newClass = Classroom::create(['class_name'=>'VIII A','academic_year'=>'2027/2028']);
        WeeklyAbsence::create(['student_id'=>$student->id,'class_id'=>$newClass->id,'academic_year'=>'2027/2028','week_start'=>'2027-07-05','alpa_jp'=>8]);
        self::assertSame(8, AbsenceReport::studentAlpa($student->id,'2027/2028','Ganjil'));
    }

    public function test_invalid_old_letter_is_blocked_at_print_and_issue(): void
    {
        $admin = \App\Models\User::create(['full_name'=>'Administrator','username'=>'saldoaudit','password'=>'PasswordTesting123!','role'=>'admin','active'=>true]);
        $class = Classroom::create(['class_name'=>'VII A','academic_year'=>'2026/2027']);
        $student = Student::create(['full_name'=>'SISWA TANPA ALPA','gender'=>'L','class_id'=>$class->id]);
        $letter = \App\Models\DisciplinaryLetter::create([
            'student_id'=>$student->id,'academic_year'=>'2026/2027','semester'=>'Ganjil','level'=>1,
            'letter_number'=>'UJI-1','letter_date'=>'2026-10-21','status'=>'draft','created_by'=>$admin->id,
        ]);
        $this->actingAs($admin)->get('/letters/'.$letter->id.'/print')->assertStatus(422);
        $this->actingAs($admin)->post('/letters/'.$letter->id.'/status',['status'=>'issued'])->assertSessionHasErrors('status');
    }
}
