<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'setting';

    protected $fillable = [
        'school_name',
        'slogan',
        'description',
        'school_logo',
        'background_image',
        'school_color',
    ];
}
