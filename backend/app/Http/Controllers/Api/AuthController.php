<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\TelegramLoginRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TelegramLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Register a new customer and issue an API token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = new User($request->safe()->only('name', 'email', 'phone', 'password'));
        $user->role = UserRole::Customer;
        $user->save();

        return $this->tokenResponse($user, $request->string('device_name')->toString(), Response::HTTP_CREATED);
    }

    /**
     * Authenticate an existing user of any role and issue an API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        // Telegram orqali ochilgan hisobda parol boʻlmasligi mumkin — bunday
        // hisobga email va parol bilan kirib boʻlmaydi.
        if (! $user || $user->password === null
            || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->isDeactivated()) {
            throw ValidationException::withMessages([
                'email' => [__('Bu hisob faolsizlantirilgan. Tizim egasiga murojaat qiling.')],
            ]);
        }

        return $this->tokenResponse($user, $request->string('device_name')->toString());
    }

    /**
     * Telegram Mini App ichidan kirish: parol soʻralmaydi, ishonch Telegram
     * imzosiga asoslanadi. Hisob birinchi kirishda oʻzi ochiladi.
     */
    public function telegram(TelegramLoginRequest $request, TelegramLogin $telegram): JsonResponse
    {
        try {
            $user = $telegram->authenticate($request->string('init_data')->toString());
        } catch (RuntimeException) {
            // Bot tokeni sozlanmagan — bu mijozning emas, serverning kamchiligi.
            return response()->json(
                ['message' => __('Telegram orqali kirish hozircha sozlanmagan.')],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return $this->tokenResponse($user, $request->string('device_name')->toString());
    }

    /**
     * Return the authenticated user together with their restaurant, if any.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => UserResource::make($user->load('restaurant')),
        ]);
    }

    /**
     * Foydalanuvchi oʻz ismi, emaili va telefonini oʻzgartiradi. Rol va oshxona
     * bu yerdan oʻzgarmaydi — ular tizim egasining ishi.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return response()->json([
            'user' => UserResource::make($user->load('restaurant')),
        ]);
    }

    /**
     * Parolni oʻzgartirish uchun joriy parol soʻraladi. Yangi parol
     * qoʻyilgandan keyin boshqa qurilmalardagi seanslar yopiladi.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->password = $request->validated('password');
        $user->save();

        $current = $user->currentAccessToken();
        $user->tokens()->whereKeyNot($current->getKey())->delete();

        return response()->json(['message' => __('Parol yangilandi.')]);
    }

    /**
     * Revoke only the token used for the current request, so other devices
     * stay signed in.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Logged out.')]);
    }

    private function tokenResponse(User $user, string $deviceName, int $status = Response::HTTP_OK): JsonResponse
    {
        $token = $user->createToken($deviceName !== '' ? $deviceName : 'api');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => UserResource::make($user->load('restaurant')),
        ], $status);
    }
}
