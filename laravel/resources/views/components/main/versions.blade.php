@php /**  components/main/versions.blade.php  **/ @endphp
<div class="card">
    <div class="card-header">Versions</div>
    <div class="card-body">
        <!-- ────────────── Upload form ────────────── -->
        <form id="upload-version-form" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="versionFile" class="form-label">Sélectionner un fichier <code>.txt</code> à téléverser</label>
                <input type="file"
                       name="versionFile"
                       id="versionFile"
                       class="form-control"
                       accept=".txt"
                       required>
                <div id="file-info" class="form-text text-muted"></div>
            </div>

            <div class="mb-3">
                <label for="editionName" class="form-label">Désignation pour cette version</label>
                <input type="text"
                       name="editionName"
                       id="editionName"
                       class="form-control"
                       placeholder="Grasset (1913)"
                       required>
            </div>

            <button type="submit" class="btn btn-primary">Téléverser</button>
        </form>

        <hr>

        <!-- ────────────── Versions list  ────────────── -->
        <ul id="versions-list" class="list-group">
            <li class="list-group-item">Sélectionner une œuvre pour voir les versions</li>
        </ul>
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
    /* Keep table headers visually consistent with card header */
    .version-table th { font-weight: normal; font-size: 1rem; color: #333; }
    .version-table td { vertical-align: middle; }
</style>

<script>
/***********************  CONFIG  *************************/
const MAX_TXT_CHARACTERS = 1_800_000; // ≈ 1 000 pages @ 1 800 chars/page
/*********************  GLOBAL STATE  *********************/
let selectedWorkId   = null;
let shortTitle       = null;
let authorId         = null;
let versionToDelete  = null;
let detectedEncoding = 'Unknown';
/*********************  UTIL HELPERS  *********************/
const formatNumber = n => n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
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
    const $fileInput = document.getElementById('versionFile');
    const $fileInfo  = document.getElementById('file-info');

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

    /* ——— Custom events from parent blades ——— */
    document.addEventListener('workSelected', e=>{
        selectedWorkId = e.detail.workId;
        authorId       = e.detail.authorId;
        shortTitle     = e.detail.short_title || null;
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
            const res = await fetch('/api/versions',{
                method:'POST',
                headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},
                body:fd
            });
            if(!res.ok){
                console.error(await res.text());
                return alert('Erreur de téléversement.');
            }
            await res.json();
            alert('Version téléversée avec succès !');
            ev.target.reset();
            $fileInfo.textContent='';
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
async function fetchVersions(workId){
    const list = document.getElementById('versions-list');
    list.innerHTML='<div class="text-muted p-2">Loading versions…</div>';
    try{
        const res = await fetch(`/api/versions?work_id=${workId}`);
        if(!res.ok) throw new Error(res.statusText);
        const data = await res.json();
        list.innerHTML='';
        if(data.length===0) return list.innerHTML='<div class="text-muted p-2">No versions available</div>';

        const table = document.createElement('table');
        table.className='table table-bordered table-hover table-sm version-table';
        table.innerHTML=`<thead class="table-light"><tr><th>ID</th><th>Dénomination</th><th>Dossier</th><th class="text-end">Actions</th></tr></thead><tbody></tbody>`;
        const tbody = table.querySelector('tbody');
        data.forEach(v=>{
            const tr = document.createElement('tr');
            tr.innerHTML=`<td>${v.id}</td><td>${v.name}</td><td>${v.folder.split('/').pop()}</td>`;
            const tdActions = document.createElement('td');
            tdActions.className='text-end';
            tdActions.innerHTML=`
                <a href="/view-version/${v.id}" target="_blank" class="btn btn-sm btn-secondary me-1">View</a>
                <a href="/versions/${v.id}/editor" target="_blank" class="btn btn-sm btn-info me-1">Editor</a>`;
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
        const res = await fetch(`/api/versions/${id}`,{
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
        const res = await fetch(`/api/versions/${versionToDelete}`,{
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