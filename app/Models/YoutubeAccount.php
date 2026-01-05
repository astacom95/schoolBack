<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YoutubeAccount extends Model
{
    protected $fillable = [
        'channel_id',
        'channel_title',
        'refresh_token',
        'connected_at',
        'is_active',
    ];

    // Laravel encryption at rest
    protected $casts = [
        'refresh_token' => 'encrypted',
        'connected_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
