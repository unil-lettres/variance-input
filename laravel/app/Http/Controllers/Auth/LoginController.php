<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        $throttleKey = $this->throttleKey($request);
        $limiter = app(RateLimiter::class);

        if ($limiter->tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Trop de tentatives de connexion. Réessayez dans '
                    . $limiter->availableIn($throttleKey)
                    . ' secondes.',
            ])->status(429);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $limiter->clear($throttleKey);
            $request->session()->regenerate();

            $intended = $request->session()->pull('url.intended');

            if ($intended) {
                $path = parse_url($intended, PHP_URL_PATH) ?? '/';
                $query = parse_url($intended, PHP_URL_QUERY);
                $clean = ltrim($path, '/');
                $prefix = ltrim(admin_base_prefix(), '/');

                if ($prefix !== '' && str_starts_with($clean, $prefix)) {
                    $clean = ltrim(substr($clean, strlen($prefix)), '/');
                }

                if ($clean !== '' && !str_starts_with($clean, 'login')) {
                    $target = admin_url($clean);
                    if ($query) {
                        $target .= '?' . $query;
                    }

                    return redirect()->to($target);
                }
            }

            return redirect()->to(admin_url());
        }

        $limiter->hit($throttleKey, 60);

        return redirect()->back()->withErrors(['msg' => 'Email ou mot de passe invalide, essayez à nouveau.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(rtrim(config('app.url'), '/').'/');
    }

    private function throttleKey(Request $request): string
    {
        return Str::lower((string) $request->input('email')) . '|' . $request->ip();
    }
}
