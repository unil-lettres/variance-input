@extends('layouts.app')

@php
    $work = $source['version']->work
@endphp

@section('content')
    <div class="m-3">
        <a href="{{ admin_path(sprintf('select/%s/%s', $work->author->folder, $work->folder)) }}" class="btn btn-outline-secondary btn-sm mb-3 d-inline-flex align-items-center gap-2">
            <i class="bi bi-arrow-left-circle"></i>
            <span>Retour à l’accueil</span>
        </a>

        <h1>Oeuvre <b>{{ $work->title }}</b> | Auteur <b>{{ $work->author->name }}</b> | Comparaison <b>#{{ $comparison->id }}</b></h1>

        <div class="row g-2 mt-2" style="height: calc(100vh - 200px);">
            @foreach ([$source, $target] as [
                'version' => $version,
                'isPublished' => $isPublished,
                'canEdit' => $canEdit,
                'hasImages' => $hasImages,
                'urlFileSave' => $urlFileSave,
                'xmlContent' => $xmlContent,
            ])
                <div class="col-12 col-md-6 h-100">
                    <div class="d-flex flex-column h-100 border p-2 rounded gap-2">
                        <div class="d-flex gap-2 align-items-center">
                            <div>Version <b>{{ $version->name }}</b></div>
                            <div class="flex-grow-1"></div>
                            <button
                                id="editor-search-{{ $version->id }}"
                                class="btn btn-outline-primary"
                                data-bs-toggle="tooltip"
                                title="Rechercher du texte"
                            ><i class="bi bi-search"></i></button>
                            @if ($canEdit)
                                <button
                                    id="editor-save-{{ $version->id }}"
                                    class="btn btn-success"
                                    data-bs-toggle="tooltip"
                                    title="Sauvegarder les modifications"
                                ><i class="bi bi-floppy-fill"></i></button>
                            @endif
                        </div>
                        @if (!$canEdit)
                            <div
                                class="alert alert-warning m-0"
                                role="alert"
                            >
                                <strong><i class="bi bi-exclamation-triangle-fill"></i> Attention :</strong> Les modifications ne sont pas autorisées.
                                @if ($isPublished)
                                    Cette comparaison est actuellement publiée.
                                @elseif(!$hasImages)
                                    Les facsimilés pour cette version ne sont pas publiés.
                                @endif
                            </div>
                        @endif
                        <div
                            id="editor-container-{{ $version->id }}"
                            style="border:1px solid #ccc;"
                            class="overflow-scroll"
                        ></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @push('scripts')
        <script>
            window.editorParams = {
                source: {
                    id: @json($source['version']->id),
                    urlFileSave: @json($source['urlFileSave']),
                    xmlContent: @json($source['xmlContent']),
                },
                target: {
                    id: @json($target['version']->id),
                    urlFileSave: @json($target['urlFileSave']),
                    xmlContent: @json($target['xmlContent']),
                },
            };
        </script>
        @vite('resources/js/editor-comparison.js')
    @endpush
@endsection
