<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'student_reg_number',
        'date_of_birth',
        'gender',
        'phone',
        'address',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guardians()
    {
        return $this->belongsToMany(Guardian::class, 'relationships')
                    ->withPivot('relationship_type', 'is_primary_contact')
                    ->withTimestamps();
    }
}
