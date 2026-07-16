<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'section_id',
        'title',
        'content',
        'banner_photo',
        'is_pinned',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
