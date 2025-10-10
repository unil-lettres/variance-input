@php /** components/main/versions_list.blade.php **/ @endphp
<div class="card">
    <div class="card-header">Versions disponibles</div>
    <div class="card-body p-0">
        <ul id="versions-list" class="list-group rounded-0">
            <li class="list-group-item">Sélectionner une œuvre pour voir les versions</li>
        </ul>
    </div>
</div>

<!-- ────── Modals shared with upload blade ────── -->
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

@push('scripts')
<style>
    .version-table th { font-weight: normal; font-size: 1rem; color: #333; }
    .version-table td { vertical-align: middle; }
</style>
<script>
/**************** GLOBAL shared from upload ***************/
window.vg = window.vg || { selectedWorkId:null, shortTitle:null, authorId:null };
let versionToDelete = null;
const publishLocks = new Set();
const lignesLocks = new Set();
const pagerLocks = new Set();
const pagerCompletionNotified = new Set();
const pagerPollers = new Map();

const formatTimestamp = ts => ts ? new Date(ts * 1000).toLocaleString('fr-FR', { hour12: false }) : null;

function buildPagerStatusText(progress){
    const fmt = (n) => (typeof n === 'number' && Number.isFinite(n)) ? n : 0;
    if (!progress || !progress.status) {
        return 'Pagination : aucune exécution enregistrée';
    }
    const status = progress.status;
    const suffix = formatTimestamp(progress.updated_at) ? ` (maj ${formatTimestamp(progress.updated_at)})` : '';

    if (status === 'queued') {
        return `🕒 En file d'attente…${suffix}`;
    }

    if (status === 'running') {
        const source = progress.source || {};
        const target = progress.target || {};
        const total = progress.entries_total || 0;
        const processedRaw = Math.max(fmt(source.processed), fmt(target.processed));
        const processed = total ? Math.min(processedRaw, total) : processedRaw;
        const inserted = fmt(source.inserted) + fmt(target.inserted);
        const missed   = fmt(source.missed) + fmt(target.missed);
        const segments = [];
        segments.push(`source : ${fmt(source.inserted)} ins · ${fmt(source.missed)} ratés`);
        if ((target.comparisons ?? 0) > 0) {
            segments.push(`cible : ${fmt(target.inserted)} ins · ${fmt(target.missed)} ratés`);
        }
        return `⏳ Progression : ${processed}/${total || '—'} — ${segments.join(' · ')} · total insérés : ${inserted}, manqués : ${missed}${suffix}`;
    }

    if (status === 'failed') {
        const error = progress.error || 'opération interrompue';
        return `❌ Échec : ${error}${suffix}`;
    }

    if (status === 'done') {
        const summary = progress.summary || {};
        const source = summary.source || progress.source || {};
        const target = summary.target || progress.target || {};
        const totalInserted = fmt(source.inserted) + fmt(target.inserted);
        const totalMissed   = fmt(source.missed) + fmt(target.missed);
        return `✅ Terminé — insérés : ${totalInserted}, manqués : ${totalMissed}${suffix}`;
    }

    return `ℹ️ Statut : ${status}${suffix}`;
}
/********************** RENDER ****************************/
function buildVersionsTable(data){
    const table = document.createElement('table');
    table.className = 'table table-bordered table-hover table-sm version-table mb-0';
    table.innerHTML = `
        <thead class="table-light"><tr>
            <th>ID</th><th>Dénomination</th><th>Dossier</th><th>Fac-similés</th><th>Pagination</th>
            <th class="text-end">Actions</th>
        </tr></thead><tbody></tbody>`;
    const tbody = table.querySelector('tbody');
    data.forEach(v=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${v.id}</td><td>${v.name}</td><td>${v.folder.split('/').pop()}</td>`;
        const tdStatus = document.createElement('td');
        tdStatus.appendChild(renderFacsimileStatus(v));
        tr.appendChild(tdStatus);
        const tdPaging = document.createElement('td');
        tdPaging.appendChild(renderPageMarkerStatus(v));
        tr.appendChild(tdPaging);
        const tdA = document.createElement('td');
        tdA.className='text-end';
        tdA.innerHTML = `
            <a href="/view-version/${v.id}" target="_blank" class="btn btn-sm btn-secondary me-1">View</a>
            <a href="/versions/${v.id}/editor" target="_blank" class="btn btn-sm btn-info me-1">Editor</a>`;
        const btnE = document.createElement('button');
        btnE.className='btn btn-sm btn-primary me-1';
        btnE.textContent='Edit';
        btnE.addEventListener('click',()=>openEditModal(v));
        tdA.appendChild(btnE);
        const btnD = document.createElement('button');
        btnD.className='btn btn-sm btn-danger';
        btnD.textContent='Delete';
        btnD.addEventListener('click',()=>confirmDeleteVersion(v));
        tdA.appendChild(btnD);
        tr.appendChild(tdA);
        tbody.appendChild(tr);
    });
    return table;
}

function renderPageMarkerStatus(version){
    const status = version.page_markers || {};
    const wrap = document.createElement('div');

    const total = status.total ?? 0;
    const badge = document.createElement('div');
    badge.innerHTML = `<span class="badge bg-secondary">${total} pages</span>`;
    wrap.appendChild(badge);

    const clearId   = `pager-clear-${version.id}`;
    const replaceId = `pager-replace-${version.id}`;
    const options = document.createElement('div');
    options.className = 'mt-2';
    options.innerHTML = `
        <div class="form-check form-check-sm">
            <input class="form-check-input" type="checkbox" id="${clearId}" checked>
            <label class="form-check-label small" for="${clearId}">
                Supprimer tous les marqueurs existants
            </label>
        </div>
        <div class="form-check form-check-sm">
            <input class="form-check-input" type="checkbox" id="${replaceId}" checked>
            <label class="form-check-label small" for="${replaceId}">
                Remplacer les marqueurs existants du même fac-similé
            </label>
        </div>`;
    wrap.appendChild(options);

    const clearToggle = options.querySelector(`#${clearId}`);
    const replaceToggle = options.querySelector(`#${replaceId}`);
    if (clearToggle && replaceToggle) {
        replaceToggle.disabled = clearToggle.checked;
        clearToggle.addEventListener('change', () => {
            replaceToggle.disabled = clearToggle.checked;
            if (clearToggle.checked) {
                replaceToggle.checked = true;
            }
        });
    }

    const uploadBtn = document.createElement('button');
    uploadBtn.type = 'button';
    uploadBtn.className = 'btn btn-sm btn-outline-secondary mt-2';
    uploadBtn.textContent = '_lignes → pages';

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.txt,.tsv,.csv';
    input.className = 'd-none';
    input.addEventListener('change', (e) => {
        if (!input.files || !input.files.length) return;
        uploadPageMarkers(version, input.files[0], uploadBtn);
        input.value = '';
    });

    uploadBtn.addEventListener('click', () => {
        if (pagerLocks.has(version.id)) return;
        const progressEl = document.getElementById(`pager-status-${version.id}`);
        if (progressEl) progressEl.textContent = 'Préparation…';
        input.click();
    });

    wrap.appendChild(input);
    wrap.appendChild(uploadBtn);

    const progress = document.createElement('div');
    progress.id = `pager-status-${version.id}`;
    progress.className = 'text-muted small mt-1';
    progress.textContent = buildPagerStatusText(version.page_marker_progress || null);
    wrap.appendChild(progress);

    return wrap;
}

function renderFacsimileStatus(version){
    const status = version.facsimiles || {};
    const wrap = document.createElement('div');

    if (!status.source_count) {
        wrap.innerHTML = '<span class="text-muted">Aucun fac-similé</span>';
        return wrap;
    }

    const badges = document.createElement('div');
    badges.innerHTML = `<span class="badge bg-secondary me-1">${status.source_count} source</span>` +
        `<span class="badge ${status.in_sync ? 'bg-success' : 'bg-warning text-dark'}">${status.published_count} publié</span>`;
    wrap.appendChild(badges);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm mt-2 ' + (status.in_sync ? 'btn-outline-success' : 'btn-outline-primary');
    btn.textContent = status.in_sync ? 'À jour' : 'Publier';
    const needsPublish = status.source_count !== status.published_count;
    btn.disabled = publishLocks.has(version.id) || !status.can_publish || (status.in_sync && !needsPublish);
    btn.addEventListener('click', () => publishFacsimiles(version, btn));
    wrap.appendChild(btn);

    if (status.dest_dir && status.published_count) {
        const hint = document.createElement('div');
        hint.className = 'text-muted small mt-1';
        hint.textContent = status.dest_dir.replace('/var/www/variance', '');
        wrap.appendChild(hint);
    }

    return wrap;
}

function publishFacsimiles(version, triggerButton){
    if (publishLocks.has(version.id)) return;
    if (!window.confirm(`Publier les fac-similés de « ${version.name} » ?`)) return;

    publishLocks.add(version.id);
    const originalLabel = triggerButton ? triggerButton.textContent : '';
    if (triggerButton) {
        triggerButton.disabled = true;
        triggerButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }

    fetch('/api/facsimiles/publish', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ version_id: version.id })
    })
    .then(async res => {
        const payload = await res.json();
        if (!res.ok) {
            throw new Error(payload.message || `HTTP ${res.status}`);
        }
        alert(payload.message || 'Publication terminée');
        fetchVersions(vg.selectedWorkId);
        document.dispatchEvent(new CustomEvent('facsimilesPublished', { detail: { versionId: version.id } }));
    })
    .catch(err => {
        console.error(err);
        alert('Publication impossible : ' + err.message);
    })
    .finally(() => {
        publishLocks.delete(version.id);
        if (triggerButton) {
            triggerButton.disabled = false;
            triggerButton.textContent = originalLabel || 'Publier';
        }
    });
}

async function uploadPageMarkers(version, file, triggerButton){
    if (pagerLocks.has(version.id)) return;

    pagerLocks.add(version.id);
    pagerCompletionNotified.delete(version.id);

    const originalLabel = triggerButton ? triggerButton.textContent : '';
    if (triggerButton) {
        triggerButton.disabled = true;
        triggerButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }

    const progressEl = document.getElementById(`pager-status-${version.id}`);
    if (progressEl) progressEl.textContent = 'Préparation…';

    const form = new FormData();
    form.append('lignes', file);
    const clearToggle = document.getElementById(`pager-clear-${version.id}`);
    const replaceToggle = document.getElementById(`pager-replace-${version.id}`);
    const clearExisting = clearToggle ? clearToggle.checked : true;
    const replaceExisting = replaceToggle ? replaceToggle.checked : true;
    form.append('clear_existing', clearExisting ? '1' : '0');
    form.append('replace_existing', replaceToggle && replaceToggle.disabled ? '1' : (replaceExisting ? '1' : '0'));

    let pollTimer = null;
    let cleanedUp = false;

    const cleanup = (status) => {
        if (cleanedUp) return;
        cleanedUp = true;
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        pagerPollers.delete(version.id);
        pagerLocks.delete(version.id);
        if (triggerButton) {
            triggerButton.disabled = false;
            triggerButton.textContent = originalLabel || '_lignes → pages';
        }
    };

    const fmt = (n) => (typeof n === 'number' ? n : 0);

    const ensurePolling = () => {
        const tick = async () => {
            const targetEl = document.getElementById(`pager-status-${version.id}`);
            try {
                const res = await fetch(`/api/versions/${version.id}/page-markers/progress?ts=${Date.now()}`, {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });
                if (!res.ok) return;
                const p = await res.json();
                if (!p || !p.status || p.status === 'idle') {
                    if (targetEl) targetEl.textContent = 'Pagination : initialisation en cours…';
                    return;
                }

                if (targetEl) {
                    targetEl.textContent = buildPagerStatusText(p);
                }

                if (p.status === 'queued') {
                    return;
                }

                if (p.status === 'failed') {
                    if (!pagerCompletionNotified.has(version.id)) {
                        alert("Impossible d'appliquer le fichier _lignes : " + (p.error || 'erreur inconnue'));
                        pagerCompletionNotified.add(version.id);
                    }
                    cleanup('failed');
                    return;
                }

                if (p.status === 'done') {
                    const summary = p.summary || {};
                    const src = summary.source || {};
                    const tgt = summary.target || {};
                    const msg = `Page markers mis à jour — source : ${fmt(src.inserted)} (miss: ${fmt(src.missed)}) · cible : ${fmt(tgt.inserted)} (miss: ${fmt(tgt.missed)})`;
                    if (!pagerCompletionNotified.has(version.id)) {
                        alert(msg);
                        pagerCompletionNotified.add(version.id);
                    }
                    cleanup('done');
                    if (vg.selectedWorkId) {
                        fetchVersions(vg.selectedWorkId);
                    }
                    return;
                }
            } catch (err) {
                console.error(err);
                const targetEl = document.getElementById(`pager-status-${version.id}`);
                if (targetEl) targetEl.textContent = '⚠️ Erreur de suivi, nouvelle tentative…';
            }
        };

        if (pagerPollers.has(version.id)) {
            pollTimer = pagerPollers.get(version.id);
            tick();
            return;
        }

        pollTimer = setInterval(tick, 1000);
        pagerPollers.set(version.id, pollTimer);
        tick();
    };

    try {
        ensurePolling();
        const res = await fetch(`/api/versions/${version.id}/page-markers`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: form
        });

        const payload = await res.json();
        if (!res.ok) {
            if (res.status === 409 && payload?.status === 'busy') {
                if (progressEl) {
                    progressEl.textContent = payload.message || 'Une importation _lignes est déjà en cours…';
                }
                pagerLocks.delete(version.id);
                if (triggerButton) {
                    triggerButton.disabled = false;
                    triggerButton.textContent = originalLabel || '_lignes → pages';
                }
                ensurePolling();
                alert(payload.message || "Une importation _lignes est déjà en cours pour cette version.");
                return;
            }
            throw new Error(payload.message || `HTTP ${res.status}`);
        }

        ensurePolling();

        if (payload.status && payload.status !== 'queued') {
            // Edge-case: synchronous completion
            if (payload.summary) {
                const srcInserted = fmt(payload.summary.source?.inserted);
                const tgtInserted = fmt(payload.summary.target?.inserted);
                const srcMissed   = fmt(payload.summary.source?.missed);
                const tgtMissed   = fmt(payload.summary.target?.missed);
                alert(`Page markers mis à jour — source : ${srcInserted} (miss: ${srcMissed}) · cible : ${tgtInserted} (miss: ${tgtMissed})`);
                pagerCompletionNotified.add(version.id);
                cleanup('done');
                if (vg.selectedWorkId) {
                    fetchVersions(vg.selectedWorkId);
                }
            }
        }
    } catch (err) {
        console.error(err);
        cleanup('failed');
        alert("Impossible d'appliquer le fichier _lignes : " + err.message);
    }
}
/******************** FETCH LIST **************************/
async function fetchVersions(workId){
    const list = document.getElementById('versions-list');
    list.innerHTML = '<li class="list-group-item text-muted">Loading…</li>';
    try{
        const res = await fetch(`/api/versions?work_id=${workId}`);
        if(!res.ok) throw new Error(res.statusText);
        const data = await res.json();
        list.innerHTML='';
        if(!data.length) return list.innerHTML='<li class="list-group-item">Aucune version</li>';
        const wrapper = document.createElement('div');
        wrapper.appendChild(buildVersionsTable(data));
        list.appendChild(wrapper);
    }catch(e){
        console.error(e);
        list.innerHTML='<li class="list-group-item text-danger">Erreur de chargement</li>';
    }
}

document.addEventListener('facsimilesUploaded', () => {
    if (vg.selectedWorkId) {
        fetchVersions(vg.selectedWorkId);
    }
});
/******************** EDIT / UPDATE ***********************/
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
        const res = await fetch(`/api/versions/${id}`,{
            method:'PUT',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
            body:JSON.stringify({name})
        });
        if(!res.ok) throw new Error(res.statusText);
        await res.json();
        bootstrap.Modal.getInstance(document.getElementById('editVersionModal')).hide();
        fetchVersions(vg.selectedWorkId);
        document.dispatchEvent(new CustomEvent('versionsUpdated',{detail:{workId:vg.selectedWorkId}}));
    }catch(err){ console.error(err); alert('Update failed'); }
}
/******************** DELETE *****************************/
function confirmDeleteVersion(v){ versionToDelete=v.id; new bootstrap.Modal(document.getElementById('deleteVersionConfirm')).show(); }
async function doDeleteVersion(){
    if(!versionToDelete) return;
    try{
        const res = await fetch(`/api/versions/${versionToDelete}`,{
            method:'DELETE',
            headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}
        });
        if(!res.ok) throw new Error(await res.text());
        await res.json();
        bootstrap.Modal.getInstance(document.getElementById('deleteVersionConfirm')).hide();
        fetchVersions(vg.selectedWorkId);
        document.dispatchEvent(new CustomEvent('versionsUpdated',{detail:{workId:vg.selectedWorkId}}));
    }catch(err){ console.error(err); alert('Delete failed'); }
}
/******************** LISTENERS **************************/
document.addEventListener('DOMContentLoaded',()=>{
    document.addEventListener('workSelected',e=>{
        vg.selectedWorkId=e.detail.workId;
        fetchVersions(vg.selectedWorkId);
    });
    document.addEventListener('versionsUpdated',e=>{
        if(e.detail.workId===vg.selectedWorkId) fetchVersions(vg.selectedWorkId);
    });
    document.getElementById('update-version-btn').addEventListener('click',updateVersionName);
    document.getElementById('confirm-delete-version').addEventListener('click',doDeleteVersion);
});
</script>
@endpush
