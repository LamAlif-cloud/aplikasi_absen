<?php
namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Models\WeeklyAbsence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function seedSimple(): array
    {
        $admin = User::create(['full_name' => 'Admin Test', 'username' => 'admin_test', 'password' => 'SecureTestPass123!', 'role' => 'admin', 'active' => true]);
        $class = Classroom::create(['class_name' => 'VII A', 'academic_year' => '2026/2027']);
        $student = Student::create(['nis' => 'TES-001', 'full_name' => 'SISWA TEST', 'gender' => 'L', 'class_id' => $class->id]);
        return [$admin, $class, $student];
    }

    public function test_guest_must_log_in(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
    public function test_correct_and_incorrect_password(): void
    {
        [$admin] = $this->seedSimple();
        $this->post('/login', ['username' => $admin->username, 'password' => 'salah'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => $admin->username, 'password' => 'SecureTestPass123!'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
    }
    public function test_weekly_save_report_and_letter_share_the_same_semester_alpa(): void
    {
        [$admin, $class, $student] = $this->seedSimple();
        $this->actingAs($admin);
        $this->post('/weekly', [
            'academic_year' => '2026/2027', 'class_id' => $class->id, 'week_start' => '2026-10-12',
            'rows' => [['student_id' => $student->id, 'alpa_jp' => 72, 'izin_jp' => 1, 'sakit_jp' => 2]],
        ])->assertRedirect();
        self::assertSame(72, WeeklyAbsence::where('student_id', $student->id)->sum('alpa_jp'));
        $this->get('/reports?academic_year=2026%2F2027&period=Ganjil')->assertOk()->assertSee('Pemanggilan Orang Tua');
        $this->post('/letters', [
            'student_id' => $student->id, 'academic_year' => '2026/2027', 'semester' => 'Ganjil', 'level' => 3,
            'letter_number' => 'TEST/01', 'letter_date' => '2026-10-19',
        ])->assertRedirect();
        self::assertDatabaseHas('disciplinary_letters', ['student_id' => $student->id, 'level' => 3, 'status' => 'draft']);
    }
    public function test_class_filter_respects_year(): void
    {
        [$admin] = $this->seedSimple();
        Classroom::create(['class_name' => 'VIII B', 'academic_year' => '2027/2028']);
        $this->actingAs($admin);
        $this->get('/classes?academic_year=2026%2F2027')->assertOk()
            ->assertSee('1 kelas untuk 2026/2027')
            ->assertSee('VII A')
            ->assertDontSee('VIII B');
    }
    public function test_petugas_cannot_import(): void
    {
        $operator = User::create(['full_name' => 'Petugas', 'username' => 'operator', 'password' => 'StrongTestPass123!', 'role' => 'petugas', 'active' => true]);
        $this->actingAs($operator)->get('/imports')->assertForbidden();
    }
}
