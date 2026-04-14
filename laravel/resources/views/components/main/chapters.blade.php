<div class="card" id="chapters-card">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2 admin-card-heading">
            <span class="admin-card-heading-text">
                <span class="admin-card-title">Chapitres</span>
            </span>
        </div>
        <span class="badge text-bg-primary">Import XLSX</span>
    </div>

    <div class="card-body">
        <div class="chapters-selection" id="chapters-selection-summary">
            Sélectionnez une œuvre pour préparer l’import des chapitres.
        </div>

        <div class="chapters-form-grid">
            <div>
                <label class="form-label" for="chapters-target-select">Cible de comparaison</label>
                <select class="form-select" id="chapters-target-select" disabled>
                    <option value="">Choisir une comparaison…</option>
                </select>
                <div class="form-text" id="chapters-target-help">Les chapitres sont stockés par dossier de comparaison.</div>
            </div>

            <div>
                <label class="form-label" for="chapters-file-input">Fichier XLSX</label>
                <input class="form-control" type="file" id="chapters-file-input" accept=".xlsx" disabled>
                <div class="form-text">Le classeur doit contenir la feuille des chapitres en 2e position.</div>
            </div>
        </div>

        <div class="chapters-actions">
            <button type="button" class="btn btn-outline-primary" id="chapters-preview-btn" disabled>
                Prévisualiser l’import
            </button>
            <button type="button" class="btn btn-primary" id="chapters-commit-btn" disabled>
                Importer les chapitres
            </button>
        </div>

        <div id="chapters-feedback" class="chapters-feedback text-muted">
            Aucun aperçu chargé.
        </div>

        <div id="chapters-preview" class="chapters-preview d-none">
            <div class="chapters-preview-summary" id="chapters-preview-summary"></div>
            <div class="chapters-warning-list d-none" id="chapters-warning-list"></div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Ligne</th>
                            <th>Niveau</th>
                            <th>Libellé</th>
                            <th>Ancre source</th>
                            <th>Ancre cible</th>
                            <th>Parent</th>
                        </tr>
                    </thead>
                    <tbody id="chapters-preview-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
  #chapters-card .card-body {
    display: grid;
    gap: 1rem;
  }
  .chapters-selection {
    padding: 0.7rem 0.85rem;
    border: 1px solid #dee2e6;
    border-radius: 0.55rem;
    background: #f8f9fa;
    font-size: 0.95rem;
    color: #495057;
  }
  .chapters-form-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  }
  .chapters-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
  }
  .chapters-feedback {
    padding: 0.8rem 0.9rem;
    border: 1px solid #e4e8ec;
    border-radius: 0.6rem;
    background: #fff;
    font-size: 0.92rem;
    line-height: 1.45;
  }
  .chapters-feedback.is-error {
    border-color: #f1b8bf;
    background: #fff5f6;
    color: #842029;
  }
  .chapters-feedback.is-success {
    border-color: #b7dfc4;
    background: #f4fcf6;
    color: #1f5d2f;
  }
  .chapters-preview {
    display: grid;
    gap: 0.75rem;
  }
  .chapters-preview-summary {
    padding: 0.75rem 0.9rem;
    border: 1px solid #d9e6f2;
    border-radius: 0.6rem;
    background: #f4f9fd;
    color: #29465e;
    font-size: 0.9rem;
  }
  .chapters-warning-list {
    padding: 0.75rem 0.9rem;
    border: 1px solid #f0d49a;
    border-radius: 0.6rem;
    background: #fff8e8;
    color: #7a5512;
    font-size: 0.88rem;
  }
  .chapters-warning-list ul {
    margin: 0.35rem 0 0;
    padding-left: 1.1rem;
  }
</style>
@endpush

@push('scripts')
<script>
  (function () {
    const summary = document.getElementById('chapters-selection-summary');
    const targetSelect = document.getElementById('chapters-target-select');
    const targetHelp = document.getElementById('chapters-target-help');
    const fileInput = document.getElementById('chapters-file-input');
    const previewBtn = document.getElementById('chapters-preview-btn');
    const commitBtn = document.getElementById('chapters-commit-btn');
    const feedback = document.getElementById('chapters-feedback');
    const previewWrap = document.getElementById('chapters-preview');
    const previewSummary = document.getElementById('chapters-preview-summary');
    const warningList = document.getElementById('chapters-warning-list');
    const previewBody = document.getElementById('chapters-preview-body');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    if (!summary || !targetSelect || !fileInput || !previewBtn || !commitBtn || !feedback || !previewWrap || !previewSummary || !warningList || !previewBody) {
      return;
    }

    let currentWorkId = null;
    let currentTargets = [];
    let previewToken = null;
    let targetsRequestSerial = 0;
    const defaultMessage = 'Sélectionnez une œuvre pour préparer l’import des chapitres.';

    const escapeHtml = (value) => String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

    function setFeedback(message, type = 'neutral') {
      feedback.textContent = message;
      feedback.classList.remove('is-error', 'is-success');
      if (type === 'error') feedback.classList.add('is-error');
      if (type === 'success') feedback.classList.add('is-success');
    }

    function resetPreview() {
      previewToken = null;
      previewBody.innerHTML = '';
      previewSummary.textContent = '';
      warningList.classList.add('d-none');
      warningList.innerHTML = '';
      previewWrap.classList.add('d-none');
      commitBtn.disabled = true;
    }

    function syncPreviewAvailability() {
      previewBtn.disabled = !(currentWorkId && targetSelect.value && fileInput.files?.length);
    }

    function renderTargets(targets) {
      targetSelect.innerHTML = '<option value="">Choisir une comparaison…</option>';
      currentTargets = Array.isArray(targets) ? targets : [];

      currentTargets.forEach((target) => {
        const option = document.createElement('option');
        option.value = String(target.id);
        option.textContent = `${target.label} [${target.folder}]`;
        option.dataset.chapterCount = String(target.chapter_count ?? 0);
        targetSelect.appendChild(option);
      });

      targetSelect.disabled = currentTargets.length === 0;
      fileInput.disabled = currentTargets.length === 0;
      syncPreviewAvailability();

      if (currentTargets.length === 0) {
        targetHelp.textContent = 'Aucune comparaison éditoriale disponible pour cette œuvre.';
      } else {
        targetHelp.textContent = 'Les chapitres seront enregistrés sous le dossier de comparaison sélectionné.';
      }
    }

    async function loadTargets(workId) {
      const requestSerial = ++targetsRequestSerial;
      resetPreview();
      renderTargets([]);
      if (!workId) {
        setFeedback('Aucun aperçu chargé.');
        return;
      }

      setFeedback('Chargement des cibles de chapitres…');

      try {
        const res = await fetch(withBasePath(`/chapters/targets?work_id=${encodeURIComponent(workId)}`), {
          headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) {
          throw new Error(data.message || data.error || `HTTP ${res.status}`);
        }

        if (requestSerial !== targetsRequestSerial) {
          return;
        }

        renderTargets(data.targets || []);
        setFeedback((data.targets || []).length
          ? 'Choisissez une comparaison, puis chargez le fichier XLSX.'
          : 'Cette œuvre n’a pas encore de comparaison éditoriale compatible pour les chapitres.');
      } catch (err) {
        if (requestSerial !== targetsRequestSerial) {
          return;
        }
        renderTargets([]);
        setFeedback(`Impossible de charger les cibles : ${err.message || 'erreur inconnue'}`, 'error');
      }
    }

    async function refreshTargetsForEvent(workId) {
      const normalizedWorkId = (workId ?? '').toString().trim();
      if (!currentWorkId || !normalizedWorkId || normalizedWorkId !== currentWorkId) {
        return;
      }

      fileInput.value = '';
      await loadTargets(currentWorkId);
      syncPreviewAvailability();
    }

    function renderPreview(data) {
      const selectedOption = targetSelect.options[targetSelect.selectedIndex];
      const existingCount = Number(data?.summary?.existing_count ?? 0);
      const rowCount = Number(data?.summary?.count ?? 0);
      const rootCount = Number(data?.summary?.root_count ?? 0);
      const folderLabel = selectedOption ? selectedOption.textContent : '';

      previewSummary.innerHTML = `
        <strong>${escapeHtml(folderLabel)}</strong><br>
        Feuille lue : ${escapeHtml(data.sheet_name || 'Feuille 2')}<br>
        ${rowCount} lignes importables détectées, dont ${rootCount} racines.<br>
        ${existingCount} ligne(s) existante(s) seraient remplacée(s).
      `;

      previewBody.innerHTML = (data.rows || []).map((row) => `
        <tr>
          <td>${escapeHtml(row.row_number)}</td>
          <td><code>${escapeHtml(row.level)}</code></td>
          <td>${escapeHtml(row.label)}</td>
          <td>${escapeHtml(row.start_line_source)}</td>
          <td>${escapeHtml(row.start_line_target)}</td>
          <td>${escapeHtml(row.parent_level || '—')}</td>
        </tr>
      `).join('');

      const warnings = Array.isArray(data.warnings) ? data.warnings : [];
      if (warnings.length) {
        warningList.classList.remove('d-none');
        warningList.innerHTML = `<strong>Avertissements</strong><ul>${warnings.map((warning) => `<li>${escapeHtml(warning)}</li>`).join('')}</ul>`;
      } else {
        warningList.classList.add('d-none');
        warningList.innerHTML = '';
      }

      previewToken = data.token || null;
      previewWrap.classList.remove('d-none');
      commitBtn.disabled = !previewToken;
      setFeedback('Aperçu prêt. Vérifiez les lignes détectées puis confirmez l’import.', 'success');
    }

    targetSelect.addEventListener('change', () => {
      resetPreview();
      syncPreviewAvailability();
      const selectedOption = targetSelect.options[targetSelect.selectedIndex];
      const count = selectedOption?.dataset.chapterCount ?? '0';
      if (targetSelect.value) {
        setFeedback(`${count} ligne(s) de chapitres actuellement stockée(s) pour cette comparaison.`);
      } else {
        setFeedback('Choisissez une comparaison, puis chargez le fichier XLSX.');
      }
    });

    fileInput.addEventListener('change', () => {
      resetPreview();
      syncPreviewAvailability();
    });

    previewBtn.addEventListener('click', async () => {
      if (!currentWorkId || !targetSelect.value || !fileInput.files?.length) {
        return;
      }

      resetPreview();
      previewBtn.disabled = true;
      setFeedback('Lecture du fichier XLSX et préparation de l’aperçu…');

      const formData = new FormData();
      formData.append('comparison_id', targetSelect.value);
      formData.append('file', fileInput.files[0]);

      try {
        const res = await fetch(withBasePath('/chapters/import/preview'), {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
          },
          body: formData,
        });

        const text = await res.text();
        let data = {};
        try { data = JSON.parse(text); } catch { data = { raw: text }; }
        if (!res.ok || data.status !== 'ok') {
          throw new Error(data.message || data.error || data.raw || `HTTP ${res.status}`);
        }

        renderPreview(data);
      } catch (err) {
        setFeedback(`Prévisualisation impossible : ${err.message || 'erreur inconnue'}`, 'error');
      } finally {
        syncPreviewAvailability();
      }
    });

    commitBtn.addEventListener('click', async () => {
      if (!previewToken || !targetSelect.value) {
        return;
      }

      commitBtn.disabled = true;
      setFeedback('Import des chapitres en cours…');

        try {
          const res = await fetch(withBasePath('/chapters/import/commit'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
          },
          body: JSON.stringify({
            comparison_id: targetSelect.value,
            token: previewToken,
          }),
        });

        const text = await res.text();
        let data = {};
        try { data = JSON.parse(text); } catch { data = { raw: text }; }
        if (!res.ok || data.status !== 'ok') {
          throw new Error(data.message || data.error || data.raw || `HTTP ${res.status}`);
        }

        resetPreview();
        await loadTargets(currentWorkId);
        setFeedback(`${data.imported_count} ligne(s) de chapitres importée(s) avec succès.`, 'success');
      } catch (err) {
        setFeedback(`Import impossible : ${err.message || 'erreur inconnue'}`, 'error');
        commitBtn.disabled = false;
      }
    });

    document.addEventListener('workSelected', async (event) => {
      const workLabel = (event?.detail?.work_label ?? '').toString().trim();
      const authorLabel = (event?.detail?.author_label ?? '').toString().trim();
      currentWorkId = (event?.detail?.workId ?? '').toString().trim() || null;

      summary.textContent = workLabel
        ? (authorLabel ? `Œuvre sélectionnée : ${authorLabel} — ${workLabel}` : `Œuvre sélectionnée : ${workLabel}`)
        : defaultMessage;

      fileInput.value = '';
      await loadTargets(currentWorkId);
      syncPreviewAvailability();
    });

    document.addEventListener('comparisonCreated', (event) => {
      refreshTargetsForEvent(event?.detail?.workId);
    });

    document.addEventListener('comparisonReady', (event) => {
      refreshTargetsForEvent(event?.detail?.workId);
    });

    document.addEventListener('comparisonCompleted', (event) => {
      refreshTargetsForEvent(event?.detail?.workId);
    });

    document.addEventListener('comparisonDeleted', (event) => {
      refreshTargetsForEvent(event?.detail?.workId);
    });

    document.addEventListener('comparisonFailed', (event) => {
      refreshTargetsForEvent(event?.detail?.workId);
    });

    document.addEventListener('refreshComparisons', () => {
      refreshTargetsForEvent(currentWorkId);
    });
  })();
</script>
@endpush
