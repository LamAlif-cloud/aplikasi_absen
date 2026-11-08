<?php
namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PondokExcelTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        $user = User::create([
            'full_name' => 'Administrator Uji', 'username' => 'pondok_admin',
            'password' => 'TestPassword12345!', 'role' => 'admin', 'active' => true,
        ]);
        $this->actingAs($user);
    }

    private function workbook(array $headers, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($headers as $column => $label) {
            $sheet->setCellValueExplicit([$column + 1, 1], $label, DataType::TYPE_STRING);
        }
        foreach ($rows as $rowIndex => $values) {
            foreach ($values as $column => $value) {
                $sheet->setCellValueExplicit([$column + 1, $rowIndex + 2], (string) $value, DataType::TYPE_STRING);
            }
        }
        $file = tempnam(sys_get_temp_dir(), 'lamalif_pondok_');
        (new Xlsx($spreadsheet))->save($file);
        $spreadsheet->disconnectWorksheets();
        return new UploadedFile($file, 'data-siswa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function importStudents(UploadedFile $file, string $action = 'update'): \Illuminate\Testing\TestResponse
    {
        return $this->post('/imports', [
            'type' => 'students', 'academic_year' => '2026/2027',
            'existing_action' => $action, 'file' => $file,
        ]);
    }

    public function test_pondok_fields_update_existing_student_without_changing_identity_or_absence(): void
    {
        $this->signIn();
        $class = Classroom::create(['class_name' => 'VII A', 'academic_year' => '2026/2027']);
        $student = Student::create([
            'nis' => null, 'nisn' => '0012345678', 'full_name' => 'SISWA TEST',
            'gender' => 'L', 'class_id' => $class->id,
        ]);
        $headers = ['NOMOR', 'NIS', 'NISN', 'NAMA', 'JENIS KELAMIN', 'KELAS', 'STATUS PONDOK', 'NAMA PONDOK'];
        $file = $this->workbook($headers, [[1, '', '0012345678', 'SISWA TEST', 'L', 'VII A', 'Mondok', 'Pondok Alif']]);
        $this->importStudents($file)->assertSessionHas('import_summary', fn ($summary) =>
            $summary['baru'] === 0 && $summary['diperbarui'] === 1 && $summary['gagal'] === 0
        );
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseHas('students', [
            'id' => $student->id, 'class_id' => $class->id, 'nisn' => '0012345678',
            'boarding_status' => 'Mondok', 'boarding_school' => 'Pondok Alif',
        ]);
        $this->get('/students?academic_year=2026%2F2027&boarding=Mondok&pondok=Alif')
            ->assertOk()->assertSee('SISWA TEST')->assertSee('Pondok Alif');
    }

    public function test_old_six_column_template_still_works_without_erasing_pondok(): void
    {
        $this->signIn();
        $class = Classroom::create(['class_name' => 'VII B', 'academic_year' => '2026/2027']);
        $student = Student::create([
            'nis' => '25001', 'nisn' => '0001234567', 'full_name' => 'TEST LAMA', 'gender' => 'P',
            'class_id' => $class->id, 'boarding_status' => 'Mondok', 'boarding_school' => 'Pondok Lama',
        ]);
        $old = ['NOMOR', 'NIS', 'NISN', 'NAMA', 'JENIS KELAMIN', 'KELAS'];
        $this->importStudents($this->workbook($old, [[1, '25001', '0001234567', 'TEST LAMA', 'P', 'VII B']]))
            ->assertSessionHas('import_summary', fn ($summary) => $summary['gagal'] === 0 && $summary['baru'] === 0);
        $this->assertDatabaseHas('students', [
            'id' => $student->id, 'boarding_status' => 'Mondok', 'boarding_school' => 'Pondok Lama',
        ]);
    }

    public function test_invalid_pondok_combination_is_rejected_without_changing_student(): void
    {
        $this->signIn();
        $class = Classroom::create(['class_name' => 'VII C', 'academic_year' => '2026/2027']);
        $student = Student::create([
            'nisn' => '0001234000', 'full_name' => 'SISWA KELIRU', 'gender' => 'L', 'class_id' => $class->id,
        ]);
        $headers = ['NOMOR', 'NIS', 'NISN', 'NAMA', 'JENIS KELAMIN', 'KELAS', 'STATUS PONDOK', 'NAMA PONDOK'];
        $this->importStudents($this->workbook($headers, [[1, '', '0001234000', 'SISWA KELIRU', 'L', 'VII C', 'Tidak', 'Pondok A']]))
            ->assertSessionHas('import_summary', fn ($summary) =>
                $summary['gagal'] === 1 && str_contains(implode(' ', $summary['errors']), 'harus kosong')
            );
        $this->assertDatabaseHas('students', ['id' => $student->id, 'boarding_status' => 'Tidak']);
    }

    public function test_blank_pondok_columns_preserve_previous_values_and_skip_mode_does_not_change(): void
    {
        $this->signIn();
        $class = Classroom::create(['class_name' => 'VIII A', 'academic_year' => '2026/2027']);
        $student = Student::create([
            'nisn' => '0001234111', 'full_name' => 'SISWA LAMA', 'gender' => 'L', 'class_id' => $class->id,
            'boarding_status' => 'Mondok', 'boarding_school' => 'Pesantren Satu',
        ]);
        $headers = ['NOMOR', 'NIS', 'NISN', 'NAMA', 'JENIS KELAMIN', 'KELAS', 'STATUS PONDOK', 'NAMA PONDOK'];
        $this->importStudents($this->workbook($headers, [[1, '', '0001234111', 'SISWA LAMA', 'L', 'VIII A', '', '']]))
            ->assertSessionHas('import_summary', fn ($summary) => $summary['gagal'] === 0);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'boarding_school' => 'Pesantren Satu']);
        $this->importStudents($this->workbook($headers, [[1, '', '0001234111', 'SISWA LAMA', 'L', 'VIII A', 'Tidak', '']]), 'skip')
            ->assertSessionHas('import_summary', fn ($summary) => $summary['dilewati'] === 1);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'boarding_status' => 'Mondok']);
    }
}
