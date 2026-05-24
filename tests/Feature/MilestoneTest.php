<?php

use App\Mail\MilestoneDeadlineReminderMail;
use App\Mail\MilestoneStatusChangedMail;
use App\Models\Application;
use App\Models\Call;
use App\Models\Milestone;
use App\Models\Program;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// --- helpers ---

function msRole(string $slug): Role
{
    return Role::firstOrCreate(['slug' => $slug], ['name' => $slug, 'description' => $slug]);
}

function msUser(string $roleSlug): User
{
    $user = User::factory()->create();
    $role = msRole($roleSlug);
    $user->roles()->attach($role->id, ['granted_by' => $user->id, 'granted_at' => now()]);

    return $user;
}

function msApplication(): Application
{
    $program = Program::firstOrCreate(
        ['code' => 'program_a'],
        ['type' => 'grant', 'is_active' => true, 'config' => null]
    );
    $call = Call::factory()->create(['program_id' => $program->id]);

    return Application::create([
        'call_id' => $call->id,
        'applicant_type' => 'student',
        'status' => 'approved',
    ]);
}

function msMilestone(int $applicationId, array $overrides = []): Milestone
{
    return Milestone::create(array_merge([
        'application_id' => $applicationId,
        'name' => 'Test Milestone',
        'description' => 'desc',
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'pending',
    ], $overrides));
}

// --- index ---

test('unauthenticated cannot list milestones', function () {
    $app = msApplication();
    $this->getJson("/api/applications/{$app->id}/milestones")
        ->assertStatus(401);
});

test('admin can list milestones for application', function () {
    $user = msUser('nti_admin');
    $app = msApplication();
    msMilestone($app->id);
    msMilestone($app->id);

    $this->actingAs($user)
        ->getJson("/api/applications/{$app->id}/milestones")
        ->assertStatus(200)
        ->assertJsonCount(2);
});

test('student without ownership gets 403 on index', function () {
    $user = msUser('student');
    $app = msApplication();

    $this->actingAs($user)
        ->getJson("/api/applications/{$app->id}/milestones")
        ->assertStatus(403);
});

test('student with linked profile can list milestones', function () {
    $user = msUser('student');
    $app = msApplication();
    StudentProfile::create([
        'user_id' => $user->id,
        'study_program' => 'IT',
        'year_of_study' => 2,
    ]);
    $app->student_profile_id = StudentProfile::where('user_id', $user->id)->first()->id;
    $app->save();
    msMilestone($app->id);

    $this->actingAs($user)
        ->getJson("/api/applications/{$app->id}/milestones")
        ->assertStatus(200)
        ->assertJsonCount(1);
});

test('index returns 404 for non-existent application', function () {
    $user = msUser('nti_admin');
    $this->actingAs($user)
        ->getJson('/api/applications/99999/milestones')
        ->assertStatus(404);
});

// --- store ---

test('unauthenticated cannot create milestone', function () {
    $app = msApplication();
    $this->postJson("/api/applications/{$app->id}/milestones", [
        'title' => 'M1', 'due_date' => now()->addDays(3)->toDateString(),
    ])->assertStatus(401);
});

test('student cannot create milestone', function () {
    $user = msUser('student');
    $app = msApplication();
    $this->actingAs($user)
        ->postJson("/api/applications/{$app->id}/milestones", [
            'title' => 'M1', 'due_date' => now()->addDays(3)->toDateString(),
        ])->assertStatus(403);
});

test('admin can create milestone', function () {
    $user = msUser('nti_admin');
    $app = msApplication();

    $this->actingAs($user)
        ->postJson("/api/applications/{$app->id}/milestones", [
            'title' => 'Sprint 1',
            'due_date' => now()->addDays(10)->toDateString(),
            'description' => 'First sprint',
        ])->assertStatus(201)
        ->assertJsonFragment(['name' => 'Sprint 1', 'status' => 'pending']);
});

test('mentor can create milestone', function () {
    $user = msUser('mentor');
    $app = msApplication();

    $this->actingAs($user)
        ->postJson("/api/applications/{$app->id}/milestones", [
            'title' => 'Sprint 1',
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertStatus(201);
});

test('store validates required fields', function () {
    $user = msUser('nti_admin');
    $app = msApplication();

    $this->actingAs($user)
        ->postJson("/api/applications/{$app->id}/milestones", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'due_date']);
});

test('store returns 404 for non-existent application', function () {
    $user = msUser('nti_admin');
    $this->actingAs($user)
        ->postJson('/api/applications/99999/milestones', [
            'title' => 'M', 'due_date' => now()->addDays(1)->toDateString(),
        ])->assertStatus(404);
});

// --- show ---

test('admin can show milestone', function () {
    $user = msUser('nti_admin');
    $app = msApplication();
    $milestone = msMilestone($app->id);

    $this->actingAs($user)
        ->getJson("/api/milestones/{$milestone->id}")
        ->assertStatus(200)
        ->assertJsonFragment(['id' => $milestone->id]);
});

test('show returns 404 for missing milestone', function () {
    $user = msUser('nti_admin');
    $this->actingAs($user)
        ->getJson('/api/milestones/99999')
        ->assertStatus(404);
});

test('unauthorized student cannot show milestone', function () {
    $user = msUser('student');
    $app = msApplication();
    $milestone = msMilestone($app->id);

    $this->actingAs($user)
        ->getJson("/api/milestones/{$milestone->id}")
        ->assertStatus(403);
});

// --- update ---

test('unauthenticated cannot update milestone', function () {
    $app = msApplication();
    $milestone = msMilestone($app->id);
    $this->patchJson("/api/milestones/{$milestone->id}", ['status' => 'completed'])
        ->assertStatus(401);
});

test('student cannot update milestone status', function () {
    $user = msUser('student');
    $app = msApplication();
    $milestone = msMilestone($app->id);

    $this->actingAs($user)
        ->patchJson("/api/milestones/{$milestone->id}", ['status' => 'completed'])
        ->assertStatus(403);
});

test('admin can update milestone status', function () {
    Mail::fake();
    $user = msUser('nti_admin');
    $app = msApplication();
    $milestone = msMilestone($app->id);

    $this->actingAs($user)
        ->patchJson("/api/milestones/{$milestone->id}", ['status' => 'in_progress'])
        ->assertStatus(200)
        ->assertJsonFragment(['status' => 'in_progress']);
});

test('update rejects invalid status value', function () {
    $user = msUser('nti_admin');
    $app = msApplication();
    $milestone = msMilestone($app->id);

    $this->actingAs($user)
        ->patchJson("/api/milestones/{$milestone->id}", ['status' => 'flying'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('completed_at is set when status becomes completed', function () {
    Mail::fake();
    $user = msUser('nti_admin');
    $app = msApplication();
    $milestone = msMilestone($app->id);

    $this->actingAs($user)
        ->patchJson("/api/milestones/{$milestone->id}", ['status' => 'completed'])
        ->assertStatus(200);

    expect(Milestone::find($milestone->id)->completed_at)->not->toBeNull();
});

test('status change sends email when student profile linked', function () {
    Mail::fake();
    $admin = msUser('nti_admin');
    $student = msUser('student');
    $app = msApplication();
    $profile = StudentProfile::create([
        'user_id' => $student->id,
        'study_program' => 'IT',
        'year_of_study' => 1,
    ]);
    $app->student_profile_id = $profile->id;
    $app->save();
    $milestone = msMilestone($app->id);

    $this->actingAs($admin)
        ->patchJson("/api/milestones/{$milestone->id}", ['status' => 'completed']);

    Mail::assertQueued(MilestoneStatusChangedMail::class);
});

// --- uploadDocument ---

test('unauthenticated cannot upload document', function () {
    $app = msApplication();
    $milestone = msMilestone($app->id);
    Storage::fake('local');

    $this->postJson("/api/milestones/{$milestone->id}/documents", [
        'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    ])->assertStatus(401);
});

test('admin can upload document to milestone', function () {
    Storage::fake('local');
    $user = msUser('nti_admin');
    $app = msApplication();
    $milestone = msMilestone($app->id);

    $this->actingAs($user)
        ->postJson("/api/milestones/{$milestone->id}/documents", [
            'file' => UploadedFile::fake()->create('report.pdf', 200, 'application/pdf'),
        ])->assertStatus(201)
        ->assertJsonStructure(['document_id', 'file_name']);
});

test('upload validates file is required', function () {
    $user = msUser('nti_admin');
    $app = msApplication();
    $milestone = msMilestone($app->id);

    $this->actingAs($user)
        ->postJson("/api/milestones/{$milestone->id}/documents", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('upload attaches document to milestone', function () {
    Storage::fake('local');
    $user = msUser('nti_admin');
    $app = msApplication();
    $milestone = msMilestone($app->id);

    $this->actingAs($user)
        ->postJson("/api/milestones/{$milestone->id}/documents", [
            'file' => UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf'),
        ]);

    expect($milestone->documents()->count())->toBe(1);
});

// --- deadline reminder command ---

test('deadline reminder command queues email for milestone due in 7 days', function () {
    Mail::fake();
    $app = msApplication();
    $student = msUser('student');
    $profile = StudentProfile::create([
        'user_id' => $student->id,
        'study_program' => 'IT',
        'year_of_study' => 1,
    ]);
    $app->student_profile_id = $profile->id;
    $app->save();
    msMilestone($app->id, ['due_date' => now()->addDays(5)->toDateString(), 'status' => 'pending']);

    $this->artisan('nti:deadlineReminders')->assertSuccessful();

    Mail::assertQueued(MilestoneDeadlineReminderMail::class);
});

test('deadline reminder skips completed milestones', function () {
    Mail::fake();
    $app = msApplication();
    $student = msUser('student');
    $profile = StudentProfile::create([
        'user_id' => $student->id,
        'study_program' => 'IT',
        'year_of_study' => 1,
    ]);
    $app->student_profile_id = $profile->id;
    $app->save();
    msMilestone($app->id, ['due_date' => now()->addDays(3)->toDateString(), 'status' => 'completed']);

    $this->artisan('nti:deadlineReminders')->assertSuccessful();

    Mail::assertNotQueued(MilestoneDeadlineReminderMail::class);
});
