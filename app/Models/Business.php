<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $casts = [
        'purchase_price' => 'integer', 'base_daily_income' => 'integer',
        'is_dummy' => 'boolean', 'is_active' => 'boolean',
    ];
}
