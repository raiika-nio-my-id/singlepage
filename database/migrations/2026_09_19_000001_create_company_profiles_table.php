<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('subdomain', 63)->unique();
            $table->longText('html');
            $table->string('status', 32)->default('draft');
            $table->timestamps();

            $table->index(['subdomain', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
