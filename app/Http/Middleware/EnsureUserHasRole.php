<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, Response::HTTP_FORBIDDEN);
        abort_if($roles === [], Response::HTTP_FORBIDDEN, 'No roles were provided to the role middleware.');
        abort_unless(
            $user->hasRole(...array_map(
                static fn (string $role): UserRole => UserRole::from($role),
                $roles,
            )),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
