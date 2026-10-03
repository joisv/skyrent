<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SanctumAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_sanctum_personal_access_token(): void
    {
        $user = User::factory()->create([
            'name' => 'Admin SKYRental',
            'email' => 'admin@skyrental.id',
        ]);

        $tokenResult = $user->createToken('test-mobile-token');
        $plainToken = $tokenResult->plainTextToken;

        $this->assertNotEmpty($plainToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'test-mobile-token',
        ]);
    }

    public function test_authenticated_user_can_access_protected_sanctum_endpoint(): void
    {
        // Define a temporary protected route to verify Sanctum middleware
        Route::middleware('auth:sanctum')->get('/api/v1/auth/user-profile-test', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => auth()->user()->id,
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ]);
        });

        $user = User::factory()->create([
            'name' => 'Kasir SKYRental',
            'email' => 'kasir@skyrental.id',
        ]);

        $token = $user->createToken('kasir-token')->plainTextToken;

        // 1. Unauthenticated request should return 401
        $responseUnauth = $this->getJson('/api/v1/auth/user-profile-test');
        $responseUnauth->assertStatus(401);

        // 2. Authenticated request with Bearer token should succeed
        $responseAuth = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/user-profile-test');

        $responseAuth->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => 'Kasir SKYRental',
                    'email' => 'kasir@skyrental.id',
                ],
            ]);
    }

    public function test_sanctum_token_can_be_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('revokable-token')->plainTextToken;

        $this->assertCount(1, $user->tokens);

        // Revoke tokens
        $user->tokens()->delete();

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
