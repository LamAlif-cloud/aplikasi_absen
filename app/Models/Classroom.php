<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    protected $table = 'classes';
    protected $fillable = ['class_name', 'academic_year', 'homeroom_teacher'];
    public function students(): HasMany { return $this->hasMany(Student::class, 'class_id'); }
    public function scopeForYear($query, string $year) { return $query->where('academic_year', $year); }
}
