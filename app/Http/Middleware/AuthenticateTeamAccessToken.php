<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTeamAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('invites.accept')) {
            return $next($request);
        }

        $token = $request->cookie((string) config('simple_auth.cookie_name', 'video2book_access_token'));

        if (! is_string($token) || $token === '') {
            return $this->deny();
        }

        $user = User::query()
            ->where('access_token', $token)
            ->first();

        if ($user === null) {
            return $this->deny();
        }

        Auth::guard('web')->setUser($user);

        return $next($request);
    }

    private function deny(): Response
    {
        return response()->view('auth.access-closed', status: 403);
    }
}
