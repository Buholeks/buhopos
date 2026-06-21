<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mediable extends Model
{
    protected $table = 'mediables';

    protected $fillable = [
        'media_id',
        'mediable_type',
        'mediable_id',
        'role',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
