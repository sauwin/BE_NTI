<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Call;
use App\Models\Evaluation;
use App\Models\Mentorship;
use App\Models\Milestone;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use App\Models\EvaluationCriterion;
use App\Models\CallEvaluator;
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

    private function makeOpenCall(): Call
    {
        return Call::factory()->create([
            'program' => 'a',
            'status' => 'open',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(7),
            'min_team_size' => 3,
            'max_team_size' => 5,
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

// Step 1: register
    public function test_student_can_register(): void
    {
        Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => '']);
        putenv('STUDENT_ALLOWED_DOMAINS=test.sk');
        $this->app['config']->set('app.student_allowed_domains', 'test.sk');

        $res = $this->postJson('/api/auth/register', [
            'first_name' => 'Jan',
            'last_name' => 'Novak',
            'email' => 'jan@test.sk',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'student',
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

// Step 4: members accept
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

// Step 5: submit application as draft then submit it
    public function test_leader_can_create_draft_application(): void
    {
        [$leader, $team] = $this->makeTeamWithMembers(3);
        $call = $this->makeOpenCall();

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

// Step 6: leader submits draft → status becomes submitted
    public function test_leader_can_submit_draft_application(): void
    {
        [$leader, $team] = $this->makeTeamWithMembers(3);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'student_profile_id' => $leader->studentProfile->id,
            'applicant_type' => 'team',
            'program_type' => 'a',
            'team_id' => $team->id,
            'status' => 'draft',
            'category' => 'Software Development',
        ]);

        $res = $this->actingAs($leader)->postJson("/api/applications/{$app->id}/submit");

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'submitted']);
    }

// Step 7: admin formally verifies
    public function test_admin_can_formally_verify_submitted_application(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'submitted',
        ]);

        $res = $this->actingAs($admin)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'formally_verified',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'formally_verified']);
    }

// Step 8: admin moves to under_evaluation
    public function test_admin_can_move_application_to_evaluation(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'formally_verified',
        ]);

        $res = $this->actingAs($admin)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'under_evaluation',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'under_evaluation']);
    }

// Step 9: admin moves to under_evaluation
    public function test_admin_can_create_criteria(): void
    {
        $admin = $this->makeUser($this->adminRole);

        $call = $this->makeOpenCall();

        $this->actingAs($admin)->putJson(
            "api/admin/calls/{$call->id}/criteria",
            [
                'criteria' => [
                    [
                        'call_id' => $call->id,
                        'slug' => 'test_criterion',
                        'title' => 'Test Criterion',
                        'weight' => 80,
                    ],
                    [
                        'call_id' => $call->id,
                        'slug' => 'other_test_criterion',
                        'title' => 'Other Test Criterion',
                        'weight' => 20,
                    ],
                ],
            ]
        );

        $this->assertDatabaseHas('evaluation_criteria', ['call_id' => $call->id]);
    }

// Step 10: evaluator submits evaluation
    public function test_evaluator_can_evaluate_application(): void
    {
        $evaluator = $this->makeUser($this->evaluatorRole);
        $admin = $this->makeUser($this->adminRole);

        $call = $this->makeOpenCall();

        $res = $this->actingAs($admin)->putJson(
            "api/admin/calls/{$call->id}/criteria",
            [
                'criteria' => [
                    [
                        'call_id' => $call->id,
                        'slug' => 'test_criterion',
                        'title' => 'Test Criterion',
                        'weight' => 80,
                    ],
                    [
                        'call_id' => $call->id,
                        'slug' => 'other_test_criterion',
                        'title' => 'Other Test Criterion',
                        'weight' => 20,
                    ],
                ],
            ]
        );

        $data = $res->json();

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
                ['criterion_id' => $data[0]['id'], 'score' => 85, 'weight_at_moment' => 50, 'comment' => null],
                ['criterion_id' => $data[0]['id'], 'score' => 70, 'weight_at_moment' => 50, 'comment' => null],
            ],
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('evaluations', ['application_id' => $app->id, 'evaluator_id' => $evaluator->id]);
    }

// Step 11: admin finalizes evaluation
    public function test_admin_can_finalize_evaluation(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $evaluator = $this->makeUser($this->evaluatorRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'under_evaluation',
        ]);

        Evaluation::create([
            'application_id' => $app->id,
            'evaluator_id' => $evaluator->id,
            'status' => 'completed'
        ]);

        CallEvaluator::create([
            'call_id' => $call->id,
            'user_id' => $evaluator->id
        ]);

        $res = $this->actingAs($admin)->postJson("/api/admin/applications/{$app->id}/finalize-evaluation", [
            'status' => 'approved',
            'comment' => 'Good job',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'approved']);
    }

// Step 12: admin requests revision
    public function test_admin_can_request_revision(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'under_evaluation',
        ]);

        $res = $this->actingAs($admin)->postJson("/api/applications/{$app->id}/revisions", [
            'message' => 'Please add budget breakdown',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'pending_revision']);
        $this->assertDatabaseHas('application_revision_requests', [
            'application_id' => $app->id,
            'requested_by' => $admin->id,
            'message' => 'Please add budget breakdown',
        ]);
    }

    public function test_mentor_can_request_revision(): void
    {
        $mentor = $this->makeUser($this->mentorRole);
        $student = $this->makeUser($this->studentRole);
        $call = $this->makeOpenCall();
        $profile = StudentProfile::factory()->create(['user_id' => $student->id]);

        $app = Application::create([
            'call_id' => $call->id,
            'student_profile_id' => $profile->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'under_evaluation',
        ]);

        Mentorship::create([
            'application_id' => $app->id,
            'mentor_id' => $mentor->id,
        ]);

        $res = $this->actingAs($mentor)->postJson("/api/applications/{$app->id}/revisions", [
            'message' => 'Please refine your timeline',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'pending_revision']);
        $this->assertDatabaseHas('application_revision_requests', [
            'application_id' => $app->id,
            'requested_by' => $mentor->id,
            'message' => 'Please refine your timeline',
        ]);
    }

// Step 13: applicant applies revision and resubmits
    public function test_applicant_can_resubmit_after_revision_request(): void
    {
        [$leader, $team] = $this->makeTeamWithMembers(3);
        $admin = $this->makeUser($this->adminRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'student_profile_id' => $leader->studentProfile->id,
            'applicant_type' => 'team',
            'program_type' => 'a',
            'team_id' => $team->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($admin)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'pending_revision',
            'comment' => 'Please add budget breakdown',
        ]);

        $res = $this->actingAs($leader)->postJson("/api/applications/{$app->id}/apply-changes");

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'submitted']);
    }

// Step 14: admin approves
    public function test_admin_can_approve_application(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $call = $this->makeOpenCall();

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

// Step 15: admin rejects
    public function test_admin_can_reject_application(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'under_evaluation',
        ]);

        $res = $this->actingAs($admin)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'rejected',
            'comment' => 'Does not meet criteria',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'rejected']);
    }

// Step 16: admin moves approved application to onboarding
    public function test_admin_can_move_approved_application_to_onboarding(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'approved',
        ]);

        $res = $this->actingAs($admin)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'onboarding',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'onboarding']);
    }

// Step 17: admin assigns mentor after approval
    public function test_admin_can_assign_mentor_to_approved_application(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $mentor = $this->makeUser($this->mentorRole);
        $student = $this->makeUser($this->studentRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'approved',
        ]);

        $res = $this->actingAs($admin)->postJson('/api/mentorships/assign', [
            'application_id' => $app->id,
            'mentor_id' => $mentor->id,
            'student_id' => $student->id,
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('mentorships', [
            'application_id' => $app->id,
            'mentor_id' => $mentor->id,
        ]);
    }

// Step 18: admin activates project
    public function test_mentor_can_set_project_active(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $mentor = $this->makeUser($this->mentorRole);
        $student = $this->makeUser($this->studentRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'onboarding',
        ]);

        $this->actingAs($admin)->postJson('/api/mentorships/assign', [
            'application_id' => $app->id,
            'mentor_id' => $mentor->id,
            'student_id' => $student->id,
        ]);

        $res = $this->actingAs($mentor)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'active',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'active']);
    }

// Step 19: mentor records consultation on active project
    public function test_mentor_can_record_consultation(): void
    {
        $mentor = $this->makeUser($this->mentorRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'active',
        ]);

        $mentorship = Mentorship::create([
            'application_id' => $app->id,
            'mentor_id' => $mentor->id,
            'assigned_at' => now(),
        ]);

        $res = $this->actingAs($mentor)->postJson("/api/mentorships/{$mentorship->id}/consultations", [
            'date' => now()->subDay()->toDateString(),
            'duration_minutes' => 60,
            'summary' => 'Reviewed architecture and database schema with the team.',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('consultations', [
            'mentorship_id' => $mentorship->id,
            'duration_minutes' => 60,
        ]);
    }

// Step 20: mentor creates milestone on active project
    public function test_mentor_can_create_milestone_for_active_project(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $mentor = $this->makeUser($this->mentorRole);
        $student = $this->makeUser($this->studentRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->postJson('/api/mentorships/assign', [
            'application_id' => $app->id,
            'mentor_id' => $mentor->id,
            'student_id' => $student->id,
        ]);

        $res = $this->actingAs($mentor)->postJson("/api/applications/{$app->id}/milestones", [
            'title' => 'MVP Release',
            'due_date' => now()->addDays(30)->toDateString(),
            'description' => 'First working prototype',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('milestones', [
            'application_id' => $app->id,
            'name' => 'MVP Release',
        ]);
    }

// Step 21: mentor can suspend active project
    public function test_mentor_can_suspend_active_project(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $mentor = $this->makeUser($this->mentorRole);
        $student = $this->makeUser($this->studentRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->postJson('/api/mentorships/assign', [
            'application_id' => $app->id,
            'mentor_id' => $mentor->id,
            'student_id' => $student->id,
        ]);

        $res = $this->actingAs($mentor)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'suspended',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'suspended']);
    }

// Step 22: mentor closes project
    public function test_mentor_can_close_completed_project(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $mentor = $this->makeUser($this->mentorRole);
        $student = $this->makeUser($this->studentRole);
        $call = $this->makeOpenCall();

        $app = Application::create([
            'call_id' => $call->id,
            'applicant_type' => 'student',
            'program_type' => 'a',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->postJson('/api/mentorships/assign', [
            'application_id' => $app->id,
            'mentor_id' => $mentor->id,
            'student_id' => $student->id,
        ]);

        $res = $this->actingAs($mentor)->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'closed',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'closed']);
    }

// Full happy path chained
    public function test_full_program_a_workflow(): void
    {
        $this->withoutExceptionHandling();
        $admin = $this->makeUser($this->adminRole);
        $evaluator = $this->makeUser($this->evaluatorRole);
        $mentor = $this->makeUser($this->mentorRole);
        $call = $this->makeOpenCall();
        [$leader, $team] = $this->makeTeamWithMembers(3);

// submit draft
        $appRes = $this->actingAs($leader)->postJson('/api/applications', [
            'applicant_type' => 'team',
            'program_type' => 'a',
            'team_id' => $team->id,
            'call_id' => $this->makeOpenCall()->id,
            'category' => 'AI',
            'submit_type' => 'draft',
        ]);
        $appRes->assertStatus(201);
        $appId = $appRes->json('application_id');

// draft → submitted
        $this->actingAs($leader)->postJson("/api/applications/{$appId}/submit")->assertStatus(200);

// submitted → formally_verified
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'formally_verified'])->assertStatus(200);

// formally_verified → under_evaluation
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'under_evaluation'])->assertStatus(200);

// set criteria
        $crits = $this->actingAs($admin)->putJson(
            "api/admin/calls/{$call->id}/criteria",
            [
                'criteria' => [
                    [
                        'call_id' => $call->id,
                        'slug' => 'test_criterion',
                        'title' => 'Test Criterion',
                        'weight' => 100,
                    ]
                ],
            ]
        );
        $crits->assertStatus(200);

        $critData = $crits->json();

// evaluate
        $this->actingAs($evaluator)->postJson('/api/evaluations', [
            'application_id' => $appId,
            'recommendation' => 'approve',
            'comment' => 'good',
            'scores' => [
                ['criterion_id' => $critData[0]['id'], 'score' => 90, 'weight_at_moment' => 100, 'comment' => null],
            ],
        ])->assertStatus(201);

// under_evaluation → approved
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'approved'])->assertStatus(200);

// approved → onboarding
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'onboarding'])->assertStatus(200);

// assign mentor
        $this->actingAs($admin)->postJson('/api/mentorships/assign', [
            'application_id' => $appId,
            'mentor_id' => $mentor->id,
            'student_id' => $leader->id,
        ])->assertStatus(201);

// onboarding → active
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'active'])->assertStatus(200);

// create milestone
        $this->actingAs($admin)->postJson("/api/applications/{$appId}/milestones", [
            'title' => 'Beta',
            'due_date' => now()->addDays(14)->toDateString(),
            'description' => 'Beta release',
        ])->assertStatus(201);

// active → closed
        $this->actingAs($admin)->patchJson("/api/applications/{$appId}/status", ['status' => 'closed'])->assertStatus(200);

        $this->assertDatabaseHas('applications', ['id' => $appId, 'status' => 'closed']);
    }
}