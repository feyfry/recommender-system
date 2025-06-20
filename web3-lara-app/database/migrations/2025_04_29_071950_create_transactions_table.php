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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable(false);
            $table->string('project_id')->nullable(false);
            $table->string('transaction_type', 50)->nullable(false); // 'buy', 'sell', 'transfer'

            // Auto-sync vs Manual source tracking
            $table->string('source', 20)->default('manual'); // 'manual', 'api_sync'

            // Blockchain information (untuk API-synced transactions)
            $table->string('blockchain_chain', 50)->nullable(); // 'eth', 'bsc', 'polygon', etc
            $table->string('from_address', 255)->nullable();
            $table->string('to_address', 255)->nullable();
            $table->string('token_address', 255)->nullable();
            $table->string('token_symbol', 20)->nullable();
            $table->bigInteger('block_number')->nullable();
            $table->decimal('gas_used', 20, 0)->nullable();
            $table->decimal('gas_price', 30, 10)->nullable(); // in Gwei
            $table->timestamp('blockchain_timestamp')->nullable();

            // Transaction amounts & values (existing fields)
            $table->decimal('amount', 30, 10)->nullable(false);
            $table->decimal('price', 30, 10)->nullable(false);
            $table->decimal('total_value', 30, 10)->nullable(false);
            $table->string('transaction_hash')->nullable();

            // Verification & metadata
            $table->boolean('is_verified')->default(true); // For API-synced transactions
            $table->jsonb('raw_data')->nullable(); // Store original API response
            $table->timestamp('last_sync_at')->nullable();

            // Enhanced manual transaction fields
            $table->text('notes')->nullable(); // User notes for manual transactions
            $table->string('exchange_platform', 100)->nullable(); // 'binance', 'uniswap', etc

            // Recommendation tracking (existing fields)
            $table->boolean('followed_recommendation')->default(false);
            $table->unsignedBigInteger('recommendation_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('recommendation_id')->references('id')->on('recommendations')->onDelete('set null');
        });

        // Indeks untuk transaction queries yang optimal
        Schema::table('transactions', function (Blueprint $table) {
            // Basic indexes
            $table->index('user_id');
            $table->index('project_id');
            $table->index('created_at');
            $table->index('source');
            $table->index('blockchain_chain');
            $table->index('token_address');
            $table->index('block_number');
            $table->index('blockchain_timestamp');
            $table->index('last_sync_at');

            // Composite indexes untuk query yang sering digunakan
            $table->index(['user_id', 'source']);
            $table->index(['user_id', 'blockchain_chain']);
            $table->index(['user_id', 'created_at']);
            $table->index(['project_id', 'transaction_type']);

            // Composite index untuk duplicate detection pada API-synced transactions
            $table->index(['transaction_hash', 'user_id', 'blockchain_chain'], 'unique_blockchain_tx');

            // Index untuk performance queries
            $table->index(['source', 'created_at']);
            $table->index(['blockchain_chain', 'block_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
