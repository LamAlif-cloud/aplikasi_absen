<?php
namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Models\WeeklyAbsence;
use App\Models\MonthlyAbsence;
use App\Services\AbsenceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function setupEntry(string $gender = 'L'): array
    {
        $user = User::create(['full_name'=>'Operator Login','username'=>'operator','password'=>'PasswordTest123!','role'=>'petugas','active'=>true]);
        $class = Classroom::create(['class_name'=>'VII A','academic_year'=>'2026/2027']);
        $student = Student::create(['full_name'=>'Siswa Koreksi','nis'=>'001','gender'=>$gender,'class_id'=>$class->id]);
        $this->actingAs($user);
        return [$user,$class,$student];
    }

    public function test_operator_can_correct_all_sources_and_reports_use_updated_values(): void
    {
        [, $class, $student] = $this->setupEntry();
        $sources = ['weekly'=>['weekly_absence','week_start','2026-11-02'], 'monthly'=>['monthly_absence','month_start','2026-12-01'], 'opening'=>['opening_absence','as_of_date','2026-10-31']];
        foreach ($sources as $mode=>[$table,$date,$value]) {
            $row = ['student_id'=>$student->id,'class_id'=>$class->id,'academic_year'=>'2026/2027',$date=>$value,'alpa_jp'=>24,'sakit_jp'=>0,'izin_jp'=>0,'created_at'=>now(),'updated_at'=>now()];
            if ($mode === 'opening') $row['semester'] = 'Ganjil';
            $id = DB::table($table)->insertGetId($row);
            $page = $this->get('/attendance-corrections/'.$mode.'/'.$id.'/edit')->assertOk();
            $this->put('/attendance-corrections/'.$mode.'/'.$id, ['sakit_jp'=>0,'izin_jp'=>8,'alpa_jp'=>16,'reason'=>'Sesuai surat izin','version'=>$page->viewData('version')])
                ->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas($table,['id'=>$id,'alpa_jp'=>16,'izin_jp'=>8,'class_id'=>$class->id,$date=>$value]);
        }
        self::assertSame(48, AbsenceReport::studentAlpa($student->id,'2026/2027','Ganjil'));
        $this->assertDatabaseCount('audit_logs',3);
        $this->get('/attendance-corrections?academic_year=2026%2F2027')->assertOk()->assertSee('Siswa Koreksi');
        $this->get('/reports/cetak?academic_year=2026%2F2027&period=Ganjil')->assertOk()->assertSee('6 hari');
    }

    public function test_conflicting_update_and_missing_reason_are_rejected_for_historical_student(): void
    {
        [, $class, $student] = $this->setupEntry();
        $entry = WeeklyAbsence::create(['student_id'=>$student->id,'class_id'=>$class->id,'academic_year'=>'2026/2027','week_start'=>'2026-11-02','alpa_jp'=>24]);
        $student->update(['active'=>false]);
        $page = $this->get('/attendance-corrections/weekly/'.$entry->id.'/edit')->assertOk();
        $payload = ['sakit_jp'=>0,'izin_jp'=>0,'alpa_jp'=>10,'version'=>$page->viewData('version')];
        $this->put('/attendance-corrections/weekly/'.$entry->id,$payload)->assertSessionHasErrors('reason');
        $entry->update(['alpa_jp'=>16]);
        $this->put('/attendance-corrections/weekly/'.$entry->id,$payload+['reason'=>'Koreksi'])->assertSessionHasErrors('version');
        $this->assertDatabaseHas('weekly_absence',['id'=>$entry->id,'alpa_jp'=>16]);
    }

    public function test_only_admin_and_operator_can_correct_and_invalid_totals_are_rejected(): void
    {
        [$user,$class,$student] = $this->setupEntry();
        $entry = WeeklyAbsence::create(['student_id'=>$student->id,'class_id'=>$class->id,'academic_year'=>'2026/2027','week_start'=>'2026-11-02','alpa_jp'=>24]);
        $page = $this->get('/attendance-corrections/weekly/'.$entry->id.'/edit')->assertOk();
        $payload = ['sakit_jp'=>80,'izin_jp'=>80,'alpa_jp'=>80,'reason'=>'Koreksi','version'=>$page->viewData('version')];
        $this->put('/attendance-corrections/weekly/'.$entry->id,$payload)->assertSessionHasErrors('alpa_jp');
        $user->update(['role'=>'kepala']);
        $this->get('/attendance-corrections')->assertForbidden();
        $this->put('/attendance-corrections/weekly/'.$entry->id,$payload)->assertForbidden();
    }

    public function test_profile_operator_names_follow_gender_and_single_name_fallback(): void
    {
        [$user,$class,$student] = $this->setupEntry();
        $user->update(['role'=>'admin']);
        $this->put('/settings/school',['school_name'=>'Madrasah Uji','operator_male_name'=>'Petugas Putra Uji','operator_female_name'=>'Petugas Putri Uji'])->assertSessionHasNoErrors();
        $url = '/reports/cetak?academic_year=2026%2F2027&period=Bulanan&month=2026-10&class_id='.$class->id;
        $this->get($url)->assertOk()->assertSee('Petugas Putra Uji')->assertDontSee('Petugas Putri Uji');
        $student->update(['gender'=>'P']);
        $this->get($url)->assertOk()->assertSee('Petugas Putri Uji')->assertDontSee('Petugas Putra Uji');
        Student::create(['full_name'=>'Siswa Putra','nis'=>'002','gender'=>'L','class_id'=>$class->id]);
        $this->get($url)->assertOk()->assertSee('Petugas Putra Uji')->assertSee('Petugas Putri Uji');
        DB::table('school_settings')->where('key','operator_male_name')->update(['value'=>'']);
        $this->get($url)->assertOk()->assertSee('Petugas Putri Uji')->assertDontSee('Petugas Putra Uji');
        DB::table('school_settings')->where('key','operator_female_name')->update(['value'=>'']);
        $this->get($url)->assertOk()->assertSee('Operator Login');
        $this->get('/dashboard')->assertOk()->assertSee('Aplikasi Absensi Siswa')->assertDontSee('LamAlif');
    }
}
