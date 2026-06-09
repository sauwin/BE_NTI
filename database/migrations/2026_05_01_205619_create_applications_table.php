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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')
                ->constrained('calls')
                ->onDelete('cascade');
            $table->enum('applicant_type', ['student', 'team']);
            $table->foreignId('student_profile_id')
                ->nullable()
                ->constrained('student_profiles')
                ->onDelete('set null');
            $table->foreignId('team_id')
                ->nullable()
                ->constrained('teams')
                ->onDelete('set null');
            $table->enum('status', [
                'draft',
                'submitted',
                'formally_verified',
                'under_evaluation',
                'pending_revision',
                'approved',
                'rejected',
                'onboarding',
                'active',
                'suspended',
                'closed',
            ])->default('draft');
            $table->string('program_type')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decision_at')->nullable();
            $table->foreignId('decision_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->text('internal_notes')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();
            $table->index('call_id');
            $table->index('status');
            $table->string('project_title')
                ->nullable();
            $table->string('proposed_solution')
                ->nullable();
            $table->index('applicant_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
