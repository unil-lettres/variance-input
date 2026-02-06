@extends('layouts.app')

@section('title', 'Changer mon mot de passe')
@section('body-class', 'admin-main-page')

@section('content')
<div class="container" style="max-width: 720px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Changer mon mot de passe</h1>
        <a href="{{ admin_path() }}" class="btn btn-outline-secondary btn-sm">Retour</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ admin_path('account/password') }}" autocomplete="off">
                @csrf

                <div class="mb-3">
                    <label for="current_password" class="form-label">Mot de passe actuel</label>
                    <input type="password"
                           id="current_password"
                           name="current_password"
                           class="form-control"
                           autocomplete="current-password"
                           required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Nouveau mot de passe</label>
                    <input type="password"
                           id="password"
                           name="password"
                           class="form-control"
                           autocomplete="new-password"
                           required>
                    <div class="form-text">8 caractères minimum.</div>
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Confirmer le nouveau mot de passe</label>
                    <input type="password"
                           id="password_confirmation"
                           name="password_confirmation"
                           class="form-control"
                           autocomplete="new-password"
                           required>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
