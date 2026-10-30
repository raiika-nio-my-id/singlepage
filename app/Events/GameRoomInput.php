<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameRoomInput implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public string $subdomain,
        public string $code,
        public int $playerId,
        public string $displayName,
        public array $input,
        public string $id,
        public string $at,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('game.'.$this->subdomain.'.'.$this->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'room.input';
    }

    /**
     * @return array{code: string, playerId: int, displayName: string, input: array<string, mixed>, id: string, at: string}
     */
    public function broadcastWith(): array
    {
        return [
            'code' => $this->code,
            'playerId' => $this->playerId,
            'displayName' => $this->displayName,
            'input' => $this->input,
            'id' => $this->id,
            'at' => $this->at,
        ];
    }
}
