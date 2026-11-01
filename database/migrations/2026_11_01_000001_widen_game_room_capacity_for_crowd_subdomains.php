<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crowd rooms store up to 500 seats. The original columns were
     * UNSIGNED TINYINT (0–255), which cannot hold that ceiling or seat 256+.
     * Keep 500 in sync with config/games.php drawquarium and langitbersama.
     */
    public function up(): void
    {
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_players')->default(4)->change();
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->unsignedSmallInteger('seat')->nullable()->change();
        });

        $this->backfillCrowdRooms();
    }

    /**
     * Existing public rooms keep the max_players they were created with.
     * Raise the live drawquarium and langitbersama rooms so match keeps
     * using them instead of opening a second room at the old cap of 4.
     */
    public function backfillCrowdRooms(): void
    {
        $profileIds = DB::table('company_profiles')
            ->whereIn('subdomain', ['drawquarium', 'langitbersama'])
            ->pluck('id');

        if ($profileIds->isEmpty()) {
            return;
        }

        DB::table('game_rooms')
            ->whereIn('company_profile_id', $profileIds)
            ->where('visibility', 'public')
            ->whereIn('status', ['waiting', 'playing'])
            ->where('max_players', '<', 500)
            ->update(['max_players' => 500]);
    }

    public function down(): void
    {
        DB::table('game_rooms')->where('max_players', '>', 255)->update(['max_players' => 16]);
        DB::table('game_players')->where('seat', '>', 255)->update(['seat' => 255]);

        Schema::table('game_rooms', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_players')->default(4)->change();
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->unsignedTinyInteger('seat')->nullable()->change();
        });
    }
};
