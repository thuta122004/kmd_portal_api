<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SectionAssignment extends Model
{
    protected $fillable = [
        'section_id',
        'subject_id',
        'user_id',
        'is_primary',
        'status',
    ];

    public function section() { 
        return $this->belongsTo(Section::class); 
    }
    public function subject() {
        return $this->belongsTo(Subject::class);
    }
    public function user() {
        return $this->belongsTo(User::class);
    }
}
