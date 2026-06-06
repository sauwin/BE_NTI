<?php

namespace Tests\Unit;

use App\Models\Application;
use App\Models\Call;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Call $call;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $studentRole = Role::create(['name' => 'Student', 'slug' => 'student', 'description' => '']);
        Role::create(['name' => 'Mentor', 'slug' => 'mentor', 'description' => '']);

        $this->student = User::factory()->create(['status' => 'active']);
        $this->student->roles()->attach($studentRole->id);

        $this->call = $this->makeActiveCall('a');
    }

    private function makeActiveCall(string $programType): Call
    {
        return Call::factory()->create(['program' => $programType, 'status' => 'open']);
    }

    public function test_student_can_create_draft_application(): void
    {
        StudentProfile::factory()->create(['user_id' => $this->student->id]);
        $this->student->refresh();

        $res = $this->actingAs($this->student)->postJson('/api/applications', [
            'applicant_type' => 'student',
            'program_type' => 'a',
            'submit_type' => 'draft',
        ]);

        $res->assertStatus(201)->assertJsonStructure(['application_id']);
        $this->assertDatabaseHas('applications', ['status' => 'draft']);
    }

    public function test_application_requires_student_profile(): void
    {
        $res = $this->actingAs($this->student)->postJson('/api/applications', [
            'applicant_type' => 'student',
            'program_type' => 'a',
            'submit_type' => 'final',
        ]);

        $res->assertStatus(403);
    }

    public function test_applicant_type_validation(): void
    {
        StudentProfile::factory()->create(['user_id' => $this->student->id]);

        $res = $this->actingAs($this->student)->postJson('/api/applications', [
            'applicant_type' => 'invalid',
            'program_type' => 'a',
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['applicant_type']);
    }

    public function test_program_type_validation(): void
    {
        StudentProfile::factory()->create(['user_id' => $this->student->id]);

        $res = $this->actingAs($this->student)->postJson('/api/applications', [
            'applicant_type' => 'student',
            'program_type' => 'z',
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['program_type']);
    }

    public function test_no_active_call_returns_error(): void
    {
        Call::query()->update(['status' => 'closed']);
        StudentProfile::factory()->create(['user_id' => $this->student->id]);

        $res = $this->actingAs($this->student)->postJson('/api/applications', [
            'applicant_type' => 'student',
            'program_type' => 'a',
            'submit_type' => 'final',
            'category' => 'AI',
        ]);

        $res->assertStatus(422);
    }

    public function test_status_change_draft_to_submitted(): void
    {
        $profile = StudentProfile::factory()->create(['user_id' => $this->student->id]);
        $app = Application::factory()->create([
            'call_id' => $this->call->id,
            'student_profile_id' => $profile->id,
            'status' => 'draft',
            'program_type' => 'a',
            'applicant_type' => 'student',
        ]);

        $res = $this->actingAs($this->student)->postJson("/api/applications/{$app->id}/submit");

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'submitted']);
    }

    public function test_cannot_edit_submitted_application(): void
    {
        $profile = StudentProfile::factory()->create(['user_id' => $this->student->id]);
        $app = Application::factory()->create([
            'call_id' => $this->call->id,
            'student_profile_id' => $profile->id,
            'status' => 'submitted',
            'program_type' => 'a',
            'applicant_type' => 'student',
        ]);

        $res = $this->actingAs($this->student)->patchJson("/api/applications/{$app->id}", [
            'category' => 'AI',
        ]);

        $res->assertStatus(403);
    }

    public function test_draft_can_be_deleted(): void
    {
        $profile = StudentProfile::factory()->create(['user_id' => $this->student->id]);
        $app = Application::factory()->create([
            'call_id' => $this->call->id,
            'student_profile_id' => $profile->id,
            'status' => 'draft',
            'program_type' => 'a',
            'applicant_type' => 'student',
        ]);

        $res = $this->actingAs($this->student)->deleteJson("/api/applications/{$app->id}");

        $res->assertStatus(200);
        $this->assertDatabaseMissing('applications', ['id' => $app->id]);
    }

    public function test_non_draft_cannot_be_deleted(): void
    {
        $profile = StudentProfile::factory()->create(['user_id' => $this->student->id]);
        $app = Application::factory()->create([
            'call_id' => $this->call->id,
            'student_profile_id' => $profile->id,
            'status' => 'submitted',
            'program_type' => 'a',
            'applicant_type' => 'student',
        ]);

        $res = $this->actingAs($this->student)->deleteJson("/api/applications/{$app->id}");

        $res->assertStatus(403);
    }
}