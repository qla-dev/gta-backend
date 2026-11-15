<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BleeterComment extends Model
{
    protected $fillable = ['user_id', 'bleeter_post_id', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BleeterPost::class, 'bleeter_post_id');
    }
}
