@extends('layouts.app')

@section('content')
    <h1 class="border-bottom pb-2">Version <b>{{ $version->name }}</b> | Oeuvre <b>{{ $version->work->title }}</b> | Auteur <b>{{ $version->work->author->name }}</b></h1>

    <x-editor
        :xml-content="$xmlContent"
        :images-data="$imagesData"
        :url-file-save="$urlFileSave"
        :can-edit="$canEdit"
    />
@endsection
