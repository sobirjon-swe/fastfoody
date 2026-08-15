<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reset_link_is_sent_and_points_at_the_spa(): void
    {
        Notification::fake();
        config(['fastfoody.frontend_url' => 'https://fastfoody.uz']);

        $user = User::factory()->customer()->create(['email' => 'mijoz@fastfoody.uz']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'mijoz@fastfoody.uz'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            $this->assertStringStartsWith('https://fastfoody.uz/parolni-tiklash?token=', $url);
            $this->assertStringContainsString('email=mijoz%40fastfoody.uz', $url);

            return true;
        });
    }

    public function test_an_unknown_email_gets_the_same_answer(): void
    {
        Notification::fake();

        // Javob bir xil: kimda hisob borligini bilib boʻlmaydi.
        $this->postJson('/api/auth/forgot-password', ['email' => 'yoq@fastfoody.uz'])
            ->assertOk()
            ->assertJsonPath('message', 'Agar bunday hisob mavjud boʻlsa, tiklash havolasi emailga yuborildi.');

        Notification::assertNothingSent();
    }

    public function test_the_password_can_be_reset_with_the_emailed_token(): void
    {
        Notification::fake();

        $user = User::factory()->customer()->create([
            'email' => 'mijoz@fastfoody.uz',
            'password' => Hash::make('eski-parol'),
        ]);
        $old = $user->createToken('telefon')->plainTextToken;

        $this->postJson('/api/auth/forgot-password', ['email' => 'mijoz@fastfoody.uz'])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'mijoz@fastfoody.uz',
            'password' => 'yangi-parol-123',
            'password_confirmation' => 'yangi-parol-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('yangi-parol-123', $user->fresh()->password));
        // Eski seanslar yopiladi.
        $this->assertSame(0, $user->tokens()->count());

        $this->withToken($old)->getJson('/api/auth/me')->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => 'mijoz@fastfoody.uz',
            'password' => 'yangi-parol-123',
        ])->assertOk();
    }

    public function test_a_wrong_or_used_token_is_refused(): void
    {
        Notification::fake();

        User::factory()->customer()->create([
            'email' => 'mijoz@fastfoody.uz',
            'password' => Hash::make('eski-parol'),
        ]);

        $this->postJson('/api/auth/reset-password', [
            'token' => 'soxta-token',
            'email' => 'mijoz@fastfoody.uz',
            'password' => 'yangi-parol-123',
            'password_confirmation' => 'yangi-parol-123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertTrue(Hash::check('eski-parol', User::firstWhere('email', 'mijoz@fastfoody.uz')->password));
    }
}
