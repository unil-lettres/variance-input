@php /**  components/main/versions.blade.php  **/ @endphp
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center fw-semibold versions-toggle"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#versionsCollapse"
         aria-expanded="true"
         aria-controls="versionsCollapse">
        <div class="d-flex align-items-center gap-2">
            <span class="collapse-chevron" aria-hidden="true"></span>
            <span>Versions</span>
        </div>
        <span id="versions-status-check" class="admin-card-check" aria-label="Statut versions">&#10003;</span>
    </div>
    <div id="versionsCollapse" class="collapse show">
    <div class="card-body">
        <p class="fst-italic text-muted small mb-3">
            Les versions textuelles alimentent Medite. La pagination se prépare au niveau des versions, puis s’injecte dans chaque comparaison. Ajoutez vos balises <code>&lt;pb&gt;</code> dans l’éditeur (icône crayon), puis cliquez sur «&nbsp;Importer depuis l’éditeur&nbsp;» pour alimenter les données de pagination de la version. Si vous disposez d’un fichier <code>_lignes</code>, importez‑le ici ; si vous combinez <code>_lignes</code> et marqueurs manuels, importez d’abord <code>_lignes</code>, puis «&nbsp;Importer depuis l’éditeur&nbsp;». Une fois les données prêtes, allez dans Comparaisons pour injecter la pagination. Pour les fac‑similés, le bouton «&nbsp;Téléverser&nbsp;» importe l’ensemble des images; l’onglet Fac‑similés permet ensuite de choisir, par comparaison, le sous‑ensemble publié (manifeste JSON). Les fac‑similés sont publiés automatiquement lors de la publication d’une comparaison.
        </p>

        <!-- ────────────── Versions list  ────────────── -->
        <ul id="versions-list" class="list-group">
            <li class="list-group-item">Sélectionner une œuvre pour voir les versions</li>
        </ul>
        <div class="d-flex justify-content-start p-3">
            <button type="button"
                    class="btn btn-outline-primary"
                    id="open-upload-version-modal"
                    disabled
                    aria-label="Téléverser une version">
                Téléverser une version
            </button>
        </div>
    </div>
    </div>
</div>

<!-- ────────────── Edit modal  ────────────── -->
<div class="modal fade" id="editVersionModal" tabindex="-1" aria-labelledby="editVersionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editVersionModalLabel">Éditer le nom d'édition</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-version-id">
                <div class="mb-3">
                    <label for="edit-version-name" class="form-label">Éditeur / année</label>
                    <input type="text" id="edit-version-name" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="update-version-btn">Update</button>
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
                <p class="text-muted small">
                    Choisissez un fichier au format texte à téléverser (formats autorisés: <code>.txt</code>, <code>.text</code>, texte brut sans extension; encodages <code>UTF-8</code>, <code>Windows-1252</code>, <code>ISO-8859-1</code>, <code>Mac Roman</code>), et indiquez la désignation éditoriale telle qu’elle apparaîtra dans la partie publique.
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
            <div class="modal-body">
                <p class="text-muted small" id="facsimile-upload-info">Sélectionnez un dossier contenant les images à importer.</p>
                <div class="mb-3">
                    <div class="input-group">
                        <label class="btn btn-outline-secondary mb-0" for="facsimile-img-input">Sélectionner le dossier d'images</label>
                        <input type="file" id="facsimile-img-input" class="d-none" webkitdirectory directory multiple accept="image/*">
                        <span class="form-control" id="facsimile-folder-label" readonly>Aucun dossier</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div id="facsimile-upload-spinner" class="spinner-border spinner-border-sm text-primary" role="status" style="display:none;"></div>
                </div>
                <div id="facsimile-upload-log" class="small text-muted mb-2" style="white-space: pre-line;"></div>
                <div id="facsimile-upload-total" class="small text-muted mb-2"></div>
                <div id="facsimile-upload-summary" class="small text-muted" style="white-space: pre-line;"></div>
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
  .versions-toggle .collapse-chevron::before {
    content: "\25BC";
    display: inline-block;
    transition: transform .2s ease;
  }
  .versions-toggle[aria-expanded="false"] .collapse-chevron::before {
    transform: rotate(-90deg);
  }
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
    .version-table td { vertical-align: middle; }
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
let selectedAuthorLabel = '';
let selectedWorkLabel   = '';
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
            }
        }
        versionsCache.set(id, cached);
        const lignesInfo = cached.lignes ?? null;
        renderLignesStatus(id, lignesInfo, data, paginationInfo);
        const status = data?.status || 'idle';
        if (['idle', 'done', 'failed'].includes(status)) {
            stopLignesPolling(id);
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
        if (state.viewBadge) state.viewBadge.textContent = '…';
        if (state.viewBtn) {
            state.viewBtn.disabled = false;
            state.viewBtn.title = 'Charger les fac-similés';
            state.viewBtn.dataset.facsimileLoading = '1';
        }
        if (state.uploadBtn) {
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
        if (state.statusNote && state.statusText) {
            state.statusText.textContent = 'Chargement des fac-similés…';
            state.statusNote.className = 'small mt-2 d-flex align-items-center gap-2 flex-wrap text-muted';
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

    if (state.viewBadge) state.viewBadge.textContent = ready;
    if (state.viewBtn) {
        const disableView = queued > 0 || ready === 0;
        state.viewBtn.disabled = disableView;
        state.viewBtn.title = disableView
            ? (queued > 0 ? 'Traitement en cours — affichage indisponible' : 'Aucune image à afficher')
            : 'Afficher la galerie de fac-similés';
    }

    if (state.uploadBtn) {
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

    if (state.statusNote && state.statusText) {
        const baseClass = 'small mt-2 d-flex align-items-center gap-2 flex-wrap';
        if (ready === 0 && queued === 0) {
            state.statusText.textContent = 'Aucun fac-similé importé.';
            state.statusNote.className = `${baseClass} text-muted`;
            if (state.cancelBtn) state.cancelBtn.hidden = true;
        } else if (queued > 0) {
            const totalForProgress = totalImages > 0 ? totalImages : ready + queued;
            state.statusText.textContent = `🛠️ Traitement en cours : ${ready}/${totalForProgress} image(s) prêtes (${queued} en attente).`;
            state.statusNote.className = `${baseClass} text-info`;
            if (state.cancelBtn) {
                state.cancelBtn.hidden = false;
                state.cancelBtn.disabled = false;
            }
            if (state.progressWrap && state.progressBar) {
                state.progressWrap.hidden = false;
                let safePercent = 0;
                if (totalForProgress > 0) {
                    safePercent = Math.floor((ready / totalForProgress) * 100);
                }
                if (queued > 0) {
                    if (safePercent >= 100) safePercent = 99;
                    if (safePercent <= 0) {
                        safePercent = ready > 0 ? 5 : 3;
                    }
                }
                state.progressBar.style.width = `${safePercent}%`;
                state.progressBar.setAttribute('aria-valuenow', ready.toString());
                state.progressBar.setAttribute('aria-valuemax', totalForProgress.toString());
                state.progressBar.classList.add('progress-bar-striped', 'progress-bar-animated');
            }
        } else if (outstanding > 0) {
            const totalReady = outstanding;
            state.statusText.textContent = `✅ ${totalReady} fac-similé(s) prêt(s) à publier.`;
            state.statusNote.className = `${baseClass} text-success`;
            if (state.cancelBtn) state.cancelBtn.hidden = true;
            if (state.progressWrap && state.progressBar) {
                state.progressWrap.hidden = true;
                state.progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
            }
        } else {
            state.statusText.textContent = `✅ ${ready} fac-similé(s) publiés.`;
            state.statusNote.className = `${baseClass} text-success`;
            if (state.cancelBtn) state.cancelBtn.hidden = true;
            if (state.progressWrap && state.progressBar) {
                state.progressWrap.hidden = true;
                state.progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
            }
        }
    }

    if (queued === 0 && state.progressWrap && state.progressBar) {
        state.progressWrap.hidden = true;
        state.progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
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

    const hasFile = lignesInfo && typeof lignesInfo === 'object';
    const hasSidecar = paginationInfo && typeof paginationInfo === 'object';
    const hasData = hasFile || hasSidecar;
    const progressData = progress ?? null;
    const status = progressData?.status ?? null;
    const stage = progressData?.stage ?? null;
    const baseClass = 'small mt-2 d-flex align-items-center gap-2 flex-wrap';
    const totalEntries = Number(progressData?.entries_total ?? 0);
    const srcProcessed = Number(progressData?.source?.processed ?? 0);
    const tgtProcessed = Number(progressData?.target?.processed ?? 0);
    const processedTotal = Number(progressData?.processed_total ?? (srcProcessed + tgtProcessed));
    const processed = Math.max(processedTotal, srcProcessed, tgtProcessed);
    const comparisonTotal = Number(progressData?.comparison_total ?? 0);
    const comparisonCurrent = Number(progressData?.comparison_current ?? 0);
    const lineCount = Number(lignesInfo?.line_count ?? 0);
    const linesLabel = Number.isFinite(lineCount) && lineCount > 0
        ? `${lineCount.toLocaleString('fr-FR')} ligne${lineCount > 1 ? 's' : ''}`
        : null;
    let message = '';
    let cssClass = 'small text-muted';
    const showSpinner = () => {
        if (state.lignesSpinner) state.lignesSpinner.style.display = 'inline-block';
    };
    const hideSpinner = () => {
        if (state.lignesSpinner) state.lignesSpinner.style.display = 'none';
    };

    if (stage === 'queued' || status === 'queued') {
        message = '🕓 Pagination en attente de traitement…';
        cssClass = `${baseClass} text-info`;
        showSpinner();
    } else if (stage === 'preparing') {
        message = 'Création du fichier de données de pagination';
        cssClass = `${baseClass} text-info`;
        showSpinner();
    } else if (status === 'running') {
        const markersTotal = totalEntries || lineCount || null;
        const processedMarkers = processed > 0 ? processed : (srcProcessed || tgtProcessed || null);
        const progressLabel = markersTotal
            ? ` (${(processedMarkers ?? 0).toLocaleString('fr-FR')}/${markersTotal.toLocaleString('fr-FR')} lignes)`
            : (processedMarkers
                ? ` (${processedMarkers.toLocaleString('fr-FR')} lignes traitées)`
                : '');
        message = `🛠️ Analyse des marqueurs…${progressLabel}`;
        cssClass = `${baseClass} text-info`;
        showSpinner();
    } else if (status === 'failed') {
        const error = String(progressData?.error || 'Erreur inconnue');
        message = `❌ Échec du traitement : ${error}`;
        cssClass = `${baseClass} text-danger`;
        hideSpinner();
    } else if (status === 'cancelled') {
        message = '🚫 Traitement annulé.';
        cssClass = `${baseClass} text-warning`;
        hideSpinner();
    } else if (status === 'done') {
        const sidecarMeta = progressData?.sidecar ?? paginationInfo ?? null;
        const summaryTotal = Number(progressData?.summary?.total ?? sidecarMeta?.marker_count ?? 0);
        const summaryMissed = Number(progressData?.missed_total ?? sidecarMeta?.missed_count ?? 0);
        const markerPart = Number.isFinite(summaryTotal) && summaryTotal > 0
            ? `${summaryTotal} marqueur(s)`
            : null;
        message = markerPart
            ? `✅ Données de pagination prêtes — ${markerPart}`
            : '✅ Données de pagination prêtes';
        cssClass = `${baseClass} text-success`;
        hideSpinner();
    } else if (hasFile && !hasSidecar) {
        const mainLabel = linesLabel ?? 'Fichier _lignes prêt';
        message = `⌛ ${mainLabel} — en attente du traitement pagination.`;
        cssClass = `${baseClass} text-warning`;
        hideSpinner();
    } else if (hasSidecar) {
        const markerCount = Number(paginationInfo?.details?.marker_count ?? 0);
        const markerPart = markerCount > 0 ? `${markerCount} marqueur(s)` : null;
        message = markerPart
            ? `✅ Données de pagination prêtes — ${markerPart}`
            : '✅ Données de pagination prêtes';
        cssClass = `${baseClass} text-success`;
        hideSpinner();
    } else if (hasFile) {
        const fallbackSize = formatBytes(lignesInfo.size);
        const updated = formatTimestamp(lignesInfo.updated_at);
        const mainLabel = linesLabel ?? fallbackSize ?? 'Fichier _lignes';
        message = updated
            ? `✅ ${mainLabel} — mis à jour le ${updated}`
            : `✅ ${mainLabel}`;
        cssClass = `${baseClass} text-success`;
        hideSpinner();
    } else {
        message = 'Aucun fichier _lignes importé.';
        cssClass = `${baseClass} text-muted`;
        hideSpinner();
    }

    if (state.lignesStatusText) {
        state.lignesStatusText.textContent = message;
    }
    if (state.lignesStatus) {
        state.lignesStatus.className = cssClass;
    }

    if (state.lignesCountsWrap && state.lignesCountPill && state.editorCountPill) {
        const counts = paginationInfo?.details?.counts ?? null;
        const lignesCount = Number(counts?.lignes ?? NaN);
        const editorCount = Number(counts?.editor ?? NaN);
        const canShowCounts = !(status && ['queued', 'running', 'failed', 'cancelled'].includes(String(status)));
        if (canShowCounts && Number.isFinite(lignesCount) && Number.isFinite(editorCount)) {
            state.lignesCountsWrap.hidden = false;
            state.lignesCountPill.textContent = `_lignes: ${lignesCount.toLocaleString('fr-FR')}`;
            state.editorCountPill.textContent = `éditeur: ${editorCount.toLocaleString('fr-FR')}`;
        } else {
            state.lignesCountsWrap.hidden = true;
        }
    }

    if (state.lignesCancelBtn) {
        if (status && ['queued', 'running'].includes(status)) {
            state.lignesCancelBtn.hidden = false;
            state.lignesCancelBtn.disabled = false;
        } else {
            state.lignesCancelBtn.hidden = true;
        }
    }

    if (state.lignesProgressWrap && state.lignesProgressBar) {
        if (status && ['queued', 'running'].includes(status)) {
            state.lignesProgressWrap.hidden = false;
            let percent = 0;
            let ariaNow = processed;
            let ariaMax = totalEntries || processed || 1;

            if (stage === 'preparing') {
                if (comparisonTotal > 0) {
                    percent = Math.floor((comparisonCurrent / comparisonTotal) * 100);
                    ariaNow = comparisonCurrent;
                    ariaMax = comparisonTotal;
                }
                if (percent <= 0) {
                    percent = 5;
                }
            } else if (status === 'running' && totalEntries > 0) {
                percent = Math.floor((processed / totalEntries) * 100);
            }

            if (percent <= 0) {
                percent = status === 'queued' ? 3 : (processed > 0 ? 10 : 5);
            }
            percent = Math.max(Math.min(percent, 99), 3);
            state.lignesProgressBar.style.width = `${percent}%`;
            state.lignesProgressBar.classList.add('progress-bar-striped', 'progress-bar-animated');
            state.lignesProgressBar.setAttribute('aria-valuenow', String(ariaNow));
            state.lignesProgressBar.setAttribute('aria-valuemax', String(ariaMax));
        } else {
            state.lignesProgressWrap.hidden = true;
            state.lignesProgressBar.style.width = '0%';
            state.lignesProgressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
        }
    }

    if (state.lignesDownloadBtn) {
        const disableDownload = !hasFile || (status && ['queued', 'running'].includes(status));
        state.lignesDownloadBtn.disabled = disableDownload;
    }
    if (state.lignesUploadBtn) {
        if (isLegacy) {
            state.lignesUploadBtn.disabled = true;
        } else {
            state.lignesUploadBtn.disabled = !!(status && ['queued', 'running'].includes(status));
        }
        if (!isLegacy) {
            state.lignesUploadBtn.title = state.lignesUploadBtn.disabled
                ? 'Traitement en cours — veuillez patienter'
                : 'Importer un fichier _lignes';
        }
    }
    if (state.lignesMergePbBtn) {
        const pbAvailable = state.lignesMergePbBtn.dataset.pbAvailable === '1';
        if (isLegacy) {
            state.lignesMergePbBtn.disabled = true;
        } else {
            state.lignesMergePbBtn.disabled = !pbAvailable || !!(status && ['queued', 'running'].includes(status));
        }
        if (!isLegacy) {
            if (!pbAvailable) {
                state.lignesMergePbBtn.title = "Aucune balise <pb> détectée dans l'éditeur.";
            } else if (state.lignesMergePbBtn.disabled) {
                state.lignesMergePbBtn.title = 'Traitement en cours — veuillez patienter';
            } else {
                state.lignesMergePbBtn.title = "Ajouter les marqueurs de l'éditeur aux données de pagination";
            }
        }
    }
    if (state.lignesDeleteBtn) {
        const disableDelete = !hasData || (status && ['queued', 'running'].includes(status));
        state.lignesDeleteBtn.hidden = !hasData;
        state.lignesDeleteBtn.disabled = disableDelete;
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

    if (state.completionMeta) {
        if (done) {
            const parts = [];
            if (Number.isFinite(doneAt) && doneAt > 0) {
                const label = formatTimestamp(doneAt);
                if (label && label !== 'Date inconnue') {
                    parts.push(label);
                }
            }
            if (doneByName) {
                parts.push(doneByName);
            }
            const suffix = parts.length ? ` — ${parts.join(' · ')}` : '';
            state.completionMeta.textContent = `Terminé${suffix}`;
            state.completionMeta.className = 'small text-success';
        } else {
            state.completionMeta.textContent = 'À cocher une fois la pagination validée.';
            state.completionMeta.className = 'small text-muted';
        }
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
    if (state?.completionMeta) {
        state.completionMeta.textContent = done ? 'Marquage…' : 'Mise à jour…';
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
        if(!file.name.toLowerCase().endsWith('.txt')){
            $fileInfo.textContent='❌ Extension invalide (uniquement .txt)';
            $fileInput.value='';
            return;
        }
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
        facFileInput.addEventListener('change', () => {
            const allFiles = facFileInput.files ? facFileInput.files.length : 0;
            const images = collectSelectedImages();
            const filesArray = facFileInput.files ? Array.from(facFileInput.files) : [];
            const dsStoreCount = filesArray.filter(isDsStoreFile).length;
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
                    facLog.textContent = `${images.length} image(s) détectée(s)` + (ignored > 0 ? ` — ${ignored} ignorée(s)` : '');
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
                facSummary.textContent = `✅ ${uploadedCount} fichier(s) importé(s)` + (lastStoredDir ? ` dans ${lastStoredDir}` : '');
                if (processingQueued) {
                    facSummary.textContent += '\n🕓 Les redimensionnements et miniatures se poursuivent en arrière-plan.';
                }
            }
            document.dispatchEvent(new CustomEvent('facsimilesUploaded', {
                detail: { versionId: uploadVersionId }
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
        fetchVersions(selectedWorkId);
    });
    document.addEventListener('versionsUpdated', e=>{
        if(e.detail.workId){
            selectedWorkId=e.detail.workId;
            fetchVersions(selectedWorkId, true);
        }
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
    const check = document.getElementById('versions-status-check');
    if (!check) return;

    if (count === null || count === undefined) {
        check.className = 'admin-card-check d-none';
        check.title = '';
        return;
    }

    const numericCount = Number(count);
    const label = Number.isFinite(numericCount) ? numericCount : 0;
    check.className = 'admin-card-check';
    check.innerHTML = '&#10003;';
    if (label > 0) {
        check.classList.add('admin-card-check--done');
    }
    check.title = `Versions : ${label}`;
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
    if (facModalInfo) facModalInfo.innerHTML = `Les fichiers seront importés dans l'ordre alphabétique-numérique de leur nom d'origine.<br><strong>Version cible :</strong> ${infoLabel}`;
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

function collectSelectedImages() {
    if (!facFileInput || !facFileInput.files) return [];
    return Array.from(facFileInput.files).filter(file => {
        if (file.type && file.type.startsWith('image/')) return true;
        return /\.(jpe?g|png)$/i.test(file.name || '');
    });
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
    if (isCancel && state?.cancelBtn) {
        buttonsToDisable.push(state.cancelBtn);
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
        if (payload?.message) {
            alert(payload.message);
        }
        stopFacsimilePolling(id);
        await requestFacsimileProgress(id);
        if (Number.isFinite(selectedWorkId)) {
            fetchVersions(selectedWorkId, true);
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
    if (state?.lignesSpinner) state.lignesSpinner.style.display = 'inline-block';
    if (state?.lignesUploadBtn) state.lignesUploadBtn.disabled = true;
    if (state?.lignesDownloadBtn) state.lignesDownloadBtn.disabled = true;

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
        if (state?.lignesUploadBtn) state.lignesUploadBtn.disabled = false;
        if (state?.lignesDownloadBtn) {
            const cache = versionsCache.get(id);
            const hasFile = !!(cache?.lignes);
            const progressStatus = cache?.page_marker_progress?.status;
            state.lignesDownloadBtn.disabled = !hasFile || (progressStatus && ['queued','running'].includes(progressStatus));
        }
        if (state?.lignesDeleteBtn) {
            const cache = versionsCache.get(id);
            const hasData = !!(cache?.lignes) || !!(cache?.pagination);
            const progressStatus = cache?.page_marker_progress?.status;
            state.lignesDeleteBtn.hidden = !hasData;
            state.lignesDeleteBtn.disabled = !hasData || (progressStatus && ['queued','running'].includes(progressStatus));
        }
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

async function mergePaginationFromPb(versionId, pbCount = null){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return;

    const count = Number(pbCount);
    const countLabel = Number.isFinite(count) && count > 0 ? count : null;
    const confirmLabel = countLabel
        ? `Ajouter ${countLabel} marqueur(s) depuis l’éditeur aux données de pagination ?`
        : "Ajouter les marqueurs de l’éditeur aux données de pagination ?";
    if (!confirm(confirmLabel)) return;

    const state = facsimileRowState.get(id);
    if (state?.lignesSpinner) state.lignesSpinner.style.display = 'inline-block';
    if (state?.lignesMergePbBtn) state.lignesMergePbBtn.disabled = true;

    try {
        const res = await fetch(withBasePath(`/api/versions/${id}/pagination/merge-from-pb`), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const payload = await readJsonResponse(res);

        const refreshed = await refreshPaginationInfo(id);
        if (versionsCache.has(id)) {
            const cached = versionsCache.get(id);
            if (refreshed) {
                cached.pagination = refreshed;
            }
            versionsCache.set(id, cached);
        }

        const cached = versionsCache.get(id) ?? {};
        renderLignesStatus(id, cached.lignes ?? null, cached.page_marker_progress ?? null, cached.pagination ?? null);

        if (payload?.message) {
            alert(payload.message);
        }
    } catch (err) {
        console.error(err);
        alert(err.message || 'Impossible de fusionner les marqueurs depuis l’éditeur.');
    } finally {
        if (state?.lignesSpinner) state.lignesSpinner.style.display = 'none';
        renderLignesStatus(id, versionsCache.get(id)?.lignes ?? null, versionsCache.get(id)?.page_marker_progress ?? null, versionsCache.get(id)?.pagination ?? null);
    }
}

async function deleteLignesFile(versionId){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return;
    if (!confirm('Supprimer les données de pagination pour cette version ?')) return;

    const state = facsimileRowState.get(id);
    if (state?.lignesDeleteBtn) state.lignesDeleteBtn.disabled = true;
    if (state?.lignesSpinner) state.lignesSpinner.style.display = 'inline-block';

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
        const cache = versionsCache.get(id);
        const hasData = !!(cache?.lignes) || !!(cache?.pagination);
        const progressStatus = cache?.page_marker_progress?.status;
        if (state?.lignesSpinner) state.lignesSpinner.style.display = 'none';
        if (state?.lignesDeleteBtn) {
            state.lignesDeleteBtn.hidden = !hasData;
            state.lignesDeleteBtn.disabled = !hasData || (progressStatus && ['queued','running'].includes(progressStatus));
        }
        if (state?.lignesDownloadBtn) {
            state.lignesDownloadBtn.disabled = !hasData || (progressStatus && ['queued','running'].includes(progressStatus));
        }
        if (state?.lignesUploadBtn) {
            state.lignesUploadBtn.disabled = !!(progressStatus && ['queued','running'].includes(progressStatus));
        }
    }
}

async function cancelLignesProcessing(versionId){
    const id = Number(versionId);
    if (!Number.isFinite(id)) return;
    if (!confirm('Annuler le traitement des balises de pagination pour cette version ?')) return;

    const state = facsimileRowState.get(id);
    if (state?.lignesCancelBtn) state.lignesCancelBtn.disabled = true;

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
    } finally {
        if (state?.lignesCancelBtn) state.lignesCancelBtn.disabled = false;
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
            list.innerHTML='<div class="text-muted p-2">Aucune version textuelle disponible pour cette oeuvre - cliquez "Téléverser une version" ci-dessous pour en ajouter une.</div>';
            facsimilePollers.forEach((_, id) => stopFacsimilePolling(id));
            lignesPollers.forEach((_, id) => stopLignesPolling(id));
            return;
        }

        updateVersionsCount(versions.length);

        const table = document.createElement('table');
        table.className='table table-bordered table-sm version-table';
        table.innerHTML=`<thead class="table-light"><tr><th>ID</th><th>Dénomination</th><th>Signes</th><th>Dossier</th><th>Fac-similés</th><th class="text-center">Pagination</th><th class="text-center">Actions</th></tr></thead><tbody></tbody>`;
        const tbody = table.querySelector('tbody');
        const activeFacsimileIds = new Set();
        versions.forEach(v=>{
            const tr = document.createElement('tr');
            const shortFolder = (v.folder || '').split('/').pop();
            // Used for pagination actions (merge from <pb> markers), but we no longer display the pb badge in the name column.
            const pbCount = Number(v.pb_markers ?? 0);

            const tdId = document.createElement('td');
            tdId.textContent = v.id;
            tr.appendChild(tdId);

            const tdName = document.createElement('td');
            const nameText = document.createElement('span');
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

            const folderCell = document.createElement('td');
            folderCell.textContent = shortFolder;
            tr.appendChild(folderCell);

            const tdFac = document.createElement('td');
            tdFac.className = 'align-middle';
            const facsimileData = v.facsimiles && typeof v.facsimiles === 'object' ? v.facsimiles : null;
            const sourceCount = Number(facsimileData?.source_count ?? 0);
            const publishedCount = Number(facsimileData?.published_count ?? 0);
            const queueCount = Number(facsimileData?.queue_count ?? 0);
            const totalExpected = Number(facsimileData?.total_expected ?? (sourceCount + queueCount));

            const facButtons = document.createElement('div');
            facButtons.className = 'btn-group btn-group-sm';

            const btnFacView = document.createElement('button');
            btnFacView.type = 'button';
            btnFacView.className = 'btn btn-outline-secondary d-inline-flex align-items-center gap-1';
            const viewBadge = document.createElement('span');
            viewBadge.className = 'badge bg-light text-muted border';
            viewBadge.textContent = sourceCount;
            btnFacView.appendChild(viewBadge);
            btnFacView.appendChild(document.createTextNode(' Voir'));
            btnFacView.disabled = sourceCount === 0;
            btnFacView.title = sourceCount === 0 ? 'Aucune image à afficher' : 'Afficher la galerie de fac-similés';
            btnFacView.addEventListener('click', () => {
                if (btnFacView.dataset.facsimileLoading === '1') {
                    requestFacsimileProgress(v.id);
                }
                document.dispatchEvent(new CustomEvent('facsimiles:select', {
                    detail: { versionId: v.id, versionName: v.name }
                }));
                const collapseEl = document.getElementById('facsimilesCollapse');
                if (collapseEl && !collapseEl.classList.contains('show')) {
                    const collapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                    collapse.show();
                }
                const facsimilesCard = document.getElementById('facsimiles-card');
                if (facsimilesCard) {
                    setTimeout(() => {
                        facsimilesCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 50);
                }
            });
            facButtons.appendChild(btnFacView);

            const btnFacUpload = document.createElement('button');
            btnFacUpload.type = 'button';
            btnFacUpload.className = 'btn btn-outline-primary';
            btnFacUpload.textContent = 'Téléverser';
            btnFacUpload.title = 'Importer de nouveaux fac-similés';
            btnFacUpload.addEventListener('click', () => {
                if (!v.is_legacy) {
                    openFacsimileUploadModal(v);
                }
            });
            facButtons.appendChild(btnFacUpload);

            const btnFacClear = document.createElement('button');
            btnFacClear.type = 'button';
            btnFacClear.className = 'btn btn-outline-danger';
            btnFacClear.textContent = 'Supprimer';
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

            const progressWrap = document.createElement('div');
            progressWrap.className = 'progress mt-1';
            progressWrap.style.height = '4px';
            progressWrap.hidden = true;
            const progressBar = document.createElement('div');
            progressBar.className = 'progress-bar bg-info';
            progressBar.style.width = '0%';
            progressBar.setAttribute('role', 'progressbar');
            progressWrap.appendChild(progressBar);

            const statusNote = document.createElement('div');
            statusNote.className = 'small text-muted mt-2 d-flex align-items-center gap-2 flex-wrap';
            const statusText = document.createElement('span');
            statusText.textContent = '';
            statusNote.appendChild(statusText);
            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-link btn-sm text-danger p-0 ms-1';
            cancelBtn.innerHTML = '&times;';
            cancelBtn.hidden = true;
            cancelBtn.title = 'Annuler le traitement et supprimer les fac-similés importés';
            cancelBtn.setAttribute('aria-label', 'Annuler le traitement des fac-similés');
            cancelBtn.addEventListener('click', () => purgeFacsimiles(v.id, { reason: 'cancel' }));
            statusNote.appendChild(cancelBtn);

            tdFac.appendChild(facButtons);
            tdFac.appendChild(progressWrap);
            tdFac.appendChild(statusNote);

            const tdCompletion = document.createElement('td');
            tdCompletion.className = 'align-middle text-center';
            const completionSwitch = document.createElement('div');
            completionSwitch.className = 'form-check form-switch d-inline-flex align-items-center justify-content-center gap-2 mb-1';
            const completionToggle = document.createElement('input');
            completionToggle.type = 'checkbox';
            completionToggle.role = 'switch';
            completionToggle.className = 'form-check-input';
            completionToggle.id = `version-${v.id}-pagination-done`;
            completionToggle.checked = !!v.pagination_done;
            completionToggle.title = 'Cochez lorsque la pagination est terminée pour cette version';
            completionToggle.setAttribute('aria-label', 'Marquer la pagination terminée');
            completionToggle.addEventListener('change', () => togglePaginationDone(v.id, completionToggle.checked));
            const completionLabel = document.createElement('label');
            completionLabel.className = 'form-check-label small text-muted';
            completionLabel.setAttribute('for', completionToggle.id);
            completionLabel.textContent = 'Terminé';
            completionSwitch.appendChild(completionToggle);
            completionSwitch.appendChild(completionLabel);
            const completionMeta = document.createElement('div');
            completionMeta.className = 'small text-muted';
            completionMeta.textContent = 'À cocher une fois la pagination validée.';

            const lignesWrap = document.createElement('div');
            lignesWrap.className = 'mt-2 d-flex flex-column align-items-center gap-1';
            const lignesTitle = document.createElement('div');
            lignesTitle.className = 'small text-muted fw-semibold';
            lignesTitle.textContent = 'Données de pagination';
            lignesWrap.appendChild(lignesTitle);

            const lignesActions = document.createElement('div');
            lignesActions.className = 'd-flex align-items-center gap-1 mt-1 flex-wrap justify-content-center';
            const lignesInput = document.createElement('input');
            lignesInput.type = 'file';
            lignesInput.accept = '.txt,text/plain';
            lignesInput.style.display = 'none';
            const lignesUploadBtn = document.createElement('button');
            lignesUploadBtn.type = 'button';
            lignesUploadBtn.className = 'btn btn-sm btn-outline-primary';
            lignesUploadBtn.textContent = '_lignes';
            lignesUploadBtn.title = 'Importer un fichier _lignes';
            const lignesMergePbBtn = document.createElement('button');
            lignesMergePbBtn.type = 'button';
            lignesMergePbBtn.className = 'btn btn-sm btn-outline-secondary';
            lignesMergePbBtn.textContent = 'Editeur';
            lignesMergePbBtn.title = "Ajouter les marqueurs de l'éditeur aux données de pagination";
            const lignesDeleteBtn = document.createElement('button');
            lignesDeleteBtn.type = 'button';
            lignesDeleteBtn.className = 'btn btn-sm btn-outline-danger';
            lignesDeleteBtn.textContent = 'Supprimer';
            lignesDeleteBtn.title = 'Supprimer les données de pagination';
            const lignesCancelBtn = document.createElement('button');
            lignesCancelBtn.type = 'button';
            lignesCancelBtn.className = 'btn btn-sm btn-outline-warning';
            lignesCancelBtn.textContent = 'Annuler';
            lignesCancelBtn.hidden = true;
            lignesCancelBtn.title = 'Annuler le traitement _lignes';
            lignesActions.appendChild(lignesUploadBtn);
            lignesActions.appendChild(lignesMergePbBtn);
            lignesActions.appendChild(lignesDeleteBtn);
            lignesActions.appendChild(lignesInput);
            lignesWrap.appendChild(lignesActions);
            tdCompletion.appendChild(lignesWrap);

            const lignesCountsWrap = document.createElement('div');
            lignesCountsWrap.className = 'd-flex align-items-center gap-2 flex-wrap justify-content-center mt-1';
            lignesCountsWrap.hidden = true;
            const lignesCountPill = document.createElement('span');
            lignesCountPill.className = 'badge rounded-pill bg-light text-muted border';
            const editorCountPill = document.createElement('span');
            editorCountPill.className = 'badge rounded-pill bg-light text-muted border';
            lignesCountsWrap.appendChild(lignesCountPill);
            lignesCountsWrap.appendChild(editorCountPill);
            lignesWrap.appendChild(lignesCountsWrap);

            const lignesStatus = document.createElement('div');
            lignesStatus.className = 'small text-muted d-flex align-items-center gap-2 flex-wrap justify-content-center';
            const lignesSpinner = document.createElement('span');
            lignesSpinner.className = 'spinner-border spinner-border-sm text-primary';
            lignesSpinner.setAttribute('role', 'status');
            lignesSpinner.style.display = 'none';
            const lignesStatusText = document.createElement('span');
            lignesStatus.appendChild(lignesSpinner);
            lignesStatus.appendChild(lignesStatusText);
            lignesStatus.appendChild(lignesCancelBtn);
            lignesWrap.appendChild(lignesStatus);

            const lignesProgressWrap = document.createElement('div');
            lignesProgressWrap.className = 'progress w-100';
            lignesProgressWrap.style.height = '4px';
            lignesProgressWrap.hidden = true;
            const lignesProgressBar = document.createElement('div');
            lignesProgressBar.className = 'progress-bar bg-info';
            lignesProgressBar.style.width = '0%';
            lignesProgressBar.setAttribute('role', 'progressbar');
            lignesProgressWrap.appendChild(lignesProgressBar);
            lignesWrap.appendChild(lignesProgressWrap);

            const hasPbMarkers = pbCount > 0;
            lignesMergePbBtn.dataset.pbAvailable = hasPbMarkers ? '1' : '0';
            lignesMergePbBtn.disabled = !hasPbMarkers;
            if (!hasPbMarkers) {
                lignesMergePbBtn.title = "Aucune balise <pb> détectée dans l'éditeur.";
            }

            if (v.is_legacy) {
                lignesUploadBtn.disabled = true;
                lignesUploadBtn.classList.add('legacy-disabled');
                lignesUploadBtn.title = 'Import _lignes désactivé pour les versions legacy.';
                lignesMergePbBtn.disabled = true;
                lignesMergePbBtn.classList.add('legacy-disabled');
                lignesMergePbBtn.title = "Import depuis l'éditeur désactivé pour les versions legacy.";
                lignesDeleteBtn.disabled = true;
                lignesDeleteBtn.classList.add('legacy-disabled');
                lignesDeleteBtn.title = 'Suppression désactivée pour les versions legacy.';
                lignesCancelBtn.disabled = true;
            }

            lignesUploadBtn.addEventListener('click', () => {
                if (v.is_legacy) return;
                lignesInput.value = '';
                lignesInput.click();
            });
            lignesMergePbBtn.addEventListener('click', () => {
                if (v.is_legacy) return;
                if (!hasPbMarkers) return;
                mergePaginationFromPb(v.id, pbCount);
            });
            lignesDeleteBtn.addEventListener('click', () => {
                if (v.is_legacy) return;
                deleteLignesFile(v.id);
            });
            lignesInput.addEventListener('change', () => {
                if (v.is_legacy) return;
                if (!lignesInput.files || !lignesInput.files.length) return;
                uploadLignesFile(v.id, lignesInput.files[0]);
            });
            lignesCancelBtn.addEventListener('click', () => {
                if (v.is_legacy) return;
                cancelLignesProcessing(v.id);
            });

            const rowState = {
                isLegacy: v.is_legacy,
                viewBtn: btnFacView,
                viewBadge,
                uploadBtn: btnFacUpload,
                statusNote,
                statusText,
                cancelBtn,
                clearBtn: btnFacClear,
                progressWrap,
                progressBar,
                completionToggle,
                completionMeta,
                lignesStatus,
                lignesStatusText,
                lignesSpinner,
                lignesCountsWrap,
                lignesCountPill,
                editorCountPill,
                lignesProgressWrap,
                lignesProgressBar,
                lignesUploadBtn,
                lignesMergePbBtn,
                lignesDeleteBtn,
                lignesCancelBtn,
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
            const editorUrl = withBasePath(`/version/${v.id}/editor`);

            const canEditXml = (typeof v.xml_available === 'boolean') ? v.xml_available : true;
            let editorControl = null;
            if (canEditXml) {
                const btnEditor = document.createElement('a');
                btnEditor.href = editorUrl;
                btnEditor.setAttribute('data-bs-toggle', 'tooltip');
                btnEditor.className = 'btn btn-outline-primary';
                btnEditor.innerHTML = '<i class="bi bi-pencil-square"></i>';
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
                disabledBtn.innerHTML = '<i class="bi bi-pencil-square"></i>';
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

            let downloadControl = null;
            if (v.text_available) {
                const btnDownload = document.createElement('a');
                btnDownload.href = withBasePath(`/versions/${v.id}/download`);
                btnDownload.className = 'btn btn-outline-secondary d-flex flex-column align-items-center justify-content-center download-btn';
                btnDownload.innerHTML = '<i class="bi bi-download download-icon"></i><span class="download-label">TXT</span>';
                btnDownload.setAttribute('data-bs-toggle', 'tooltip');
                btnDownload.setAttribute('download', '');
                const tooltipDownload = new bootstrap.Tooltip(
                    btnDownload,
                    {
                        title: 'Télécharger la version (TXT)',
                        delay: { show: 500, hide: 0 },
                        trigger: 'hover'
                    }
                );
                actionButtonsTooltips.push(tooltipDownload);
                downloadControl = btnDownload;
            }

            let downloadXmlControl = null;
            if (v.xml_available) {
                const btnDownloadXml = document.createElement('a');
                btnDownloadXml.href = withBasePath(`/versions/${v.id}/download-xml`);
                btnDownloadXml.className = 'btn btn-outline-secondary d-flex flex-column align-items-center justify-content-center download-btn';
                btnDownloadXml.innerHTML = '<i class="bi bi-download download-icon"></i><span class="download-label">XML</span>';
                btnDownloadXml.setAttribute('data-bs-toggle', 'tooltip');
                btnDownloadXml.setAttribute('download', '');
                const tooltipDownloadXml = new bootstrap.Tooltip(
                    btnDownloadXml,
                    {
                        title: 'Télécharger la version (XML)',
                        delay: { show: 500, hide: 0 },
                        trigger: 'hover'
                    }
                );
                actionButtonsTooltips.push(tooltipDownloadXml);
                downloadXmlControl = btnDownloadXml;
            }

            const btnDel = document.createElement('button');
            btnDel.className = 'btn btn-outline-danger';
            btnDel.innerHTML = '<i class="bi bi-trash3"></i>';
            btnDel.setAttribute('data-bs-toggle', 'tooltip');
            btnDel.addEventListener('click',()=>confirmDeleteVersion(v));
            const deleteTitle = v.is_legacy
                ? 'La suppression est désactivée pour les versions legacy.'
                : 'Supprimer la version';
            if (v.is_legacy) {
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
            tdActions.appendChild(btnDel);

            const btnGroup = document.createElement('div');
            btnGroup.className = 'btn-group';
            btnGroup.role = 'group';
            btnGroup.ariaLabel = 'Version utility buttons';

            if (editorControl) {
                btnGroup.appendChild(editorControl);
            }
            if (downloadControl) {
                btnGroup.appendChild(downloadControl);
            }
            if (downloadXmlControl) {
                btnGroup.appendChild(downloadXmlControl);
            }
            btnGroup.appendChild(btnDel);
            tdActions.appendChild(btnGroup);

            const completionWrap = document.createElement('div');
            completionWrap.className = 'mt-2 d-flex flex-column align-items-center gap-1';
            completionWrap.appendChild(completionSwitch);
            completionWrap.appendChild(completionMeta);
            tdActions.appendChild(completionWrap);

            tr.appendChild(tdActions);
            tbody.appendChild(tr);
        });
        list.appendChild(table);
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
        if (payload?.message) {
            alert(payload.message);
        }
    }catch(err){
        console.error(err);
        alert(err.message || 'Impossible de supprimer la version.');
    }finally{
        versionToDelete = null;
    }
}
</script>
@endpush
