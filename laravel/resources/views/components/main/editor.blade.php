@extends('layouts.app')

@section('content')
    <div class="editor">
        <h1>Edition de la version <b>{{ $version->name }}</b> pour la comparaison <b>#{{ $comparison->id }}</b></h1>

        @if (!$canEdit)
            <div
                class="alert alert-warning mt-3"
                role="alert"
            >
                <strong>⚠️ Attention :</strong> Les modifications ne sont pas autorisées.
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
        <div class="border border-top-0 p-3 row m-0 overflow-auto" style="height: calc(100vh - 200px);">
            <div class="col-md-6 col-12 order-last order-md-first h-100 d-flex flex-column">
                    <div>
                        <button
                            id="save-xml"
                            class="btn btn-success mb-2"
                            {{ !$canEdit ? 'disabled' : '' }}
                        >Enregistrer</button>
                        <button
                            id="toggle-readonly"
                            class="btn btn-warning mb-2"
                            {{ !$canEdit ? 'disabled' : '' }}
                        >Activer le mode édition</button>
                        <button
                            id="toggle-tags"
                            class="btn btn-secondary mb-2"
                        >Afficher les balises</button>
                    </div>
                <div
                    id="editor-container"
                    style="border:1px solid #ccc;"
                    class="overflow-scroll"
                ></div>
            </div>
            <div class="col-md-2 col-12">
                <div id="buttons-container" class="row row-cols-4 g-1 align-items-start">
                    @foreach ($imagesData ?? [] as $facsimile)
                        <div class="col button-item" style="display: none;">
                            <button
                                class="h-100 w-100 btn btn-primary btn-sm position-relative"
                                data-tag="{{ $loop->iteration }}"
                                data-img-src="{{ Storage::url($facsimile['big']) }}"
                                data-enable-when-readonly
                                {{ !$canEdit ? 'disabled' : '' }}
                            >
                                <span>{{ $loop->iteration }}</span>
                                <span
                                    class="position-absolute top-0 start-75 translate-middle badge rounded-pill bg-danger"
                                    data-tag-count="{{ $loop->iteration }}"
                                    style="display: none; font-size: 0.65rem;"
                                ></span>
                            </button>
                        </div>
                    @endforeach
                </div>
                <nav aria-label="Page navigation" class="mt-2 overflow-auto">
                    <ul id="pagination" class="pagination pagination-sm mb-0 flex-wrap justify-content-center"></ul>
                </nav>
            </div>
            <div class="col-md-4 col-12 h-100 d-flex flex-column align-items-center">
                <p id="image-name" class="text-muted fw-bold mb-2" style="display: none;"></p>
                <div id="loading-spinner" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
                <img 
                    id="facsimile-preview" 
                    src="" 
                    alt="Aperçu du facsimilé" 
                    style="display: none; min-height: 0;"
                    class="w-100 object-fit-contain"
                >
                <p id="no-preview" class="text-muted"></p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
      window.editorParams = {
        xmlContent: @json($xmlContent),
        comparisonId: {{ $comparison->id }},
        fileType: '{{ $isSource ? 'source' : 'target' }}',
        canEdit: {{ $canEdit ? 'true' : 'false' }},
      };
    </script>
    @vite('resources/js/editor.js')
@endpush
