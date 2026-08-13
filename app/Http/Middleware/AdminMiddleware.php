<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // if (!Auth::guard('admin')->check()) {
        //    return redirect()->route('admin.login');
        // }

        // return $next($request);

        // 1. grab the currently authenticated token‐user from the sanctum/admin guard:
        $admin = Auth::guard('admin')->user();

        // 2. If there is no admin, block the request:
        if (! $admin) {
            return response()->json([
                'message' => 'Unauthorized Admin',
            ], 401);
        }

        // 3. Otherwise let it pass through:
        return $next($request);
        
    }
}
