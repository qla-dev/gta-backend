<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = ['name', 'category', 'speed', 'color', 'icon', 'description', 'sort_order'];
}
