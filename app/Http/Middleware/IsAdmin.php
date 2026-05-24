<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->roles()->whereIn('slug', ['nti_admin', 'super_admin', 'evaluator'])->exists()) {
            return response()->json(['message' => 'Unauthorized. Admin required.'], 403);
        }

        return $next($request);
    }
}
