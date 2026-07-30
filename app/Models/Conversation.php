<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['created_by'];

    public function participants()
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function readStates()
    {
        return $this->hasMany(MessageRead::class);
    }

    // Filtra conversaciones en las que participa $userId (sin tocar tabla users)
    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->whereExists(function ($sub) use ($userId) {
            $sub->selectRaw(1)
                ->from('conversation_participants as cp')
                ->whereColumn('cp.conversation_id', 'conversations.id')
                ->where('cp.user_id', $userId);
        });
    }
}
