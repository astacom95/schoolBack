<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PapersWork extends Model
{
    protected $table = 'papers_work';
    protected $guarded = [];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function classroom()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
