<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timetable extends Model
{
    protected $fillable = [
        'section_assignments_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room_number',
        'status',
    ];

    public function sectionAssignments() { 
        return $this->belongsTo(SectionAssignment::class); 
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'timetable_id');
    }
}
