@props(['xmlContent', 'imagesData', 'urlFileSave', 'canEdit'])

<div class="border border-top-0 p-3 row m-0 overflow-auto editor" style="height: calc(100vh - 200px);">
    <div class="col-md-6 col-12 order-last order-md-first h-100 d-flex flex-column">
        <div>
            <button
                id="save-xml"
                class="btn btn-success mb-2"
                {{ !$canEdit ? 'disabled' : '' }}
                title="Sauvegarder les modifications"
            ><i class="bi bi-floppy-fill"></i></button>
            <div class="btn-group" role="group" aria-label="Editor utility buttons">
                <button
                    id="toggle-readonly"
                    class="btn btn-outline-primary mb-2"
                    {{ !$canEdit ? 'disabled' : '' }}
                    data-bs-toggle="button"
                    title="Activer/Désactiver le mode édition"
                ><i class="bi bi-pencil-square"></i></button>
                <button
                    id="toggle-tags"
                    data-bs-toggle="button"
                    class="btn btn-outline-primary mb-2"
                    title="Afficher/Masquer les balises"
                ><i class="bi bi-code-square"></i></button>
            </div>
            <div class="btn-group" role="group" aria-label="Editor italic buttons">
                <button
                    id="italic-open-btn"
                    class="btn btn-outline-primary mb-2"
                    title="Insérer balise italique ouvrante"
                ><i class="bi bi-code"></i><i class="bi bi-type-italic"></i></button>
                <button
                    id="italic-close-btn"
                    class="btn btn-outline-primary mb-2"
                    title="Insérer balise italique fermante"
                ><i class="bi bi-code-slash"></i><i class="bi bi-type-italic"></i></button>
                <button
                    id="italic-report-btn"
                    class="btn btn-outline-primary mb-2"
                    data-bs-toggle="modal" 
                    data-bs-target="#italicErrorsModal"
                    title="Rapport d'erreurs des tags italiques"
                ><i class="bi bi-exclamation-triangle-fill"></i></button>
            </div>
            <button
                id="search-btn"
                class="btn btn-outline-primary mb-2"
                data-bs-toggle="button"
                title="Rechercher du texte"
            ><i class="bi bi-search"></i></button>
        </div>
        <div
            id="editor-container"
            style="border:1px solid #ccc;"
            class="overflow-scroll"
        ></div>
    </div>
    <div class="col-md-2 col-12 h-100 d-flex flex-column justify-content-between gap-2">
        <button
            id="generate-page-numbers"
            class="btn btn-outline-primary align-self-start"
            data-bs-toggle="modal" data-bs-target="#generatePageNumbersModal"
            {{ !$canEdit ? 'disabled' : '' }}
            title="Générer les numéros de page"
        ><i class="bi bi-123"></i></button>
        <div class="overflow-auto mb-auto">
            <div id="buttons-container" class="row row-cols-3 g-1 align-items-start w-100">
                @foreach ($imagesData ?? [] as $facsimile)
                    <div class="col button-item" style="display: none;">
                        <button
                            class="h-100 w-100 btn btn-primary btn-sm position-relative"
                            data-tag="{{ $loop->iteration }}"
                            data-img-src="{{ Storage::url(ltrim($facsimile['big'], '/')) }}"
                            {{ !$canEdit ? 'disabled' : '' }}
                        >
                            <span>?</span>
                            <span
                                class="position-absolute top-0 start-75 translate-middle badge rounded-pill bg-danger"
                                data-tag-count="{{ $loop->iteration }}"
                                style="display: none; font-size: 0.65rem;"
                            ></span>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
        <nav aria-label="Page navigation">
            <ul id="pagination" class="pagination pagination-sm mb-0 flex-wrap justify-content-center"></ul>
        </nav>
    </div>
    <div class="col-md-4 col-12 h-100 d-flex flex-column align-items-center">
        <a id="image-name" class="btn btn-link mb-2" style="display: none;" href="#" target="_blank"></a>
        <div id="loading-spinner" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
        </div>
        <div
                style="display: none; min-height: 0;"
                class="w-100 overflow-hidden position-relative"
        >
            <img 
                id="facsimile-preview" 
                src="" 
                alt="Aperçu du facsimilé" 
                class="w-100 h-100 object-fit-contain"
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
        canEdit: {{ $canEdit ? 'true' : 'false' }},
        urlFileSave: '{{ $urlFileSave }}',
      };
    </script>
    @vite('resources/js/editor.js')
@endpush