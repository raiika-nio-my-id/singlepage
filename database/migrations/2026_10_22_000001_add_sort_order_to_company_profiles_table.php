<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('hide_footer');
            $table->index('sort_order');
        });

        // Backfill: assign ascending sort_order based on id order (oldest = 0).
        // Correlated subquery works on both MySQL and SQLite.
        DB::statement('
            UPDATE company_profiles
            SET sort_order = (
                SELECT COUNT(*) - 1
                FROM company_profiles AS cp2
                WHERE cp2.id <= company_profiles.id
            )
        ');
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
