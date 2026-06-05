<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;
    private Role $studentRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->studentRole = Role::create(['name' => 'Student', 'slug' => 'student', 'description' => '']);

        $this->leader = User::factory()->create(['status' => 'active']);
        \DB::table('user_roles')->insert([
            'user_id' => $this->leader->id,
            'role_id' => $this->studentRole->id,
            'granted_by' => null,
            'granted_at' => now(),
        ]);
    }

    private function makeStudent(): User
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

    public function test_leader_can_create_team(): void
    {
        $res = $this->actingAs($this->leader)->postJson('/api/teams', [
            'name' => 'Alpha Team',
            'description' => 'desc',
        ]);

        $res->assertStatus(201)->assertJsonPath('name', 'Alpha Team');
        $this->assertDatabaseHas('teams', ['name' => 'Alpha Team', 'leader_id' => $this->leader->id]);
    }

    public function test_creator_is_added_as_member(): void
    {
        $this->actingAs($this->leader)->postJson('/api/teams', ['name' => 'T1']);

        $team = Team::where('leader_id', $this->leader->id)->first();
        $this->assertNotNull($team->members()->where('user_id', $this->leader->id)->first());
    }

    public function test_leader_can_invite_student(): void
    {
        $team = Team::factory()->create(['leader_id' => $this->leader->id, 'status' => 'forming']);
        $team->members()->syncWithoutDetaching([$this->leader->id => ['status' => 'accepted', 'joined_at' => now()]]);

        $student = $this->makeStudent();

        $res = $this->actingAs($this->leader)->postJson("/api/teams/{$team->id}/invite", [
            'email' => $student->email,
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'user_id' => $student->id, 'status' => 'pending']);
    }

    public function test_cannot_invite_non_student(): void
    {
        $team = Team::factory()->create(['leader_id' => $this->leader->id, 'status' => 'forming']);
        $team->members()->syncWithoutDetaching([$this->leader->id => ['status' => 'accepted', 'joined_at' => now()]]);

        $nonStudent = User::factory()->create(['status' => 'active']);

        $res = $this->actingAs($this->leader)->postJson("/api/teams/{$team->id}/invite", [
            'email' => $nonStudent->email,
        ]);

        $res->assertStatus(422);
    }

    public function test_cannot_invite_already_member(): void
    {
        $team = Team::factory()->create(['leader_id' => $this->leader->id, 'status' => 'forming']);
        $member = $this->makeStudent();
        $team->members()->syncWithoutDetaching([
            $this->leader->id => ['status' => 'accepted', 'joined_at' => now()],
            $member->id => ['status' => 'accepted', 'joined_at' => now()],
        ]);

        $res = $this->actingAs($this->leader)->postJson("/api/teams/{$team->id}/invite", [
            'email' => $member->email,
        ]);

        $res->assertStatus(422);
    }

    public function test_invited_member_can_accept(): void
    {
        $team = Team::factory()->create(['leader_id' => $this->leader->id, 'status' => 'forming']);
        $student = $this->makeStudent();
        $team->members()->syncWithoutDetaching([
            $this->leader->id => ['status' => 'accepted', 'joined_at' => now()],
            $student->id => ['status' => 'pending', 'joined_at' => null],
        ]);

        $res = $this->actingAs($student)->postJson("/api/teams/{$team->id}/invitation/respond", [
            'status' => 'accepted',
        ]);

        $res->assertStatus(200);
        $this->assertEquals('accepted', $team->members()->where('user_id', $student->id)->first()->pivot->status);
    }

    public function test_invited_member_can_reject(): void
    {
        $team = Team::factory()->create(['leader_id' => $this->leader->id, 'status' => 'forming']);
        $student = $this->makeStudent();
        $team->members()->syncWithoutDetaching([
            $this->leader->id => ['status' => 'accepted', 'joined_at' => now()],
            $student->id => ['status' => 'pending', 'joined_at' => null],
        ]);

        $res = $this->actingAs($student)->postJson("/api/teams/{$team->id}/invitation/respond", [
            'status' => 'rejected',
        ]);

        $res->assertStatus(200);
        $this->assertNull($team->members()->where('user_id', $student->id)->first());
    }

    public function test_team_requires_min_3_members_for_final_submission(): void
    {
        $call = \App\Models\Call::factory()->create(['status' => 'open', 'program' => 'a']);
        $profile = \App\Models\StudentProfile::factory()->create(['user_id' => $this->leader->id]);

        $team = Team::factory()->create(['leader_id' => $this->leader->id, 'status' => 'forming']);
        $team->members()->syncWithoutDetaching([$this->leader->id => ['status' => 'accepted', 'joined_at' => now()]]);

        $res = $this->actingAs($this->leader)->postJson('/api/applications', [
            'applicant_type' => 'team',
            'program_type' => 'a',
            'team_id' => $team->id,
            'submit_type' => 'final',
            'category' => 'AI',
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['team_id']);
    }

    public function test_non_leader_cannot_delete_team(): void
    {
        $team = Team::factory()->create(['leader_id' => $this->leader->id, 'status' => 'forming']);
        $other = $this->makeStudent();
        $team->members()->syncWithoutDetaching([$other->id => ['status' => 'accepted', 'joined_at' => now()]]);

        $res = $this->actingAs($other)->deleteJson("/api/teams/{$team->id}");

        $res->assertStatus(403);
    }

    public function test_ready_team_cannot_be_deleted(): void
    {
        $team = Team::factory()->create(['leader_id' => $this->leader->id, 'status' => 'ready']);
        $team->members()->syncWithoutDetaching([$this->leader->id => ['status' => 'accepted', 'joined_at' => now()]]);

        $res = $this->actingAs($this->leader)->deleteJson("/api/teams/{$team->id}");

        $res->assertStatus(422);
    }
}
