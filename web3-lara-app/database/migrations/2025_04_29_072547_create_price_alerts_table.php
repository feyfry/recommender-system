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
        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable(false);
            $table->string('project_id')->nullable(false);
            $table->decimal('target_price', 30, 10)->nullable(false);
            $table->string('alert_type', 50)->nullable(false); // 'above', 'below'
            $table->boolean('is_triggered')->default(false);
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });

        // Indeks untuk queries yang sering digunakan
        Schema::table('price_alerts', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('project_id');
            $table->index('is_triggered');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
    }
};
