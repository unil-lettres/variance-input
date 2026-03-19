@php /** components/main/medite.blade.php **/ @endphp
<div class="card medite-launch-card" id="medite-launch-card">
    <div class="card-header fw-semibold d-flex align-items-start gap-2">
        <span class="admin-card-heading-text">
            <span class="admin-card-title">Alignement Medite</span>
            <span class="admin-card-subtitle">Lancer une nouvelle comparaison entre deux versions</span>
        </span>
    </div>
    <div class="card-body">
        <div class="medite-launch-shell">
            <div class="medite-launch-copy">
                <div class="medite-launch-kicker">Action</div>
                <h3 class="medite-launch-title">Ouvrir le module d’alignement</h3>
                <p class="medite-launch-text">
                    Lancez Medite dans une fenêtre dédiée, puis retrouvez la comparaison produite dans la section
                    Comparaisons textuelles.
                </p>
            </div>
            <button type="button" class="btn btn-primary" id="open-medite-modal-btn" data-bs-toggle="modal" data-bs-target="#mediteModal">
                Lancer un alignement
            </button>
        </div>
    </div>
</div>

<div class="modal fade" id="mediteModal" tabindex="-1" aria-labelledby="mediteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content medite-modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5 mb-1" id="mediteModalLabel">Alignement Medite</h2>
                    <p class="text-muted small mb-0">Choisissez deux versions textuelles et lancez l’alignement dans cette fenêtre.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <form id="medite-form">
                    @csrf
                    <input type="hidden" id="work_id" name="work_id">
                    <input type="hidden" id="author_id" name="author_id">
                    <input type="hidden" id="author_name" name="author_name">
                    <input type="hidden" id="work_name" name="work_name">
                    <input type="hidden" id="comparison_id" name="comparison_id">
                    <input type="hidden" id="src_short" name="src_short">
                    <input type="hidden" id="tgt_short" name="tgt_short">
                    <input type="hidden" id="output_xml" name="output_xml">
                    <input type="hidden" id="xhtml_output_dir" name="xhtml_output_dir">

                    <div class="medite-panel-grid">
                        <section class="medite-panel">
                            <div class="medite-panel-kicker">Étape 1</div>
                            <h3 class="medite-panel-title">Versions choisies</h3>
                            <p class="medite-panel-text">Définissez le texte de référence et le texte à comparer.</p>
                            <div class="mb-3">
                                <label for="source_version" class="form-label">Version source</label>
                                <select id="source_version" name="source_version" class="form-control" required aria-describedby="sourceHelp"></select>
                                <div id="sourceHelp" class="form-text">Choisissez la version qui servira de texte de référence.</div>
                            </div>
                            <div class="mb-0">
                                <label for="target_version" class="form-label">Version cible</label>
                                <select id="target_version" name="target_version" class="form-control" required aria-describedby="targetHelp"></select>
                                <div id="targetHelp" class="form-text">Sélectionnez la version à comparer avec la source.</div>
                            </div>
                        </section>

                        <section class="medite-panel medite-panel--settings">
                            <div class="medite-panel-kicker">Étape 2</div>
                            <h3 class="medite-panel-title">Paramètres d’alignement</h3>
                            <p class="medite-panel-text">Affinez l’analyse si nécessaire. Les valeurs proposées conviennent à l’usage courant.</p>
                            <div class="mb-3">
                                <label for="lg_pivot" class="form-label">Longueur de pivot</label>
                                <input type="number" id="lg_pivot" name="lg_pivot" class="form-control" value="7" required aria-describedby="pivotHelp">
                                <div id="pivotHelp" class="form-text">Plus la valeur est grande, plus Medite exige de caractères consécutifs identiques pour aligner deux passages. Réduisez-la pour détecter des micro-variantes, augmentez-la pour ne garder que des correspondances substantielles.</div>
                            </div>
                            <div class="mb-3">
                                <label for="ratio" class="form-label">Ratio</label>
                                <input type="number" id="ratio" name="ratio" class="form-control" value="15" required aria-describedby="ratioHelp">
                                <div id="ratioHelp" class="form-text">Pourcentage du texte concerné par un déplacement avant qu’il ne soit signalé. Une valeur élevée élimine les déplacements mineurs ; une valeur plus faible permet de voir des mouvements de texte plus localisés.</div>
                            </div>
                            <div class="mb-3">
                                <label for="sep" class="form-label">Séparateurs</label>
                                <input type="text" id="sep" name="sep" class="form-control" value=",.;?!" aria-describedby="sepHelp" data-default-example=",.;?!" placeholder="Laisser vide pour utiliser les valeurs par défaut">
                                <div id="sepHelp" class="form-text">
                                    Liste personnalisée des caractères séparateurs. Laissez le champ vide (ou cochez l’option ci-dessous) pour que Medite applique ses valeurs par défaut : espace, !, retour chariot (\r), saut de ligne (\n), deux-points (:), tabulation (\t), point-virgule (;), trait d’union (-), point d’interrogation (?), guillemet double (&quot;), apostrophe droite ('), accent grave (`), apostrophe typographique (’), parenthèses ouvrantes et fermantes.
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
                        </section>
                    </div>

                    <div class="medite-launch-bar">
                        <div class="medite-launch-text">Étape 3 : lancer l’alignement puis consulter le résultat dans Comparaisons textuelles.</div>
                        <button type="submit" class="btn btn-primary">Lancer Medite</button>
                    </div>
                </form>

                <div id="progress-indicator" class="mt-4" style="display:none;"></div>
                <div id="results" class="mt-4" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
  .medite-launch-shell {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.05rem;
    border: 1px solid #ddd6ca;
    border-radius: 0.95rem;
    background: linear-gradient(180deg, #fbfaf7 0%, #f4f1eb 100%);
  }
  .medite-launch-copy {
    min-width: 0;
    flex: 1 1 22rem;
  }
  .medite-launch-kicker {
    margin-bottom: 0.3rem;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #7a7165;
  }
  .medite-launch-title {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    font-weight: 700;
    color: #433d36;
  }
  .medite-launch-text {
    margin: 0;
    font-size: 0.88rem;
    line-height: 1.45;
    color: #62594f;
  }
  .medite-modal-content {
    border-radius: 1rem;
  }
  .medite-panel-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
  }
  .medite-panel {
    padding: 1rem 1.1rem;
    border: 1px solid #ddd6ca;
    border-radius: 0.95rem;
    background: linear-gradient(180deg, #fbfaf7 0%, #f4f1eb 100%);
  }
  .medite-panel--settings {
    background: linear-gradient(180deg, #f8f6f1 0%, #efebe4 100%);
  }
  .medite-panel-kicker {
    margin-bottom: 0.35rem;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #7a7165;
  }
  .medite-panel-title {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    font-weight: 700;
    color: #433d36;
  }
  .medite-panel-text {
    margin-bottom: 0.9rem;
    font-size: 0.88rem;
    line-height: 1.45;
    color: #62594f;
  }
  .medite-modal-content .medite-launch-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 1rem;
    padding: 0.95rem 1.05rem;
    border: 1px solid #e0d9ce;
    border-radius: 0.9rem;
    background: rgba(246, 243, 237, 0.9);
  }
  @media (max-width: 991.98px) {
    .medite-panel-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
@endpush

@push('scripts')
<script>
const $srcSel  = document.getElementById('source_version');
const $tgtSel  = document.getElementById('target_version');
const $form    = document.getElementById('medite-form');
const $submitBtn = $form ? $form.querySelector('button[type="submit"]') : null;
const $progress= document.getElementById('progress-indicator');
const $results = document.getElementById('results');
const $sepInput = document.getElementById('sep');
const $useDefaultSep = document.getElementById('use_default_sep');
const $mediteModalEl = document.getElementById('mediteModal');
const $mediteLaunchCard = document.getElementById('medite-launch-card');
const CSRF     = document.querySelector('meta[name="csrf-token"]').content;
const DEFAULT_SEP_VALUE = ($sepInput && $sepInput.dataset.defaultExample) ? $sepInput.dataset.defaultExample : ',.;?!';
let cachedCustomSep = $sepInput ? $sepInput.value : DEFAULT_SEP_VALUE;
const setMediteLoading = (state) => {
  if ($mediteLaunchCard) {
    $mediteLaunchCard.classList.toggle('blade-loading', !!state);
    let overlay = $mediteLaunchCard.querySelector('.blade-loading-overlay');
    if (state) {
      if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'blade-loading-overlay';
        overlay.innerHTML = '<div class="spinner-border spinner-border-sm" role="status" aria-label="Chargement"></div><span class="loading-label">Chargement...</span>';
        $mediteLaunchCard.appendChild(overlay);
      }
    } else if (overlay) {
      overlay.remove();
    }
  }
};

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

function refreshComparisonsTable() {
    document.dispatchEvent(new CustomEvent('refreshComparisons'));
}

function normalizePaginationStatus(status) {
    return String(status || '').toLowerCase();
}

function renderMeditePhase(message, variant = 'info') {
    $progress.style.display = 'block';
    $progress.innerHTML = `<div class="alert alert-${variant} mb-0">${message}</div>`;
}

function summarizePaginationProgress(progress) {
    const roles = Object.values(progress?.roles || {});
    const statuses = roles
        .map(role => normalizePaginationStatus(role?.status))
        .filter(Boolean);

    if (!statuses.length) {
        return { state: 'idle', message: 'Alignement Medite terminé.' };
    }

    if (statuses.some(status => status === 'failed')) {
        return { state: 'failed', message: 'Alignement Medite terminé. Échec de l’injection de la pagination.' };
    }

    if (statuses.some(status => status === 'queued' || status === 'running')) {
        return { state: 'running', message: 'Alignement Medite terminé. Injection de la pagination en cours…' };
    }

    if (statuses.every(status => ['done', 'ok'].includes(status))) {
        const sourceInserted = Number(progress?.roles?.source?.inserted ?? 0) || 0;
        const targetInserted = Number(progress?.roles?.target?.inserted ?? 0) || 0;
        return {
            state: 'done',
            message: `Alignement Medite terminé. Pagination injectée (source : ${sourceInserted}, cible : ${targetInserted}).`
        };
    }

    if (statuses.every(status => ['missing', 'idle', 'skipped', 'cancelled'].includes(status))) {
        return { state: 'idle', message: 'Alignement Medite terminé. Aucune pagination à injecter.' };
    }

    return { state: 'idle', message: 'Alignement Medite terminé.' };
}

function pollComparisonPagination(comparisonId) {
    let attempts = 0;
    let idleStreak = 0;
    const intervalMs = 1500;
    const max = Math.ceil((5 * 60 * 1000) / intervalMs);

    renderMeditePhase('Alignement Medite terminé. Vérification de la pagination…', 'info');

    const timer = setInterval(async () => {
        if (++attempts > max) {
            clearInterval(timer);
            refreshComparisonsTable();
            renderMeditePhase('Alignement Medite terminé. Le suivi de l’injection de pagination continue en arrière-plan.', 'info');
            return;
        }

        try {
            const r = await fetch(withBasePath(`/api/comparisons/${comparisonId}/page-markers/progress?ts=${Date.now()}`), {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });
            if (!r.ok) return;
            const progress = await r.json();
            const summary = summarizePaginationProgress(progress);

            if (summary.state === 'running') {
                idleStreak = 0;
                renderMeditePhase(summary.message, 'info');
                return;
            }

            if (summary.state === 'idle' && idleStreak < 3) {
                idleStreak += 1;
                renderMeditePhase('Alignement Medite terminé. Vérification de la pagination…', 'info');
                return;
            }

            clearInterval(timer);
            refreshComparisonsTable();

            if (summary.state === 'done') {
                renderMeditePhase(summary.message, 'success');
            } else if (summary.state === 'failed') {
                renderMeditePhase(summary.message, 'warning');
            } else {
                renderMeditePhase(summary.message, 'success');
            }
        } catch (_error) {
            // Keep polling quietly; the table refresh after timeout still repairs UI state.
        }
    }, intervalMs);
}

function updateSubmitState() {
  if (!$submitBtn || !$srcSel || !$tgtSel) return;
  const src = $srcSel.value;
  const tgt = $tgtSel.value;
  const canRun = !!src && !!tgt && src !== tgt;
  $submitBtn.disabled = !canRun;
}

async function refreshVersions(workId, { force = false } = {}){
    if (!workId) {
        $srcSel.innerHTML = '<option value="">Choisir la version source</option>';
        $tgtSel.innerHTML = '<option value="">Choisir la version cible</option>';
        $srcSel.disabled = true;
        $tgtSel.disabled = true;
        updateSubmitState();
        setMediteLoading(false);
        return;
    }
    setMediteLoading(true);
    $srcSel.innerHTML = '<option value="">Choisir la version source</option>';
    $tgtSel.innerHTML = '<option value="">Choisir la version cible</option>';
    let vers = [];
    try {
      if (typeof window.varianceGetVersionsForWork === 'function') {
        vers = await window.varianceGetVersionsForWork(workId, { force });
      } else {
        const res = await fetch(withBasePath(`/api/versions?work_id=${workId}${force ? '&fresh=1' : ''}`));
        if (!res.ok) throw new Error(res.statusText || `HTTP ${res.status}`);
        vers = await res.json();
      }
    } catch (err) {
      updateSubmitState();
      setMediteLoading(false);
      return;
    }
    const available = Array.isArray(vers)
        ? vers.filter(v => v && v.text_available)
        : [];
    if (available.length < 2) {
        const message = 'Deux versions au minimum doivent être disponibles pour réaliser une comparaison Medite.';
        $srcSel.innerHTML = '';
        $tgtSel.innerHTML = '';
        const emptyOptSrc = new Option(message, '');
        emptyOptSrc.disabled = true;
        emptyOptSrc.selected = true;
        const emptyOptTgt = new Option(message, '');
        emptyOptTgt.disabled = true;
        emptyOptTgt.selected = true;
        $srcSel.add(emptyOptSrc);
        $tgtSel.add(emptyOptTgt);
        $srcSel.disabled = true;
        $tgtSel.disabled = true;
        updateSubmitState();
        setMediteLoading(false);
        return;
    }
    $srcSel.disabled = false;
    $tgtSel.disabled = false;
    available.forEach(v=>{
        const opt1 = new Option(v.name, v.id);
        const opt2 = new Option(v.name, v.id);
        opt1.dataset.short = v.folder;
        opt2.dataset.short = v.folder;
        $srcSel.add(opt1); $tgtSel.add(opt2);
    });
    updateSubmitState();
    setMediteLoading(false);
}

document.addEventListener('workSelected', e=>{
    const {workId, authorId, author_folder, work_folder} = e.detail;
    fillHidden('work_id', workId);
    fillHidden('author_id', authorId||'');
    fillHidden('author_name', author_folder || '');
    fillHidden('work_name', work_folder || '');
    refreshVersions(workId);
});

document.addEventListener('versionsUpdated', e=>{
    if(e.detail.workId) refreshVersions(e.detail.workId, { force: true });
});

if ($srcSel) {
  $srcSel.addEventListener('change', updateSubmitState);
}
if ($tgtSel) {
  $tgtSel.addEventListener('change', updateSubmitState);
}

if ($mediteModalEl) {
  $mediteModalEl.addEventListener('shown.bs.modal', () => {
    if ($progress) {
      $progress.style.display = 'none';
      $progress.innerHTML = '';
    }
    if ($results) {
      $results.style.display = 'none';
      $results.innerHTML = '';
    }
    $srcSel?.focus();
  });
}

$form.addEventListener('submit', async ev => {
    ev.preventDefault();

    if (!$srcSel.value || !$tgtSel.value) {
        alert('Veuillez sélectionner une version source et une version cible.');
        return;
    }

    if ($useDefaultSep && $useDefaultSep.checked && $sepInput) {
        $sepInput.value = '';
    }

    const srcShort = $srcSel.selectedOptions[0].dataset.short;
    const tgtShort = $tgtSel.selectedOptions[0].dataset.short;
    fillHidden('src_short', srcShort);
    fillHidden('tgt_short', tgtShort);

    const cmpResp = await fetch(withBasePath('/api/comparisons'), {
        method : 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept'      : 'application/json',
            'X-CSRF-TOKEN': CSRF
        },
        body: JSON.stringify({
            source_id:        $srcSel.value,
            target_id:        $tgtSel.value,
            folder:           `${srcShort}-${tgtShort}`,
            lg_pivot:         +document.getElementById('lg_pivot').value,
            ratio:            +document.getElementById('ratio').value,
            sep:              document.getElementById('sep').value,
            case_sensitive:   document.getElementById('case_sensitive').checked,
            diacri_sensitive: true
        })
    });

    if (!cmpResp.ok) {
        const err = await cmpResp.clone().json().catch(() => cmpResp.text());
        console.error('Create-comparison error', cmpResp.status, err);
        alert(`Create comparison failed (${cmpResp.status}). Check console.`);
        return;
    }

    const cmpData = await cmpResp.json();
    fillHidden('comparison_id', cmpData.id);
    notifyComparison('comparisonCreated', cmpData.id);

    const authorSlug = document.getElementById('author_name').value || 'author';
    const workSlug   = document.getElementById('work_name').value   || 'work';
    const cmpFolder  = `/app/uploads/${authorSlug}/${workSlug}/comparisons/${cmpData.id}`;

    fillHidden('output_xml',        `${cmpFolder}/${srcShort}-${tgtShort}.xml`);
    fillHidden('xhtml_output_dir',  cmpFolder);

    $progress.style.display = 'block';
    $progress.innerHTML = `
        <p>Processing…
           <span class="spinner-border spinner-border-sm" role="status"></span>
        </p>`;

    const fd  = new FormData($form);

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
        $progress.innerHTML = '<div class="alert alert-danger">Launch failed</div>';
        return;
    }

    const { task_id } = await run.json();
    const modalInstance = window.bootstrap?.Modal.getInstance($mediteModalEl);
    if (modalInstance) {
        modalInstance.hide();
    }
    pollTask(task_id, cmpData.id);
});

function pollTask(taskId, cmpId){
    let retries = 0;
    const intervalMs = 2000;
    const max = Math.ceil((45 * 60 * 1000) / intervalMs);

    const timer = setInterval(async () => {
        if (++retries > max) {
            clearInterval(timer);
            $progress.innerHTML = '<div class="alert alert-info mb-0">Processing continues in the background. Check the comparisons table later.</div>';
            return;
        }

        const r = await fetch(withBasePath(`/api/task_status/${taskId}`));
        const d = await r.json();
        if (d.status === 'pending') return;

        clearInterval(timer);

        if (d.status === 'completed') {
            const result = d.result || {};
            const outArr = result.output || [];
            const publicUrls = result.public_urls || {};
            const meta = result.meta || {};

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
                if (result?.status && result.status !== 'success') extra.push(`Status: ${result.status}`);
                if (result?.error) extra.push(result.error.trim());
                if (result?.stdout) extra.push(result.stdout.trim());
                if (result?.stderr) extra.push(result.stderr.trim());
                if (result?.traceback) extra.push(result.traceback.trim());

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
                        if (hasBreaks) block.classList.add('mb-0');
                        alert.appendChild(block);
                    });
                }

                $results.style.display = 'block';
                $results.innerHTML = '';
                $results.appendChild(alert);
                notifyComparison('comparisonDeleted', cmpId);
                return;
            }

            renderMeditePhase('Alignement Medite terminé.', 'success');
            $results.style.display = 'block';
            $results.innerHTML = `
                <div class="alert alert-light border mb-0">
                    <p class="mb-2"><strong>Comparaison produite.</strong></p>
                    <ul class="mb-2">
                        <li><a href="${withBasePath(xmlUrl)}" target="_blank" rel="noopener">XML de comparaison</a></li>
                        ${htmlUrl ? `<li><a href="${withBasePath(htmlUrl)}" target="_blank" rel="noopener">Aperçu HTML</a></li>` : ''}
                    </ul>
                    ${meta.runtime_ms ? `<p class="small text-muted mb-1">Durée : ${meta.runtime_ms} ms</p>` : ''}
                    ${meta.peak_rss_kb ? `<p class="small text-muted mb-0">Pic mémoire : ${meta.peak_rss_kb} KB</p>` : ''}
                </div>`;

            notifyComparison('comparisonCompleted', cmpId);
            pollComparisonPagination(cmpId);
            return;
        }

        const failureText = d.error || d.traceback || 'Medite a échoué.';
        $progress.innerHTML = '<div class="alert alert-danger mb-0">Échec du traitement.</div>';
        $results.style.display = 'block';
        $results.innerHTML = `<div class="alert alert-danger mb-0"><pre class="mb-0">${failureText}</pre></div>`;
        notifyComparison('comparisonDeleted', cmpId);
    }, intervalMs);
}
</script>
@endpush
