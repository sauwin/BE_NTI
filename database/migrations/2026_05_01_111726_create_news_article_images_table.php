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
        Schema::create('news_article_images', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->string('image_alt')
                ->nullable();
            $table->string('image_description')
                ->nullable();
            $table->enum('type', ['cover', 'inline'])
                ->default('inline');
            $table->foreignId('article_id')
                ->constrained('news_articles')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_article_images');
    }
};
