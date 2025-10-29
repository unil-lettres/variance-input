@extends('layouts.app')

@section('content')
    <h1>Edition de la version <b>{{ $version->name }}</b> pour la comparaison <b>#{{ $comparison->id }}</b></h1>

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
                href="{{ route('comparison.editor', ['comparison' => $comparison->id, 'type' => 'source']) }}"
                class="nav-link {{ $isSource ? 'active' : '' }}"
            >
                Source
            </a>
        </li>
        <li class="nav-item">
            <a
                href="{{ route('comparison.editor', ['comparison' => $comparison->id, 'type' => 'target']) }}"
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
