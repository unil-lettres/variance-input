@php /** components/main/versions_upload.blade.php **/ @endphp
<div class="card mb-4">
    <div class="card-header">Ajouter une version</div>
    <div class="card-body">
        <!-- ─── Upload form ─── -->
        <form id="upload-version-form" enctype="multipart/form-data">
            @csrf
            <!-- File selector -->
            <div class="mb-3">
                <label for="versionFile" class="form-label">Fichier <code>.txt</code></label>
                <input type="file"
                       name="versionFile"
                       id="versionFile"
                       class="form-control"
                       accept=".txt"
                       required>
                <div id="file-info" class="form-text text-muted"></div>
            </div>

            <!-- Edition name -->
            <div class="mb-3">
                <label for="editionName" class="form-label">Désignation (éditeur / année)</label>
                <input type="text"
                       name="editionName"
                       id="editionName"
                       class="form-control"
                       placeholder="Grasset (1913)"
                       required>
            </div>

            <button type="submit" class="btn btn-primary">Téléverser</button>
        </form>
    </div>
</div>

@push('scripts')
<!-- Heuristic charset detector -->
<!-- jschardet heuristic detector -->
<script src="https://cdn.jsdelivr.net/npm/jschardet/dist/jschardet.min.js"></script>
<script>
/*********************** CONFIG *************************/
const MAX_TXT_CHARACTERS = 1_800_000; // ≈ 1 000 pages
/******************* SHARED GLOBAL **********************/
window.vg = window.vg || { selectedWorkId:null, shortTitle:null, authorId:null };
let detectedEncoding = 'Unknown';
/********************* HELPERS **************************/
const formatNumber = n => n.toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ');
function detectEncodingBOM(file){
    return new Promise(resolve=>{
        const r = new FileReader();
        r.onload=e=>{
            const v=new Uint8Array(e.target.result||new ArrayBuffer(0));
            if(v.length>=3 && v[0]===0xEF && v[1]===0xBB && v[2]===0xBF) return resolve('UTF‑8 (BOM)');
            if(v.length>=2 && v[0]===0xFE && v[1]===0xFF) return resolve('UTF‑16 BE (BOM)');
            if(v.length>=2 && v[0]===0xFF && v[1]===0xFE) return resolve('UTF‑16 LE (BOM)');
            resolve('No BOM / Unknown');
        };
        r.onerror=()=>resolve('Unknown');
        r.readAsArrayBuffer(file.slice(0,4));
    });
}
async function heuristicDetect(file){
    try {
        const buf = await file.arrayBuffer();
        const guess = jschardet.detect(new Uint8Array(buf));
        if (guess.encoding && guess.confidence > 0.2) {           // ↓ lower threshold
            let enc = guess.encoding.toUpperCase();
            if (enc.startsWith('ISO-8859') || enc === 'WINDOWS-1252') enc = 'WINDOWS-1252';
            if (enc === 'ASCII') enc = 'ASCII';
            return enc;
        }
    } catch(err) { console.warn('jschardet error', err); }
    return 'Unknown';
}
/********************** MAIN ****************************/
window.addEventListener('DOMContentLoaded',()=>{
    const $fileInput=document.getElementById('versionFile');
    const $fileInfo =document.getElementById('file-info');

    /* workSelected updates context */
    document.addEventListener('workSelected',e=>{
        vg.selectedWorkId=e.detail.workId;
        vg.authorId=e.detail.authorId;
        vg.shortTitle=e.detail.short_title||null;
    });

    /* File change → size + encoding */
    $fileInput.addEventListener('change',async()=>{
        const file=$fileInput.files[0];
        $fileInfo.innerHTML='';
        detectedEncoding='Unknown';
        if(!file) return;
        if(!file.name.toLowerCase().endsWith('.txt')){
            $fileInfo.textContent='❌ extension invalide (uniquement .txt)';
            $fileInput.value='';
            return;
        }
        // 1) BOM detection
        detectedEncoding=await detectEncodingBOM(file);
        // 2) Heuristic detection when no BOM
        if (detectedEncoding === 'No BOM / Unknown') {
            const guess = await heuristicDetect(file);
            if (guess !== 'Unknown') {
                detectedEncoding = `${guess} (heuristic)`;
            } else {
                // Final fallback: attempt strict UTF‑8 decode
                const raw = new Uint8Array(await file.arrayBuffer());
                try {
                    new TextDecoder('utf-8', {fatal: true}).decode(raw);
                    detectedEncoding = 'UTF‑8 (no BOM)'; // bytes form valid UTF‑8
                } catch (_) {
                    detectedEncoding = 'WINDOWS-1252 (assumed)';
                }
            }
        }
        // 3) Char count / size feedback / size feedback / size feedback
        try{
            const txt=await file.text();
            const len=txt.length;
            $fileInfo.innerHTML=`Encodage : <strong>${detectedEncoding}</strong><br>`+
                               `Caractères : <strong>${formatNumber(len)}</strong> / ${formatNumber(MAX_TXT_CHARACTERS)}`+
                               (len>MAX_TXT_CHARACTERS? ' – <span class="text-danger">fichier trop volumineux</span>':'' );
        }catch(err){ console.error(err); $fileInfo.textContent='Erreur lecture fichier.'; }
    });

    /* Submit upload */
    document.getElementById('upload-version-form').addEventListener('submit',async ev=>{
        ev.preventDefault();
        if(!vg.selectedWorkId) return alert('Veuillez sélectionner une œuvre.');
        const file=$fileInput.files[0];
        const edition=document.getElementById('editionName').value.trim();
        if(!file||!edition) return alert('Merci de remplir tous les champs.');
        if(!file.name.toLowerCase().endsWith('.txt')) return alert('Seuls les fichiers .txt sont autorisés.');
        const txt=await file.text();
        if(txt.length>MAX_TXT_CHARACTERS) return alert('Fichier trop volumineux.');

        const fd=new FormData();
        fd.append('work_id',vg.selectedWorkId);
        fd.append('versionFile',file);
        fd.append('name',edition);
        fd.append('original_encoding',detectedEncoding);
        if(vg.shortTitle) fd.append('short_title',vg.shortTitle);

        try{
            const res=await fetch('/api/versions',{
                method:'POST',
                headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},
                body:fd
            });
            if(!res.ok){ console.error(await res.text()); return alert('Erreur de téléversement.'); }
            await res.json();
            alert('Version téléversée !');
            ev.target.reset();
            $fileInfo.textContent='';
            document.dispatchEvent(new CustomEvent('versionsUpdated',{detail:{workId:vg.selectedWorkId}}));
        }catch(err){ console.error(err); alert('Erreur réseau ou serveur.'); }
    });
});
</script>
@endpush
