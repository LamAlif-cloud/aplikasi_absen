<?php
namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\MonthlyAbsence;
use App\Models\Student;
use App\Models\User;
use App\Models\WeeklyAbsence;
use App\Services\AbsenceReport;
use App\Services\Discipline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Tahap5AbsensiAdministrasiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['full_name'=>'Administrator Uji','username'=>'tahap5qa','password'=>'PasswordTesting123!','role'=>'admin','active'=>true]);
    }
    private function classAndStudent(?string $nis = null): array
    {
        $c = Classroom::create(['class_name'=>'VII A','academic_year'=>'2026/2027']);
        $s = Student::create(['nis'=>$nis,'nisn'=>'0000471234','full_name'=>'SISWA CONTOH','gender'=>'L','class_id'=>$c->id]);
        return [$c,$s];
    }
    public function test_monthly_input_triggers_semester_warning_and_blocks_duplicate_weekly(): void
    {
        $this->actingAs($this->admin());
        [$c,$s] = $this->classAndStudent();
        $this->get('/monthly?academic_year=2026%2F2027&class_id='.$c->id.'&month=2026-10')
            ->assertOk()->assertSee('SISWA CONTOH');
        $this->post('/monthly', [
            'academic_year'=>'2026/2027','class_id'=>$c->id,'month'=>'2026-10',
            'rows'=>[['student_id'=>$s->id,'alpa_jp'=>24,'izin_jp'=>0,'sakit_jp'=>0]],
        ])->assertSessionHasNoErrors();
        self::assertSame(24, AbsenceReport::studentAlpa($s->id,'2026/2027','Ganjil'));
        $this->get('/reports?academic_year=2026%2F2027&period=Ganjil')->assertSee('Peringatan Wali Kelas');
        $this->get('/letters?academic_year=2026%2F2027&semester=Ganjil')->assertSee('SISWA CONTOH');
        $this->post('/weekly', [
            'academic_year'=>'2026/2027','class_id'=>$c->id,'week_start'=>'2026-10-12',
            'rows'=>[['student_id'=>$s->id,'alpa_jp'=>24,'izin_jp'=>0,'sakit_jp'=>0]],
        ])->assertSessionHasErrors('rows');
        $this->assertDatabaseCount('weekly_absence', 0);
    }
    public function test_weekly_input_blocks_monthly_for_same_student_month(): void
    {
        $this->actingAs($this->admin()); [$c,$s]=$this->classAndStudent();
        WeeklyAbsence::create(['student_id'=>$s->id,'class_id'=>$c->id,'academic_year'=>'2026/2027','week_start'=>'2026-10-12','alpa_jp'=>2,'izin_jp'=>0,'sakit_jp'=>0]);
        $this->post('/monthly',['academic_year'=>'2026/2027','class_id'=>$c->id,'month'=>'2026-10',
            'rows'=>[['student_id'=>$s->id,'alpa_jp'=>40,'izin_jp'=>0,'sakit_jp'=>0]]])->assertSessionHasErrors('rows');
        $this->assertDatabaseCount('monthly_absence',0);
    }
    public function test_monthly_and_weekly_different_months_share_report_and_threshold(): void
    {
        $this->actingAs($this->admin()); [$c,$s]=$this->classAndStudent();
        MonthlyAbsence::create(['student_id'=>$s->id,'class_id'=>$c->id,'academic_year'=>'2026/2027','month_start'=>'2026-10-01','alpa_jp'=>24,'izin_jp'=>4,'sakit_jp'=>2]);
        WeeklyAbsence::create(['student_id'=>$s->id,'class_id'=>$c->id,'academic_year'=>'2026/2027','week_start'=>'2026-11-02','alpa_jp'=>48,'izin_jp'=>0,'sakit_jp'=>0]);
        self::assertSame(72,AbsenceReport::studentAlpa($s->id,'2026/2027','Ganjil'));
        self::assertSame(3,Discipline::level(72));
        $this->get('/reports?academic_year=2026%2F2027&period=Ganjil')->assertOk()->assertSee('Pemanggilan Orang Tua');
        $this->get('/reports?academic_year=2026%2F2027&period=Bulanan&month=2026-11')->assertOk()->assertSee('Pemanggilan Orang Tua');
    }
    private function excel(array $columns, array $rows): UploadedFile
    {
        $book=new Spreadsheet();$sh=$book->getActiveSheet();
        foreach ($columns as $i=>$label) $sh->setCellValue([$i+1,1],$label);
        foreach ($rows as $n=>$row) foreach ($row as $i=>$value) $sh->setCellValueExplicit([$i+1,$n+2],(string) $value,DataType::TYPE_STRING);
        $temp=tempnam(sys_get_temp_dir(),'tahap5_');(new Xlsx($book))->save($temp);$book->disconnectWorksheets();
        return new UploadedFile($temp,'data.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',null,true);
    }
    public function test_import_students_with_optional_nis_and_prevent_ambiguous_names(): void
    {
        $this->actingAs($this->admin());Classroom::create(['class_name'=>'VII A','academic_year'=>'2026/2027']);
        $file=$this->excel(['NOMOR','NIS','NISN','NAMA','JENIS KELAMIN','KELAS'],[[1,'','003455','SISWA TANPA NIS','P','VII A']]);
        $this->post('/imports',['type'=>'students','academic_year'=>'2026/2027','existing_action'=>'update','file'=>$file])->assertRedirect();
        $this->assertImportCounts(1, 0);
        $student=Student::sole();self::assertNull($student->nis);self::assertSame('003455',$student->nisn);
        $this->get('/monthly?academic_year=2026%2F2027&month=2026-10')->assertSee('SISWA TANPA NIS');
        $this->get('/excel/template?type=monthly')->assertOk()->assertHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
    public function test_promotion_and_graduation_keep_old_absences():void
    {
        $this->actingAs($this->admin()); [$from,$s]=$this->classAndStudent();
        $to=Classroom::create(['class_name'=>'VIII A','academic_year'=>'2027/2028']);
        MonthlyAbsence::create(['student_id'=>$s->id,'class_id'=>$from->id,'academic_year'=>'2026/2027','month_start'=>'2026-10-01','alpa_jp'=>48]);
        $this->post('/progression/promote',['source_class_id'=>$from->id,'target_class_id'=>$to->id,'student_ids'=>[$s->id],'confirm'=>1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_class_history',['student_id'=>$s->id,'old_class_id'=>$from->id,'new_class_id'=>$to->id]);
        $this->assertDatabaseHas('monthly_absence',['student_id'=>$s->id,'class_id'=>$from->id,'alpa_jp'=>48]);
        $this->post('/progression/status',['source_class_id'=>$to->id,'student_ids'=>[$s->id],'status'=>'Lulus','effective_date'=>'2028-06-30','confirm'=>1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('students',['id'=>$s->id,'active'=>false,'student_status'=>'Lulus']);
        $this->assertDatabaseHas('student_status_history',['student_id'=>$s->id,'new_status'=>'Lulus']);
        $this->get('/reports?academic_year=2026%2F2027&period=Ganjil')->assertSee('Penanganan BP');
    }
    public function test_reset_requires_password_and_keeps_admin_account():void
    {
        $admin=$this->admin();$this->actingAs($admin);[$c,$s]=$this->classAndStudent();
        MonthlyAbsence::create(['student_id'=>$s->id,'class_id'=>$c->id,'academic_year'=>'2026/2027','month_start'=>'2026-10-01','alpa_jp'=>24]);
        $input=['scope'=>'all','confirmation'=>'HAPUS DATA UJI','backup_acknowledged'=>1,'password'=>'salah'];
        $this->post('/settings/reset',$input)->assertSessionHasErrors('password');
        $this->assertDatabaseCount('students',1);
        $this->post('/settings/reset',array_merge($input,['password'=>'PasswordTesting123!']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('students',0);
        $this->assertDatabaseCount('classes',0);
        $this->assertDatabaseHas('users',['id'=>$admin->id]);
    }
}
