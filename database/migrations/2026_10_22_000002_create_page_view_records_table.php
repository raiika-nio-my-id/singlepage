<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_view_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained()->cascadeOnDelete();
            $table->date('viewed_on');
            // SHA-256 hex digest of IP + UA + date + app-key salt (privacy-friendly, not reversible)
            $table->char('visitor_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            // Unique constraint deduplicates the same visitor on the same day
            $table->unique(['company_profile_id', 'viewed_on', 'visitor_hash'], 'pvr_unique_daily_visitor');
            // Index to speed up stats queries per profile over a date range
            $table->index(['company_profile_id', 'viewed_on'], 'pvr_profile_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_view_records');
    }
};
