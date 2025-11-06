@extends('layouts.app')

@section('content')
    <a href="{{ admin_path(sprintf('select/%s/%s', $version->work->author->folder, $version->work->folder)) }}" class="btn btn-outline-secondary btn-sm mb-3 d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-left-circle"></i>
        <span>Retour à l’accueil</span>
    </a>

    <h1>Version <b>{{ $version->name }}</b> | Comparaison <b>#{{ $comparison->id }}</b> | Oeuvre <b>{{ $version->work->title }}</b> | Auteur <b>{{ $version->work->author->name }}</b></h1>

    @if (!$canEdit)
        <div
            class="alert alert-warning mt-3"
            role="alert"
        >
            <strong><i class="bi bi-exclamation-triangle-fill"></i> Attention :</strong> Les modifications ne sont pas autorisées.
            @if ($isPublished)
                Cette comparaison est actuellement publiée.
            @elseif(!$imagesData)
                Les facsimilés pour cette version ne sont pas publiés.
            @endif
        </div>
    @endif

    <ul class="nav nav-tabs mt-3">
        <li class="nav-item">
            <a
                href="{{ $sourceTabUrl }}"
                class="nav-link {{ $isSource ? 'active' : '' }}"
            >
                Source
            </a>
        </li>
        <li class="nav-item">
            <a
                href="{{ $targetTabUrl }}"
                class="nav-link {{ !$isSource ? 'active' : '' }}"
            >
                Cible
            </a>
        </li>
    </ul>
    <x-editor
        :xml-content="$xmlContent"
        :images-data="$imagesData"
        :url-file-save="$urlFileSave"
        :can-edit="$canEdit"
    />
@endsection
