<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
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

        return redirect()->back()->withErrors(['msg' => 'Email ou mot de passe invalide, essayez à nouveau.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(rtrim(config('app.url'), '/').'/');
    }
}
