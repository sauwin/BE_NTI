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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name')
                ->unique();
            $table->foreignId('leader_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->text('description')
                ->nullable();
            $table->timestamps();
            $table->index('leader_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
