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
        Schema::create('projects', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->nullable(false);
            $table->string('symbol', 50)->nullable(false);
            $table->jsonb('categories')->nullable();
            $table->jsonb('platforms')->nullable();
            $table->decimal('market_cap', 30, 2)->nullable();
            $table->decimal('volume_24h', 30, 2)->nullable();
            $table->decimal('price_usd', 30, 10)->nullable();
            $table->decimal('price_change_24h', 30, 10)->nullable();
            $table->decimal('price_change_percentage_24h', 10, 2)->nullable();
            $table->decimal('price_change_percentage_7d', 10, 2)->nullable();
            $table->decimal('price_change_percentage_1h', 10, 2)->nullable();
            $table->decimal('price_change_percentage_30d', 10, 2)->nullable();
            $table->string('image')->nullable();
            $table->decimal('popularity_score', 10, 2)->nullable();
            $table->decimal('trend_score', 10, 2)->nullable();
            $table->decimal('social_score', 10, 2)->nullable();
            $table->decimal('social_engagement_score', 10, 2)->nullable();
            $table->decimal('developer_activity_score', 10, 2)->nullable();
            $table->decimal('maturity_score', 10, 2)->nullable();
            $table->decimal('sentiment_positive', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->integer('twitter_followers')->nullable();
            $table->integer('telegram_channel_user_count')->nullable();
            $table->integer('github_stars')->nullable();
            $table->integer('github_forks')->nullable();
            $table->integer('github_subscribers')->nullable();
            $table->string('chain', 100)->nullable();
            $table->string('primary_category', 100)->nullable();
            $table->date('genesis_date')->nullable();
            $table->decimal('all_time_high', 30, 10)->nullable();
            $table->timestamp('all_time_high_date')->nullable();
            $table->decimal('all_time_low', 30, 10)->nullable();
            $table->timestamp('all_time_low_date')->nullable();
            $table->decimal('circulating_supply', 30, 2)->nullable();
            $table->decimal('total_supply', 30, 2)->nullable();
            $table->decimal('max_supply', 30, 2)->nullable();
            $table->decimal('fully_diluted_valuation', 30, 2)->nullable();
            $table->timestamps();
        });

        // Indeks untuk pencarian dan filter
        Schema::table('projects', function (Blueprint $table) {
            $table->index('symbol');
            $table->index(['popularity_score']);
            $table->index(['trend_score']);
            $table->index('chain');
            $table->index('primary_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
