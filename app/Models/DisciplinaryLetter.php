<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryLetter extends Model
{
    protected $fillable = ['student_id', 'academic_year', 'semester', 'level', 'letter_number', 'letter_date', 'meeting_date', 'notes', 'status', 'created_by'];
    protected $casts = ['level' => 'integer', 'letter_date' => 'date', 'meeting_date' => 'date'];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
