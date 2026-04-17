{{-- resources/views/components/main/facsimiles.blade.php --}}
<div id="facsimile-reader-card" class="card mb-3 d-none">
    <div class="card-header fw-semibold d-flex justify-content-center align-items-center facsimile-reader-card-header">
        <div class="d-flex align-items-start gap-2 admin-card-heading">
            <span class="admin-card-heading-text">
                <span id="facsimile-reader-card-title" class="admin-card-title">Lecteur synchronisé</span>
            </span>
        </div>
    </div>
    <div class="card-body">
        <div id="facsimile-reader" class="facsimile-reader d-none">
            <div class="facsimile-reader-toolbar">
                <div class="facsimile-reader-controls">
                    <div class="facsimile-reader-control-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="facsimile-reader-prev">‹ Page précédente</button>
                        <select id="facsimile-reader-page" class="form-select form-select-sm"></select>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="facsimile-reader-next">Page suivante ›</button>
                    </div>
                    <div class="facsimile-reader-control-group">
                        <select id="facsimile-reader-text-source" class="form-select form-select-sm" title="Choisir la source du texte affiché">
                            <option value="auto">Source texte auto</option>
                        </select>
                        <select id="facsimile-reader-encoding" class="form-select form-select-sm" title="Ajuster l’encodage si le rendu du texte est visiblement incorrect">
                            <option value="auto">Encodage auto</option>
                            <option value="UTF-8">UTF-8</option>
                            <option value="Windows-1252">Windows-1252</option>
                            <option value="ISO-8859-1">ISO-8859-1</option>
                            <option value="Mac Roman">Mac Roman</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="facsimile-reader-rebuild" title="Reconstruire le dataset du lecteur pour cette version">Reconstruire</button>
                        <span id="facsimile-reader-action-status" class="small text-muted facsimile-reader-action-status" aria-live="polite"></span>
                        <button type="button" class="btn btn-sm btn-outline-success d-none" id="facsimile-reader-convert-utf8" disabled aria-hidden="true" tabindex="-1">Convertir en UTF-8</button>
                    </div>
                </div>
            </div>

            <div id="facsimile-reader-loading" class="facsimile-reader-loading d-none" aria-hidden="true">
                <div class="facsimile-reader-loading-spinner spinner-border text-secondary" role="status" aria-hidden="true"></div>
                <div id="facsimile-reader-loading-label" class="facsimile-reader-loading-label small text-muted">Chargement du viewer…</div>
                <div class="visually-hidden" role="status">Chargement du viewer</div>
            </div>

            <div id="facsimile-reader-empty" class="facsimile-reader-empty text-muted small">
                Les repères de pagination de cette version permettront d’aligner le fac-similé et le texte ici.
            </div>

            <div id="facsimile-reader-carousel" class="facsimile-reader-carousel d-none">
                <button type="button" class="btn btn-sm btn-outline-secondary facsimile-reader-carousel-nav" id="facsimile-reader-carousel-prev" aria-label="Miniatures précédentes">‹</button>
                <div id="facsimile-reader-thumbs" class="facsimile-reader-thumbs" aria-label="Miniatures des fac-similés"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary facsimile-reader-carousel-nav" id="facsimile-reader-carousel-next" aria-label="Miniatures suivantes">›</button>
            </div>

            <div id="facsimile-reader-workspace" class="facsimile-reader-workspace d-none">
                <section class="facsimile-reader-pane">
                    <div class="facsimile-reader-pane-heading">
                        <div class="fw-semibold">Fac-similé</div>
                        <div id="facsimile-reader-image-meta" class="small text-muted"></div>
                    </div>
                    <div class="facsimile-reader-pane-toolbar">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Ajustement de l'image">
                            <button type="button" class="btn btn-outline-secondary" id="facsimile-reader-fit-auto" aria-pressed="true">Auto</button>
                            <button type="button" class="btn btn-outline-secondary" id="facsimile-reader-fit-width">Largeur</button>
                            <button type="button" class="btn btn-outline-secondary" id="facsimile-reader-fit-height">Hauteur</button>
                            <button type="button" class="btn btn-outline-secondary" id="facsimile-reader-fit-natural">Réel</button>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Recadrage de l'image">
                            <button type="button" class="btn btn-outline-secondary" id="facsimile-reader-crop-set">Recadrer</button>
                            <button type="button" class="btn btn-outline-secondary" id="facsimile-reader-crop-clear" disabled>Effacer cadre</button>
                        </div>
                    </div>
                    <div class="facsimile-reader-image-shell">
                        <div id="facsimile-reader-crop-viewport" class="facsimile-reader-crop-viewport">
                            <img id="facsimile-reader-image" class="facsimile-reader-image" alt="Fac-similé synchronisé">
                            <div id="facsimile-reader-crop-overlay" class="facsimile-reader-crop-overlay d-none">
                                <div id="facsimile-reader-crop-rect" class="facsimile-reader-crop-rect d-none"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="facsimile-reader-pane">
                    <div class="facsimile-reader-pane-heading">
                        <div class="fw-semibold">Texte</div>
                        <div id="facsimile-reader-text-meta" class="small text-muted"></div>
                    </div>
                    <pre id="facsimile-reader-text" class="facsimile-reader-text"></pre>
                </section>
            </div>
        </div>
    </div>
</div>

<div id="facsimiles-card" class="card d-none" aria-hidden="true">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center facsimiles-toggle"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#facsimilesCollapse"
        aria-expanded="true"
        aria-controls="facsimilesCollapse">
        <div class="d-flex align-items-start gap-2 admin-card-heading">
            <span class="collapse-chevron" aria-hidden="true"></span>
            <span class="admin-card-heading-text">
                <span class="admin-card-title">Fac-similés</span>
            </span>
        </div>
    </div>
    <div id="facsimilesCollapse" class="collapse show">
    <div class="card-body">
        <p class="fst-italic text-muted small mb-3">
            Consultez ici les fac-similés associés à chaque version textuelle et préparez, si besoin, leur publication par manifeste.
        </p>

        <div id="facsimiles-empty-state" class="facsimiles-empty-state">
            <div class="facsimiles-empty-title">Aucune série de fac-similés sélectionnée</div>
            <div class="facsimiles-empty-text">
                Choisissez une version textuelle dans la section «&nbsp;Versions textuelles&nbsp;» pour afficher les fac-similés associés.
            </div>
            <div class="facsimiles-empty-hint">
                Les images importées et les manifestes de publication apparaîtront ici.
            </div>
        </div>

        <div id="facsimiles-workspace" class="d-none">
            <div id="facsimile-status" class="text-muted small mb-3"></div>

            <div id="manifest-manager" class="manifest-manager border rounded px-3 py-3 mb-3 d-none">
                <div class="d-flex flex-column flex-xl-row align-items-xl-center gap-2 gap-xl-3">
                    <div class="manifest-instructions">
                        <div class="fw-semibold text-uppercase small text-muted">Gestion du manifeste JSON</div>
                        <div class="text-muted small">Sélectionnez une comparaison pour choisir les images publiées.</div>
                    </div>
                    <div class="flex-grow-1">
                        <label for="manifest-comparison" class="form-label small mb-1">Comparaison</label>
                        <select id="manifest-comparison" class="form-select form-select-sm" disabled>
                            <option value="">Associer une comparaison…</option>
                        </select>
                    </div>
                    <div class="d-flex flex-nowrap gap-2">
                        <button type="button" class="btn btn-sm btn-primary" id="manifest-save" disabled>Enregistrer</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="manifest-cancel" disabled>Annuler</button>
                    </div>
                </div>
                <div id="manifest-list" class="manifest-list d-flex flex-wrap gap-2 mt-3"></div>
                <div id="manifest-summary" class="small text-muted mt-2"></div>
            </div>

            <div class="facsimile-subcard">
                <div class="facsimile-subcard-header">
                    <div>
                        <div class="fw-semibold text-uppercase small text-muted">Galerie des fac-similés</div>
                        <div class="small text-muted">Visualiseur historique centré sur les images seules.</div>
                    </div>
                </div>
                <div class="facsimile-subcard-body">
                    <div id="gallery" class="d-flex flex-wrap gap-2"></div>
                    <div id="gallery-meta" class="mt-2 text-center text-muted small"></div>
                    <div id="gallery-pagination" class="mt-1 d-flex flex-wrap justify-content-center gap-1"></div>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

@push('styles')
<style>
    .facsimiles-toggle .collapse-chevron::before {
        content: "\25BC";
        display: inline-block;
        transition: transform .2s ease;
    }
    .facsimiles-toggle[aria-expanded="false"] .collapse-chevron::before {
        transform: rotate(-90deg);
    }
    #facsimilesCollapse,
    #facsimilesCollapse *,
    #facsimilesCollapse.show,
    #facsimilesCollapse.show * {
        visibility: visible !important;
    }
    #manifest-manager {
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
    }
    .facsimile-subcard {
        border: 1px solid #e9ecef;
        border-radius: 0.85rem;
        background: #fff;
        overflow: hidden;
    }
    .facsimile-subcard-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.95rem 1rem;
        background: linear-gradient(180deg, #fcfbf8 0%, #f6f3ee 100%);
        border-bottom: 1px solid #ece5da;
    }
    .facsimile-subcard-body {
        padding: 1rem;
    }
    .facsimiles-empty-state {
        display: grid;
        place-items: center;
        gap: 0.7rem;
        padding: 1.75rem 1.5rem;
        border: 1px dashed #d4cec3;
        border-radius: 0.9rem;
        background: linear-gradient(180deg, #faf8f4 0%, #f3f0ea 100%);
        text-align: center;
    }
    .facsimiles-empty-title {
        font-size: 1rem;
        font-weight: 600;
        color: #4b453d;
        letter-spacing: 0.01em;
    }
    .facsimiles-empty-text,
    .facsimiles-empty-hint {
        max-width: 44rem;
        font-size: 0.88rem;
        line-height: 1.5;
    }
    .facsimiles-empty-text {
        color: #61594f;
    }
    .facsimiles-empty-hint {
        color: #7a7165;
    }
    #manifest-manager select {
        min-width: 260px;
    }
    .manifest-instructions {
        min-width: 220px;
    }
    .manifest-list {
        min-height: 1.5rem;
    }
    .manifest-pill {
        font-size: 0.78rem;
        line-height: 1.2;
        border-radius: 999px;
        padding: 0.2rem 0.65rem;
        transition: all 0.15s ease-in-out;
    }
    .manifest-pill.btn-outline-secondary:hover {
        color: var(--bs-primary);
        border-color: var(--bs-primary);
        background-color: rgba(13, 110, 253, 0.08);
    }
    .fac-item {
        width: 125px;
    }
    .fac-item-selectable {
        cursor: pointer;
    }
    .fac-item-selected .fac-thumb {
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }
    .manifest-toggle {
        cursor: pointer;
    }
    .facsimile-reader {
        background: transparent;
        border: 0;
        border-radius: 0;
        padding: 0;
    }
    .facsimile-reader-card-header .admin-card-heading {
        width: 100%;
        justify-content: center;
    }
    .facsimile-reader-card-header .admin-card-heading-text {
        display: block;
        width: 100%;
        text-align: center;
    }
    .facsimile-reader-card-title {
        display: block;
        white-space: normal;
        line-height: 1.35;
        font-size: 1.18rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        color: #3f352b;
    }
    .facsimile-reader-card-subtitle {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.88rem;
        font-weight: 500;
        color: #5f5b55;
        text-align: center;
    }
    .facsimile-reader-toolbar {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }
    .facsimile-reader-controls {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: nowrap;
        justify-content: center;
        min-width: 0;
        width: 100%;
    }
    .facsimile-reader-control-group {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: nowrap;
    }
    .facsimile-reader-action-status {
        min-height: 1.25rem;
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
    }
    .facsimile-reader-controls select {
        min-width: 0;
    }
    #facsimile-reader-page {
        width: 11rem;
    }
    #facsimile-reader-encoding {
        width: 10.5rem;
    }
    .facsimile-reader-empty {
        border: 1px dashed #d4cec3;
        border-radius: 0.8rem;
        background: rgba(255, 255, 255, 0.55);
        padding: 1rem;
        text-align: center;
    }
    .facsimile-reader-loading {
        margin-bottom: 0.85rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        padding: 0.9rem 1rem;
        border: 1px dashed #d4cec3;
        border-radius: 0.8rem;
        background: rgba(255, 255, 255, 0.55);
    }
    .facsimile-reader-loading-spinner {
        width: 1.65rem;
        height: 1.65rem;
        border-width: 0.18rem;
        color: #8a7358 !important;
    }
    .facsimile-reader-loading-label {
        margin-bottom: 0;
    }
    .facsimile-reader-carousel {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
        padding: 0.85rem 0.95rem;
        border: 1px solid #ddd4c8;
        border-radius: 0.9rem;
        background: rgba(255, 255, 255, 0.72);
    }
    .facsimile-reader-carousel-nav {
        width: 2.25rem;
        min-width: 2.25rem;
        height: 2.25rem;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .facsimile-reader-thumbs {
        display: flex;
        gap: 0.75rem;
        overflow-x: auto;
        overflow-y: hidden;
        scroll-behavior: smooth;
        padding-bottom: 0.2rem;
    }
    .facsimile-reader-thumb-card {
        flex: 0 0 auto;
        width: 6.5rem;
        display: grid;
        gap: 0.35rem;
    }
    .facsimile-reader-thumb-btn {
        display: block;
        border: 1px solid #d5cdc0;
        border-radius: 0.6rem;
        background: #fff;
        overflow: hidden;
        padding: 0.28rem;
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .facsimile-reader-thumb-btn:hover {
        border-color: #9fb0c1;
        box-shadow: 0 0 0 0.18rem rgba(55, 95, 145, 0.10);
    }
    .facsimile-reader-thumb-card.is-current .facsimile-reader-thumb-btn {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.18);
        background: #eef4ff;
    }
    .facsimile-reader-thumb-image {
        display: block;
        width: 100%;
        aspect-ratio: 2 / 3;
        object-fit: contain;
        background: #f8f4ee;
        border-radius: 0.4rem;
    }
    .facsimile-reader-thumb-caption {
        font-size: 0.74rem;
        line-height: 1.3;
        color: #4f4a43;
        word-break: break-word;
    }
    .facsimile-reader-thumb-card.is-current .facsimile-reader-thumb-caption {
        color: #184b96;
        font-weight: 600;
    }
    .facsimile-reader-workspace {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 1rem;
        align-items: stretch;
    }
    .facsimile-reader-pane {
        min-width: 0;
        border: 1px solid #ddd4c8;
        border-radius: 0.9rem;
        background: rgba(255, 255, 255, 0.78);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 40rem;
    }
    .facsimile-reader-pane-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 0.85rem;
        border-bottom: 1px solid #e5ded2;
        background: rgba(248, 245, 239, 0.92);
        min-height: 2.5rem;
        font-size: 0.92rem;
    }
    .facsimile-reader-pane-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        padding: 0.45rem 0.85rem;
        border-bottom: 1px solid #ece4d8;
        background: rgba(252, 249, 244, 0.92);
    }
    .facsimile-reader-pane-heading .fw-semibold {
        font-size: 0.9rem;
        letter-spacing: 0.01em;
        color: #43382c;
    }
    .facsimile-reader-pane-heading .small {
        font-size: 0.76rem;
        line-height: 1.35;
    }
    .facsimile-reader-pane-toolbar .btn-group > .btn {
        min-width: 5.4rem;
    }
    .facsimile-reader-image-shell {
        flex: 1 1 auto;
        padding: 1rem;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        background: #f7f3ed;
        overflow: auto;
    }
    .facsimile-reader-crop-viewport {
        position: relative;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        width: 100%;
        min-height: 100%;
        overflow: hidden;
    }
    .facsimile-reader-image {
        display: block;
        max-width: 100%;
        max-height: 72vh;
        width: auto;
        height: auto;
        object-fit: contain;
        border: 1px solid #d5cdc0;
        border-radius: 0.5rem;
        background: #fff;
    }
    .facsimile-reader.has-user-crop .facsimile-reader-crop-viewport {
        align-items: stretch;
        justify-content: stretch;
    }
    .facsimile-reader.fit-width .facsimile-reader-image-shell,
    .facsimile-reader.fit-height .facsimile-reader-image-shell,
    .facsimile-reader.fit-natural .facsimile-reader-image-shell {
        align-items: flex-start;
        justify-content: flex-start;
    }
    .facsimile-reader.fit-auto .facsimile-reader-image {
        max-width: 100%;
        max-height: 72vh;
        width: auto;
        height: auto;
        object-fit: contain;
    }
    .facsimile-reader.fit-width .facsimile-reader-image {
        max-width: 100%;
        width: 100%;
        height: auto;
        max-height: none;
        object-fit: contain;
    }
    .facsimile-reader.fit-height .facsimile-reader-image {
        width: auto;
        height: 72vh;
        max-width: none;
        max-height: 72vh;
        object-fit: contain;
    }
    .facsimile-reader.fit-natural .facsimile-reader-image {
        max-width: none;
        max-height: none;
        width: auto;
        height: auto;
        object-fit: initial;
    }
    .facsimile-reader-crop-overlay {
        position: absolute;
        inset: 0;
        cursor: crosshair;
        background: rgba(255, 255, 255, 0.08);
    }
    .facsimile-reader-crop-rect {
        position: absolute;
        border: 2px solid #0d6efd;
        background: rgba(13, 110, 253, 0.10);
        box-shadow: 0 0 0 9999px rgba(17, 24, 39, 0.18);
        pointer-events: none;
    }
    .facsimile-reader-text {
        margin: 0;
        padding: 1rem 1.1rem 1.3rem;
        white-space: pre-wrap;
        word-break: break-word;
        overflow: auto;
        flex: 1 1 auto;
        background: #fffdf9;
        font-size: 0.94rem;
        line-height: 1.7;
        color: #2f2a24;
        text-align: justify;
        text-justify: inter-word;
    }
    .facsimile-reader-anchor {
        display: inline-block;
        margin: 0 0.35rem 0.35rem 0;
        padding: 0.08rem 0.48rem;
        border-radius: 999px;
        background: #d7e5ff;
        color: #294a7a;
        font-size: 0.78rem;
        font-weight: 600;
        line-height: 1.4;
        vertical-align: middle;
    }
    @media (max-width: 991.98px) {
        .facsimile-reader-toolbar {
            flex-direction: column;
            display: flex;
            align-items: stretch;
        }
        .facsimile-reader-controls {
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        .facsimile-reader-control-group {
            flex-wrap: wrap;
        }
        .facsimile-reader-pane-toolbar {
            justify-content: flex-start;
        }
        .facsimile-reader-workspace {
            grid-template-columns: 1fr;
        }
        .facsimile-reader-pane {
            min-height: 22rem;
        }
        .facsimile-reader-carousel {
            grid-template-columns: 1fr;
        }
        .facsimile-reader-carousel-nav {
            display: none;
        }
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const gallery       = document.getElementById('gallery');
    const galleryMeta   = document.getElementById('gallery-meta');
    const galleryPager  = document.getElementById('gallery-pagination');
    const statusEl      = document.getElementById('facsimile-status');
    const readerCardEl  = document.getElementById('facsimile-reader-card');
    const readerCardTitleEl = document.getElementById('facsimile-reader-card-title');
    const emptyStateEl  = document.getElementById('facsimiles-empty-state');
    const workspaceEl   = document.getElementById('facsimiles-workspace');
    const manifestManager   = document.getElementById('manifest-manager');
    const manifestSelect    = document.getElementById('manifest-comparison');
    const manifestSaveBtn   = document.getElementById('manifest-save');
    const manifestCancelBtn = document.getElementById('manifest-cancel');
    const manifestSummary   = document.getElementById('manifest-summary');
    const manifestList      = document.getElementById('manifest-list');
    const readerRoot        = document.getElementById('facsimile-reader');
    const readerLoadingEl   = document.getElementById('facsimile-reader-loading');
    const readerLoadingLabelEl = document.getElementById('facsimile-reader-loading-label');
    const readerEmptyEl     = document.getElementById('facsimile-reader-empty');
    const readerCarouselEl  = document.getElementById('facsimile-reader-carousel');
    const readerThumbsEl    = document.getElementById('facsimile-reader-thumbs');
    const readerCarouselPrevBtn = document.getElementById('facsimile-reader-carousel-prev');
    const readerCarouselNextBtn = document.getElementById('facsimile-reader-carousel-next');
    const readerWorkspaceEl = document.getElementById('facsimile-reader-workspace');
    const readerPrevBtn     = document.getElementById('facsimile-reader-prev');
    const readerNextBtn     = document.getElementById('facsimile-reader-next');
    const readerPageSelect  = document.getElementById('facsimile-reader-page');
    const readerImageEl     = document.getElementById('facsimile-reader-image');
    const readerCropViewportEl = document.getElementById('facsimile-reader-crop-viewport');
    const readerCropOverlayEl = document.getElementById('facsimile-reader-crop-overlay');
    const readerCropRectEl  = document.getElementById('facsimile-reader-crop-rect');
    const readerImageMetaEl = document.getElementById('facsimile-reader-image-meta');
    const readerTextEl      = document.getElementById('facsimile-reader-text');
    const readerTextMetaEl  = document.getElementById('facsimile-reader-text-meta');
    const readerFitAutoBtn  = document.getElementById('facsimile-reader-fit-auto');
    const readerFitWidthBtn = document.getElementById('facsimile-reader-fit-width');
    const readerFitHeightBtn = document.getElementById('facsimile-reader-fit-height');
    const readerFitNaturalBtn = document.getElementById('facsimile-reader-fit-natural');
    const readerCropSetBtn  = document.getElementById('facsimile-reader-crop-set');
    const readerCropClearBtn = document.getElementById('facsimile-reader-crop-clear');
    const readerTextSourceSelect = document.getElementById('facsimile-reader-text-source');
    const readerEncodingSelect = document.getElementById('facsimile-reader-encoding');
    const readerRebuildBtn  = document.getElementById('facsimile-reader-rebuild');
    const readerActionStatusEl = document.getElementById('facsimile-reader-action-status');
    const readerConvertBtn  = document.getElementById('facsimile-reader-convert-utf8');
    const csrfToken         = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const manifestOptionElements = new Map();

    const GALLERY_PAGE_SIZE = 12;

    const formatBytes = (size) => {
        const value = Number(size);
        if (!Number.isFinite(value) || value <= 0) return '0 o';
        const units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        let idx = 0;
        let current = value;
        while (current >= 1024 && idx < units.length - 1) {
            current /= 1024;
            idx++;
        }
        const precision = idx === 0 ? 0 : 1;
        return `${current.toFixed(precision)} ${units[idx]}`;
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    let galleryFiles      = [];
    let galleryPage       = 1;
    let currentWorkId     = null;
    let currentVersionId  = null;
    let currentVersionName= '';
    let facsimilesLoadingCount = 0;
    const setFacsimilesLoading = (state) => {
        if (typeof window.setBladeLoading === 'function') {
            window.setBladeLoading('facsimilesCollapse', state);
        }
    };
    const bumpFacsimilesLoading = (delta) => {
        facsimilesLoadingCount = Math.max(0, facsimilesLoadingCount + delta);
        setFacsimilesLoading(facsimilesLoadingCount > 0);
    };

    const facsimilesCollapse = document.getElementById('facsimilesCollapse');
    const facsimilesCard     = facsimilesCollapse ? facsimilesCollapse.closest('.card') : null;
    let manifestOptions      = [];
    let manifestActiveKey    = null;
    let manifestSelectedSet  = new Set();
    let manifestOriginalSet  = new Set();
    let manifestReadOnly     = false;
    let manifestBusy         = false;
    let manifestRequestToken = 0;
    let manifestLoadAbortController = null;
    let readerData           = null;
    let readerPages          = [];
    let readerPageIndex      = 0;
    let readerImageIndex     = 0;
    let readerFitMode        = 'auto';
    let readerTextSource     = 'auto';
    let readerEncoding       = 'auto';
    let readerRebuildBusy    = false;
    let readerConvertBusy    = false;
    let readerLoadRequestToken = 0;
    let readerLoadAbortController = null;
    let readerLoadingVersionId = null;
    let facsimileSelectionInFlight = false;
    let pendingFacsimileSelection = null;
    let readerPageRequestToken = 0;
    const readerPageLoadPromises = new Map();
    const readerImagePrefetchUrls = new Set();
    let readerCropMode       = false;
    let readerCurrentCrop    = null;
    let readerCropDraft      = null;
    let readerCropImageRect  = null;
    let readerCropResizeObserver = null;

    function loadReaderFitPreference() {
        try {
            const stored = window.localStorage?.getItem('variance.facsimileReader.fitMode');
            if (['auto', 'width', 'height', 'natural'].includes(String(stored))) {
                return String(stored);
            }
            if (stored === '0') return 'natural';
            if (stored === '1') return 'auto';
        } catch (_) {}
        return 'auto';
    }

    function readerEncodingStorageKey(versionId) {
        return `variance.facsimileReader.encoding.${versionId || 'default'}`;
    }

    function readerTextSourceStorageKey(versionId) {
        return `variance.facsimileReader.textSource.${versionId || 'default'}`;
    }

    function normalizeReaderTextSource(value) {
        const raw = String(value || '').trim();
        if (!raw || raw.toLowerCase() === 'auto') return 'auto';
        if (raw === 'version-txt') return 'version-txt';
        if (raw === 'comparison-xhtml') return 'comparison-xhtml';
        return 'auto';
    }

    function loadReaderTextSourcePreference(versionId) {
        try {
            return normalizeReaderTextSource(window.localStorage?.getItem(readerTextSourceStorageKey(versionId)));
        } catch (_) {
            return 'auto';
        }
    }

    function saveReaderTextSourcePreference(versionId, value) {
        try {
            window.localStorage?.setItem(readerTextSourceStorageKey(versionId), normalizeReaderTextSource(value));
        } catch (_) {}
    }

    function normalizeReaderEncoding(value) {
        const raw = String(value || '').trim();
        if (!raw || raw.toLowerCase() === 'auto') return 'auto';
        if (raw === 'UTF-8') return 'UTF-8';
        if (raw === 'Windows-1252') return 'Windows-1252';
        if (raw === 'ISO-8859-1') return 'ISO-8859-1';
        if (raw === 'Mac Roman' || raw === 'Macintosh') return 'Mac Roman';
        return 'auto';
    }

    function loadReaderEncodingPreference(versionId) {
        try {
            return normalizeReaderEncoding(window.localStorage?.getItem(readerEncodingStorageKey(versionId)));
        } catch (_) {
            return 'auto';
        }
    }

    function saveReaderEncodingPreference(versionId, value) {
        try {
            window.localStorage?.setItem(readerEncodingStorageKey(versionId), normalizeReaderEncoding(value));
        } catch (_) {}
    }

    function readerCropStorageKey(versionId, imageName) {
        return `variance.facsimileReader.crop.${versionId || 'default'}.${imageName || 'image'}`;
    }

    function loadStoredReaderCrop(versionId, imageName) {
        if (!versionId || !imageName) return null;
        try {
            const raw = window.localStorage?.getItem(readerCropStorageKey(versionId, imageName));
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            const x = Number(parsed?.x);
            const y = Number(parsed?.y);
            const w = Number(parsed?.w);
            const h = Number(parsed?.h);
            if (![x, y, w, h].every(Number.isFinite)) return null;
            if (w <= 0 || h <= 0) return null;
            return {
                x: Math.max(0, Math.min(1, x)),
                y: Math.max(0, Math.min(1, y)),
                w: Math.max(0.01, Math.min(1, w)),
                h: Math.max(0.01, Math.min(1, h)),
            };
        } catch (_) {
            return null;
        }
    }

    function saveStoredReaderCrop(versionId, imageName, crop) {
        if (!versionId || !imageName || !crop) return;
        try {
            window.localStorage?.setItem(readerCropStorageKey(versionId, imageName), JSON.stringify(crop));
        } catch (_) {}
    }

    function clearStoredReaderCrop(versionId, imageName) {
        if (!versionId || !imageName) return;
        try {
            window.localStorage?.removeItem(readerCropStorageKey(versionId, imageName));
        } catch (_) {}
    }

    function applyReaderEncodingControl() {
        if (!readerEncodingSelect) return;
        readerEncodingSelect.value = normalizeReaderEncoding(readerEncoding);
        readerEncodingSelect.disabled = !currentVersionId;
        if (readerConvertBtn) {
            readerConvertBtn.disabled = true;
            readerConvertBtn.textContent = 'Convertir en UTF-8';
            readerConvertBtn.title = 'Conversion désactivée dans le lecteur.';
        }
    }

    function applyReaderRebuildControl() {
        if (!readerRebuildBtn) return;
        readerRebuildBtn.disabled = !currentVersionId || readerRebuildBusy || !!readerLoadingVersionId;
        readerRebuildBtn.textContent = readerRebuildBusy ? 'Reconstruction…' : 'Reconstruire';
    }

    function applyReaderTextSourceControl() {
        if (!readerTextSourceSelect) return;

        const options = Array.isArray(readerData?.text_source_options) ? readerData.text_source_options : [];
        const available = options.filter(option => option?.value && option?.label);

        readerTextSourceSelect.innerHTML = '';
        if (!available.length) {
            readerTextSourceSelect.appendChild(new Option('Source texte auto', 'auto'));
            readerTextSourceSelect.value = 'auto';
            readerTextSourceSelect.disabled = true;
            return;
        }

        available.forEach(option => {
            readerTextSourceSelect.appendChild(new Option(String(option.label), String(option.value)));
        });

        const selected = normalizeReaderTextSource(readerData?.text_source ?? readerTextSource);
        readerTextSourceSelect.value = selected === 'auto'
            ? String(available[0].value)
            : selected;
        readerTextSourceSelect.disabled = !currentVersionId || available.length < 2;
        readerTextSource = normalizeReaderTextSource(readerTextSourceSelect.value);
    }

    function updateReaderCardTitle() {
        if (!readerCardTitleEl) return;
        if (readerData && (currentVersionName || readerData?.text_source_label || readerData?.pagination?.origin)) {
            const versionLabel = currentVersionName || 'Version';
            const summary = buildReaderSummary();
            const prefix = `${versionLabel} · `;
            const details = summary.startsWith(prefix) ? summary.slice(prefix.length) : summary;
            readerCardTitleEl.innerHTML = `
                <span class="facsimile-reader-card-title">${escapeHtml(versionLabel)}</span>
                <span class="facsimile-reader-card-subtitle">${escapeHtml(details)}</span>
            `;
            return;
        }
        readerCardTitleEl.textContent = currentVersionName || 'Lecteur synchronisé';
    }

    function readerUsesIndependentImageNavigation() {
        return !readerData?.pagination?.available && Array.isArray(readerData?.facsimiles) && readerData.facsimiles.length > 0;
    }

    function currentDisplayedReaderImage() {
        if (readerUsesIndependentImageNavigation()) {
            return readerData?.facsimiles?.[readerImageIndex] || null;
        }
        return readerPages[readerPageIndex]?.image || null;
    }

    function currentReaderImageName() {
        return currentDisplayedReaderImage()?.name || null;
    }

    function hideReaderCropOverlay() {
        if (readerCropOverlayEl) {
            readerCropOverlayEl.classList.add('d-none');
        }
        if (readerCropRectEl) {
            readerCropRectEl.classList.add('d-none');
            readerCropRectEl.style.left = '';
            readerCropRectEl.style.top = '';
            readerCropRectEl.style.width = '';
            readerCropRectEl.style.height = '';
        }
    }

    function updateReaderCropControls() {
        const hasImage = !!currentReaderImageName();
        const hasCrop = !!readerCurrentCrop;
        if (readerCropSetBtn) {
            readerCropSetBtn.disabled = !hasImage;
            readerCropSetBtn.classList.toggle('btn-primary', readerCropMode);
            readerCropSetBtn.classList.toggle('btn-outline-secondary', !readerCropMode);
            readerCropSetBtn.textContent = readerCropMode ? 'Tracer le cadre…' : 'Recadrer';
        }
        if (readerCropClearBtn) {
            readerCropClearBtn.disabled = !hasImage || !hasCrop || readerCropMode;
        }
    }

    function resetReaderImageInlineStyles() {
        if (!readerImageEl) return;
        readerImageEl.style.position = '';
        readerImageEl.style.left = '';
        readerImageEl.style.top = '';
        readerImageEl.style.width = '';
        readerImageEl.style.height = '';
        readerImageEl.style.maxWidth = '';
        readerImageEl.style.maxHeight = '';
        readerImageEl.style.transform = '';
    }

    function applyReaderCropDisplay() {
        if (!readerRoot || !readerImageEl || !readerCropViewportEl) return;
        readerRoot.classList.remove('has-user-crop', 'crop-mode');
        resetReaderImageInlineStyles();

        if (readerCropMode) {
            readerRoot.classList.add('crop-mode');
            if (readerCropOverlayEl) {
                readerCropOverlayEl.classList.remove('d-none');
            }
            return;
        }

        hideReaderCropOverlay();

        if (!readerCurrentCrop || !readerImageEl.complete || !readerImageEl.naturalWidth || !readerImageEl.naturalHeight) {
            return;
        }

        const viewportWidth = readerCropViewportEl.clientWidth;
        const viewportHeight = readerCropViewportEl.clientHeight;
        if (viewportWidth <= 0 || viewportHeight <= 0) {
            return;
        }

        const naturalWidth = readerImageEl.naturalWidth;
        const naturalHeight = readerImageEl.naturalHeight;
        const srcX = naturalWidth * readerCurrentCrop.x;
        const srcY = naturalHeight * readerCurrentCrop.y;
        const srcW = naturalWidth * readerCurrentCrop.w;
        const srcH = naturalHeight * readerCurrentCrop.h;

        if (srcW <= 0 || srcH <= 0) {
            return;
        }

        const scale = Math.min(viewportWidth / srcW, viewportHeight / srcH);
        const displayWidth = naturalWidth * scale;
        const displayHeight = naturalHeight * scale;
        const offsetX = -srcX * scale + ((viewportWidth - srcW * scale) / 2);
        const offsetY = -srcY * scale + ((viewportHeight - srcH * scale) / 2);

        readerRoot.classList.add('has-user-crop');
        readerImageEl.style.position = 'absolute';
        readerImageEl.style.left = `${offsetX}px`;
        readerImageEl.style.top = `${offsetY}px`;
        readerImageEl.style.width = `${displayWidth}px`;
        readerImageEl.style.height = `${displayHeight}px`;
        readerImageEl.style.maxWidth = 'none';
        readerImageEl.style.maxHeight = 'none';
    }

    function syncReaderCropForCurrentPage() {
        readerCropMode = false;
        readerCropDraft = null;
        readerCropImageRect = null;
        readerCurrentCrop = loadStoredReaderCrop(currentVersionId, currentReaderImageName());
        hideReaderCropOverlay();
        updateReaderCropControls();
        requestAnimationFrame(() => applyReaderCropDisplay());
    }

    function applyReaderFitMode() {
        if (!readerRoot) return;
        readerRoot.classList.remove('fit-auto', 'fit-width', 'fit-height', 'fit-natural');
        readerRoot.classList.add(`fit-${readerFitMode}`);

        const buttons = [
            [readerFitAutoBtn, 'auto'],
            [readerFitWidthBtn, 'width'],
            [readerFitHeightBtn, 'height'],
            [readerFitNaturalBtn, 'natural'],
        ];
        buttons.forEach(([btn, mode]) => {
            if (!btn) return;
            const active = readerFitMode === mode;
            btn.classList.toggle('btn-primary', active);
            btn.classList.toggle('btn-outline-secondary', !active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function setReaderFitMode(nextValue) {
        readerFitMode = ['auto', 'width', 'height', 'natural'].includes(String(nextValue))
            ? String(nextValue)
            : 'auto';
        try {
            window.localStorage?.setItem('variance.facsimileReader.fitMode', readerFitMode);
        } catch (_) {}
        applyReaderFitMode();
    }

    function openFacsimilesPanel() {
        if (typeof window.openEditorialStep === 'function') {
            window.openEditorialStep(2, { focusPanel: false, scrollToJourney: false });
        }
        if (facsimilesCollapse && window.bootstrap?.Collapse) {
            const collapse = bootstrap.Collapse.getOrCreateInstance(facsimilesCollapse, { toggle: false });
            collapse.show();
        }
        window.setTimeout(() => {
            (readerCardEl || facsimilesCard)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 120);
    }
    let pendingManifestFocus = null;

    readerFitMode = loadReaderFitPreference();
    applyReaderFitMode();
    applyReaderEncodingControl();
    applyReaderRebuildControl();

    function manifestOptionKey(comparisonId, role) {
        return `${comparisonId}:${role}`;
    }

    function getManifestOption(key) {
        return manifestOptions.find(opt => manifestOptionKey(opt.comparison_id, opt.role) === key);
    }

    function buildManifestOptionLabel(opt) {
        const base = opt.comparison_label ? String(opt.comparison_label) : `#${opt.comparison_id}`;
        const roleLabel = opt.role_label ? String(opt.role_label) : (opt.role === 'source' ? 'Source' : 'Cible');
        const count = Number.isFinite(opt.count) ? opt.count : (Array.isArray(opt.selected) ? opt.selected.length : 0);
        const suffix = `${count} image${count === 1 ? '' : 's'}`;
        return `${base} — ${roleLabel} (${suffix})`;
    }

    function setsEqual(a, b) {
        if (a.size !== b.size) return false;
        for (const value of a) {
            if (!b.has(value)) return false;
        }
        return true;
    }

    function setStatus(message, tone = 'muted') {
        if (!statusEl) return;
        statusEl.className = `small mb-3 text-${tone}`;
        statusEl.textContent = message;
    }

    function setReaderActionStatus(message = '', tone = 'muted') {
        if (!readerActionStatusEl) return;
        readerActionStatusEl.className = `small facsimile-reader-action-status text-${tone}`;
        readerActionStatusEl.textContent = message;
    }

    function setWorkspaceState(hasSelection) {
        if (readerCardEl) {
            readerCardEl.classList.toggle('d-none', !hasSelection);
        }
        if (emptyStateEl) {
            emptyStateEl.classList.toggle('d-none', !!hasSelection);
        }
        if (workspaceEl) {
            workspaceEl.classList.toggle('d-none', !hasSelection);
        }
    }

    function normalizeReaderCode(value) {
        const raw = String(value ?? '').trim();
        if (!raw) return null;
        const suffixMatch = raw.match(/_(\d+)(?:_thumb)?\.(?:jpe?g|png)$/i);
        if (suffixMatch) {
            return String(Number(suffixMatch[1])).padStart(3, '0');
        }
        const digitMatch = raw.match(/(\d+)/);
        if (digitMatch) {
            return String(Number(digitMatch[1])).padStart(3, '0');
        }
        return null;
    }

    function describePaginationOrigin(origin, markerCount, guessed = false) {
        if (guessed) return 'approximation sans repères';
        if (!origin || !Number.isFinite(Number(markerCount)) || Number(markerCount) <= 0) {
            return 'sans pagination';
        }

        const labels = {
            'lignes': 'fichier _lignes',
            'pb-tei': 'balises <pb> du TEI',
            'pb-xhtml': 'balises de pagination du XHTML',
            'merged': 'sources fusionnées',
        };

        const base = labels[String(origin || '')] || 'source non précisée';
        if (!Number.isFinite(Number(markerCount)) || Number(markerCount) <= 0) {
            return base;
        }

        return `${base} (${Number(markerCount).toLocaleString('fr-FR')} repère(s))`;
    }

    function buildReaderSummary() {
        const versionLabel = currentVersionName || 'Version';
        const markerCount = Number(readerData?.pagination?.marker_count ?? readerPages.length ?? 0);
        const hasGuessedPages = readerPages.some(page => page?.guessed === true);
        const paginationLabel = describePaginationOrigin(readerData?.pagination?.origin, markerCount, hasGuessedPages);

        const textLabel = readerData?.text_source_label || 'source texte non précisée';
        return `${versionLabel} · Texte : ${textLabel} · Pagination : ${paginationLabel}`;
    }

    function setReaderLoading(isLoading) {
        if (!readerLoadingEl) return;
        readerLoadingEl.classList.toggle('d-none', !isLoading);
        readerLoadingEl.setAttribute('aria-hidden', isLoading ? 'false' : 'true');
        if (readerLoadingLabelEl) {
            readerLoadingLabelEl.textContent = readerRebuildBusy
                ? 'Reconstruction du lecteur…'
                : 'Chargement du viewer…';
        }
        applyReaderRebuildControl();
    }

    function resetReader(message = 'Les repères de pagination de cette version permettront d’aligner le fac-similé et le texte ici.') {
        readerData = null;
        readerPages = [];
        readerPageIndex = 0;
        readerPageLoadPromises.clear();
        readerImagePrefetchUrls.clear();
        setReaderActionStatus('');
        setReaderLoading(false);
        applyReaderRebuildControl();
        if (readerRoot) {
            readerRoot.classList.add('d-none');
        }
        updateReaderCardTitle();
        if (readerEmptyEl) {
            readerEmptyEl.textContent = message;
            readerEmptyEl.classList.remove('d-none');
        }
        if (readerCarouselEl) {
            readerCarouselEl.classList.add('d-none');
        }
        if (readerThumbsEl) {
            readerThumbsEl.innerHTML = '';
        }
        if (readerWorkspaceEl) {
            readerWorkspaceEl.classList.add('d-none');
        }
        if (readerPageSelect) {
            readerPageSelect.innerHTML = '';
            readerPageSelect.disabled = true;
        }
        if (readerPrevBtn) readerPrevBtn.disabled = true;
        if (readerNextBtn) readerNextBtn.disabled = true;
        if (readerImageEl) {
            readerImageEl.removeAttribute('src');
            readerImageEl.alt = 'Fac-similé synchronisé';
        }
        if (readerImageMetaEl) readerImageMetaEl.textContent = '';
        if (readerTextMetaEl) readerTextMetaEl.textContent = '';
        if (readerTextEl) readerTextEl.textContent = '';
        applyReaderTextSourceControl();
        applyReaderEncodingControl();
    }

    function renderReaderThumbs() {
        if (!readerThumbsEl) return;
        const independentNavigation = readerUsesIndependentImageNavigation();
        const previewItems = independentNavigation
            ? (Array.isArray(readerData?.facsimiles) ? readerData.facsimiles : [])
                .filter(image => image?.big)
                .map((image, index) => ({
                    targetIndex: index,
                    image,
                    label: image?.image_code ? `Image ${image.image_code}` : (image?.name || `Image ${index + 1}`),
                    line: null,
                    isCurrent: index === readerImageIndex,
                }))
            : readerPages
                .filter(page => page?.image?.big)
                .map((page, index) => ({
                    targetIndex: page.index ?? index,
                    image: page.image || {},
                    label: page.label || page?.image?.name || page?.image?.image_code || 'Fac-similé',
                    line: page.line,
                    isCurrent: (page.index ?? index) === readerPageIndex,
                }));

        if (!previewItems.length) {
            readerThumbsEl.innerHTML = '';
            if (readerCarouselEl) {
                readerCarouselEl.classList.add('d-none');
            }
            return;
        }

        if (readerCarouselEl) {
            readerCarouselEl.classList.remove('d-none');
        }

        readerThumbsEl.innerHTML = previewItems.map((item) => {
            const image = item.image || {};
            const thumbSrc = image.thumb || image.big;
            const label = item.label || image.name || image.image_code || 'Fac-similé';
            const meta = [];
            const targetIndex = item.targetIndex;
            const isCurrent = item.isCurrent;
            if (item.line) meta.push(`ligne ${item.line}`);
            if (image.size_human) meta.push(image.size_human);
            return `
                <article class="facsimile-reader-thumb-card ${isCurrent ? 'is-current' : ''}" data-page-index="${targetIndex}">
                    <button type="button" class="facsimile-reader-thumb-btn" data-page-index="${targetIndex}" aria-pressed="${isCurrent ? 'true' : 'false'}">
                        <img src="${thumbSrc}" alt="${label}" class="facsimile-reader-thumb-image">
                    </button>
                    <div class="facsimile-reader-thumb-caption">
                        <div>${label}</div>
                        ${meta.length ? `<div class="text-muted">${meta.join(' · ')}</div>` : ''}
                    </div>
                </article>
            `;
        }).join('');

        readerThumbsEl.querySelectorAll('[data-page-index]').forEach((node) => {
            node.addEventListener('click', () => {
                const targetIndex = Number(node.getAttribute('data-page-index'));
                if (Number.isFinite(targetIndex)) {
                    if (independentNavigation) {
                        readerImageIndex = Math.max(0, Math.min(targetIndex, (readerData?.facsimiles?.length || 1) - 1));
                        renderReaderPage(readerPageIndex);
                    } else {
                        renderReaderPage(targetIndex);
                    }
                }
            });
        });
        scrollCurrentThumbIntoView();
    }

    function scrollCurrentThumbIntoView() {
        if (!readerThumbsEl) return;
        const currentThumb = readerThumbsEl.querySelector('.facsimile-reader-thumb-card.is-current');
        currentThumb?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }

    function buildReaderPages(payload) {
        if (Array.isArray(payload?.pages) && payload.pages.length) {
            return payload.pages.map((page, index) => ({
                index,
                label: String(page?.label || `Repère ${index + 1}`),
                image: page?.image || null,
                text: String(page?.text || ''),
                start: Number(page?.start ?? 0),
                end: Number(page?.end ?? 0),
                line: Number.isFinite(Number(page?.line)) ? Number(page.line) : null,
                imageCode: normalizeReaderCode(page?.imageCode ?? page?.image?.image_code ?? page?.image?.name ?? null),
                anchorOffset: Number.isFinite(Number(page?.anchorOffset)) ? Number(page.anchorOffset) : null,
                anchorPhrase: page?.anchorPhrase ? String(page.anchorPhrase) : null,
                guessed: page?.guessed === true,
                loaded: typeof page?.text === 'string',
            }));
        }

        const text = String(payload?.text || '');
        const markers = Array.isArray(payload?.markers) ? payload.markers.slice() : [];
        const facsimiles = Array.isArray(payload?.facsimiles) ? payload.facsimiles.slice() : [];
        if (!text || !markers.length) {
            return [];
        }

        const normalizedMarkers = markers
            .map(marker => ({
                char_index: Math.max(0, Number(marker?.char_index ?? 0)),
                image_code: normalizeReaderCode(marker?.image_code ?? marker?.page ?? null),
                raw_image_code: marker?.image_code ?? null,
                page_label: String(marker?.page || marker?.image_code || '').trim(),
                line: Number.isFinite(Number(marker?.line)) ? Number(marker.line) : null,
            }))
            .sort((a, b) => a.char_index - b.char_index);

        const imageByCode = new Map();
        facsimiles.forEach(image => {
            const code = normalizeReaderCode(image?.image_code ?? image?.name ?? null);
            if (code && !imageByCode.has(code)) {
                imageByCode.set(code, image);
            }
        });

        const exactMatches = normalizedMarkers.reduce((count, marker) => {
            return count + (marker.image_code && imageByCode.has(marker.image_code) ? 1 : 0);
        }, 0);
        const useSequentialFallback = exactMatches === 0 && facsimiles.length === normalizedMarkers.length;

        return normalizedMarkers.map((marker, index) => {
            const start = index === 0 ? 0 : Math.max(0, Math.min(text.length, marker.char_index));
            const nextChar = index < normalizedMarkers.length - 1
                ? Math.max(start, Math.min(text.length, normalizedMarkers[index + 1].char_index))
                : text.length;
            const segment = text.slice(start, nextChar).trim();
            const image = marker.image_code && imageByCode.has(marker.image_code)
                ? imageByCode.get(marker.image_code)
                : (useSequentialFallback ? facsimiles[index] ?? null : null);
            const label = marker.page_label || (image?.image_code ? `p. ${image.image_code}` : `Repère ${index + 1}`);

            return {
                index,
                label,
                image,
                text: segment || text.slice(start, nextChar),
                start,
                end: nextChar,
                line: marker.line,
                imageCode: marker.image_code || normalizeReaderCode(image?.name ?? null),
                anchorOffset: 0,
                anchorPhrase: marker.page_label || null,
                guessed: false,
                loaded: true,
            };
        });
    }

    function mergeReaderPage(index, pagePayload) {
        if (!readerPages[index] || !pagePayload) return null;
        const merged = {
            ...readerPages[index],
            ...pagePayload,
            loaded: true,
            guessed: pagePayload?.guessed === true || readerPages[index]?.guessed === true,
        };
        readerPages[index] = merged;
        return merged;
    }

    function prefetchReaderImage(page) {
        const url = page?.image?.big;
        if (!url || readerImagePrefetchUrls.has(url)) return;
        readerImagePrefetchUrls.add(url);
        const img = new Image();
        img.src = url;
    }

    async function loadReaderPage(index, { silent = false, useRequestToken = true } = {}) {
        if (!currentVersionId || !readerPages[index] || readerPages[index].loaded) {
            return readerPages[index] || null;
        }

        if (readerPageLoadPromises.has(index)) {
            return readerPageLoadPromises.get(index);
        }

        const requestToken = useRequestToken ? ++readerPageRequestToken : readerPageRequestToken;
        const params = new URLSearchParams({ index: String(index) });
        if (readerTextSource && readerTextSource !== 'auto') {
            params.set('text_source', readerTextSource);
        }
        if (readerEncoding && readerEncoding !== 'auto') {
            params.set('encoding', readerEncoding);
        }

        if (!silent && readerTextEl) {
            readerTextEl.textContent = 'Chargement du texte…';
        }
        if (!silent && readerTextMetaEl) {
            readerTextMetaEl.textContent = `${readerPages[index].label} · chargement`;
        }

        const promise = (async () => {
            try {
                const res = await fetch(withBasePath(`/api/versions/${currentVersionId}/reader/page?${params.toString()}`), {
                    headers: { Accept: 'application/json' }
                });
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }
                const payload = await res.json();
                if (useRequestToken && requestToken !== readerPageRequestToken) {
                    return null;
                }
                const merged = mergeReaderPage(index, payload?.page || null);
                if (payload?.text_source) {
                    readerTextSource = normalizeReaderTextSource(payload.text_source);
                }
                applyReaderTextSourceControl();
                applyReaderEncodingControl();
                return merged;
            } finally {
                readerPageLoadPromises.delete(index);
            }
        })();

        readerPageLoadPromises.set(index, promise);
        return promise;
    }

    function prefetchReaderPage(index) {
        if (readerUsesIndependentImageNavigation()) {
            const image = readerData?.facsimiles?.[index];
            if (!image) return;
            prefetchReaderImage({ image });
            return;
        }
        const page = readerPages[index];
        if (!page) return;
        prefetchReaderImage(page);
    }

    function cancelPendingReaderLoad() {
        if (readerLoadAbortController) {
            readerLoadAbortController.abort();
            readerLoadAbortController = null;
        }
        readerLoadingVersionId = null;
    }

    function renderReaderText(page) {
        if (!readerTextEl) return;

        const rawText = String(page?.text || '');
        if (!rawText.trim()) {
            readerTextEl.textContent = 'Aucun extrait textuel disponible pour ce repère.';
            return;
        }

        const anchorOffset = Number.isFinite(Number(page?.anchorOffset)) ? Number(page.anchorOffset) : null;
        const anchorLabel = page?.anchorPhrase
            ? `Repère : ${page.anchorPhrase}`
            : `Repère ${page?.label || ''}`.trim();

        if (anchorOffset === null || page?.guessed === true) {
            readerTextEl.textContent = rawText;
            return;
        }

        const safeOffset = Math.max(0, Math.min(rawText.length, anchorOffset));
        const before = escapeHtml(rawText.slice(0, safeOffset));
        const after = escapeHtml(rawText.slice(safeOffset));
        const marker = `<span class="facsimile-reader-anchor">${escapeHtml(anchorLabel)}</span>`;

        readerTextEl.innerHTML = `${before}${marker}${after}`;
    }

    async function renderReaderPage(index) {
        if (!readerPages.length) {
            resetReader('Aucun repère de pagination exploitable n’est disponible pour cette version.');
            return;
        }

        readerPageIndex = Math.min(Math.max(index, 0), readerPages.length - 1);
        let page = readerPages[readerPageIndex];
        if (!page) return;
        const independentNavigation = readerUsesIndependentImageNavigation();

        if (!page.loaded) {
            try {
                const loaded = await loadReaderPage(readerPageIndex);
                if (loaded) {
                    page = loaded;
                }
            } catch (err) {
                console.error('Could not load reader page', err);
                if (readerTextEl) {
                    readerTextEl.textContent = 'Impossible de charger cette page du lecteur.';
                }
                return;
            }
        }

        if (readerRoot) {
            readerRoot.classList.remove('d-none');
        }
        if (readerEmptyEl) {
            readerEmptyEl.classList.add('d-none');
        }
        if (readerCarouselEl) {
            readerCarouselEl.classList.toggle('d-none', !readerPages.length);
        }
        if (readerWorkspaceEl) {
            readerWorkspaceEl.classList.remove('d-none');
        }
        updateReaderCardTitle();
        applyReaderEncodingControl();
        if (readerPageSelect) {
            readerPageSelect.disabled = false;
            readerPageSelect.value = String(independentNavigation ? readerImageIndex : readerPageIndex);
        }
        if (readerPrevBtn) {
            readerPrevBtn.disabled = independentNavigation ? readerImageIndex <= 0 : readerPageIndex <= 0;
            readerPrevBtn.textContent = independentNavigation ? '‹ Image précédente' : '‹ Page précédente';
        }
        if (readerNextBtn) {
            const maxImageIndex = Math.max(0, (readerData?.facsimiles?.length || 1) - 1);
            readerNextBtn.disabled = independentNavigation ? readerImageIndex >= maxImageIndex : readerPageIndex >= readerPages.length - 1;
            readerNextBtn.textContent = independentNavigation ? 'Image suivante ›' : 'Page suivante ›';
        }

        const displayedImage = currentDisplayedReaderImage();

        if (readerImageEl) {
            if (displayedImage?.big) {
                readerImageEl.onload = () => {
                    applyReaderCropDisplay();
                };
                readerImageEl.src = displayedImage.big;
                readerImageEl.alt = displayedImage?.name || page.label;
            } else {
                readerImageEl.removeAttribute('src');
                readerImageEl.alt = 'Fac-similé manquant';
            }
        }

        if (readerImageMetaEl) {
            if (displayedImage) {
                const parts = [];
                if (displayedImage.image_code) {
                    parts.push(`image ${displayedImage.image_code}`);
                } else if (page.label) {
                    parts.push(page.label);
                }
                if (independentNavigation && Array.isArray(readerData?.facsimiles) && readerData.facsimiles.length > 1) {
                    parts.push(`image ${readerImageIndex + 1}/${readerData.facsimiles.length}`);
                }
                if (displayedImage.size_human) parts.push(displayedImage.size_human);
                if (displayedImage.width && displayedImage.height) parts.push(`${displayedImage.width}×${displayedImage.height}px`);
                readerImageMetaEl.textContent = parts.join(' · ');
            } else {
                readerImageMetaEl.textContent = `Aucun fac-similé correspondant pour ${page.label}.`;
            }
        }

        if (readerTextMetaEl) {
            const textParts = [];
            if (!readerData?.pagination?.available) {
                textParts.push('texte intégral');
            } else {
                textParts.push(page?.guessed === true ? 'approximation' : 'extrait aligné');
            }
            if (page.label) textParts.push(page.label);
            if (page.line) textParts.push(`ligne ${page.line}`);
            if (readerData?.text_source_label) textParts.push(readerData.text_source_label);
            const segmentLength = Math.max(0, page.end - page.start);
            textParts.push(`${segmentLength.toLocaleString('fr-FR')} signes`);
            readerTextMetaEl.textContent = textParts.join(' · ');
        }
        renderReaderText(page);
        if (readerTextEl) readerTextEl.scrollTop = 0;
        syncReaderCropForCurrentPage();
        renderReaderThumbs();
        prefetchReaderPage(independentNavigation ? readerImageIndex + 1 : readerPageIndex + 1);
    }

    function renderReader(payload) {
        readerData = payload;
        readerTextSource = normalizeReaderTextSource(payload?.text_source);
        readerPageLoadPromises.clear();
        readerImagePrefetchUrls.clear();
        readerPages = buildReaderPages(payload);
        const initialPageIndex = Number.isFinite(Number(payload?.current_page_index)) ? Number(payload.current_page_index) : 0;
        if (payload?.current_page && readerPages[initialPageIndex]) {
            mergeReaderPage(initialPageIndex, payload.current_page);
        }
        readerImageIndex = 0;
        if (payload?.current_page?.image && Array.isArray(payload?.facsimiles)) {
            const currentImageName = String(payload.current_page.image.name || '');
            const currentIndex = payload.facsimiles.findIndex((image) => String(image?.name || '') === currentImageName);
            if (currentIndex >= 0) {
                readerImageIndex = currentIndex;
            }
        }

        if (readerPageSelect) {
            readerPageSelect.innerHTML = '';
            if (!payload?.pagination?.available && Array.isArray(payload?.facsimiles) && payload.facsimiles.length) {
                payload.facsimiles.forEach((image, index) => {
                    const label = image?.image_code
                        ? `${index + 1}. Image ${image.image_code}`
                        : `${index + 1}. ${image?.name || 'Image'}`;
                    readerPageSelect.appendChild(new Option(label, String(index)));
                });
            } else {
                readerPages.forEach((page, index) => {
                    const option = new Option(`${index + 1}. ${page.label}`, String(index));
                    readerPageSelect.appendChild(option);
                });
            }
        }

        if (!payload?.text_available) {
            resetReader('Le fichier texte de cette version est indisponible pour le lecteur synchronisé.');
            return;
        }

        updateReaderCardTitle();
        applyReaderTextSourceControl();
        renderReaderPage(initialPageIndex);
    }

    function resetGallery(message = '') {
        galleryFiles = [];
        galleryPage  = 1;
        gallery.innerHTML = message ? `<div class="text-muted">${message}</div>` : '';
        galleryPager.innerHTML = '';
        galleryMeta.textContent = '';
    }

    function renderGallery(files) {
        galleryFiles = Array.isArray(files) ? files : [];
        galleryPage = 1;
        updateGallery();
        renderReaderThumbs();
    }

    function resetManifestControls({ hideManager = false, summary = '' } = {}) {
        manifestOptions = [];
        manifestActiveKey = null;
        manifestSelectedSet = new Set();
        manifestOriginalSet = new Set();
        manifestReadOnly = false;
        manifestOptionElements.clear();
        if (manifestSelect) {
            manifestSelect.innerHTML = '<option value="">Associer une comparaison…</option>';
            manifestSelect.disabled = true;
            manifestSelect.value = '';
        }
        if (manifestList) {
            manifestList.innerHTML = hideManager
                ? ''
                : '<div class="text-muted small">Sélectionnez une comparaison pour personnaliser son manifeste.</div>';
        }
        if (manifestManager) {
            manifestManager.classList.toggle('d-none', hideManager);
        }
        if (manifestSummary) {
            const defaultSummary = hideManager
                ? ''
                : 'Choisissez une comparaison pour préparer le manifeste JSON.';
            manifestSummary.textContent = summary || defaultSummary;
        }
        updateManifestButtons();
        updateGallery();
    }

    function updateManifestButtons() {
        const hasActive = !!manifestActiveKey;
        const hasChanges = hasActive && !setsEqual(manifestSelectedSet, manifestOriginalSet);
        if (manifestSaveBtn) {
            manifestSaveBtn.disabled = manifestReadOnly || !hasActive || !hasChanges || manifestBusy;
            manifestSaveBtn.title = manifestReadOnly
                ? 'Lecture seule (legacy) — modifications désactivées'
                : '';
        }
        if (manifestCancelBtn) {
            manifestCancelBtn.disabled = manifestReadOnly || !hasActive || !hasChanges || manifestBusy;
            manifestCancelBtn.title = manifestReadOnly
                ? 'Lecture seule (legacy) — modifications désactivées'
                : '';
        }
    }

    function updateManifestSummary(option) {
        if (!manifestSummary) return;
        if (!manifestActiveKey || !option) {
            manifestSummary.textContent = '';
            return;
        }
        const count = manifestSelectedSet.size;
        const roleLabel = option.role_label ? option.role_label.toLowerCase() : (option.role === 'source' ? 'source' : 'cible');
        let message = `${count} image${count === 1 ? '' : 's'} sélectionnée${count === 1 ? '' : 's'} pour le ${roleLabel}.`;
        if (!option.exists && option.inferred) {
            message += ' Manifeste non enregistré — sélection par défaut.';
        } else if (option.updated_at) {
            const date = new Date(option.updated_at * 1000);
            message += ` Dernière mise à jour : ${date.toLocaleString('fr-FR', { hour12: false })}.`;
        }
        if (manifestReadOnly) {
            message += ' Mode lecture seule (legacy).';
        }
        manifestSummary.textContent = message;
    }

    function renderManifestList(activeKey = manifestActiveKey) {
        if (!manifestList) {
            return;
        }
        manifestList.innerHTML = '';
        if (!manifestOptions.length) {
            manifestList.innerHTML = '<div class="text-muted small">Aucune comparaison Medite associée à cette version.</div>';
            return;
        }
        manifestOptions.forEach(opt => {
            const key = manifestOptionKey(opt.comparison_id, opt.role);
            const isActive = key === activeKey;
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.dataset.manifestKey = key;
            pill.className = [
                'btn',
                'btn-sm',
                'manifest-pill',
                isActive ? 'btn-primary' : 'btn-outline-secondary',
            ].join(' ');
            pill.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            pill.textContent = buildManifestOptionLabel(opt);
            if (opt.file) {
                pill.title = opt.file;
            }
            pill.addEventListener('click', () => {
                if (manifestSelect) {
                    manifestSelect.value = key;
                }
                applyManifestOption(key);
            });
            manifestList.appendChild(pill);
        });
    }

    function applyManifestOption(key) {
        const option = getManifestOption(key);
        if (!option) {
            manifestActiveKey = null;
            manifestSelectedSet = new Set();
            manifestOriginalSet = new Set();
            manifestReadOnly = false;
            renderManifestList(null);
            updateGallery();
            updateManifestSummary(null);
            updateManifestButtons();
            return;
        }
        manifestReadOnly = !!option.read_only;
        manifestActiveKey = key;
        const selected = Array.isArray(option.selected) ? option.selected : [];
        manifestSelectedSet = new Set(selected);
        manifestOriginalSet = new Set(selected);
        if (manifestSelect) {
            manifestSelect.value = key;
        }
        if (manifestManager) {
            manifestManager.classList.remove('d-none');
        }
        renderManifestList(key);
        updateGallery();
        updateManifestSummary(option);
        updateManifestButtons();
    }

    function attachManifestEvents() {
        if (!manifestActiveKey || manifestReadOnly) return;
        const option = getManifestOption(manifestActiveKey);
        if (!option) return;

        gallery.querySelectorAll('.manifest-toggle').forEach(input => {
            input.addEventListener('change', event => {
                const name = event.target.dataset.file;
                if (!name) return;
                if (event.target.checked) {
                    manifestSelectedSet.add(name);
                    event.target.closest('.fac-item')?.classList.add('fac-item-selected');
                } else {
                    manifestSelectedSet.delete(name);
                    event.target.closest('.fac-item')?.classList.remove('fac-item-selected');
                }
                updateManifestButtons();
                updateManifestSummary(option);
            });
        });

        gallery.querySelectorAll('.fac-item-selectable').forEach(item => {
            item.addEventListener('click', event => {
                if (!manifestActiveKey) return;
                event.preventDefault();
                const checkbox = item.querySelector('.manifest-toggle');
                if (!checkbox) return;
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    function focusManifest(key) {
        if (!key) return false;
        if (manifestOptionElements.has(key)) {
            applyManifestOption(key);
            const option = getManifestOption(key);
            updateManifestSummary(option);
            if (manifestManager) {
                manifestManager.classList.remove('d-none');
                if (typeof window.openEditorialStep === 'function') {
                    window.openEditorialStep(2, { focusPanel: false, scrollToJourney: false });
                }
            }
            pendingManifestFocus = null;
            return true;
        }
        pendingManifestFocus = key;
        return false;
    }

    function cancelPendingManifestLoad() {
        if (manifestLoadAbortController) {
            manifestLoadAbortController.abort();
            manifestLoadAbortController = null;
        }
    }

    async function loadManifestOptions(versionId, { preserveSelection = false, focusKey = null } = {}) {
        manifestRequestToken++;
        const requestId = manifestRequestToken;
        cancelPendingManifestLoad();
        const abortController = new AbortController();
        manifestLoadAbortController = abortController;
        const previousKey = preserveSelection ? manifestActiveKey : null;

        if (!preserveSelection) {
            manifestActiveKey = null;
            manifestSelectedSet = new Set();
            manifestOriginalSet = new Set();
            updateGallery();
        }

        if (!versionId) {
            resetManifestControls({ hideManager: true });
            setFacsimilesLoading(false);
            return;
        }
        bumpFacsimilesLoading(1);

        if (manifestManager) {
            manifestManager.classList.remove('d-none');
        }
        if (manifestSelect) {
            manifestSelect.disabled = true;
            manifestSelect.value = '';
        }
        if (manifestSummary) {
            manifestSummary.textContent = 'Chargement des comparaisons…';
        }
        if (manifestList) {
            manifestList.innerHTML = '<div class="text-muted small">Chargement…</div>';
        }

        try {
            const res = await fetch(withBasePath(`/api/versions/${versionId}/comparisons`), {
                headers: { 'Accept': 'application/json' },
                signal: abortController.signal,
            });
            const data = await res.json();
            if (requestId !== manifestRequestToken) return;

            manifestOptions = Array.isArray(data) ? data : [];
            manifestOptionElements.clear();
            manifestReadOnly = manifestOptions.some(opt => opt?.read_only);

            if (!manifestOptions.length) {
                manifestActiveKey = null;
                manifestSelectedSet = new Set();
                manifestOriginalSet = new Set();
                manifestReadOnly = false;
                if (manifestSelect) {
                    manifestSelect.innerHTML = '<option value="">Aucune comparaison disponible</option>';
                    manifestSelect.value = '';
                    manifestSelect.disabled = true;
                }
                renderManifestList(null);
                if (manifestSummary) {
                    manifestSummary.textContent = 'Aucune comparaison Medite n’est associée à cette version.';
                }
                updateManifestButtons();
                pendingManifestFocus = null;
                return;
            }

            if (manifestSelect) {
                manifestSelect.innerHTML = '';
                const placeholder = new Option('Associer une comparaison…', '');
                manifestSelect.appendChild(placeholder);

                manifestOptions.forEach(opt => {
                    const value = manifestOptionKey(opt.comparison_id, opt.role);
                    const optionEl = new Option(buildManifestOptionLabel(opt), value);
                    manifestOptionElements.set(value, optionEl);
                    manifestSelect.appendChild(optionEl);
                });
                manifestSelect.disabled = false;
            }

            renderManifestList(previousKey);

            const reapplyKey = previousKey && manifestOptionElements.has(previousKey) ? previousKey : null;
            const desiredKey = [focusKey, pendingManifestFocus, reapplyKey].find(key => key && manifestOptionElements.has(key));
            if (desiredKey) {
                focusManifest(desiredKey);
            } else {
                manifestActiveKey = null;
                manifestSelectedSet = new Set();
                manifestOriginalSet = new Set();
                manifestReadOnly = manifestOptions.some(opt => opt?.read_only);
                if (manifestSelect) manifestSelect.value = '';
                updateGallery();
                updateManifestSummary(null);
                if (manifestSummary) {
                    const baseSummary = 'Sélectionnez une comparaison pour personnaliser son manifeste.';
                    manifestSummary.textContent = manifestReadOnly
                        ? `${baseSummary} Mode lecture seule (legacy).`
                        : baseSummary;
                }
                renderManifestList(null);
                updateManifestButtons();
                pendingManifestFocus = null;
            }
        } catch (err) {
            if (err?.name === 'AbortError') {
                return;
            }
            console.error('Could not load manifest options', err);
            if (requestId === manifestRequestToken) {
                resetManifestControls({ summary: 'Impossible de charger les manifestes pour cette version.' });
                if (manifestList) {
                    manifestList.innerHTML = '<div class="text-danger small">Erreur lors du chargement des manifestes.</div>';
                }
            }
        } finally {
            if (requestId === manifestRequestToken) {
                manifestLoadAbortController = null;
                updateManifestButtons();
            }
            bumpFacsimilesLoading(-1);
        }
    }

    async function saveManifestSelection() {
        if (manifestReadOnly || !manifestActiveKey || !currentVersionId) {
            return;
        }
        const option = getManifestOption(manifestActiveKey);
        if (!option) {
            return;
        }

        const [comparisonIdStr, role] = manifestActiveKey.split(':');
        const comparisonId = Number(comparisonIdStr);
        if (!Number.isFinite(comparisonId)) {
            return;
        }

        const payload = {
            role,
            images: Array.from(manifestSelectedSet),
        };

        const originalSaveLabel = manifestSaveBtn ? manifestSaveBtn.innerHTML : '';

        manifestBusy = true;
        updateManifestButtons();
        if (manifestSaveBtn) {
            manifestSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        }
        if (manifestSelect) manifestSelect.disabled = true;
        if (manifestSummary) manifestSummary.textContent = 'Enregistrement en cours…';

        try {
            const res = await fetch(withBasePath(`/api/versions/${currentVersionId}/manifests/${comparisonId}`), {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            const text = await res.text();
            let data = {};
            if (text) {
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    console.error('Invalid JSON response', err, text);
                }
            }
            if (!res.ok) {
                const message = data?.message || data?.error || `HTTP ${res.status}`;
                throw new Error(message);
            }

            const updatedSelected = Array.isArray(data.selected) ? data.selected : Array.from(manifestSelectedSet);
            manifestSelectedSet = new Set(updatedSelected);
            manifestOriginalSet = new Set(updatedSelected);

            const optionRef = getManifestOption(manifestActiveKey);
            if (optionRef) {
                optionRef.selected = updatedSelected;
                optionRef.count = Number.isFinite(data.count) ? data.count : updatedSelected.length;
                optionRef.exists = data.exists !== undefined ? !!data.exists : true;
                optionRef.inferred = data.inferred !== undefined ? !!data.inferred : false;
                optionRef.file = data.file ?? optionRef.file ?? null;
                optionRef.updated_at = data.updated_at ?? optionRef.updated_at ?? Math.floor(Date.now() / 1000);
            }

            updateGallery();
            updateManifestSummary(optionRef || option);
            renderManifestList(manifestActiveKey);
            updateManifestButtons();

            const manifestDetail = {
                count: optionRef?.count ?? updatedSelected.length,
                exists: optionRef?.exists ?? true,
                file: optionRef?.file ?? null,
                updated_at: optionRef?.updated_at ?? null,
                inferred: optionRef?.inferred ?? false,
                selected: updatedSelected,
            };

            document.dispatchEvent(new CustomEvent('comparisonManifestUpdated', {
                detail: {
                    comparisonId,
                    role,
                    workId: currentWorkId ?? null,
                    versionId: currentVersionId ?? null,
                    versionName: currentVersionName ?? '',
                    count: manifestDetail.count,
                    manifest: manifestDetail,
                },
            }));
        } catch (err) {
            console.error('Manifest update failed', err);
            alert(err.message || 'Impossible de mettre à jour le manifeste.');
        } finally {
            manifestBusy = false;
            if (manifestSaveBtn) {
                manifestSaveBtn.innerHTML = originalSaveLabel || 'Enregistrer';
            }
            if (manifestSelect) manifestSelect.disabled = false;
            updateManifestButtons();
        }
    }

    function updateGallery() {
        if (!galleryFiles.length) {
            resetGallery('Aucun fac-similé pour cette version.');
            return;
        }

        const totalPages = Math.max(1, Math.ceil(galleryFiles.length / GALLERY_PAGE_SIZE));
        galleryPage = Math.min(Math.max(galleryPage, 1), totalPages);
        const startIndex = (galleryPage - 1) * GALLERY_PAGE_SIZE;
        const pageItems  = galleryFiles.slice(startIndex, startIndex + GALLERY_PAGE_SIZE);

        const manifestActive = !!manifestActiveKey;
        const manifestEditable = manifestActive && !manifestReadOnly;

        const markup = pageItems.map((f, idx) => {
            const metaParts = [];
            if (Number.isFinite(f.width) && Number.isFinite(f.height)) {
                metaParts.push(`${f.width}×${f.height}px`);
            }
            if (typeof f.size_human === 'string' && f.size_human) {
                metaParts.push(f.size_human);
            } else if (Number.isFinite(f.size_bytes)) {
                metaParts.push(formatBytes(f.size_bytes));
            }
            const metaHtml = metaParts.length
                ? `<div class="text-muted small text-center">${metaParts.join(' — ')}</div>`
                : '';

            const thumbWarning = !f.hasThumb
                ? '<div class="text-danger small text-center">⚠️ pas de miniature</div>'
                : '';
            const thumbSrc = f.thumb || f.big;
            const name = f.name || `file-${startIndex + idx}`;
            const isSelected = manifestActive && manifestSelectedSet.has(name);
            const checkboxId = `manifest-${startIndex + idx}`;

            const checkbox = manifestActive
                ? `<div class="form-check form-check-sm position-absolute top-0 start-0 m-1">
                        <input class="form-check-input manifest-toggle" type="checkbox" id="${checkboxId}" data-file="${name}" ${isSelected ? 'checked' : ''} ${manifestReadOnly ? 'disabled' : ''}>
                        <label class="visually-hidden" for="${checkboxId}">Associer ${name}</label>
                   </div>`
                : '';

            return `
            <div class="fac-item d-flex flex-column align-items-center ${manifestEditable ? 'fac-item-selectable' : ''} ${isSelected ? 'fac-item-selected' : ''}" data-file="${name}">
                <div class="position-relative mb-1">
                    ${checkbox}
                    <a href="${f.big}" target="_blank" rel="noopener" class="d-block">
                        <img src="${thumbSrc}"
                             alt="${name}"
                             class="border rounded fac-thumb">
                    </a>
                </div>
                <div class="fac-caption text-truncate text-center" title="${name}">
                    ${name}
                </div>
                ${metaHtml}
                ${thumbWarning}
            </div>`;
        }).join('');

        gallery.innerHTML = markup;

        galleryMeta.textContent = `${currentVersionName ? currentVersionName + ' — ' : ''}${galleryFiles.length} image(s) · page ${galleryPage}/${totalPages}`;

        attachManifestEvents();
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        if (totalPages <= 1) {
            galleryPager.innerHTML = '';
            return;
        }

        const buttons = [];
        buttons.push(`<button type="button" class="btn btn-sm btn-outline-secondary" data-page="prev" ${galleryPage === 1 ? 'disabled' : ''}>‹</button>`);

        for (let p = 1; p <= totalPages; p++) {
            if (p === 1 || p === totalPages || Math.abs(p - galleryPage) <= 2) {
                buttons.push(`<button type="button" class="btn btn-sm ${p === galleryPage ? 'btn-primary' : 'btn-outline-secondary'}" data-page="${p}">${p}</button>`);
            } else if (buttons[buttons.length - 1] !== '…') {
                buttons.push('…');
            }
        }

        buttons.push(`<button type="button" class="btn btn-sm btn-outline-secondary" data-page="next" ${galleryPage === totalPages ? 'disabled' : ''}>›</button>`);

        galleryPager.innerHTML = buttons.map(btn => btn === '…' ? '<span class="px-2">…</span>' : btn).join('');

        galleryPager.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-page');
                if (target === 'prev') galleryPage = Math.max(1, galleryPage - 1);
                else if (target === 'next') galleryPage = Math.min(totalPages, galleryPage + 1);
                else galleryPage = Number(target);
                updateGallery();
            });
        });
    }

    async function loadGallery(versionId, versionName = '') {
        if (!versionId) {
            setWorkspaceState(false);
            resetGallery();
            resetReader();
            setFacsimilesLoading(false);
            return;
        }

        setWorkspaceState(true);
        setStatus(`Chargement des fac-similés pour ${versionName || 'cette version'}…`);
        resetGallery('Chargement…');
        bumpFacsimilesLoading(1);

        try {
            const res = await fetch(withBasePath(`/api/facsimiles?version_id=${versionId}`), {
                headers: { Accept: 'application/json' }
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const files = await res.json();
            if (!Array.isArray(files) || !files.length) {
                setStatus(`Aucun fac-similé pour ${versionName || 'cette version'}.`, 'muted');
                resetGallery('Aucun fac-similé pour cette version.');
                return;
            }
            const label = versionName || 'cette version';
            setStatus(`Fac-similés pour ${label}.`, 'muted');
            renderGallery(files);
        } catch (err) {
            console.error(err);
            const detail = err?.message ? ` (${err.message})` : '';
            setStatus(`Erreur lors du chargement des fac-similés${detail}.`, 'danger');
            resetGallery('Impossible de charger les fac-similés.');
        } finally {
            bumpFacsimilesLoading(-1);
        }
    }

    async function loadReader(versionId) {
        if (!versionId) {
            cancelPendingReaderLoad();
            resetReader();
            return;
        }

        if (readerLoadingVersionId === versionId && readerLoadAbortController) {
            return;
        }

        cancelPendingReaderLoad();
        const requestToken = ++readerLoadRequestToken;
        const abortController = new AbortController();
        readerLoadAbortController = abortController;
        readerLoadingVersionId = versionId;

        resetReader();
        if (readerRoot) {
            readerRoot.classList.remove('d-none');
        }
        setReaderLoading(true);
        bumpFacsimilesLoading(1);

        try {
            const params = new URLSearchParams();
            if (readerTextSource && readerTextSource !== 'auto') {
                params.set('text_source', readerTextSource);
            }
            if (readerEncoding && readerEncoding !== 'auto') {
                params.set('encoding', readerEncoding);
            }
            const suffix = params.toString() ? `?${params.toString()}` : '';
            const res = await fetch(withBasePath(`/api/versions/${versionId}/reader${suffix}`), {
                headers: { Accept: 'application/json' },
                signal: abortController.signal,
            });
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }
            const payload = await res.json();
            if (requestToken !== readerLoadRequestToken) {
                return;
            }
            renderReader(payload);
        } catch (err) {
            if (err?.name === 'AbortError') {
                return;
            }
            console.error('Could not load synchronized reader', err);
            resetReader('Impossible de charger le lecteur synchronisé pour cette version.');
            if (readerRoot) {
                readerRoot.classList.remove('d-none');
            }
        } finally {
            if (requestToken === readerLoadRequestToken) {
                readerLoadAbortController = null;
                readerLoadingVersionId = null;
            }
            setReaderLoading(false);
            applyReaderRebuildControl();
            bumpFacsimilesLoading(-1);
        }
    }

    async function processFacsimileSelection(versionId, versionName) {
        currentVersionId = versionId || null;
        currentVersionName = versionName || '';
        updateReaderCardTitle();
        readerTextSource = loadReaderTextSourcePreference(currentVersionId);
        readerEncoding = loadReaderEncodingPreference(currentVersionId);
        resetManifestControls();

        if (!currentVersionId) {
            cancelPendingReaderLoad();
            cancelPendingManifestLoad();
            setWorkspaceState(false);
            setStatus('');
            resetGallery();
            resetReader();
            return;
        }

        setWorkspaceState(true);
        await Promise.allSettled([
            loadReader(currentVersionId),
            loadManifestOptions(currentVersionId, { focusKey: pendingManifestFocus }),
        ]);
    }

    async function scheduleFacsimileSelection(versionId, versionName) {
        const normalizedVersionId = versionId || null;
        const normalizedVersionName = versionName || '';
        const sameSelection = String(currentVersionId || '') === String(normalizedVersionId || '');

        if (facsimileSelectionInFlight) {
            pendingFacsimileSelection = {
                versionId: normalizedVersionId,
                versionName: normalizedVersionName,
            };
            return;
        }

        if (sameSelection && !readerLoadingVersionId && !manifestLoadAbortController) {
            setWorkspaceState(!!normalizedVersionId);
            return;
        }

        facsimileSelectionInFlight = true;
        document.dispatchEvent(new CustomEvent('facsimiles:selection-loading', {
            detail: { loading: true, versionId: normalizedVersionId }
        }));

        try {
            await processFacsimileSelection(normalizedVersionId, normalizedVersionName);
        } finally {
            facsimileSelectionInFlight = false;
            document.dispatchEvent(new CustomEvent('facsimiles:selection-loading', {
                detail: { loading: false, versionId: normalizedVersionId }
            }));
            if (pendingFacsimileSelection) {
                const next = pendingFacsimileSelection;
                pendingFacsimileSelection = null;
                const nextIsSame = String(currentVersionId || '') === String(next.versionId || '');
                if (!nextIsSame || readerLoadingVersionId || manifestLoadAbortController) {
                    void scheduleFacsimileSelection(next.versionId, next.versionName);
                }
            }
        }
    }

    document.addEventListener('workSelected', e => {
        currentWorkId     = e.detail?.workId ?? null;
        currentVersionId  = null;
        currentVersionName= '';
        readerTextSource  = 'auto';
        readerEncoding    = 'auto';
        pendingFacsimileSelection = null;
        facsimileSelectionInFlight = false;
        cancelPendingReaderLoad();
        cancelPendingManifestLoad();
        resetManifestControls({ hideManager: true });
        setWorkspaceState(false);
        setStatus('');
        resetGallery();
        resetReader();
        setFacsimilesLoading(false);
    });

    document.addEventListener('facsimiles:select', e => {
        const { versionId, versionName } = e.detail || {};
        void scheduleFacsimileSelection(versionId || null, versionName || '');
    });

    document.addEventListener('facsimilesUploaded', e => {
        if (currentVersionId && e.detail?.versionId === currentVersionId) {
            loadReader(currentVersionId);
            loadManifestOptions(currentVersionId, { preserveSelection: true });
        }
    });

    if (manifestSelect) {
        manifestSelect.addEventListener('change', () => {
            const value = manifestSelect.value;
            if (!value) {
                manifestActiveKey = null;
                manifestSelectedSet = new Set();
                manifestOriginalSet = new Set();
                updateGallery();
                updateManifestSummary(null);
                updateManifestButtons();
                return;
            }
            applyManifestOption(value);
        });
    }

    if (manifestSaveBtn) {
        manifestSaveBtn.addEventListener('click', saveManifestSelection);
    }

    if (manifestCancelBtn) {
        manifestCancelBtn.addEventListener('click', () => {
            if (!manifestActiveKey) return;
            manifestSelectedSet = new Set(manifestOriginalSet);
            updateGallery();
            const option = getManifestOption(manifestActiveKey);
            updateManifestSummary(option);
            updateManifestButtons();
        });
    }

    if (readerPageSelect) {
        readerPageSelect.addEventListener('change', () => {
            const target = Number(readerPageSelect.value);
            if (Number.isFinite(target)) {
                if (readerUsesIndependentImageNavigation()) {
                    readerImageIndex = Math.max(0, Math.min(target, (readerData?.facsimiles?.length || 1) - 1));
                    renderReaderPage(readerPageIndex);
                } else {
                    renderReaderPage(target);
                }
            }
        });
    }
    if (readerPrevBtn) {
        readerPrevBtn.addEventListener('click', () => {
            if (readerUsesIndependentImageNavigation()) {
                readerImageIndex = Math.max(0, readerImageIndex - 1);
                renderReaderPage(readerPageIndex);
            } else {
                renderReaderPage(readerPageIndex - 1);
            }
        });
    }

    if (readerNextBtn) {
        readerNextBtn.addEventListener('click', () => {
            if (readerUsesIndependentImageNavigation()) {
                const maxIndex = Math.max(0, (readerData?.facsimiles?.length || 1) - 1);
                readerImageIndex = Math.min(maxIndex, readerImageIndex + 1);
                renderReaderPage(readerPageIndex);
            } else {
                renderReaderPage(readerPageIndex + 1);
            }
        });
    }

    if (readerFitAutoBtn) {
        readerFitAutoBtn.addEventListener('click', () => setReaderFitMode('auto'));
    }
    if (readerFitWidthBtn) {
        readerFitWidthBtn.addEventListener('click', () => setReaderFitMode('width'));
    }
    if (readerFitHeightBtn) {
        readerFitHeightBtn.addEventListener('click', () => setReaderFitMode('height'));
    }
    if (readerFitNaturalBtn) {
        readerFitNaturalBtn.addEventListener('click', () => setReaderFitMode('natural'));
    }

    if (readerCropSetBtn) {
        readerCropSetBtn.addEventListener('click', () => {
            if (!currentReaderImageName() || !readerImageEl?.src) return;
            readerCropMode = true;
            readerCropDraft = null;
            readerCropImageRect = null;
            updateReaderCropControls();
            applyReaderCropDisplay();
        });
    }

    if (readerCropClearBtn) {
        readerCropClearBtn.addEventListener('click', () => {
            const imageName = currentReaderImageName();
            if (!imageName) return;
            clearStoredReaderCrop(currentVersionId, imageName);
            readerCurrentCrop = null;
            updateReaderCropControls();
            applyReaderCropDisplay();
        });
    }

    if (readerCropOverlayEl && readerCropViewportEl) {
        const updateCropRect = (draft) => {
            if (!readerCropRectEl || !draft) return;
            readerCropRectEl.classList.remove('d-none');
            readerCropRectEl.style.left = `${draft.left}px`;
            readerCropRectEl.style.top = `${draft.top}px`;
            readerCropRectEl.style.width = `${draft.width}px`;
            readerCropRectEl.style.height = `${draft.height}px`;
        };

        const clearCropDraft = () => {
            readerCropDraft = null;
            readerCropImageRect = null;
            hideReaderCropOverlay();
            if (readerCropMode) {
                readerCropOverlayEl?.classList.remove('d-none');
            }
        };

        readerCropOverlayEl.addEventListener('mousedown', (event) => {
            if (!readerCropMode || event.button !== 0 || !readerImageEl) return;
            const imageRect = readerImageEl.getBoundingClientRect();
            if (imageRect.width <= 0 || imageRect.height <= 0) return;
            if (
                event.clientX < imageRect.left || event.clientX > imageRect.right ||
                event.clientY < imageRect.top || event.clientY > imageRect.bottom
            ) {
                return;
            }

            const overlayRect = readerCropOverlayEl.getBoundingClientRect();
            readerCropImageRect = imageRect;
            readerCropDraft = {
                startX: event.clientX,
                startY: event.clientY,
                overlayLeft: overlayRect.left,
                overlayTop: overlayRect.top,
                left: event.clientX - overlayRect.left,
                top: event.clientY - overlayRect.top,
                width: 0,
                height: 0,
            };
            updateCropRect(readerCropDraft);
            event.preventDefault();
        });

        window.addEventListener('mousemove', (event) => {
            if (!readerCropMode || !readerCropDraft || !readerCropImageRect) return;
            const imageRect = readerCropImageRect;
            const currentX = Math.min(Math.max(event.clientX, imageRect.left), imageRect.right);
            const currentY = Math.min(Math.max(event.clientY, imageRect.top), imageRect.bottom);
            const startX = Math.min(Math.max(readerCropDraft.startX, imageRect.left), imageRect.right);
            const startY = Math.min(Math.max(readerCropDraft.startY, imageRect.top), imageRect.bottom);
            const left = Math.min(startX, currentX) - readerCropDraft.overlayLeft;
            const top = Math.min(startY, currentY) - readerCropDraft.overlayTop;
            const width = Math.abs(currentX - startX);
            const height = Math.abs(currentY - startY);
            readerCropDraft = { ...readerCropDraft, left, top, width, height };
            updateCropRect(readerCropDraft);
        });

        window.addEventListener('mouseup', () => {
            if (!readerCropMode || !readerCropDraft || !readerCropImageRect) return;
            const imageRect = readerCropImageRect;
            const minSize = 12;
            if (readerCropDraft.width < minSize || readerCropDraft.height < minSize) {
                clearCropDraft();
                updateReaderCropControls();
                return;
            }

            const leftWithinImage = Math.max(0, (readerCropDraft.left + readerCropDraft.overlayLeft) - imageRect.left);
            const topWithinImage = Math.max(0, (readerCropDraft.top + readerCropDraft.overlayTop) - imageRect.top);
            const crop = {
                x: Math.max(0, Math.min(1, leftWithinImage / imageRect.width)),
                y: Math.max(0, Math.min(1, topWithinImage / imageRect.height)),
                w: Math.max(0.01, Math.min(1, readerCropDraft.width / imageRect.width)),
                h: Math.max(0.01, Math.min(1, readerCropDraft.height / imageRect.height)),
            };

            const imageName = currentReaderImageName();
            if (imageName) {
                saveStoredReaderCrop(currentVersionId, imageName, crop);
                readerCurrentCrop = crop;
            }

            readerCropMode = false;
            clearCropDraft();
            updateReaderCropControls();
            applyReaderCropDisplay();
        });
    }

    if (readerEncodingSelect) {
        readerEncodingSelect.addEventListener('change', () => {
            readerEncoding = normalizeReaderEncoding(readerEncodingSelect.value);
            saveReaderEncodingPreference(currentVersionId, readerEncoding);
            if (currentVersionId) {
                loadReader(currentVersionId);
            }
        });
    }

    if (readerRebuildBtn) {
        readerRebuildBtn.addEventListener('click', async () => {
            if (!currentVersionId || readerRebuildBusy) {
                return;
            }

            readerRebuildBusy = true;
            applyReaderRebuildControl();
            if (readerRoot) {
                readerRoot.classList.remove('d-none');
            }
            setReaderLoading(true);
            setStatus('Reconstruction du lecteur en cours…', 'muted');
            setReaderActionStatus('Demande envoyée…', 'muted');

            try {
                const payload = {};
                if (readerTextSource && readerTextSource !== 'auto') {
                    payload.text_source = readerTextSource;
                }
                if (readerEncoding && readerEncoding !== 'auto') {
                    payload.encoding = readerEncoding;
                }

                const res = await fetch(withBasePath(`/api/versions/${currentVersionId}/reader/rebuild`), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data?.message || `HTTP ${res.status}`);
                }

                setStatus(data?.message || 'Lecteur reconstruit.', 'success');
                setReaderActionStatus('Reconstruit.', 'success');
                renderReader(data);
            } catch (err) {
                console.error('Could not rebuild reader dataset', err);
                const detail = err?.message ? ` (${err.message})` : '';
                setStatus(`Impossible de reconstruire le lecteur${detail}.`, 'danger');
                setReaderActionStatus('Échec.', 'danger');
            } finally {
                readerRebuildBusy = false;
                setReaderLoading(false);
                applyReaderRebuildControl();
            }
        });
    }

    if (readerTextSourceSelect) {
        readerTextSourceSelect.addEventListener('change', () => {
            readerTextSource = normalizeReaderTextSource(readerTextSourceSelect.value);
            saveReaderTextSourcePreference(currentVersionId, readerTextSource);
            if (currentVersionId) {
                loadReader(currentVersionId);
            }
        });
    }

    if (readerConvertBtn) {
        readerConvertBtn.addEventListener('click', async () => {
            const selectedEncoding = normalizeReaderEncoding(readerEncoding);
            if (!currentVersionId || selectedEncoding === 'auto' || readerConvertBusy) {
                return;
            }

            readerConvertBusy = true;
            applyReaderEncodingControl();

            try {
                const res = await fetch(withBasePath(`/api/versions/${currentVersionId}/text/convert-utf8`), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ encoding: selectedEncoding }),
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data?.message || `HTTP ${res.status}`);
                }

                setStatus(data?.message || 'Fichier texte converti en UTF-8.', 'success');
                readerEncoding = 'auto';
                saveReaderEncodingPreference(currentVersionId, readerEncoding);
                loadReader(currentVersionId);
            } catch (err) {
                console.error('Could not convert text to UTF-8', err);
                const detail = err?.message ? ` (${err.message})` : '';
                setStatus(`Impossible de convertir le fichier texte en UTF-8${detail}.`, 'danger');
            } finally {
                readerConvertBusy = false;
                applyReaderEncodingControl();
            }
        });
    }

    if (readerCarouselPrevBtn) {
        readerCarouselPrevBtn.addEventListener('click', () => {
            readerThumbsEl?.scrollBy({ left: -420, behavior: 'smooth' });
        });
    }

    if (readerCarouselNextBtn) {
        readerCarouselNextBtn.addEventListener('click', () => {
            readerThumbsEl?.scrollBy({ left: 420, behavior: 'smooth' });
        });
    }

    document.addEventListener('facsimiles:focusManifest', e => {
        const detail = e.detail || {};
        const versionId = Number(detail.versionId);
        const versionName = detail.versionName || '';
        const comparisonId = Number(detail.comparisonId);
        const role = (detail.role === 'target') ? 'target' : 'source';
        if (!Number.isFinite(versionId) || !Number.isFinite(comparisonId)) {
            return;
        }
        openFacsimilesPanel();
        const key = manifestOptionKey(comparisonId, role);
        pendingManifestFocus = key;
        if (currentVersionId !== versionId) {
            document.dispatchEvent(new CustomEvent('facsimiles:select', {
                detail: { versionId, versionName }
            }));
        } else {
            if (!focusManifest(key)) {
                loadManifestOptions(versionId, { preserveSelection: true, focusKey: key });
            }
        }
    });

    const clearInitialLoading = () => {
        facsimilesLoadingCount = 0;
        setFacsimilesLoading(false);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', clearInitialLoading);
    } else {
        clearInitialLoading();
    }

    function renderPagination(totalPages) {
        if (totalPages <= 1) {
            galleryPager.innerHTML = '';
            return;
        }

        const buttons = [];
        buttons.push(`<button type="button" class="btn btn-sm btn-outline-secondary" data-page="prev" ${galleryPage === 1 ? 'disabled' : ''}>‹</button>`);

        for (let p = 1; p <= totalPages; p++) {
            if (p === 1 || p === totalPages || Math.abs(p - galleryPage) <= 2) {
                buttons.push(`<button type="button" class="btn btn-sm ${p === galleryPage ? 'btn-primary' : 'btn-outline-secondary'}" data-page="${p}">${p}</button>`);
            } else if (buttons[buttons.length - 1] !== '…') {
                buttons.push('…');
            }
        }

        buttons.push(`<button type="button" class="btn btn-sm btn-outline-secondary" data-page="next" ${galleryPage === totalPages ? 'disabled' : ''}>›</button>`);

        galleryPager.innerHTML = buttons.map(btn => btn === '…' ? '<span class="px-2">…</span>' : btn).join('');

        galleryPager.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-page');
                if (target === 'prev') galleryPage = Math.max(1, galleryPage - 1);
                else if (target === 'next') galleryPage = Math.min(totalPages, galleryPage + 1);
                else galleryPage = Number(target);
                updateGallery();
            });
        });
    }
});
</script>
@endpush
