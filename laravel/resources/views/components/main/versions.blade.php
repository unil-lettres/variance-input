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
            <span class="text-uppercase">Versions & fac-similés</span>
        </div>
        <span id="versions-count-pill" class="badge bg-danger-subtle text-danger media-status-pill">0</span>
    </div>
    <div id="versionsCollapse" class="collapse show">
    <div class="card-body">
        <p class="fst-italic text-muted small mb-3">
            Les versions textuelles alimentent Medite ainsi que la partie publique, où les lecteurs peuvent consulter les différentes éditions.
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
                    title="Téléverser une version"
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
</div>

<!-- ────────────── Delete confirmation ────────────── -->
<div class="modal fade" id="deleteVersionConfirm" tabindex="-1" aria-labelledby="deleteVersionConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteVersionConfirmLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">Are you sure you want to delete this version&nbsp;?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-version">Delete</button>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- ────────────── Upload modal ────────────── -->
<div class="modal fade" id="uploadVersionModal" tabindex="-1" aria-labelledby="uploadVersionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadVersionModalLabel">Ajouter une version</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Choisissez un fichier <code>.txt</code> à téléverser et indiquez la désignation éditoriale telle qu’elle apparaîtra dans la partie publique.
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
                                   accept=".txt"
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
                <div id="facsimile-upload-summary" class="small text-muted" style="white-space: pre-line;"></div>
            </div>
            <div class="modal-footer">
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
</style>
@endpush

@push('scripts')
<style>
    /* Keep table headers visually consistent with card header */
    .version-table th { font-weight: normal; font-size: 1rem; color: #333; }
    .version-table td { vertical-align: middle; }
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
let facModalEl       = null;
let facModalTitle    = null;
let facModalInfo     = null;
let facFileInput     = null;
let facUploadBtn     = null;
let facSpinner       = null;
let facLog           = null;
let facSummary       = null;
let facFolderLabel  = null;
let facModalInstance = null;
let facVersionId     = null;
let facVersionName   = '';
let selectedAuthorLabel = '';
let selectedWorkLabel   = '';
const UPLOAD_MODAL_BASE_TITLE = 'Ajouter une version';
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
    facSpinner     = document.getElementById('facsimile-upload-spinner');
    facLog         = document.getElementById('facsimile-upload-log');
    facSummary     = document.getElementById('facsimile-upload-summary');
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
        facModalEl.addEventListener('hidden.bs.modal', () => {
            facVersionId = null;
            facVersionName = '';
            if (facFileInput) facFileInput.value = '';
            if (facUploadBtn) facUploadBtn.disabled = true;
            if (facLog) facLog.textContent = '';
            if (facSummary) {
                facSummary.className = 'small text-muted';
                facSummary.textContent = '';
            }
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
            if (facFolderLabel) {
                const firstPath = images.length ? (images[0].webkitRelativePath || images[0].name || '') : '';
                facFolderLabel.textContent = firstPath ? (firstPath.split('/')[0] || firstPath) : 'Aucun dossier';
            }
            if (facUploadBtn) facUploadBtn.disabled = images.length === 0;
            if (facLog) {
                if (!allFiles) {
                    facLog.textContent = '';
                } else if (!images.length) {
                    facLog.textContent = 'Aucun fichier image reconnu dans ce dossier.';
                } else {
                    const ignored = allFiles - images.length;
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

            const sortedFiles = files.sort((a, b) => {
                const keyA = (a.webkitRelativePath || a.name || '').toLocaleLowerCase();
                const keyB = (b.webkitRelativePath || b.name || '').toLocaleLowerCase();
                return keyA.localeCompare(keyB, undefined, { numeric: true, sensitivity: 'base' });
            });

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

            let cursor = 0;
            let batchIndex = 0;
            while (cursor < totalFiles) {
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
                form.append('version_id', facVersionId);
                form.append('reset', batchIndex === 0 ? '1' : '0');
                chunk.forEach(file => form.append('images[]', file));

                try {
                    const res = await fetch(withBasePath('/api/upload_facsimiles'), {
                        method : 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content
                        },
                        body   : form
                    });

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
                } catch (err) {
                    console.error(err);
                    batchErrors.push({
                        range: `${start}-${end}`,
                        message: err.message
                    });
                }

                batchIndex += 1;
            }

        if (facSpinner) facSpinner.style.display = 'none';
        facUploadBtn.disabled = false;

        const success = uploadedCount && !processingIssues.length && !batchErrors.length;

        if (success) {
            if (facFileInput) facFileInput.value = '';
            if (facUploadBtn) facUploadBtn.disabled = true;
            if (facLog) facLog.textContent = '';
            if (facSummary) {
                facSummary.className = 'small text-success';
                facSummary.textContent = `✅ ${uploadedCount} fichier(s) importé(s)` + (lastStoredDir ? ` dans ${lastStoredDir}` : '');
            }
            document.dispatchEvent(new CustomEvent('facsimilesUploaded', {
                detail: { versionId: facVersionId }
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
            fetchVersions(selectedWorkId);
        }
    });

    /* ——— Upload submit ——— */
    document.getElementById('upload-version-form').addEventListener('submit',async ev=>{
        ev.preventDefault();
        if(!selectedWorkId) return alert('Veuillez sélectionner une œuvre.');
        const file        = $fileInput.files[0];
        const editionName = document.getElementById('editionName').value.trim();
        if(!file || !editionName) return alert('Merci de remplir tous les champs.');
        if(!file.name.toLowerCase().endsWith('.txt')) return alert('Seuls les fichiers .txt sont autorisés.');
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
                console.error(await res.text());
                return alert('Erreur de téléversement.');
            }
            await res.json();
            ev.target.reset();
            $fileInfo.textContent='';
            if (uploadModalInstance) { uploadModalInstance.hide(); }
            fetchVersions(selectedWorkId);
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
    const pill = document.getElementById('versions-count-pill');
    if (!pill) return;

    if (count === null || count === undefined) {
        pill.style.display = 'none';
        return;
    }

    const classes = count > 0
        ? 'badge bg-success-subtle text-success media-status-pill d-inline-flex align-items-center'
        : 'badge bg-danger-subtle text-danger media-status-pill d-inline-flex align-items-center';

    pill.className = classes;
    pill.style.display = 'inline-flex';
    const label = count === 1 ? '1 version' : `${count} versions`;
    pill.textContent = label;
    pill.title = label;
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

    if (facModalTitle) facModalTitle.textContent = `Téléverser des fac-similés — ${facVersionName}`;
    const versionShort = version.folder || '';
    const infoLabel = versionShort ? `${facVersionName} [${versionShort}]` : facVersionName;
    if (facModalInfo) facModalInfo.innerHTML = `Les fichiers seront importés dans l'ordre alphabétique-numérique de leur nom d'origine.<br><strong>Version cible :</strong> ${infoLabel}`;
    if (facFileInput) facFileInput.value = '';
    if (facLog) facLog.textContent = '';
    if (facSummary) facSummary.textContent = '';
    if (facSpinner) facSpinner.style.display = 'none';
    if (facUploadBtn) facUploadBtn.disabled = true;

    document.dispatchEvent(new CustomEvent('facsimiles:select', { detail: { versionId: facVersionId, versionName: facVersionName } }));

    facModalInstance = facModalInstance || new bootstrap.Modal(facModalEl);
    facModalInstance.show();
}

function collectSelectedImages() {
    if (!facFileInput || !facFileInput.files) return [];
    return Array.from(facFileInput.files).filter(file => {
        if (file.type && file.type.startsWith('image/')) return true;
        return /\.(jpe?g|png)$/i.test(file.name || '');
    });
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
            throw new Error(json.message ?? `HTTP ${res.status}`);
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

async function publishFacsimiles(version) {
    if (!version || !version.id) return;
    if (!confirm(`Publier les fac-similés pour "${version.name}" ?`)) return;
    try {
        const res = await fetch(withBasePath('/api/facsimiles/publish'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ version_id: version.id })
        });
        const payload = await readJsonResponse(res);
        alert(payload.message || 'Fac-similés publiés.');
        document.dispatchEvent(new CustomEvent('facsimilesUploaded', { detail: { versionId: version.id } }));
        fetchVersions(selectedWorkId);
    } catch (err) {
        console.error(err);
        alert(err.message || 'Erreur lors de la publication des fac-similés.');
    }
}

async function fetchVersions(workId){
    const list = document.getElementById('versions-list');
    if (!workId) {
        list.innerHTML = '<li class="list-group-item">Sélectionner une œuvre pour voir les versions</li>';
        updateVersionsCount(null);
        versionsCache = new Map();
        return;
    }
    list.innerHTML='<div class="text-muted p-2">Loading versions…</div>';
    try{
        const res = await fetch(withBasePath(`/api/versions?work_id=${workId}`));
        if(!res.ok) throw new Error(res.statusText);
        const data = await res.json();
        list.innerHTML='';
        const versions = Array.isArray(data) ? data : [];
        versionsCache = new Map(versions.map(v => [Number(v.id), v]));
        if(versions.length===0) {
            updateVersionsCount(0);
            list.innerHTML='<div class="text-muted p-2">No versions available</div>';
            return;
        }

        updateVersionsCount(versions.length);

        const table = document.createElement('table');
        table.className='table table-bordered table-hover table-sm version-table';
        table.innerHTML=`<thead class="table-light"><tr><th>ID</th><th>Dénomination</th><th>Dossier</th><th>Fac-similés</th><th class="text-end">Actions</th></tr></thead><tbody></tbody>`;
        const tbody = table.querySelector('tbody');
        versions.forEach(v=>{
            const tr = document.createElement('tr');
            const shortFolder = (v.folder || '').split('/').pop();
            tr.innerHTML=`<td>${v.id}</td><td>${v.name}</td><td>${shortFolder}</td>`;

                        const tdFac = document.createElement('td');
            tdFac.className = 'align-middle';
            const sourceCount = Number(v.facsimiles?.source_count ?? 0);
            const publishedCount = Number(v.facsimiles?.published_count ?? 0);
            const missingCount = Math.max(0, sourceCount - publishedCount);

            const facButtons = document.createElement('div');
            facButtons.className = 'btn-group btn-group-sm';

            const btnFacView = document.createElement('button');
            btnFacView.type = 'button';
            btnFacView.className = 'btn btn-outline-secondary d-inline-flex align-items-center gap-1';
            btnFacView.innerHTML = `<span class=\'badge bg-light text-muted border\'>${sourceCount}</span> Voir`;
            btnFacView.disabled = sourceCount === 0;
            btnFacView.title = sourceCount === 0 ? 'Aucune image à afficher' : 'Afficher la galerie de fac-similés';
            btnFacView.addEventListener('click', () => {
                document.dispatchEvent(new CustomEvent('facsimiles:select', {
                    detail: { versionId: v.id, versionName: v.name }
                }));
            });
            facButtons.appendChild(btnFacView);

            const btnFacUpload = document.createElement('button');
            btnFacUpload.type = 'button';
            btnFacUpload.className = 'btn btn-outline-primary';
            btnFacUpload.textContent = 'Téléverser';
            btnFacUpload.title = 'Importer de nouveaux fac-similés';
            btnFacUpload.addEventListener('click', () => {
                openFacsimileUploadModal(v);
            });
            facButtons.appendChild(btnFacUpload);

            const btnFacPublish = document.createElement('button');
            btnFacPublish.type = 'button';
            btnFacPublish.className = 'btn btn-outline-success d-inline-flex align-items-center gap-1';
            btnFacPublish.innerHTML = `<span class=\'badge bg-light text-muted border\'>${publishedCount}</span> Publier`;
            btnFacPublish.disabled = sourceCount === 0;
            btnFacPublish.title = sourceCount === 0 ? 'Aucune image à publier' : (missingCount > 0 ? `${missingCount} image(s) en attente` : 'Toutes les images sont publiées');
            btnFacPublish.addEventListener('click', () => { publishFacsimiles(v); });
            facButtons.appendChild(btnFacPublish);

            tdFac.appendChild(facButtons);
            tr.appendChild(tdFac);
const tdActions = document.createElement('td');
            tdActions.className='text-end align-middle';

            const viewUrl = withBasePath(`/view-version/${v.id}`);
            const editorUrl = withBasePath(`/versions/${v.id}/editor`);

            const btnView = document.createElement('a');
            btnView.href = viewUrl;
            btnView.target = '_blank';
            btnView.className = 'btn btn-sm btn-secondary me-1';
            btnView.textContent = 'View';
            tdActions.appendChild(btnView);

            const btnEditor = document.createElement('a');
            btnEditor.href = editorUrl;
            btnEditor.target = '_blank';
            btnEditor.className = 'btn btn-sm btn-info me-1';
            btnEditor.textContent = 'Editor';
            tdActions.appendChild(btnEditor);

            const btnEdit = document.createElement('button');
            btnEdit.className='btn btn-sm btn-primary me-1';
            btnEdit.textContent='Edit';
            btnEdit.addEventListener('click',()=>openEditModal(v));
            tdActions.appendChild(btnEdit);

            const btnDel = document.createElement('button');
            btnDel.className='btn btn-sm btn-danger';
            btnDel.textContent='Delete';
            btnDel.addEventListener('click',()=>confirmDeleteVersion(v));
            tdActions.appendChild(btnDel);

            tr.appendChild(tdActions);
            tbody.appendChild(tr);
        });
        list.appendChild(table);
    }catch(err){
        console.error(err);
        versionsCache = new Map();
        updateVersionsCount(null);
        list.innerHTML='<div class="text-danger p-2">Failed to load versions</div>';
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
        fetchVersions(selectedWorkId);
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
    try{
        const res = await fetch(withBasePath(`/api/versions/${versionToDelete}`),{
            method:'DELETE',
            headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}
        });
        if(!res.ok) throw new Error(await res.text());
        await res.json();
        bootstrap.Modal.getInstance(document.getElementById('deleteVersionConfirm')).hide();
        fetchVersions(selectedWorkId);
        document.dispatchEvent(new CustomEvent('versionsUpdated',{detail:{workId:selectedWorkId}}));
    }catch(err){
        console.error(err);
        alert('Could not delete version.');
    }
}
</script>
@endpush
