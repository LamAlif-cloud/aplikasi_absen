<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Student;
use App\Services\AbsenceReport;
use App\Services\AcademicPeriod;
use App\Services\Discipline;
use App\Services\YearOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DisciplinaryActionController
{
    public function index(Request $request): View
    {
        $year = YearOptions::selected($request->query('academic_year'));
        $semester = in_array($request->query('semester'),['Ganjil','Genap'],true) ? $request->query('semester') : AcademicPeriod::currentSemester();
        $eligible = AbsenceReport::query($year,$semester)->get()->map(fn($r)=>(object)array_merge((array)$r,['level'=>Discipline::level((int)$r->alpa_jp)]))->filter(fn($r)=>$r->level>0)->values();
        $actions = DB::table('disciplinary_actions')->where('academic_year',$year)->where('semester',$semester)->get()->keyBy(fn($a)=>$a->student_id.':'.$a->level);
        return view('discipline.index',compact('year','semester','eligible','actions')+['years'=>YearOptions::all()]);
    }
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id'=>['required','integer',Rule::exists('students','id')],
            'academic_year'=>['required','string'],
            'semester'=>['required',Rule::in(['Ganjil','Genap'])],
            'level'=>['required','integer',Rule::in([1,2,3])],
            'status'=>['required',Rule::in(['pending','processed','completed'])],
            'action_date'=>['nullable','date_format:Y-m-d'],
            'note'=>['nullable','string','max:500'],
        ]);
        if (!AcademicPeriod::isValidYear($data['academic_year'])) throw ValidationException::withMessages(['academic_year'=>'Tahun pelajaran tidak valid.']);
        DB::transaction(function() use($data,$request):void {
            Student::whereKey($data['student_id'])->lockForUpdate()->firstOrFail();
            $jp = AbsenceReport::studentAlpa((int)$data['student_id'],$data['academic_year'],$data['semester']);
            if (Discipline::level($jp)<$data['level']) throw ValidationException::withMessages(['level'=>'Ambang Alpa belum mencapai tahap tersebut.']);
            if ($data['status'] !== 'pending' && !$data['action_date']) throw ValidationException::withMessages(['action_date'=>'Isi tanggal penanganan untuk status Diproses atau Selesai.']);
            $identity = ['student_id'=>$data['student_id'],'academic_year'=>$data['academic_year'],
                'semester'=>$data['semester'],'level'=>$data['level']];
            $fields = ['status'=>$data['status'],'action_date'=>$data['action_date']??null,
                'note'=>$data['note']??null,'handled_by'=>Auth::id(),'updated_at'=>now()];
            if (DB::table('disciplinary_actions')->where($identity)->exists()) DB::table('disciplinary_actions')->where($identity)->update($fields);
            else DB::table('disciplinary_actions')->insert($identity + $fields + ['created_at'=>now()]);
            AuditLog::create(['user_id'=>Auth::id(),'action'=>'disciplinary_action','detail'=>'Siswa '.$data['student_id'].' tahap '.$data['level'].' '.$data['status'],'ip_address'=>$request->ip()]);
        },3);
        return redirect()->route('discipline.index',['academic_year'=>$data['academic_year'],'semester'=>$data['semester']])->with('success','Tindak lanjut pembinaan tersimpan.');
    }
}
