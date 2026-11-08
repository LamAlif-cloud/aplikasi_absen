<?php
namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\MonthlyAbsence;
use App\Models\Student;
use App\Models\User;
use App\Models\WeeklyAbsence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CetakLaporanWaliKelasTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::create([
            'full_name' => 'Petugas Absen', 'username' => 'petugas_cetak',
            'password' => 'StrongPasswordTest123!', 'role' => 'petugas', 'active' => true,
        ]);
    }

    private function student(Classroom $class, string $name, string $nis): Student
    {
        return Student::create(['full_name' => $name, 'nis' => $nis, 'gender' => 'L', 'class_id' => $class->id]);
    }

    public function test_operator_can_print_weekly_for_one_class_with_semester_status_and_signatures(): void
    {
        $this->actingAs($this->operator());
        $class = Classroom::create(['academic_year' => '2026/2027', 'class_name' => 'VII A', 'homeroom_teacher' => 'Ibu Anisa']);
        $student = $this->student($class, 'Siswa Uji A', '00123');
        WeeklyAbsence::create(['student_id' => $student->id, 'class_id' => $class->id, 'academic_year' => '2026/2027',
            'week_start' => '2026-10-12', 'sakit_jp' => 2, 'izin_jp' => 3, 'alpa_jp' => 24]);
        $this->get('/reports/cetak?academic_year=2026%2F2027&period=Mingguan&class_id='.$class->id.'&week_start=2026-10-12')
            ->assertOk()->assertSee('Siswa Uji A')->assertSee('Ibu Anisa')
            ->assertSee('Peringatan Wali Kelas')->assertSee('Petugas Absen')
            ->assertSee('Sakit', false); // Form memakai singkatan S/I/A dan keterangannya.
    }

    public function test_all_classes_print_on_separate_sheets_and_keep_history_after_transfer(): void
    {
        $this->actingAs($this->operator());
        $old = Classroom::create(['academic_year'=>'2026/2027','class_name'=>'VII A','homeroom_teacher'=>'Pak Umar']);
        $other = Classroom::create(['academic_year'=>'2026/2027','class_name'=>'VII B','homeroom_teacher'=>'Bu Maryam']);
        $new = Classroom::create(['academic_year'=>'2027/2028','class_name'=>'VIII A']);
        $student = $this->student($old, 'Siswa Historis', '000234');
        MonthlyAbsence::create(['student_id'=>$student->id,'class_id'=>$old->id,'academic_year'=>'2026/2027',
            'month_start'=>'2026-10-01','sakit_jp'=>0,'izin_jp'=>0,'alpa_jp'=>48]);
        $student->update(['class_id'=>$new->id]);
        $this->student($other, 'Siswa B', '000235');
        $response = $this->get('/reports/cetak?academic_year=2026%2F2027&period=Ganjil&class_id=0');
        $response->assertOk()->assertSee('VII A')->assertSee('VII B')->assertSee('Siswa Historis')
            ->assertSee('Penanganan BP')->assertSee('Belum diinput untuk periode ini')
            ->assertSee('Pak Umar')->assertSee('Bu Maryam');
    }

    public function test_annual_report_separates_ganjil_and_genap_for_tatib(): void
    {
        $this->actingAs($this->operator());
        $class = Classroom::create(['academic_year'=>'2026/2027','class_name'=>'IX A']);
        $student = $this->student($class,'Siswa Tahunan','000300');
        MonthlyAbsence::create(['student_id'=>$student->id,'class_id'=>$class->id,'academic_year'=>'2026/2027',
            'month_start'=>'2026-10-01','alpa_jp'=>48]);
        MonthlyAbsence::create(['student_id'=>$student->id,'class_id'=>$class->id,'academic_year'=>'2026/2027',
            'month_start'=>'2027-02-01','alpa_jp'=>24]);
        $this->get('/reports/cetak?academic_year=2026%2F2027&period=Tahunan&class_id='.$class->id)
            ->assertOk()->assertSee('Tatib Ganjil')->assertSee('Tatib Genap')
            ->assertSee('Penanganan BP')->assertSee('Peringatan Wali Kelas');
    }

    public function test_no_input_is_not_printed_as_zero_absence(): void
    {
        $this->actingAs($this->operator());
        $class = Classroom::create(['academic_year'=>'2026/2027','class_name'=>'VII C']);
        $this->student($class,'Belum Dientri','000456');
        $this->get('/reports/cetak?academic_year=2026%2F2027&period=Bulanan&month=2026-10&class_id='.$class->id)
            ->assertOk()->assertSee('Belum Dientri')->assertSee('Belum diinput untuk periode ini');
    }

    public function test_print_requires_login_and_operator_can_open_filter_page(): void
    {
        $this->get('/reports/cetak/pilih')->assertRedirect('/login');
        $this->actingAs($this->operator())->get('/reports/cetak/pilih')
            ->assertOk()->assertSee('Semua kelas')->assertSee('Pratinjau', false);
    }

    public function test_pdf_export_has_pdf_content_type_when_dompdf_installed(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->markTestSkipped('Dompdf belum terpasang: composer require dompdf/dompdf');
        }
        $this->actingAs($this->operator());
        $class = Classroom::create(['academic_year'=>'2026/2027','class_name'=>'VII A']);
        $this->student($class,'Siswa PDF','000567');
        $response = $this->get('/reports/cetak/pdf?academic_year=2026%2F2027&period=Ganjil&class_id='.$class->id);
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', (string) $response->getContent());
    }
}
