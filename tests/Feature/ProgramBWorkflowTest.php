<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Call;
use App\Models\Evaluation;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProgramBWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Role $studentRole;
    private Role $companyRole;
    private Role $evaluatorRole;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->studentRole = Role::create(['name' => 'Student', 'slug' => 'student', 'description' => '']);
        $this->companyRole = Role::create(['name' => 'Company', 'slug' => 'company', 'description' => '']);
        $this->evaluatorRole = Role::create(['name' => 'Evaluator', 'slug' => 'evaluator', 'description' => '']);
        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'nti_admin', 'description' => '']);
    }

    private function makeUser(Role $role, ?int $orgId = null): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'organization_id' => $orgId,
            'role_in_org' => $orgId ? 'owner' : null,
        ]);
        \DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);
        return $user;
    }

    private function makeProgramB(): Program
    {
        return Program::firstOrCreate(
            ['code' => 'program_b'],
            ['type' => Program::TYPE_LIVE, 'is_active' => true, 'config' => null]
        );
    }

    private function makeOrg(): Organization
    {
        return Organization::create([
            'name' => 'Acme s.r.o.',
            'registration_number' => '12345678',
            'sector' => 'IT',
            'description' => 'desc',
            'status' => 'active',
        ]);
    }

// Step 1: company creates a task (draft)
    public function test_company_can_create_task(): void
    {
        $org = $this->makeOrg();
        $company = $this->makeUser($this->companyRole, $org->id);
        $program = $this->makeProgramB();
        $call = Call::factory()->create([
            'program_id' => $program->id,
            'status' => 'open',
        ]);

        $res = $this->actingAs($company)->postJson('/api/company/tasks', [
            'call_id' => $call->id,
            'title' => 'E-commerce portal',
            'brief' => 'Build a shop',
            'budget' => 5000,
            'status' => 'draft',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('tasks', ['title' => 'E-commerce portal', 'organization_id' => $org->id]);
    }

// Step 2: company publishes task via call+task endpoint
    public function test_company_can_publish_task_with_call(): void
    {
        $org = $this->makeOrg();
        $company = $this->makeUser($this->companyRole, $org->id);
        $program = $this->makeProgramB();

        $res = $this->actingAs($company)->postJson('/api/calls-with-tasks', [
            'title' => 'E-commerce portal',
            'brief' => 'Build a shop',
            'budget' => 5000,
            'status' => 'published',
            'program_id' => $program->id,
            'call_opens_at' => now()->toDateTimeString(),
            'call_deadline_at' => now()->addDays(14)->toDateTimeString(),
            'min_team_size' => 3,
            'max_team_size' => 5,
        ]);

        $res->assertStatus(201);
    }

// Step 3: student can see published tasks publicly
    public function test_published_tasks_visible_publicly(): void
    {
        $org = $this->makeOrg();
        $program = $this->makeProgramB();
        $call = Call::factory()->create(['program_id' => $program->id, 'status' => 'open']);
        Task::create([
            'call_id' => $call->id,
            'organization_id' => $org->id,
            'title' => 'Visible Task',
            'brief' => 'do something',
            'budget' => 3000,
            'status' => 'published',
        ]);

        $res = $this->getJson('/api/programs/b/tasks');

        $res->assertStatus(200);
        $res->assertJsonFragment(['title' => 'Visible Task']);
    }

// Step 4: student submits application to a program B call
    public function test_student_can_apply_to_program_b_call(): void
    {
        $program = $this->makeProgramB();
        $call = Call::factory()->create([
            'program_id' => $program->id,
            'status' => 'open',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(7),
            'min_team_size' => 3,
        ]);

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

        $res = $this->actingAs($leader)->postJson('/api/applications', [
            'applicant_type' => 'team',
            'program_type' => 'b',
            'team_id' => $team->id,
            'call_id' => $call->id,
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('applications', ['program_type' => 'b', 'team_id' => $team->id]);
    }

// Step 5: committee decides - approved
    public function test_commission_can_approve_program_b_application(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $program = $this->makeProgramB();
        $call = Call::factory()->create(['program_id' => $program->id, 'status' => 'open']);

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'team',
            'program_type' => 'b',
            'status' => 'under_evaluation',
        ]);

        $res = $this->actingAs($admin)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'approved',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'approved']);
    }

// Step 6: committee can reject
    public function test_commission_can_reject_program_b_application(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $program = $this->makeProgramB();
        $call = Call::factory()->create(['program_id' => $program->id, 'status' => 'open']);

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'team',
            'program_type' => 'b',
            'status' => 'under_evaluation',
        ]);

        $res = $this->actingAs($admin)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'rejected',
            'comment' => 'Not a fit',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'rejected']);
    }

// Draft task not visible publicly
    public function test_draft_task_not_visible_publicly(): void
    {
        $org = $this->makeOrg();
        $program = $this->makeProgramB();
        $call = Call::factory()->create(['program_id' => $program->id, 'status' => 'open']);
        Task::create([
            'call_id' => $call->id,
            'organization_id' => $org->id,
            'title' => 'Hidden Task',
            'brief' => 'do something',
            'budget' => 3000,
            'status' => 'draft',
        ]);

        $res = $this->getJson('/api/programs/b/tasks');
        $res->assertStatus(200);
        $data = $res->json();
        $titles = array_column(is_array($data) ? $data : ($data['data'] ?? []), 'title');
        $this->assertNotContains('Hidden Task', $titles);
    }

// Unauthenticated cannot create task
    public function test_unauthenticated_cannot_create_task(): void
    {
        $this->postJson('/api/company/tasks', [
            'title' => 'Test',
            'brief' => 'brief',
            'budget' => 1000,
        ])->assertStatus(401);
    }
}