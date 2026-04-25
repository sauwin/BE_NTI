<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function(Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('users', function(Blueprint $table) {
            $table->id(); 
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->enum('status', ['active', 'pending_verification', 'blocked'])
                ->default('pending_verification');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('language_preference', ['sk', 'en'])
                ->default('sk');
            $table->index('email');
            $table->index('status');
            $table->timestamps();
        });

        Schema::create('user_roles', function(Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->onDelete('cascade');
            $table->foreignId('role_id')
                ->constrained('roles')
                ->onDelete('cascade');
            $table->foreignId('granted_by')
                ->nullable()
                ->constrained('users', 'id')
                ->onDelete('set null');
            $table->timestamp('granted_at')->useCurrent();
            $table->index('granted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};