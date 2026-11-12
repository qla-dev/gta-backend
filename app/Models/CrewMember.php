<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewMember extends Model
{
    protected $fillable = ['crew_id', 'user_id', 'role'];

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
