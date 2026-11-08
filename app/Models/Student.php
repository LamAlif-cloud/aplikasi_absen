<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = ['nis', 'nisn', 'full_name', 'gender', 'class_id', 'active', 'student_status', 'boarding_status', 'boarding_school', 'status_date', 'status_note'];
    protected $casts = ['active' => 'boolean'];
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class, 'class_id'); }
    public function weeklyAbsences(): HasMany { return $this->hasMany(WeeklyAbsence::class); }
}
