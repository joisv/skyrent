<?php

namespace Tests\Feature\Api;

use App\Models\ShopSetting;
use App\Models\User;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLogoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_login_with_valid_email_and_password(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $user = User::factory()->create([
            'name' => 'Admin SKYRental',
            'email' => 'admin@skyrental.id',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@skyrental.id',
            'password' => 'password123',
            'device_name' => 'SKYRental Flutter Mobile',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => 'Admin SKYRental',
                        'email' => 'admin@skyrental.id',
                        'outlet_name' => 'Outlet Utama Malioboro',
                    ],
                    'token_type' => 'Bearer',
                ],
            ]);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);
    }

    public function test_can_login_via_shorter_alias_route_and_username(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $user = User::factory()->create([
            'name' => 'Budi Kasir',
            'email' => 'kasir@skyrental.id',
            'password' => bcrypt('kasirpass123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'Budi Kasir',
            'password' => 'kasirpass123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Budi Kasir',
                        'email' => 'kasir@skyrental.id',
                    ],
                ],
            ]);
    }

    public function test_login_fails_with_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'admin@skyrental.id',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@skyrental.id',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_login_validation_errors_when_fields_are_missing(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password', 'email']);
    }

    public function test_authenticated_user_can_view_profile_me(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $user = User::factory()->create([
            'name' => 'Admin Utama',
            'email' => 'admin@test.id',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => 'Admin Utama',
                    'email' => 'admin@test.id',
                ],
            ]);
    }

    public function test_user_can_logout_and_revoke_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('logout-token')->plainTextToken;

        // Logout
        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->app['auth']->forgetGuards();

        // Attempting to access protected endpoint with the revoked token must return 401
        $subsequentResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $subsequentResponse->assertStatus(401);
    }
}
