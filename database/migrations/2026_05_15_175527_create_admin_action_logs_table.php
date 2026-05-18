<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_action_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('action');
            $table->string('action_type');
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->text('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('action_type');
            $table->index('target_user_id');
            $table->index('admin_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_logs');
    }
};