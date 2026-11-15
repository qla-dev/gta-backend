<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBusiness extends Model
{
    protected $fillable = ['user_id', 'business_id', 'owned', 'level', 'status'];

    protected $casts = ['owned' => 'boolean', 'level' => 'integer'];
}
