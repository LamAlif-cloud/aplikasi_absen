<?php
namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Models\WeeklyAbsence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrudSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['full_name'=>'Admin QA','username'=>'admin_qa','password'=>'SafePasswordTest123!','role'=>'admin','active'=>true]);
    }
    private function petugas(): User
    {
        return User::create(['full_name'=>'Petugas QA','username'=>'petugas_qa','password'=>'SafePasswordTest123!','role'=>'petugas','active'=>true]);
    }
    private function classWithStudent(): array
    {
        $c = Classroom::create(['class_name'=>'VII A','academic_year'=>'2026/2027','homeroom_teacher'=>'Bapak A']);
        $s = Student::create(['nis'=>'0000123','full_name'=>'SISWA CONTOH','gender'=>'L','class_id'=>$c->id]);
        return [$c,$s];
    }
    public function test_student_can_be_seen_on_weekly_input_after_created(): void
    {
        $this->actingAs($this->admin());
        [$class,$student] = $this->classWithStudent();
        $this->get('/students?academic_year=2026%2F2027&class_id='.$class->id)->assertOk()->assertSee('SISWA CONTOH');
        $this->get('/weekly?academic_year=2026%2F2027&class_id='.$class->id.'&week_start=2026-10-12')
            ->assertOk()->assertSee('SISWA CONTOH')->assertSee('1 siswa aktif');
        $this->post('/weekly', [
            'academic_year'=>'2026/2027','class_id'=>$class->id,'week_start'=>'2026-10-12',
            'rows'=>[['student_id'=>$student->id,'alpa_jp'=>24,'izin_jp'=>0,'sakit_jp'=>0]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/reports?academic_year=2026%2F2027&period=Ganjil')->assertOk()
            ->assertSee('SISWA CONTOH')->assertSee('Peringatan Wali Kelas');
    }
    public function test_initial_weekly_selection_prefers_enrolled_class_over_empty_class(): void
    {
        $this->actingAs($this->admin());
        Classroom::create(['class_name'=>'VI A','academic_year'=>'2026/2027']);
        [$class] = $this->classWithStudent();
        $this->get('/weekly?academic_year=2026%2F2027')->assertOk()->assertSee('SISWA CONTOH');
    }
    public function test_edit_class_and_homeroom_teacher_then_remove_assignment(): void
    {
        $this->actingAs($this->admin());
        [$class] = $this->classWithStudent();
        $this->put('/classes/'.$class->id, ['class_name'=>'VII B','homeroom_teacher'=>'Ibu B'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('classes',['id'=>$class->id,'class_name'=>'VII B','homeroom_teacher'=>'Ibu B']);
        $this->get('/teachers?academic_year=2026%2F2027')->assertOk()->assertSee('Ibu B');
        $this->put('/teachers/'.$class->id, ['homeroom_teacher'=>'Ibu C'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('classes',['id'=>$class->id,'homeroom_teacher'=>'Ibu C']);
        $this->delete('/teachers/'.$class->id, ['confirm_delete'=>1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('classes',['id'=>$class->id,'homeroom_teacher'=>null]);
    }
    public function test_cannot_delete_class_with_students_or_student_with_weekly_history(): void
    {
        $this->actingAs($this->admin());
        [$class,$student] = $this->classWithStudent();
        $this->delete('/classes/'.$class->id, ['confirm_delete'=>1])->assertSessionHasErrors('classroom');
        $this->assertDatabaseHas('classes',['id'=>$class->id]);
        WeeklyAbsence::create(['student_id'=>$student->id,'class_id'=>$class->id,'academic_year'=>'2026/2027','week_start'=>'2026-10-12','alpa_jp'=>24,'izin_jp'=>0,'sakit_jp'=>0]);
        $this->delete('/students/'.$student->id, ['confirm_delete'=>1])->assertSessionHasErrors('student');
        $this->assertDatabaseHas('students',['id'=>$student->id]);
        $this->put('/students/'.$student->id, [
            'nis'=>$student->nis,'nisn'=>'','full_name'=>$student->full_name,'gender'=>'L',
            'class_id'=>$class->id,'student_status'=>'Mutasi','active'=>0,
        ])->assertSessionHasNoErrors();
        $this->get('/weekly?academic_year=2026%2F2027&class_id='.$class->id)->assertSee('Belum ada siswa aktif');
        $this->get('/reports?academic_year=2026%2F2027&period=Ganjil')->assertSee('SISWA CONTOH');
    }
    public function test_student_class_change_is_logged_but_prior_weekly_class_remains(): void
    {
        $this->actingAs($this->admin());
        [$old,$student] = $this->classWithStudent();
        $new = Classroom::create(['class_name'=>'VII B','academic_year'=>'2026/2027']);
        WeeklyAbsence::create(['student_id'=>$student->id,'class_id'=>$old->id,'academic_year'=>'2026/2027','week_start'=>'2026-10-12','alpa_jp'=>12,'izin_jp'=>0,'sakit_jp'=>0]);
        $this->put('/students/'.$student->id, [
            'nis'=>$student->nis,'nisn'=>'','full_name'=>$student->full_name,'gender'=>'L',
            'class_id'=>$new->id,'student_status'=>'Aktif','active'=>1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_class_history',['student_id'=>$student->id,'old_class_id'=>$old->id,'new_class_id'=>$new->id]);
        $this->assertDatabaseHas('weekly_absence',['student_id'=>$student->id,'class_id'=>$old->id,'alpa_jp'=>12]);
        $this->get('/weekly?academic_year=2026%2F2027&class_id='.$new->id)->assertSee('SISWA CONTOH');
    }
    public function test_petugas_cannot_edit_or_delete_master_data(): void
    {
        [$class,$student] = $this->classWithStudent();
        $this->actingAs($this->petugas());
        $this->put('/classes/'.$class->id,['class_name'=>'VII X','homeroom_teacher'=>'Wali'])->assertForbidden();
        $this->delete('/students/'.$student->id,['confirm_delete'=>1])->assertForbidden();
        $this->put('/teachers/'.$class->id,['homeroom_teacher'=>'Wali'])->assertForbidden();
    }
    public function test_unlinked_student_and_empty_class_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        [$class,$student] = $this->classWithStudent();
        $this->delete('/students/'.$student->id,['confirm_delete'=>1])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('students',['id'=>$student->id]);
        $this->delete('/classes/'.$class->id,['confirm_delete'=>1])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('classes',['id'=>$class->id]);
    }
    public function test_all_admin_main_menus_open_and_excel_is_only_export_format(): void
    {
        $this->actingAs($this->admin());
        [$class] = $this->classWithStudent();
        foreach (['/dashboard', '/classes', '/teachers', '/students', '/weekly', '/reports', '/letters', '/imports'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/excel/template?type=classes')->assertOk()->assertHeader(
            'Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->get('/excel/export?type=students&academic_year=2026%2F2027')->assertOk();
        $this->get('/reports/export-excel?academic_year=2026%2F2027&period=Ganjil')->assertOk();
    }

    public function test_weekly_cannot_reassign_historic_week_to_new_class(): void
    {
        $this->actingAs($this->admin());
        [$old,$student] = $this->classWithStudent();
        $new = Classroom::create(['class_name'=>'VII B','academic_year'=>'2026/2027']);
        WeeklyAbsence::create(['student_id'=>$student->id,'class_id'=>$old->id,'academic_year'=>'2026/2027','week_start'=>'2026-10-12','alpa_jp'=>4,'izin_jp'=>0,'sakit_jp'=>0]);
        $student->update(['class_id'=>$new->id]);
        $this->post('/weekly', [
            'academic_year'=>'2026/2027','class_id'=>$new->id,'week_start'=>'2026-10-12',
            'rows'=>[['student_id'=>$student->id,'alpa_jp'=>12,'izin_jp'=>0,'sakit_jp'=>0]],
        ])->assertSessionHasErrors('rows');
        $this->assertDatabaseHas('weekly_absence', ['student_id'=>$student->id,'class_id'=>$old->id,'alpa_jp'=>4]);
    }

}
