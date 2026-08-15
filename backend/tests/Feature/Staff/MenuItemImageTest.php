<?php

namespace Tests\Feature\Staff;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuItemImageTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $staff;

    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->restaurant = Restaurant::factory()->create();
        $this->staff = User::factory()->staff($this->restaurant)->create();
        $this->item = MenuItem::factory()->for($this->restaurant)->create();
    }

    public function test_staff_can_upload_a_picture_of_a_dish(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/menu-items/{$this->item->id}/image", [
                'image' => UploadedFile::fake()->image('lavash.jpg', 800, 600),
            ]);

        $response->assertOk();

        $path = $this->item->fresh()->image_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString("menu-items/{$this->restaurant->id}", $path);
        $this->assertNotNull($response->json('menu_item.image_url'));
    }

    public function test_replacing_a_picture_removes_the_old_file(): void
    {
        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/menu-items/{$this->item->id}/image", [
                'image' => UploadedFile::fake()->image('eski.jpg'),
            ])->assertOk();

        $old = $this->item->fresh()->image_path;

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/menu-items/{$this->item->id}/image", [
                'image' => UploadedFile::fake()->image('yangi.jpg'),
            ])->assertOk();

        $new = $this->item->fresh()->image_path;

        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }

    public function test_a_picture_can_be_removed(): void
    {
        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/menu-items/{$this->item->id}/image", [
                'image' => UploadedFile::fake()->image('lavash.jpg'),
            ])->assertOk();

        $path = $this->item->fresh()->image_path;

        $this->actingAs($this->staff, 'sanctum')
            ->deleteJson("/api/staff/menu-items/{$this->item->id}/image")
            ->assertOk()
            ->assertJsonPath('menu_item.image_url', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($this->item->fresh()->image_path);
    }

    public function test_deleting_the_dish_also_deletes_its_picture(): void
    {
        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/menu-items/{$this->item->id}/image", [
                'image' => UploadedFile::fake()->image('lavash.jpg'),
            ])->assertOk();

        $path = $this->item->fresh()->image_path;

        $this->actingAs($this->staff, 'sanctum')
            ->deleteJson("/api/staff/menu-items/{$this->item->id}")
            ->assertNoContent();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_only_real_images_of_a_sane_size_are_accepted(): void
    {
        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/menu-items/{$this->item->id}/image", [
                'image' => UploadedFile::fake()->create('menyu.pdf', 100, 'application/pdf'),
            ])->assertStatus(422)->assertJsonValidationErrors('image');

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/menu-items/{$this->item->id}/image", [
                'image' => UploadedFile::fake()->image('katta.jpg')->size(5000),
            ])->assertStatus(422)->assertJsonValidationErrors('image');

        $this->assertNull($this->item->fresh()->image_path);
    }

    public function test_another_restaurants_dish_cannot_be_given_a_picture(): void
    {
        $foreign = MenuItem::factory()->create();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/menu-items/{$foreign->id}/image", [
                'image' => UploadedFile::fake()->image('lavash.jpg'),
            ])->assertNotFound();

        $this->assertNull($foreign->fresh()->image_path);
    }

    public function test_customers_see_the_picture_and_the_category(): void
    {
        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/staff/menu-items/{$this->item->id}", ['category' => 'Ichimliklar'])
            ->assertOk()
            ->assertJsonPath('menu_item.category', 'Ichimliklar');

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/menu-items/{$this->item->id}/image", [
                'image' => UploadedFile::fake()->image('cola.jpg'),
            ])->assertOk();

        $this->getJson("/api/restaurants/{$this->restaurant->id}")
            ->assertOk()
            ->assertJsonPath('menu_items.0.category', 'Ichimliklar')
            ->assertJsonPath('menu_items.0.image_url', fn ($url) => is_string($url) && $url !== '');
    }
}
