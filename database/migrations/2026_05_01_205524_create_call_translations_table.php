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
        Schema::create('call_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')
                ->constrained('calls')
                ->onDelete('cascade');
            $table->char('language', 2);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unique(['call_id', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_translations');
    }
};
