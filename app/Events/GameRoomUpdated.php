<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameRoomUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $subdomain,
        public string $code,
        public int $version,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('game.'.$this->subdomain),
            new Channel('game.'.$this->subdomain.'.'.$this->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'room.updated';
    }

    /**
     * @return array{code: string, version: int}
     */
    public function broadcastWith(): array
    {
        return [
            'code' => $this->code,
            'version' => $this->version,
        ];
    }
}
