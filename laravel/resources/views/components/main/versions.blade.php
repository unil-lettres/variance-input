@php /**  components/main/versions.blade.php  **/ @endphp
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center gap-3 fw-semibold">
        <div class="d-flex align-items-center flex-grow-1">
            <div class="d-flex align-items-start gap-2 admin-card-heading">
                <span class="admin-card-heading-text">
                    <span class="admin-card-title">Versions</span>
                </span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="form-check form-switch version-details-toggle mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="version-details-toggle">
                <label class="form-check-label small fw-semibold" for="version-details-toggle">Détails</label>
            </div>
            <button type="button"
                    class="btn btn-sm btn-primary"
                    id="open-upload-version-modal"
                    disabled
                    aria-label="Téléverser une version">
                Téléverser une version
            </button>
        </div>
    </div>
    <div id="versionsCollapse" class="show">
    <div class="card-body">
        <!-- ────────────── Versions list  ────────────── -->
        <ul id="versions-list" class="list-group versions-list-shell">
            <li class="list-group-item">Sélectionner une œuvre pour voir les versions</li>
        </ul>
    </div>
    </div>
</div>

@push('styles')
<style>
    .versions-list-shell {
        border-radius: 0.85rem;
        overflow: hidden;
    }
    @media (max-width: 767.98px) {
        .card-header > #open-upload-version-modal,
        .card-header > .btn#open-upload-version-modal {
            width: 100%;
        }
    }
    .version-details-toggle .form-check-input,
    .version-details-toggle .form-check-label {
        cursor: pointer;
    }
</style>
@endpush

<!-- ────────────── Edit modal  ────────────── -->
<div class="modal fade" id="editVersionModal" tabindex="-1" aria-labelledby="editVersionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editVersionModalLabel">Modifier le nom d'édition</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-version-id">
                <div class="mb-3">
                    <label for="edit-version-name" class="form-label">Forme conseillée: "Editeur (année)"</label>
                    <input type="text" id="edit-version-name" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="update-version-btn">Mettre à jour</button>
            </div>
        </div>
    </div>
</div>

<!-- ────────────── Delete confirmation ────────────── -->
<div class="modal fade" id="deleteVersionConfirm" tabindex="-1" aria-labelledby="deleteVersionConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteVersionConfirmLabel">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Voulez-vous vraiment supprimer cette version&nbsp;?</p>
                <p class="text-danger small mb-0">Tous les fac-similés (originaux, miniatures, manifestes) et le fichier <code>_lignes</code> associé seront définitivement supprimés.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-version">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<!-- ────────────── Upload modal ────────────── -->
<div class="modal fade" id="uploadVersionModal" tabindex="-1" aria-labelledby="uploadVersionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadVersionModalLabel">Téléverser une version textuelle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Format recommandé: <code>UTF-8</code>. Les formats <code>Windows-1252</code>, <code>ISO-8859-1</code>, <code>Mac Roman</code> sont acceptés et seront convertis.
                </p>
                <form id="upload-version-form" enctype="multipart/form-data">
                    @csrf
                    <div class="row mb-3 align-items-center">
                        <label for="versionFile" class="col-sm-3 col-form-label">Fichier texte :</label>
                        <div class="col-sm-9">
                            <input type="file"
                                   name="versionFile"
                                   id="versionFile"
                                   class="form-control"
                                   accept=".txt,.text,text/plain"
                                   required>
                            <div id="file-info" class="form-text text-muted"></div>
                        </div>
                    </div>
                    <div class="row mb-3 align-items-center">
                        <label for="editionName" class="col-sm-3 col-form-label">Désignation :</label>
                        <div class="col-sm-9">
                            <input type="text"
                                   name="editionName"
                                   id="editionName"
                                   class="form-control"
                                   placeholder="Éditeur (année)"
                                   required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="submit-upload-form">Téléverser</button>
            </div>
        </div>
    </div>
</div>

<!-- ────────────── Facsimile upload modal ────────────── -->
<div class="modal fade" id="facsimileUploadModal" tabindex="-1" aria-labelledby="facsimileUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="facsimileUploadModalLabel">Téléverser des fac-similés</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="rounded border bg-light-subtle px-3 py-3 mb-4">
                    <p class="text-muted small mb-0 lh-base" id="facsimile-upload-info">
                    Sélectionnez un dossier source contenant soit une suite d'images (JPG, PNG), soit un ou plusieurs fichiers TIFF multipages.
                    Chaque image devient un fac-similé ; pour un TIFF multipage, chaque page est importée comme un fac-similé distinct.
                    </p>
                </div>
                <div class="mb-4">
                    <div class="input-group">
                        <label class="btn btn-outline-secondary mb-0" for="facsimile-img-input">Sélectionner le dossier source</label>
                        <input type="file" id="facsimile-img-input" class="d-none" webkitdirectory directory multiple accept="image/*">
                        <span class="form-control" id="facsimile-folder-label" readonly>Aucun dossier</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div id="facsimile-upload-spinner" class="spinner-border spinner-border-sm text-primary" role="status" style="display:none;"></div>
                </div>
                <div class="vstack gap-2">
                    <div id="facsimile-upload-log" class="small text-muted lh-base" style="white-space: pre-line;"></div>
                    <div id="facsimile-upload-total" class="small text-muted lh-base"></div>
                    <div id="facsimile-upload-summary" class="small text-muted lh-base" style="white-space: pre-line;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger d-none" id="facsimile-upload-cancel-btn">Annuler l'import</button>
                <button type="button" class="btn btn-primary" id="facsimile-upload-btn-modal" disabled>Importer</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
  #versionsCollapse,
  #versionsCollapse *,
  #versionsCollapse.show,
  #versionsCollapse.show * {
    visibility: visible !important;
  }
  .legacy-disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
</style>
@endpush

@push('scripts')
<style>
    /* Keep table headers visually consistent with card header */
    .version-table th { font-weight: normal; font-size: 1rem; color: #333; }
    .version-table th:nth-child(7) { text-align: center; }
    .version-table td { vertical-align: middle; }
    .version-table.compact-details th:nth-child(2),
    .version-table.compact-details td:nth-child(2),
    .version-table.compact-details th:nth-child(4),
    .version-table.compact-details td:nth-child(4) {
      display: none;
    }
    .version-table .download-btn {
      line-height: 1;
      padding-top: 0.25rem;
      padding-bottom: 0.2rem;
      min-width: 2.2rem;
    }
    .version-table .download-btn .download-icon {
      font-size: 0.95rem;
    }
    .version-table .download-btn .download-label {
      font-size: 0.6rem;
      letter-spacing: 0.04em;
    }
    .version-table .version-name-cell {
      white-space: nowrap;
    }
    .version-table .version-name-label {
      display: inline-block;
      max-width: calc(100% - 1.75rem);
      overflow: hidden;
      text-overflow: ellipsis;
      vertical-align: middle;
      white-space: nowrap;
    }
    .version-table .versions-inline-cell {
      display: grid;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      width: 100%;
    }
    .version-table .versions-inline-cell--facsimiles {
      grid-template-columns: 7.4rem auto;
    }
    .version-table .versions-inline-cell--pagination {
      grid-template-columns: 7.4rem 4.5rem 4.8rem;
    }
    .version-table .versions-count-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 7.4rem;
      max-width: 7.4rem;
      text-align: center;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      font-size: 0.74rem;
    }
    .version-table .versions-icon-btn {
      position: relative;
      width: 2.2rem;
      min-width: 2.2rem;
      padding-left: 0;
      padding-right: 0;
    }
    .version-table .versions-action-group {
      display: inline-flex;
      align-items: center;
    }
    .version-table .versions-switch-wrap {
      width: 4.8rem;
      min-width: 4.8rem;
      justify-content: flex-start !important;
    }
    .version-table .versions-switch-wrap .form-check-label {
      font-size: 0.74rem;
    }
    .version-table .versions-btn-icon,
    .version-table .versions-btn-spinner {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 100%;
    }
    .version-table .versions-btn-spinner[hidden] {
      display: none;
    }
    .version-table .versions-btn-activity {
      position: absolute;
      top: 0.15rem;
      right: 0.15rem;
      width: 0.65rem;
      height: 0.65rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      background: #fff;
      box-shadow: 0 0 0 1px rgba(13, 110, 253, 0.2);
    }
    .version-table .versions-btn-activity[hidden] {
      display: none;
    }
    .version-table .versions-btn-activity .spinner-border {
      width: 0.55rem;
      height: 0.55rem;
      border-width: 0.11rem;
      color: #0d6efd;
    }
    .version-table .version-viewer-col {
      width: 3.2rem;
      min-width: 3.2rem;
      text-align: center;
    }
    .version-table .version-viewer-btn {
      width: 2.35rem;
      min-width: 2.35rem;
      height: 2.35rem;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
    }
    .version-table .version-viewer-btn .bi-eye {
      font-size: 1.15rem;
    }
    .version-table .version-viewer-state {
      display: none;
      margin-top: 0.2rem;
      font-size: 0.62rem;
      font-weight: 600;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      color: #0d6efd;
      line-height: 1.1;
    }
    .version-table tr.version-row-selected > td {
      background-color: rgba(13, 110, 253, 0.11);
    }
    .version-table tr.version-row-selected td:first-child {
      box-shadow: inset 4px 0 0 #0d6efd;
    }
    .version-table tr.version-row-selected .version-viewer-btn {
      background-color: #0d6efd;
      border-color: #0d6efd;
      color: #fff;
      box-shadow: 0 0 0 0.18rem rgba(13, 110, 253, 0.18);
    }
    .version-table tr.version-row-selected .version-viewer-state {
      display: block;
    }
</style>

<script>
/***********************  CONFIG  *************************/
const MAX_TXT_CHARACTERS = 5_000_000; // ≈ 3 Mo UTF-8, laisse de la marge pour les romans longs
const MAX_FAC_BATCH_FILES = 10;
const MAX_FAC_BATCH_BYTES = 7.5 * 1024 * 1024;
/*********************  GLOBAL STATE  *********************/
let selectedWorkId   = null;
let shortTitle       = null;
let authorId         = null;
let versionToDelete  = null;
let detectedEncoding = 'Unknown';
let versionsCache    = new Map();
const versionsFetchCache = new Map();
const textLengthFetches = new Map();
const setVersionsLoading = (state) => {
    if (typeof window.setBladeLoading === 'function') {
        window.setBladeLoading('versionsCollapse', state);
    }
};
const VERSIONS_FETCH_TTL = 8000;
const facsimileRowState = new Map();
const facsimilePollers  = new Map();
const lignesPollers     = new Map();
let facModalEl       = null;
let facModalTitle    = null;
let facModalInfo     = null;
let facFileInput     = null;
let facUploadBtn     = null;
let facCancelBtn     = null;
let facSpinner       = null;
let facLog           = null;
let facSummary       = null;
let facFolderLabel  = null;
let facModalInstance = null;
let facTotalSizeEl   = null;
let facVersionId     = null;
let facVersionName   = '';
let facSpaceOk       = true;
let facSpaceCheckToken = 0;
let facUploadInProgress = false;
let facUploadAbortController = null;
let facUploadCancelRequested = false;
let facCurrentUploadVersionId = null;
let currentViewerVersionId = null;
let selectedAuthorLabel = '';
let selectedWorkLabel   = '';
let showVersionDetails  = false;
const UPLOAD_MODAL_BASE_TITLE = 'Téléverser une version textuelle';
/*********************  GLOBAL STATE  *********************/
/*********************  UTIL HELPERS  *********************/
const formatNumber = n => n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
const formatBytes = (size) => {
    const value = Number(size);
    if (!Number.isFinite(value) || value <= 0) return '0 o';
    const units = ['o','Ko','Mo','Go','To'];
    let idx = 0;
    let current = value;
    while (current >= 1024 && idx < units.length - 1) {
        current /= 1024;
        idx++;
    }
    const precision = idx === 0 ? 0 : 1;
    return `${current.toFixed(precision)} ${units[idx]}`;
};
const formatTimestamp = (seconds) => {
    const value = Number(seconds);
    if (!Number.isFinite(value) || value <= 0) return 'Date inconnue';
    const date = new Date(value * 1000);
    const datePart = date.toLocaleDateString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit' });
    const timePart = date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    return `${datePart} ${timePart}`;
};
function requestTextLength(versionId, cell) {
    const id = Number(versionId);
    if (!Number.isFinite(id) || !cell) return;
    const existing = textLengthFetches.get(id);
    if (existing) {
        existing.then(length => {
            if (!cell || cell.dataset.versionId !== String(id)) return;
            if (typeof length === 'number') {
                cell.textContent = length.toLocaleString('fr-FR');
                cell.title = 'Nombre de signes dans la version';
            } else {
                cell.textContent = 'n/a';
                cell.title = 'Fichier texte indisponible';
            }
        });
        return;
    }

    const promise = fetch(withBasePath(`/api/versions/${id}/text-length`), { headers: { 'Accept': 'application/json' } })
        .then(res => res.ok ? res.json() : null)
        .then(payload => {
            const length = payload && typeof payload.text_length === 'number' ? payload.text_length : null;
            return length;
        })
        .catch(() => null);

    textLengthFetches.set(id, promise);
    promise.then(length => {
        if (!cell || cell.dataset.versionId !== String(id)) return;
        if (typeof length === 'number') {
            cell.textContent = length.toLocaleString('fr-FR');
            cell.title = 'Nombre de signes dans la version';
        } else {
            cell.textContent = 'n/a';
            cell.title = 'Fichier texte indisponible';
        }
    });
}
const buildVersionsUrl = (workId, force = false) => {
    const suffix = force ? '&fresh=1' : '';
    return withBasePath(`/api/versions?work_id=${workId}${suffix}`);
};
function getVersionsForWork(workId, { force = false } = {}) {
    const id = Number(workId);
    if (!Number.isFinite(id) || id <= 0) {
        return Promise.resolve([]);
    }
    if (force) {
        versionsFetchCache.delete(id);
    }
    const now = Date.now();
    const cached = versionsFetchCache.get(id);
    if (!force && cached?.data && (now - cached.ts) < VERSIONS_FETCH_TTL) {
        return Promise.resolve(cached.data);
    }
    if (!force && cached?.promise) {
        return cached.promise;
    }
    const fetchPromise = fetch(buildVersionsUrl(id, force), { headers: { 'Accept': 'application/json' } })
        .then(res => {
            if (!res.ok) {
                throw new Error(res.statusText || `HTTP ${res.status}`);
            }
            return res.json();
        })
        .then(data => {
            versionsFetchCache.set(id, { data, ts: Date.now(), promise: null });
            return data;
        })
        .catch(err => {
            versionsFetchCache.delete(id);
            throw err;
        })
        .finally(() => {
            const entry = versionsFetchCache.get(id);
            if (entry?.promise === fetchPromise) {
                entry.promise = null;
                versionsFetchCache.set(id, entry);
            }
        });
    versionsFetchCache.set(id, { ...(cached ?? {}), promise: fetchPromise });
    return fetchPromise;
}
window.varianceGetVersionsForWork = getVersionsForWork;
function applyVersionDetailsMode() {
    const table = document.querySelector('#versions-list .version-table');
    if (!table) return;
    table.classList.toggle('compact-details', !showVersionDetails);
}
function detectEncodingBOM(file){
    return new Promise(resolve=>{
        const r = new FileReader();
        r.onload = e=>{
            const v = new Uint8Array(e.target.result||new ArrayBuffer(0));
            if (v.length>=3 && v[0]===0xEF && v[1]===0xBB && v[2]===0xBF) return resolve('UTF-8 (BOM)');
            if (v.length>=4 && v[0]===0x00 && v[1]===0x00 && v[2]===0xFE && v[3]===0xFF) return resolve('UTF-32 BE (BOM)');
            if (v.length>=4 && v[0]===0xFF && v[1]===0xFE && v[2]===0x00 && v[3]===0x00) return resolve('UTF-32 LE (BOM)');
            if (v.length>=2 && v[0]===0xFE && v[1]===0xFF) return resolve('UTF-16 BE (BOM)');
            if (v.length>=2 && v[0]===0xFF && v[1]===0xFE) return resolve('UTF-16 LE (BOM)');
            resolve('No BOM / Unknown');
        };
        r.onerror = ()=>resolve('Unknown');
        r.readAsArrayBuffer(file.slice(0,4));
    });
}
/*********************  MAIN LOGIC  *********************/

function stopFacsimilePolling(versionId){
    const id = Number(versionId);
    const timer = facsimilePollers.get(id);
    if (timer) {
        clearInterval(timer);
        facsimilePollers.delete(id);
    }
}

async function requestFacsimileProgress(versionId){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return;
    try {
        const res = await fetch(withBasePath(`/api/versions/${id}/facsimiles/progress?ts=${Date.now()}`), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) throw new Error(res.statusText);
        const data = await res.json();
        const cached = versionsCache.get(id);
        if (cached) {
            cached.facsimiles = data;
        }
        renderFacsimileStatus(id, data);
        if (!(data?.queue_count > 0)) {
            stopFacsimilePolling(id);
        }
    } catch (err) {
        console.error('Could not refresh fac-similé progress', err);
    }
}

function ensureFacsimilePolling(versionId, { immediate = false } = {}){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return;
    if (!facsimilePollers.has(id)) {
        const timer = setInterval(() => requestFacsimileProgress(id), 4000);
        facsimilePollers.set(id, timer);
    }
    if (immediate) {
        requestFacsimileProgress(id);
    }
}

function stopLignesPolling(versionId){
    const id = Number(versionId);
    const timer = lignesPollers.get(id);
    if (timer) {
        clearInterval(timer);
        lignesPollers.delete(id);
    }
}

async function requestLignesProgress(versionId){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return;
    try {
        const res = await fetch(withBasePath(`/api/versions/${id}/page-markers/progress?ts=${Date.now()}`), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (!res.ok) {
            if (res.status === 404) {
                stopLignesPolling(id);
            }
            return;
        }
        const data = await res.json();
        const cached = versionsCache.get(id) ?? {};
        cached.page_marker_progress = data;
        let paginationInfo = cached.pagination ?? null;
        if (data?.sidecar) {
            paginationInfo = data.sidecar;
            cached.pagination = paginationInfo;
        } else if (data?.status === 'done') {
            const refreshed = await refreshPaginationInfo(id);
            if (refreshed) {
                paginationInfo = refreshed;
                cached.pagination = refreshed;
            }
        }
        versionsCache.set(id, cached);
        const lignesInfo = cached.lignes ?? null;
        renderLignesStatus(id, lignesInfo, data, paginationInfo);
        const status = data?.status || 'idle';
        if (['idle', 'done', 'failed'].includes(status)) {
            stopLignesPolling(id);
            if (status === 'done' && Number.isFinite(selectedWorkId)) {
                fetchVersions(selectedWorkId, true);
            }
        }
    } catch (err) {
        console.error('Could not refresh _lignes progress', err);
    }
}

function ensureLignesPolling(versionId, { immediate = false } = {}){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return;
    if (!lignesPollers.has(id)) {
        const timer = setInterval(() => requestLignesProgress(id), 4000);
        lignesPollers.set(id, timer);
    }
    if (immediate) {
        requestLignesProgress(id);
    }
}

async function refreshPaginationInfo(versionId){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return null;
    try {
        const res = await fetch(withBasePath(`/api/versions/${id}/pagination-info?ts=${Date.now()}`), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (!res.ok) {
            return null;
        }
        const data = await res.json();
        const cached = versionsCache.get(id) ?? {};
        cached.pagination = data;
        versionsCache.set(id, cached);
        return data;
    } catch (err) {
        console.error('Could not refresh pagination info', err);
        return null;
    }
}

function renderFacsimileStatus(versionId, facsimileData){
    const id = Number(versionId);
    const state = facsimileRowState.get(id);
    if (!state) return;
    const isLegacy = !!(versionsCache.get(id)?.is_legacy);
    const legacyTooltip = 'La collection de facsimilés est en lecture seule pour les collections legacy';
    const attachLegacyTooltip = (btn) => {
        if (!btn) return;
        btn.title = legacyTooltip;
        if (!window.bootstrap || !bootstrap.Tooltip) {
            return;
        }
        const existing = btn._legacyTooltipInstance;
        btn.setAttribute('data-bs-toggle', 'tooltip');
        btn.setAttribute('data-bs-placement', 'top');
        if (existing) {
            if (typeof existing.setContent === 'function') {
                existing.setContent({ '.tooltip-inner': legacyTooltip });
            } else {
                btn.setAttribute('data-bs-original-title', legacyTooltip);
            }
            return;
        }
        const tooltip = new bootstrap.Tooltip(btn);
        btn._legacyTooltipInstance = tooltip;
        actionButtonsTooltips.push(tooltip);
    };
    const detachLegacyTooltip = (btn) => {
        if (!btn) return;
        const existing = btn._legacyTooltipInstance;
        if (existing) {
            existing.dispose();
            delete btn._legacyTooltipInstance;
        }
        btn.removeAttribute('data-bs-toggle');
        btn.removeAttribute('data-bs-placement');
        btn.removeAttribute('data-bs-original-title');
    };
    const applyLegacyState = (btn) => {
        if (!btn) return;
        btn.disabled = false;
        btn.classList.add('legacy-disabled');
        btn.setAttribute('aria-disabled', 'true');
        attachLegacyTooltip(btn);
    };
    const clearLegacyState = (btn) => {
        if (!btn) return;
        btn.classList.remove('legacy-disabled');
        btn.removeAttribute('aria-disabled');
        detachLegacyTooltip(btn);
    };

    if (facsimileData?.loading) {
        if (state.facCountPill) state.facCountPill.textContent = '… fac-similé(s)';
        if (state.viewBtn) {
            state.viewBtn.disabled = false;
            state.viewBtn.title = 'Charger les fac-similés';
            state.viewBtn.dataset.facsimileLoading = '1';
        }
        if (state.uploadBtn) {
            if (state.uploadBtnSpinner) state.uploadBtnSpinner.hidden = false;
            if (isLegacy) {
                applyLegacyState(state.uploadBtn);
            } else {
                clearLegacyState(state.uploadBtn);
                state.uploadBtn.disabled = false;
                state.uploadBtn.title = 'Importer de nouveaux fac-similés';
            }
        }
        if (state.clearBtn) {
            if (isLegacy) {
                applyLegacyState(state.clearBtn);
            } else {
                clearLegacyState(state.clearBtn);
                state.clearBtn.disabled = true;
                state.clearBtn.title = 'Chargement des fac-similés…';
            }
        }
        return;
    }

    if (state.viewBtn) {
        state.viewBtn.dataset.facsimileLoading = '';
    }

    const ready = Math.max(0, Number(facsimileData?.source_count ?? 0));
    const published = Math.max(0, Number(facsimileData?.published_count ?? 0));
    const queued = Math.max(0, Number(facsimileData?.queue_count ?? 0));
    const total = Math.max(0, Number(facsimileData?.total_expected ?? (ready + queued)));
    const expected = total || (ready + queued);
    const totalImages = expected > 0 ? expected : (ready + queued);
    const outstanding = Math.max(0, ready - published);

    if (state.facCountPill) {
        state.facCountPill.textContent = `${ready.toLocaleString('fr-FR')} fac-similé(s)`;
    }
    if (state.viewBtn) {
        const disableView = queued > 0 || ready === 0;
        state.viewBtn.disabled = disableView;
        state.viewBtn.title = disableView
            ? (queued > 0 ? 'Traitement en cours — affichage indisponible' : 'Aucune image à afficher')
            : 'Afficher la galerie de fac-similés';
    }

    if (state.uploadBtn) {
        if (state.uploadBtnSpinner) state.uploadBtnSpinner.hidden = !(queued > 0);
        if (isLegacy) {
            applyLegacyState(state.uploadBtn);
        } else {
            clearLegacyState(state.uploadBtn);
            state.uploadBtn.disabled = queued > 0;
            state.uploadBtn.title = queued > 0
                ? 'Traitement en cours — veuillez patienter avant de téléverser'
                : 'Importer de nouveaux fac-similés';
        }
    }
    if (state.clearBtn) {
        const hasAnyFacsimiles = ready > 0 || published > 0 || queued > 0;
        if (isLegacy) {
            applyLegacyState(state.clearBtn);
        } else if (queued > 0) {
            clearLegacyState(state.clearBtn);
            state.clearBtn.disabled = true;
            state.clearBtn.title = 'Traitement en cours — utilisez plutôt l’annulation ci-dessous';
        } else {
            clearLegacyState(state.clearBtn);
            state.clearBtn.disabled = !hasAnyFacsimiles;
            state.clearBtn.title = hasAnyFacsimiles
                ? 'Supprimer tous les fac-similés'
                : 'Aucune image à supprimer';
        }
    }

    if (versionsCache.has(id)) {
        const cached = versionsCache.get(id);
        cached.facsimiles = { ...(cached.facsimiles ?? {}), ...(facsimileData ?? {}) };
        versionsCache.set(id, cached);
    }
}

function renderLignesStatus(versionId, lignesInfo, progress, paginationInfo = null){
    const id = Number(versionId);
    const state = facsimileRowState.get(id);
    if (!state) return;
    const isLegacy = !!state.isLegacy;

    const hasSidecar = paginationInfo && typeof paginationInfo === 'object';
    const progressData = progress ?? null;
    const status = progressData?.status ?? null;
    let markerCount = hasSidecar
        ? Math.max(0, Number(paginationInfo?.details?.marker_count ?? 0))
        : 0;

    if (status === 'done') {
        const sidecarMeta = progressData?.sidecar ?? paginationInfo ?? null;
        const sidecarTotal = Number(paginationInfo?.details?.marker_count ?? sidecarMeta?.details?.marker_count ?? sidecarMeta?.marker_count ?? NaN);
        const summaryTotal = Number.isFinite(sidecarTotal)
            ? sidecarTotal
            : Number(progressData?.summary?.total ?? 0);
        markerCount = Math.max(0, Number.isFinite(summaryTotal) ? summaryTotal : 0);
    }

    if (state.markerCountPill) {
        state.markerCountPill.textContent = `${markerCount.toLocaleString('fr-FR')} marqueur(s)`;
    }

    if (state.lignesUploadBtn) {
        const inProgress = !!(status && ['queued', 'running'].includes(status));
        if (state.lignesUploadSpinner) state.lignesUploadSpinner.hidden = !inProgress;
        if (isLegacy) {
            state.lignesUploadBtn.disabled = true;
        } else {
            state.lignesUploadBtn.disabled = inProgress;
        }
        if (!isLegacy) {
            state.lignesUploadBtn.title = state.lignesUploadBtn.disabled
                ? 'Traitement en cours — veuillez patienter'
                : 'Importer un fichier _lignes';
        }
        if (state.clearMarkersBtn && !isLegacy) {
            state.clearMarkersBtn.disabled = inProgress;
            state.clearMarkersBtn.title = inProgress
                ? 'Traitement en cours — veuillez patienter'
                : 'Supprimer tous les marqueurs de pagination';
        }
    }
    if (versionsCache.has(id)) {
        const cached = versionsCache.get(id) ?? {};
        cached.lignes = lignesInfo ?? null;
        cached.page_marker_progress = progressData ?? null;
        versionsCache.set(id, cached);
    }
}

function renderPaginationDoneState(versionId, data = null){
    const id = Number(versionId);
    const state = facsimileRowState.get(id);
    if (!state) return;

    const done = !!(data?.pagination_done ?? data?.done ?? data);
    const doneAt = data?.pagination_done_at ?? null;
    const doneByName = data?.pagination_done_by_name ?? data?.done_by ?? null;

    if (state.completionToggle) {
        state.completionToggle.checked = done;
    }

    if (versionsCache.has(id)) {
        const cached = versionsCache.get(id) ?? {};
        cached.pagination_done = done;
        cached.pagination_done_at = done ? doneAt : null;
        cached.pagination_done_by = done ? (data?.pagination_done_by ?? null) : null;
        cached.pagination_done_by_name = done ? (doneByName ?? cached.pagination_done_by_name ?? null) : null;
        versionsCache.set(id, cached);
    }
}

async function togglePaginationDone(versionId, done){
    const id = Number(versionId);
    const state = facsimileRowState.get(id);
    if (state?.completionToggle) {
        state.completionToggle.disabled = true;
    }

    try {
        const res = await fetch(withBasePath(`/api/versions/${id}/pagination/done`), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ done })
        });
        const payload = await readJsonResponse(res);
        renderPaginationDoneState(id, payload);
    } catch (err) {
        console.error(err);
        alert(err.message || 'Impossible de mettre à jour le statut de pagination.');
        if (state?.completionToggle) {
            state.completionToggle.checked = !done;
        }
        if (versionsCache.has(id)) {
            renderPaginationDoneState(id, versionsCache.get(id));
        }
    } finally {
        if (state?.completionToggle) {
            state.completionToggle.disabled = false;
        }
    }
}

window.addEventListener('DOMContentLoaded',()=>{
    const openUploadBtn   = document.getElementById('open-upload-version-modal');
    const submitUploadBtn = document.getElementById('submit-upload-form');
    const uploadModalEl   = document.getElementById('uploadVersionModal');
    const uploadModalLabel = document.getElementById('uploadVersionModalLabel');
    let uploadModalInstance = null;
    const refreshUploadModalTitle = () => {
        if (!uploadModalLabel) return;
        if (selectedAuthorLabel && selectedWorkLabel) {
            uploadModalLabel.textContent = `${UPLOAD_MODAL_BASE_TITLE} — ${selectedAuthorLabel} · ${selectedWorkLabel}`;
        } else {
            uploadModalLabel.textContent = UPLOAD_MODAL_BASE_TITLE;
        }
    };
    refreshUploadModalTitle();
    const detailsToggle = document.getElementById('version-details-toggle');
    if (detailsToggle) {
        detailsToggle.checked = showVersionDetails;
        detailsToggle.addEventListener('change', () => {
            showVersionDetails = !!detailsToggle.checked;
            applyVersionDetailsMode();
        });
    }

    facModalEl     = document.getElementById('facsimileUploadModal');
    facModalTitle  = document.getElementById('facsimileUploadModalLabel');
    facModalInfo   = document.getElementById('facsimile-upload-info');
    facFileInput   = document.getElementById('facsimile-img-input');
    facUploadBtn   = document.getElementById('facsimile-upload-btn-modal');
    facCancelBtn   = document.getElementById('facsimile-upload-cancel-btn');
    facSpinner     = document.getElementById('facsimile-upload-spinner');
    facLog         = document.getElementById('facsimile-upload-log');
    facSummary     = document.getElementById('facsimile-upload-summary');
    facTotalSizeEl = document.getElementById('facsimile-upload-total');
    facFolderLabel = document.getElementById('facsimile-folder-label');

    const $fileInput = document.getElementById('versionFile');
    const $fileInfo  = document.getElementById('file-info');

    updateVersionsCount(null);

    if (openUploadBtn) {
        openUploadBtn.addEventListener('click', () => {
            if (!selectedWorkId) {
                alert('Veuillez sélectionner une œuvre avant d\'ajouter une version.');
                return;
            }
            if (uploadModalEl) {
                const form = document.getElementById('upload-version-form');
                if (form) form.reset();
                if ($fileInfo) $fileInfo.textContent = '';
                detectedEncoding = 'Unknown';
                refreshUploadModalTitle();
                uploadModalInstance = uploadModalInstance || new bootstrap.Modal(uploadModalEl);
                uploadModalInstance.show();
            }
        });
    }
    if (facModalEl) {
        facModalEl.addEventListener('hide.bs.modal', (event) => {
            if (!facUploadInProgress) return;
            event.preventDefault();
            if (facSummary) {
                facSummary.className = 'small text-warning';
                facSummary.textContent = 'Import en cours: fermeture désactivée pour éviter une série incomplète.';
            }
        });
        facModalEl.addEventListener('hidden.bs.modal', () => {
            setFacsimileUploadBusy(false);
            facVersionId = null;
            facVersionName = '';
            facUploadCancelRequested = false;
            facCurrentUploadVersionId = null;
            facUploadAbortController = null;
            facSpaceOk = true;
            facSpaceCheckToken += 1;
            if (facFileInput) facFileInput.value = '';
            if (facUploadBtn) facUploadBtn.disabled = true;
            if (facLog) facLog.textContent = '';
            if (facSummary) {
                facSummary.className = 'small text-muted';
                facSummary.textContent = '';
            }
            if (facTotalSizeEl) facTotalSizeEl.textContent = '';
            if (facSpinner) facSpinner.style.display = 'none';
            if (facModalInfo) facModalInfo.innerHTML = 'Sélectionnez un dossier contenant les images à importer.';
            if (facFolderLabel) facFolderLabel.textContent = 'Aucun dossier';
        });
    }

    if (submitUploadBtn) {
        submitUploadBtn.addEventListener('click', () => {
            const form = document.getElementById('upload-version-form');
            if (form) {
                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }
        });
    }

    /* ——— File change ——— */
    $fileInput.addEventListener('change', async()=>{
        const file = $fileInput.files[0];
        $fileInfo.innerHTML='';
        detectedEncoding='Unknown';
        if(!file) return;
        detectedEncoding = await detectEncodingBOM(file);
        try{
            const txt = await file.text();
            const len = txt.length;
            $fileInfo.innerHTML = `Encodage : <strong>${detectedEncoding}</strong><br>`+
                                  `Caractères : <strong>${formatNumber(len)}</strong> / ${formatNumber(MAX_TXT_CHARACTERS)}`+
                                  (len>MAX_TXT_CHARACTERS ? ' – <span class="text-danger">fichier trop volumineux</span>' : '');
        }catch(err){
            console.error(err);
            $fileInfo.textContent='Erreur lors de la lecture du fichier.';
        }
    });

    if (facFileInput) {
        facFileInput.addEventListener('change', async () => {
            const allFiles = facFileInput.files ? facFileInput.files.length : 0;
            const images = collectSelectedImages();
            const filesArray = facFileInput.files ? Array.from(facFileInput.files) : [];
            const dsStoreCount = filesArray.filter(isDsStoreFile).length;
            const estimatedPages = await estimateFacsimilePages(images);
            if (facSummary) {
                facSummary.className = 'small text-muted';
                facSummary.textContent = '';
            }
            if (facFolderLabel) {
                const firstPath = images.length ? (images[0].webkitRelativePath || images[0].name || '') : '';
                facFolderLabel.textContent = firstPath ? (firstPath.split('/')[0] || firstPath) : 'Aucun dossier';
            }
            if (facTotalSizeEl) {
                if (images.length) {
                    const totalBytes = images.reduce((sum, file) => sum + (file.size || 0), 0);
                    facTotalSizeEl.textContent = `Volume total estimé : ${formatBytes(totalBytes)}`;
                    checkFacsimileSpace(totalBytes, images.length);
                } else {
                    facTotalSizeEl.textContent = '';
                }
            }
            updateFacUploadButtonState(images.length);
            if (facLog) {
                if (!allFiles) {
                    facLog.textContent = '';
                } else if (!images.length) {
                    facLog.textContent = 'Aucun fichier image reconnu dans ce dossier.';
                } else {
                    const ignored = Math.max(0, allFiles - images.length - dsStoreCount);
                    const sourceLabel = `${images.length} fichier(s) source détecté(s)`;
                    const pageLabel = estimatedPages > 0 ? ` — ${estimatedPages} page(s) estimée(s)` : '';
                    facLog.textContent = sourceLabel + pageLabel + (ignored > 0 ? ` — ${ignored} ignorée(s)` : '');
                }
            }
        });
    }
    if (facUploadBtn) {
        facUploadBtn.addEventListener('click', async () => {
            if (!facVersionId) {
                alert('Sélectionnez une version dans la liste avant d\'importer des fac-similés.');
                return;
            }

            const totalSelected = facFileInput?.files ? facFileInput.files.length : 0;
            const files = collectSelectedImages();
            if (!files.length) {
                alert(totalSelected ? 'Aucun fichier image valide détecté dans ce dossier.' : 'Sélectionnez un dossier contenant des images.');
                return;
            }
            if (!facSpaceOk) {
                alert('Espace disque insuffisant pour importer ces fac-similés. Réduisez le volume ou libérez de la place.');
                return;
            }

            const sortedFiles = files.sort((a, b) => {
                const keyA = (a.webkitRelativePath || a.name || '').toLocaleLowerCase();
                const keyB = (b.webkitRelativePath || b.name || '').toLocaleLowerCase();
                return keyA.localeCompare(keyB, undefined, { numeric: true, sensitivity: 'base' });
            });

            const uploadVersionId = Number(facVersionId);
            if (!Number.isFinite(uploadVersionId) || uploadVersionId <= 0) {
                alert('Version cible invalide. Réouvrez la fenêtre d\'import.');
                return;
            }
            facUploadCancelRequested = false;
            facCurrentUploadVersionId = uploadVersionId;
            setFacsimileUploadBusy(true);
            facUploadBtn.disabled = true;
            if (facSpinner) facSpinner.style.display = 'inline-block';
            if (facLog) facLog.textContent = '';
            if (facSummary) {
                facSummary.className = 'small text-muted';
                facSummary.textContent = '';
            }

            const totalFiles = sortedFiles.length;
            let uploadedCount = 0;
            const processingIssues = [];
            const batchErrors = [];
            const perFileReport = [];
            let overallMaxLongEdge = null;
            let lastStoredDir = null;
            let processingQueued = false;

            let cursor = 0;
            let batchIndex = 0;
            while (cursor < totalFiles) {
                if (facUploadCancelRequested) break;
                let batchSize = 0;
                let byteTotal = 0;
                const chunk = [];

                while (cursor < totalFiles && batchSize < MAX_FAC_BATCH_FILES) {
                    const file = sortedFiles[cursor];
                    const tentative = byteTotal + (file.size || 0);
                    if (batchSize > 0 && tentative > MAX_FAC_BATCH_BYTES) break;

                    chunk.push(file);
                    byteTotal = tentative;
                    batchSize += 1;
                    cursor += 1;

                    if (byteTotal >= MAX_FAC_BATCH_BYTES) break;
                }

                if (!chunk.length) {
                    const file = sortedFiles[cursor];
                    chunk.push(file);
                    cursor += 1;
                }

                const start = cursor - chunk.length + 1;
                const end   = cursor;

                if (facLog) facLog.textContent = `Envoi des images ${start} à ${end} sur ${totalFiles}…`;

                const form = new FormData();
                form.append('version_id', String(uploadVersionId));
                form.append('reset', batchIndex === 0 ? '1' : '0');
                chunk.forEach(file => form.append('images[]', file));

                try {
                    facUploadAbortController = new AbortController();
                    const res = await fetch(withBasePath('/api/upload_facsimiles'), {
                        method : 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content
                        },
                        body   : form,
                        signal : facUploadAbortController.signal
                    });
                    facUploadAbortController = null;

                    const payload = await readJsonResponse(res);

                    uploadedCount += payload.files_added ?? 0;
                    if (payload.stored_in) {
                        lastStoredDir = payload.stored_in;
                    }
                    if (Array.isArray(payload.errors) && payload.errors.length) {
                        processingIssues.push(...payload.errors);
                    }
                    if (Array.isArray(payload.files_report)) {
                        payload.files_report.forEach(item => perFileReport.push(item));
                    }
                    if (payload?.limits?.max_long_edge) {
                        overallMaxLongEdge = payload.limits.max_long_edge;
                    }
                    if (payload?.processing) {
                        processingQueued = true;
                    }
                } catch (err) {
                    facUploadAbortController = null;
                    if (facUploadCancelRequested && (err?.name === 'AbortError' || /aborted/i.test(String(err?.message || '')))) {
                        break;
                    }
                    console.error(err);
                    batchErrors.push({
                        range: `${start}-${end}`,
                        message: err.message
                    });
                }

                batchIndex += 1;
            }

        if (facUploadCancelRequested) {
            return;
        }
        if (facSpinner) facSpinner.style.display = 'none';
        setFacsimileUploadBusy(false);
        updateFacUploadButtonState(collectSelectedImages().length);
        facCurrentUploadVersionId = null;

        const success = uploadedCount && !processingIssues.length && !batchErrors.length;

            if (success) {
            if (facFileInput) facFileInput.value = '';
            if (facUploadBtn) facUploadBtn.disabled = true;
            if (facLog) facLog.textContent = '';
            if (facTotalSizeEl) facTotalSizeEl.textContent = '';
                if (facSummary) {
                    facSummary.className = 'small text-success';
                    facSummary.textContent = `✅ ${uploadedCount} fac-similé(s) importé(s)` + (lastStoredDir ? ` dans ${lastStoredDir}` : '');
                    if (processingQueued) {
                        facSummary.textContent += '\n🕓 Les redimensionnements et miniatures se poursuivent en arrière-plan.';
                    }
                }
                await requestFacsimileProgress(uploadVersionId);
                if (Number.isFinite(selectedWorkId)) {
                    await fetchVersions(selectedWorkId, true);
                }
                revealFacsimilesForVersion(uploadVersionId, facVersionName);
                document.dispatchEvent(new CustomEvent('facsimilesUploaded', {
                    detail: { versionId: uploadVersionId, versionName: facVersionName || '' }
                }));
                if (facModalInstance) facModalInstance.hide();
                return;
        }

        const messages = [];
        if (uploadedCount) {
            messages.push(`✅ ${uploadedCount} fichier(s) importé(s)${lastStoredDir ? ` dans ${lastStoredDir}` : ''}`);
        }
        if (processingIssues.length) {
            messages.push(`⚠️ Traitement partiel pour ${processingIssues.length} fichier(s). Exemple : ${processingIssues.slice(0,3).join(', ')}${processingIssues.length>3?'…':''}`);
        }
        if (batchErrors.length) {
            const sample = batchErrors[0];
            messages.push(`❌ ${batchErrors.length} lot(s) en erreur (images ${sample.range}) : ${sample.message}`);
        }
        if (overallMaxLongEdge) {
            messages.push(`↘️ Redimensionnement appliqué : bord long limité à ${overallMaxLongEdge}px.`);
        }
        if (processingQueued) {
            messages.push('🕓 Les redimensionnements et miniatures se poursuivent en arrière-plan.');
        }
        if (perFileReport.length) {
            messages.push(...perFileReport.map(report => {
                const origDims = (Number.isFinite(report.original_width) && Number.isFinite(report.original_height))
                    ? `${report.original_width}×${report.original_height}px`
                    : 'dimensions inconnues';
                const storedDims = (Number.isFinite(report.stored_width) && Number.isFinite(report.stored_height))
                    ? `${report.stored_width}×${report.stored_height}px`
                    : 'dimensions inconnues';
                const origSizeStr = formatBytes(report.original_size);
                const storedSizeStr = formatBytes(report.stored_size);
                const resizeFlag = report.resized ? '' : ' (aucun redimensionnement)';
                return `${report.name}: ${origDims} / ${origSizeStr} → ${storedDims} / ${storedSizeStr}${resizeFlag}`;
            }));
        }
        if (!messages.length) {
            messages.push('Aucun fichier importé.');
        }

        if (facSummary) {
            let cls = 'text-muted';
            if (batchErrors.length) {
                cls = 'text-danger';
            } else if (processingIssues.length) {
                cls = 'text-warning';
            } else if (uploadedCount) {
                cls = 'text-success';
            }
            facSummary.className = `small ${cls}`;
            facSummary.textContent = messages.join('\n');
        }
        if (facLog) facLog.textContent = '';
        });
    }
    if (facCancelBtn) {
        facCancelBtn.addEventListener('click', () => {
            cancelCurrentFacsimileUpload();
        });
    }

    document.addEventListener('facsimiles:requestUpload', e => {
        const versionId = Number(e.detail?.versionId);
        if (!versionId) {
            alert('Sélectionnez une version dans la liste.');
            return;
        }
        const version = versionsCache.get(versionId);
        if (!version) {
            alert('Version introuvable. Actualisez la liste.');
            return;
        }
        openFacsimileUploadModal(version);
    });

    document.addEventListener('facsimilesUploaded', e => {
        const versionId = Number(e.detail?.versionId);
        if (!Number.isFinite(versionId)) return;
        ensureFacsimilePolling(versionId, { immediate: true });
    });

    /* ——— Custom events from parent blades ——— */
    document.addEventListener('workSelected', e=>{
        console.debug('workSelected event', e.detail);

        const rawWorkId = e.detail.workId;
        const rawAuthorId = e.detail.authorId;
        selectedWorkId = rawWorkId === undefined || rawWorkId === null || rawWorkId === '' ? null : Number(rawWorkId);
        authorId = rawAuthorId === undefined || rawAuthorId === null || rawAuthorId === '' ? null : Number(rawAuthorId);
        shortTitle     = e.detail.short_title || null;
        selectedAuthorLabel = e.detail.author_label || '';
        selectedWorkLabel   = e.detail.work_label || '';
        refreshUploadModalTitle();
        if (openUploadBtn) {
            openUploadBtn.disabled = !selectedWorkId;
        }
        if (!selectedWorkId) {
            currentViewerVersionId = null;
            if (uploadModalInstance) {
                uploadModalInstance.hide();
            }
            if (facModalInstance) {
                facModalInstance.hide();
            }
            facVersionId = null;
            facVersionName = '';
            const form = document.getElementById('upload-version-form');
            if (form) { form.reset(); }
            if ($fileInfo) { $fileInfo.textContent = ''; }
            refreshUploadModalTitle();
        }
        if (!selectedWorkId) {
            fetchVersions(null);
        }
    });
    document.addEventListener('editorialStepChanged', e => {
        const step = Number(e.detail?.step);
        if (step !== 2) return;
        const workId = e.detail?.workId ?? selectedWorkId;
        fetchVersions(workId);
    });
    document.addEventListener('versionsUpdated', e=>{
        if(e.detail.workId){
            selectedWorkId=e.detail.workId;
            fetchVersions(selectedWorkId, true);
        }
    });
    document.addEventListener('comparisonDeleted', e => {
        const workId = Number(e.detail?.workId || selectedWorkId);
        if (Number.isFinite(workId) && workId > 0) {
            selectedWorkId = workId;
            fetchVersions(workId, true);
        }
    });
    document.addEventListener('facsimiles:select', e => {
        const versionId = Number(e.detail?.versionId);
        currentViewerVersionId = Number.isFinite(versionId) && versionId > 0 ? versionId : null;
        updateViewerRowSelection();
    });

    /* ——— Upload submit ——— */
    document.getElementById('upload-version-form').addEventListener('submit',async ev=>{
        ev.preventDefault();
        if(!selectedWorkId) return alert('Veuillez sélectionner une œuvre.');
        const file        = $fileInput.files[0];
        const editionName = document.getElementById('editionName').value.trim();
        if(!file || !editionName) return alert('Merci de remplir tous les champs.');
        const txt = await file.text();
        if(txt.length>MAX_TXT_CHARACTERS) return alert(`Le fichier dépasse ${formatNumber(MAX_TXT_CHARACTERS)} caractères.`);

        const fd = new FormData();
        fd.append('work_id',selectedWorkId);
        fd.append('versionFile',file);
        fd.append('name',editionName);
        fd.append('original_encoding',detectedEncoding);
        if(shortTitle) fd.append('short_title',shortTitle);

        try{
            const res = await fetch(withBasePath('/api/versions'),{
                method:'POST',
                headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},
                body:fd
            });
            if(!res.ok){
                const raw = await res.text();
                let payload = null;
                try { payload = JSON.parse(raw); } catch (_) {}
                const msg = payload?.message
                    || payload?.error
                    || (payload?.errors ? Object.values(payload.errors).flat().join(' ') : null)
                    || 'Erreur de téléversement.';
                console.error(raw);
                return alert(msg);
            }
            await res.json();
            ev.target.reset();
            $fileInfo.textContent='';
            if (uploadModalInstance) { uploadModalInstance.hide(); }
            fetchVersions(selectedWorkId, true);
            document.dispatchEvent(new CustomEvent('versionsUpdated',{detail:{workId:selectedWorkId}}));
        }catch(err){
            console.error(err);
            alert('Erreur réseau ou serveur.');
        }
    });

    /* ——— Modal buttons ——— */
    document.getElementById('update-version-btn').addEventListener('click',updateVersionName);
    document.getElementById('confirm-delete-version').addEventListener('click',doDeleteVersion);
});
/*********************  API HELPERS  *********************/
function updateVersionsCount(count) {
    const countLabel = document.getElementById('versions-title-count');
    if (!countLabel) return;

    if (count === null || count === undefined) {
        countLabel.textContent = '(0)';
        return;
    }

    const numericCount = Number(count);
    const label = Number.isFinite(numericCount) ? numericCount : 0;
    countLabel.textContent = `(${label})`;
}

function openFacsimileUploadModal(version) {
    if (!facModalEl) {
        alert('Composant de téléversement indisponible.');
        return;
    }
    if (!version || !version.id) {
        alert('Version introuvable.');
        return;
    }
    facVersionId   = Number(version.id);
    facVersionName = version.name || '';
    setFacsimileUploadBusy(false);
    facUploadCancelRequested = false;
    facCurrentUploadVersionId = null;
    facUploadAbortController = null;

    if (facModalTitle) facModalTitle.textContent = `Téléverser des fac-similés — ${facVersionName}`;
    const versionShort = version.folder || '';
    const infoLabel = versionShort ? `${facVersionName} [${versionShort}]` : facVersionName;
    if (facModalInfo) facModalInfo.innerHTML = `Importez un dossier source contenant des images JPG/PNG ou des fichiers TIFF multipages.<br>Les fichiers seront traités dans l'ordre alphabétique-numérique de leur nom d'origine et, pour chaque TIFF multipage, les pages seront importées dans leur ordre interne.<br><strong>Version cible :</strong> ${infoLabel}`;
    facSpaceOk = true;
    facSpaceCheckToken += 1;
    if (facFileInput) facFileInput.value = '';
    if (facLog) facLog.textContent = '';
    if (facSummary) facSummary.textContent = '';
    if (facSpinner) facSpinner.style.display = 'none';
    if (facUploadBtn) facUploadBtn.disabled = true;

    document.dispatchEvent(new CustomEvent('facsimiles:select', { detail: { versionId: facVersionId, versionName: facVersionName } }));

    facModalInstance = facModalInstance || new bootstrap.Modal(facModalEl, { backdrop: 'static', keyboard: false });
    facModalInstance.show();
}

function revealFacsimilesForVersion(versionId, versionName = '') {
    const numericVersionId = Number(versionId);
    if (!Number.isFinite(numericVersionId) || numericVersionId <= 0) return;
    currentViewerVersionId = numericVersionId;
    updateViewerRowSelection();

    if (typeof window.openEditorialStep === 'function') {
        window.openEditorialStep(2, { focusPanel: false, scrollToJourney: false });
    }

    document.dispatchEvent(new CustomEvent('facsimiles:select', {
        detail: { versionId: numericVersionId, versionName: versionName || '' }
    }));

    const collapseEl = document.getElementById('facsimilesCollapse');
    if (collapseEl && !collapseEl.classList.contains('show') && window.bootstrap?.Collapse) {
        const collapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
        collapse.show();
    }
}

function updateViewerRowSelection() {
    const rows = document.querySelectorAll('#versions-list .version-table tbody tr[data-version-id]');
    rows.forEach((row) => {
        const isSelected = String(row.dataset.versionId || '') === String(currentViewerVersionId || '');
        row.classList.toggle('version-row-selected', isSelected);
        const button = row.querySelector('.version-viewer-btn');
        if (button) {
            button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
            button.setAttribute('title', isSelected ? 'Version actuellement affichée dans le lecteur' : 'Ouvrir le viewer');
        }
    });
}

function collectSelectedImages() {
    if (!facFileInput || !facFileInput.files) return [];
    return Array.from(facFileInput.files).filter(file => {
        if (file.type && file.type.startsWith('image/')) return true;
        return /\.(jpe?g|png|tiff?)$/i.test(file.name || '');
    });
}

async function estimateFacsimilePages(files) {
    if (!Array.isArray(files) || !files.length) return 0;
    const counts = await Promise.all(files.map(async (file) => {
        if (isTiffFile(file)) {
            const pages = await estimateTiffPages(file);
            return pages > 0 ? pages : 1;
        }
        return 1;
    }));
    return counts.reduce((sum, n) => sum + (Number.isFinite(n) ? n : 0), 0);
}

function isTiffFile(file) {
    const name = String(file?.name || '').toLowerCase();
    const type = String(file?.type || '').toLowerCase();
    return name.endsWith('.tif') || name.endsWith('.tiff') || type === 'image/tiff' || type === 'image/tif';
}

async function estimateTiffPages(file) {
    try {
        const buffer = await file.arrayBuffer();
        const view = new DataView(buffer);
        if (view.byteLength < 8) return 0;

        const bom = String.fromCharCode(view.getUint8(0), view.getUint8(1));
        const littleEndian = bom === 'II';
        const bigEndian = bom === 'MM';
        if (!littleEndian && !bigEndian) return 0;
        const le = littleEndian;

        const magic = view.getUint16(2, le);
        if (magic !== 42) return 0; // classic TIFF

        let ifdOffset = view.getUint32(4, le);
        let pages = 0;
        const seen = new Set();

        while (ifdOffset > 0 && ifdOffset + 6 <= view.byteLength) {
            if (seen.has(ifdOffset)) break;
            seen.add(ifdOffset);
            pages += 1;

            const entryCount = view.getUint16(ifdOffset, le);
            const nextPtrOffset = ifdOffset + 2 + (entryCount * 12);
            if (nextPtrOffset + 4 > view.byteLength) break;
            ifdOffset = view.getUint32(nextPtrOffset, le);
        }

        return pages;
    } catch (_) {
        return 0;
    }
}

function isDsStoreFile(file) {
    const rawName = file?.name || '';
    const rawPath = file?.webkitRelativePath || '';
    const path = String(rawPath || rawName);
    const parts = path.split('/');
    const base = parts.length ? parts[parts.length - 1] : path;
    return base === '.DS_Store';
}

function updateFacUploadButtonState(imageCount) {
    if (!facUploadBtn) return;
    facUploadBtn.disabled = facUploadInProgress || imageCount === 0 || !facSpaceOk;
}

function setFacsimileUploadBusy(isBusy) {
    facUploadInProgress = !!isBusy;
    if (facFileInput) {
        facFileInput.disabled = facUploadInProgress;
    }
    if (facCancelBtn) {
        facCancelBtn.classList.toggle('d-none', !facUploadInProgress);
        facCancelBtn.disabled = !facUploadInProgress;
    }
    if (facModalEl) {
        facModalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach((btn) => {
            btn.disabled = facUploadInProgress;
            btn.classList.toggle('disabled', facUploadInProgress);
        });
    }
}

async function cancelCurrentFacsimileUpload() {
    if (!facUploadInProgress) return;
    const versionId = Number(facCurrentUploadVersionId || facVersionId);
    if (!Number.isFinite(versionId) || versionId <= 0) return;

    facUploadCancelRequested = true;
    if (facCancelBtn) {
        facCancelBtn.disabled = true;
    }
    if (facLog) {
        facLog.textContent = 'Annulation en cours... restauration de la série précédente si disponible.';
    }
    if (facSummary) {
        facSummary.className = 'small text-warning';
        facSummary.textContent = 'Annulation de l’import en cours...';
    }

    if (facUploadAbortController) {
        facUploadAbortController.abort();
    }

    try {
        const res = await fetch(withBasePath(`/api/versions/${versionId}/facsimiles/cancel-upload?restore_previous=1`), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        const payload = await readJsonResponse(res);
        const restored = !!payload?.restored_previous;
        const msg = payload?.message || (restored
            ? 'Import annulé et série précédente restaurée.'
            : 'Import annulé. Les fichiers partiels ont été supprimés.');

        setFacsimileUploadBusy(false);
        updateFacUploadButtonState(collectSelectedImages().length);
        facCurrentUploadVersionId = null;

        if (facSpinner) facSpinner.style.display = 'none';
        if (facSummary) {
            facSummary.className = restored ? 'small text-success' : 'small text-warning';
            facSummary.textContent = msg;
        }
        if (facLog) facLog.textContent = '';
        document.dispatchEvent(new CustomEvent('facsimilesUploaded', {
            detail: { versionId }
        }));
    } catch (err) {
        setFacsimileUploadBusy(false);
        updateFacUploadButtonState(collectSelectedImages().length);
        facCurrentUploadVersionId = null;
        if (facSummary) {
            facSummary.className = 'small text-danger';
            facSummary.textContent = `⚠️ Échec de l'annulation: ${err.message || 'erreur inconnue'}`;
        }
    } finally {
        facUploadAbortController = null;
    }
}

async function checkFacsimileSpace(totalBytes, imageCount) {
    const token = ++facSpaceCheckToken;
    if (!totalBytes || totalBytes <= 0) {
        facSpaceOk = true;
        updateFacUploadButtonState(imageCount);
        return;
    }
    if (facTotalSizeEl) {
        facTotalSizeEl.textContent = `Volume total estimé : ${formatBytes(totalBytes)} — vérification de l’espace disque…`;
    }
    try {
        const res = await fetch(withBasePath(`/api/facsimiles/space?required_bytes=${encodeURIComponent(totalBytes)}`), {
            headers: { 'Accept': 'application/json' }
        });
        const data = await readJsonResponse(res);
        if (data.status && data.status !== 'ok') {
            throw new Error(data.message || 'Impossible de vérifier l’espace disque.');
        }
        if (token !== facSpaceCheckToken) return;
        const free = Number(data.free_bytes ?? 0);
        const remaining = Number(data.remaining_bytes ?? (free - totalBytes));
        const minFree = Number(data.min_free_bytes ?? 0);
        const ok = (data.ok !== undefined) ? !!data.ok : remaining >= minFree;
        facSpaceOk = ok;
        if (facTotalSizeEl) {
            facTotalSizeEl.textContent = `Volume total estimé : ${formatBytes(totalBytes)} — espace libre ${formatBytes(free)} (reste ${formatBytes(remaining)} après import)`;
        }
        if (!ok && facSummary) {
            facSummary.className = 'small text-danger';
            facSummary.textContent = `⚠️ Espace disque insuffisant. Il faut conserver au moins ${formatBytes(minFree)} libres après import.`;
        } else if (ok && facSummary && facSummary.textContent.startsWith('⚠️ Espace disque insuffisant')) {
            facSummary.className = 'small text-muted';
            facSummary.textContent = '';
        }
        updateFacUploadButtonState(imageCount);
    } catch (err) {
        if (token !== facSpaceCheckToken) return;
        facSpaceOk = false;
        updateFacUploadButtonState(imageCount);
        if (facSummary) {
            facSummary.className = 'small text-danger';
            facSummary.textContent = `⚠️ ${err.message || 'Impossible de vérifier l’espace disque.'}`;
        }
    }
}

async function readJsonResponse(res) {
    const text = await res.text();
    if (!text) {
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }
        return {};
    }

    try {
        const json = JSON.parse(text);
        if (!res.ok) {
            let serverMessage = null;
            if (typeof json.message === 'string' && json.message.trim()) {
                serverMessage = json.message.trim();
            } else if (typeof json.error === 'string' && json.error.trim()) {
                serverMessage = json.error.trim();
            } else if (json.error && typeof json.error === 'object') {
                const parts = Object.values(json.error)
                    .flat()
                    .map(item => String(item).trim())
                    .filter(Boolean);
                if (parts.length) {
                    serverMessage = parts.join('\n');
                }
            } else if (json.errors && typeof json.errors === 'object') {
                const parts = Object.values(json.errors)
                    .flat()
                    .map(item => String(item).trim())
                    .filter(Boolean);
                if (parts.length) {
                    serverMessage = parts.join('\n');
                }
            }

            throw new Error(serverMessage ?? `HTTP ${res.status}`);
        }
        return json;
    } catch (parseErr) {
        const preview = text.replace(/<[^>]*>/g, ' ')
                            .replace(/\s+/g, ' ')
                            .trim()
                            .slice(0, 200);
        throw new Error(res.ok ? preview : `HTTP ${res.status} — ${preview || 'Réponse invalide'}`);
    }
}

async function purgeFacsimiles(versionId, { reason = 'clear' } = {}){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return;
    const version = versionsCache.get(id);
    const label = version?.name ? `« ${version.name} »` : `ID ${id}`;
    const isCancel = reason === 'cancel';
    const confirmMessage = isCancel
        ? `Interrompre le traitement des fac-similés pour ${label} ?\nLes images déjà importées seront supprimées.`
        : `Supprimer tous les fac-similés pour ${label} ?\nLes originaux, miniatures et manifestes seront définitivement supprimés.`;
    if (!confirm(confirmMessage)) {
        return;
    }

    const state = facsimileRowState.get(id);
    const buttonsToDisable = [];
    if (state?.viewBtn) {
        buttonsToDisable.push(state.viewBtn);
    }
    if (state?.uploadBtn) {
        buttonsToDisable.push(state.uploadBtn);
    }
    if (!isCancel && state?.clearBtn) {
        buttonsToDisable.push(state.clearBtn);
    }
    buttonsToDisable.forEach(btn => btn.disabled = true);

    try {
        const res = await fetch(withBasePath(`/api/versions/${id}/facsimiles`), {
            method : 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const payload = await readJsonResponse(res);
        stopFacsimilePolling(id);
        if (payload?.facsimiles) {
            const cached = versionsCache.get(id) ?? {};
            cached.facsimiles = payload.facsimiles;
            versionsCache.set(id, cached);
            renderFacsimileStatus(id, payload.facsimiles);
        } else {
            await requestFacsimileProgress(id);
        }
        if (Number.isFinite(selectedWorkId)) {
            await fetchVersions(selectedWorkId, true);
        }
    } catch (err) {
        console.error(err);
        alert(err.message || (isCancel
            ? 'Impossible d’annuler le traitement des fac-similés.'
            : 'Impossible de supprimer les fac-similés.'));
    } finally {
        buttonsToDisable.forEach(btn => { btn.disabled = false; });
    }
}

async function uploadLignesFile(versionId, file){
    const id = Number(versionId);
    if (!Number.isFinite(id) || !file) return;

    const state = facsimileRowState.get(id);
    if (state?.lignesUploadBtn) state.lignesUploadBtn.disabled = true;
    if (state?.lignesUploadSpinner) state.lignesUploadSpinner.hidden = false;

    try{
        const form = new FormData();
        form.append('lignes', file);
        const res = await fetch(withBasePath(`/api/versions/${id}/lignes`), {
            method : 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body   : form
        });
        const payload = await readJsonResponse(res);
        const progress = payload?.progress ?? null;
        const paginationInfo = payload?.pagination ?? versionsCache.get(id)?.pagination ?? null;
        renderLignesStatus(id, payload.lignes ?? null, progress, paginationInfo);
        if (versionsCache.has(id)) {
            const cached = versionsCache.get(id);
            cached.lignes = payload.lignes ?? cached.lignes ?? null;
            cached.page_marker_progress = progress;
            if (Object.prototype.hasOwnProperty.call(payload ?? {}, 'pagination')) {
                cached.pagination = payload.pagination;
            }
            versionsCache.set(id, cached);
        }
        if (progress?.status && ['queued', 'running'].includes(progress.status)) {
            ensureLignesPolling(id, { immediate: true });
        } else if (progress?.status) {
            stopLignesPolling(id);
        } else {
            // Progress file may not be immediately available; poll once to confirm state.
            ensureLignesPolling(id, { immediate: true });
        }
    }catch(err){
        console.error(err);
        alert(err.message || 'Échec de l’import du fichier _lignes.');
    }finally{
        const progressStatus = versionsCache.get(id)?.page_marker_progress?.status;
        const stillRunning = !!(progressStatus && ['queued', 'running'].includes(progressStatus));
        if (state?.lignesUploadBtn) state.lignesUploadBtn.disabled = stillRunning;
        if (state?.lignesUploadSpinner) state.lignesUploadSpinner.hidden = !stillRunning;
    }
}

async function clearVersionPageMarkers(versionId){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return;
    if (!confirm('Supprimer tous les marqueurs de pagination de cette version ?')) return;

    const state = facsimileRowState.get(id);
    const controls = [state?.lignesUploadBtn, state?.clearMarkersBtn].filter(Boolean);
    controls.forEach(btn => btn.disabled = true);

    try {
        const res = await fetch(withBasePath(`/api/versions/${id}/page-markers`), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const payload = await readJsonResponse(res);
        const paginationInfo = payload?.pagination ?? null;
        if (versionsCache.has(id)) {
            const cached = versionsCache.get(id) ?? {};
            cached.pagination = paginationInfo;
            versionsCache.set(id, cached);
        }
        renderLignesStatus(id, versionsCache.get(id)?.lignes ?? null, versionsCache.get(id)?.page_marker_progress ?? null, paginationInfo);
        if (Number.isFinite(selectedWorkId)) {
            await fetchVersions(selectedWorkId, true);
        }
    } catch (err) {
        console.error(err);
        alert(err.message || 'Impossible de supprimer les marqueurs de pagination.');
    } finally {
        const progressStatus = versionsCache.get(id)?.page_marker_progress?.status;
        const stillRunning = !!(progressStatus && ['queued', 'running'].includes(progressStatus));
        if (state?.lignesUploadBtn) state.lignesUploadBtn.disabled = stillRunning;
        if (state?.clearMarkersBtn) state.clearMarkersBtn.disabled = false;
    }
}

async function createPaginationFromPb(versionId){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return;

    const res = await fetch(withBasePath(`/api/versions/${id}/pagination/from-pb`), {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    });

    const payload = await readJsonResponse(res);
    if (payload?.status === 'ok') {
        if (versionsCache.has(id)) {
            const cached = versionsCache.get(id);
            cached.pagination = { status: 'generated_from_pb', count: payload.count };
            versionsCache.set(id, cached);
        }
        alert(`Sidecar pagination créé (${payload.count} balises <pb>).`);
    } else if (payload?.status === 'empty') {
        alert(payload.message || 'Aucune balise <pb> trouvée.');
    } else {
        alert(payload?.message || 'Impossible de générer le sidecar depuis les <pb>.');
    }
}

async function deleteLignesFile(versionId){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return;
    if (!confirm('Supprimer les données de pagination pour cette version ?')) return;

    try {
        const res = await fetch(withBasePath(`/api/versions/${id}/lignes/file`), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const payload = await readJsonResponse(res);
        const progress = payload?.progress ?? null;
        renderLignesStatus(id, payload.lignes ?? null, progress, payload?.pagination ?? null);
        if (versionsCache.has(id)) {
            const cached = versionsCache.get(id) ?? {};
            cached.lignes = payload.lignes ?? null;
            cached.page_marker_progress = progress ?? null;
            if (Object.prototype.hasOwnProperty.call(payload ?? {}, 'pagination')) {
                cached.pagination = payload.pagination;
            }
            versionsCache.set(id, cached);
        }
        stopLignesPolling(id);
        if (Number.isFinite(selectedWorkId)) {
            document.dispatchEvent(new CustomEvent('versionsUpdated', { detail: { workId: selectedWorkId } }));
        } else {
            document.dispatchEvent(new CustomEvent('versionsUpdated', { detail: { workId: null } }));
        }
        if (payload?.message) {
            alert(payload.message);
        }
    } catch (err) {
        console.error(err);
        alert(err.message || 'Impossible de supprimer le fichier _lignes.');
    } finally {
        const progressStatus = versionsCache.get(id)?.page_marker_progress?.status;
        const state = facsimileRowState.get(id);
        if (state?.lignesUploadBtn) {
            state.lignesUploadBtn.disabled = !!(progressStatus && ['queued','running'].includes(progressStatus));
        }
    }
}

async function cancelLignesProcessing(versionId){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return;
    if (!confirm('Annuler le traitement des balises de pagination pour cette version ?')) return;

    try {
        const res = await fetch(withBasePath(`/api/versions/${id}/lignes`), {
            method : 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const payload = await readJsonResponse(res);
        const progress = payload?.progress ?? null;
        const cachedBefore = versionsCache.get(id)?.lignes ?? null;
        const paginationInfo = payload?.pagination ?? versionsCache.get(id)?.pagination ?? null;
        renderLignesStatus(id, payload.lignes ?? cachedBefore, progress, paginationInfo);
        if (versionsCache.has(id)) {
            const cached = versionsCache.get(id);
            cached.page_marker_progress = progress;
            if (Object.prototype.hasOwnProperty.call(payload ?? {}, 'pagination')) {
                cached.pagination = payload.pagination;
            }
            versionsCache.set(id, cached);
        }
        stopLignesPolling(id);
        if (payload?.message) {
            alert(payload.message);
        }
    } catch (err) {
        console.error(err);
        alert(err.message || 'Impossible d’annuler le traitement _lignes.');
    }
}

let actionButtonsTooltips = [];

async function fetchVersions(workId, force = false){

    actionButtonsTooltips.forEach(tt => tt.dispose());
    actionButtonsTooltips = [];
    
    const list = document.getElementById('versions-list');
    if (!workId) {
        list.innerHTML = '<li class="list-group-item">Sélectionner une œuvre pour voir les versions</li>';
        updateVersionsCount(null);
        versionsCache = new Map();
        facsimileRowState.clear();
        facsimilePollers.forEach((_, id) => stopFacsimilePolling(id));
        lignesPollers.forEach((_, id) => stopLignesPolling(id));
        setVersionsLoading(false);
        return;
    }
    setVersionsLoading(true);
    list.innerHTML='<div class="text-muted p-2">Loading versions…</div>';
    try{
        const data = await getVersionsForWork(workId, { force });
        list.innerHTML='';
        const versions = Array.isArray(data) ? data : [];
        versionsCache = new Map(versions.map(v => [Number(v.id), v]));
        facsimileRowState.clear();
        if(versions.length===0) {
            updateVersionsCount(0);
            list.innerHTML='<div class="text-muted p-2 text-center">Aucune version textuelle n’est encore associée à cette œuvre. Cliquez sur « Téléverser une version » pour en ajouter une.</div>';
            facsimilePollers.forEach((_, id) => stopFacsimilePolling(id));
            lignesPollers.forEach((_, id) => stopLignesPolling(id));
            return;
        }

        updateVersionsCount(versions.length);

        const table = document.createElement('table');
        table.className='table table-bordered table-sm version-table';
        table.classList.toggle('compact-details', !showVersionDetails);
        table.innerHTML=`<thead class="table-light"><tr><th class="version-viewer-col"></th><th>ID</th><th>Dénomination</th><th>Dossier</th><th>Signes</th><th class="text-center">Texte</th><th class="text-center">TEI-XML</th><th>Fac-similés</th><th class="text-center">Pagination</th><th class="text-center">Actions</th></tr></thead><tbody></tbody>`;
        const tbody = table.querySelector('tbody');
        const activeFacsimileIds = new Set();
        versions.forEach(v=>{
            const tr = document.createElement('tr');
            tr.dataset.versionId = String(v.id);
            const facsimileData = v.facsimiles && typeof v.facsimiles === 'object' ? v.facsimiles : null;
            const sourceCount = Number(facsimileData?.source_count ?? 0);
            const shortFolder = (v.folder || '').split('/').pop();

            const tdViewer = document.createElement('td');
            tdViewer.className = 'version-viewer-col align-middle';
            const viewerBtn = document.createElement('button');
            viewerBtn.type = 'button';
            viewerBtn.className = 'btn btn-outline-secondary version-viewer-btn';
            viewerBtn.title = sourceCount > 0
                ? 'Ouvrir le lecteur fac-similé synchronisé'
                : 'Ouvrir le panneau fac-similés pour cette version';
            viewerBtn.setAttribute('aria-label', 'Ouvrir le viewer');
            viewerBtn.dataset.viewerVersionId = String(v.id);
            viewerBtn.dataset.viewerVersionName = v.name || '';
            viewerBtn.innerHTML = '<i class="bi bi-eye"></i>';
            tdViewer.appendChild(viewerBtn);
            const viewerState = document.createElement('div');
            viewerState.className = 'version-viewer-state';
            viewerState.textContent = 'Affichée';
            tdViewer.appendChild(viewerState);
            tr.appendChild(tdViewer);

            const tdId = document.createElement('td');
            tdId.textContent = v.id;
            tr.appendChild(tdId);

            const tdName = document.createElement('td');
            tdName.className = 'version-name-cell';
            const nameText = document.createElement('span');
            nameText.className = 'version-name-label';
            nameText.textContent = v.name;
            tdName.appendChild(nameText);

            const editTrigger = document.createElement('button');
            editTrigger.type = 'button';
            editTrigger.className = 'btn btn-link btn-sm text-muted ms-2 p-0 align-baseline';
            editTrigger.innerHTML = '&#9998;'; // pencil
            editTrigger.title = 'Modifier la désignation';
            editTrigger.addEventListener('click', () => openEditModal(v));
            tdName.appendChild(editTrigger);

            tr.appendChild(tdName);

            const folderCell = document.createElement('td');
            folderCell.textContent = shortFolder;
            tr.appendChild(folderCell);

            const tdChars = document.createElement('td');
            tdChars.className = 'text-muted';
            tdChars.dataset.versionId = String(v.id);
            if (typeof v.text_length === 'number') {
                tdChars.textContent = v.text_length.toLocaleString('fr-FR');
                tdChars.title = 'Nombre de signes dans la version';
            } else if (v.text_available) {
                tdChars.textContent = '…';
                tdChars.title = 'Chargement du nombre de signes';
                requestTextLength(v.id, tdChars);
            } else {
                tdChars.textContent = 'n/a';
                tdChars.title = 'Fichier texte indisponible';
            }
            tr.appendChild(tdChars);

            const textCell = document.createElement('td');
            textCell.className = 'text-center align-middle';
            if (v.text_available && v.text_url) {
                const textGroup = document.createElement('div');
                textGroup.className = 'btn-group btn-group-sm versions-action-group';
                const textLink = document.createElement('a');
                textLink.href = v.text_url;
                textLink.className = 'btn btn-outline-secondary versions-icon-btn';
                textLink.setAttribute('data-bs-toggle', 'tooltip');
                textLink.setAttribute('title', 'Télécharger le fichier texte');
                textLink.setAttribute('aria-label', 'Télécharger le fichier texte');
                textLink.innerHTML = '<i class="bi bi-download"></i>';
                textGroup.appendChild(textLink);
                textCell.appendChild(textGroup);
            } else {
                textCell.innerHTML = '<span class="text-muted">—</span>';
            }
            tr.appendChild(textCell);

            const xmlCell = document.createElement('td');
            xmlCell.className = 'text-center align-middle';
            if (v.xml_available && v.xml_url) {
                const xmlGroup = document.createElement('div');
                xmlGroup.className = 'btn-group btn-group-sm versions-action-group';
                const xmlLink = document.createElement('a');
                xmlLink.href = v.xml_url;
                xmlLink.className = 'btn btn-outline-secondary versions-icon-btn';
                xmlLink.setAttribute('data-bs-toggle', 'tooltip');
                xmlLink.setAttribute('title', 'Télécharger le fichier TEI-XML');
                xmlLink.setAttribute('aria-label', 'Télécharger le fichier TEI-XML');
                xmlLink.innerHTML = '<i class="bi bi-download"></i>';
                xmlGroup.appendChild(xmlLink);
                xmlCell.appendChild(xmlGroup);
            } else {
                xmlCell.innerHTML = '<span class="text-muted">—</span>';
            }
            tr.appendChild(xmlCell);

            const tdFac = document.createElement('td');
            tdFac.className = 'align-middle';
            const publishedCount = Number(facsimileData?.published_count ?? 0);
            const queueCount = Number(facsimileData?.queue_count ?? 0);
            const totalExpected = Number(facsimileData?.total_expected ?? (sourceCount + queueCount));

            const facWrap = document.createElement('div');
            facWrap.className = 'versions-inline-cell versions-inline-cell--facsimiles';

            const facCountPill = document.createElement('span');
            facCountPill.className = 'badge rounded-pill bg-light text-muted border versions-count-pill';
            facCountPill.textContent = `${sourceCount.toLocaleString('fr-FR')} fac-similé(s)`;
            facWrap.appendChild(facCountPill);

            const facButtons = document.createElement('div');
            facButtons.className = 'btn-group btn-group-sm versions-action-group';

            const btnFacView = document.createElement('button');
            btnFacView.type = 'button';
            btnFacView.className = 'btn btn-outline-secondary versions-icon-btn';
            btnFacView.innerHTML = '<i class="bi bi-eye"></i>';
            btnFacView.disabled = sourceCount === 0;
            btnFacView.title = sourceCount === 0 ? 'Aucune image à afficher' : 'Afficher la galerie de fac-similés';
            btnFacView.setAttribute('aria-label', 'Voir les fac-similés');
            btnFacView.addEventListener('click', () => {
                if (btnFacView.dataset.facsimileLoading === '1') {
                    requestFacsimileProgress(v.id);
                }
                revealFacsimilesForVersion(v.id, v.name);
            });
            facButtons.appendChild(btnFacView);

            const btnFacUpload = document.createElement('button');
            btnFacUpload.type = 'button';
            btnFacUpload.className = 'btn btn-outline-primary versions-icon-btn';
            const facUploadIcon = document.createElement('span');
            facUploadIcon.className = 'versions-btn-icon';
            facUploadIcon.innerHTML = '<i class="bi bi-upload"></i>';
            const facUploadSpinner = document.createElement('span');
            facUploadSpinner.className = 'versions-btn-activity versions-btn-spinner';
            facUploadSpinner.hidden = true;
            facUploadSpinner.setAttribute('aria-hidden', 'true');
            facUploadSpinner.innerHTML = '<span class="spinner-border" role="status" aria-hidden="true"></span>';
            btnFacUpload.appendChild(facUploadIcon);
            btnFacUpload.appendChild(facUploadSpinner);
            btnFacUpload.title = 'Importer de nouveaux fac-similés';
            btnFacUpload.setAttribute('aria-label', 'Importer des fac-similés');
            btnFacUpload.addEventListener('click', () => {
                if (!v.is_legacy) {
                    openFacsimileUploadModal(v);
                }
            });
            facButtons.appendChild(btnFacUpload);

            const btnFacClear = document.createElement('button');
            btnFacClear.type = 'button';
            btnFacClear.className = 'btn btn-outline-danger versions-icon-btn';
            btnFacClear.innerHTML = '<i class="bi bi-x-lg"></i>';
            const hasAnyFacsimiles = (sourceCount + publishedCount + queueCount) > 0;
            btnFacClear.disabled = !hasAnyFacsimiles;
            btnFacClear.title = hasAnyFacsimiles
                ? 'Supprimer tous les fac-similés'
                : 'Aucune image à supprimer';
            btnFacClear.setAttribute('aria-label', 'Supprimer les fac-similés');
            btnFacClear.addEventListener('click', () => {
                if (!v.is_legacy) {
                    purgeFacsimiles(v.id, { reason: 'clear' });
                }
            });
            facButtons.appendChild(btnFacClear);
            facWrap.appendChild(facButtons);
            tdFac.appendChild(facWrap);

            const tdCompletion = document.createElement('td');
            tdCompletion.className = 'align-middle text-center';
            const completionSwitch = document.createElement('div');
            completionSwitch.className = 'form-check form-switch d-inline-flex align-items-center justify-content-center gap-2 m-0 versions-switch-wrap';
            const completionToggle = document.createElement('input');
            completionToggle.type = 'checkbox';
            completionToggle.role = 'switch';
            completionToggle.className = 'form-check-input';
            completionToggle.id = `version-${v.id}-pagination-done`;
            completionToggle.checked = !!v.pagination_done;
            completionToggle.title = 'Cochez lorsque la pagination est validée pour cette version';
            completionToggle.setAttribute('aria-label', 'Marquer la pagination validée');
            completionToggle.addEventListener('change', () => togglePaginationDone(v.id, completionToggle.checked));
            const completionLabel = document.createElement('label');
            completionLabel.className = 'form-check-label small text-muted';
            completionLabel.setAttribute('for', completionToggle.id);
            completionLabel.textContent = 'Validée';
            completionSwitch.appendChild(completionToggle);
            completionSwitch.appendChild(completionLabel);

            const lignesWrap = document.createElement('div');
            lignesWrap.className = 'versions-inline-cell versions-inline-cell--pagination';

            const markerCountPill = document.createElement('span');
            markerCountPill.className = 'badge rounded-pill bg-light text-muted border versions-count-pill';
            markerCountPill.textContent = '0 marqueur(s)';
            lignesWrap.appendChild(markerCountPill);

            const lignesActions = document.createElement('div');
            lignesActions.className = 'btn-group btn-group-sm versions-action-group';
            const lignesInput = document.createElement('input');
            lignesInput.type = 'file';
            lignesInput.accept = '.txt,text/plain';
            lignesInput.style.display = 'none';
            const lignesUploadBtn = document.createElement('button');
            lignesUploadBtn.type = 'button';
            lignesUploadBtn.className = 'btn btn-outline-primary versions-icon-btn';
            const lignesUploadIcon = document.createElement('span');
            lignesUploadIcon.className = 'versions-btn-icon';
            lignesUploadIcon.innerHTML = '<i class="bi bi-upload"></i>';
            const lignesUploadSpinner = document.createElement('span');
            lignesUploadSpinner.className = 'versions-btn-activity versions-btn-spinner';
            lignesUploadSpinner.hidden = true;
            lignesUploadSpinner.setAttribute('aria-hidden', 'true');
            lignesUploadSpinner.innerHTML = '<span class="spinner-border" role="status" aria-hidden="true"></span>';
            lignesUploadBtn.appendChild(lignesUploadIcon);
            lignesUploadBtn.appendChild(lignesUploadSpinner);
            lignesUploadBtn.title = 'Importer un fichier _lignes';
            lignesActions.appendChild(lignesUploadBtn);
            lignesActions.appendChild(lignesInput);

            const clearMarkersBtn = document.createElement('button');
            clearMarkersBtn.type = 'button';
            clearMarkersBtn.className = 'btn btn-outline-danger versions-icon-btn';
            clearMarkersBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
            clearMarkersBtn.title = 'Supprimer tous les marqueurs de pagination';
            clearMarkersBtn.setAttribute('aria-label', 'Supprimer tous les marqueurs de pagination');
            clearMarkersBtn.addEventListener('click', () => {
                if (v.is_legacy) return;
                clearVersionPageMarkers(v.id);
            });
            lignesActions.appendChild(clearMarkersBtn);
            lignesWrap.appendChild(lignesActions);
            lignesWrap.appendChild(completionSwitch);

            tdCompletion.appendChild(lignesWrap);

            if (v.is_legacy) {
                lignesUploadBtn.disabled = true;
                lignesUploadBtn.classList.add('legacy-disabled');
                lignesUploadBtn.title = 'Import _lignes désactivé pour les versions legacy.';
                clearMarkersBtn.disabled = true;
                clearMarkersBtn.classList.add('legacy-disabled');
                clearMarkersBtn.title = 'Suppression des marqueurs désactivée pour les versions legacy.';
            }

            lignesUploadBtn.addEventListener('click', () => {
                if (v.is_legacy) return;
                lignesInput.value = '';
                lignesInput.click();
            });
            lignesInput.addEventListener('change', () => {
                if (v.is_legacy) return;
                if (!lignesInput.files || !lignesInput.files.length) return;
                uploadLignesFile(v.id, lignesInput.files[0]);
            });

            const rowState = {
                isLegacy: v.is_legacy,
                viewBtn: btnFacView,
                facCountPill,
                uploadBtn: btnFacUpload,
                uploadBtnIcon: facUploadIcon,
                uploadBtnSpinner: facUploadSpinner,
                clearBtn: btnFacClear,
                completionToggle,
                markerCountPill,
                lignesUploadBtn,
                lignesUploadIcon,
                lignesUploadSpinner,
                clearMarkersBtn,
            };

            facsimileRowState.set(Number(v.id), rowState);

            tr.appendChild(tdFac);
            tr.appendChild(tdCompletion);

            renderPaginationDoneState(v.id, v);
            if (facsimileData) {
                renderFacsimileStatus(v.id, {
                    source_count: sourceCount,
                    published_count: publishedCount,
                    queue_count: queueCount,
                    total_expected: totalExpected,
                    processing: queueCount > 0
                });
            } else {
                renderFacsimileStatus(v.id, { loading: true });
                requestFacsimileProgress(v.id);
            }

            if (facsimileData && queueCount > 0) {
                ensureFacsimilePolling(v.id);
            } else {
                stopFacsimilePolling(v.id);
            }

            renderLignesStatus(v.id, v.lignes ?? null, v.page_marker_progress ?? null, v.pagination ?? null);
            if (v.page_marker_progress?.status && ['queued', 'running'].includes(v.page_marker_progress.status)) {
                ensureLignesPolling(v.id, { immediate: true });
            }

            activeFacsimileIds.add(Number(v.id));
            const tdActions = document.createElement('td');
            tdActions.className='text-center align-middle';
            const currentBaseUrl = `${window.location.pathname}${window.location.search}`;
            const returnTo = `${currentBaseUrl}#etape-2`;
            const editorUrl = withBasePath(`/version/${v.id}/editor?return_to=${encodeURIComponent(returnTo)}`);

            const canEditXml = (typeof v.xml_available === 'boolean') ? v.xml_available : true;
            let editorControl = null;
            if (canEditXml) {
                const btnEditor = document.createElement('a');
                btnEditor.href = editorUrl;
                btnEditor.setAttribute('data-bs-toggle', 'tooltip');
                btnEditor.className = 'btn btn-outline-primary';
                btnEditor.textContent = 'Edition';
                const tooltipEditor = new bootstrap.Tooltip(
                    btnEditor,
                    {
                        title: 'Éditer la version',
                        delay: { show: 500, hide: 0 },
                        trigger: 'hover'
                    }
                );
                actionButtonsTooltips.push(tooltipEditor);
                editorControl = btnEditor;
            } else {
                const wrapper = document.createElement('span');
                wrapper.className = 'd-inline-block';
                wrapper.setAttribute('data-bs-toggle', 'tooltip');
                wrapper.setAttribute('title', 'Fichier texte non disponible pour cette version.');
                const disabledBtn = document.createElement('span');
                disabledBtn.className = 'btn btn-outline-secondary disabled';
                disabledBtn.setAttribute('tabindex', '-1');
                disabledBtn.setAttribute('aria-disabled', 'true');
                disabledBtn.textContent = 'Edition';
                wrapper.appendChild(disabledBtn);
                const tooltipEditor = new bootstrap.Tooltip(
                    wrapper,
                    {
                        title: 'Fichier texte non disponible pour cette version.',
                        delay: { show: 500, hide: 0 },
                        trigger: 'hover'
                    }
                );
                actionButtonsTooltips.push(tooltipEditor);
                editorControl = wrapper;
            }

            const btnDel = document.createElement('button');
            btnDel.className = 'btn btn-outline-danger versions-icon-btn';
            btnDel.innerHTML = '<i class="bi bi-x-lg"></i>';
            btnDel.setAttribute('data-bs-toggle', 'tooltip');
            const deleteDisabled = !!(v.is_legacy || v.is_in_use);
            if (!deleteDisabled) {
                btnDel.addEventListener('click',()=>confirmDeleteVersion(v));
            }
            const deleteTitle = v.is_legacy
                ? 'La suppression est désactivée pour les versions legacy.'
                : (v.is_in_use
                    ? 'La suppression est désactivée car cette version est utilisée dans une comparaison.'
                    : 'Supprimer la version');
            if (deleteDisabled) {
                btnDel.disabled = true;
                btnDel.classList.add('legacy-disabled');
            }
            btnDel.title = deleteTitle;
            const tooltipDel = new bootstrap.Tooltip(
                btnDel,
                {
                    title: deleteTitle,
                    delay: { show: 500, hide: 0 },
                    trigger: 'hover'
                }
            );
            actionButtonsTooltips.push(tooltipDel);
            const btnGroup = document.createElement('div');
            btnGroup.className = 'btn-group btn-group-sm versions-action-group';
            btnGroup.role = 'group';
            btnGroup.ariaLabel = 'Version utility buttons';

            if (editorControl) {
                btnGroup.appendChild(editorControl);
            }
            btnGroup.appendChild(btnDel);
            tdActions.appendChild(btnGroup);

            tr.appendChild(tdActions);
            tbody.appendChild(tr);
        });
        tbody.addEventListener('click', (event) => {
            const button = event.target.closest('.version-viewer-btn');
            if (!button) return;
            revealFacsimilesForVersion(button.dataset.viewerVersionId, button.dataset.viewerVersionName || '');
        });
        list.appendChild(table);
        updateViewerRowSelection();
        facsimilePollers.forEach((_, id) => {
            if (!activeFacsimileIds.has(id)) {
                stopFacsimilePolling(id);
            }
        });
    }catch(err){
        console.error(err);
        versionsCache = new Map();
        updateVersionsCount(null);
        list.innerHTML='<div class="text-danger p-2">Failed to load versions</div>';
        facsimilePollers.forEach((_, id) => stopFacsimilePolling(id));
    } finally {
        setVersionsLoading(false);
    }
}
function openEditModal(v){
    document.getElementById('edit-version-id').value = v.id;
    document.getElementById('edit-version-name').value = v.name;
    new bootstrap.Modal(document.getElementById('editVersionModal')).show();
}
async function updateVersionName(){
    const id   = document.getElementById('edit-version-id').value;
    const name = document.getElementById('edit-version-name').value.trim();
    if(!name) return alert('Edition Name cannot be empty');
    try{
        const res = await fetch(withBasePath(`/api/versions/${id}`),{
            method:'PUT',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
            body:JSON.stringify({name})
        });
        if(!res.ok) throw new Error(res.statusText);
        await res.json();
        bootstrap.Modal.getInstance(document.getElementById('editVersionModal')).hide();
        fetchVersions(selectedWorkId, true);
        document.dispatchEvent(new CustomEvent('versionsUpdated',{detail:{workId:selectedWorkId}}));
    }catch(err){
        console.error(err);
        alert('Could not update version name.');
    }
}
/*********************  DELETE FLOW  *********************/
function confirmDeleteVersion(v){
    versionToDelete=v.id;
    new bootstrap.Modal(document.getElementById('deleteVersionConfirm')).show();
}
async function doDeleteVersion(){
    if(!versionToDelete) return;
    const modalEl = document.getElementById('deleteVersionConfirm');
    try{
        const res = await fetch(withBasePath(`/api/versions/${versionToDelete}`),{
            method:'DELETE',
            headers:{
                'Accept':'application/json',
                'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const payload = await readJsonResponse(res);
        if (modalEl) {
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
        }
        versionToDelete = null;
        fetchVersions(selectedWorkId, true);
        document.dispatchEvent(new CustomEvent('versionsUpdated',{detail:{workId:selectedWorkId}}));
    }catch(err){
        console.error(err);
        alert(err.message || 'Impossible de supprimer la version.');
    }finally{
        versionToDelete = null;
    }
}
</script>
@endpush
