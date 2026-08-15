<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to one or more roles, e.g. middleware('role:super_admin').
 * Must run after an authentication middleware.
 */
class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $allowed = array_map(
            fn (string $role) => UserRole::from($role),
            $roles,
        );

        if (! $user || ! $user->hasRole(...$allowed)) {
            abort(Response::HTTP_FORBIDDEN, 'This action is not allowed for your role.');
        }

        return $next($request);
    }
}
