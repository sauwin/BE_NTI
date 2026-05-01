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
        Schema::create('program_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')
                ->constrained('programs')
                ->onDelete('cascade');
            $table->char('language', 2);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unique(['program_id', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_translations');
    }
};
