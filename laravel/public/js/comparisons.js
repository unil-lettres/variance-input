function initComparisonsTable() {
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
  const pendingRoleStatuses = new Map();
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  let latestComparisonsRequest = 0;
  let activeComparisonsRequest = 0;

  const TERMINAL_STATUSES = new Set(['done', 'ok', 'failed', 'restored', 'idle', 'skipped', 'cancelled', 'missing']);
  const STATUS_LABELS = {
    queued: 'En attente',
    running: 'En cours',
    done: 'Terminé',
    ok: 'Terminé',
    failed: 'En échec',
    restored: 'Restauré',
    idle: 'Inactif',
    cancelled: 'Annulé',
    skipped: 'Ignoré',
    missing: 'Indisponible',
  };
  const normalizeStatus = status => String(status ?? '').toLowerCase();
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

  function pendingStatusKey(comparisonId, role) {
    if (!role) return null;
    return `${comparisonId}:${role}`;
  }

  function setPendingStatus(comparisonId, role, status, meta = {}) {
    const key = pendingStatusKey(comparisonId, role);
    if (!key) return;
    pendingRoleStatuses.set(key, {
      status,
      updated_at: meta.updated_at ?? Math.floor(Date.now() / 1000),
      reason: meta.reason,
      total: meta.total,
      inserted: meta.inserted,
      missed: meta.missed,
    });
  }

  function clearPendingStatus(comparisonId, role) {
    const key = pendingStatusKey(comparisonId, role);
    if (!key) return;
    pendingRoleStatuses.delete(key);
  }

  function computeStatusInfo(progress, role, comparisonId) {
    const fmt = (n) => (typeof n === 'number' && Number.isFinite(n)) ? n : 0;
    const comparisonKey = progress?.comparison_id ?? comparisonId ?? 0;
    const key = pendingStatusKey(comparisonKey, role);
    const roles = progress?.roles || {};
    const roleEntry = role && Object.prototype.hasOwnProperty.call(roles, role)
      ? roles[role] ?? null
      : null;
    const progressUpdatedAt = Number(progress?.updated_at ?? 0);
    const pending = key ? pendingRoleStatuses.get(key) : null;
    const pendingUpdatedAt = Number(pending?.updated_at ?? 0);
    const roleUpdatedAt = Number(roleEntry?.updated_at ?? progressUpdatedAt);
    let usePending = false;

    if (pending) {
      if (!roleEntry || roleEntry.status === undefined) {
        usePending = true;
      } else if (pendingUpdatedAt && roleUpdatedAt < pendingUpdatedAt && progressUpdatedAt < pendingUpdatedAt) {
        usePending = true;
      }

      if (!usePending) {
        pendingRoleStatuses.delete(key);
      }
    }

    let rawStatus = (!usePending && roleEntry && roleEntry.status !== undefined)
      ? roleEntry.status
      : progress?.status ?? 'idle';
    let reason = roleEntry?.reason || progress?.reason || '';
    let total = roleEntry?.total ?? roleEntry?.expected ?? progress?.total ?? 0;
    let inserted = roleEntry?.inserted ?? 0;
    let missed = roleEntry?.missed ?? 0;

    if (usePending && pending) {
      rawStatus = pending.status ?? rawStatus;
      reason = pending.reason ?? reason;
      if (pending.total !== undefined) total = pending.total;
      if (pending.inserted !== undefined) inserted = pending.inserted;
      if (pending.missed !== undefined) missed = pending.missed;
      progress = {
        ...(progress || {}),
        updated_at: pending.updated_at ?? progress?.updated_at,
      };
    } else if (!roleEntry && role && !usePending) {
      rawStatus = 'idle';
    }

    let normalized = normalizeStatus(rawStatus);
    if (normalized === 'ok') normalized = 'done';
    if (!normalized) normalized = 'idle';

    const label = STATUS_LABELS[normalized] || STATUS_LABELS[rawStatus] || (rawStatus || 'Inconnu');
    const updated = progress?.updated_at ? formatTimestamp(progress.updated_at) : null;
    const suffix = updated ? ` (maj ${updated})` : '';

    let body;
    if (normalized === 'queued') {
      body = `🕒 En file d'attente…${suffix}`;
    } else if (normalized === 'skipped') {
      const extra = reason ? ` — ${reason}` : '';
      body = `⚪ Pagination ignorée${extra}${suffix}`;
    } else if (normalized === 'running') {
      const rawTotal = Number(total);
      const displayTotal = Number.isFinite(rawTotal) && rawTotal > 0 ? formatNumber(rawTotal) : 'quelques';
      body = `⚙️ Injection de ${displayTotal} marqueurs de pagination… Patientez svp${suffix}`;
    } else if (normalized === 'failed') {
      const error = roleEntry?.error || progress?.error || reason || 'opération interrompue';
      body = `❌ Échec : ${error}${suffix}`;
    } else if (normalized === 'done') {
      const totalNumber = Number(total) || 0;
      const insertedNumber = Number(inserted) || 0;
      const missedNumber = Number(missed) || 0;
      if (totalNumber > 0 && insertedNumber === 0) {
        body = `⚠️ Terminé sans pagination (0/${totalNumber}). Relancez Medite puis réinjectez.${suffix}`;
      } else {
        const safeTotal = totalNumber ? formatNumber(totalNumber) : '—';
        body = `✅ Terminé — insérés : ${formatNumber(insertedNumber)}/${safeTotal}, manqués : ${formatNumber(missedNumber)}${suffix}`;
      }
    } else if (normalized === 'restored') {
      body = `ℹ️ Originaux restaurés${suffix}`;
    } else if (normalized === 'cancelled') {
      const extra = reason ? ` — ${reason}` : '';
      body = `🚫 Annulé${extra}${suffix}`;
    } else if (normalized === 'missing') {
      body = `⚠️ Données de pagination indisponibles${suffix}`;
    } else if (normalized === 'idle') {
      body = `Pagination : aucune exécution enregistrée${suffix}`;
    } else {
      body = `ℹ️ Statut : ${rawStatus}${suffix}`;
    }

    const text = `Statut : ${label} — ${body}`;
    const allowCancel = ['queued', 'running'].includes(normalized);
    return { text, status: normalized, allowCancel };
  }

  function buildComparisonStatusText(progress, role, comparisonId) {
    return computeStatusInfo(progress, role, comparisonId).text;
  }

  function registerPaginationStatus(comparisonId, element, initialProgress, label = '', role = '') {
    if (!comparisonId || !element) return;
    const statusInfo = computeStatusInfo(initialProgress || null, role || '', comparisonId);
    element.dataset.paginationLabel = label;
    element.dataset.paginationRole = role || '';
    element.dataset.comparisonId = String(comparisonId);
    element.textContent = label ? `${label} — ${statusInfo.text}` : statusInfo.text;
    const cancelBtn = element.__cancelBtn;
    if (cancelBtn) {
      cancelBtn.disabled = !statusInfo.allowCancel;
    }
    let entry = paginationObservers.get(comparisonId);
    if (!entry) {
      entry = { elements: new Set(), timer: null, completed: false, roleTerminated: new Map() };
      paginationObservers.set(comparisonId, entry);
    }
    entry.elements.add(element);
    const roleStatus = role ? statusInfo.status : normalizeStatus(initialProgress?.status ?? null);
    const initStatus = normalizeStatus(initialProgress?.status ?? null);
    if (entry.roleTerminated instanceof Map) {
      const roleKey = role || '__global__';
      entry.roleTerminated.set(roleKey, TERMINAL_STATUSES.has(roleStatus));
    }
    const shouldPoll = [roleStatus, initStatus].some(status => status && !TERMINAL_STATUSES.has(status));
    if (shouldPoll) {
      entry.completed = false;
      for (const other of paginationObservers.values()) {
        if (other === entry) continue;
        if (other.elements.has(element)) {
          other.elements.delete(element);
        }
      }
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
        const globalStatus = normalizeStatus(progress?.status ?? null);
        const rolesData = progress?.roles || {};
        const roleTerminatedMap = currentEntry.roleTerminated instanceof Map
          ? currentEntry.roleTerminated
          : new Map();
        currentEntry.roleTerminated = roleTerminatedMap;
        let terminalTransition = false;

        currentEntry.elements.forEach(el => {
          const label = el.dataset.paginationLabel || '';
          const role = el.dataset.paginationRole || '';
          const statusInfo = computeStatusInfo(progress, role, comparisonId);
          el.textContent = label ? `${label} — ${statusInfo.text}` : statusInfo.text;
          const cancelBtn = el.__cancelBtn;
          if (cancelBtn) {
            cancelBtn.disabled = !statusInfo.allowCancel;
          }
          const roleKey = role || '__global__';
          const roleStatus = role ? statusInfo.status : normalizeStatus(rolesData?.[role]?.status ?? globalStatus);
          const isRoleTerminal = TERMINAL_STATUSES.has(roleStatus);
          const wasTerminal = roleTerminatedMap.get(roleKey) === true;
          roleTerminatedMap.set(roleKey, isRoleTerminal);
          if (isRoleTerminal && !wasTerminal) {
            terminalTransition = true;
          }
        });

        if (terminalTransition && typeof loadComparisons === 'function' && isValidWorkId(currentWorkId)) {
          loadComparisons(currentWorkId);
        }

        const roles = rolesData;
        const roleStatuses = Object.values(roles).map(roleInfo => normalizeStatus(roleInfo?.status ?? globalStatus));
        const rolesKnown = roleStatuses.length > 0;
        const allRolesTerminal = rolesKnown && roleStatuses.every(status => TERMINAL_STATUSES.has(status));
        const isTerminal = !progress
          || (globalStatus && TERMINAL_STATUSES.has(globalStatus))
          || allRolesTerminal;

        if (isTerminal) {
          if (!currentEntry.completed && typeof loadComparisons === 'function' && isValidWorkId(currentWorkId)) {
            loadComparisons(currentWorkId);
          }
          currentEntry.completed = true;
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

  function renderComparisonRole(comp, role, versionName) {
    const data = (comp.pagination && comp.pagination[role]) || {};
    const container = document.createElement('div');
    container.className = 'small text-start d-flex flex-column gap-2';

    const badges = document.createElement('div');
    const markersBadge = createBadge({
      text: `${formatNumber(data.markers ?? 0)} tags`,
      className: 'bg-secondary'
    });
    badges.appendChild(markersBadge);

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
    badges.appendChild(lignesBadge);

    const manifestInfo = (comp.manifests && comp.manifests[role]) || {};
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
    container.appendChild(badges);

    if (data.lignes && (data.lignes.updated_at || data.lignes.size)) {
      const hint = document.createElement('div');
      hint.className = 'text-muted small';
      const updated = data.lignes.updated_at ? formatTimestamp(data.lignes.updated_at) : '—';
      const size = data.lignes.size ? formatBytes(data.lignes.size) : '0 o';
      hint.textContent = `Fichier _lignes : ${updated} · ${size}`;
      container.appendChild(hint);
    }

    const statusEl = document.createElement('div');
    statusEl.className = 'text-muted small';
    container.appendChild(statusEl);
    const progressSnapshot = comp.comparison_progress || null;
    statusEl.dataset.comparisonId = String(comp.id);
    statusEl.dataset.paginationRole = role;

    const options = document.createElement('div');
    options.className = 'mt-2';
    const clearId = `cmp-${comp.id}-${role}-clear`;
    const replaceId = `cmp-${comp.id}-${role}-replace`;
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
    feedback.className = 'small text-muted';
    container.appendChild(feedback);

    const btnGroup = document.createElement('div');
    btnGroup.className = 'd-flex gap-2 mt-2 flex-wrap';
    container.appendChild(btnGroup);

    const runBtn = document.createElement('button');
    runBtn.type = 'button';
    runBtn.className = 'btn btn-sm btn-outline-secondary';
    runBtn.textContent = 'Injecter la pagination';
    btnGroup.appendChild(runBtn);

    const restoreBtn = document.createElement('button');
    restoreBtn.type = 'button';
    restoreBtn.className = 'btn btn-sm btn-outline-danger';
    restoreBtn.textContent = 'Restaurer les originaux';
    btnGroup.appendChild(restoreBtn);

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'btn btn-sm btn-outline-warning';
    cancelBtn.textContent = 'Annuler l\'injection';
    btnGroup.appendChild(cancelBtn);

    statusEl.__cancelBtn = cancelBtn;
    const statusRef = { role, statusEl, label: versionName, cancelBtn };
    registerPaginationStatus(comp.id, statusEl, progressSnapshot, versionName, role);

    const hasLignes = data.lignes_available ?? false;
    if (!hasLignes) {
      runBtn.disabled = true;
      feedback.textContent = 'Associez un fichier _lignes à cette version.';
    }

    const currentRoleStatus = normalizeStatus(
      (progressSnapshot?.roles?.[role]?.status ?? progressSnapshot?.status) || ''
    );
    if (!['queued', 'running'].includes(currentRoleStatus)) {
      cancelBtn.disabled = true;
    }

    runBtn.addEventListener('click', () => {
      const clearExisting = clearToggle ? clearToggle.checked : true;
      const replaceExisting = replaceToggle ? replaceToggle.checked : true;
      cancelBtn.disabled = false;
      triggerComparisonPagination(comp, {
        role,
        clearExisting,
        replaceExisting,
        button: runBtn,
        feedback,
        statusRefs: [statusRef]
      });
    });

    restoreBtn.addEventListener('click', () => {
      if (!confirm('Les fichiers originaux de sortie Medite vont être restaurés; tous les marqueurs de pagination seront supprimés.')) {
        return;
      }
      restoreComparisonPagination(comp, {
        role,
        button: restoreBtn,
        feedback,
        statusRefs: [statusRef],
      });
    });

    cancelBtn.addEventListener('click', () => {
      if (!confirm('Annuler la pagination en cours pour cette version ?')) {
        return;
      }
      cancelComparisonPagination(comp, {
        role,
        button: cancelBtn,
        feedback,
        statusRefs: [statusRef],
      });
    });

    return { element: container, statusRef };
  }

  async function triggerComparisonPagination(comp, { role = null, clearExisting, replaceExisting, button, feedback, statusRefs }) {
    const lockKey = role ? `inject-${comp.id}-${role}` : `inject-${comp.id}`;
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
      const requestPayload = {
        clear_existing: clearExisting ? 1 : 0,
        replace_existing: replaceExisting ? 1 : 0,
      };
      if (role) {
        requestPayload.role = role;
      }

      const res = await fetch(withBasePath(`/api/comparisons/${comp.id}/page-markers`), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(CSRF_TOKEN ? { 'X-CSRF-TOKEN': CSRF_TOKEN } : {})
        },
        body: JSON.stringify(requestPayload)
      });

      const text = await res.text();
      let responsePayload = {};
      try { responsePayload = JSON.parse(text); } catch { responsePayload = { raw: text }; }

      if (!res.ok || responsePayload.status !== 'queued') {
        const message = responsePayload.message || responsePayload.error || responsePayload.raw || `HTTP ${res.status}`;
        throw new Error(message);
      }

      if (feedback) {
        feedback.textContent = responsePayload.message || 'Injection en file d\'attente…';
      }

      const timestamp = Math.floor(Date.now() / 1000);
      if (role) {
        setPendingStatus(comp.id, role, 'queued', {
          total: responsePayload.total ?? null,
          updated_at: timestamp,
        });
      }
      (statusRefs || []).forEach(({ role: statusRole, statusEl, label }) => {
        if (!statusEl) return;
        statusEl.dataset.paginationLabel = label || '';
        statusEl.dataset.paginationRole = statusRole || '';
        const progressStub = {
          status: 'queued',
          updated_at: timestamp,
          roles: statusRole ? { [statusRole]: { status: 'queued', updated_at: timestamp } } : {}
        };
        const statusInfo = computeStatusInfo(progressStub, statusRole, comp.id);
        statusEl.textContent = label ? `${label} — ${statusInfo.text}` : statusInfo.text;
        const cancelBtn = statusEl.__cancelBtn;
        if (cancelBtn) cancelBtn.disabled = !statusInfo.allowCancel;
      });
      const observerEntry = paginationObservers.get(comp.id);
      if (observerEntry) {
        observerEntry.completed = false;
      }
      startComparisonPolling(comp.id);
      if (isValidWorkId(currentWorkId)) {
        setTimeout(() => {
          if (isValidWorkId(currentWorkId)) {
            loadComparisons(currentWorkId);
          }
        }, 250);
      }
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

  async function restoreComparisonPagination(comp, { role = null, button, feedback, statusRefs }) {
    const lockKey = role ? `restore-${comp.id}-${role}` : `restore-${comp.id}`;
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
      const requestPayload = role ? { role } : null;
      const headers = {
        'Accept': 'application/json',
        ...(CSRF_TOKEN ? { 'X-CSRF-TOKEN': CSRF_TOKEN } : {})
      };
      if (requestPayload) {
        headers['Content-Type'] = 'application/json';
      }

      const res = await fetch(withBasePath(`/api/comparisons/${comp.id}/page-markers/restore`), {
        method: 'POST',
        headers,
        body: requestPayload ? JSON.stringify(requestPayload) : null
      });

      const responsePayload = await res.json().catch(() => ({}));
      if (!res.ok || responsePayload.status !== 'restored') {
        const message = responsePayload.message || `HTTP ${res.status}`;
        throw new Error(message);
      }

      if (feedback) {
        feedback.textContent = responsePayload.message || 'Originaux restaurés.';
      }

      if (role) {
        clearPendingStatus(comp.id, role);
      } else {
        ['source', 'target'].forEach(r => clearPendingStatus(comp.id, r));
      }

      const progress = responsePayload.progress || {
        status: 'restored',
        updated_at: Math.floor(Date.now() / 1000),
        roles: role ? { [role]: { status: 'restored' } } : {}
      };

      (statusRefs || []).forEach(({ role: statusRole, statusEl, label }) => {
        if (!statusEl) return;
        const statusInfo = computeStatusInfo(progress, statusRole, comp.id);
        statusEl.textContent = label ? `${label} — ${statusInfo.text}` : statusInfo.text;
        const cancelBtn = statusEl.__cancelBtn;
        if (cancelBtn) cancelBtn.disabled = true;
      });
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

  async function cancelComparisonPagination(comp, { role = null, button, feedback, statusRefs }) {
    const lockKey = role ? `cancel-${comp.id}-${role}` : `cancel-${comp.id}`;
    if (paginationLocks.has(lockKey)) return;
    paginationLocks.add(lockKey);

    const originalLabel = button ? button.textContent : '';
    if (button) {
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }
    if (feedback) {
      feedback.textContent = 'Annulation en cours…';
    }

    let shouldDisableButton = false;
    try {
      const payload = role ? { role } : {};
      const res = await fetch(withBasePath(`/api/comparisons/${comp.id}/page-markers/cancel`), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(CSRF_TOKEN ? { 'X-CSRF-TOKEN': CSRF_TOKEN } : {})
        },
        body: JSON.stringify(payload)
      });

      const text = await res.text();
      let data = {};
      try { data = JSON.parse(text); } catch { data = { raw: text }; }

      if (!res.ok || data.status !== 'cancelled') {
        const message = data.message || data.error || data.raw || `HTTP ${res.status}`;
        throw new Error(message);
      }

      if (feedback) {
        feedback.textContent = data.message || 'Pagination annulée.';
      }

      const progress = data.progress || {
        status: 'cancelled',
        updated_at: Math.floor(Date.now() / 1000),
        roles: role ? { [role]: { status: 'cancelled', reason: data.message || '' } } : {}
      };

      if (role) {
        clearPendingStatus(comp.id, role);
      } else {
        ['source', 'target'].forEach(r => clearPendingStatus(comp.id, r));
      }

      (statusRefs || []).forEach(({ role: statusRole, statusEl, label }) => {
        if (!statusEl) return;
        const statusInfo = computeStatusInfo(progress, statusRole, comp.id);
        statusEl.textContent = label ? `${label} — ${statusInfo.text}` : statusInfo.text;
        const cancelBtn = statusEl.__cancelBtn;
        if (cancelBtn) cancelBtn.disabled = true;
      });

      shouldDisableButton = true;

    } catch (err) {
      console.error('Erreur annulation pagination', err);
      if (feedback) {
        feedback.textContent = 'Échec de l\'annulation.';
      }
      alert("Impossible d'annuler la pagination : " + (err?.message || 'erreur inconnue'));
    } finally {
      paginationLocks.delete(lockKey);
      if (button) {
        button.textContent = originalLabel || 'Annuler l\'injection';
        button.disabled = shouldDisableButton;
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

    const requestToken = ++latestComparisonsRequest;
    activeComparisonsRequest = requestToken;

    loading.style.display = 'block';
    tbody.innerHTML = '';
    noComparisons.style.display = 'none';

    try {
      const res = await fetch(withBasePath(`/comparisons/by-work?work_id=${workId}`), { headers: JSON_HEADERS });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const comparisons = await res.json();

      if (requestToken !== activeComparisonsRequest) {
        return;
      }

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

        const sourceName = comp.source_version?.name ?? `Version ${comp.source_id}`;
        const targetName = comp.target_version?.name ?? `Version ${comp.target_id}`;

        tr.innerHTML = `
          <td>${comp.id}</td>
          <td class="align-top source-cell">
            <div><strong>${sourceName}</strong></div>
            <div class="role-wrapper"></div>
          </td>
          <td class="align-top target-cell">
            <div><strong>${targetName}</strong></div>
            <div class="role-wrapper"></div>
          </td>
          <td>${comp.folder ?? ''}</td>
          <td>${comp.ratio ?? ''}</td>
          <td>${comp.lg_pivot ?? ''}</td>
          <td>${caseSensitive ? 'yes' : 'no'}</td>
          <td class="text-center components-status">${statusHtml}</td>
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
            <a href="/comparison/${comp.id}/editor"  class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i></a>
            <button class="btn btn-sm btn-outline-danger ms-1 delete-comparison-btn" data-id="${comp.id}"><i class="bi bi-trash3"></i></button>
          </td>
        `;
        tbody.appendChild(tr);
        const sourceWrapper = tr.querySelector('.source-cell .role-wrapper');
        if (sourceWrapper) {
          const roleComponent = renderComparisonRole(comp, 'source', sourceName);
          sourceWrapper.appendChild(roleComponent.element);
        }
        const targetWrapper = tr.querySelector('.target-cell .role-wrapper');
        if (targetWrapper) {
          const roleComponent = renderComparisonRole(comp, 'target', targetName);
          targetWrapper.appendChild(roleComponent.element);
        }
      });
    } catch (err) {
      console.error('Erreur lors du chargement des comparaisons:', err);
      // Leave UI cleared; don't try to render an error page as JSON
    } finally {
      if (requestToken === activeComparisonsRequest) {
        loading.style.display = 'none';
      }
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
}

window.initComparisonsTable = initComparisonsTable;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initComparisonsTable);
} else {
  initComparisonsTable();
}
