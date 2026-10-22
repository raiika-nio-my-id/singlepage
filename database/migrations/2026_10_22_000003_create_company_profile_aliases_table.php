<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: idempotent in case of a partial prior run on a non-transactional engine.
        if (Schema::hasTable('company_profile_aliases')) {
            return;
        }

        Schema::create('company_profile_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained()->cascadeOnDelete();
            // Each alias subdomain must be globally unique (same namespace as primary subdomains).
            $table->string('subdomain', 63)->unique();
            $table->timestamps();

            $table->index('company_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profile_aliases');
    }
};
