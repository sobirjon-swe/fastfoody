<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
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
