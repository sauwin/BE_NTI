<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Call;
use App\Models\Mentorship;
use App\Models\Organization;
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
    private Role $mentorRole;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->studentRole = Role::create(['name' => 'Student', 'slug' => 'student', 'description' => '']);
        $this->companyRole = Role::create(['name' => 'Company', 'slug' => 'company', 'description' => '']);
        $this->evaluatorRole = Role::create(['name' => 'Evaluator', 'slug' => 'evaluator', 'description' => '']);
        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'nti_admin', 'description' => '']);
        $this->mentorRole = Role::create(['name' => 'Mentor', 'slug' => 'mentor', 'description' => '']);
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

    private function makeTeamWithMembers(int $count = 3): array
    {
        $leader = $this->makeUser($this->studentRole);
        $members = [];
        for ($i = 1; $i < $count; $i++) {
            $members[] = $this->makeUser($this->studentRole);
        }
        StudentProfile::factory()->create(['user_id' => $leader->id]);
        $team = Team::factory()->create(['leader_id' => $leader->id, 'status' => 'forming']);
        $pivot = [$leader->id => ['status' => 'accepted', 'joined_at' => now()]];
        foreach ($members as $m) {
            $pivot[$m->id] = ['status' => 'accepted', 'joined_at' => now()];
        }
        $team->members()->syncWithoutDetaching($pivot);
        return [$leader, $team];
    }

// Step 1: company creates task as draft (spec 8.2: draft)
    public function test_company_can_create_task(): void
    {
        $org = $this->makeOrg();
        $company = $this->makeUser($this->companyRole, $org->id);
        $call = Call::factory()->create([
            'program' => 'b',
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

// Step 2: company sets Product Owner on the task (spec 8.1: Product Owner za firmu)
    public function test_company_can_set_product_owner_on_task(): void
    {
        $org = $this->makeOrg();
        $company = $this->makeUser($this->companyRole, $org->id);
        $productOwner = $this->makeUser($this->companyRole, $org->id);
        $call = Call::factory()->create([
            'program' => 'b',
            'status' => 'open',
        ]);

        $task = Task::create([
            'call_id' => $call->id,
            'organization_id' => $org->id,
            'title' => 'Portal',
            'brief' => 'Build it',
            'budget' => 4000,
            'status' => 'draft',
        ]);

        $res = $this->actingAs($company)->putJson("/api/company/tasks/{$task->id}", [
            'product_owner_user_id' => $productOwner->id,
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'product_owner_user_id' => $productOwner->id]);
    }

// Step 3: company publishes task via call+task endpoint (spec 8.2: publikované do backlogu)
    public function test_company_can_publish_task_with_call(): void
    {
        $org = $this->makeOrg();
        $company = $this->makeUser($this->companyRole, $org->id);

        $res = $this->actingAs($company)->postJson('/api/calls-with-tasks', [
            'title' => 'E-commerce portal',
            'brief' => 'Build a shop',
            'budget' => 5000,
            'status' => 'published',
            'program' => 'b',
            'call_opens_at' => now()->toDateTimeString(),
            'call_deadline_at' => now()->addDays(14)->toDateTimeString(),
            'min_team_size' => 3,
            'max_team_size' => 5,
        ]);

        $res->assertStatus(201);
    }

// Step 4: published tasks visible publicly, draft tasks not (spec 8.2 backlog)
    public function test_published_tasks_visible_publicly(): void
    {
        $org = $this->makeOrg();
        $call = Call::factory()->create([
            'program' => 'b',
            'status' => 'open',
        ]);

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

    public function test_draft_task_not_visible_publicly(): void
    {
        $org = $this->makeOrg();
        $call = Call::factory()->create([
            'program' => 'b',
            'status' => 'open',
        ]);

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

// Step 6: student submits application to program B call (spec 8.3)
    public function test_student_can_apply_to_program_b_call(): void
    {
        $call = Call::factory()->create([
            'program' => 'b',
            'status' => 'open',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(7),
            'min_team_size' => 3,
        ]);

        [$leader, $team] = $this->makeTeamWithMembers(3);

        $res = $this->actingAs($leader)->postJson('/api/applications', [
            'applicant_type' => 'team',
            'program_type' => 'b',
            'team_id' => $team->id,
            'call_id' => $call->id,
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('applications', ['program_type' => 'b', 'team_id' => $team->id]);
    }

// Step 7: committee approves or rejects (spec 8.3: výber tímu komisii so zástupcom firmy)
    public function test_commission_can_approve_program_b_application(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $call = Call::factory()->create([
            'program' => 'b',
            'status' => 'open',
        ]);

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

    public function test_commission_can_reject_program_b_application(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $call = Call::factory()->create([
            'program' => 'b',
            'status' => 'open',
        ]);

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

// Step 8: admin assigns NTI mentor after approval (spec 8.3: pridelí mentor za NTI)
    public function test_admin_assigns_mentor_after_program_b_approval(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $mentor = $this->makeUser($this->mentorRole);
        [$leader, $team] = $this->makeTeamWithMembers(3);
        $call = Call::factory()->create([
            'program' => 'b',
            'status' => 'open',
        ]);

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'team',
            'program_type' => 'b',
            'team_id' => $team->id,
            'status' => 'approved',
        ]);

        $res = $this->actingAs($admin)->postJson('/api/mentorships/assign', [
            'application_id' => $app->id,
            'mentor_id' => $mentor->id,
            'student_id' => $leader->id,
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('mentorships', [
            'application_id' => $app->id,
            'mentor_id' => $mentor->id,
        ]);
    }

// Step 9: mentor records consultation during realization (spec 8.4)
    public function test_mentor_can_record_consultation_for_program_b(): void
    {
        $mentor = $this->makeUser($this->mentorRole);
        $call = Call::factory()->create([
            'program' => 'b',
            'status' => 'open',
        ]);

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'team',
            'program_type' => 'b',
            'status' => 'active',
        ]);

        $mentorship = Mentorship::create([
            'application_id' => $app->id,
            'mentor_id' => $mentor->id,
            'assigned_at' => now(),
        ]);

        $res = $this->actingAs($mentor)->postJson("/api/mentorships/{$mentorship->id}/consultations", [
            'date' => now()->subDay()->toDateString(),
            'duration_minutes' => 45,
            'summary' => 'Reviewed sprint backlog and unblocked the team on API integration issues.',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('consultations', [
            'mentorship_id' => $mentorship->id,
            'duration_minutes' => 45,
        ]);
    }

// Step 10: milestone tracking during active project (spec 8.4: míľniky projektu)
    public function test_admin_can_create_milestone_for_program_b_project(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $call = Call::factory()->create([
            'program' => 'b',
            'status' => 'open',
        ]);

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'team',
            'program_type' => 'b',
            'status' => 'active',
        ]);

        $res = $this->actingAs($admin)->postJson("/api/applications/{$app->id}/milestones", [
            'title' => 'MVP Delivery',
            'due_date' => now()->addDays(21)->toDateString(),
            'description' => 'Working MVP delivered to Product Owner',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('milestones', [
            'application_id' => $app->id,
            'name' => 'MVP Delivery',
        ]);
    }

// Step 12: admin closes application after delivery (spec 8.4: uzavreté)
    public function test_admin_can_close_program_b_application(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $call = Call::factory()->create([
            'program' => 'b',
            'status' => 'open',
        ]);

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'team',
            'program_type' => 'b',
            'status' => 'active',
        ]);

        $res = $this->actingAs($admin)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'closed',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'closed']);
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

// Full happy path: company posts task → student applies → committee picks team → mentor assigned → milestones → closed
    public function test_full_program_b_workflow(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $mentor = $this->makeUser($this->mentorRole);
        $org = $this->makeOrg();
        $company = $this->makeUser($this->companyRole, $org->id);
        [$leader, $team] = $this->makeTeamWithMembers(3);

// company creates and publishes task
        $callTaskRes = $this->actingAs($company)->postJson('/api/calls-with-tasks', [
            'title' => 'Logistics App',
            'brief' => 'Build logistics tracking',
            'budget' => 6000,
            'status' => 'published',
            'program' => 'b',
            'call_opens_at' => now()->toDateTimeString(),
            'call_deadline_at' => now()->addDays(14)->toDateTimeString(),
            'min_team_size' => 3,
            'max_team_size' => 5,
        ]);
        $callTaskRes->assertStatus(201);
        $callId = $callTaskRes->json('call.id') ?? $callTaskRes->json('call_id');

// student applies
        $appRes = $this->actingAs($leader)->postJson('/api/applications', [
            'applicant_type' => 'team',
            'program_type' => 'b',
            'team_id' => $team->id,
            'call_id' => $callId,
        ]);
        $appRes->assertStatus(201);
        $appId = $appRes->json('application_id');

// admin moves to under_evaluation then approves
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'formally_verified'])->assertStatus(200);
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'under_evaluation'])->assertStatus(200);
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'approved'])->assertStatus(200);

// assign mentor
        $this->actingAs($admin)->postJson('/api/mentorships/assign', [
            'application_id' => $appId,
            'mentor_id' => $mentor->id,
            'student_id' => $leader->id,
        ])->assertStatus(201);

// activate
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'onboarding'])->assertStatus(200);
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'active'])->assertStatus(200);

// milestone
        $this->actingAs($admin)->postJson("/api/applications/{$appId}/milestones", [
            'title' => 'Final Delivery',
            'due_date' => now()->addDays(30)->toDateString(),
            'description' => 'Deliver to company',
        ])->assertStatus(201);

// close
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'closed'])->assertStatus(200);

        $this->assertDatabaseHas('applications', ['id' => $appId, 'status' => 'closed']);
    }
}