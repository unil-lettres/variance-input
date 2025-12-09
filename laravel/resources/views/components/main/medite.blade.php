@php /** components/main/medite.blade.php **/ @endphp
<div class="card">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center medite-toggle"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#mediteCollapse"
        aria-expanded="true"
        aria-controls="mediteCollapse">
        <div class="d-flex align-items-center gap-2">
            <span class="collapse-chevron" aria-hidden="true"></span>
            <span>Medite</span>
        </div>
    </div>
    <div id="mediteCollapse" class="collapse show">
    <div class="card-body">
        <p class="fst-italic text-muted small mb-3">
            Choisissez deux versions et paramétrez Medite pour générer un alignement. Une fois le traitement terminé, les résultats alimentent la liste des comparaisons ci-dessous et la partie publique.
        </p>
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

            <!-- User‑visible fields -->
            <div class="mb-3">
                <label for="source_version" class="form-label">Version source</label>
                <select id="source_version" name="source_version" class="form-control" required aria-describedby="sourceHelp"></select>
                <div id="sourceHelp" class="form-text">Choisissez la version qui servira de texte de référence.</div>
            </div>
            <div class="mb-3">
                <label for="target_version" class="form-label">Version cible</label>
                <select id="target_version" name="target_version" class="form-control" required aria-describedby="targetHelp"></select>
                <div id="targetHelp" class="form-text">Sélectionnez la version à comparer avec la source.</div>
            </div>
            <div class="mb-3">
                <label for="lg_pivot" class="form-label">Longueur de pivot</label>
                <input type="number" id="lg_pivot" name="lg_pivot" class="form-control" value="7" required aria-describedby="pivotHelp">
                <div id="pivotHelp" class="form-text">Plus la valeur est grande, plus Medite exige de caractères consécutifs identiques pour aligner deux passages. Réduisez-la pour détecter des micro-variantes, augmentez-la pour ne garder que des correspondances substantielles.</div>
            </div>
            <div class="mb-3">
                <label for="ratio" class="form-label">Ratio</label>
                <input type="number" id="ratio" name="ratio" class="form-control" value="15" required aria-describedby="ratioHelp">
                <div id="ratioHelp" class="form-text">Pourcentage du texte concerné par un déplacement avant qu’il ne soit signalé. Une valeur élevée élimine les déplacements mineurs ; une valeur plus faible permet de voir des mouvements de texte plus localisés.</div>
            </div>
            <div class="mb-3">
                <label for="sep" class="form-label">Séparateurs</label>
                <input type="text" id="sep" name="sep" class="form-control" value=",.;?!" aria-describedby="sepHelp" data-default-example=",.;?!" placeholder="Laisser vide pour utiliser les valeurs par défaut">
                <div id="sepHelp" class="form-text">
                    Liste personnalisée des caractères séparateurs. Laissez le champ vide (ou cochez l’option ci-dessous) pour que Medite applique ses valeurs par défaut : espace, !, retour chariot (\r), saut de ligne (\n), deux-points (:), tabulation (\t), point-virgule (;), trait d’union (-), point d’interrogation (?), guillemet double (&quot;), apostrophe droite ('), accent grave (`), apostrophe typographique (’), parenthèses ouvrantes et fermantes.
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="use_default_sep" checked>
                    <label class="form-check-label" for="use_default_sep">Utiliser la liste de séparateurs par défaut de Medite</label>
                </div>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="case_sensitive" name="case_sensitive" checked>
                <label class="form-check-label" for="case_sensitive">Sensibilité à la casse</label>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Lancer Medite</button>
        </form>

        <!-- Progress / Results unchanged -->
        <div id="progress-indicator" class="mt-4" style="display:none;"></div>
        <div id="results" class="mt-4" style="display:none;"></div>
    </div>
    </div>
</div>

@push('styles')
<style>
  .medite-toggle .collapse-chevron::before {
    content: "\25BC";
    display: inline-block;
    transition: transform .2s ease;
  }
  .medite-toggle[aria-expanded="false"] .collapse-chevron::before {
    transform: rotate(-90deg);
  }
  #mediteCollapse,
  #mediteCollapse *,
  #mediteCollapse.show,
  #mediteCollapse.show * {
    visibility: visible !important;
  }
</style>
@endpush

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
const $sepInput = document.getElementById('sep');
const $useDefaultSep = document.getElementById('use_default_sep');
const CSRF     = document.querySelector('meta[name="csrf-token"]').content;
const DEFAULT_SEP_VALUE = ($sepInput && $sepInput.dataset.defaultExample) ? $sepInput.dataset.defaultExample : ',.;?!';
let cachedCustomSep = $sepInput ? $sepInput.value : DEFAULT_SEP_VALUE;

function fillHidden(id,val){ document.getElementById(id).value = val; }

function updateSeparatorControl() {
  if (!$sepInput || !$useDefaultSep) return;

  if ($useDefaultSep.checked) {
    const current = $sepInput.value;
    if (current) {
      cachedCustomSep = current;
    }
    $sepInput.value = '';
    $sepInput.readOnly = true;
    $sepInput.classList.add('text-muted');
  } else {
    $sepInput.readOnly = false;
    $sepInput.classList.remove('text-muted');
    if (!$sepInput.value) {
      $sepInput.value = cachedCustomSep || DEFAULT_SEP_VALUE;
    }
  }
}

if ($useDefaultSep && $sepInput) {
  $useDefaultSep.addEventListener('change', updateSeparatorControl);
  updateSeparatorControl();
}

function notifyComparison(eventName, comparisonId) {
    document.dispatchEvent(new CustomEvent(eventName, {
        detail: {
            comparisonId,
            workId: document.getElementById('work_id').value || null,
        }
    }));
}

/*------------------------------------------------------------*
 | 1) Populate dropdowns when a work is selected               |
 *------------------------------------------------------------*/
async function refreshVersions(workId){
    $srcSel.innerHTML = '<option value="">Choisir la version source</option>';
    $tgtSel.innerHTML = '<option value="">Choisir la version cible</option>';
    const res = await fetch(withBasePath(`/api/versions?work_id=${workId}`));
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
    const {workId, authorId, author_folder, work_folder} = e.detail;
    fillHidden('work_id', workId);
    fillHidden('author_id', authorId||'');
    fillHidden('author_name', author_folder || '');
    fillHidden('work_name', work_folder || '');
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
        alert('Veuillez sélectionner une version source et une version cible.');
        return;
    }

    if ($useDefaultSep && $useDefaultSep.checked && $sepInput) {
        $sepInput.value = '';
    }

    /* short names come from <option data-short="1pda"> */
    const srcShort = $srcSel.selectedOptions[0].dataset.short;
    const tgtShort = $tgtSel.selectedOptions[0].dataset.short;
    fillHidden('src_short', srcShort);
    fillHidden('tgt_short', tgtShort);

    /*──────────────────── 1) Reserve a comparison row ────────────────────*/
    const cmpResp = await fetch(withBasePath('/api/comparisons'), {
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
            sep:              document.getElementById('sep').value,

            /* Flags as simple booleans (true / false) */
            case_sensitive:   document.getElementById('case_sensitive').checked,
            diacri_sensitive: true
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

    notifyComparison('comparisonCreated', cmpData.id);

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



    const run = await fetch(withBasePath('/api/run_medite'), {
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
    const intervalMs = 2000;
    const max = Math.ceil((45 * 60 * 1000) / intervalMs); // allow 45 minutes of polling

    const timer = setInterval(async () => {
        if (++retries > max) {
            clearInterval(timer);
            $progress.innerHTML = '<div class="alert alert-info mb-0">Processing continues in the background. Check the comparisons table later.</div>';
            return;
        }

        const r = await fetch(withBasePath(`/api/task_status/${taskId}`));
        const d = await r.json();
        if (d.status === 'pending') return;   // still running

        clearInterval(timer);

        /* ── handle completed / failed ─────────────────────────────── */
        if (d.status === 'completed') {
            const result = d.result || {};
            const outArr = result.output || [];
            const publicUrls = result.public_urls || {};
            const meta = result.meta || {};

            // find the XML and HTML paths regardless of order
            const xmlPath  = outArr.find(p => p.endsWith('.xml'));
            const htmlPath = outArr.find(p => p.endsWith('.html'));

            const xmlUrl = (() => {
                if (publicUrls.xml) return publicUrls.xml;
                if (xmlPath && xmlPath.includes('/uploads/')) {
                    return `/uploads/${xmlPath.split('/uploads/')[1]}`;
                }
                return null;
            })();

            const htmlUrl = (() => {
                if (publicUrls.html) return publicUrls.html;
                if (htmlPath && htmlPath.includes('/uploads/')) {
                    return `/uploads/${htmlPath.split('/uploads/')[1]}`;
                }
                return null;
            })();

            if (!xmlUrl) {
                console.warn('Medite finished without XML output', result);
                $progress.textContent = '';

                const alert = document.createElement('div');
                alert.className = 'alert alert-danger';

                const heading = document.createElement('p');
                heading.textContent = 'Medite did not produce an XML output.';
                alert.appendChild(heading);

                const extra = [];
                if (result?.status && result.status !== 'success') {
                    extra.push(`Status: ${result.status}`);
                }
                if (result?.error) {
                    extra.push(result.error.trim());
                }
                if (result?.stdout) {
                    extra.push(result.stdout.trim());
                }
                if (result?.stderr) {
                    extra.push(result.stderr.trim());
                }
                if (result?.traceback) {
                    extra.push(result.traceback.trim());
                }

                if (!extra.length) {
                    const fallback = document.createElement('p');
                    fallback.textContent = 'No additional diagnostics were returned.';
                    alert.appendChild(fallback);
                } else {
                    extra.forEach(text => {
                        if (!text) return;
                        const hasBreaks = /\n/.test(text);
                        const block = document.createElement(hasBreaks ? 'pre' : 'p');
                        block.textContent = text;
                        if (hasBreaks) {
                            block.classList.add('mb-0');
                        }
                        alert.appendChild(block);
                    });
                }

                $progress.appendChild(alert);
                notifyComparison('comparisonFailed', cmpId);
                return;
            }

            // show success message
            const successMsg = meta.html_fallback === false
                ? 'Medite run completed successfully. You can open the consolidated results from the comparisons table.'
                : 'Medite run completed successfully. Component XHTML outputs were generated; open them from the comparisons table.';

            $progress.innerHTML = '';
            const success = document.createElement('div');
            success.className = 'alert alert-success';
            success.textContent = successMsg;
            $progress.appendChild(success);
            $progress.style.display = 'block';

            const closeBtn = document.createElement('button');
            closeBtn.className = 'btn btn-link btn-sm p-0 mt-2';
            closeBtn.type = 'button';
            closeBtn.textContent = 'Fermer ce message';
            closeBtn.addEventListener('click', () => {
                $progress.innerHTML = '';
                $progress.style.display = 'none';
            });
            $progress.appendChild(closeBtn);

            $results.style.display = 'none';

            if (Array.isArray(meta.xhtml_components) && meta.xhtml_components.length) {
                const list = document.createElement('ul');
                list.className = 'mt-3 mb-0 small';
                const counts = meta.component_counts || {};
                const labels = {
                    'd.xhtml': { title: 'd.xhtml', desc: 'déplacements', one: 'déplacement', many: 'déplacements' },
                    'i.xhtml': { title: 'i.xhtml', desc: 'insertions', one: 'insertion', many: 'insertions' },
                    'r.xhtml': { title: 'r.xhtml', desc: 'remplacements', one: 'remplacement', many: 'remplacements' },
                    's.xhtml': { title: 's.xhtml', desc: 'suppressions', one: 'suppression', many: 'suppressions' },
                    'source.xhtml': { title: 'source.xhtml', desc: 'texte source aligné' },
                    'target.xhtml': { title: 'target.xhtml', desc: 'texte cible aligné' },
                };

                meta.xhtml_components.forEach(name => {
                    const key = name.toLowerCase();
                    const info = labels[key] || { title: name };
                    const li = document.createElement('li');
                    const count = counts[name] ?? counts[key] ?? null;

                    if (typeof count === 'number') {
                        const unit = info;
                        const noun = count === 1 ? (unit.one || 'entrée') : (unit.many || 'entrées');
                        li.innerHTML = `<strong>${info.title}</strong> — ${count} ${noun}`;
                    } else if (info.desc) {
                        li.innerHTML = `<strong>${info.title}</strong> — ${info.desc}`;
                    } else {
                        li.textContent = info.title;
                    }

                    list.appendChild(li);
                });

                const hint = document.createElement('div');
                hint.className = 'alert alert-info mt-3';
                hint.innerHTML = '<strong>Composants générés :</strong>';
                hint.appendChild(list);
                $progress.appendChild(hint);
            }

            notifyComparison('comparisonReady', cmpId);

        } else {   // failed → roll back comparison
            await fetch(withBasePath(`/comparisons/${cmpId}`), {
                method:'DELETE',
                headers:{'X-CSRF-TOKEN': CSRF}
            });

            const msg = d.error || 'Task failed';
            $progress.textContent = '';
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.textContent = msg;
            $progress.appendChild(alert);
            notifyComparison('comparisonFailed', cmpId);
        }
    }, intervalMs);
}

</script>
@endpush
