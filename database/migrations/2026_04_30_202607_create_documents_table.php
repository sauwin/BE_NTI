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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->onDelete('cascade');
            $table->enum('type', [
                'cv',
                'executive_summary',
                'technical_architecture',
                'roadmap',
                'budget',
                'risk_analysis',
                'monetization',
                'motivation_letter',
                'final_presentation',
                'other',
            ]);
            $table->enum('classification', [
                'public',
                'internal',
                'confidential',
            ])->default('internal');
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size_bytes');
            $table->timestamp('created_at')->useCurrent()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->index('uploaded_by');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
