<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_group');  
            $table->string('subject');          
            $table->text('message');  
            $table->integer('total_recipients');
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete(); // Хто відправив
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_notification_campaigns');
    }
};