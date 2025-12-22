<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherTimeTable extends Model
{
    protected $table = 'teacher_time_table';
    protected $guarded = [];

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function classroom()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}
