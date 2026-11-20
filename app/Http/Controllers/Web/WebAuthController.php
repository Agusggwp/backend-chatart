<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WebAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('web.chat');
        }

        $users = User::orderBy('id')->take(6)->get();

        return view('auth.login', compact('users'));
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $field = filter_var($credentials['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$field => $credentials['email'], 'password' => $credentials['password']], (bool) $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();
            $user->presence()->updateOrCreate([], ['is_online' => true, 'last_seen_at' => now()]);

            $token = $user->createToken('web_session')->plainTextToken;
            session(['sanctum_token' => $token]);

            return redirect()->intended(route('web.chat'));
        }

        return back()->withErrors([
            'email' => 'Email/Username atau kata sandi tidak cocok.',
        ])->onlyInput('email');
    }

    public function quickLogin(Request $request, User $user): RedirectResponse
    {
        if (app()->environment('production')) {
            abort(403, 'Fitur Quick Login dinonaktifkan pada lingkungan produksi untuk alasan keamanan.');
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $user->presence()->updateOrCreate([], ['is_online' => true, 'last_seen_at' => now()]);

        $token = $user->createToken('web_session')->plainTextToken;
        session(['sanctum_token' => $token]);

        return redirect()->route('web.chat');
    }

    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            $token = session('sanctum_token');
            if ($token && str_contains($token, '|')) {
                [$tokenId] = explode('|', $token, 2);
                $user->tokens()->where('id', $tokenId)->delete();
            }

            if (! $user->tokens()->exists()) {
                $user->presence()->updateOrCreate([], ['is_online' => false, 'last_seen_at' => now()]);
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
