<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Login for family (trusted viewers) and admin on the public site. Rate limited in the routes. */
class LoginController extends Controller
{
    public function show(): View
    {
        return view('pages.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            Log::warning('Failed login', ['email' => $credentials['email'], 'ip' => $request->ip()]);

            throw ValidationException::withMessages(['email' => __('site.login.failed')]);
        }

        $request->session()->regenerate();
        Log::info('Login', ['user' => $request->user()->id, 'ip' => $request->ip()]);

        return redirect()->intended(lroute('live'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(lroute('home'));
    }
}
