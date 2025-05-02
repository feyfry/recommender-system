<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_caches', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint')->nullable(false);
            $table->jsonb('parameters')->nullable();
            $table->jsonb('response')->nullable();
            $table->timestamp('expires_at')->nullable(false);
            $table->timestamps();
        });

        // Indeks untuk API cache
        Schema::table('api_caches', function (Blueprint $table) {
            $table->index('endpoint');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_caches');
    }
};
