<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = [
        'name',
        'code',
        'start_date',
        'end_date',
        'status',
    ];

    public function sectionAssignments()
    {
        return $this->hasMany(SectionAssignment::class, 'section_id');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'enrolments', 'section_id', 'student_id')
                    ->withPivot('status', 'note')
                    ->withTimestamps();
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }
}
