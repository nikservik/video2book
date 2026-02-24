<?php

namespace App\Http\Web\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;

class AcceptInviteController extends Controller
{
    public function __invoke(string $token): RedirectResponse|Response
    {
        $user = User::query()
            ->where('access_token', $token)
            ->first();

        if ($user === null) {
            return response()->view('auth.access-closed', status: 403);
        }

        $cookie = Cookie::forever(
            name: (string) config('simple_auth.cookie_name', 'video2book_access_token'),
            value: $token,
            path: '/',
            domain: config('session.domain'),
            secure: config('session.secure'),
            httpOnly: true,
            raw: false,
            sameSite: config('session.same_site')
        );

        return redirect()
            ->route('home')
            ->withCookie($cookie);
    }
}
