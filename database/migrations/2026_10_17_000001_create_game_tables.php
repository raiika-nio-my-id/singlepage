<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained()->cascadeOnDelete();
            $table->string('code', 12);
            $table->string('visibility', 16)->default('private');
            $table->string('status', 16)->default('waiting');
            $table->unsignedTinyInteger('max_players')->default(4);
            $table->json('state');
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['company_profile_id', 'code']);
            $table->index(['company_profile_id', 'visibility', 'status']);
        });

        Schema::create('game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->string('display_name', 40);
            $table->unsignedTinyInteger('seat')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->boolean('is_host')->default(false);
            $table->boolean('is_spectator')->default(false);
            $table->json('private_state')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index(['game_room_id', 'left_at']);
        });

        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_player_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('type', 64);
            $table->json('payload');
            $table->timestamp('created_at')->nullable();

            $table->index(['game_room_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
        Schema::dropIfExists('game_players');
        Schema::dropIfExists('game_rooms');
    }
};
