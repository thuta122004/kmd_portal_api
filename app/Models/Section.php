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
}
