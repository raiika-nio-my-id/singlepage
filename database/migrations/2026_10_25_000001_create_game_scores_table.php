<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: idempotent in case of a partial prior run on a non-transactional engine.
        if (Schema::hasTable('game_scores')) {
            return;
        }

        Schema::create('game_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name', 16);
            $table->unsignedInteger('score');
            $table->timestamps();

            $table->index(['company_profile_id', 'score', 'created_at'], 'game_scores_tenant_rank_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_scores');
    }
};
