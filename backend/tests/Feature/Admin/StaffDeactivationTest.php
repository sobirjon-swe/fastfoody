<?php

namespace Tests\Feature\Admin;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->admin = User::factory()->superAdmin()->create();
        $this->staff = User::factory()->staff($this->restaurant)->create(['email' => 'xodim@fastfoody.uz']);
    }

    public function test_a_deactivated_staff_member_is_locked_out_immediately(): void
    {
        $token = $this->staff->createToken('web')->plainTextToken;

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/restaurants/{$this->restaurant->id}/staff/{$this->staff->id}")
            ->assertOk()
            ->assertJsonPath('user.is_deactivated', true);

        $this->assertNotNull($this->staff->fresh()->deactivated_at);
        // Ochiq seanslar ham darhol yopiladi.
        $this->assertSame(0, $this->staff->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/staff/orders')->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => 'xodim@fastfoody.uz',
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_a_deactivated_staff_member_can_be_restored(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/restaurants/{$this->restaurant->id}/staff/{$this->staff->id}")
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/restaurants/{$this->restaurant->id}/staff/{$this->staff->id}/restore")
            ->assertOk()
            ->assertJsonPath('user.is_deactivated', false);

        $this->postJson('/api/auth/login', [
            'email' => 'xodim@fastfoody.uz',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_the_account_itself_is_kept_so_history_survives(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/restaurants/{$this->restaurant->id}/staff/{$this->staff->id}")
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $this->staff->id, 'email' => 'xodim@fastfoody.uz']);
    }

    public function test_staff_of_another_restaurant_cannot_be_touched(): void
    {
        $foreign = User::factory()->staff()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/restaurants/{$this->restaurant->id}/staff/{$foreign->id}")
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->deactivated_at);
    }

    public function test_a_customer_account_is_not_a_staff_member(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/restaurants/{$this->restaurant->id}/staff/{$customer->id}")
            ->assertNotFound();
    }

    public function test_only_the_super_admin_may_deactivate_staff(): void
    {
        foreach ([$this->staff, User::factory()->customer()->create()] as $user) {
            $this->actingAs($user, 'sanctum')
                ->deleteJson("/api/admin/restaurants/{$this->restaurant->id}/staff/{$this->staff->id}")
                ->assertForbidden();
        }

        $this->assertNull($this->staff->fresh()->deactivated_at);
    }
}
