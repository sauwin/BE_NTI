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
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')
                ->constrained('applications')
                ->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', [
                'pending',
                'in_progress',
                'completed',
                'overdue',
            ])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('application_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
