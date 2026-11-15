<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mission extends Model
{
    protected $casts = [
        'map_x' => 'float', 'map_y' => 'float', 'progress_total' => 'integer',
        'is_dummy' => 'boolean', 'is_active' => 'boolean',
    ];
}
