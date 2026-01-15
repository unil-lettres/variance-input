<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        $admins = User::query()
            ->where('is_admin', true)
            ->orderBy('full_name')
            ->orderBy('name')
            ->get();

        return view('pages.users', [
            'admins' => $admins,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $data['full_name'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => true,
        ]);

        return redirect()
            ->to(admin_path('users'))
            ->with('status', 'Administrateur créé.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->name = $data['full_name'];
        $user->full_name = $data['full_name'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (! $user->is_admin) {
            $user->is_admin = true;
        }

        $user->save();

        return redirect()
            ->to(admin_path('users'))
            ->with('status', 'Utilisateur mis à jour.');
    }
}
