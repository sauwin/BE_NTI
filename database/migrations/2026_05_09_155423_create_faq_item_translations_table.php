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
        Schema::create('faq_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_item_id')->constrained('faq_items')->onDelete('cascade');
            $table->enum('language', ['sk', 'en']);
            $table->string('question');
            $table->text('answer');
            $table->unique(['faq_item_id', 'language']);
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faq_item_translations');
    }
};
