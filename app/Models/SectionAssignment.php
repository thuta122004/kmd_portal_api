<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SectionAssignment extends Model
{
    protected $fillable = [
        'section_id',
        'subject_id',
        'lecturer_id',
        'is_primary',
        'status',
    ];

    public function section() { 
        return $this->belongsTo(Section::class); 
    }
    
    public function subject() {
        return $this->belongsTo(Subject::class);
    }

    public function lecturer() {
        return $this->belongsTo(Lecturer::class, 'lecturer_id');
    }

    public function timetables()
    {
        return $this->hasMany(Timetable::class, 'section_assignments_id');
    }
}
