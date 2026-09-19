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
            $table->date('expires_at')->nullable()->after('status');
        });

        DB::table('company_profiles')
            ->whereNull('expires_at')
            ->update(['expires_at' => now()->addYear()->toDateString()]);
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
