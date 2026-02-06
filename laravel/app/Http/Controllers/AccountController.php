<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function editPassword()
    {
        return view('pages.account.password');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Mot de passe actuel incorrect.'])
                ->withInput();
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        return back()->with('status', 'Mot de passe mis à jour.');
    }
}
