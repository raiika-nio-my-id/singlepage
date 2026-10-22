<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Column ──────────────────────────────────────────────────────────
        // Guard: MySQL DDL is auto-committed, so a partially-executed migration
        // (column created, backfill failed) leaves the column in place but no
        // entry in the migrations table. A subsequent `php artisan migrate`
        // would fail with "Duplicate column name". The hasColumn guard makes
        // this migration safe to re-run in that partial state.
        if (! Schema::hasColumn('company_profiles', 'sort_order')) {
            Schema::table('company_profiles', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->after('hide_footer');
            });
        }

        // ── Index ────────────────────────────────────────────────────────────
        $hasIndex = collect(Schema::getIndexes('company_profiles'))
            ->pluck('name')
            ->contains('company_profiles_sort_order_index');

        if (! $hasIndex) {
            Schema::table('company_profiles', function (Blueprint $table) {
                $table->index('sort_order');
            });
        }

        // ── Backfill ─────────────────────────────────────────────────────────
        // PHP iteration avoids MySQL's correlated-subquery restrictions on
        // UPDATE … SET col = (SELECT … FROM same_table). Works identically on
        // MySQL 5.7+, MariaDB, PostgreSQL, and SQLite.
        // Re-running is safe: the same ascending order is produced each time.
        DB::table('company_profiles')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int|string $id, int $position): void {
                DB::table('company_profiles')
                    ->where('id', $id)
                    ->update(['sort_order' => $position]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('company_profiles', 'sort_order')) {
            return;
        }

        Schema::table('company_profiles', function (Blueprint $table) {
            $hasIndex = collect(Schema::getIndexes('company_profiles'))
                ->pluck('name')
                ->contains('company_profiles_sort_order_index');

            if ($hasIndex) {
                $table->dropIndex('company_profiles_sort_order_index');
            }

            $table->dropColumn('sort_order');
        });
    }
};
