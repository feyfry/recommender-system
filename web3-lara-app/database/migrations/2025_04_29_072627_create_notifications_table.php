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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable(false);
            $table->string('notification_type', 100)->nullable(false); // 'price_alert', 'new_recommendation', dll
            $table->string('title')->nullable(false);
            $table->text('content')->nullable();
            $table->boolean('is_read')->default(false);
            $table->jsonb('data')->nullable(); // Data tambahan terkait notifikasi
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });

        // Indeks untuk notifications
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('user_id');
            $table->index(['user_id', 'is_read']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
