<?php

use App\Models\Application;
use App\Models\Call;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function appRole(string $slug): Role
{
    return Role::firstOrCreate(['slug' => $slug], ['name' => $slug, 'description' => $slug]);
}

function appUser(string $roleSlug): User
{
    $user = User::factory()->create();
    $role = appRole($roleSlug);
    $user->roles()->attach($role->id, ['granted_by' => $user->id, 'granted_at' => now()]);

    return $user;
}

function appWithOwner(): array
{
    $owner = appUser('student');
    $profile = StudentProfile::factory()->create(['user_id' => $owner->id]);

    $call = Call::factory()->create(['program' => 'a']);

    $application = Application::factory()->create([
        'student_profile_id' => $profile->id,
        'call_id' => $call->id,
    ]);

    return [$owner, $application];
}

// --- show ---

test('owner can view own application', function () {
    [$owner, $app] = appWithOwner();
    $this->actingAs($owner)->getJson("/api/applications/{$app->id}")->assertStatus(200);
});

test('other student cannot view application', function () {
    [, $app] = appWithOwner();
    $other = appUser('student');
    $this->actingAs($other)->getJson("/api/applications/{$app->id}")->assertStatus(403);
});

test('nti_admin can view any application', function () {
    [, $app] = appWithOwner();
    $admin = appUser('nti_admin');
    $this->actingAs($admin)->getJson("/api/applications/{$app->id}")->assertStatus(200);
});

test('super_admin can view any application', function () {
    [, $app] = appWithOwner();
    $admin = appUser('super_admin');
    $this->actingAs($admin)->getJson("/api/applications/{$app->id}")->assertStatus(200);
});

// --- update ---

test('owner can update draft application', function () {
    [$owner, $app] = appWithOwner();
    $app->update(['status' => 'draft']);
    $this->actingAs($owner)->patchJson("/api/applications/{$app->id}", [])->assertStatus(200);
});

test('other student cannot update application', function () {
    [, $app] = appWithOwner();
    $app->update(['status' => 'draft']);
    $other = appUser('student');
    $this->actingAs($other)->patchJson("/api/applications/{$app->id}", [])->assertStatus(403);
});

test('nti_admin can update any application', function () {
    [, $app] = appWithOwner();
    $app->update(['status' => 'draft']);
    $admin = appUser('nti_admin');
    $this->actingAs($admin)->patchJson("/api/applications/{$app->id}", [])->assertStatus(200);
});

// --- destroy ---

test('owner can delete draft application', function () {
    [$owner, $app] = appWithOwner();
    $app->update(['status' => 'draft']);
    $this->actingAs($owner)->deleteJson("/api/applications/{$app->id}")->assertStatus(200);
});

test('other student cannot delete application', function () {
    [, $app] = appWithOwner();
    $app->update(['status' => 'draft']);
    $other = appUser('student');
    $this->actingAs($other)->deleteJson("/api/applications/{$app->id}")->assertStatus(403);
});

test('nti_admin can delete any application', function () {
    [, $app] = appWithOwner();
    $app->update(['status' => 'draft']);
    $admin = appUser('nti_admin');
    $this->actingAs($admin)->deleteJson("/api/applications/{$app->id}")->assertStatus(200);
});

// --- index ---

test('student sees only own applications', function () {
    [$owner, $app] = appWithOwner();

    $call = \App\Models\Call::factory()->create(['program' => 'b']);
    Application::factory()->create(['call_id' => $call->id]);

    $response = $this->actingAs($owner)->getJson('/api/applications');
    $response->assertStatus(200);
    expect(count($response->json()))->toBe(1);
    expect($response->json()[0]['id'])->toBe($app->id);
});

test('nti_admin sees all applications', function () {
    appWithOwner();
    appWithOwner();
    $admin = appUser('nti_admin');

    $response = $this->actingAs($admin)->getJson('/api/applications');
    $response->assertStatus(200);
    expect(count($response->json()))->toBeGreaterThanOrEqual(2);
});
