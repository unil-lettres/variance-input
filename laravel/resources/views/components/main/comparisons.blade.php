<div class="card mb-3">
    <div class="card-header text-uppercase fw-semibold">Comparaisons générées</div>

    <div class="card-body">
        <p class="fst-italic text-muted small mb-3">
            Retrouvez ici toutes les comparaisons produites avec Medite pour l'œuvre sélectionnée. Vous pouvez suivre leur état, accéder aux résultats ou relancer la pagination si nécessaire.
        </p>
        <!-- Spinner while loading -->
        <div id="comparisons-loading" class="mb-3" style="display:none;">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            Chargement des comparaisons...
        </div>

        <!-- Table -->
        <table class="table table-sm table-bordered align-middle comparisons-table" id="comparisons-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Source</th>
                    <th>Cible</th>
          <th>Folder</th>
          <th>Ratio</th>
          <th>Pivot</th>
          <th>Sens. Casse</th>
          <th>Composants</th>
          <th>Pagination</th>
          <th>Publié</th>
          <th>Publier</th>
          <th>Date</th>
          <th>Résultats</th>
        </tr>
      </thead>
            <tbody></tbody>
        </table>

        <!-- Empty state -->
        <div id="no-comparisons" style="display:none;" class="text-muted">
            Aucune comparaison trouvée pour cette œuvre.
        </div>
    </div>
</div>

@push('scripts')
<style>
  .comparisons-table th {
    font-weight: normal;
    font-size: 1rem;
    color: #333;
  }
  .comparisons-table td { vertical-align: middle; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tbody          = document.querySelector('#comparisons-table tbody');
  const loading        = document.getElementById('comparisons-loading');
  const noComparisons  = document.getElementById('no-comparisons');

  const JSON_HEADERS = { 'Accept': 'application/json' };
  let currentWorkId = null;
  let currentAuthorFolder = '';
  let currentWorkFolder = '';
  const runningComparisons = window.__runningComparisons || new Set();
  window.__runningComparisons = runningComparisons;
  const paginationLocks = new Set();
  const paginationObservers = new Map();
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  const formatTimestamp = ts => ts ? new Date(ts * 1000).toLocaleString('fr-FR', { hour12: false }) : null;
  const formatNumber = value => {
    if (typeof value === 'number' && Number.isFinite(value)) {
      return value.toLocaleString('fr-FR');
    }
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed.toLocaleString('fr-FR') : '0';
  };
  const formatBytes = size => {
    if (!Number.isFinite(size) || size <= 0) return '0 o';
    const units = ['o','Ko','Mo','Go'];
    let idx = 0;
    let val = size;
    while (val >= 1024 && idx < units.length - 1) {
      val /= 1024;
      idx++;
    }
    return `${val.toFixed(idx === 0 ? 0 : 1)} ${units[idx]}`;
  };

  function createBadge({ text, className = '', href = null, title = '' }) {
    const tag = href ? 'a' : 'span';
    const el = document.createElement(tag);
    const classes = ['badge'];
    if (className && className.trim()) {
      classes.push(className.trim());
    }
    el.className = classes.join(' ');
    el.textContent = text;
    if (title) {
      el.title = title;
    }
    if (href) {
      el.href = href;
      el.target = '_blank';
      el.rel = 'noopener';
    }
    return el;
  }

  function buildComparisonStatusText(progress, role) {
    const fmt = (n) => (typeof n === 'number' && Number.isFinite(n)) ? n : 0;
    if (!progress) {
      return 'Pagination : aucune exécution enregistrée';
    }
    const updated = progress.updated_at ? formatTimestamp(progress.updated_at) : null;
    const suffix = updated ? ` (maj ${updated})` : '';
    const globalStatus = progress.status || 'idle';
    const roleData = progress.roles?.[role] || {};
    const status = roleData.status || globalStatus;

    if (status === 'queued') {
      return `🕒 En file d'attente…${suffix}`;
    }
    if (status === 'skipped') {
      const reason = roleData.reason ? ` — ${roleData.reason}` : '';
      return `⚪ Pagination ignorée${reason}${suffix}`;
    }
    if (status === 'running') {
      const total = roleData.total ?? 0;
      const inserted = fmt(roleData.inserted);
      const missed = fmt(roleData.missed);
      const processed = inserted;
      return `⏳ Progression : ${processed}/${total || '—'} — insérés : ${inserted}, manqués : ${missed}${suffix}`;
    }
    if (status === 'failed') {
      const error = roleData.error || progress.error || 'opération interrompue';
      return `❌ Échec : ${error}${suffix}`;
    }
    if (status === 'done' || status === 'ok') {
      const total = roleData.total ?? 0;
      const inserted = fmt(roleData.inserted);
      const missed = fmt(roleData.missed);
      if (total > 0 && inserted === 0) {
        return `⚠️ Terminé sans pagination (0/${total}). Relancez Medite puis réinjectez.${suffix}`;
      }
      return `✅ Terminé — insérés : ${inserted}/${total || '—'}, manqués : ${missed}${suffix}`;
    }
    if (status === 'restored') {
      return `ℹ️ Originaux restaurés${suffix}`;
    }
    if (status === 'idle') {
      return `Pagination : aucune exécution enregistrée${suffix}`;
    }
    return `ℹ️ Statut : ${status}${suffix}`;
  }

  function registerPaginationStatus(comparisonId, element, initialProgress, label = '', role = '') {
    if (!comparisonId || !element) return;
    const message = buildComparisonStatusText(initialProgress || null, role || '');
    element.dataset.paginationLabel = label;
    element.dataset.paginationRole = role || '';
    element.dataset.comparisonId = String(comparisonId);
    element.textContent = label ? `${label} — ${message}` : message;
    let entry = paginationObservers.get(comparisonId);
    if (!entry) {
      entry = { elements: new Set(), timer: null, completed: false };
      paginationObservers.set(comparisonId, entry);
    }
    entry.elements.add(element);
    const initStatus = initialProgress?.status ?? null;
    if (initStatus && !['done', 'failed', 'idle', 'restored'].includes(initStatus)) {
      entry.completed = false;
      startComparisonPolling(comparisonId);
    }
  }

  function startComparisonPolling(comparisonId) {
    const entry = paginationObservers.get(comparisonId);
    if (!entry || entry.timer) return;

    const tick = async () => {
      const currentEntry = paginationObservers.get(comparisonId);
      if (!currentEntry) return;

      // Remove detached elements
      for (const el of Array.from(currentEntry.elements)) {
        if (!document.body.contains(el)) {
          currentEntry.elements.delete(el);
        }
      }
      if (currentEntry.elements.size === 0) {
        if (currentEntry.timer) {
          clearInterval(currentEntry.timer);
        }
        paginationObservers.delete(comparisonId);
        return;
      }

      try {
        const res = await fetch(withBasePath(`/api/comparisons/${comparisonId}/page-markers/progress?ts=${Date.now()}`), {
          headers: { 'Accept': 'application/json' },
          cache: 'no-store'
        });
        if (!res.ok) return;
        const progress = await res.json();
        currentEntry.elements.forEach(el => {
          const label = el.dataset.paginationLabel || '';
          const role = el.dataset.paginationRole || '';
          const message = buildComparisonStatusText(progress, role);
          el.textContent = label ? `${label} — ${message}` : message;
        });

        if (!progress || ['done', 'failed', 'idle'].includes(progress.status)) {
          if (!currentEntry.completed) {
            currentEntry.completed = true;
            if (typeof loadComparisons === 'function' && isValidWorkId(currentWorkId)) {
              loadComparisons(currentWorkId);
            }
          }
          if (currentEntry.timer) {
            clearInterval(currentEntry.timer);
            currentEntry.timer = null;
          }
          paginationObservers.delete(comparisonId);
        }
      } catch (err) {
        console.error('Erreur de suivi de pagination', err);
        currentEntry.elements.forEach(el => {
          const label = el.dataset.paginationLabel || '';
          const text = '⚠️ Erreur de suivi de pagination';
          el.textContent = label ? `${label} — ${text}` : text;
        });
      }
    };

    entry.timer = setInterval(tick, 1500);
    tick();
  }

  function renderComparisonPagination(comp) {
    const container = document.createElement('div');
    container.className = 'small text-start';

    const roles = [
      { key: 'source', label: 'Source', versionId: comp.source_id },
      { key: 'target', label: 'Cible',  versionId: comp.target_id }
    ];

    const roleSummaries = [];

    roles.forEach(({ key, label, versionId }, index) => {
      const data = (comp.pagination && comp.pagination[key]) || {};
      const block = document.createElement('div');
      if (index > 0) {
        block.className = 'mt-2';
      }

      const badges = document.createElement('div');
      const labelEl = document.createElement('strong');
      labelEl.textContent = label;
      badges.appendChild(labelEl);
      badges.appendChild(document.createTextNode(' · '));
      badges.appendChild(createBadge({
        text: `${formatNumber(data.markers ?? 0)} tags`,
        className: 'bg-secondary'
      }));

      const lignesBadge = (data.lignes_available ?? false)
        ? createBadge({
            text: '_lignes',
            className: 'bg-success ms-1',
            title: 'Fichier _lignes disponible'
          })
        : createBadge({
            text: '_lignes manquant',
            className: 'bg-warning text-dark ms-1',
            title: 'Associez un fichier _lignes à cette version'
          });
      badges.appendChild(document.createTextNode(' '));
      badges.appendChild(lignesBadge);

      const manifestInfo = (comp.manifests && comp.manifests[key]) || {};
      badges.appendChild(document.createTextNode(' '));
      if (manifestInfo.exists) {
        const rawCount = Number(manifestInfo.count ?? 0);
        const countValue = Number.isFinite(rawCount) ? rawCount : 0;
        const displayCount = formatNumber(countValue);
        badges.appendChild(createBadge({
          text: `JSON ${displayCount} x2`,
          className: 'bg-info text-dark ms-1',
          href: manifestInfo.api_url || manifestInfo.url || null,
          title: manifestInfo.file
            ? `${manifestInfo.file} — ${displayCount} fac-similé${countValue === 1 ? '' : 's'} + miniature${countValue === 1 ? '' : 's'}`
            : 'Manifeste JSON — fac-similés et miniatures'
        }));
      } else {
        badges.appendChild(createBadge({
          text: 'manifeste absent',
          className: 'bg-light text-muted ms-1',
          title: 'Aucun manifeste JSON détecté'
        }));
      }
      block.appendChild(badges);

      if (data.lignes && (data.lignes.updated_at || data.lignes.size)) {
        const hint = document.createElement('div');
        hint.className = 'text-muted small';
        const updated = data.lignes.updated_at ? formatTimestamp(data.lignes.updated_at) : '—';
        const size = data.lignes.size ? formatBytes(data.lignes.size) : '0 o';
        hint.textContent = `Fichier : ${updated} · ${size}`;
        block.appendChild(hint);
      }

      const statusEl = document.createElement('div');
      statusEl.className = 'text-muted small mt-1';
      block.appendChild(statusEl);
      container.appendChild(block);

      if (versionId) {
        const progressSnapshot = comp.comparison_progress || null;
        statusEl.dataset.comparisonId = String(comp.id);
        statusEl.dataset.paginationRole = key;
        registerPaginationStatus(comp.id, statusEl, progressSnapshot, label, key);
        roleSummaries.push({ role: key, statusEl, label });
      } else {
        statusEl.textContent = `${label} — version indisponible`;
      }
    });

    const clearId = `cmp-clear-${comp.id}`;
    const replaceId = `cmp-replace-${comp.id}`;
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
    container.appendChild(options);

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

    const feedback = document.createElement('div');
    feedback.className = 'small text-muted mt-1';
    container.appendChild(feedback);

    const runBtn = document.createElement('button');
    runBtn.type = 'button';
    runBtn.className = 'btn btn-sm btn-outline-secondary mt-2';
    runBtn.textContent = 'Injecter la pagination';

    const lignesReady = (comp.pagination?.source?.lignes_available ?? false) &&
                        (comp.pagination?.target?.lignes_available ?? false);
    if (!lignesReady) {
      runBtn.disabled = true;
      feedback.textContent = 'Associez un fichier _lignes aux deux versions.';
    }

    runBtn.addEventListener('click', () => {
      const clearExisting = clearToggle ? clearToggle.checked : true;
      const replaceExisting = replaceToggle ? replaceToggle.checked : true;
      triggerComparisonPagination(comp, {
        clearExisting,
        replaceExisting,
        button: runBtn,
        feedback,
        roles: roleSummaries
      });
    });

    container.appendChild(runBtn);

    const restoreBtn = document.createElement('button');
    restoreBtn.type = 'button';
    restoreBtn.className = 'btn btn-sm btn-outline-danger mt-2 ms-2';
    restoreBtn.textContent = 'Restaurer les originaux';
    restoreBtn.addEventListener('click', () => {
      if (!confirm('Les fichiers originaux de sortie Medite vont être restaurés; tous les marqueurs de pagination seront supprimés.')) {
        return;
      }
      restoreComparisonPagination(comp, {
        button: restoreBtn,
        feedback,
      });
    });
    container.appendChild(restoreBtn);

    return container;
  }

  async function triggerComparisonPagination(comp, { clearExisting, replaceExisting, button, feedback, roles }) {
    const lockKey = `inject-${comp.id}`;
    if (paginationLocks.has(lockKey)) return;
    paginationLocks.add(lockKey);

    const originalLabel = button ? button.textContent : '';
    if (button) {
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }
    if (feedback) {
      feedback.textContent = 'Préparation de l’injection de pagination…';
    }

    try {
      const res = await fetch(withBasePath(`/api/comparisons/${comp.id}/page-markers`), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(CSRF_TOKEN ? { 'X-CSRF-TOKEN': CSRF_TOKEN } : {})
        },
        body: JSON.stringify({
          clear_existing: clearExisting ? 1 : 0,
          replace_existing: replaceExisting ? 1 : 0
        })
      });

      const text = await res.text();
      let payload = {};
      try { payload = JSON.parse(text); } catch { payload = { raw: text }; }

      if (!res.ok || payload.status !== 'queued') {
        const message = payload.message || payload.error || payload.raw || `HTTP ${res.status}`;
        throw new Error(message);
      }

      if (feedback) {
        feedback.textContent = payload.message || 'Injection en file d\'attente…';
      }

      let firstRoleHandled = false;
      roles.forEach(({ role, statusEl, label }) => {
        if (!statusEl) return;
        statusEl.dataset.paginationLabel = label || '';
        statusEl.dataset.paginationRole = role || '';
        const text = firstRoleHandled
          ? 'En file d\'attente…'
          : 'Pagination : initialisation en cours…';
        statusEl.textContent = label ? `${label} — ${text}` : text;
        firstRoleHandled = true;
      });
      const observerEntry = paginationObservers.get(comp.id);
      if (observerEntry) {
        observerEntry.completed = false;
      }
      startComparisonPolling(comp.id);
    } catch (err) {
      console.error('Erreur pagination comparaison', err);
      if (feedback) {
        feedback.textContent = 'Échec du lancement de la pagination.';
      }
      alert("Impossible de lancer la pagination : " + (err?.message || 'erreur inconnue'));
    } finally {
      paginationLocks.delete(lockKey);
      if (button) {
        button.disabled = false;
        button.textContent = originalLabel || 'Injecter la pagination';
      }
      if (feedback) {
        setTimeout(() => { feedback.textContent = ''; }, 5000);
      }
    }
  }

  async function restoreComparisonPagination(comp, { button, feedback }) {
    const lockKey = `restore-${comp.id}`;
    if (paginationLocks.has(lockKey)) return;
    paginationLocks.add(lockKey);

    const originalLabel = button ? button.textContent : '';
    if (button) {
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }
    if (feedback) {
      feedback.textContent = 'Restauration des originaux…';
    }

    try {
      const res = await fetch(withBasePath(`/api/comparisons/${comp.id}/page-markers/restore`), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          ...(CSRF_TOKEN ? { 'X-CSRF-TOKEN': CSRF_TOKEN } : {})
        }
      });

      const payload = await res.json().catch(() => ({}));
      if (!res.ok || payload.status !== 'restored') {
        const message = payload.message || `HTTP ${res.status}`;
        throw new Error(message);
      }

      if (feedback) {
        feedback.textContent = payload.message || 'Originaux restaurés.';
      }

      if (currentWorkId) {
        loadComparisons(currentWorkId);
      }
    } catch (err) {
      console.error('Erreur restauration pagination', err);
      if (feedback) {
        feedback.textContent = 'Échec de la restauration.';
      }
      alert("Impossible de restaurer les originaux : " + (err?.message || 'erreur inconnue'));
    } finally {
      paginationLocks.delete(lockKey);
      if (button) {
        button.disabled = false;
        button.textContent = originalLabel || 'Restaurer les originaux';
      }
      if (feedback) {
        setTimeout(() => { feedback.textContent = ''; }, 5000);
      }
    }
  }

  function isValidWorkId(id) {
    const s = String(id ?? '').trim();
    return /^\d+$/.test(s) && Number(s) > 0;
  }

  function resetUI() {
    loading.style.display = 'none';
    noComparisons.style.display = 'none';
    tbody.innerHTML = '';
  }

  // Track current author/work folders from the global selector
  document.addEventListener('workSelected', e => {
    currentAuthorFolder = e.detail?.author_folder || '';
    currentWorkFolder   = e.detail?.work_folder || '';
  });

  async function loadComparisons(workId) {
    if (!isValidWorkId(workId)) { resetUI(); return; }
    currentWorkId = workId;

    loading.style.display = 'block';
    tbody.innerHTML = '';
    noComparisons.style.display = 'none';

    try {
      const res = await fetch(withBasePath(`/comparisons/by-work?work_id=${workId}`), { headers: JSON_HEADERS });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const comparisons = await res.json();

      if (!Array.isArray(comparisons) || comparisons.length === 0) {
        noComparisons.style.display = 'block';
        return;
      }

      const boolVal = value => value === true || value === 1 || value === '1';

      comparisons.forEach(comp => {
        const tr = document.createElement('tr');
        tr.dataset.id = comp.id;
        tr.dataset.published = comp.published ? '1' : '0';
        tr.dataset.publishDest = comp.publish_dest || '';

        const missing = Array.isArray(comp.publish_missing) ? comp.publish_missing : [];
        const ready = comp.components_ready && missing.length === 0;
        const published = comp.published;
        const caseSensitive = boolVal(comp.case_sensitive);
        const publishedTitle = published
          ? 'Fichiers publiés disponibles'
          : 'Comparaison non publiée';
        const isRunning = runningComparisons.has(Number(comp.id));
        let statusHtml;
        if (isRunning) {
          statusHtml = '<span class="text-info" title="Medite en cours…">⏳ Medite en cours…</span>';
        } else if (ready) {
          statusHtml = '<span class="text-success" title="Tous les composants Medite sont présents">✔</span>';
        } else {
          const statusTitle = `Composants manquants :\n- ${missing.join('\n- ')}`;
          const safeTitle = statusTitle.replace(/"/g, '&quot;');
          statusHtml = `<span class="text-warning" title="${safeTitle}">⚠</span>`;
        }

        const xmlUrl  = withBasePath(`/storage/uploads/comparisons/${comp.id}.xml`);
        const legacyUrl = (function() {
          if (!currentAuthorFolder || !currentWorkFolder || !comp.folder) return null;
          const origin = window.location.origin;
          return `${origin}/${currentAuthorFolder}/${currentWorkFolder}/comparaison/${comp.folder}`;
        })();

        tr.innerHTML = `
          <td>${comp.id}</td>
          <td>${comp.source_version?.name ?? comp.source_id}</td>
          <td>${comp.target_version?.name ?? comp.target_id}</td>
          <td>${comp.folder ?? ''}</td>
          <td>${comp.ratio ?? ''}</td>
          <td>${comp.lg_pivot ?? ''}</td>
          <td>${caseSensitive ? 'yes' : 'no'}</td>
          <td class="text-center components-status">${statusHtml}</td>
          <td class="pagination-cell align-top"></td>
          <td class="text-center published-status">${published ? `<span class="text-success" title="${publishedTitle}">✔</span>` : `<span class="text-muted" title="${publishedTitle}">—</span>`}</td>
          <td class="text-center">
            <input type="checkbox" class="form-check-input publish-toggle"
                   data-id="${comp.id}"
                   data-missing='${JSON.stringify(comp.publish_missing ?? [])}'
                   data-source='${comp.publish_source ?? ''}'
                   ${comp.published ? 'checked' : ''}
                   ${comp.publish_source ? '' : 'disabled'}>
          </td>
          <td>${comp.created_at ? new Date(comp.created_at).toLocaleString() : ''}</td>
          <td>
            <a href="${xmlUrl}"  class="btn btn-sm btn-outline-primary" target="_blank">XML</a>
            ${(legacyUrl && published) ? `<a href="${legacyUrl}" class="btn btn-sm btn-outline-success ms-1" target="_blank" title="Voir sur le site public">Public</a>` : ''}
            <button class="btn btn-sm btn-outline-danger ms-1 delete-comparison-btn" data-id="${comp.id}">🗑️</button>
          </td>
        `;
        tbody.appendChild(tr);
        const paginationCell = tr.querySelector('.pagination-cell');
        if (paginationCell) {
          paginationCell.appendChild(renderComparisonPagination(comp));
        }
      });
    } catch (err) {
      console.error('Erreur lors du chargement des comparaisons:', err);
      // Leave UI cleared; don't try to render an error page as JSON
    } finally {
      loading.style.display = 'none';
    }
  }

  // React to global events, but let loadComparisons() guard invalid IDs
  document.addEventListener('workSelected', e => {
    runningComparisons.clear();
    loadComparisons(e.detail?.workId);
  });

  document.addEventListener('comparisonCreated', e => {
    if (e.detail?.comparisonId) {
      runningComparisons.add(Number(e.detail.comparisonId));
    }
    loadComparisons(e.detail?.workId);
  });

  document.addEventListener('comparisonReady', e => {
    if (e.detail?.comparisonId) {
      runningComparisons.delete(Number(e.detail.comparisonId));
    }
    loadComparisons(e.detail?.workId);
  });

  document.addEventListener('comparisonFailed', e => {
    if (e.detail?.comparisonId) {
      runningComparisons.delete(Number(e.detail.comparisonId));
    }
    loadComparisons(e.detail?.workId);
  });

  document.addEventListener('versionsUpdated',   e => loadComparisons(e.detail?.workId));

  document.addEventListener('change', async event => {
    const toggle = event.target.closest('.publish-toggle');
    if (!toggle) return;

    const comparisonId = toggle.dataset.id;
    if (!comparisonId) return;

    const shouldPublish = toggle.checked;
    toggle.disabled = true;

    const sourceDir = toggle.dataset.source || '';
    let knownMissing = [];
    try {
      knownMissing = JSON.parse(toggle.dataset.missing || '[]');
    } catch {
      knownMissing = [];
    }

    if (shouldPublish && !sourceDir) {
      alert('Les fichiers Medite ne sont pas encore disponibles pour cette comparaison. Exécutez Medite avant de publier.');
      toggle.checked = false;
      toggle.disabled = false;
      return;
    }

    if (shouldPublish && Array.isArray(knownMissing) && knownMissing.length) {
      const proceed = confirm(
        'Certains composants Medite semblent manquants :\n- ' +
        knownMissing.join('\n- ') +
        '\n\nVoulez-vous publier malgré tout ?'
      );
      if (!proceed) {
        toggle.checked = false;
        toggle.disabled = false;
        return;
      }
    }

    try {
      if (shouldPublish) {
        const res = await fetch(withBasePath('/api/publish_xhtml'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({ comparison_id: comparisonId })
        });

        const text = await res.text();
        let data = {};
        try { data = JSON.parse(text); } catch { data = { raw: text }; }
        if (!res.ok || data.error || data.status !== 'ok') {
          const reason = data.error || data.message || data.raw || `Statut HTTP ${res.status}`;
          throw new Error(`Publication échouée : ${reason}`);
        }

        if (Array.isArray(data.missing_files) && data.missing_files.length) {
          alert(
            'Publication partielle : fichiers manquants\n- ' +
            data.missing_files.join('\n- ') +
            '\n\nVérifiez les sorties Medite avant de republier.'
          );
        }

      } else {
        const res = await fetch(withBasePath(`/api/publish_xhtml/${comparisonId}`), {
          method: 'DELETE',
          headers: { 'Accept': 'application/json' }
        });

        const text = await res.text();
        let data = {};
        try { data = JSON.parse(text); } catch { data = { raw: text }; }
        if (!res.ok || data.error || data.status !== 'ok') {
          const reason = data.error || data.message || data.raw || `Statut HTTP ${res.status}`;
          throw new Error(`Impossible de dépublier : ${reason}`);
        }
      }

      if (currentWorkId) {
        await loadComparisons(currentWorkId);
      }

    } catch (err) {
      console.error(err);
      alert((err && err.message) ? err.message : 'Erreur lors de la mise à jour de la publication');
      toggle.checked = !shouldPublish;
    } finally {
      toggle.disabled = false;
    }
  });

  // Delete comparison (event delegation)
  document.addEventListener('click', async event => {
    const btn = event.target.closest('.delete-comparison-btn');
    if (!btn) return;

    const comparisonId = btn.dataset.id;
    if (!confirm(`Voulez-vous vraiment supprimer la comparaison #${comparisonId} ?`)) return;

    try {
      const response = await fetch(withBasePath(`/comparisons/${comparisonId}`), {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          ...JSON_HEADERS
        }
      });

      const text = await response.text();
      let data = {};
      try { data = JSON.parse(text); } catch { data = { raw: text }; }

      if (!response.ok || data.error) {
        const errMsg = data.error || data.raw || `HTTP ${response.status}`;
        throw new Error(errMsg);
      }

      // Remove row; if table is empty, show "no comparisons"
      btn.closest('tr')?.remove();
      if (!tbody.querySelector('tr')) noComparisons.style.display = 'block';

    } catch (err) {
      console.error('Erreur lors de la suppression de la comparaison:', err);
      alert(`Suppression impossible : ${err.message || 'erreur inconnue'}.`);
    }
  });
});
</script>
@endpush
