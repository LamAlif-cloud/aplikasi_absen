<?php
namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\MonthlyAbsence;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportActionFilterTest extends TestCase
{
    use RefreshDatabase;

    private function setupClass(): Classroom
    {
        $this->actingAs(User::create(['full_name'=>'Petugas','username'=>'filter_test','password'=>'StrongPassword123!','role'=>'petugas','active'=>true]));
        return Classroom::create(['class_name'=>'VII A','academic_year'=>'2026/2027']);
    }

    private function entry(Classroom $class, string $name, int $alpa, string $month='2026-10-01', ?Student $student=null): Student
    {
        $student ??= Student::create(['full_name'=>$name,'gender'=>'L','class_id'=>$class->id]);
        MonthlyAbsence::create(['student_id'=>$student->id,'class_id'=>$class->id,'academic_year'=>'2026/2027','month_start'=>$month,'sakit_jp'=>10,'izin_jp'=>25,'alpa_jp'=>$alpa]);
        return $student;
    }

    public function test_action_boundaries_match_on_screen_and_print_and_exclude_unrecorded_roster(): void
    {
        $class=$this->setupClass();
        foreach ([23,24,47,48,71,72] as $jp) $this->entry($class,'Siswa Batas '.$jp,$jp);
        Student::create(['full_name'=>'Belum Input','gender'=>'P','class_id'=>$class->id]);
        foreach (['wali'=>[24,47], 'bp'=>[48,71], 'parents'=>[72], 'action'=>[24,47,48,71,72]] as $action=>$expected) {
            foreach (['/reports','/reports/cetak'] as $path) {
                $response=$this->get($path.'?academic_year=2026%2F2027&period=Ganjil&action='.$action)->assertOk();
                foreach ([23,24,47,48,71,72] as $jp) {
                    if (in_array($jp,$expected,true)) $response->assertSee('Siswa Batas '.$jp);
                    else $response->assertDontSee('Siswa Batas '.$jp);
                }
                $response->assertDontSee('Belum Input')->assertSee('1 hari 2 jam')->assertSee('3 hari 1 jam');
            }
        }
    }

    public function test_monthly_action_uses_semester_history_and_does_not_hide_students_without_current_input(): void
    {
        $class=$this->setupClass();
        $student=$this->entry($class,'Akumulasi BP',47,'2026-09-01');
        $this->entry($class,'Akumulasi BP',1,'2026-10-01',$student);
        $this->entry($class,'BP Tanpa Oktober',48,'2026-09-01');
        foreach (['/reports','/reports/cetak'] as $path) {
            $this->get($path.'?academic_year=2026%2F2027&period=Bulanan&month=2026-10&action=bp')
                ->assertOk()->assertSee('Akumulasi BP')->assertSee('BP Tanpa Oktober')->assertSee('Belum diinput untuk periode ini');
        }
    }

    public function test_annual_action_does_not_combine_semesters_and_respects_class_filter(): void
    {
        $class=$this->setupClass();
        $student=$this->entry($class,'Dua Semester 23',23);
        $this->entry($class,'Dua Semester 23',23,'2027-02-01',$student);
        $student=$this->entry($class,'BP Semester Genap',0);
        $this->entry($class,'BP Semester Genap',48,'2027-02-01',$student);
        $other=Classroom::create(['class_name'=>'VII B','academic_year'=>'2026/2027']);
        $this->entry($other,'Kelas Lain',48);
        $this->get('/reports/cetak?academic_year=2026%2F2027&period=Tahunan&action=bp&class_id='.$class->id)
            ->assertOk()->assertSee('BP Semester Genap')->assertDontSee('Dua Semester 23')->assertDontSee('Kelas Lain');
        $this->get('/reports?academic_year=2026%2F2027&period=Tahunan&action=action')
            ->assertOk()->assertDontSee('Dua Semester 23');
    }

    public function test_excel_and_pdf_preserve_action_filter_and_day_columns(): void
    {
        $class=$this->setupClass();
        $this->entry($class,'Untuk BP',48);
        $this->entry($class,'Bukan BP',24);
        $params='?academic_year=2026%2F2027&period=Ganjil&action=bp';
        $response=$this->get('/reports/export-excel'.$params)->assertOk();
        $path=tempnam(sys_get_temp_dir(),'action_report_');
        file_put_contents($path,$response->streamedContent());
        try {
            $book=IOFactory::load($path);
            $cells=$book->getActiveSheet()->toArray();
            $text=json_encode($cells,JSON_UNESCAPED_UNICODE);
            self::assertStringContainsString('Untuk BP',$text);
            self::assertStringNotContainsString('Bukan BP',$text);
            self::assertStringContainsString('Sakit (Hari)',$text);
            self::assertStringContainsString('Izin (Hari)',$text);
            self::assertStringContainsString('1 hari 2 jam',$text);
            self::assertStringContainsString('3 hari 1 jam',$text);
            $book->disconnectWorksheets();
        } finally { unlink($path); }
        $this->get('/reports/cetak'.$params)->assertOk()->assertSee('action=bp',false)->assertDontSee('Bukan BP');
        $this->get('/reports/cetak/pdf'.$params)->assertOk()->assertHeader('Content-Type','application/pdf');
    }

    public function test_empty_action_report_is_explicit_and_setup_keeps_selection(): void
    {
        $this->setupClass();
        $this->get('/reports/cetak?academic_year=2026%2F2027&period=Ganjil&action=parents')
            ->assertOk()->assertSee('Tidak ada siswa yang memenuhi filter tindakan.');
        $this->get('/reports/cetak/pilih?academic_year=2026%2F2027&period=Ganjil&action=bp')
            ->assertOk()->assertSee('value="bp" selected',false);
    }
}
