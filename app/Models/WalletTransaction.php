<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = ['user_id', 'title', 'amount', 'icon'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
