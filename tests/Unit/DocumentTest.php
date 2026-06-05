<?php

namespace Tests\Unit;

use App\Models\Application;
use App\Models\Call;
use App\Models\Document;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Application $application;

   protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        putenv('CLAMAV_SKIP_VALIDATION=true');

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $studentRole = Role::create(['name' => 'Student', 'slug' => 'student', 'description' => '']);

        $this->user = User::factory()->create(['status' => 'active']);
        $this->user->roles()->attach($studentRole->id);

        $call = Call::factory()->create(['program' => 'a', 'status' => 'open']);
        $profile = StudentProfile::factory()->create(['user_id' => $this->user->id]);

        $this->application = Application::factory()->create([
            'student_profile_id' => $profile->id,
            'call_id' => $call->id,
            'status' => 'draft',
            'program_type' => 'a',
            'applicant_type' => 'student',
        ]);
    }

    private function fakeFile(string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'nti_');

        if (str_contains($mime, 'pdf')) {
            file_put_contents($path,
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\nxref\n0 0\ntrailer\n<< >>\n%%EOF"
            );
        } elseif (str_contains($mime, 'openxmlformats') || str_contains($mime, 'ms-excel') || str_contains($mime, 'ms-powerpoint')) {
            $zip = new \ZipArchive;
            $zip->open($path, \ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
                '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
                '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
                '<Default Extension="xml" ContentType="application/xml"/>'.
                '</Types>'
            );
            $zip->addFromString('_rels/.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
                '</Relationships>'
            );
            $zip->close();
        } elseif ($mime === 'image/png') {
            // 1x1 real PNG
            file_put_contents($path, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk'.
                '+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
            ));
        } elseif ($mime === 'image/jpeg') {
            file_put_contents($path, base64_decode(
                '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsL'.
                'DBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/'.
                '2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy'.
                'MjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFgAB'.
                'AQEAAAAAAAAAAAAAAAAABQQGAwcAAQEAAAAAAAAAAAAAAAAAAAAAEAEAAAAAAAAA'.
                'AAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AmwAB/9k='
            ));
        }

        return new UploadedFile($path, $name, $mime, null, true);
    }

    public function test_upload_pdf_returns_201(): void
    {
        $file = $this->fakeFile('doc.pdf', 'application/pdf');
        $res = $this->actingAs($this->user)->postJson('/api/documents/upload', [
            'file' => $file,
            'type' => 'cv',
            'application_id' => $this->application->id,
        ]);
        $res->assertStatus(201)->assertJsonStructure(['id', 'file_name', 'mime_type']);
    }

    public function test_upload_docx_returns_201(): void
    {
        $file = $this->fakeFile(
            'doc.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        $res = $this->actingAs($this->user)->postJson('/api/documents/upload', [
            'file' => $file,
            'type' => 'technical_specification',
            'application_id' => $this->application->id,
        ]);
        $res->assertStatus(201);
    }

    public function test_upload_image_png_returns_201(): void
    {
        $file = $this->fakeFile('photo.png', 'image/png');
        $res = $this->actingAs($this->user)->postJson('/api/documents/upload', [
            'file' => $file,
            'type' => 'cv',
            'application_id' => $this->application->id,
        ]);
        $res->assertStatus(201);
    }

    public function test_upload_missing_file_returns_422(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/documents/upload', [
            'type' => 'cv',
            'application_id' => $this->application->id,
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_upload_missing_type_returns_422(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $res = $this->actingAs($this->user)->postJson('/api/documents/upload', [
            'file' => $file,
            'application_id' => $this->application->id,
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_upload_without_application_or_task_returns_422(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $res = $this->actingAs($this->user)->postJson('/api/documents/upload', [
            'file' => $file,
            'type' => 'cv',
        ]);

        $res->assertStatus(422);
    }

    public function test_upload_exceeds_size_returns_422(): void
    {
        $file = UploadedFile::fake()->create('big.pdf', 25000, 'application/pdf');

        $res = $this->actingAs($this->user)->postJson('/api/documents/upload', [
            'file' => $file,
            'type' => 'cv',
            'application_id' => $this->application->id,
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_document_stored_in_db_after_upload(): void
    {
        $file = $this->fakeFile('doc.pdf', 'application/pdf');
        $this->actingAs($this->user)->postJson('/api/documents/upload', [
            'file' => $file,
            'type' => 'cv',
            'application_id' => $this->application->id,
        ]);
        $this->assertDatabaseHas('documents', ['uploaded_by' => $this->user->id, 'type' => 'cv']);
    }

    public function test_uploader_can_download_own_document(): void
    {
        $file = $this->fakeFile('doc.pdf', 'application/pdf');

        $uploadRes = $this->actingAs($this->user)->postJson('/api/documents/upload', [
            'file'           => $file,
            'type'           => 'cv',
            'application_id' => $this->application->id,
        ]);

        $uploadRes->assertStatus(201);
        $id = $uploadRes->json('id');

        $res = $this->actingAs($this->user)->getJson("/api/documents/{$id}/download");
        $res->assertStatus(200);
    }

    public function test_unauthorized_user_cannot_download_document(): void
    {
        $doc = Document::factory()->create(['uploaded_by' => $this->user->id]);

        $other = User::factory()->create(['status' => 'active']);

        $res = $this->actingAs($other)->getJson("/api/documents/{$doc->id}/download");

        $res->assertStatus(403);
    }
}
