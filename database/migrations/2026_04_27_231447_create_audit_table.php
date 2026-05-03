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
        Schema::create('audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')
                ->constrained('users'); /*
                ->onDelete('set null')
                ->nullable();*/
            $table->string('action');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->json('old_value')
                ->nullable();
            $table->json('new_value')
                ->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('actor_id');
            $table->index('action');
            $table->index('target_type');
            $table->index('created_at');
            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit');
    }
};
