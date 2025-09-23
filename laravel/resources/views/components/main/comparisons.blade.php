<div class="card mb-3">
    <div class="card-header">Comparaisons</div>

    <div class="card-body">
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
          <th>Seuil</th>
          <th>Pivot</th>
          <th>Sens. Casse</th>
          <th>Sens. Diac.</th>
          <th>Composants</th>
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
  const runningComparisons = window.__runningComparisons || new Set();
  window.__runningComparisons = runningComparisons;

  function isValidWorkId(id) {
    const s = String(id ?? '').trim();
    return /^\d+$/.test(s) && Number(s) > 0;
  }

  function resetUI() {
    loading.style.display = 'none';
    noComparisons.style.display = 'none';
    tbody.innerHTML = '';
  }

  async function loadComparisons(workId) {
    if (!isValidWorkId(workId)) { resetUI(); return; }
    currentWorkId = workId;

    loading.style.display = 'block';
    tbody.innerHTML = '';
    noComparisons.style.display = 'none';

    try {
      const res = await fetch(`/comparisons/by-work?work_id=${workId}`, { headers: JSON_HEADERS });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const comparisons = await res.json();

      if (!Array.isArray(comparisons) || comparisons.length === 0) {
        noComparisons.style.display = 'block';
        return;
      }

      comparisons.forEach(comp => {
        const tr = document.createElement('tr');
        tr.dataset.id = comp.id;
        tr.dataset.published = comp.published ? '1' : '0';
        tr.dataset.publishDest = comp.publish_dest || '';

        const missing = Array.isArray(comp.publish_missing) ? comp.publish_missing : [];
        const ready = comp.components_ready && missing.length === 0;
        const published = comp.published;
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

        tr.innerHTML = `
          <td>${comp.id}</td>
          <td>${comp.source_version?.name ?? comp.source_id}</td>
          <td>${comp.target_version?.name ?? comp.target_id}</td>
          <td>${comp.folder ?? ''}</td>
          <td>${comp.ratio ?? ''}</td>
          <td>${comp.seuil ?? ''}</td>
          <td>${comp.lg_pivot ?? ''}</td>
          <td>${comp.case_sensitive ? 'yes' : 'no'}</td>
          <td>${comp.diacri_sensitive ? 'yes' : 'no'}</td>
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
            <a href="/storage/uploads/comparisons/${comp.id}.html" class="btn btn-sm btn-outline-primary" target="_blank">HTML</a>
            <a href="/storage/uploads/comparisons/${comp.id}.xml"  class="btn btn-sm btn-outline-secondary" target="_blank">XML</a>
            <button class="btn btn-sm btn-outline-danger ms-1 delete-comparison-btn" data-id="${comp.id}">🗑️</button>
          </td>
        `;
        tbody.appendChild(tr);
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
        const res = await fetch('/api/publish_xhtml', {
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
        const res = await fetch(`/api/publish_xhtml/${comparisonId}`, {
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
      const response = await fetch(`/comparisons/${comparisonId}`, {
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
