<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fee extends Model
{
    protected $guarded = [];

    public function classroom()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
