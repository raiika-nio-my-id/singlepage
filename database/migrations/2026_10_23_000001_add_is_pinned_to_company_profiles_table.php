<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('company_profiles', 'is_pinned')) {
            return;
        }

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('sort_order');
            $table->index('is_pinned');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('company_profiles', 'is_pinned')) {
            return;
        }

        Schema::table('company_profiles', function (Blueprint $table) {
            $hasIndex = collect(Schema::getIndexes('company_profiles'))
                ->pluck('name')
                ->contains('company_profiles_is_pinned_index');

            if ($hasIndex) {
                $table->dropIndex('company_profiles_is_pinned_index');
            }

            $table->dropColumn('is_pinned');
        });
    }
};
