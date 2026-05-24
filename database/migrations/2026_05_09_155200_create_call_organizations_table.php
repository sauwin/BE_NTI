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
        Schema::create('call_organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('calls')->onDelete('cascade');
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('product_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('brief')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->text('project_goal')->nullable();
            $table->text('expected_outcome')->nullable();
            $table->text('detailed_technical_description')->nullable();
            $table->json('required_technologies')->nullable();
            $table->text('architecture_requirements')->nullable();
            $table->text('integrations_apis')->nullable();
            $table->string('platforms')->nullable();
            $table->json('required_skills')->nullable();
            $table->integer('preferred_team_size')->nullable();
            $table->string('required_experience')->nullable();
            $table->decimal('budget', 10, 2)->nullable();
            $table->string('expected_duration')->nullable();
            $table->text('milestones')->nullable();
            $table->date('deadline')->nullable();
            $table->enum('status', ['draft', 'published', 'in_matching', 'assigned', 'in_progress', 'closed'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_organizations');
    }
};