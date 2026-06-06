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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('status', ['active', 'pending_verification', 'blocked', 'deleted'])
                ->default('pending_verification');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('language_preference', ['sk', 'en'])
                ->default('sk');
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->onDelete('set null');
            $table->enum('role_in_org', ['owner', 'contact', 'evaluator', 'mentor'])
                ->nullable();
            $table->index('email');
            $table->index('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
