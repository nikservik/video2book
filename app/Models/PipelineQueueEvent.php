<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineQueueEvent extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'stream',
        'event',
        'payload',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
    ];
}
