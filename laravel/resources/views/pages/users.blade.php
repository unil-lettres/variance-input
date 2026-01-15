@extends('layouts.app')

@section('title', 'Utilisateurs')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Gestion des utilisateurs</h1>
        <a href="{{ admin_path() }}" class="btn btn-outline-secondary btn-sm">Retour</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger small">
            <div class="fw-semibold mb-1">Corrigez les erreurs suivantes :</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header fw-semibold">Créer un administrateur</div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Les comptes créés via cet écran disposent des droits d’administration.
            </p>
            <form method="POST" action="{{ admin_path('users') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nom complet</label>
                        <input type="text" name="full_name" class="form-control" value="{{ old('full_name') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirmation</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">Créer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">Administrateurs</div>
        <div class="card-body">
            @if($admins->isEmpty())
                <div class="text-muted">Aucun administrateur enregistré.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Créé le</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($admins as $admin)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $admin->display_name }}</div>
                                        @if($admin->full_name && $admin->full_name !== $admin->name)
                                            <div class="text-muted small">{{ $admin->name }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $admin->email }}</td>
                                    <td class="text-muted small">{{ optional($admin->created_at)->format('d/m/Y') }}</td>
                                    <td class="text-end">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editUserModal"
                                            data-action="{{ admin_path('users/' . $admin->id) }}"
                                            data-full-name="{{ $admin->full_name ?? $admin->name }}"
                                            data-email="{{ $admin->email }}"
                                        >
                                            Modifier
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="edit-user-form">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Modifier un administrateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom complet</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="password" class="form-control">
                        <div class="form-text">Laisser vide pour conserver le mot de passe actuel.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmation</label>
                        <input type="password" name="password_confirmation" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('editUserModal');
    const form = document.getElementById('edit-user-form');
    if (!modal || !form) return;

    modal.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        if (!button) return;

        const action = button.getAttribute('data-action');
        const fullName = button.getAttribute('data-full-name') || '';
        const email = button.getAttribute('data-email') || '';

        form.action = action;
        form.querySelector('input[name="full_name"]').value = fullName;
        form.querySelector('input[name="email"]').value = email;
        form.querySelector('input[name="password"]').value = '';
        form.querySelector('input[name="password_confirmation"]').value = '';
    });
});
</script>
@endpush
@endsection
