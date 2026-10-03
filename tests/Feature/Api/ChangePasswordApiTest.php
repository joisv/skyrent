<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_change_password(): void
    {
        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password123',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(401);
    }

    public function test_change_password_requires_fields(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password', 'new_password']);
    }

    public function test_change_password_fails_if_current_password_is_incorrect(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct-old-password'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'wrong-old-password',
                'new_password' => 'new-secret-password-123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_change_password_fails_if_new_password_is_too_short(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'password123',
                'new_password' => '123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_change_password_fails_if_new_password_same_as_current(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'password123',
                'new_password' => 'password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_change_password_fails_if_confirmation_mismatches(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'password123',
                'new_password' => 'secret123',
                'new_password_confirmation' => 'different-secret',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password_confirmation']);
    }

    public function test_user_can_successfully_change_password_and_login_with_new_password(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $user = User::factory()->create([
            'email' => 'kasir@skyrental.id',
            'password' => bcrypt('old-password-123'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'old-password-123',
                'new_password' => 'new-secure-password-456',
                'new_password_confirmation' => 'new-secure-password-456',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify password in DB updated
        $this->assertTrue(Hash::check('new-secure-password-456', $user->fresh()->password));

        // Verify old password cannot be used to login
        $oldLoginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'kasir@skyrental.id',
            'password' => 'old-password-123',
        ]);
        $oldLoginResponse->assertStatus(401);

        // Verify new password can be used to login
        $newLoginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'kasir@skyrental.id',
            'password' => 'new-secure-password-456',
        ]);
        $newLoginResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_change_password_via_alias_route(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('kasirpass123'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'kasirpass123',
                'new_password' => 'brand-new-pass-999',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue(Hash::check('brand-new-pass-999', $user->fresh()->password));
    }
}