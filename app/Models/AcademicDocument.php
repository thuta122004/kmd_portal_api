<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDocument extends Model
{
    protected $fillable = [
        'student_id',
        'document_type',
        'title',
        'file_path',
        'is_verified'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
