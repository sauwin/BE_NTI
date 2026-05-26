<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $superAdminRole;
    private Role $studentRole;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'nti_admin', 'description' => '']);
        $this->superAdminRole = Role::create(['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => '']);
        $this->studentRole = Role::create(['name' => 'Student', 'slug' => 'student', 'description' => '']);
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

    private function makePendingStudent(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        \DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $this->studentRole->id,
            'granted_by' => null,
            'granted_at' => now(),
        ]);
        return $user;
    }

// --- Role approval ---

    public function test_admin_can_see_pending_approvals(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $pending = $this->makePendingStudent();

        $res = $this->actingAs($admin)->getJson('/api/admin/approvals');

        $res->assertStatus(200);
        $ids = array_column($res->json(), 'id');
        $this->assertContains($pending->id, $ids);
    }

    public function test_admin_can_approve_pending_role(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $pending = $this->makePendingStudent();

        $res = $this->actingAs($admin)->postJson("/api/admin/approve/{$pending->id}");

        $res->assertStatus(200)->assertJsonFragment(['message' => 'Role approved']);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $pending->id,
            'granted_by' => $admin->id,
        ]);
    }

    public function test_approve_user_with_no_pending_role_returns_404(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $alreadyApproved = $this->makeUser($this->studentRole);

        $res = $this->actingAs($admin)->postJson("/api/admin/approve/{$alreadyApproved->id}");

        $res->assertStatus(404);
    }

    public function test_non_admin_cannot_approve_role(): void
    {
        $student = $this->makeUser($this->studentRole);
        $pending = $this->makePendingStudent();

        $this->actingAs($student)->postJson("/api/admin/approve/{$pending->id}")->assertStatus(403);
    }

// --- Block / unblock ---

    public function test_admin_can_block_user(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $target = $this->makeUser($this->studentRole);

        $res = $this->actingAs($admin)->postJson("/api/admin/block/{$target->id}");

        $res->assertStatus(200)->assertJsonFragment(['message' => 'User blocked']);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'blocked']);
    }

    public function test_blocked_user_cannot_login(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $target = $this->makeUser($this->studentRole);

        $this->actingAs($admin)->postJson("/api/admin/block/{$target->id}");

        $res = $this->postJson('/api/auth/login', [
            'email' => $target->email,
            'password' => 'password',
        ]);
        $res->assertStatus(403)->assertJsonPath('message', 'blocked');
    }

    public function test_admin_can_unblock_user(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $target = User::factory()->create(['status' => 'blocked']);
        \DB::table('user_roles')->insert([
            'user_id' => $target->id,
            'role_id' => $this->studentRole->id,
            'granted_by' => $admin->id,
            'granted_at' => now(),
        ]);

        $res = $this->actingAs($admin)->postJson("/api/admin/unblock/{$target->id}");

        $res->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'active']);
    }

    public function test_non_admin_cannot_block_user(): void
    {
        $student = $this->makeUser($this->studentRole);
        $target = $this->makeUser($this->studentRole);

        $this->actingAs($student)->postJson("/api/admin/block/{$target->id}")->assertStatus(403);
    }

// --- Create call (výzva) ---

    public function test_admin_can_create_call(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $program = Program::factory()->programA()->create();

        $res = $this->actingAs($admin)->postJson('/api/admin/calls', [
            'program_type' => 'a',
            'name' => 'Q1 2026',
            'status' => 'draft',
            'opens_at' => now()->addDay()->toDateTimeString(),
            'deadline_at' => now()->addDays(30)->toDateTimeString(),
            'min_team_size' => 3,
            'max_team_size' => 5,
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('calls', ['name' => 'Q1 2026']);
    }

    public function test_admin_can_open_call(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $program = Program::factory()->programA()->create();
        $call = Call::factory()->create(['program_id' => $program->id, 'status' => 'draft']);

        $res = $this->actingAs($admin)->patchJson("/api/admin/calls/{$call->id}/status", [
            'status' => 'open',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('calls', ['id' => $call->id, 'status' => 'open']);
    }

    public function test_admin_can_close_call(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $program = Program::factory()->programA()->create();
        $call = Call::factory()->create(['program_id' => $program->id, 'status' => 'open']);

        $res = $this->actingAs($admin)->patchJson("/api/admin/calls/{$call->id}/status", [
            'status' => 'closed',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('calls', ['id' => $call->id, 'status' => 'closed']);
    }

    public function test_non_admin_cannot_create_call(): void
    {
        $student = $this->makeUser($this->studentRole);

        $this->actingAs($student)->postJson('/api/admin/calls', [
            'program_type' => 'a',
            'name' => 'test',
            'status' => 'draft',
        ])->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_admin_endpoints(): void
    {
        $this->getJson('/api/admin/users')->assertStatus(401);
        $this->getJson('/api/admin/approvals')->assertStatus(401);
        $this->postJson('/api/admin/calls')->assertStatus(401);
    }

// --- Super admin: create admin user ---

    public function test_super_admin_can_create_admin_user(): void
    {
        $super = $this->makeUser($this->superAdminRole);
        Role::firstOrCreate(['slug' => 'evaluator'], ['name' => 'Evaluator', 'description' => '']);

        $res = $this->actingAs($super)->postJson('/api/admin/create-admin', [
            'first_name' => 'Tomas',
            'last_name' => 'Admin',
            'email' => 'tomas@nti.sk',
            'password' => 'password1',
            'role' => 'evaluator',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'tomas@nti.sk']);
    }

    public function test_non_super_admin_cannot_create_admin_user(): void
    {
        $admin = $this->makeUser($this->adminRole);
        Role::firstOrCreate(['slug' => 'evaluator'], ['name' => 'Evaluator', 'description' => '']);

        $this->actingAs($admin)->postJson('/api/admin/create-admin', [
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'xy@nti.sk',
            'password' => 'password1',
            'role' => 'evaluator',
        ])->assertStatus(403);
    }

// --- Assign / remove role ---

    public function test_admin_can_assign_role_to_user(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $target = User::factory()->create(['status' => 'active']);
        Role::firstOrCreate(['slug' => 'mentor'], ['name' => 'Mentor', 'description' => '']);

        $res = $this->actingAs($admin)->postJson("/api/admin/users/{$target->id}/roles", [
            'role' => 'mentor',
        ]);

        $res->assertStatus(200);
    }

    public function test_admin_can_remove_role_from_user(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $target = $this->makeUser($this->studentRole);

        $res = $this->actingAs($admin)->deleteJson("/api/admin/users/{$target->id}/roles", [
            'role' => 'student',
        ]);

        $res->assertStatus(200);
    }
}