<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lecturer extends Model
{
    protected $fillable = [
        'user_id',
        'employee_id',
        'department',
        'qualification',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sectionAssignments()
    {
        return $this->hasMany(SectionAssignment::class, 'lecturer_id');
    }
}
