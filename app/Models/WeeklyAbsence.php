<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyAbsence extends Model
{
    protected $table = 'weekly_absence';
    protected $fillable = ['student_id', 'class_id', 'academic_year', 'week_start', 'alpa_jp', 'izin_jp', 'sakit_jp', 'note', 'created_by'];
    protected $casts = ['week_start' => 'date:Y-m-d', 'alpa_jp' => 'integer', 'izin_jp' => 'integer', 'sakit_jp' => 'integer'];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class, 'class_id'); }
}
