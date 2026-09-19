<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Abort unless the authenticated user holds one of the required roles.
     *
     * Usage: ->middleware('role:cashier,admin')
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        abort_if($user === null, 401);

        $allowed = array_map(
            fn (string $role) => UserRole::from($role),
            $roles,
        );

        if (! in_array($user->role, $allowed, true)) {
            abort(403, 'دسترسی به این بخش برای نقش شما مجاز نیست.');
        }

        return $next($request);
    }
}
