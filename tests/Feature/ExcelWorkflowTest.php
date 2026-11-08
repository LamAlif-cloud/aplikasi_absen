<?php
namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Models\WeeklyAbsence;
use App\Services\Discipline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExcelWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'full_name'=>'Admin', 'username'=>'exceltest', 'password'=>'ExcelTestSecure123!',
            'role'=>'admin', 'active'=>true,
        ]);
    }

    private function workbook(array $headers, array $rows): UploadedFile
    {
        $book = new Spreadsheet(); $sheet = $book->getActiveSheet();
        foreach ($headers as $i => $v) $sheet->setCellValue([ $i + 1, 1 ], $v);
        foreach ($rows as $n => $row) foreach ($row as $i => $value) {
            $sheet->setCellValueExplicit([ $i + 1, $n + 2 ], (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        $temp = tempnam(sys_get_temp_dir(), 'lamalif_xlsx_');
        (new Xlsx($book))->save($temp); $book->disconnectWorksheets();
        return new UploadedFile($temp, 'data.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_import_excel_counts_success_and_failure_per_row(): void
    {
        $this->actingAs($this->admin());
        Classroom::create(['class_name'=>'VII A','academic_year'=>'2026/2027']);
        $file = $this->workbook(['nis','full_name','gender','class_name','nisn'], [
            ['00123', 'SISWA BENAR', 'L', 'VII A', '0012345'],
            ['00124', 'SISWA SALAH', 'L', 'VII Z', '0012346'],
        ]);
        $this->post('/imports', [
            'type'=>'students', 'academic_year'=>'2026/2027', 'existing_action'=>'update', 'file'=>$file,
        ])->assertRedirect();
        $this->assertImportCounts(1, 1);
        self::assertStringContainsString('Baris 3', session('import_summary')['errors'][0]);
        self::assertSame('00123', Student::sole()->nis);
    }

    public function test_excel_template_is_not_csv_and_downloads(): void
    {
        $this->actingAs($this->admin());
        $this->get('/excel/template?type=students')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_failed_rows_persist_and_can_be_corrected_without_bypassing_limits(): void
    {
        $this->actingAs($this->admin());
        $class = Classroom::create(['class_name'=>'VII A','academic_year'=>'2026/2027']);
        $student = Student::create(['nis'=>'001','full_name'=>'Siswa Sakit','gender'=>'L','class_id'=>$class->id]);
        $file = $this->workbook(['nis','class_name','month','sakit_jp','izin_jp','alpa_jp'], [
            ['001','VII A','2026-10','35.75','0','0'],
        ]);
        $this->post('/imports', ['type'=>'monthly','academic_year'=>'2026/2027',
            'existing_action'=>'update','input_unit'=>'days','file'=>$file])->assertRedirect();
        $issue = \App\Models\ImportIssue::sole();
        self::assertNull($issue->resolved_at);
        $this->get('/import-issues')->assertOk()->assertSee('35.75')->assertSee('Periksa');
        $this->get('/import-issues/'.$issue->id.'/edit')->assertOk()->assertSee('Simpan Perbaikan');
        $url = '/import-issues/'.$issue->id;
        $this->put($url, ['row'=>$issue->row_data,'input_unit'=>'days'])->assertSessionHasErrors();
        $this->assertDatabaseCount('monthly_absence', 0);
        self::assertNull($issue->fresh()->resolved_at);
        $row = $issue->row_data;
        $row['sakit_jp'] = '28';
        $this->put($url, ['row'=>$row,'input_unit'=>'jp'])->assertSessionHasNoErrors()->assertRedirect('/import-issues');
        $this->assertDatabaseHas('monthly_absence', ['student_id'=>$student->id,'sakit_jp'=>28,'alpa_jp'=>0]);
        self::assertNotNull($issue->fresh()->resolved_at);
        $this->put($url, ['row'=>$row,'input_unit'=>'jp'])->assertSessionHasErrors();
        $this->assertDatabaseCount('monthly_absence', 1);
        $this->get('/import-issues?status=resolved')->assertOk()->assertSee('Selesai');
        $admin = auth()->user();
        $admin->update(['role'=>'bp']);
        $this->actingAs($admin)->get('/import-issues')->assertForbidden();
        $this->put($url, ['row'=>$row,'input_unit'=>'jp'])->assertForbidden();
    }

    public function test_report_annual_discipline_is_separated_by_semester(): void
    {
        $this->actingAs($this->admin());
        $class = Classroom::create(['class_name'=>'VII A','academic_year'=>'2026/2027']);
        $s = Student::create(['nis'=>'T001','full_name'=>'TEST SISWA','gender'=>'L','class_id'=>$class->id]);
        foreach ([['2026-10-12', 48], ['2027-01-11', 24]] as [$week, $alpa]) {
            WeeklyAbsence::create(['student_id'=>$s->id,'class_id'=>$class->id,'academic_year'=>'2026/2027',
                'week_start'=>$week,'alpa_jp'=>$alpa,'izin_jp'=>0,'sakit_jp'=>0]);
        }
        $this->get('/reports?academic_year=2026%2F2027&period=Tahunan')->assertOk()
            ->assertSee('Penanganan BP')->assertSee('Peringatan Wali Kelas');
        self::assertSame(2, Discipline::level(48));
        self::assertSame(1, Discipline::level(24));
    }
    public function test_weekly_and_monthly_show_semester_status_not_only_period_alpa(): void
    {
        $this->actingAs($this->admin());
        $class = Classroom::create(['class_name'=>'VII A', 'academic_year'=>'2026/2027']);
        $s = Student::create(['nis'=>'R001', 'full_name'=>'SISWA AKUMULASI', 'gender'=>'L', 'class_id'=>$class->id]);
        foreach ([['2026-10-05', 20], ['2026-10-12', 4]] as [$date, $jp]) {
            WeeklyAbsence::create(['student_id'=>$s->id, 'class_id'=>$class->id,
                'academic_year'=>'2026/2027', 'week_start'=>$date,
                'alpa_jp'=>$jp, 'izin_jp'=>10, 'sakit_jp'=>15]);
        }
        $this->get('/reports?academic_year=2026%2F2027&period=Mingguan&week_start=2026-10-12')
            ->assertOk()->assertSee('Peringatan Wali Kelas')->assertSee('SISWA AKUMULASI');
        $this->get('/reports?academic_year=2026%2F2027&period=Bulanan&month=2026-10')
            ->assertOk()->assertSee('Peringatan Wali Kelas')->assertSee('SISWA AKUMULASI');
    }

}
