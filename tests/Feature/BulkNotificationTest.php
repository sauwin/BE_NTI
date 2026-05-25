<?php

use App\Jobs\SendBulkNotification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function bulkRole(string $slug): Role
{
    return Role::firstOrCreate(['slug' => $slug], ['name' => $slug, 'description' => $slug]);
}

function bulkUser(string $roleSlug): User
{
    $user = User::factory()->create(['status' => 'active']);
    $role = bulkRole($roleSlug);
    $user->roles()->attach($role->id, ['granted_by' => $user->id, 'granted_at' => now()]);
    return $user;
}

function bulkAdmin(): User
{
    return bulkUser('nti_admin');
}

function postBulk(User $admin, array $payload)
{
    return test()->actingAs($admin)->postJson('/api/admin/notifications/bulk', $payload);
}

// validation

test('rejects missing fields', function () {
    $admin = bulkAdmin();
    postBulk($admin, [])->assertStatus(422);
});

test('rejects invalid recipient_group', function () {
    $admin = bulkAdmin();
    postBulk($admin, [
        'recipient_group' => 'invalid_group',
        'subject' => 'Test',
        'message' => 'Hello',
    ])->assertStatus(422);
});

test('accepts call_ group format', function () {
    Queue::fake();
    $admin = bulkAdmin();
    postBulk($admin, [
        'recipient_group' => 'call_99',
        'subject' => 'Test',
        'message' => 'Hello',
    ])->assertStatus(200);
});

// auth

test('guest cannot access endpoint', function () {
    test()->postJson('/api/admin/notifications/bulk', [
        'recipient_group' => 'all',
        'subject' => 'Test',
        'message' => 'Hello',
    ])->assertStatus(401);
});

test('student cannot access endpoint', function () {
    $student = bulkUser('student');
    postBulk($student, [
        'recipient_group' => 'all',
        'subject' => 'Test',
        'message' => 'Hello',
    ])->assertStatus(403);
});

// queuing

test('dispatches job per recipient for all group', function () {
    Queue::fake();

    $admin = bulkAdmin();
    bulkUser('student');
    bulkUser('student');

    postBulk($admin, [
        'recipient_group' => 'all',
        'subject' => 'Broadcast',
        'message' => 'Hello everyone',
    ])->assertStatus(200)->assertJsonStructure(['queued']);

    Queue::assertPushed(SendBulkNotification::class);
});

test('dispatches only to students when group is students', function () {
    Queue::fake();

    $admin = bulkAdmin();
    $s1 = bulkUser('student');
    $s2 = bulkUser('student');
    bulkUser('company');

    $response = postBulk($admin, [
        'recipient_group' => 'students',
        'subject' => 'Students only',
        'message' => 'Hi students',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['queued' => 2]);

    Queue::assertPushed(SendBulkNotification::class, 2);
});

test('dispatches only to mentors when group is mentors', function () {
    Queue::fake();

    $admin = bulkAdmin();
    bulkUser('student');
    bulkUser('mentor');

    $response = postBulk($admin, [
        'recipient_group' => 'mentors',
        'subject' => 'Mentors',
        'message' => 'Hi mentors',
    ]);

    $response->assertJson(['queued' => 1]);
    Queue::assertPushed(SendBulkNotification::class, 1);
});

test('dispatches only to companies when group is companies', function () {
    Queue::fake();

    $admin = bulkAdmin();
    bulkUser('student');
    $c1 = bulkUser('company');
    $c2 = bulkUser('company');

    $response = postBulk($admin, [
        'recipient_group' => 'companies',
        'subject' => 'Companies',
        'message' => 'Hi companies',
    ]);

    $response->assertJson(['queued' => 2]);
});

test('queued job carries correct payload', function () {
    Queue::fake();

    $admin = bulkAdmin();
    bulkUser('mentor');

    postBulk($admin, [
        'recipient_group' => 'mentors',
        'subject' => 'Sub',
        'message' => 'Msg',
    ]);

    Queue::assertPushed(SendBulkNotification::class, function ($job) {
        return $job->subject === 'Sub'
            && $job->message === 'Msg'
            && $job->recipientGroup === 'mentors';
    });
});
