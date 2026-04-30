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
        Schema::create('milestone_documents', function (Blueprint $table) {
            // $table->foreignId('milestone_id')
            //     ->constrained('milestones')
            //     ->onDelete('cascade');
            $table->foreignId('document_id')
                ->constrained('documents')
                ->onDelete('cascade');
 
            // $table->primary(['milestone_id', 'document_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestone_documents');
    }
};
