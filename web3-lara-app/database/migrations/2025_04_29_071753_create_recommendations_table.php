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
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable(false);
            $table->string('project_id')->nullable(false);
            $table->decimal('score', 10, 4)->nullable(false);
            $table->integer('rank')->nullable(false);
            $table->string('recommendation_type', 50)->nullable(false); // 'item-based', 'user-based', 'feature-enhanced', 'hybrid', 'ncf', 'popular', 'trending'
            $table->string('category_filter', 100)->nullable();
            $table->string('chain_filter', 100)->nullable();
            $table->string('action_type', 50)->nullable(); // 'buy', 'sell', 'hold'
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->decimal('target_price', 30, 10)->nullable();
            $table->text('explanation')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });

        // Indeks untuk query rekomendasi
        Schema::table('recommendations', function (Blueprint $table) {
            $table->index(['user_id', 'recommendation_type']);
            $table->index(['user_id', 'rank']);
            $table->index('action_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
