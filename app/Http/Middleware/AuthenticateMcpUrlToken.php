<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMcpUrlToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $user = User::query()
            ->where('access_token', $token)
            ->first();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        Auth::shouldUse('web');
        Auth::guard('web')->setUser($user);
        $request->setUserResolver(static fn (): User => $user);

        return $next($request);
    }
}
