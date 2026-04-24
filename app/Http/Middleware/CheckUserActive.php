<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $user = $request->user();

            if (! $user->is_active) {
                auth()->logout();

                return response()->json([
                    'message' => 'Your account has been deactivated. Please contact administrator.',
                ], 403);
            }
        }

        return $next($request);
    }
}
