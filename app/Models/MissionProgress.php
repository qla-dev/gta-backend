<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissionProgress extends Model
{
    protected $table = 'mission_progress';

    protected $fillable = ['user_id', 'mission_id', 'status', 'progress_current'];

    protected $casts = ['progress_current' => 'integer'];
}
