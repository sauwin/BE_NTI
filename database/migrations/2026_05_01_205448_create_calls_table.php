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
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')
                ->constrained('programs')
                ->onDelete('cascade');
            $table->enum('status', ['draft', 'open', 'closed', 'archived'])->default('draft');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->unsignedTinyInteger('min_team_size')->default(3);
            $table->unsignedTinyInteger('max_team_size')->nullable();
            $table->json('evaluation_criteria')->nullable();
            $table->json('required_documents')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->onDelete('cascade');
            $table->timestamps();
            $table->index('program_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
