<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UnsubscribedMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::user()->status == 'Unsubscribe'){
            return response()->json([
                'code' => 401,
                'message' => 'Please proceed with payments and access our services seamlessly.'
            ], 401);
        } else {
            return $next($request);
        }
    }
}
