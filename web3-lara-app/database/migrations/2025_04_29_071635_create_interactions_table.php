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
        Schema::create('interactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable(false);
            $table->string('project_id')->nullable(false);
            $table->string('interaction_type', 50)->nullable(false); // 'view', 'favorite', 'portfolio_add', 'research', 'click'
            $table->integer('weight')->default(1);
            $table->jsonb('context')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });

        // Indeks untuk queries yang sering digunakan
        Schema::table('interactions', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('project_id');
            $table->index('interaction_type');
            $table->index('created_at');

            // Composite index untuk deduplication
            // Format yang benar: index(array_kolom, nama_index)
            $table->index(
                ['user_id', 'project_id', 'interaction_type', 'created_at'],
                'unique_interaction_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
