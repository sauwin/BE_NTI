<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! $user->roles()->where('slug', 'super_admin')->exists()) {
            return response()->json(['message' => 'Unauthorized. Super admin required.'], 403);
        }

        return $next($request);
    }
}
