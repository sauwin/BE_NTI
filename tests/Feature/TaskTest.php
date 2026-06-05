<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Call;
use App\Models\Task;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    protected User $companyUser;
    protected Call $call;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Tech Corp',
            'status' => 'active',
            'is_public_partner' => false,
        ]);

        $this->companyUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active'
        ]);

        $this->call = Call::create([
            'program' => 'b',
            'name' => 'Test Call B',
            'status' => 'open',
            'min_team_size' => 1,
            'created_by' => 1
        ]);
    }

    public function test_company_can_create_task_for_program_b(): void
    {
        $this->actingAs($this->companyUser);

        $taskData = [
            'call_id' => $this->call->id,
            'title' => 'Build an AI App',
            'budget' => 5000.50,
            'brief' => 'Short brief here',
            'status' => 'draft',
            'required_technologies' => ['Vue.js', 'Laravel']
        ];

        $response = $this->postJson('/api/company/tasks', $taskData);

        $response->assertStatus(201)
                 ->assertJsonFragment(['title' => 'Build an AI App']);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Build an AI App',
            'organization_id' => $this->organization->id,
            'product_owner_user_id' => $this->companyUser->id,
        ]);
    }

    public function test_can_create_call_with_task_and_upload_documents(): void
    {
        Storage::fake('local');
        $this->actingAs($this->companyUser);

        $file = UploadedFile::fake()->create('requirements.pdf', 100, 'application/pdf');

        $payload = [
            'title' => 'Complex Call and Task',
            'status' => 'published',
            'budget' => 10000,
            'min_team_size' => 2,
            'files' => [
                'specification' => $file
            ]
        ];

        $response = $this->postJson('/api/calls-with-tasks', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'call' => ['id', 'name', 'status'],
                     'task' => ['id', 'title', 'documents']
                 ]);

        $documentPath = $response->json('task.documents.0.file_path');
        Storage::disk('local')->assertExists($documentPath);
    }

    public function test_can_update_task(): void
    {
        $this->actingAs($this->companyUser);

        $task = Task::create([
            'call_id' => $this->call->id,
            'organization_id' => $this->organization->id,
            'product_owner_user_id' => $this->companyUser->id,
            'title' => 'Initial Title',
            'status' => 'draft'
        ]);

        $response = $this->putJson("/api/company/tasks/{$task->id}", [
            'title' => 'Updated Title',
            'status' => 'published'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated Title',
            'status' => 'published'
        ]);
    }

    public function test_can_fetch_public_tasks_for_program_b(): void
    {
        Task::create([
            'call_id' => $this->call->id,
            'organization_id' => $this->organization->id,
            'product_owner_user_id' => $this->companyUser->id,
            'title' => 'Public Task',
            'status' => 'published'
        ]);

        $response = $this->getJson('/api/programs/b/tasks');

        $response->assertStatus(200)
                 ->assertJsonCount(1)
                 ->assertJsonFragment(['title' => 'Public Task']);
    }
}