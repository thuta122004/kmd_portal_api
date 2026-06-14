<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = [
        'name',
        'code',
        'subject',
    ];

    public function sectionAssignments()
    {
        return $this->hasMany(SectionAssignment::class, 'subject_id');
    }
}
