<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'role:super_admin'])
            ->get('/api/test/admin-only', fn () => response()->json(['ok' => true]));

        Route::middleware(['api', 'auth:sanctum', 'role:restaurant_staff,super_admin'])
            ->get('/api/test/staff-or-admin', fn () => response()->json(['ok' => true]));
    }

    public function test_it_allows_a_user_whose_role_matches(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create(), 'sanctum')
            ->getJson('/api/test/admin-only')
            ->assertOk();
    }

    public function test_it_allows_any_of_the_listed_roles(): void
    {
        $this->actingAs(User::factory()->staff()->create(), 'sanctum')
            ->getJson('/api/test/staff-or-admin')
            ->assertOk();
    }

    public function test_it_blocks_a_user_with_another_role(): void
    {
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')
            ->getJson('/api/test/admin-only')
            ->assertForbidden();

        $this->actingAs(User::factory()->staff()->create(), 'sanctum')
            ->getJson('/api/test/admin-only')
            ->assertForbidden();
    }

    public function test_it_blocks_guests(): void
    {
        $this->getJson('/api/test/admin-only')->assertUnauthorized();
    }
}
