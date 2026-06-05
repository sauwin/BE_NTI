<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Models\PasswordResetToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Role::insert([
            ['name' => 'Student', 'slug' => 'student', 'description' => ''],
            ['name' => 'Mentor', 'slug' => 'mentor', 'description' => ''],
            ['name' => 'Company', 'slug' => 'company', 'description' => ''],
        ]);
    }

    public function test_register_student_returns_201(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        
        $res = $this->postJson('/api/auth/register', [
            'first_name' => 'Jan',
            'last_name' => 'Novak',
            'email' => 'jan@ukf.sk',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'role' => 'student',
            'agreed_terms' => true,
            'gdpr_consent' => true,
        ]);
        $res->assertStatus(201)->assertJsonStructure(['token', 'user']);
    }

    public function test_register_student_invalid_domain_returns_422(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $res = $this->postJson('/api/auth/register', [
            'first_name' => 'Jan',
            'last_name' => 'Novak',
            'email' => 'jan@test.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'role' => 'student',
            'agreed_terms' => true,
            'gdpr_consent' => true,
        ]);

        $res->assertStatus(422)->assertJsonPath('errors.email.0', fn($v) => str_contains($v, 'domain'));
    }

    public function test_register_duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'dup@ukf.sk']);

        $res = $this->postJson('/api/auth/register', [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'dup@ukf.sk',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'role' => 'mentor',
            'agreed_terms' => true,
            'gdpr_consent' => true,
        ]);

        $res->assertStatus(422);
    }

    public function test_login_active_user_returns_token(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $res = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $res->assertStatus(200)->assertJsonStructure(['token']);
    }

    public function test_login_wrong_password_returns_401(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $res = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrongpass',
        ]);

        $res->assertStatus(401);
    }

    public function test_login_pending_verification_returns_403(): void
    {
        $user = User::factory()->create(['status' => 'pending_verification']);

        $res = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $res->assertStatus(403)->assertJsonPath('message', 'pending_verification');
    }

    public function test_login_blocked_user_returns_403(): void
    {
        $user = User::factory()->create(['status' => 'blocked']);

        $res = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $res->assertStatus(403)->assertJsonPath('message', 'blocked');
    }

    public function test_password_reset_request_stores_token(): void
    {
        $user = User::factory()->create();

        $res = $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('password_reset_tokens', ['user_id' => $user->id]);
    }

    public function test_password_reset_with_valid_token_updates_password(): void
    {
        $user = User::factory()->create();
        $raw = \Illuminate\Support\Str::random(64);
        PasswordResetToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $raw),
            'expires_at' => now()->addHour(),
        ]);

        $res = $this->postJson('/api/auth/reset-password', [
            'token' => $raw,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ]);

        $res->assertStatus(200);
        $this->assertTrue(Hash::check('newpassword1', $user->fresh()->password));
    }

    public function test_password_reset_expired_token_returns_400(): void
    {
        $user = User::factory()->create();
        $raw = \Illuminate\Support\Str::random(64);
        PasswordResetToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $raw),
            'expires_at' => now()->subHour(),
        ]);

        $res = $this->postJson('/api/auth/reset-password', [
            'token' => $raw,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ]);

        $res->assertStatus(400);
    }

    public function test_logout_deletes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withToken($token)->postJson('/api/auth/logout');

        $res->assertStatus(200);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}