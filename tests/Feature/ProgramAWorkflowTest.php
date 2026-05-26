<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Call;
use App\Models\Evaluation;
use App\Models\Program;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProgramAWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Role $studentRole;
    private Role $evaluatorRole;
    private Role $adminRole;
    private Role $mentorRole;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->studentRole = Role::create(['name' => 'Student', 'slug' => 'student', 'description' => '']);
        $this->evaluatorRole = Role::create(['name' => 'Evaluator', 'slug' => 'evaluator', 'description' => '']);
        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'nti_admin', 'description' => '']);
        $this->mentorRole = Role::create(['name' => 'Mentor', 'slug' => 'mentor', 'description' => '']);
    }

    private function makeUser(Role $role): User
    {
        $user = User::factory()->create(['status' => 'active']);
        \DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);
        return $user;
    }

    private function makeProgram(): Program
    {
        return Program::firstOrCreate(
            ['code' => 'program_a'],
            ['type' => Program::TYPE_GRANT, 'is_active' => true, 'config' => null]
        );
    }

    private function makeOpenCall(Program $program): Call
    {
        return Call::factory()->create([
            'program_id' => $program->id,
            'status' => 'open',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(7),
            'min_team_size' => 3,
            'max_team_size' => 5,
        ]);
    }

// Step 1: register
    public function test_student_can_register(): void
    {
        Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => '']);
        putenv('STUDENT_ALLOWED_DOMAINS=');
        $this->app['config']->set('app.student_allowed_domains', '');

        $res = $this->postJson('/api/auth/register', [
            'first_name' => 'Jan',
            'last_name' => 'Novak',
            'email' => 'jan@test.sk',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'role' => 'mentor',
            'agreed_terms' => true,
            'gdpr_consent' => true,
        ]);

        $res->assertStatus(201)->assertJsonStructure(['token', 'user']);
    }

// Step 2: complete student profile
    public function test_student_can_create_profile(): void
    {
        $student = $this->makeUser($this->studentRole);

        $res = $this->actingAs($student)->postJson('/api/profile/student', [
            'study_program' => 'Aplikovaná informatika',
            'year_of_study' => 2,
            'university' => 'UKF Nitra',
            'bio' => 'short bio',
            'academic_declaration_confirmed' => true,
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id]);
    }

// Step 3: create team with min 3 members
    public function test_leader_can_create_team_and_invite_members(): void
    {
        $leader = $this->makeUser($this->studentRole);
        $m1 = $this->makeUser($this->studentRole);
        $m2 = $this->makeUser($this->studentRole);

        $res = $this->actingAs($leader)->postJson('/api/teams', [
            'name' => 'Alpha',
            'description' => 'desc',
        ]);
        $res->assertStatus(201);

        $team = Team::where('leader_id', $leader->id)->first();

        $this->actingAs($leader)->postJson("/api/teams/{$team->id}/invite", ['email' => $m1->email])->assertStatus(200);
        $this->actingAs($leader)->postJson("/api/teams/{$team->id}/invite", ['email' => $m2->email])->assertStatus(200);

        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'user_id' => $m1->id, 'status' => 'pending']);
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'user_id' => $m2->id, 'status' => 'pending']);
    }

// Step 4: members accept, team has 3 accepted members
    public function test_members_accept_invitations(): void
    {
        $leader = $this->makeUser($this->studentRole);
        $m1 = $this->makeUser($this->studentRole);
        $m2 = $this->makeUser($this->studentRole);

        $team = Team::factory()->create(['leader_id' => $leader->id, 'status' => 'forming']);
        $team->members()->syncWithoutDetaching([
            $leader->id => ['status' => 'accepted', 'joined_at' => now()],
            $m1->id => ['status' => 'pending', 'joined_at' => null],
            $m2->id => ['status' => 'pending', 'joined_at' => null],
        ]);

        $this->actingAs($m1)->postJson("/api/teams/{$team->id}/invitation/respond", ['status' => 'accepted'])->assertStatus(200);
        $this->actingAs($m2)->postJson("/api/teams/{$team->id}/invitation/respond", ['status' => 'accepted'])->assertStatus(200);

        $this->assertEquals('accepted', $team->members()->where('user_id', $m1->id)->first()->pivot->status);
        $this->assertEquals('accepted', $team->members()->where('user_id', $m2->id)->first()->pivot->status);
    }

// Step 5: submit application
    public function test_leader_can_submit_team_application_to_program_a(): void
    {
        $leader = $this->makeUser($this->studentRole);
        $m1 = $this->makeUser($this->studentRole);
        $m2 = $this->makeUser($this->studentRole);

        StudentProfile::factory()->create(['user_id' => $leader->id]);

        $team = Team::factory()->create(['leader_id' => $leader->id, 'status' => 'forming']);
        $team->members()->syncWithoutDetaching([
            $leader->id => ['status' => 'accepted', 'joined_at' => now()],
            $m1->id => ['status' => 'accepted', 'joined_at' => now()],
            $m2->id => ['status' => 'accepted', 'joined_at' => now()],
        ]);

        $call = $this->makeOpenCall($this->makeProgram());

        $res = $this->actingAs($leader)->postJson('/api/applications', [
            'applicant_type' => 'team',
            'program_type' => 'a',
            'team_id' => $team->id,
            'call_id' => $call->id,
            'category' => 'AI',
            'submit_type' => 'draft',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('applications', [
            'team_id' => $team->id,
            'program_type' => 'a',
            'status' => 'draft',
        ]);
    }

// Step 6: admin moves to under_evaluation
    public function test_admin_can_move_application_to_evaluation(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $program = $this->makeProgram();
        $call = $this->makeOpenCall($program);

        $student = $this->makeUser($this->studentRole);
        $profile = StudentProfile::factory()->create(['user_id' => $student->id]);

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'student_profile_id' => $profile->id,
            'status' => 'formally_verified',
        ]);

        $res = $this->actingAs($admin)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'under_evaluation',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'under_evaluation']);
    }

// Step 7: evaluator submits evaluation
    public function test_evaluator_can_evaluate_application(): void
    {
        $evaluator = $this->makeUser($this->evaluatorRole);
        $program = $this->makeProgram();
        $call = $this->makeOpenCall($program);

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'under_evaluation',
        ]);

        $res = $this->actingAs($evaluator)->postJson('/api/evaluations', [
            'application_id' => $app->id,
            'recommendation' => 'approve',
            'comment' => 'Strong project',
            'scores' => [
                ['criterion_key' => 'innovation', 'score' => 85, 'weight_at_moment' => 50, 'comment' => null],
                ['criterion_key' => 'feasibility', 'score' => 70, 'weight_at_moment' => 50, 'comment' => null],
            ],
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('evaluations', ['application_id' => $app->id, 'evaluator_id' => $evaluator->id]);
    }

// Step 8: admin approves
    public function test_admin_can_approve_application(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $program = $this->makeProgram();
        $call = $this->makeOpenCall($program);

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'under_evaluation',
        ]);

        $res = $this->actingAs($admin)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'approved',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'approved']);
    }

// Full happy path chained
    public function test_full_program_a_workflow(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $evaluator = $this->makeUser($this->evaluatorRole);
        $leader = $this->makeUser($this->studentRole);
        $m1 = $this->makeUser($this->studentRole);
        $m2 = $this->makeUser($this->studentRole);

        StudentProfile::factory()->create(['user_id' => $leader->id]);

        $team = Team::factory()->create(['leader_id' => $leader->id, 'status' => 'forming']);
        $team->members()->syncWithoutDetaching([
            $leader->id => ['status' => 'accepted', 'joined_at' => now()],
            $m1->id => ['status' => 'accepted', 'joined_at' => now()],
            $m2->id => ['status' => 'accepted', 'joined_at' => now()],
        ]);

        $call = $this->makeOpenCall($this->makeProgram());

// submit
        $appRes = $this->actingAs($leader)->postJson('/api/applications', [
            'applicant_type' => 'team',
            'program_type' => 'a',
            'team_id' => $team->id,
            'call_id' => $call->id,
            'category' => 'AI',
        ]);
        $appRes->assertStatus(201);
        $appId = $appRes->json('application_id');

// admin: formally_verified → under_evaluation
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'formally_verified'])->assertStatus(200);
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'under_evaluation'])->assertStatus(200);

// evaluate
        $this->actingAs($evaluator)->postJson('/api/evaluations', [
            'application_id' => $appId,
            'recommendation' => 'approve',
            'comment' => 'good',
            'scores' => [
                ['criterion_key' => 'innovation', 'score' => 90, 'weight_at_moment' => 100, 'comment' => null],
            ],
        ])->assertStatus(201);

// approve
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'approved'])->assertStatus(200);

        $this->assertDatabaseHas('applications', ['id' => $appId, 'status' => 'approved']);
    }
}