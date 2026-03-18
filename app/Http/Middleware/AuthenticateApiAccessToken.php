<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return $this->unauthorized();
        }

        $user = User::query()
            ->where('access_token', $token)
            ->first();

        if (! $user instanceof User) {
            return $this->unauthorized();
        }

        Auth::shouldUse('web');
        Auth::guard('web')->setUser($user);
        $request->setUserResolver(static fn (): User => $user);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['message' => 'Unauthorized.'], 401);
    }
}
