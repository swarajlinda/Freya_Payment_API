<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('razorpay.admin_api_key');
        $given    = (string) $request->header('X-Admin-Key');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
