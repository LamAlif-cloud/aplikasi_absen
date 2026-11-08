<?php
namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\AbsenceEntries;
use App\Services\AbsenceReport;
use App\Services\Discipline;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentHistoryController
{
    public function show(Student $student): View
    {
        $student->load('classroom');
        $entries = AbsenceEntries::query()->join('classes as c','c.id','=','entries.class_id')
            ->where('entries.student_id',$student->id)
            ->select('entries.academic_year','entries.entry_mode','entries.entry_date','entries.alpa_jp','entries.izin_jp','entries.sakit_jp','c.class_name')
            ->orderBy('entries.entry_date')->get();
        $years = $entries->pluck('academic_year')->unique()->values();
        $summaries = $years->map(fn($year)=>(object)[
            'year'=>$year,
            'ganjil'=>AbsenceReport::studentAlpa($student->id,$year,'Ganjil'),
            'genap'=>AbsenceReport::studentAlpa($student->id,$year,'Genap'),
        ]);
        $classes = DB::table('student_class_history as h')->join('classes as old','old.id','=','h.old_class_id')
            ->join('classes as new','new.id','=','h.new_class_id')->where('h.student_id',$student->id)
            ->select('old.class_name as old_class','new.class_name as new_class','h.academic_year_from','h.academic_year_to','h.created_at')
            ->orderBy('h.created_at')->get();
        $statuses = DB::table('student_status_history')->where('student_id',$student->id)->orderBy('effective_date')->get();
        $actions = DB::table('disciplinary_actions')->where('student_id',$student->id)->orderBy('academic_year')->orderBy('semester')->orderBy('level')->get();
        $letters = DB::table('disciplinary_letters')->where('student_id',$student->id)->orderBy('letter_date')->get();
        return view('students.history',compact('student','entries','summaries','classes','statuses','actions','letters'));
    }
}
