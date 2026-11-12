<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'apple_id',
        'apple_email',
        'apple_email_private',
        'google_id',
        'google_email',
        'ai_coins',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'apple_email_private' => 'boolean',
            'ai_coins' => 'integer',
            'free_messages_used' => 'integer',
            'free_messages_date' => 'date',
        ];
    }

    public function freeMessagesUsedToday(): int
    {
        if (! $this->free_messages_date || ! $this->free_messages_date->isToday()) {
            return 0;
        }

        return $this->free_messages_used;
    }

    public function consumeFreeMessage(): bool
    {
        if (! $this->free_messages_date || ! $this->free_messages_date->isToday()) {
            $this->free_messages_used = 1;
            $this->free_messages_date = today();
            $this->save();
            return true;
        }

        if ($this->free_messages_used < 30) {
            $this->increment('free_messages_used');
            return true;
        }

        return false;
    }

    public function consumeCoin(): bool
    {
        if ($this->ai_coins <= 0) {
            return false;
        }

        $this->decrement('ai_coins');
        return true;
    }
}
