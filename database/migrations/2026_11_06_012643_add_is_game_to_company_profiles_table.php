<?php

use App\Services\GameHubGameBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('company_profiles', 'is_game')) {
            Schema::table('company_profiles', function (Blueprint $table) {
                $table->boolean('is_game')->nullable()->default(false)->after('is_pinned');
            });
        }

        $hasIndex = collect(Schema::getIndexes('company_profiles'))
            ->pluck('name')
            ->contains('company_profiles_is_game_index');

        if (! $hasIndex) {
            Schema::table('company_profiles', function (Blueprint $table) {
                $table->index('is_game');
            });
        }

        GameHubGameBackfill::apply();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('company_profiles', 'is_game')) {
            return;
        }

        Schema::table('company_profiles', function (Blueprint $table) {
            $hasIndex = collect(Schema::getIndexes('company_profiles'))
                ->pluck('name')
                ->contains('company_profiles_is_game_index');

            if ($hasIndex) {
                $table->dropIndex('company_profiles_is_game_index');
            }

            $table->dropColumn('is_game');
        });
    }
};
