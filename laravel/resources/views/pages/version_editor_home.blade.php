@extends('layouts.app')

@section('title', 'Éditeur de versions')
@section('body-class', 'admin-main-page')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Éditeur de versions</h1>
        <form method="POST" action="{{ admin_path('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Déconnexion</button>
        </form>
    </div>

    @if($works->isEmpty())
        <div class="alert alert-secondary small">
            Aucune œuvre ne vous est assignée.
        </div>
    @else
        <div class="list-group">
            @foreach($works as $work)
                <div class="list-group-item">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">{{ $work->title }}</div>
                            <div class="text-muted small">{{ $work->author?->name }}</div>
                        </div>
                    </div>
                    @if($work->versions->isEmpty())
                        <div class="text-muted small mt-2">Aucune version disponible.</div>
                    @else
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            @foreach($work->versions->sortBy('name') as $version)
                                <a class="btn btn-outline-primary btn-sm" href="{{ admin_path('version/' . $version->id . '/editor') }}">
                                    {{ $version->name }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
