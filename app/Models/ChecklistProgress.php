<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistProgress extends Model
{
    protected $table = 'checklist_progress';

    protected $fillable = ['user_id', 'checklist_item_id', 'progress_current', 'done'];

    protected function casts(): array
    {
        return [
            'done' => 'boolean',
            'progress_current' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
