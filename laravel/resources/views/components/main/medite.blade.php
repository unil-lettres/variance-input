@php /** components/main/medite.blade.php **/ @endphp
<div class="card">
    <div class="card-header">Medite Script</div>
    <div class="card-body">
        <form id="medite-form">
            @csrf
            <!-- Hidden context -->
            <input type="hidden" id="work_id"          name="work_id">
            <input type="hidden" id="author_id"        name="author_id">
            <input type="hidden" id="author_name"      name="author_name">
            <input type="hidden" id="work_name"        name="work_name">
            <input type="hidden" id="comparison_id"    name="comparison_id">
            <input type="hidden" id="src_short"        name="src_short">
            <input type="hidden" id="tgt_short"        name="tgt_short">
            <input type="hidden" id="output_xml"       name="output_xml">
            <input type="hidden" id="xhtml_output_dir" name="xhtml_output_dir">
            <input type="hidden" id="sep"             name="sep" value=",.;?!"> <!-- default separators -->

            <!-- User‑visible fields -->
            <div class="mb-3">
                <label for="source_version" class="form-label">Source Version</label>
                <select id="source_version" name="source_version" class="form-control" required></select>
            </div>
            <div class="mb-3">
                <label for="target_version" class="form-label">Target Version</label>
                <select id="target_version" name="target_version" class="form-control" required></select>
            </div>
            <div class="mb-3">
                <label for="lg_pivot" class="form-label">Pivot Length</label>
                <input type="number" id="lg_pivot" name="lg_pivot" class="form-control" value="7" required>
            </div>
            <div class="mb-3">
                <label for="ratio" class="form-label">Ratio</label>
                <input type="number" id="ratio" name="ratio" class="form-control" value="15" required>
            </div>
            <div class="mb-3">
                <label for="seuil" class="form-label">Threshold</label>
                <input type="number" id="seuil" name="seuil" class="form-control" value="50" required>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="case_sensitive" name="case_sensitive" checked>
                <label class="form-check-label" for="case_sensitive">Case Sensitive</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="diacri_sensitive" name="diacri_sensitive" checked>
                <label class="form-check-label" for="diacri_sensitive">Diacritical Sensitive</label>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Run Medite</button>
        </form>

        <!-- Progress / Results unchanged -->
        <div id="progress-indicator" class="mt-4" style="display:none;"></div>
        <div id="results" class="mt-4" style="display:none;">
            <h5>Results</h5>
            <a id="result-html" href="#" target="_blank">View HTML Result</a><br>
            <a id="result-xml" href="#" target="_blank">Download XML Result</a>
        </div>
    </div>
</div>

@push('scripts')
<script>
/*------------------------------------------------------------*
 | Shared helpers                                              |
 *------------------------------------------------------------*/
const $srcSel  = document.getElementById('source_version');
const $tgtSel  = document.getElementById('target_version');
const $form    = document.getElementById('medite-form');
const $progress= document.getElementById('progress-indicator');
const $results = document.getElementById('results');
const $resHtml = document.getElementById('result-html');
const $resXml  = document.getElementById('result-xml');
const CSRF     = document.querySelector('meta[name="csrf-token"]').content;

function fillHidden(id,val){ document.getElementById(id).value = val; }

/*------------------------------------------------------------*
 | 1) Populate dropdowns when a work is selected               |
 *------------------------------------------------------------*/
async function refreshVersions(workId){
    $srcSel.innerHTML = '<option value="">Select Source Version</option>';
    $tgtSel.innerHTML = '<option value="">Select Target Version</option>';
    const res = await fetch(`/api/versions?work_id=${workId}`);
    if(!res.ok) return;
    const vers = await res.json();
    vers.forEach(v=>{
        const opt1 = new Option(v.name, v.id);
        const opt2 = new Option(v.name, v.id);
        opt1.dataset.short = v.folder;   // e.g. "1pda"
        opt2.dataset.short = v.folder;
        $srcSel.add(opt1); $tgtSel.add(opt2);
    });
}

/* workSelected comes from outer UI */
document.addEventListener('workSelected', e=>{
    const {workId, authorId, authorName, workName} = e.detail;
    fillHidden('work_id', workId);
    fillHidden('author_id', authorId||'');
    fillHidden('author_name', authorName||'');
    fillHidden('work_name', workName||'');
    refreshVersions(workId);
});

document.addEventListener('versionsUpdated', e=>{
    if(e.detail.workId) refreshVersions(e.detail.workId);
});

/*------------------------------------------------------------*
 | 2) On submit → add silent params then POST to backend       |
 *------------------------------------------------------------*/
$form.addEventListener('submit', async ev => {
    ev.preventDefault();

    /* basic selection guard */
    if (!$srcSel.value || !$tgtSel.value) {
        alert('Select both versions'); return;
    }

    /* short names come from <option data-short="1pda"> */
    const srcShort = $srcSel.selectedOptions[0].dataset.short;
    const tgtShort = $tgtSel.selectedOptions[0].dataset.short;
    fillHidden('src_short', srcShort);
    fillHidden('tgt_short', tgtShort);

    /*──────────────────── 1) Reserve a comparison row ────────────────────*/
    const cmpResp = await fetch('/api/comparisons', {
        method : 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept'      : 'application/json',   // ensures JSON error on 4xx/5xx
            'X-CSRF-TOKEN': CSRF
        },
        body: JSON.stringify({
            /* IDs */
            source_id:        $srcSel.value,
            target_id:        $tgtSel.value,

            /* Unique folder short-name */
            folder:           `${srcShort}-${tgtShort}`,

            /* Medite parameters you may want to persist */
            lg_pivot:         +document.getElementById('lg_pivot').value,
            ratio:            +document.getElementById('ratio').value,
            seuil:            +document.getElementById('seuil').value,
            sep:              document.getElementById('sep').value,

            /* Flags as simple booleans (true / false) */
            case_sensitive:   document.getElementById('case_sensitive').checked,
            diacri_sensitive: document.getElementById('diacri_sensitive').checked
        })

    });

    if (!cmpResp.ok) {
        /* show Laravel’s JSON message or fallback to raw text */
        const err = await cmpResp.clone().json().catch(() => cmpResp.text());
        console.error('Create-comparison error', cmpResp.status, err);
        alert(`Create comparison failed (${cmpResp.status}). Check console.`);
        return;
    }

    const cmpData = await cmpResp.json();        // { id, folder, … }
    fillHidden('comparison_id', cmpData.id);

    /*──────────────────── 2) Build output paths for Flask ───────────────*/
    const authorSlug = document.getElementById('author_name').value || 'author';
    const workSlug   = document.getElementById('work_name').value   || 'work';
    const cmpFolder  = `/app/uploads/${authorSlug}/${workSlug}/comparisons/${cmpData.id}`;

    fillHidden('output_xml',        `${cmpFolder}/${srcShort}-${tgtShort}.xml`);
    fillHidden('xhtml_output_dir',  cmpFolder);

    /*──────────────────── 3) Launch Medite via Laravel proxy ────────────*/
    $progress.style.display = 'block';
    $progress.innerHTML = `
        <p>Processing…
           <span class="spinner-border spinner-border-sm" role="status"></span>
        </p>`;

    const fd  = new FormData($form);   // includes all visible + hidden fields

    console.log('Medite FormData 🚀', Object.fromEntries(fd));



    const run = await fetch('/api/run_medite', {
        method : 'POST',
        body   : fd,
        headers: {
            'X-CSRF-TOKEN': CSRF,
            'Accept'      : 'application/json'
        }
    });

    if (!run.ok) {
        const err = await run.clone().json().catch(() => run.text());
        console.error('run_medite error', run.status, err);
        $progress.innerHTML =
            '<div class="alert alert-danger">Launch failed</div>';
        return;
    }

    const { task_id } = await run.json();
    pollTask(task_id, cmpData.id);     // keep existing polling logic
});


/*------------------------------------------------------------*
 | 3) Poll task status                                         |
 *------------------------------------------------------------*/
function pollTask(taskId, cmpId){
    let retries = 0;
    const max = 120;

    const timer = setInterval(async () => {
        if (++retries > max) {
            clearInterval(timer);
            $progress.textContent = 'Timeout';
            return;
        }

        const r = await fetch(`/api/task_status/${taskId}`);
        const d = await r.json();
        if (d.status === 'pending') return;   // still running

        clearInterval(timer);

        /* ── handle completed / failed ─────────────────────────────── */
        if (d.status === 'completed') {
            const outArr = (d.result && d.result.output) || [];

            // find the XML and HTML paths regardless of order
            const xmlPath  = outArr.find(p => p.endsWith('.xml'));
            const htmlPath = outArr.find(p => p.endsWith('.html'));

            if (!xmlPath) {
                $progress.innerHTML =
                    '<div class="alert alert-danger">No XML produced by Medite</div>';
                return;
            }

            // hide spinner
            $progress.style.display = 'none';

            // show links
            $resXml.href  = `/uploads/${xmlPath.split('/uploads/')[1]}`;
            $resXml.style.display = 'inline';

            if (htmlPath) {
                $resHtml.href = `/uploads/${htmlPath.split('/uploads/')[1]}`;
                $resHtml.style.display = 'inline';
            } else {
                $resHtml.style.display = 'none';
            }
            $results.style.display = 'block';

        } else {   // failed → roll back comparison
            await fetch(`/comparisons/${cmpId}`, {
                method:'DELETE',
                headers:{'X-CSRF-TOKEN': CSRF}
            });
            $progress.innerHTML =
                '<div class="alert alert-danger">Task failed</div>';
        }
    }, 2000);
}

</script>
@endpush
