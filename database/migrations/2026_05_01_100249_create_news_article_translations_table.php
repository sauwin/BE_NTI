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
        Schema::create('news_article_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')
                ->constrained('news_articles')
                ->onDelete('cascade');
            $table->enum('language', ['en', 'sk'])
                ->default('sk');
            $table->string('title');
            $table->string('excerpt');
            $table->text('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_article_translations');
    }
};
