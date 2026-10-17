<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shared multiplayer store
    |--------------------------------------------------------------------------
    |
    | Every game subdomain uses this one database. Rooms are isolated by the
    | company profile that owns the subdomain. Browsers never receive a
    | database password; they use /api/game and the Reverb websocket.
    |
    */

    'state_max_bytes' => (int) env('GAME_STATE_MAX_BYTES', 200_000),

    'private_max_bytes' => (int) env('GAME_PRIVATE_MAX_BYTES', 100_000),

    'payload_max_bytes' => (int) env('GAME_PAYLOAD_MAX_BYTES', 20_000),

    'spectator_limit' => 20,

];
