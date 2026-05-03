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
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->onDelete('cascade');
            $table->string('study_program');
            $table->unsignedTinyInteger('year_of_study');
            $table->string('university')->nullable();
            $table->text('bio')->nullable();
            $table->foreignId('cv_document_id')
                ->nullable()
                ->constrained('documents')
                ->onDelete('set null');
            $table->boolean('academic_declaration_confirmed')->default(false);
            $table->string('github_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
