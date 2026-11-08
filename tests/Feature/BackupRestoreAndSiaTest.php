<?php
namespace Tests\Feature;

use App\Http\Controllers\ExcelController;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupRestoreAndSiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_monthly_and_excel_follow_sakit_izin_alpa_column_order(): void
    {
        $admin = User::create(['full_name'=>'Admin Test','username'=>'siacheck','password'=>'VerySecurePassword2026!','role'=>'admin','active'=>true]);
        $class = Classroom::create(['class_name'=>'VII A','academic_year'=>'2026/2027']);
        Student::create(['full_name'=>'SISWA UJI SIA','nis'=>null,'gender'=>'P','class_id'=>$class->id]);
        $this->actingAs($admin);
        $this->get('/weekly?academic_year=2026%2F2027&class_id='.$class->id.'&week_start=2026-10-19')
            ->assertOk()->assertSeeInOrder(['Sakit (JP)', 'Izin (JP)', 'Alpa (JP)']);
        $this->get('/monthly?academic_year=2026%2F2027&class_id='.$class->id.'&month=2026-10')
            ->assertOk()->assertSeeInOrder(['Sakit JP', 'Izin JP', 'Alpa JP']);
        self::assertSame(['SAKIT JP','IZIN JP','ALPA JP'], array_slice(ExcelController::displayHeaders('weekly'), 5, 3));
        self::assertSame(['SAKIT JP','IZIN JP','ALPA JP'], array_slice(ExcelController::displayHeaders('monthly'), 5, 3));
    }

    public function test_restore_routes_are_restricted_to_admin(): void
    {
        $operator = User::create(['full_name'=>'Petugas','username'=>'restoredisabled','password'=>'VerySecurePassword2026!','role'=>'petugas','active'=>true]);
        $this->actingAs($operator);
        $this->post('/settings/backup/restore/preview')->assertForbidden();
        $this->post('/settings/backup/restore/confirm')->assertForbidden();
    }

    public function test_restore_rejects_unconfirmed_requests_in_local_testing(): void
    {
        $admin = User::create(['full_name'=>'Admin','username'=>'restoreadmin','password'=>'VerySecurePassword2026!','role'=>'admin','active'=>true]);
        $this->actingAs($admin);
        // SQLite (unit test DB) is intentionally not eligible for browser-based restore.
        $this->post('/settings/backup/restore/confirm', [
            'password'=>'VerySecurePassword2026!', 'confirmation'=>'PULIHKAN DATABASE', 'understand_replace'=>1,
        ])->assertForbidden();
    }
}
