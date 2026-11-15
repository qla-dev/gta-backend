<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BleeterPost extends Model
{
    protected $fillable = ['user_id', 'channel_id', 'body', 'likes_count'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(BleeterChannel::class, 'channel_id');
    }
}
