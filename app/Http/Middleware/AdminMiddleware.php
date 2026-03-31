<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $roleId = (int) $user->role_id;

        if ($roleId !== User::ROLE_ADMIN && $roleId !== User::ROLE_ROOT) {
            abort(403);
        }

        return $next($request);
    }
}