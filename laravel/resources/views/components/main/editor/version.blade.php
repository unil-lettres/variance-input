@extends('layouts.app')

@section('content')
    <a href="{{ admin_path(sprintf('select/%s/%s', $version->work->author->folder, $version->work->folder)) }}" class="btn btn-outline-secondary btn-sm mb-3 d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-left-circle"></i>
        <span>Retour à l’accueil</span>
    </a>

    <h1 class="border-bottom pb-2">Version <b>{{ $version->name }}</b> | Oeuvre <b>{{ $version->work->title }}</b> | Auteur <b>{{ $version->work->author->name }}</b></h1>

    <x-editor
        :xml-content="$xmlContent"
        :images-data="$imagesData"
        :url-file-save="$urlFileSave"
        :can-edit="$canEdit"
    />
@endsection
