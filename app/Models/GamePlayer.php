<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_room_id', 'display_name', 'seat', 'token_hash', 'is_host', 'is_spectator', 'is_gamemaster', 'private_state', 'left_at'])]
#[Hidden(['token_hash', 'private_state'])]
class GamePlayer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seat' => 'integer',
            'is_host' => 'boolean',
            'is_spectator' => 'boolean',
            'is_gamemaster' => 'boolean',
            'private_state' => 'array',
            'left_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class, 'game_room_id');
    }
}
