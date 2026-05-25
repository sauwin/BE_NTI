<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_generates_hashed_password(): void
    {
        $user = User::factory()->create();
        $this->assertNotEmpty($user->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_login_succeeds_with_correct_password(): void
    {
        $user = User::factory()->create();
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $response->assertStatus(200);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);
        $response->assertStatus(401);
    }
}