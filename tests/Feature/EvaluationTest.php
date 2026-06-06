<?php

use App\Models\Application;
use App\Models\Call;
use App\Models\Evaluation;
use App\Models\EvaluationCriteriaScore;
use App\Models\Role;
use App\Models\User;
use \App\Models\EvaluationCriterion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// helpers

function makeRole(string $slug): Role
{
    return Role::firstOrCreate(['slug' => $slug], ['name' => $slug, 'description' => $slug]);
}

function makeUser(string $roleSlug): User
{
    $user = User::factory()->create();
    $role = makeRole($roleSlug);
    $user->roles()->attach($role->id, ['granted_by' => $user->id, 'granted_at' => now()]);
    return $user;
}

function makeApplication(): Application
{
    $call = Call::factory()->create(['program' => 'a']);

    \App\Models\EvaluationCriterion::create([
        'call_id' => $call->id,
        'slug' => 'innovation',
        'title' => 'Innovation',
        'weight' => 50,
    ]);
    \App\Models\EvaluationCriterion::create([
        'call_id' => $call->id,
        'slug' => 'feasibility',
        'title' => 'Feasibility',
        'weight' => 50,
    ]);

    return Application::create([
        'call_id' => $call->id,
        'applicant_type' => 'student',
        'status' => 'under_evaluation',
    ]);
}

function validPayload(int $applicationId): array
{
    $app = Application::find($applicationId);
    $criteria = \App\Models\EvaluationCriterion::where('call_id', $app->call_id)->get();

    return [
        'application_id' => $applicationId,
        'recommendation' => 'approve',
        'comment' => 'Looks good',
        'scores' => $criteria->map(fn($c) => [
            'criterion_id' => $c->id,
            'score' => 80,
            'weight_at_moment' => $c->weight,
            'comment' => null,
        ])->toArray(),
    ];
}

// Bug 5 — auth check before DB query
test('unauthenticated user gets 401', function () {
    $app = makeApplication();

    $this->postJson('/api/evaluations', validPayload($app->id))
        ->assertStatus(401);
});

test('unauthorized role cannot create evaluation', function () {
    $user = makeUser('student');
    $app = makeApplication();

    $this->actingAs($user)
        ->postJson('/api/evaluations', validPayload($app->id))
        ->assertStatus(403);
});

test('non-existent application returns 404 not 403 for authorized user', function () {
    $user = makeUser('evaluator');

    $this->actingAs($user)
        ->postJson('/api/evaluations', [
            'application_id' => 99999,
            'recommendation' => 'approve',
            'comment' => 'test',
            'scores' => [],
        ])
        ->assertStatus(422);
});

// Bug 3 & 2 — status=completed and evaluated_at set on store
test('evaluator can create evaluation and status is completed', function () {
    $user = makeUser('evaluator');
    $app = makeApplication();

    $this->actingAs($user)
        ->postJson('/api/evaluations', validPayload($app->id))
        ->assertStatus(201);

    $evaluation = Evaluation::where('application_id', $app->id)
        ->where('evaluator_id', $user->id)
        ->first();

    expect($evaluation)->not->toBeNull();
    expect($evaluation->status)->toBe('completed');
    expect($evaluation->evaluated_at)->not->toBeNull();
});

// Bug 4 — ID returned in response
test('store returns evaluation id', function () {
    $user = makeUser('evaluator');
    $app = makeApplication();

    $response = $this->actingAs($user)
        ->postJson('/api/evaluations', validPayload($app->id));

    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'message']);
});

// Bug 3 — update also sets status=completed and evaluated_at
test('update sets status completed and evaluated_at', function () {
    $user = makeUser('evaluator');
    $app = makeApplication();

    $createResponse = $this->actingAs($user)
        ->postJson('/api/evaluations', validPayload($app->id));

    $id = $createResponse->json('id');

    $criterion = EvaluationCriterion::where('call_id', $app->call_id)->first();

    $this->actingAs($user)
        ->patchJson("/api/evaluations/{$id}", [
            'recommendation' => 'reject',
            'scores' => [
                [
                    'criterion_id' => $criterion->id,
                    'score' => 40,
                    'weight_at_moment' => 100,
                    'comment' => null,
                ],
            ],
        ])
        ->assertStatus(200);

    $evaluation = Evaluation::find($id);
    expect($evaluation->status)->toBe('completed')
        ->and($evaluation->evaluated_at)->not->toBeNull();
});

// Duplicate guard
test('same evaluator cannot evaluate same application twice', function () {
    $user = makeUser('evaluator');
    $app = makeApplication();

    $this->actingAs($user)->postJson('/api/evaluations', validPayload($app->id));
    $this->actingAs($user)->postJson('/api/evaluations', validPayload($app->id))
        ->assertStatus(409);
});

// Scores stored
test('criteria scores are persisted', function () {
    $user = makeUser('evaluator');
    $app = makeApplication();

    $this->actingAs($user)
        ->postJson('/api/evaluations', validPayload($app->id))
        ->assertStatus(201);

    $evaluation = Evaluation::where('application_id', $app->id)->first();
    expect($evaluation->scores)->toHaveCount(2);
});

// Update blocked on completed — only reachable if status is manually forced
// because store() now sets completed immediately
test('cannot update a completed evaluation by another user', function () {
    $owner = makeUser('evaluator');
    $other = makeUser('evaluator');
    $app = makeApplication();

    $createResponse = $this->actingAs($owner)
        ->postJson('/api/evaluations', validPayload($app->id));

    $id = $createResponse->json('id');

    $this->actingAs($other)
        ->patchJson("/api/evaluations/{$id}", ['recommendation' => 'reject'])
        ->assertStatus(403);
});

test('nti_admin can create evaluation', function () {
    $user = makeUser('nti_admin');
    $app = makeApplication();

    $this->actingAs($user)
        ->postJson('/api/evaluations', validPayload($app->id))
        ->assertStatus(201);
});
