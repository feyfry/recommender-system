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
            // Primary key
            $table->string('id')->primary();

            // Basic info
            $table->string('symbol', 50);
            $table->string('name');
            $table->text('image')->nullable();

            // Price data
            $table->decimal('current_price', 30, 10)->nullable();
            $table->decimal('market_cap', 30, 2)->nullable();
            $table->integer('market_cap_rank')->nullable();
            $table->decimal('fully_diluted_valuation', 30, 2)->nullable();
            $table->decimal('total_volume', 30, 2)->nullable();
            $table->decimal('high_24h', 30, 10)->nullable();
            $table->decimal('low_24h', 30, 10)->nullable();

            // Price changes
            $table->decimal('price_change_24h', 30, 10)->nullable();
            $table->decimal('price_change_percentage_24h', 30, 10)->nullable();
            $table->decimal('market_cap_change_24h', 30, 10)->nullable();
            $table->decimal('market_cap_change_percentage_24h', 30, 10)->nullable();

            // Supply info
            $table->decimal('circulating_supply', 30, 2)->nullable();
            $table->decimal('total_supply', 30, 2)->nullable();
            $table->decimal('max_supply', 30, 2)->nullable();

            // All time high/low
            $table->decimal('ath', 30, 10)->nullable();
            $table->decimal('ath_change_percentage', 30, 10)->nullable();
            $table->timestamp('ath_date')->nullable();
            $table->decimal('atl', 30, 10)->nullable();
            $table->decimal('atl_change_percentage', 30, 10)->nullable();
            $table->timestamp('atl_date')->nullable();

            // ROI
            $table->text('roi')->nullable();

            // Time data
            $table->timestamp('last_updated')->nullable();

            // Price change percentages
            $table->decimal('price_change_percentage_1h_in_currency', 30, 10)->nullable();
            $table->decimal('price_change_percentage_24h_in_currency', 30, 10)->nullable();
            $table->decimal('price_change_percentage_30d_in_currency', 30, 10)->nullable();
            $table->decimal('price_change_percentage_7d_in_currency', 30, 10)->nullable();

            // Categories and platforms
            $table->string('query_category', 100)->nullable();
            $table->text('platforms')->nullable();
            $table->text('categories')->nullable();

            // Social metrics
            $table->integer('twitter_followers')->nullable();
            $table->integer('github_stars')->nullable();
            $table->integer('github_subscribers')->nullable();
            $table->integer('github_forks')->nullable();

            // Description info
            $table->text('description')->nullable();
            $table->integer('description_length')->nullable();

            // Date and age
            $table->date('genesis_date')->nullable();
            $table->integer('age_days')->nullable();

            // Sentiment data
            $table->decimal('sentiment_votes_up_percentage', 10, 2)->nullable();
            $table->integer('telegram_channel_user_count')->nullable();

            // Classification
            $table->string('primary_category', 100)->nullable();
            $table->string('chain', 100)->nullable();

            // Scores
            $table->decimal('popularity_score', 10, 2)->nullable();
            $table->decimal('trend_score', 10, 2)->nullable();
            $table->decimal('developer_activity_score', 10, 2)->nullable();
            $table->decimal('social_engagement_score', 10, 2)->nullable();
            $table->decimal('maturity_score', 10, 2)->nullable();

            // Trending status
            $table->boolean('is_trending')->default(false);

            // Timestamps
            $table->timestamps();
        });

        // Indeks untuk pencarian dan filter
        Schema::table('projects', function (Blueprint $table) {
            $table->index('symbol');
            $table->index('market_cap_rank');
            $table->index('popularity_score');
            $table->index('trend_score');
            $table->index('chain');
            $table->index('primary_category');
            $table->index('is_trending');
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
