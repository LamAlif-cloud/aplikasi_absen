<?php
namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\DisciplinaryLetter;
use App\Models\MonthlyAbsence;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuratResmiCetakPdfTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $admin = User::create(['full_name'=>'Administrator','username'=>'testsurat','password'=>'Pass1234!Pass1234!', 'role'=>'admin','active'=>true]);
        $class = Classroom::create(['class_name'=>'VII A','academic_year'=>'2026/2027','homeroom_teacher'=>'Wali Test']);
        $student = Student::create(['nis'=>null,'nisn'=>'00123456','full_name'=>'SISWA PENERIMA SURAT','gender'=>'L','class_id'=>$class->id]);
        MonthlyAbsence::create(['student_id'=>$student->id,'class_id'=>$class->id,'academic_year'=>'2026/2027','month_start'=>'2026-10-01','sakit_jp'=>8,'izin_jp'=>4,'alpa_jp'=>72]);
        foreach (['school_name'=>'MTs CONTOH NASIONAL','school_address'=>'Jl. Pendidikan 12',
                  'principal_name'=>'KEPALA TEST','vice_principal_name'=>'WAKA TEST','bp_name'=>'BP TEST',
                  'school_nsm'=>'123456789012','school_npsn'=>'11223344'] as $key=>$value) {
            DB::table('school_settings')->updateOrInsert(['key'=>$key],['value'=>$value]);
        }
        return [$admin, $student];
    }

    public function test_all_three_letters_use_correct_recipient_sender_and_sia_semester(): void
    {
        [$admin,$student] = $this->fixture();
        $this->actingAs($admin);
        foreach ([1,2,3] as $level) {
            $letter = DisciplinaryLetter::create([
                'student_id'=>$student->id,'academic_year'=>'2026/2027','semester'=>'Ganjil','level'=>$level,
                'letter_number'=>'012/TEST/'.$level,'letter_date'=>'2026-10-20','status'=>'draft',
            ]);
            $page = $this->get(route('letters.print',$letter))->assertOk()
                ->assertSee('MTs CONTOH NASIONAL')->assertSee('SISWA PENERIMA SURAT')
                ->assertSee('VII A')->assertSee('8 JP')->assertSee('4 JP')->assertSee('72 JP')->assertDontSee('D R A F');
            if ($level === 1) $page->assertSee('Wali Kelas VII A')->assertSee('WAKA TEST');
            if ($level === 2) $page->assertSee('Bagian Bimbingan dan Penyuluhan (BP)')->assertSee('WAKA TEST');
            if ($level === 3) $page->assertSee('Orang Tua/Wali Murid SISWA PENERIMA SURAT')->assertSee('BP TEST')->assertSee('KEPALA TEST');
        }
    }

    public function test_print_pdf_routes_reject_non_staff_role(): void
    {
        [$admin,$student] = $this->fixture();
        $letter = DisciplinaryLetter::create(['student_id'=>$student->id,'academic_year'=>'2026/2027','semester'=>'Ganjil','level'=>3,'letter_number'=>'012/TEST','letter_date'=>'2026-10-20']);
        $staff = User::create(['full_name'=>'Petugas','username'=>'petugasuji','password'=>'Pass1234!Pass1234!','role'=>'petugas','active'=>true]);
        $this->actingAs($staff);
        $this->get(route('letters.print',$letter))->assertForbidden();
        $this->get(route('letters.pdf',$letter))->assertForbidden();
    }

    public function test_profile_can_store_letter_signatories(): void
    {
        [$admin] = $this->fixture();
        $this->actingAs($admin);
        $this->put(route('settings.school.update'), [
            'school_name'=>'MTs CONTOH NASIONAL','vice_principal_name'=>'WAKA BARU', 'bp_name'=>'BP BARU',
            'principal_name'=>'KEPALA TEST',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('school_settings',['key'=>'vice_principal_name','value'=>'WAKA BARU']);
        $this->assertDatabaseHas('school_settings',['key'=>'bp_name','value'=>'BP BARU']);
    }

    public function test_pdf_is_valid_when_dompdf_dependency_installed(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) $this->markTestSkipped('Jalankan composer require dompdf/dompdf lebih dahulu.');
        [$admin,$student] = $this->fixture();
        $this->actingAs($admin);
        $letter = DisciplinaryLetter::create(['student_id'=>$student->id,'academic_year'=>'2026/2027','semester'=>'Ganjil','level'=>3,'letter_number'=>'023/TEST','letter_date'=>'2026-10-20']);
        $response = $this->get(route('letters.pdf',$letter))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
