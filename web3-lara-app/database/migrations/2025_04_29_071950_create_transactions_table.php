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
            $table->string('transaction_type', 50)->nullable(false); // 'buy', 'sell'
            $table->decimal('amount', 30, 10)->nullable(false);
            $table->decimal('price', 30, 10)->nullable(false);
            $table->decimal('total_value', 30, 10)->nullable(false);
            $table->string('transaction_hash')->nullable();
            $table->boolean('followed_recommendation')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });

        // Indeks untuk transaction queries
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('created_at');
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
