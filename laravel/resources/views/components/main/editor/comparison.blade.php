@extends('layouts.app')
@section('body-class', 'admin-editor-page')

@section('content')
    <div class="m-2 comparison-editor-shell">
        <div class="d-flex align-items-center gap-2 mb-2 comparison-editor-topbar">
            <button
                type="button"
                id="editor-exit-button"
                class="btn btn-outline-secondary btn-sm"
                data-bs-toggle="tooltip"
                title="Fermer l’éditeur"
                aria-label="Fermer l’éditeur et revenir aux comparaisons"
            >
                <i class="bi bi-x-lg"></i>
            </button>
            <h1 class="mb-0">Œuvre <b>{{ $work->title }}</b> | Auteur <b>{{ $work->author->name }}</b> | Comparaison <b>#{{ $comparison->id }}</b></h1>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-1 comparison-editor-summary">
            <span class="badge text-bg-secondary">Composants: {{ count($components) }}</span>
            @if ($canEditComparison)
                <span class="badge text-bg-success">Édition autorisée</span>
            @else
                <span class="badge text-bg-warning">Édition restreinte</span>
            @endif
            <span id="consistency-status-badge" class="badge text-bg-secondary">Cohérence: vérification…</span>
            <div class="ms-auto d-flex gap-2">
                @if ($canEditComparison)
                    <button
                        id="editor-save-all"
                        class="btn btn-success btn-sm"
                        data-bs-toggle="tooltip"
                        title="Sauvegarder tous les fichiers modifiés"
                    >
                        <i class="bi bi-floppy-fill"></i>
                        <span class="ms-1">Sauvegarder tout</span>
                    </button>
                @endif
            </div>
        </div>

        @if (!$canEditComparison)
            <div class="alert alert-warning" role="alert">
                <strong><i class="bi bi-exclamation-triangle-fill"></i> Attention :</strong> Les modifications ne sont pas autorisées.
                @if ($isPublished)
                    Cette comparaison est actuellement publiée.
                @endif
            </div>
        @endif

        <div id="consistency-panel" class="alert alert-warning d-none" role="alert">
            <div class="d-flex align-items-center">
                <strong><i class="bi bi-exclamation-triangle-fill"></i> Avertissements de cohérence</strong>
                <span class="ms-auto small text-muted" id="consistency-checked-at"></span>
            </div>
            <ul class="mb-0 mt-2" id="consistency-issues-list"></ul>
        </div>

        <ul class="nav nav-tabs mt-1 comparison-editor-tabs" id="comparison-editor-tabs" role="tablist">
            @foreach ($components as $component)
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link @if ($loop->first) active @endif"
                        id="editor-tab-{{ $component['type'] }}"
                        data-bs-toggle="tab"
                        data-bs-target="#editor-pane-{{ $component['type'] }}"
                        type="button"
                        role="tab"
                        aria-controls="editor-pane-{{ $component['type'] }}"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    >
                        {{ $component['filename'] }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content border border-top-0 rounded-bottom p-1 comparison-editor-tab-content" id="comparison-editor-tab-content">
            @foreach ($components as $component)
                <div
                    class="tab-pane fade @if ($loop->first) show active @endif"
                    id="editor-pane-{{ $component['type'] }}"
                    role="tabpanel"
                    aria-labelledby="editor-tab-{{ $component['type'] }}"
                    tabindex="0"
                >
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex gap-2 align-items-center comparison-editor-pane-header">
                            <div>
                                <strong>{{ $component['label'] }}</strong>
                                <span class="text-muted">({{ $component['filename'] }})</span>
                            </div>
                            <span
                                id="editor-status-{{ $component['type'] }}"
                                class="badge text-bg-secondary"
                            >{{ $component['exists'] ? 'Prêt' : 'Nouveau fichier' }}</span>
                            <div class="flex-grow-1"></div>
                            <button
                                id="editor-search-{{ $component['type'] }}"
                                class="btn btn-outline-primary btn-sm"
                                data-bs-toggle="tooltip"
                                title="Rechercher du texte"
                            ><i class="bi bi-search"></i></button>
                            @if ($component['canEdit'])
                                <button
                                    id="editor-save-{{ $component['type'] }}"
                                    class="btn btn-success btn-sm"
                                    data-bs-toggle="tooltip"
                                    title="Sauvegarder {{ $component['filename'] }}"
                                ><i class="bi bi-floppy-fill"></i></button>
                                @if (in_array($component['type'], ['d', 'i', 'r', 's'], true))
                                    <button
                                        id="editor-remove-transfo-{{ $component['type'] }}"
                                        class="btn btn-outline-danger btn-sm"
                                        data-bs-toggle="tooltip"
                                        title="Supprimer la transformation ciblée (ligne courante)"
                                    >
                                        <i class="bi bi-trash3"></i>
                                        <span class="ms-1">Supprimer la transformation</span>
                                    </button>
                                @endif
                            @endif
                        </div>

                        @if (!$component['exists'])
                            <div class="alert alert-info m-0 py-2" role="alert">
                                <i class="bi bi-info-circle"></i>
                                Ce fichier n'existe pas encore. Vous pouvez le créer en enregistrant ce panneau.
                            </div>
                        @endif

                        <div
                            id="editor-container-{{ $component['type'] }}"
                            style="height: calc(100vh - 308px); border:1px solid #ccc;"
                            class="overflow-auto"
                        ></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="modal fade" id="removeTransformationModal" tabindex="-1" aria-labelledby="removeTransformationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeTransformationModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Supprimer la transformation <strong id="remove-transformation-refid">—</strong>
                        dans <strong id="remove-transformation-file">—</strong>,
                        ainsi que ses références dans <code>source.xhtml</code> et <code>target.xhtml</code> ?
                    </p>
                    <div class="small text-muted mb-1">Ligne ciblée :</div>
                    <pre id="remove-transformation-line" class="mb-0 p-2 border rounded bg-light small" style="white-space: pre-wrap;"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="confirm-remove-transformation-btn">
                        Supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>
    @push('styles')
        <style>
            .comparison-editor-topbar {
                min-height: 2rem;
            }

            .comparison-editor-topbar h1 {
                font-size: 1.1rem;
                line-height: 1.2;
            }

            .comparison-editor-summary {
                min-height: 1.9rem;
            }

            .comparison-editor-tabs .nav-link {
                padding: 0.32rem 0.6rem;
                font-size: 0.84rem;
                line-height: 1.1;
            }

            .comparison-editor-tab-content {
                padding-top: 0.45rem !important;
            }

            .comparison-editor-pane-header {
                min-height: 1.9rem;
            }
        </style>
    @endpush
    @push('scripts')
        <script>
            window.editorParams = {
                components: @json($components),
                consistencyUrl: @json(admin_url('comparison/' . $comparison->id . '/editor/consistency')),
                returnUrl: @json(admin_path(sprintf('select/%s/%s#etape-3', $work->author->folder, $work->folder))),
            };
        </script>
        @vite('resources/js/editor-comparison.js')
    @endpush
@endsection
