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
/********************** RENDER ****************************/
function buildVersionsTable(data){
    const table = document.createElement('table');
    table.className = 'table table-bordered table-hover table-sm version-table mb-0';
    table.innerHTML = `
        <thead class="table-light"><tr>
            <th>ID</th><th>Dénomination</th><th>Dossier</th><th>Fac-similés</th>
            <th class="text-end">Actions</th>
        </tr></thead><tbody></tbody>`;
    const tbody = table.querySelector('tbody');
    data.forEach(v=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${v.id}</td><td>${v.name}</td><td>${v.folder.split('/').pop()}</td>`;
        const tdStatus = document.createElement('td');
        tdStatus.appendChild(renderFacsimileStatus(v));
        tr.appendChild(tdStatus);
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
