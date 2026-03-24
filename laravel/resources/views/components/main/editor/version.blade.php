@extends('layouts.app')
@section('body-class', 'admin-editor-page')

@section('content')
    <div class="border border-top-0 p-2 editor d-flex flex-column">
        <div class="d-flex align-items-center gap-2 mb-2 comparison-editor-topbar">
            <a
                href="{{ $returnTo ?? admin_path(sprintf('select/%s/%s#etape-2', $version->work->author->folder, $version->work->folder)) }}"
                class="btn btn-outline-secondary btn-sm"
                data-bs-toggle="tooltip"
                title="Quitter l’éditeur"
                aria-label="Fermer l’éditeur et revenir aux versions"
            ><i class="bi bi-x-circle"></i></a>
            <h1 class="mb-0">Œuvre <b>{{ $version->work->title }}</b> | Auteur <b>{{ $version->work->author->name }}</b> | Version <b>{{ $version->name }}</b></h1>
        </div>
        <div class="editor-toolbar d-flex flex-wrap align-items-start gap-2">
                <div class="editor-toolbar-group editor-toolbar-status">
                    <span
                    id="file-status"
                    class="btn btn-success btn-sm"
                    style="cursor: default;"
                    data-bs-toggle="tooltip"
                    title="Le fichier a été sauvegardé et est à jour"
                    ><i class="bi bi-check-circle-fill"></i></span>
                    <button
                    id="save-xml"
                    class="btn btn-success btn-sm d-none"
                    ><i class="bi bi-floppy-fill"></i></button>
                </div>

                <div class="editor-toolbar-group">
                    <div class="editor-toolbar-label">Edit.</div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Editor edit mode button">
                    <button
                        id="toggle-readonly"
                        class="btn btn-outline-primary"
                        data-bs-toggle="tooltip"
                        title="Activer / Désactiver le mode édition"
                    ><i class="bi bi-pencil-square"></i></button>
                    </div>
                </div>

                <div class="editor-toolbar-group">
                    <div class="editor-toolbar-label">Affich.</div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Editor display buttons">
                    <button
                        id="toggle-tags"
                        data-bs-toggle="tooltip"
                        class="btn btn-outline-primary"
                        title="Afficher / Masquer les balises"
                    ><i class="bi bi-code-square"></i></button>
                    <button
                        id="toggle-line-numbers"
                        data-bs-toggle="tooltip"
                        class="btn btn-outline-primary"
                        title="Afficher / Masquer les numéros de ligne"
                    ><i class="bi bi-list-ol"></i></button>
                    </div>
                </div>

                <div class="editor-toolbar-group">
                    <div class="editor-toolbar-label">Import</div>
                    <input
                        type="file"
                        id="upload-lignes-input"
                        accept=".txt,text/plain"
                        class="d-none"
                    >
                    <input
                        type="file"
                        id="upload-facsimiles-input"
                        class="d-none"
                        webkitdirectory
                        directory
                        multiple
                        accept="image/*,.tif,.tiff"
                    >
                    <div class="btn-group btn-group-sm" role="group" aria-label="Import tools">
                    <button
                        id="upload-lignes-btn"
                        type="button"
                        class="btn btn-outline-primary"
                        data-bs-toggle="tooltip"
                        title="Importer et appliquer un fichier _lignes"
                    ><span id="upload-lignes-spinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span><i class="bi bi-upload"></i> <code>_lignes</code></button>
                    <button
                        id="upload-facsimiles-btn"
                        type="button"
                        class="btn btn-outline-primary"
                        data-bs-toggle="tooltip"
                        title="Importer un dossier de fac-similés"
                    ><span id="upload-facsimiles-spinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span><i class="bi bi-images"></i> Fac-similés</button>
                    </div>
                </div>

                <div class="editor-toolbar-group">
                    <div class="editor-toolbar-label">Balises</div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Editor italic buttons">
                    <button
                        id="italic-open-btn"
                        data-bs-toggle="tooltip"
                        class="btn btn-outline-primary"
                        title="Insérer balise italique ouvrante"
                    ><i class="bi bi-code"></i><i class="bi bi-type-italic"></i></button>
                    <button
                        id="italic-close-btn"
                        class="btn btn-outline-primary"
                        data-bs-toggle="tooltip"
                        title="Insérer balise italique fermante"
                    ><i class="bi bi-code-slash"></i><i class="bi bi-type-italic"></i></button>
                    <button
                        id="italic-report-btn"
                        class="btn btn-outline-primary"
                        data-bs-toggle="modal" 
                        data-bs-target="#italicErrorsModal"
                        title="Rapport d'erreurs des tags italiques"
                    ><i class="bi bi-exclamation-triangle-fill"></i></button>
                    </div>
                </div>

                <div class="editor-toolbar-group editor-toolbar-group--button-only">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Search tools">
                        <button
                            id="search-btn"
                            class="btn btn-outline-primary"
                            data-bs-toggle="tooltip"
                            title="Rechercher du texte"
                        ><i class="bi bi-search"></i></button>
                    </div>
                </div>

                <div class="editor-toolbar-group editor-toolbar-group--button-only">
                    <div class="form-check form-switch editor-toolbar-switch m-0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="toggle-tooltips"
                        >
                        <label class="form-check-label small" for="toggle-tooltips">Info-bulles</label>
                    </div>
                </div>
        </div>

        <div class="row g-2 mt-0 editor-workspace align-items-start">
        <div class="col-md-6 col-12 order-last order-md-first d-flex flex-column">
            <div
                id="editor-container"
                style="border:1px solid #ccc;"
                class="editor-text-frame"
            ></div>
        </div>
        <div class="col-md-1 col-12 d-flex flex-column justify-content-between gap-2">
            <div class="editor-page-tools d-flex flex-wrap align-items-center gap-1 px-1" aria-label="Page tools buttons">
                <button
                    id="select-prev-facsimile"
                    class="btn btn-outline-secondary editor-page-tool-btn"
                    data-bs-toggle="tooltip"
                    title="Fac-similé précédent"
                    disabled
                ><i class="bi bi-chevron-up"></i></button>
                <button
                    id="select-next-facsimile"
                    class="btn btn-outline-secondary editor-page-tool-btn"
                    data-bs-toggle="tooltip"
                    title="Fac-similé suivant"
                    disabled
                ><i class="bi bi-chevron-down"></i></button>
                <button
                    id="generate-page-numbers"
                    class="btn btn-outline-primary editor-page-tool-btn"
                    data-bs-toggle="modal" data-bs-target="#generatePageNumbersModal"
                    title="Générer les numéros de page"
                ><i class="bi bi-file-earmark mr-1"></i><i class="bi bi-123"></i></button>
                @if($urlToggleIgnored)
                <button
                    id="toggle-ignored-page"
                    class="btn btn-outline-danger editor-page-tool-btn"
                    data-bs-toggle="tooltip"
                    title="Ignorer / Restaurer la page sélectionnée"
                    disabled
                ><i class="bi bi-eye-slash"></i></button>
                @endif
                <button
                    id="remove-page-marker"
                    class="btn btn-outline-danger editor-page-tool-btn"
                    data-bs-toggle="tooltip"
                    title="Retirer le marqueur de page"
                    disabled
                ><i class="bi bi-file-earmark-x"></i></button>
                <button
                    id="clear-all-page-markers"
                    class="btn btn-outline-danger editor-page-tool-btn"
                    data-bs-toggle="tooltip"
                    title="Retirer tous les marqueurs de page"
                    disabled
                ><i class="bi bi-file-earmark-minus"></i><i class="bi bi-x-lg"></i></button>
                <i
                    id="page-number-warning"
                    class="bi bi-exclamation-triangle-fill text-warning ms-2"
                    style="font-size: 1.1rem; display: none;"
                    data-bs-toggle="tooltip"
                    title="Certains numéros de page ne sont pas encore insérés dans le document. Si vous quittez cette page, ces numéros seront perdus."
                ></i>
            </div>
            <div class="editor-marker-gallery">
                <div id="buttons-container" class="row row-cols-3 px-1 py-2 g-1 align-items-start w-100">
                    @foreach ($imagesData ?? [] as $facsimile)
                        <div class="col button-item {{ ($facsimile['ignored'] ?? false) ? 'page-ignored' : '' }}" style="display: none;" data-filename="{{ $facsimile['filename'] }}">
                            <button
                                class="h-100 w-100 btn btn-secondary btn-sm position-relative"
                                data-tag="{{ $facsimile['filename'] }}"
                                data-img-src="{{ $facsimile['big'] }}"
                                data-ignored="{{ ($facsimile['ignored'] ?? false) ? 'true' : 'false' }}"
                            >
                                <span></span>
                                <span
                                    class="position-absolute top-0 start-75 translate-middle badge rounded-pill bg-danger"
                                    data-tag-count="{{ $facsimile['filename'] }}"
                                    style="display: none; font-size: 0.65rem;"
                                ></span>
                            </button>
                        </div>
                    @endforeach
                </div>
                <div id="facsimiles-empty-state" class="small text-muted px-2 py-3 text-center" style="display: none;">
                    Aucun fac-similé importé pour cette version.
                </div>
            </div>
            <nav aria-label="Page navigation" class="mt-2">
                <ul id="pagination" class="pagination pagination-sm mb-0 flex-wrap justify-content-center"></ul>
            </nav>
        </div>
        <div class="col-md-5 col-12 d-flex flex-column align-items-center editor-preview-column">
            <a id="image-name" class="d-none" href="#" target="_blank" aria-hidden="true" tabindex="-1"></a>
            <div id="loading-spinner" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
            </div>
            <div
                    style="display: none;"
                    class="w-100 overflow-auto position-relative editor-preview-frame"
            >
                <img 
                    id="facsimile-preview" 
                    src="" 
                    alt="Aperçu du facsimilé" 
                    class="w-100"
                >
            </div>
            <p id="no-preview" class="text-muted"></p>
        </div>
        </div>
    </div>

    <!-- Modal for page number generation -->
    <div class="modal fade" id="generatePageNumbersModal" tabindex="-1" aria-labelledby="generatePageNumbersModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="generatePageNumbersModalLabel">Générer les numéros de page</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" role="alert">
                        Cette fonction génère des numéros pour les pages qui ne sont pas encore insérées dans le XML (en bleu) en fonction des paramètres choisis. Elle ne modifie pas le fichier XML.
                    </div>
                    <div class="mb-3">
                        <label for="leadingZeros" class="form-label">Nombre de zéros de remplissage (ex: 3 pour 001)</label>
                        <input type="number" class="form-control" id="leadingZeros" min="0" max="4" value="0">
                        <div class="form-text">Entre 0 et 4</div>
                    </div>
                    <div class="mb-3">
                        <label for="startPageNumber" class="form-label">Nombre de pages liminaires</label>
                        <input type="number" class="form-control" id="startPageNumber" min="0" value="0">
                        <div class="form-text">Nombre de pages à ignorer avant de commencer la numérotation</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="confirmGeneratePageNumbers">Générer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for italic tag errors -->
    <div class="modal fade" id="italicErrorsModal" tabindex="-1" aria-labelledby="italicErrorsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="italicErrorsModalLabel">
                        <i class="bi bi-exclamation-triangle-fill"></i> Rapport d'erreurs - Tags italiques
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="italic-errors-list"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.editorParams = {
                xmlContent: @json($xmlContent),
                urlFileSave: @json($urlFileSave),
                urlToggleIgnored: @json($urlToggleIgnored),
                versionId: @json($version->id),
                urlLignesUpload: @json(admin_url("api/versions/{$version->id}/lignes")),
                urlLignesProgress: @json(admin_url("api/versions/{$version->id}/page-markers/progress")),
                urlFacsimilesUpload: @json(admin_url('api/upload_facsimiles')),
                urlFacsimilesProgress: @json(admin_url("api/versions/{$version->id}/facsimiles/progress")),
            };
        </script>
        @vite('resources/js/editor.js')
    @endpush

    @push('styles')
        <style>
            .editor-toolbar {
                margin-bottom: 0.3rem;
                column-gap: 0.65rem;
                row-gap: 0.4rem;
            }

            .comparison-editor-topbar h1 {
                font-size: 1.1rem;
                line-height: 1.2;
            }

            .editor-toolbar-group {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                min-height: 2rem;
            }

            .editor-toolbar-status {
                align-self: flex-end;
                margin-bottom: 0.02rem;
            }

            .editor-toolbar-group--button-only {
                align-self: flex-end;
                margin-bottom: 0.02rem;
            }

            .editor-toolbar-label {
                margin-bottom: 0;
                font-size: 0.72rem;
                line-height: 1;
            }

            .editor-page-tool-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.2rem;
                min-width: 0;
                flex: 0 0 auto;
                padding: 0.2rem 0.28rem;
                font-size: 0.8rem;
                line-height: 1;
            }

            .editor-page-tool-btn i {
                pointer-events: none;
            }

            .editor-page-tools {
                max-width: 100%;
                align-content: flex-start;
            }

            .editor-toolbar-switch {
                min-height: 28px;
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding-top: 0;
            }

            .editor-toolbar-switch .form-check-input {
                margin-top: 0;
            }

            .editor-toolbar-switch .form-check-label {
                margin-bottom: 0;
            }

            #buttons-container .btn {
                font-size: 0.78rem;
                padding: 0.26rem 0.2rem;
            }

            #editor-container .cm-content {
                text-align: left !important;
                text-justify: auto !important;
            }

            .editor-text-frame {
                min-height: calc(100vh - 232px);
            }
        </style>
    @endpush

@endsection
