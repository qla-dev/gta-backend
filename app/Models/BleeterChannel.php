<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BleeterChannel extends Model
{
    protected $fillable = ['name', 'description', 'icon', 'color', 'sort_order'];

    public function posts(): HasMany
    {
        return $this->hasMany(BleeterPost::class, 'channel_id');
    }
}
