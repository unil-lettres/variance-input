function initComparisonsTable() {
  const tbody          = document.querySelector('#comparisons-table tbody');
  const comparisonsTable = document.getElementById('comparisons-table');
  const detailsToggle  = document.getElementById('comparison-details-toggle');
  const loading        = document.getElementById('comparisons-loading');
  const noComparisons  = document.getElementById('no-comparisons');
  const comparisonsTitleCount = document.getElementById('comparisons-title-count');
  const comparisonsSummaryTitle = document.getElementById('comparisons-summary-title');
  const comparisonsSummarySubtitle = document.getElementById('comparisons-summary-subtitle');
  const comparisonsSummaryTotal = document.getElementById('comparisons-summary-total');
  const comparisonsSummaryPublished = document.getElementById('comparisons-summary-published');
  const comparisonsSummaryDraft = document.getElementById('comparisons-summary-draft');
  const setComparisonsLoading = (state) => {
    if (typeof window.setBladeLoading === 'function') {
      window.setBladeLoading('comparisonsCollapse', state);
    }
  };
  const setComparisonsTableVisible = (visible) => {
    if (!comparisonsTable) return;
    comparisonsTable.style.display = visible ? '' : 'none';
  };

  const JSON_HEADERS = { 'Accept': 'application/json' };
  let currentWorkId = null;
  let currentAuthorFolder = '';
  let currentWorkFolder = '';
  const runningComparisons = window.__runningComparisons || new Set();
  window.__runningComparisons = runningComparisons;
  const paginationLocks = new Set();
  const paginationObservers = new Map();
  const pendingRoleStatuses = new Map();
  const comparisonRoleComponents = new Map();
  const comparisonData = new Map();
  const comparisonRows = new Map();
  const comparisonDetailsRequests = new Map();
  const exportStatusPollers = new Map();
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  let latestComparisonsRequest = 0;
  let activeComparisonsRequest = 0;
  let loadedComparisonsWorkId = null;
  let currentAuthorId = null;
  const adminMain = document.getElementById('admin-main');
  const currentUserId = (() => {
    const raw = adminMain?.dataset?.userId ?? '';
    return /^\d+$/.test(raw) ? Number(raw) : null;
  })();
  const currentUserIsAdmin = adminMain?.dataset?.userIsAdmin === '1';
  const ownershipNote = 'Action réservée au créateur de la comparaison ou aux éditeurs de l’œuvre.';
  const publishedNote = 'Dépubliez cette comparaison avant de modifier ses composants.';
  let showComparisonDetails = false;
  const isComparisonPublished = (comp) => {
    const scope = comp?.publication_scope || ((comp?.is_legacy) ? 'prod' : null);
    return !!(scope || comp?.published === true);
  };

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
  const escapeHtml = (value) => {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };
  const formatPublishWarnings = warnings => {
    if (!Array.isArray(warnings)) return [];
    return warnings
      .map(item => String(item ?? '').trim())
      .filter(Boolean);
  };
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
  const formatComparisonDate = value => {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    const formatted = date.toLocaleString('fr-FR', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });
    return formatted.replace(',', '').trim();
  };
  const applyComparisonDetailsMode = () => {
    if (!comparisonsTable) return;
    comparisonsTable.classList.toggle('compact-details', !showComparisonDetails);
  };
  if (detailsToggle) {
    detailsToggle.checked = false;
  }
  applyComparisonDetailsMode();
  const formatDuration = ms => {
    const value = Number(ms);
    if (!Number.isFinite(value) || value <= 0) return null;
    const totalSeconds = Math.round(value / 1000);
    if (totalSeconds < 60) {
      return `${totalSeconds}s`;
    }
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    if (minutes < 60) {
      return `${minutes}m ${seconds}s`;
    }
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
  };
  const formatElapsedSince = value => {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    const elapsedMs = Date.now() - date.getTime();
    if (!Number.isFinite(elapsedMs) || elapsedMs < 0) return null;
    const totalSeconds = Math.floor(elapsedMs / 1000);
    if (totalSeconds < 60) {
      return `${totalSeconds}s`;
    }
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    if (minutes < 60) {
      return `${minutes}m ${seconds.toString().padStart(2, '0')}s`;
    }
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins.toString().padStart(2, '0')}m`;
  };
  const normalizeCount = value => {
    const num = Number(value);
    return Number.isFinite(num) && num >= 0 ? num : 0;
  };
  const isValidId = value => {
    const s = String(value ?? '').trim();
    return /^\d+$/.test(s) && Number(s) > 0;
  };

  let paginationWarningModal = null;
  let paginationWarningResolve = null;
  let commentModal = null;
  let activeCommentComparisonId = null;
  const getPaginationWarningChoice = () => {
    const modalEl = document.getElementById('pagination-warning-modal');
    if (!modalEl || !window.bootstrap || !bootstrap.Modal) {
      return Promise.resolve(null);
    }
    if (!paginationWarningModal) {
      paginationWarningModal = new bootstrap.Modal(modalEl, {
        backdrop: true,
        keyboard: true,
      });
      modalEl.addEventListener('hidden.bs.modal', () => {
        if (paginationWarningResolve) {
          paginationWarningResolve(null);
          paginationWarningResolve = null;
        }
      });
      modalEl.querySelectorAll('[data-pagination-choice]').forEach(btn => {
        btn.addEventListener('click', () => {
          const choice = btn.dataset.paginationChoice || null;
          if (paginationWarningResolve) {
            paginationWarningResolve(choice);
            paginationWarningResolve = null;
          }
          paginationWarningModal.hide();
        });
      });
    }
    return new Promise(resolve => {
      paginationWarningResolve = resolve;
      paginationWarningModal.show();
    });
  };
  const commentModalEl = document.getElementById('comparison-comment-modal');
  const commentInput = document.getElementById('comparison-comment-input');
  const commentSaveBtn = document.getElementById('comparison-comment-save-btn');
  const getCommentModal = () => {
    if (!commentModalEl || !window.bootstrap || !bootstrap.Modal) {
      return null;
    }
    if (!commentModal) {
      commentModal = new bootstrap.Modal(commentModalEl, {
        backdrop: true,
        keyboard: true,
      });
      commentModalEl.addEventListener('hidden.bs.modal', () => {
        activeCommentComparisonId = null;
        if (commentInput) {
          commentInput.value = '';
        }
        if (commentSaveBtn) {
          commentSaveBtn.disabled = false;
        }
      });
    }
    return commentModal;
  };

  function normalizeComment(value) {
    const text = String(value ?? '').trim();
    return text === '' ? null : text;
  }

  function openCommentModal(comparisonId) {
    const id = Number(comparisonId);
    if (!Number.isFinite(id) || !commentInput) return;
    const modal = getCommentModal();
    if (!modal) return;
    const comp = comparisonData.get(id) || {};
    activeCommentComparisonId = id;
    commentInput.value = comp.comments ?? '';
    commentSaveBtn.disabled = false;
    modal.show();
    window.setTimeout(() => commentInput.focus(), 50);
  }

  function openChaptersPanel(comparisonId) {
    const id = Number(comparisonId);
    if (!Number.isFinite(id)) return;
    const comp = comparisonData.get(id) || {};

    document.dispatchEvent(new CustomEvent('comparisonChaptersRequested', {
      detail: {
        comparisonId: id,
        workId: currentWorkId,
        chapterCount: Number(comp.chapter_count ?? 0),
      }
    }));
  }

  function updateComparisonCounts(published, total) {
    const pub = normalizeCount(published);
    const tot = normalizeCount(total);
    const count = (tot === 0 && pub === 0 && (!isValidWorkId(currentWorkId) || !isValidId(currentAuthorId))) ? 0 : tot;
    if (comparisonsTitleCount) {
      comparisonsTitleCount.textContent = `(${count})`;
    }
    const draft = Math.max(0, count - pub);
    const totalLabel = `${count} comparaison${count === 1 ? '' : 's'}`;
    const publishedLabel = `${pub} publiée${pub === 1 ? '' : 's'}`;
    const draftLabel = `${draft} éditoriale${draft === 1 ? '' : 's'}`;
    if (comparisonsSummaryTotal) comparisonsSummaryTotal.textContent = totalLabel;
    if (comparisonsSummaryPublished) comparisonsSummaryPublished.textContent = publishedLabel;
    if (comparisonsSummaryDraft) comparisonsSummaryDraft.textContent = draftLabel;
  }

  function updateComparisonSummaryState(state, counts = {}) {
    if (!comparisonsSummaryTitle || !comparisonsSummarySubtitle || !comparisonsSummaryTotal || !comparisonsSummaryPublished || !comparisonsSummaryDraft) {
      return;
    }
    const total = normalizeCount(counts.total);
    const published = normalizeCount(counts.published);
    const draft = Math.max(0, total - published);
    comparisonsSummaryTotal.textContent = `${total} comparaison${total === 1 ? '' : 's'}`;
    comparisonsSummaryPublished.textContent = `${published} publiée${published === 1 ? '' : 's'}`;
    comparisonsSummaryDraft.textContent = `${draft} éditoriale${draft === 1 ? '' : 's'}`;

    if (state === 'idle') {
      comparisonsSummaryTitle.textContent = 'Section en attente de sélection';
      comparisonsSummarySubtitle.textContent = 'Sélectionnez une œuvre pour afficher les comparaisons textuelles disponibles.';
      return;
    }
    if (state === 'loading') {
      comparisonsSummaryTitle.textContent = 'Chargement des comparaisons textuelles';
      comparisonsSummarySubtitle.textContent = 'Les résultats éditoriaux de l’œuvre sélectionnée sont en cours de récupération.';
      return;
    }
    if (state === 'empty') {
      comparisonsSummaryTitle.textContent = 'Aucune comparaison textuelle disponible';
      comparisonsSummarySubtitle.textContent = 'Lancez un alignement dans la section Alignement Medite pour produire un premier résultat.';
      return;
    }
    comparisonsSummaryTitle.textContent = 'Comparaisons disponibles pour l’œuvre sélectionnée';
    comparisonsSummarySubtitle.textContent = published > 0
      ? 'Chaque comparaison peut être consultée, complétée puis publiée individuellement.'
      : 'Les comparaisons listées ci-dessous sont encore au stade éditorial tant qu’elles ne sont pas publiées.';
  }

  function refreshComparisonCounts() {
    const rows = Array.from(comparisonData.values());
    const total = rows.length;
    const published = rows.filter(comp => isComparisonPublished(comp)).length;
    updateComparisonCounts(published, total);
  }

  function updateComparisonRow(comparisonId, updates) {
    const id = Number(comparisonId);
    if (!Number.isFinite(id)) return;
    const existing = comparisonData.get(id) || {};
    const merged = { ...existing, ...updates, details_loaded: true };
    comparisonData.set(id, merged);
    const newRow = buildComparisonRow(merged);
    const oldRow = comparisonRows.get(id);
    if (oldRow && oldRow.parentNode) {
      oldRow.parentNode.replaceChild(newRow, oldRow);
    } else {
      tbody.appendChild(newRow);
    }
    comparisonRows.set(id, newRow);
    refreshComparisonReorderButtons();
    refreshComparisonCounts();
  }

  function refreshComparisonReorderButtons() {
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const reorderRows = rows.filter(row => row.querySelector('.comparison-reorder-btn'));
    const lastIndex = reorderRows.length - 1;

    reorderRows.forEach((row, index) => {
      const upBtn = row.querySelector('.comparison-reorder-btn[data-direction="up"]');
      const downBtn = row.querySelector('.comparison-reorder-btn[data-direction="down"]');

      if (upBtn) {
        upBtn.style.display = (index <= 0) ? 'none' : '';
      }
      if (downBtn) {
        downBtn.style.display = (index >= lastIndex) ? 'none' : '';
      }
    });
  }

  function swapComparisonNumbers(firstId, secondId) {
    const first = comparisonData.get(Number(firstId));
    const second = comparisonData.get(Number(secondId));
    if (!first || !second) return;

    const firstNumber = first.number ?? null;
    first.number = second.number ?? null;
    second.number = firstNumber;
    comparisonData.set(Number(firstId), first);
    comparisonData.set(Number(secondId), second);
  }

  function moveComparisonRowLocally(comparisonId, direction) {
    const id = Number(comparisonId);
    if (!Number.isFinite(id) || !tbody) return false;

    const row = comparisonRows.get(id) || tbody.querySelector(`tr[data-id="${id}"]`);
    if (!row) return false;

    const sibling = direction === 'up'
      ? row.previousElementSibling
      : row.nextElementSibling;

    if (!sibling) return false;

    const siblingId = Number(sibling.dataset.id);
    if (!Number.isFinite(siblingId)) return false;

    if (direction === 'up') {
      tbody.insertBefore(row, sibling);
    } else {
      tbody.insertBefore(sibling, row);
    }

    swapComparisonNumbers(id, siblingId);
    refreshComparisonReorderButtons();
    return true;
  }

  function normalizeExportBundle(comp) {
    const bundle = comp?.export_bundle;
    return bundle && typeof bundle === 'object' ? bundle : { status: 'idle' };
  }

  function stopExportStatusPolling(comparisonId) {
    const id = Number(comparisonId);
    if (!Number.isFinite(id)) return;
    const timerId = exportStatusPollers.get(id);
    if (timerId) {
      window.clearInterval(timerId);
      exportStatusPollers.delete(id);
    }
  }

  async function refreshExportStatus(comparisonId) {
    const id = Number(comparisonId);
    if (!Number.isFinite(id)) return;
    const comp = comparisonData.get(id);
    const statusUrl = comp?.export_bundle?.status_url;
    if (!statusUrl) {
      updateComparisonRow(id, { export_bundle: { status: 'idle' } });
      stopExportStatusPolling(id);
      return;
    }

    try {
      const response = await fetch(statusUrl, { headers: JSON_HEADERS });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const bundle = await response.json();
      if (!bundle || typeof bundle !== 'object') {
        throw new Error('Réponse vide');
      }
      updateComparisonRow(id, { export_bundle: bundle });
      if (!['queued', 'running'].includes(bundle?.status || 'idle')) {
        stopExportStatusPolling(id);
      }
    } catch (error) {
      console.error('Erreur lors du suivi de l’export legacy:', error);
      updateComparisonRow(id, {
        export_bundle: {
          ...(comparisonData.get(id)?.export_bundle || {}),
          status: 'idle',
          message: null,
        }
      });
      stopExportStatusPolling(id);
    }
  }

  function ensureExportStatusPolling(comparisonId) {
    const id = Number(comparisonId);
    if (!Number.isFinite(id) || exportStatusPollers.has(id)) return;
    refreshExportStatus(id);
    const timerId = window.setInterval(() => refreshExportStatus(id), 2000);
    exportStatusPollers.set(id, timerId);
  }

  function renderExportAction(comp) {
    const exportBundle = normalizeExportBundle(comp);
    const status = exportBundle.status || 'idle';
    const queueUrl = exportBundle.queue_url || (typeof withBasePath === 'function'
      ? withBasePath(`/comparisons/${comp.id}/export`)
      : `/comparisons/${comp.id}/export`);
    const downloadUrl = exportBundle.download_url || '';
    const message = exportBundle.message || '';

    if (status === 'ready' && downloadUrl) {
      return `<a href="${downloadUrl}" class="btn btn-outline-success comparison-action-btn"><i class="bi bi-download"></i></a>`;
    }

    if (status === 'queued' || status === 'running') {
      return `<span class="btn btn-outline-secondary comparison-action-btn" aria-disabled="true"><span class="comparison-running-spinner" aria-hidden="true"></span></span>`;
    }

    return `<button type="button" data-export-queue-url="${queueUrl}" data-comparison-id="${comp.id}" class="btn btn-outline-secondary comparison-action-btn comparison-export-btn"><i class="bi bi-download"></i></button>`;
  }

  function renderPublishStatusHtml(comp, { detailsLoaded = false, isLegacy = false, isPublished = false, isPublishedProd = false } = {}) {
    const pending = comp?.publication_pending;
    if (pending && typeof pending === 'object') {
      const pendingLabel = String(pending.label || 'Mise à jour…');
      return `<span class="comparison-publish-status"><span class="comparison-running-spinner" aria-hidden="true"></span><span class="badge text-bg-warning text-dark comparison-publish-pill">${pendingLabel}</span></span>`;
    }

    const publishStatusLabel = !detailsLoaded && !isLegacy
      ? '…'
      : (isPublished ? (isPublishedProd ? 'Publié sur prod' : 'Publié sur /dev') : 'Non publié');
    const publishStatusClass = isPublished
      ? (isPublishedProd ? 'comparison-publish-pill--prod' : 'comparison-publish-pill--dev')
      : 'comparison-publish-pill--draft';

    return `<span class="badge ${publishStatusClass} comparison-publish-pill">${publishStatusLabel}</span>`;
  }

  function createBadge({ text, className = '', href = null, title = '' }) {
    const tag = href ? 'a' : 'span';
    const el = document.createElement(tag);
    const classes = ['badge'];
    if (className && className.trim()) {
      classes.push(className.trim());
    }
    el.className = classes.join(' ');
    el.textContent = text;
    if (href) {
      el.href = href;
      el.target = '_blank';
      el.rel = 'noopener';
    }
    return el;
  }

  function registerTooltip(element, options = {}) {
    return null;
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
      } else {
        const roleStatusNow = normalizeStatus(roleEntry.status ?? '');
        if (!TERMINAL_STATUSES.has(roleStatusNow)) {
          usePending = pendingUpdatedAt
            ? (roleUpdatedAt < pendingUpdatedAt && progressUpdatedAt < pendingUpdatedAt)
            : false;
        }
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
          loadComparisons(currentWorkId, { force: true });
        }

        const roles = rolesData;
        const roleStatuses = Object.values(roles).map(roleInfo => normalizeStatus(roleInfo?.status ?? globalStatus));
        const rolesKnown = roleStatuses.length > 0;
        const allRolesTerminal = rolesKnown && roleStatuses.every(status => TERMINAL_STATUSES.has(status));
        const isTerminal = rolesKnown
          ? allRolesTerminal
          : (!progress || (globalStatus && TERMINAL_STATUSES.has(globalStatus)));

        if (isTerminal) {
          if (!currentEntry.completed && typeof loadComparisons === 'function' && isValidWorkId(currentWorkId)) {
            loadComparisons(currentWorkId, { force: true });
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
    if (comp?.details_loaded === false) {
      const container = document.createElement('div');
      container.className = 'small text-start text-muted';
      container.textContent = 'Chargement…';
      return {
        element: container,
        statusRef: null,
        updateManifest: () => {},
        getManifestInfo: () => ({}),
      };
    }
    const isLegacy = !!comp.is_legacy;
    const ownershipBlocked = !!comp._ownershipBlocked;
    const publishedLocked = isComparisonPublished(comp);
    const legacyNote = 'La pagination des comparaisons legacy est en lecture seule.';
    const data = (comp.pagination && comp.pagination[role]) || {};
    const container = document.createElement('div');
    container.className = 'small text-start d-flex flex-column gap-2';
    const applyLegacyButtonState = (btn) => {
      if (!btn) return;
      btn.classList.add('legacy-disabled');
      btn.setAttribute('aria-disabled', 'true');
      btn.setAttribute('data-legacy-disabled', '1');
      registerTooltip(btn);
    };
    const applyLegacyDisabledState = (ctrl) => {
      if (!ctrl) return;
      ctrl.disabled = true;
      ctrl.setAttribute('aria-disabled', 'true');
    };
    const applyOwnershipButtonState = (btn) => {
      if (!btn) return;
      btn.classList.add('legacy-disabled');
      btn.setAttribute('aria-disabled', 'true');
      btn.setAttribute('data-ownership-disabled', '1');
      registerTooltip(btn);
    };
    const applyOwnershipDisabledState = (ctrl) => {
      if (!ctrl) return;
      ctrl.disabled = true;
      ctrl.setAttribute('aria-disabled', 'true');
    };
    const applyPublishedButtonState = (btn) => {
      if (!btn) return;
      btn.classList.add('legacy-disabled');
      btn.setAttribute('aria-disabled', 'true');
      btn.setAttribute('data-published-disabled', '1');
      registerTooltip(btn);
    };
    const applyPublishedDisabledState = (ctrl) => {
      if (!ctrl) return;
      ctrl.disabled = true;
      ctrl.setAttribute('aria-disabled', 'true');
    };

    const badges = document.createElement('div');
    badges.className = 'comparison-role-details';
    const markersBadge = createBadge({
      text: `${formatNumber(data.markers ?? 0)} tags`,
      className: 'bg-secondary'
    });
    badges.appendChild(markersBadge);

    const sidecarAvailable = data.sidecar && typeof data.sidecar === 'object';
    const sidecarInfo = sidecarAvailable ? data.sidecar : {};
    const origin = (sidecarInfo.details?.origin || sidecarInfo.origin || '').toLowerCase();
    let originLabel = '';
    let originClass = 'bg-warning text-dark ms-1';
    let originTitle = '';
    if (origin === 'pb-xhtml') {
      originLabel = 'sidecar (pb)';
      originClass = 'bg-info text-dark ms-1';
      originTitle = 'Sidecar généré depuis les balises <pb> des fichiers XHTML';
    } else if (origin === 'pb-tei') {
      originLabel = 'sidecar (pb TEI)';
      originClass = 'bg-info text-dark ms-1';
      originTitle = 'Sidecar généré depuis les balises <pb> du TEI';
    } else if (origin === 'lignes') {
      originLabel = 'pagination JSON';
      originClass = 'bg-info text-dark ms-1';
      originTitle = 'Sidecar de pagination généré depuis un fichier _lignes';
    } else if (origin) {
      originLabel = origin;
      originClass = 'bg-info text-dark ms-1';
    }

    const hasLignesFile = data.has_lignes_file ?? !!data.lignes;
    if (hasLignesFile) {
      badges.appendChild(createBadge({
        text: '_lignes',
        className: 'bg-success ms-1',
        title: 'Fichier _lignes disponible'
      }));
    } else {
      badges.appendChild(createBadge({
        text: '_lignes manquant',
        className: 'bg-warning text-dark ms-1',
        title: 'Associez un fichier _lignes ou générez un sidecar'
      }));
    }

    if (originLabel) {
      badges.appendChild(createBadge({
        text: originLabel,
        className: originClass,
        title: originTitle || 'Sidecar présent'
      }));
    } else if (sidecarAvailable) {
      badges.appendChild(createBadge({
        text: 'sidecar',
        className: 'bg-info text-dark ms-1',
        title: 'Sidecar de pagination présent'
      }));
    } else {
      badges.appendChild(createBadge({
        text: 'sidecar absent',
        className: 'bg-light text-muted ms-1',
        title: 'Aucun sidecar de pagination détecté'
      }));
    }

    const manifestBadgeWrapper = document.createElement('span');
    manifestBadgeWrapper.className = 'manifest-badge-wrapper';
    badges.appendChild(manifestBadgeWrapper);
    const versionId = role === 'source' ? Number(comp.source_id) : Number(comp.target_id);
    const versionLabel = versionName || (role === 'source'
      ? (comp.source_version?.name ?? `Version ${comp.source_id}`)
      : (comp.target_version?.name ?? `Version ${comp.target_id}`));
    let currentManifestInfo = (comp.manifests && comp.manifests[role])
      ? { ...comp.manifests[role] }
      : {};

    function manifestTargetUrl(info) {
      if (info && typeof info === 'object') {
        if (info.api_url) return info.api_url;
        if (info.url) return info.url;
      }
      return typeof withBasePath === 'function'
        ? withBasePath(`/comparisons/${comp.id}/manifests/${role}`)
        : `/comparisons/${comp.id}/manifests/${role}`;
    }

    function renderManifestBadge(info = {}) {
      manifestBadgeWrapper.innerHTML = '';
      const safeInfo = info && typeof info === 'object' ? info : {};
      const exists = !!safeInfo.exists;
      const rawCount = Number(safeInfo.count ?? (Array.isArray(safeInfo.selected) ? safeInfo.selected.length : 0));
      const countValue = Number.isFinite(rawCount) ? rawCount : 0;
      const displayCount = formatNumber(countValue);

      if (exists) {
        const manifestBadge = createBadge({
          text: `JSON ${displayCount} x2`,
          className: 'bg-info text-dark ms-1 manifest-json-pill',
          title: safeInfo.file
            ? `${safeInfo.file} — ${displayCount} fac-similé${countValue === 1 ? '' : 's'} + miniature${countValue === 1 ? '' : 's'}`
            : 'Manifeste JSON — fac-similés et miniatures'
        });
        const focusManifest = (evt) => {
          if (evt) {
            evt.preventDefault();
            evt.stopPropagation();
          }
          document.dispatchEvent(new CustomEvent('facsimiles:focusManifest', {
            detail: {
              versionId,
              versionName: versionLabel || '',
              comparisonId: comp.id,
              role,
            },
          }));
        };
        manifestBadge.setAttribute('role', 'button');
        manifestBadge.tabIndex = 0;
        if (!ownershipBlocked && !isLegacy) {
          manifestBadge.addEventListener('click', focusManifest);
          manifestBadge.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
              focusManifest(event);
            }
          });
        } else {
          manifestBadge.classList.add('legacy-disabled');
          manifestBadge.setAttribute('aria-disabled', 'true');
        }
        manifestBadgeWrapper.appendChild(manifestBadge);
      } else {
        const placeholder = createBadge({
          text: 'manifeste absent',
          className: 'bg-light text-muted ms-1',
          title: 'Aucun manifeste JSON détecté'
        });
        manifestBadgeWrapper.appendChild(placeholder);
      }
    }

    function updateManifest(info = {}) {
      const incoming = info && typeof info === 'object' ? info : {};
      currentManifestInfo = {
        ...(currentManifestInfo || {}),
        ...incoming,
      };
      if (currentManifestInfo.count === undefined && Array.isArray(currentManifestInfo.selected)) {
        currentManifestInfo.count = currentManifestInfo.selected.length;
      }
      if (incoming.api_url === undefined && currentManifestInfo.api_url === undefined) {
        currentManifestInfo.api_url = manifestTargetUrl(incoming);
      }
      renderManifestBadge(currentManifestInfo);
    }

    updateManifest(currentManifestInfo);
    container.appendChild(badges);

    const feedback = document.createElement('div');
    feedback.className = 'small text-muted comparison-role-details';
    container.appendChild(feedback);

    const btnGroup = document.createElement('div');
    btnGroup.className = 'd-flex gap-2 mt-2 flex-wrap d-none';
    container.appendChild(btnGroup);

    const lignesInputId = `cmp-${comp.id}-${role}-lignes`;
    const lignesInput = document.createElement('input');
    lignesInput.type = 'file';
    lignesInput.accept = '.txt,text/plain';
    lignesInput.id = lignesInputId;
    lignesInput.style.display = 'none';
    container.appendChild(lignesInput);

    const uploadLignesBtn = document.createElement('button');
    uploadLignesBtn.type = 'button';
    uploadLignesBtn.className = 'btn btn-sm btn-outline-primary';
    uploadLignesBtn.textContent = 'Importer _lignes';
    uploadLignesBtn.hidden = !!sidecarAvailable;
    btnGroup.appendChild(uploadLignesBtn);

    const buildSidecarBtn = document.createElement('button');
    buildSidecarBtn.type = 'button';
    buildSidecarBtn.className = 'btn btn-sm btn-outline-primary';
    buildSidecarBtn.textContent = 'Recréer depuis la comparaison (XHTML)';
    buildSidecarBtn.hidden = !!sidecarAvailable;
    btnGroup.appendChild(buildSidecarBtn);

    const hasPaginationData = !!sidecarAvailable || !!(data.lignes_available ?? false);
    if (!hasPaginationData && !isLegacy) {
      feedback.textContent = 'Associez un fichier _lignes ou générez le sidecar depuis les balises <pb>.';
    }

    uploadLignesBtn.addEventListener('click', () => {
      if (isLegacy || ownershipBlocked || publishedLocked) return;
      lignesInput.value = '';
      lignesInput.click();
    });

    lignesInput.addEventListener('change', async () => {
      if (isLegacy || ownershipBlocked || publishedLocked) return;
      if (!lignesInput.files || !lignesInput.files.length) return;
      const file = lignesInput.files[0];
      const versionId = role === 'source' ? comp.source_id : comp.target_id;
      if (!versionId) {
        alert('Version introuvable pour cette comparaison.');
        return;
      }
      uploadLignesBtn.disabled = true;
      uploadLignesBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
      if (feedback) {
        feedback.textContent = 'Envoi du fichier _lignes…';
      }
      try {
        const fd = new FormData();
        fd.append('lignes', file);
        const res = await fetch(withBasePath(`/api/versions/${versionId}/lignes`), {
          method: 'POST',
          headers: {
            ...(CSRF_TOKEN ? { 'X-CSRF-TOKEN': CSRF_TOKEN } : {})
          },
          body: fd
        });
        const text = await res.text();
        let payload = {};
        try { payload = JSON.parse(text); } catch { payload = { raw: text }; }
        if (!res.ok) {
          const msg = payload.message || payload.error || payload.raw || `HTTP ${res.status}`;
          throw new Error(msg);
        }
        if (feedback) {
          feedback.textContent = payload.message || 'Fichier _lignes importé, traitement en cours…';
        }
        // refresh table after a short delay to reflect new sidecar status
        setTimeout(() => {
          if (typeof loadComparisons === 'function' && isValidWorkId(currentWorkId)) {
            loadComparisons(currentWorkId, { force: true });
          }
        }, 800);
      } catch (err) {
        console.error('Upload _lignes failed', err);
        alert(err.message || 'Impossible d’importer le fichier _lignes.');
        if (feedback) {
          feedback.textContent = `Erreur _lignes : ${err.message || 'échec'}`;
        }
      } finally {
        uploadLignesBtn.disabled = false;
        uploadLignesBtn.textContent = 'Importer _lignes';
      }
    });

    buildSidecarBtn.addEventListener('click', () => {
      if (isLegacy || ownershipBlocked || publishedLocked) return;
      buildSidecarFromXhtml(comp, {
        role,
        button: buildSidecarBtn,
        feedback,
        onSuccess: () => {
          feedback.textContent = 'Sidecar généré.';
        }
      });
    });

    if (isLegacy) {
      applyLegacyDisabledState(lignesInput);
      [uploadLignesBtn, buildSidecarBtn].forEach(applyLegacyButtonState);
    } else if (publishedLocked) {
      applyPublishedDisabledState(lignesInput);
      [uploadLignesBtn, buildSidecarBtn].forEach(applyPublishedButtonState);
    } else if (ownershipBlocked) {
      applyOwnershipDisabledState(lignesInput);
      [uploadLignesBtn, buildSidecarBtn].forEach(applyOwnershipButtonState);
    }

    return {
      element: container,
      statusRef: null,
      updateManifest,
      getManifestInfo: () => ({ ...(currentManifestInfo || {}) }),
    };
  }

  function renderMediteParams(comp, { detailsLoaded = false } = {}) {
    if (comp?.is_legacy) {
      return '<span class="text-muted">n/a</span>';
    }
    if (!detailsLoaded) {
      return '<span class="text-muted">—</span>';
    }
    const chips = [];

    const pushChip = (html, variant) => {
      let variantClass = 'comparison-param-chip--input';
      chips.push(`<span class="comparison-param-chip ${variantClass}">${html}</span>`);
    };

    const addNumeric = (label, raw) => {
      if (raw === null || raw === undefined || raw === '') return;
      const num = Number(raw);
      const display = Number.isFinite(num) ? num : raw;
      pushChip(`<strong>${label}</strong> ${display}`, 'input');
    };

    const addBoolean = (label, raw) => {
      if (raw === null || raw === undefined) return;
      const active = raw === true || raw === 1 || raw === '1';
      const stateClass = active ? 'text-bg-primary text-white border-0' : 'text-bg-light text-muted';
      pushChip(`<strong>${label}</strong> <span class="${stateClass} px-2 rounded-pill">${active ? 'oui' : 'non'}</span>`, 'input');
    };

    const addSeparators = (rawSep) => {
      const trimmed = rawSep === null || rawSep === undefined ? '' : String(rawSep).trim();
      if (!trimmed) {
        pushChip('<strong>Séparateurs</strong> défaut', 'input');
        return;
      }
      pushChip(`<strong>Séparateurs</strong> ${escapeHtml(trimmed)}`, 'input');
    };

    addNumeric('Pivot', comp.lg_pivot);
    addNumeric('Ratio', comp.ratio);
    addNumeric('Seuil', comp.seuil);
    addBoolean('Sensibilité casse', comp.case_sensitive);
    addSeparators(comp.sep);

    if (!chips.length) {
      return '<span class="text-muted">n/a</span>';
    }

    return `<div class="comparison-params">${chips.join('')}</div>`;
  }

  function getMediteComponentCounts(comp) {
    const counts = comp?.medite_component_counts || comp?.component_counts || {};
    return {
      s: counts.s ?? counts['s.xhtml'] ?? null,
      i: counts.i ?? counts['i.xhtml'] ?? null,
      r: counts.r ?? counts['r.xhtml'] ?? null,
      d: counts.d ?? counts['d.xhtml'] ?? null,
    };
  }

  function renderMetricCell(raw) {
    const num = Number(raw);
    if (!Number.isFinite(num)) {
      return '<span class="text-muted">–</span>';
    }
    return `<span class="comparison-metric-cell">${formatNumber(num)}</span>`;
  }

  function describePaginationState(data = {}) {
    const hasLignesFile = data.has_lignes_file ?? !!data.lignes;
    const sidecar = data.sidecar && typeof data.sidecar === 'object' ? data.sidecar : null;
    const origin = String(sidecar?.details?.origin || sidecar?.origin || '').toLowerCase();

    if (hasLignesFile && sidecar) {
      if (origin === 'pb-xhtml') return '_lignes + sidecar XHTML';
      if (origin === 'pb-tei') return '_lignes + sidecar TEI';
      return '_lignes + sidecar';
    }
    if (hasLignesFile) {
      return '_lignes';
    }
    if (sidecar) {
      if (origin === 'pb-xhtml') return 'sidecar XHTML';
      if (origin === 'pb-tei') return 'sidecar TEI';
      return 'sidecar';
    }
    return 'absent';
  }

  function describeLignesFile(data = {}) {
    const lignes = data?.lignes && typeof data.lignes === 'object' ? data.lignes : null;
    if (!lignes || (!lignes.updated_at && !lignes.size)) {
      return null;
    }
    const updated = lignes.updated_at ? formatTimestamp(lignes.updated_at) : '—';
    const size = lignes.size ? formatBytes(lignes.size) : '0 o';
    return `${updated} · ${size}`;
  }

  function describeExportStatus(comp) {
    const bundle = normalizeExportBundle(comp);
    const status = normalizeStatus(bundle.status || 'idle');
    if (status === 'ready') return 'prêt';
    if (status === 'queued') return 'en file';
    if (status === 'running') return 'en cours';
    if (status === 'failed') return 'échec';
    return 'absent';
  }

  function renderComparisonDataSummary(comp, { detailsLoaded = false } = {}) {
    const lines = [];

    const pushLine = (html, variant = '') => {
      const cls = variant ? ` comparison-results-line--${variant}` : '';
      lines.push(`<div class="comparison-results-line${cls}">${html}</div>`);
    };

    if (!detailsLoaded) {
      pushLine('Chargement…', 'muted');
    }

    const creatorName = comp?.creator_name || comp?.creator?.name || null;
    const createdAt = comp?.created_at ? formatTimestamp(comp.created_at) : null;
    const identityBits = [`<strong>ID</strong> ${comp.id}`];
    if (createdAt) {
      identityBits.push(`<strong>Créée</strong> ${createdAt}`);
    }
    if (creatorName) {
      identityBits.push(`<strong>Par</strong> ${creatorName}`);
    }
    pushLine(identityBits.join(' · '));

    pushLine(
      `<strong>Versions</strong> source #${formatNumber(Number(comp?.source_id ?? 0))} · cible #${formatNumber(Number(comp?.target_id ?? 0))}`
    );

    if (comp?.pagination && typeof comp.pagination === 'object') {
      const sourceLignesFile = describeLignesFile(comp.pagination?.source || {});
      const targetLignesFile = describeLignesFile(comp.pagination?.target || {});
      if (sourceLignesFile || targetLignesFile) {
        const parts = [];
        if (sourceLignesFile) parts.push(`<strong>_lignes source</strong> ${sourceLignesFile}`);
        if (targetLignesFile) parts.push(`<strong>_lignes cible</strong> ${targetLignesFile}`);
        pushLine(parts.join(' · '));
      }
    }

    const runtime = formatDuration(comp.medite_runtime_ms);
    if (runtime) {
      pushLine(`<strong>Durée Medite</strong> ${runtime}`);
    }

    const peakKb = Number(comp.medite_peak_rss_kb);
    if (Number.isFinite(peakKb) && peakKb > 0) {
      pushLine(`<strong>Pic mémoire</strong> ${formatBytes(peakKb * 1024)}`);
    }

    pushLine(`<strong>Export legacy</strong> ${describeExportStatus(comp)}`);

    if (detailsLoaded) {
      const missing = Array.isArray(comp?.publish_missing) ? comp.publish_missing : [];
      const availableComponents = Math.max(0, 6 - missing.length);
      pushLine(`<strong>Fichiers XHTML</strong> ${formatNumber(availableComponents)}/6`);
    }

    return `<div class="comparison-results comparison-data-col">${lines.join('')}</div>`;
  }

  function renderResultsSummary(comp, { isRunning = false, detailsLoaded = false } = {}) {
    const progress = comp?.comparison_progress || null;
    const missing = Array.isArray(comp?.publish_missing) ? comp.publish_missing : [];
    const lines = [];

    const pushLine = (html, variant = '') => {
      const cls = variant ? ` comparison-results-line--${variant}` : '';
      lines.push(`<div class="comparison-results-line${cls}">${html}</div>`);
    };

    if (!detailsLoaded) {
      pushLine('Chargement…', 'muted');
    }

    if (missing.length > 0) {
      pushLine('<span class="badge text-bg-warning text-dark">Composants manquants</span>');
    }

    if (isRunning) {
      pushLine('<span class="comparison-running-spinner" aria-hidden="true"></span><strong>Alignement Medite en cours</strong>', 'running');
      const elapsed = formatElapsedSince(comp.created_at);
      if (elapsed) {
        pushLine(`<strong>Temps écoulé</strong> ${elapsed}`, 'running');
      }
    }

    if (progress && typeof progress === 'object') {
      const roles = Object.values(progress.roles || {});
      const statuses = roles
        .map(role => normalizeStatus(role?.status ?? ''))
        .filter(Boolean);

      if (statuses.length) {
        const hasRunning = statuses.some(status => status === 'queued' || status === 'running');
        const hasFailed = statuses.some(status => status === 'failed');
        const allDone = statuses.every(status => ['done', 'ok'].includes(status));

        if (hasRunning) {
          pushLine('<span class="comparison-running-spinner" aria-hidden="true"></span><strong>Pagination en cours</strong>', 'running');
        } else if (hasFailed) {
          pushLine('<strong>Pagination</strong> échec');
        } else if (allDone) {
          pushLine('<strong>Pagination</strong> injectée');
        }
      }
    }

    if (!lines.length) {
      return '';
    }

    return `<div class="comparison-results comparison-results-details">${lines.join('')}</div>`;
  }

  function renderCompactProcessStatus(comp, { isRunning = false } = {}) {
    if (comp?.is_legacy) {
      return '';
    }

    const progress = comp?.comparison_progress || null;
    const statuses = Object.values(progress?.roles || {})
      .map(role => normalizeStatus(role?.status ?? ''))
      .filter(Boolean);

    if (isRunning) {
      return '<div class="comparison-results-compact"><span class="comparison-running-spinner" aria-hidden="true"></span><strong>Alignement…</strong></div>';
    }

    if (statuses.length) {
      const hasRunning = statuses.some(status => status === 'queued' || status === 'running');
      if (hasRunning) {
        return '<div class="comparison-results-compact"><span class="comparison-running-spinner" aria-hidden="true"></span><strong>Pagination…</strong></div>';
      }
    }

    return '';
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
            loadComparisons(currentWorkId, { force: true });
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

  async function buildSidecarFromXhtml(comp, { role = null, button, feedback, onSuccess } = {}) {
    const originalLabel = button ? button.textContent : '';
    if (button) {
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }
    if (feedback) {
      feedback.textContent = 'Génération du sidecar depuis les balises <pb>…';
    }

    try {
      const payload = role ? { role } : {};
      const res = await fetch(withBasePath(`/api/comparisons/${comp.id}/pagination/from-xhtml`), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(CSRF_TOKEN ? { 'X-CSRF-TOKEN': CSRF_TOKEN } : {})
        },
        body: JSON.stringify(payload),
      });

      const text = await res.text();
      let responsePayload = {};
      try { responsePayload = JSON.parse(text); } catch { responsePayload = { raw: text }; }

      if (!res.ok) {
        const message = responsePayload.message || responsePayload.error || responsePayload.raw || `HTTP ${res.status}`;
        throw new Error(message);
      }

      if (feedback) {
        feedback.textContent = responsePayload.message || 'Sidecar généré depuis le XHTML.';
      }
      if (typeof onSuccess === 'function') {
        onSuccess(responsePayload);
      }
    } catch (err) {
      console.error('Sidecar generation failed', err);
      if (feedback) {
        feedback.textContent = `Erreur lors de la génération du sidecar : ${err.message}`;
      }
      alert(err.message || 'Impossible de générer le sidecar.');
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = originalLabel || 'Créer le sidecar (pb)';
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

      // Refresh comparison list so pagination marker counts / availability reflect restored state
      if (typeof loadComparisons === 'function' && isValidWorkId(currentWorkId)) {
        loadComparisons(currentWorkId, { force: true });
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

  // Initial reset for counts with no selection
  updateComparisonCounts(0, 0);
  updateComparisonSummaryState('idle', { total: 0, published: 0 });

  function resetUI() {
    loading.style.display = 'none';
    noComparisons.textContent = `Aucune comparaison n'a encore été établie pour cette œuvre. Cliquez sur "Lancer un alignement" pour lancer une comparaison Medite.`;
    noComparisons.classList.remove('text-danger');
    noComparisons.classList.add('text-muted');
    noComparisons.style.display = 'none';
    setComparisonsTableVisible(false);
    tbody.innerHTML = '';
    exportStatusPollers.forEach((timerId) => window.clearInterval(timerId));
    exportStatusPollers.clear();
    comparisonRoleComponents.clear();
    comparisonData.clear();
    comparisonRows.clear();
    comparisonDetailsRequests.clear();
    loadedComparisonsWorkId = null;
    updateComparisonCounts(0, 0);
    updateComparisonSummaryState('idle', { total: 0, published: 0 });
    setComparisonsLoading(false);
  }

  // Track current author/work folders from the global selector
  document.addEventListener('workSelected', e => {
    currentAuthorId = e.detail?.authorId ?? null;
    currentAuthorFolder = e.detail?.author_folder || '';
    currentWorkFolder   = e.detail?.work_folder || '';
  });

  function buildComparisonRow(comp) {
    const tr = document.createElement('tr');
    tr.dataset.id = comp.id;

    const detailsLoaded = comp.details_loaded === true
      || Array.isArray(comp.publish_missing)
      || typeof comp.components_ready === 'boolean';
    comp.details_loaded = detailsLoaded;

    const missing = Array.isArray(comp.publish_missing) ? comp.publish_missing : [];
    const ready = detailsLoaded ? (comp.components_ready && missing.length === 0) : false;
    const isLegacy = !!comp.is_legacy;
    tr.dataset.legacy = isLegacy ? '1' : '0';
    const serverCanManage = typeof comp?.can_manage === 'boolean' ? comp.can_manage : null;
    const ownershipBlocked = !currentUserIsAdmin
      && (serverCanManage === null
        ? (!!currentUserId && Number(comp?.created_by) !== Number(currentUserId))
        : !serverCanManage);
    comp._ownershipBlocked = ownershipBlocked;
    const manageDisabledNote = isLegacy
      ? 'Comparaison legacy en lecture seule.'
      : ownershipNote;
    const scope = comp.publication_scope || (isLegacy ? 'prod' : null);
    const inferredScope = scope || ((comp?.published === true) ? 'prod' : null);
    const isPublished = !!inferredScope;
    const isPublishedProd = inferredScope === 'prod';
    const isPublishedDev = inferredScope === 'dev';
    const isPublicationPending = !!(comp?.publication_pending && typeof comp.publication_pending === 'object');
    tr.dataset.published = isPublished ? '1' : '0';
    tr.dataset.publishDest = comp.publish_dest || '';
    const isRunning = runningComparisons.has(Number(comp.id));

    const xmlUrl     = withBasePath(`/storage/uploads/comparisons/${comp.id}.xml`);
    const legacyUrl = (function() {
      if (!currentAuthorFolder || !currentWorkFolder || !comp.folder) return null;
      const origin = window.location.origin;
      return `${origin}/${currentAuthorFolder}/${currentWorkFolder}/comparaison/${comp.folder}`;
    })();
    const devUrl = (function() {
      if (isLegacy) return null;
      if (!currentAuthorFolder || !currentWorkFolder || !comp?.id) return null;
      const origin = window.location.origin;
      return `${origin}/dev/${currentAuthorFolder}/${currentWorkFolder}/comparaison/${comp.id}`;
    })();
    const editorUrl = typeof withBasePath === 'function'
      ? withBasePath(`/comparison/${comp.id}/editor`)
      : `/comparison/${comp.id}/editor`;

    const sourceName = comp.source_version?.name ?? `Version ${comp.source_id}`;
    const targetName = comp.target_version?.name ?? `Version ${comp.target_id}`;
    const counts = getMediteComponentCounts(comp);
    const runningPlaceholder = '<span class="text-muted">-</span>';
    const folderText = comp.folder || '';
    const folderHtml = folderText
      ? `<strong>${folderText}</strong>`
      : '<span class="text-muted">—</span>';

    comparisonData.set(comp.id, comp);
    const mediteParamsHtml = renderMediteParams(comp, { detailsLoaded });
    const dataSummaryHtml = renderComparisonDataSummary(comp, { detailsLoaded });
    const resultsHtml = renderResultsSummary(comp, { isRunning, detailsLoaded });
    const compactStatusHtml = renderCompactProcessStatus(comp, { isRunning });
    const scopeName = `publish-scope-${comp.id}`;
    const defaultScope = scope || (isLegacy ? 'prod' : 'dev');
    const publishDisabled = isPublicationPending || !detailsLoaded || !comp.publish_source || isLegacy || ownershipBlocked;
    const scopeDisabled = isPublicationPending || !detailsLoaded || isLegacy || ownershipBlocked;
    const editAllowed = detailsLoaded && !isRunning && ready && !isPublished && !isLegacy;
    const editDisabled = editAllowed && ownershipBlocked;
    const deleteDisabled = isLegacy || ownershipBlocked;
    const commentDisabled = isLegacy || ownershipBlocked || isPublicationPending;
    const normalizedComment = normalizeComment(comp.comments);
    const hasComments = normalizedComment !== null;
    const chapterCount = Number(comp.chapter_count ?? 0);
    const hasChapters = chapterCount > 0;
    const commentTitle = hasComments
      ? 'Voir ou modifier le commentaire'
      : 'Ajouter un commentaire';
    const chaptersTitle = hasChapters
      ? `Voir les chapitres (${chapterCount})`
      : 'Ouvrir le panneau des chapitres';
    const commentButtonHtml = `<button type="button"
            class="btn ${hasComments ? 'btn-primary comparison-comment-btn--filled' : 'btn-outline-secondary'} comparison-comment-btn ${commentDisabled ? 'legacy-disabled' : ''}"
            data-comparison-comment="1"
            data-id="${comp.id}"
            title="${escapeHtml(commentTitle)}"
            ${commentDisabled ? 'disabled aria-disabled="true"' : ''}>
          <i class="bi ${hasComments ? 'bi-chat-left-text-fill' : 'bi-chat-left-text'}" aria-hidden="true"></i>
          <span class="visually-hidden">${escapeHtml(commentTitle)}</span>
        </button>`;
    const chaptersButtonHtml = `<button type="button"
            class="btn ${hasChapters ? 'btn-primary comparison-chapters-btn--filled' : 'btn-outline-secondary'} comparison-chapters-btn"
            data-comparison-chapters="1"
            data-id="${comp.id}"
            title="${escapeHtml(chaptersTitle)}">
          <i class="bi ${hasChapters ? 'bi-list-ul' : 'bi-list'}" aria-hidden="true"></i>
          <span class="visually-hidden">${escapeHtml(chaptersTitle)}</span>
        </button>`;

    const xmlAvailable = comp.xml_available === true;
    const xmlUnknown = comp.xml_available === null || comp.xml_available === undefined;
    const xmlActionHtml = xmlAvailable
      ? `<a href="${xmlUrl}" class="btn btn-outline-primary comparison-action-btn" target="_blank"><i class="bi bi-filetype-xml"></i></a>`
      : (xmlUnknown
        ? `<span class="btn btn-outline-secondary disabled comparison-action-btn" tabindex="-1" aria-disabled="true"><i class="bi bi-filetype-xml"></i></span>`
        : `<span class="btn btn-outline-secondary disabled comparison-action-btn" tabindex="-1" aria-disabled="true"><i class="bi bi-filetype-xml"></i></span>`);

    const publishStatusHtml = isRunning
      ? runningPlaceholder
      : renderPublishStatusHtml(comp, {
          detailsLoaded,
          isLegacy,
          isPublished,
          isPublishedProd,
        });
    const publishControlsHtml = isRunning
      ? runningPlaceholder
      : `<div class="publish-control">
          <button type="button"
                  class="btn btn-sm ${isPublished ? 'btn-outline-secondary' : 'btn-primary'} publish-action-btn ${publishDisabled ? 'legacy-disabled' : ''}"
                  data-id="${comp.id}"
                  data-published="${isPublished ? '1' : '0'}"
                  data-missing='${JSON.stringify(comp.publish_missing ?? [])}'
                  data-source='${comp.publish_source ?? ''}'
                  data-scope="${scope || ''}"
                  ${publishDisabled ? 'disabled aria-disabled="true"' : ''}>
            ${isPublished ? 'Dépublier' : 'Publier'}
          </button>
          <div class="btn-group btn-group-sm publish-scope" role="group" aria-label="Destination de publication">
            <input type="radio" class="btn-check publish-scope-toggle"
                   name="${scopeName}" id="${scopeName}-prod" value="prod"
                   data-id="${comp.id}" ${defaultScope === 'prod' ? 'checked' : ''}
                   ${scopeDisabled ? 'disabled' : ''}>
            <label class="btn btn-outline-secondary" for="${scopeName}-prod">prod</label>
            <input type="radio" class="btn-check publish-scope-toggle"
                   name="${scopeName}" id="${scopeName}-dev" value="dev"
                   data-id="${comp.id}" ${defaultScope === 'dev' ? 'checked' : ''}
                   ${scopeDisabled ? 'disabled' : ''}>
            <label class="btn btn-outline-secondary" for="${scopeName}-dev">/dev</label>
          </div>
        </div>`;
    const reorderDisabled = isLegacy || ownershipBlocked || isPublicationPending;
    const reorderControlsHtml = isRunning
      ? ''
      : `<div class="comparison-action-bar comparison-reorder-bar" role="group" aria-label="Ordre des comparaisons">
          <button class="btn btn-outline-secondary comparison-action-btn comparison-reorder-btn ${reorderDisabled ? 'legacy-disabled' : ''}"
                  data-id="${comp.id}"
                  data-direction="up"
                  title="Monter la comparaison"
                  ${reorderDisabled ? 'disabled aria-disabled="true"' : ''}>
            <span aria-hidden="true">▲</span>
          </button>
          <button class="btn btn-outline-secondary comparison-action-btn comparison-reorder-btn ${reorderDisabled ? 'legacy-disabled' : ''}"
                  data-id="${comp.id}"
                  data-direction="down"
                  title="Descendre la comparaison"
                  ${reorderDisabled ? 'disabled aria-disabled="true"' : ''}>
            <span aria-hidden="true">▼</span>
          </button>
        </div>`;
    const actionBarHtml = isRunning
      ? ''
      : `<div class="comparison-action-bar" role="group" aria-label="Action buttons">
          ${(legacyUrl && (isPublishedProd || isLegacy)) ? `<a href="${legacyUrl}" class="btn btn-outline-success comparison-action-btn" target="_blank"><i class="bi bi-eye"></i></a>` : ''}
          ${(devUrl && isPublishedDev && !isLegacy) ? `<a href="${devUrl}" class="btn btn-outline-info comparison-action-btn" target="_blank"><i class="bi bi-eye"></i></a>` : ''}
          ${editAllowed
            ? (editDisabled
              ? `<span class="btn btn-outline-secondary comparison-action-btn legacy-disabled" aria-disabled="true"><i class="bi bi-pencil-square"></i></span>`
              : `<a href="${editorUrl}" class="btn btn-outline-primary comparison-action-btn"><i class="bi bi-pencil-square"></i></a>`)
            : ''}
          ${xmlActionHtml}
          ${renderExportAction(comp)}
          <button class="btn btn-outline-danger comparison-action-btn delete-comparison-btn ${deleteDisabled ? 'legacy-disabled' : ''}"
                  data-id="${comp.id}"
                  ${deleteDisabled ? 'disabled aria-disabled="true"' : ''}>
            <i class="bi bi-trash3"></i>
          </button>
        </div>`;

    tr.innerHTML = `
      <td class="text-center comparison-order-cell">
        ${reorderControlsHtml}
      </td>
      <td class="text-center comparison-comment-cell">
        ${isRunning ? '' : commentButtonHtml}
      </td>
      <td class="text-center comparison-chapters-cell">
        ${chaptersButtonHtml}
      </td>
      <td class="comparison-folder-cell">${folderHtml}</td>
      <td class="source-cell comparison-source-cell">
        <div><strong class="comparison-version-label" title="${escapeHtml(sourceName)}">${escapeHtml(sourceName)}</strong></div>
        <div class="role-wrapper"></div>
      </td>
      <td class="target-cell comparison-target-cell">
        <div><strong class="comparison-version-label" title="${escapeHtml(targetName)}">${escapeHtml(targetName)}</strong></div>
        <div class="role-wrapper"></div>
      </td>
      <td class="align-top comparison-params-cell">${mediteParamsHtml}</td>
      <td class="align-top comparison-data-col comparison-data-cell">${dataSummaryHtml}</td>
      <td>${isRunning ? runningPlaceholder : renderMetricCell(counts.s)}</td>
      <td>${isRunning ? runningPlaceholder : renderMetricCell(counts.i)}</td>
      <td>${isRunning ? runningPlaceholder : renderMetricCell(counts.r)}</td>
      <td>${isRunning ? runningPlaceholder : renderMetricCell(counts.d)}</td>
      <td class="text-center comparison-publish-cell">
        ${publishStatusHtml}
      </td>
      <td class="text-center comparison-manage-cell">
        ${publishControlsHtml}
      </td>
      <td class="text-center comparison-action-cell">
        <div class="d-flex flex-column align-items-center gap-1">
          ${compactStatusHtml}
          <div class="comparison-results-slot">${resultsHtml}</div>
          ${actionBarHtml}
        </div>
      </td>
    `;

    const sourceWrapper = tr.querySelector('.source-cell .role-wrapper');
    if (sourceWrapper) {
      const roleComponent = renderComparisonRole(comp, 'source', sourceName);
      sourceWrapper.appendChild(roleComponent.element);
      comparisonRoleComponents.set(`${comp.id}:source`, {
        comp,
        component: roleComponent,
      });
    }
    const targetWrapper = tr.querySelector('.target-cell .role-wrapper');
    if (targetWrapper) {
      const roleComponent = renderComparisonRole(comp, 'target', targetName);
      targetWrapper.appendChild(roleComponent.element);
      comparisonRoleComponents.set(`${comp.id}:target`, {
        comp,
        component: roleComponent,
      });
    }

    const exportStatus = normalizeExportBundle(comp).status || 'idle';
    if (['queued', 'running'].includes(exportStatus)) {
      ensureExportStatusPolling(comp.id);
    } else {
      stopExportStatusPolling(comp.id);
    }

    return tr;
  }

  async function loadComparisonDetails(comparisonId) {
    const id = Number(comparisonId);
    if (!Number.isFinite(id)) return;
    if (comparisonDetailsRequests.has(id)) return;
    const request = fetch(withBasePath(`/comparisons/${id}/details`), { headers: JSON_HEADERS })
      .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then(data => {
        const existing = comparisonData.get(id) || {};
        const merged = { ...existing, ...data, details_loaded: true };
        comparisonData.set(id, merged);
        const newRow = buildComparisonRow(merged);
        const oldRow = comparisonRows.get(id);
        if (oldRow && oldRow.parentNode) {
          oldRow.parentNode.replaceChild(newRow, oldRow);
        } else {
          tbody.appendChild(newRow);
        }
        comparisonRows.set(id, newRow);
        refreshComparisonReorderButtons();
        refreshComparisonCounts();
      })
      .catch(err => {
        console.error('Erreur chargement comparaison', err);
      })
      .finally(() => {
        comparisonDetailsRequests.delete(id);
      });
    comparisonDetailsRequests.set(id, request);
  }

  async function loadComparisons(workId, { force = false } = {}) {
    if (!isValidWorkId(workId)) { resetUI(); return; }
    if (!force && String(loadedComparisonsWorkId ?? '') === String(workId)) {
      return;
    }
    currentWorkId = workId;

    const requestToken = ++latestComparisonsRequest;
    activeComparisonsRequest = requestToken;

    setComparisonsLoading(true);
    updateComparisonSummaryState('loading', { total: 0, published: 0 });
    loading.style.display = 'block';
    tbody.innerHTML = '';
    setComparisonsTableVisible(false);
    comparisonRoleComponents.clear();
    comparisonData.clear();
    comparisonRows.clear();
    comparisonDetailsRequests.clear();
    pendingRoleStatuses.clear();
    noComparisons.textContent = `Aucune comparaison n'a encore été établie pour cette œuvre. Cliquez sur "Lancer un alignement" pour lancer une comparaison Medite.`;
    noComparisons.classList.remove('text-danger');
    noComparisons.classList.add('text-muted');
    noComparisons.style.display = 'none';

    initComparisonsTable.initializedTooltips?.forEach(tooltip => tooltip.dispose());
    initComparisonsTable.initializedTooltips = [];

    try {
      const res = await fetch(withBasePath(`/comparisons/by-work?work_id=${workId}&light=1`), { headers: JSON_HEADERS });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const comparisons = await res.json();

      if (requestToken !== activeComparisonsRequest) {
        return;
      }

      const totalCount = Array.isArray(comparisons) ? comparisons.length : 0;
      const publishedCount = Array.isArray(comparisons)
        ? comparisons.filter(comp => isComparisonPublished(comp)).length
        : 0;

      if (!Array.isArray(comparisons) || comparisons.length === 0) {
        loadedComparisonsWorkId = String(workId);
        updateComparisonCounts(publishedCount, totalCount);
        updateComparisonSummaryState('empty', { total: totalCount, published: publishedCount });
        noComparisons.style.display = 'block';
        setComparisonsTableVisible(false);
        return;
      }

      loadedComparisonsWorkId = String(workId);
      updateComparisonCounts(publishedCount, totalCount);
      updateComparisonSummaryState('ready', { total: totalCount, published: publishedCount });
      setComparisonsTableVisible(true);

      let renderedCount = 0;
      comparisons.forEach(comp => {
        try {
          const row = buildComparisonRow(comp);
          tbody.appendChild(row);
          comparisonRows.set(comp.id, row);
          renderedCount += 1;
          if (comp.details_loaded === false) {
            loadComparisonDetails(comp.id);
          }
        } catch (err) {
          console.error('Erreur rendu comparaison', comp?.id, err, comp);
        }
      });

      refreshComparisonReorderButtons();

      if (renderedCount === 0) {
        loadedComparisonsWorkId = null;
        updateComparisonSummaryState('empty', { total: 0, published: 0 });
        noComparisons.textContent = 'Erreur d\'affichage des comparaisons. Consultez la console.';
        noComparisons.classList.remove('text-muted');
        noComparisons.classList.add('text-danger');
        noComparisons.style.display = 'block';
        setComparisonsTableVisible(false);
        return;
      }
    } catch (err) {
      console.error('Erreur lors du chargement des comparaisons:', err);
      loadedComparisonsWorkId = null;
      updateComparisonCounts(0, 0);
      updateComparisonSummaryState('empty', { total: 0, published: 0 });
      noComparisons.textContent = `Chargement des comparaisons impossible : ${err.message || 'erreur inconnue'}.`;
      noComparisons.classList.remove('text-muted');
      noComparisons.classList.add('text-danger');
      noComparisons.style.display = 'block';
      setComparisonsTableVisible(false);
    } finally {
      if (requestToken === activeComparisonsRequest) {
        loading.style.display = 'none';
      }
      setComparisonsLoading(false);
    }
  }

  function refreshRunningComparisonIndicators() {
    comparisonData.forEach((comp, comparisonId) => {
      const numericId = Number(comparisonId);
      if (!runningComparisons.has(numericId)) return;
      const row = comparisonRows.get(numericId);
      if (!row) return;
      const paramsCell = row.querySelector('.comparison-params-cell');
      const resultsCell = row.querySelector('.comparison-action-cell');
      if (paramsCell) {
        paramsCell.innerHTML = renderMediteParams(comp, { detailsLoaded: !!comp.details_loaded });
      }
      if (resultsCell) {
        const compactStatus = resultsCell.querySelector('.comparison-results-compact');
        if (compactStatus) {
          compactStatus.outerHTML = renderCompactProcessStatus(comp, { isRunning: true }) || '<div class="comparison-results-compact"></div>';
        }
        const slot = resultsCell.querySelector('.comparison-results-slot');
        if (slot) {
          slot.innerHTML = renderResultsSummary(comp, { isRunning: true, detailsLoaded: !!comp.details_loaded });
        }
      }
    });
  }

  // React to global events, but let loadComparisons() guard invalid IDs
  document.addEventListener('workSelected', e => {
    runningComparisons.clear();
    currentWorkId = e.detail?.workId ?? null;
    if (!isValidWorkId(currentWorkId)) {
      resetUI();
    }
  });

  document.addEventListener('editorialStepChanged', e => {
    const step = Number(e.detail?.step);
    if (step !== 3) return;
    loadComparisons(e.detail?.workId ?? currentWorkId);
  });

  document.addEventListener('comparisonCreated', e => {
    const comparisonId = Number(e.detail?.comparisonId);
    if (!Number.isFinite(comparisonId)) return;
    runningComparisons.add(comparisonId);

    if (e.detail?.workId && String(e.detail.workId) !== String(currentWorkId)) {
      return;
    }

    if (comparisonRows.has(comparisonId)) {
      const existing = comparisonData.get(comparisonId) || {};
      const updated = { ...existing };
      comparisonData.set(comparisonId, updated);
      const row = buildComparisonRow(updated);
      const oldRow = comparisonRows.get(comparisonId);
      if (oldRow && oldRow.parentNode) {
        oldRow.parentNode.replaceChild(row, oldRow);
      }
      comparisonRows.set(comparisonId, row);
      return;
    }

    const placeholderComp = {
      id: comparisonId,
      folder: e.detail?.folder || '',
      source_id: e.detail?.sourceId ?? null,
      target_id: e.detail?.targetId ?? null,
      source_version: { name: e.detail?.sourceName || `Version ${e.detail?.sourceId ?? ''}`.trim() },
      target_version: { name: e.detail?.targetName || `Version ${e.detail?.targetId ?? ''}`.trim() },
      details_loaded: false,
      components_ready: false,
      publish_missing: [],
      pagination: { source: {}, target: {} },
      medite_component_counts: {},
      comparison_progress: null,
      published: false,
      publication_scope: null,
      publish_source: null,
      xml_available: null,
      export_bundle: { status: 'idle' },
      created_by: currentUserId,
    };

    const row = buildComparisonRow(placeholderComp);
    noComparisons.textContent = `Aucune comparaison n'a encore été établie pour cette œuvre. Cliquez sur "Lancer un alignement" pour lancer une comparaison Medite.`;
    noComparisons.classList.remove('text-danger');
    noComparisons.classList.add('text-muted');
    noComparisons.style.display = 'none';
    setComparisonsTableVisible(true);
    tbody.prepend(row);
    comparisonRows.set(comparisonId, row);
    comparisonData.set(comparisonId, placeholderComp);
    refreshComparisonReorderButtons();
    refreshComparisonCounts();
  });

  document.addEventListener('comparisonReady', e => {
    if (e.detail?.comparisonId) {
      runningComparisons.delete(Number(e.detail.comparisonId));
    }
    loadComparisons(e.detail?.workId, { force: true });
  });

  document.addEventListener('comparisonCompleted', e => {
    // Wait for the final refresh triggered after pagination polling completes.
  });

  document.addEventListener('comparisonFailed', e => {
    if (e.detail?.comparisonId) {
      runningComparisons.delete(Number(e.detail.comparisonId));
    }
    loadComparisons(e.detail?.workId, { force: true });
  });

  document.addEventListener('comparisonDeleted', e => {
    if (e.detail?.comparisonId) {
      runningComparisons.delete(Number(e.detail.comparisonId));
    }
    loadComparisons(e.detail?.workId, { force: true });
  });

  document.addEventListener('versionsUpdated',   e => loadComparisons(e.detail?.workId, { force: true }));
  document.addEventListener('refreshComparisons', () => loadComparisons(currentWorkId, { force: true }));
  window.setInterval(() => {
    if (runningComparisons.size === 0) return;
    refreshRunningComparisonIndicators();
  }, 1000);

  document.addEventListener('comparisonManifestUpdated', e => {
    const detail = e.detail || {};
    const comparisonId = Number(detail.comparisonId ?? detail.id);
    if (!Number.isFinite(comparisonId)) {
      return;
    }
    const role = detail.role === 'target' ? 'target' : 'source';

    if (detail.workId && currentWorkId && Number(detail.workId) !== Number(currentWorkId)) {
      return;
    }

    const key = `${comparisonId}:${role}`;
    const entry = comparisonRoleComponents.get(key);
    if (!entry) {
      if (!detail.workId || Number(detail.workId) === Number(currentWorkId)) {
        loadComparisons(currentWorkId, { force: true });
      }
      return;
    }

    const comp = entry.comp || comparisonData.get(comparisonId) || {};
    comparisonData.set(comparisonId, comp);

    comp.manifests = comp.manifests || {};
    const manifestDetail = detail.manifest && typeof detail.manifest === 'object'
      ? { ...detail.manifest }
      : {};
    if (typeof detail.count === 'number' && !Number.isNaN(detail.count)) {
      manifestDetail.count = detail.count;
    }
    if (!('exists' in manifestDetail)) {
      manifestDetail.exists = true;
    }

    const previous = comp.manifests[role] || {};
    const updatedManifest = {
      ...previous,
      ...manifestDetail,
    };

    if (!updatedManifest.api_url) {
      updatedManifest.api_url = previous.api_url
        || (typeof withBasePath === 'function'
          ? withBasePath(`/comparisons/${comparisonId}/manifests/${role}`)
          : `/comparisons/${comparisonId}/manifests/${role}`);
    }
    if (!updatedManifest.url && previous.url) {
      updatedManifest.url = previous.url;
    }

    comp.manifests[role] = updatedManifest;

    if (entry.component?.updateManifest) {
      entry.component.updateManifest(updatedManifest);
    }
  });

  const getSelectedScope = (row) => {
    if (!row) return '';
    const selected = row.querySelector('.publish-scope-toggle:checked');
    return selected ? selected.value : '';
  };

  const setSelectedScope = (row, scope) => {
    if (!row || !scope) return;
    const input = row.querySelector(`.publish-scope-toggle[value="${scope}"]`);
    if (input) input.checked = true;
  };

  document.addEventListener('click', async event => {
    const commentBtn = event.target.closest('[data-comparison-comment="1"]');
    if (commentBtn) {
      if (commentBtn.disabled || commentBtn.getAttribute('aria-disabled') === 'true') return;
      const comparisonId = commentBtn.dataset.id;
      if (!comparisonId) return;
      openCommentModal(comparisonId);
      return;
    }

    const chaptersBtn = event.target.closest('[data-comparison-chapters="1"]');
    if (chaptersBtn) {
      event.preventDefault();
      event.stopPropagation();
      const comparisonId = chaptersBtn.dataset.id;
      if (!comparisonId) return;
      openChaptersPanel(comparisonId);
      return;
    }

    const actionBtn = event.target.closest('.publish-action-btn');
    if (!actionBtn) return;
    if (actionBtn.disabled || actionBtn.getAttribute('aria-disabled') === 'true') return;

    const comparisonId = actionBtn.dataset.id;
    if (!comparisonId) return;

    const row = actionBtn.closest('tr');
    const shouldPublish = actionBtn.dataset.published !== '1';
    actionBtn.disabled = true;

    const sourceDir = actionBtn.dataset.source || '';
    const scope = getSelectedScope(row);
    let knownMissing = [];
    try {
      knownMissing = JSON.parse(actionBtn.dataset.missing || '[]');
    } catch {
      knownMissing = [];
    }

    if (shouldPublish && !scope) {
      alert('Choisissez une destination de publication (/ ou /dev) avant de publier.');
      actionBtn.disabled = false;
      return;
    }

    if (shouldPublish && !sourceDir) {
      alert('Les fichiers Medite ne sont pas encore disponibles pour cette comparaison. Exécutez Medite avant de publier.');
      actionBtn.disabled = false;
      return;
    }

    let insertDefaultMarker = false;
    if (shouldPublish) {
      const comp = comparisonData.get(Number(comparisonId)) || {};
      const sourceMarkers = Number(comp?.pagination?.source?.markers ?? 0);
      const targetMarkers = Number(comp?.pagination?.target?.markers ?? 0);
      if (sourceMarkers <= 0 && targetMarkers <= 0) {
        const choice = await getPaginationWarningChoice();
        if (choice === 'insert') {
          insertDefaultMarker = true;
        } else if (choice === 'continue') {
          insertDefaultMarker = false;
        } else {
          actionBtn.disabled = false;
          return;
        }
      }
    }

    if (shouldPublish && Array.isArray(knownMissing) && knownMissing.length) {
      const proceed = confirm(
        'Certains composants Medite semblent manquants :\n- ' +
        knownMissing.join('\n- ') +
        '\n\nVoulez-vous publier malgré tout ?'
      );
      if (!proceed) {
        actionBtn.disabled = false;
        return;
      }
    }

    try {
      if (shouldPublish) {
        updateComparisonRow(comparisonId, {
          publication_pending: { label: `Publication ${scope}…` },
        });
        const res = await fetch(withBasePath('/api/publish_xhtml'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            comparison_id: comparisonId,
            destination: scope,
            insert_default_marker: insertDefaultMarker
          })
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

        const publishWarnings = formatPublishWarnings(data.warnings);
        if (publishWarnings.length) {
          alert(
            'Publication effectuée avec avertissement(s)\n- ' +
            publishWarnings.join('\n- ')
          );
        }

        if (insertDefaultMarker && data.default_marker && Number(data.default_marker.inserted ?? 0) === 0) {
          alert(
            'Aucun marqueur par défaut n\'a été inséré. ' +
            'Vérifiez que des fac-similés sont bien disponibles pour cette comparaison.'
          );
        }
        updateComparisonRow(comparisonId, {
          publication_scope: scope,
          published: true,
          publication_pending: null,
          publish_missing: Array.isArray(data.missing_files) ? data.missing_files : (comparisonData.get(Number(comparisonId))?.publish_missing ?? []),
          components_ready: Array.isArray(data.missing_files) ? data.missing_files.length === 0 : (comparisonData.get(Number(comparisonId))?.components_ready ?? null),
        });

      } else {
        updateComparisonRow(comparisonId, {
          publication_pending: { label: 'Dépublication…' },
        });
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
        updateComparisonRow(comparisonId, {
          publication_scope: null,
          published: false,
          publication_pending: null,
          publish_dest: null,
        });
      }

      document.dispatchEvent(new CustomEvent('publicationCountsChanged'));

    } catch (err) {
      console.error(err);
      updateComparisonRow(comparisonId, { publication_pending: null });
      alert((err && err.message) ? err.message : 'Erreur lors de la mise à jour de la publication');
    } finally {
      actionBtn.disabled = false;
    }
  });

  document.addEventListener('change', async event => {
    if (event.target === detailsToggle) {
      showComparisonDetails = !!detailsToggle.checked;
      applyComparisonDetailsMode();
      return;
    }

    const scopeToggle = event.target.closest('.publish-scope-toggle');
    if (!scopeToggle) return;

    const row = scopeToggle.closest('tr');
    const publishAction = row?.querySelector('.publish-action-btn');
    if (!publishAction || publishAction.dataset.published !== '1') {
      return;
    }

    const comparisonId = publishAction.dataset.id;
    const previousScope = publishAction.dataset.scope || '';
    const nextScope = scopeToggle.value;
    if (!comparisonId || nextScope === previousScope) {
      return;
    }

    const proceed = confirm('Changer la destination de publication ? La comparaison sera republiée.');
    if (!proceed) {
      setSelectedScope(row, previousScope || 'prod');
      return;
    }

    publishAction.disabled = true;

    try {
      updateComparisonRow(comparisonId, {
        publication_pending: { label: `Publication ${nextScope}…` },
      });
      const res = await fetch(withBasePath('/api/publish_xhtml'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ comparison_id: comparisonId, destination: nextScope })
      });

      const text = await res.text();
      let data = {};
      try { data = JSON.parse(text); } catch { data = { raw: text }; }
      if (!res.ok || data.error || data.status !== 'ok') {
        const reason = data.error || data.message || data.raw || `Statut HTTP ${res.status}`;
        throw new Error(`Publication échouée : ${reason}`);
      }

      const publishWarnings = formatPublishWarnings(data.warnings);
      if (publishWarnings.length) {
        alert(
          'Publication effectuée avec avertissement(s)\n- ' +
          publishWarnings.join('\n- ')
        );
      }

      updateComparisonRow(comparisonId, {
        publication_scope: nextScope,
        published: true,
        publication_pending: null,
        publish_missing: Array.isArray(data.missing_files) ? data.missing_files : (comparisonData.get(Number(comparisonId))?.publish_missing ?? []),
        components_ready: Array.isArray(data.missing_files) ? data.missing_files.length === 0 : (comparisonData.get(Number(comparisonId))?.components_ready ?? null),
      });
      document.dispatchEvent(new CustomEvent('publicationCountsChanged'));
    } catch (err) {
      console.error(err);
      updateComparisonRow(comparisonId, { publication_pending: null });
      alert((err && err.message) ? err.message : 'Erreur lors de la mise à jour de la publication');
      setSelectedScope(row, previousScope || 'prod');
    } finally {
      publishAction.disabled = false;
    }
  });

  document.addEventListener('click', async event => {
    const btn = event.target.closest('.comparison-export-btn');
    if (!btn) return;
    if (btn.disabled || btn.getAttribute('aria-disabled') === 'true') return;

    const comparisonId = Number(btn.dataset.comparisonId);
    const queueUrl = btn.dataset.exportQueueUrl || '';
    if (!Number.isFinite(comparisonId) || !queueUrl) return;

    btn.disabled = true;
    updateComparisonRow(comparisonId, {
      export_bundle: {
        ...(comparisonData.get(comparisonId)?.export_bundle || {}),
        status: 'queued',
        message: 'Préparation de l’archive en attente.',
      }
    });

    try {
      const response = await fetch(queueUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': CSRF_TOKEN,
          'Accept': 'application/json',
        }
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload?.message || `HTTP ${response.status}`);
      }

      updateComparisonRow(comparisonId, { export_bundle: payload });
      if (['queued', 'running'].includes(payload?.status || 'idle')) {
        ensureExportStatusPolling(comparisonId);
      }
    } catch (error) {
      console.error('Erreur lors du lancement de l’export legacy:', error);
      updateComparisonRow(comparisonId, {
        export_bundle: {
          ...(comparisonData.get(comparisonId)?.export_bundle || {}),
          status: 'failed',
          message: error?.message || 'Impossible de lancer la préparation du pack legacy.',
        }
      });
      alert(error?.message || 'Impossible de lancer la préparation du pack legacy.');
    } finally {
      btn.disabled = false;
    }
  });

  if (commentSaveBtn && commentInput) {
    commentSaveBtn.addEventListener('click', async () => {
      const comparisonId = Number(activeCommentComparisonId);
      if (!Number.isFinite(comparisonId)) {
        return;
      }

      const comments = normalizeComment(commentInput.value);
      commentSaveBtn.disabled = true;

      try {
        const res = await fetch(withBasePath(`/comparisons/${comparisonId}/comments`), {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(CSRF_TOKEN ? { 'X-CSRF-TOKEN': CSRF_TOKEN } : {}),
          },
          body: JSON.stringify({ comments }),
        });

        const text = await res.text();
        let data = {};
        try { data = JSON.parse(text); } catch { data = { raw: text }; }

        if (!res.ok || data.status !== 'ok') {
          const reason = data.message || data.error || data.raw || `Statut HTTP ${res.status}`;
          throw new Error(`Enregistrement du commentaire impossible : ${reason}`);
        }

        updateComparisonRow(comparisonId, {
          comments: data.comments ?? null,
        });

        const modal = getCommentModal();
        modal?.hide();
      } catch (err) {
        console.error(err);
        alert((err && err.message) ? err.message : 'Erreur lors de l’enregistrement du commentaire');
      } finally {
        commentSaveBtn.disabled = false;
      }
    });
  }

  document.addEventListener('click', async event => {
    const reorderBtn = event.target.closest('.comparison-reorder-btn');
    if (!reorderBtn) return;
    if (reorderBtn.disabled || reorderBtn.getAttribute('aria-disabled') === 'true') {
      return;
    }

    const comparisonId = reorderBtn.dataset.id;
    const direction = reorderBtn.dataset.direction;
    if (!comparisonId || !direction) return;

    const row = reorderBtn.closest('tr');
    const rowButtons = row ? Array.from(row.querySelectorAll('.comparison-reorder-btn')) : [reorderBtn];
    rowButtons.forEach((btn) => { btn.disabled = true; });

    try {
      const response = await fetch(withBasePath(`/comparisons/${comparisonId}/reorder`), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(CSRF_TOKEN ? { 'X-CSRF-TOKEN': CSRF_TOKEN } : {}),
        },
        body: JSON.stringify({ direction }),
      });

      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(data?.message || `HTTP ${response.status}`);
      }

      if (data?.status === 'ok') {
        moveComparisonRowLocally(comparisonId, direction);
      } else {
        refreshComparisonReorderButtons();
      }
    } catch (err) {
      console.error('Erreur de réordonnancement de la comparaison:', err);
      alert(err?.message || 'Impossible de modifier l’ordre des comparaisons.');
    } finally {
      rowButtons.forEach((btn) => { btn.disabled = false; });
    }
  });

  // Delete comparison (event delegation)
  document.addEventListener('click', async event => {
    const btn = event.target.closest('.delete-comparison-btn');
    if (!btn) return;
    if (btn.disabled || btn.getAttribute('aria-disabled') === 'true') {
      return;
    }

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
      const rows = Array.from(tbody.querySelectorAll('tr'));
      const publishedLeft = rows.filter(row => row.dataset.published === '1').length;
      updateComparisonCounts(publishedLeft, rows.length);
      updateComparisonSummaryState(rows.length ? 'ready' : 'empty', { total: rows.length, published: publishedLeft });
      if (!rows.length) noComparisons.style.display = 'block';
      document.dispatchEvent(new CustomEvent('comparisonDeleted', {
        detail: {
          comparisonId: Number(comparisonId),
          workId: currentWorkId,
        }
      }));

    } catch (err) {
      console.error('Erreur lors de la suppression de la comparaison:', err);
      alert(`Suppression impossible : ${err.message || 'erreur inconnue'}.`);
    }
  });
}

window.initComparisonsTable = initComparisonsTable;
initComparisonsTable.initializedTooltips = [];

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initComparisonsTable);
} else {
  initComparisonsTable();
}
