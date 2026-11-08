<?php
namespace Tests\Feature;

use App\Http\Controllers\ExcelController;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\ExcelWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Tahap4ExcelAdminBackupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['full_name' => 'Administrator', 'username' => 'admincheck',
            'password' => 'LongPasswordTesting123!', 'role' => 'admin', 'active' => true]);
    }

    private function file(array $headers, array $rows): UploadedFile
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        foreach ($headers as $i => $v) $sheet->setCellValueExplicit([$i + 1, 1], $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        foreach ($rows as $r => $row) foreach ($row as $c => $value) {
            $sheet->setCellValueExplicit([$c + 1, $r + 2], (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        $path = tempnam(sys_get_temp_dir(), 'lamalif_import_');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        return new UploadedFile($path, 'uji.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_student_template_headers_are_in_exact_school_order(): void
    {
        self::assertSame(['NOMOR', 'NIS', 'NISN', 'NAMA', 'JENIS KELAMIN', 'KELAS', 'STATUS PONDOK', 'NAMA PONDOK'], ExcelController::displayHeaders('students'));
    }

    public function test_combined_class_and_teacher_import_works(): void
    {
        $this->actingAs($this->admin());
        $file = $this->file(['KELAS', 'WALI KELAS'], [['VII A', 'Bapak Wali'], ['VII B', 'Ibu Wali']]);
        $this->post('/imports', ['type' => 'classes', 'academic_year' => '2026/2027', 'existing_action' => 'update', 'file' => $file])
            ->assertSessionHas('import_summary', fn ($s) => $s['baru'] === 2 && $s['gagal'] === 0);
        $this->assertDatabaseHas('classes', ['class_name' => 'VII A', 'academic_year' => '2026/2027', 'homeroom_teacher' => 'Bapak Wali']);
    }

    public function test_student_import_accepts_school_columns_and_preserves_leading_zeros(): void
    {
        $this->actingAs($this->admin());
        Classroom::create(['class_name' => 'VII A', 'academic_year' => '2026/2027']);
        $file = $this->file(['NOMOR', 'NIS', 'NISN', 'NAMA', 'JENIS KELAMIN', 'KELAS'], [
            ['1', '0000123', '0012345678', 'CONTOH SISWA', 'Laki-laki', 'VII A'],
            ['2', '0000124', '0012345679', 'CONTOH SISWI', 'Perempuan', 'VII A'],
        ]);
        $this->post('/imports', ['type' => 'students', 'academic_year' => '2026/2027', 'existing_action' => 'update', 'file' => $file])
            ->assertSessionHas('import_summary', fn ($s) => $s['baru'] === 2 && $s['gagal'] === 0);
        $this->assertDatabaseHas('students', ['nis' => '0000123', 'nisn' => '0012345678', 'gender' => 'L']);
        $this->assertDatabaseHas('students', ['nis' => '0000124', 'nisn' => '0012345679', 'gender' => 'P']);
    }

    public function test_account_management_cannot_disable_only_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->put('/settings/users/'.$admin->id, [
            'full_name' => 'Administrator', 'username' => 'admincheck', 'role' => 'petugas', 'active' => 0,
        ])->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin', 'active' => 1]);
    }

    public function test_user_settings_forbidden_to_petugas_and_backup_requires_admin(): void
    {
        $petugas = User::create(['full_name' => 'Petugas', 'username' => 'petugascheck',
            'password' => 'LongPasswordTesting123!', 'role' => 'petugas', 'active' => true]);
        $this->actingAs($petugas);
        $this->get('/settings/users')->assertForbidden();
        $this->get('/settings/backup')->assertForbidden();
        $this->post('/settings/backup/download', ['password' => 'LongPasswordTesting123!'])->assertForbidden();
    }
}
