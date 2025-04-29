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
        Schema::create('historical_prices', function (Blueprint $table) {
            $table->id();
            $table->string('project_id')->nullable(false);
            $table->timestamp('timestamp')->nullable(false);
            $table->decimal('price', 30, 10)->nullable(false);
            $table->decimal('volume', 30, 2)->nullable();
            $table->decimal('market_cap', 30, 2)->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });

        // Indeks untuk time-series queries
        Schema::table('historical_prices', function (Blueprint $table) {
            $table->index(['project_id', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historical_prices');
    }
};
