<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Parolni unutgan foydalanuvchi uchun tiklash oqimi.
 *
 * Havola emailga yuboriladi va SPA sahifasiga olib boradi. Pochta sozlanmagan
 * boʻlsa (MAIL_MAILER=log) xat jurnalga yoziladi — oqim baribir ishlaydi.
 */
class PasswordResetController extends Controller
{
    /**
     * Javob har doim bir xil: mavjud emailni mavjud emasidan ajratib boʻlmasin.
     */
    public function sendLink(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => __('Agar bunday hisob mavjud boʻlsa, tiklash havolasi emailga yuborildi.'),
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = $password;
                $user->save();

                // Eski seanslar yopiladi: parol oʻzgardi.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => [__('Tiklash havolasi notoʻgʻri yoki muddati oʻtgan.')],
            ]);
        }

        return response()->json(['message' => __('Parol yangilandi. Endi kirishingiz mumkin.')]);
    }
}
