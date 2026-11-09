<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function test_guest_cannot_access_products(): void
    {
        $this->getJson('/api/products')
            ->assertUnauthorized();
    }

    public function test_guest_cannot_access_inventory(): void
    {
        $this->getJson('/api/inventory/summary')
            ->assertUnauthorized();
    }

    public function test_guest_cannot_access_current_user(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_user_can_login(): void
    {
        $user = $this->makeUser('admin');

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.role', 'admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_password_is_rejected(): void
    {
        $user = $this->makeUser('staff');

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertUnprocessable();
    }

    public function test_admin_can_read_products(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->getJson('/api/products')
            ->assertOk();
    }

    public function test_staff_can_read_products(): void
    {
        $staff = $this->makeUser('staff');

        $this->actingAs($staff)
            ->getJson('/api/products')
            ->assertOk();
    }

    public function test_staff_cannot_create_products(): void
    {
        $staff = $this->makeUser('staff');

        $this->actingAs($staff)
            ->postJson('/api/products', [
                'name' => 'Test Product',
                'sku' => 'TEST-001',
                'price' => 100,
                'cost_price' => 80,
                'reorder_level' => 5,
                'status' => true,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_products(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->postJson('/api/products', [
                'name' => 'Test Product',
                'sku' => 'TEST-001',
                'price' => 100,
                'cost_price' => 80,
                'reorder_level' => 5,
                'status' => true,
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.stock_quantity',
                0
            );
    }

    public function test_direct_stock_update_is_rejected(): void
    {
        $admin = $this->makeUser('admin');

        $createResponse = $this->actingAs($admin)
            ->postJson('/api/products', [
                'name' => 'Test Product',
                'sku' => 'TEST-002',
                'price' => 100,
                'cost_price' => 80,
                'reorder_level' => 5,
                'status' => true,
            ]);

        $createResponse->assertCreated();

        $productId = $createResponse->json('data.id');

        $this->actingAs($admin)
            ->patchJson("/api/products/{$productId}", [
                'stock_quantity' => 500,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'stock_quantity' => 0,
        ]);
    }

    public function test_user_can_logout(): void
    {
        $user = $this->makeUser('staff');

        $this->actingAs($user)
            ->postJson('/logout')
            ->assertOk();

        $this->assertGuest();
    }
}
