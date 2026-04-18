<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timetable extends Model
{
    protected $fillable = [
        'section_assignment_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room_number',
        'status',
    ];

    public function sectionAssignments() { 
        return $this->belongsTo(SectionAssignment::class); 
    }
}
