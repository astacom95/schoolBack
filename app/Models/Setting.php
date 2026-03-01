<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'setting';

    protected $fillable = [
        'school_name',
        'slogan',
        'school_logo',
        'school_color',
    ];
}
