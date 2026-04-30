<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use App\Models\Work;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->with(['permissions.author', 'permissions.work.author'])
            ->orderBy('full_name')
            ->orderBy('name')
            ->orderBy('email')
            ->get();

        $works = Work::query()
            ->with('author')
            ->where('is_legacy', false)
            ->orderBy('title')
            ->get();

        return view('pages.users', [
            'users' => $users,
            'works' => $works,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'name')],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['boolean'],
        ]);

        User::create([
            'name' => $data['username'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => $request->boolean('is_admin'),
        ]);

        return redirect()
            ->to(admin_path('users'))
            ->with('status', 'Utilisateur créé.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['boolean'],
        ]);

        $user->name = $data['username'];
        $user->full_name = $data['full_name'];
        $user->email = $data['email'];
        $user->is_admin = $request->boolean('is_admin');

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()
            ->to(admin_path('users'))
            ->with('status', 'Utilisateur mis à jour.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->is($user)) {
            return redirect()
                ->to(admin_path('users'))
                ->with('status', 'Impossible de supprimer votre propre compte.');
        }

        $user->delete();

        return redirect()
            ->to(admin_path('users'))
            ->with('status', 'Utilisateur supprimé.');
    }

    public function grantVersionEditorPermission(Request $request, User $user)
    {
        if ($user->is_admin) {
            return redirect()
                ->to(admin_path('users'))
                ->with('status', 'Les administrateurs disposent déjà d’un accès complet.');
        }

        $data = $request->validate([
            'work_id' => ['required', 'integer', 'exists:works,id'],
        ]);

        $work = Work::query()
            ->where('is_legacy', false)
            ->findOrFail((int) $data['work_id']);

        Permission::firstOrCreate([
            'user_id' => $user->id,
            'author_id' => null,
            'work_id' => $work->id,
            'permission_type' => User::PERMISSION_VERSION_EDITOR,
        ]);

        return redirect()
            ->to(admin_path('users'))
            ->with('status', 'Accès éditeur de versions accordé.');
    }

    public function revokeVersionEditorPermission(User $user, Permission $permission)
    {
        if ((int) $permission->user_id !== (int) $user->id ||
            $permission->permission_type !== User::PERMISSION_VERSION_EDITOR) {
            abort(404);
        }

        $permission->delete();

        return redirect()
            ->to(admin_path('users'))
            ->with('status', 'Accès éditeur de versions retiré.');
    }

    public function grantWorkEditPermission(Request $request, User $user)
    {
        if ($user->is_admin) {
            return redirect()
                ->to(admin_path('users'))
                ->with('status', 'Les administrateurs disposent déjà d’un accès complet.');
        }

        $data = $request->validate([
            'work_id' => ['required', 'integer', 'exists:works,id'],
        ]);

        $work = Work::query()
            ->where('is_legacy', false)
            ->findOrFail((int) $data['work_id']);

        Permission::firstOrCreate([
            'user_id' => $user->id,
            'author_id' => null,
            'work_id' => $work->id,
            'permission_type' => User::PERMISSION_EDIT,
        ]);

        return redirect()
            ->to(admin_path('users'))
            ->with('status', 'Droit édition complète accordé.');
    }

    public function revokeWorkEditPermission(User $user, Permission $permission)
    {
        if ((int) $permission->user_id !== (int) $user->id ||
            $permission->permission_type !== User::PERMISSION_EDIT ||
            $permission->work_id === null) {
            abort(404);
        }

        $permission->delete();

        return redirect()
            ->to(admin_path('users'))
            ->with('status', 'Droit édition complète retiré.');
    }
}
