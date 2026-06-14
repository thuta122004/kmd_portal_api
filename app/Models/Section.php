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
}
