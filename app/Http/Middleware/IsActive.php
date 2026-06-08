<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->status === 'blocked' || $user->status === 'pending_verification') {
            return response()->json(['message' => 'Your account is not active.'], 403);
        }

        return $next($request);
    }
}
