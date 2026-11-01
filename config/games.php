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

    'input_max_bytes' => (int) env('GAME_INPUT_MAX_BYTES', 4_096),

    /*
    |--------------------------------------------------------------------------
    | Room capacity
    |--------------------------------------------------------------------------
    |
    | Ordinary games stay small: create/match accept maxPlayers from 2 to 16,
    | omit it and the room stores 4, and watch stops at 20 spectators.
    |
    | drawquarium and langitbersama share one public room with a crowd.
    | Those primary subdomains override the three numbers below. An explicit
    | maxPlayers is still stored as sent, so those clients should omit it or
    | send the ceiling. Other subdomains are unchanged.
    |
    */

    'max_players_min' => 2,

    'max_players_max' => 16,

    'default_max_players' => 4,

    'spectator_limit' => 20,

    'subdomains' => [
        'drawquarium' => [
            'max_players_max' => 500,
            'default_max_players' => 500,
            'spectator_limit' => 500,
        ],
        'langitbersama' => [
            'max_players_max' => 500,
            'default_max_players' => 500,
            'spectator_limit' => 500,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gamemaster secret
    |--------------------------------------------------------------------------
    |
    | Typed-code path (preferred): a seated player POSTs the code to
    | /api/game/rooms/{code}/gamemaster. The server compares with
    | Setting::gamemasterSecret() and never returns the secret.
    |
    | Transitional/deprecated: GET /api/game/rooms?gm= or X-Game-Master still
    | lists private rooms for the same tenant. Live clients still send a
    | hardcoded key (currently gamemasterjd, rolling toward gmjd1212). Prefer
    | the typed-code claim going forward; do not add this value to describe
    | or public config.
    |
    | Admin Settings can store a non-empty override in the settings table.
    | This env value is the fallback when that override is empty or unset.
    |
    */

    'gamemaster_secret' => (string) env('GAME_GAMEMASTER_SECRET', 'gamemasterjd'),

];
