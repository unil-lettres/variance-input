{{-- resources/views/components/main/facsimiles.blade.php --}}
<div id="facsimiles-card" class="card mb-3">
    <div class="card-header fw-semibold">fac-similés</div>
    <div class="card-body">
        <p class="fst-italic text-muted small mb-3">
            Choisissez une version pour parcourir son dossier d’images. Utilisez ensuite la « gestion du manifeste »
            pour sélectionner les fac-similés à exposer dans les comparaisons Medite (JSON) et sur le site public.
        </p>

        <div id="facsimile-status" class="text-muted small mb-3">
            Sélectionnez une version pour afficher les fac-similés.
        </div>

        <div id="manifest-manager" class="manifest-manager border rounded px-3 py-3 mb-3 d-none">
            <div class="d-flex flex-column flex-xl-row align-items-xl-center gap-2 gap-xl-3">
                <div class="manifest-instructions">
                    <div class="fw-semibold text-uppercase small text-muted">Gestion du manifeste JSON</div>
                    <div class="text-muted small">Sélectionnez une comparaison pour choisir les images publiées.</div>
                </div>
                <div class="flex-grow-1">
                    <label for="manifest-comparison" class="form-label small mb-1">Comparaison</label>
                    <select id="manifest-comparison" class="form-select form-select-sm" disabled>
                        <option value="">Associer une comparaison…</option>
                    </select>
                </div>
                <div class="d-flex flex-nowrap gap-2">
                    <button type="button" class="btn btn-sm btn-primary" id="manifest-save" disabled>Enregistrer</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="manifest-cancel" disabled>Annuler</button>
                </div>
            </div>
            <div id="manifest-list" class="manifest-list d-flex flex-wrap gap-2 mt-3"></div>
            <div id="manifest-summary" class="small text-muted mt-2"></div>
        </div>

        <div id="gallery" class="d-flex flex-wrap gap-2"></div>
        <div id="gallery-meta" class="mt-2 text-center text-muted small"></div>
        <div id="gallery-pagination" class="mt-1 d-flex flex-wrap justify-content-center gap-1"></div>
    </div>
</div>

@push('styles')
<style>
    #manifest-manager {
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
    }
    #manifest-manager select {
        min-width: 260px;
    }
    .manifest-instructions {
        min-width: 220px;
    }
    .manifest-list {
        min-height: 1.5rem;
    }
    .manifest-pill {
        font-size: 0.78rem;
        line-height: 1.2;
        border-radius: 999px;
        padding: 0.2rem 0.65rem;
        transition: all 0.15s ease-in-out;
    }
    .manifest-pill.btn-outline-secondary:hover {
        color: var(--bs-primary);
        border-color: var(--bs-primary);
        background-color: rgba(13, 110, 253, 0.08);
    }
    .fac-item {
        width: 125px;
    }
    .fac-item-selectable {
        cursor: pointer;
    }
    .fac-item-selected .fac-thumb {
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }
    .manifest-toggle {
        cursor: pointer;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const gallery       = document.getElementById('gallery');
    const galleryMeta   = document.getElementById('gallery-meta');
    const galleryPager  = document.getElementById('gallery-pagination');
    const statusEl      = document.getElementById('facsimile-status');
    const manifestManager   = document.getElementById('manifest-manager');
    const manifestSelect    = document.getElementById('manifest-comparison');
    const manifestSaveBtn   = document.getElementById('manifest-save');
    const manifestCancelBtn = document.getElementById('manifest-cancel');
    const manifestSummary   = document.getElementById('manifest-summary');
    const manifestList      = document.getElementById('manifest-list');
    const csrfToken         = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const manifestOptionElements = new Map();

    const GALLERY_PAGE_SIZE = 12;

    const formatBytes = (size) => {
        const value = Number(size);
        if (!Number.isFinite(value) || value <= 0) return '0 o';
        const units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        let idx = 0;
        let current = value;
        while (current >= 1024 && idx < units.length - 1) {
            current /= 1024;
            idx++;
        }
        const precision = idx === 0 ? 0 : 1;
        return `${current.toFixed(precision)} ${units[idx]}`;
    };

    let galleryFiles      = [];
    let galleryPage       = 1;
    let currentWorkId     = null;
    let currentVersionId  = null;
    let currentVersionName= '';

    let manifestOptions      = [];
    let manifestActiveKey    = null;
    let manifestSelectedSet  = new Set();
    let manifestOriginalSet  = new Set();
    let manifestBusy         = false;
    let manifestRequestToken = 0;
    let pendingManifestFocus = null;

    function manifestOptionKey(comparisonId, role) {
        return `${comparisonId}:${role}`;
    }

    function getManifestOption(key) {
        return manifestOptions.find(opt => manifestOptionKey(opt.comparison_id, opt.role) === key);
    }

    function buildManifestOptionLabel(opt) {
        const base = opt.comparison_label ? String(opt.comparison_label) : `#${opt.comparison_id}`;
        const roleLabel = opt.role_label ? String(opt.role_label) : (opt.role === 'source' ? 'Source' : 'Cible');
        const count = Number.isFinite(opt.count) ? opt.count : (Array.isArray(opt.selected) ? opt.selected.length : 0);
        const suffix = `${count} image${count === 1 ? '' : 's'}`;
        return `${base} — ${roleLabel} (${suffix})`;
    }

    function setsEqual(a, b) {
        if (a.size !== b.size) return false;
        for (const value of a) {
            if (!b.has(value)) return false;
        }
        return true;
    }

    function setStatus(message, tone = 'muted') {
        if (!statusEl) return;
        statusEl.className = `small mb-3 text-${tone}`;
        statusEl.textContent = message;
    }

    function resetGallery(message = 'Sélectionnez une version pour afficher les fac-similés.') {
        galleryFiles = [];
        galleryPage  = 1;
        gallery.innerHTML = `<div class="text-muted">${message}</div>`;
        galleryPager.innerHTML = '';
        galleryMeta.textContent = '';
    }

    function renderGallery(files) {
        galleryFiles = Array.isArray(files) ? files : [];
        galleryPage = 1;
        updateGallery();
    }

    function resetManifestControls({ hideManager = false, summary = '' } = {}) {
        manifestOptions = [];
        manifestActiveKey = null;
        manifestSelectedSet = new Set();
        manifestOriginalSet = new Set();
        manifestOptionElements.clear();
        if (manifestSelect) {
            manifestSelect.innerHTML = '<option value="">Associer une comparaison…</option>';
            manifestSelect.disabled = true;
            manifestSelect.value = '';
        }
        if (manifestList) {
            manifestList.innerHTML = hideManager
                ? ''
                : '<div class="text-muted small">Sélectionnez une comparaison pour personnaliser son manifeste.</div>';
        }
        if (manifestManager) {
            manifestManager.classList.toggle('d-none', hideManager);
        }
        if (manifestSummary) {
            const defaultSummary = hideManager
                ? ''
                : 'Choisissez une comparaison pour préparer le manifeste JSON.';
            manifestSummary.textContent = summary || defaultSummary;
        }
        updateManifestButtons();
        updateGallery();
    }

    function updateManifestButtons() {
        const hasActive = !!manifestActiveKey;
        const hasChanges = hasActive && !setsEqual(manifestSelectedSet, manifestOriginalSet);
        if (manifestSaveBtn) {
            manifestSaveBtn.disabled = !hasActive || !hasChanges || manifestBusy;
        }
        if (manifestCancelBtn) {
            manifestCancelBtn.disabled = !hasActive || !hasChanges || manifestBusy;
        }
    }

    function updateManifestSummary(option) {
        if (!manifestSummary) return;
        if (!manifestActiveKey || !option) {
            manifestSummary.textContent = '';
            return;
        }
        const count = manifestSelectedSet.size;
        const roleLabel = option.role_label ? option.role_label.toLowerCase() : (option.role === 'source' ? 'source' : 'cible');
        let message = `${count} image${count === 1 ? '' : 's'} sélectionnée${count === 1 ? '' : 's'} pour le ${roleLabel}.`;
        if (!option.exists && option.inferred) {
            message += ' Manifeste non enregistré — sélection par défaut.';
        } else if (option.updated_at) {
            const date = new Date(option.updated_at * 1000);
            message += ` Dernière mise à jour : ${date.toLocaleString('fr-FR', { hour12: false })}.`;
        }
        manifestSummary.textContent = message;
    }

    function renderManifestList(activeKey = manifestActiveKey) {
        if (!manifestList) {
            return;
        }
        manifestList.innerHTML = '';
        if (!manifestOptions.length) {
            manifestList.innerHTML = '<div class="text-muted small">Aucune comparaison Medite associée à cette version.</div>';
            return;
        }
        manifestOptions.forEach(opt => {
            const key = manifestOptionKey(opt.comparison_id, opt.role);
            const isActive = key === activeKey;
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.dataset.manifestKey = key;
            pill.className = [
                'btn',
                'btn-sm',
                'manifest-pill',
                isActive ? 'btn-primary' : 'btn-outline-secondary',
            ].join(' ');
            pill.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            pill.textContent = buildManifestOptionLabel(opt);
            if (opt.file) {
                pill.title = opt.file;
            }
            pill.addEventListener('click', () => {
                if (manifestSelect) {
                    manifestSelect.value = key;
                }
                applyManifestOption(key);
            });
            manifestList.appendChild(pill);
        });
    }

    function applyManifestOption(key) {
        const option = getManifestOption(key);
        if (!option) {
            manifestActiveKey = null;
            manifestSelectedSet = new Set();
            manifestOriginalSet = new Set();
            renderManifestList(null);
            updateGallery();
            updateManifestSummary(null);
            updateManifestButtons();
            return;
        }
        manifestActiveKey = key;
        const selected = Array.isArray(option.selected) ? option.selected : [];
        manifestSelectedSet = new Set(selected);
        manifestOriginalSet = new Set(selected);
        if (manifestSelect) {
            manifestSelect.value = key;
        }
        if (manifestManager) {
            manifestManager.classList.remove('d-none');
        }
        renderManifestList(key);
        updateGallery();
        updateManifestSummary(option);
        updateManifestButtons();
    }

    function attachManifestEvents() {
        if (!manifestActiveKey) return;
        const option = getManifestOption(manifestActiveKey);
        if (!option) return;

        gallery.querySelectorAll('.manifest-toggle').forEach(input => {
            input.addEventListener('change', event => {
                const name = event.target.dataset.file;
                if (!name) return;
                if (event.target.checked) {
                    manifestSelectedSet.add(name);
                    event.target.closest('.fac-item')?.classList.add('fac-item-selected');
                } else {
                    manifestSelectedSet.delete(name);
                    event.target.closest('.fac-item')?.classList.remove('fac-item-selected');
                }
                updateManifestButtons();
                updateManifestSummary(option);
            });
        });

        gallery.querySelectorAll('.fac-item-selectable').forEach(item => {
            item.addEventListener('click', event => {
                if (!manifestActiveKey) return;
                event.preventDefault();
                const checkbox = item.querySelector('.manifest-toggle');
                if (!checkbox) return;
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    function focusManifest(key) {
        if (!key) return false;
        if (manifestOptionElements.has(key)) {
            applyManifestOption(key);
            const option = getManifestOption(key);
            updateManifestSummary(option);
            if (manifestManager) {
                manifestManager.classList.remove('d-none');
                manifestManager.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            pendingManifestFocus = null;
            return true;
        }
        pendingManifestFocus = key;
        return false;
    }

    async function loadManifestOptions(versionId, { preserveSelection = false, focusKey = null } = {}) {
        manifestRequestToken++;
        const requestId = manifestRequestToken;
        const previousKey = preserveSelection ? manifestActiveKey : null;

        if (!preserveSelection) {
            manifestActiveKey = null;
            manifestSelectedSet = new Set();
            manifestOriginalSet = new Set();
            updateGallery();
        }

        if (!versionId) {
            resetManifestControls({ hideManager: true });
            return;
        }

        if (manifestManager) {
            manifestManager.classList.remove('d-none');
        }
        if (manifestSelect) {
            manifestSelect.disabled = true;
            manifestSelect.value = '';
        }
        if (manifestSummary) {
            manifestSummary.textContent = 'Chargement des comparaisons…';
        }
        if (manifestList) {
            manifestList.innerHTML = '<div class="text-muted small">Chargement…</div>';
        }

        try {
            const res = await fetch(withBasePath(`/api/versions/${versionId}/comparisons`), {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (requestId !== manifestRequestToken) return;

            manifestOptions = Array.isArray(data) ? data : [];
            manifestOptionElements.clear();

            if (!manifestOptions.length) {
                manifestActiveKey = null;
                manifestSelectedSet = new Set();
                manifestOriginalSet = new Set();
                if (manifestSelect) {
                    manifestSelect.innerHTML = '<option value="">Aucune comparaison disponible</option>';
                    manifestSelect.value = '';
                    manifestSelect.disabled = true;
                }
                renderManifestList(null);
                if (manifestSummary) {
                    manifestSummary.textContent = 'Aucune comparaison Medite n’est associée à cette version.';
                }
                updateManifestButtons();
                pendingManifestFocus = null;
                return;
            }

            if (manifestSelect) {
                manifestSelect.innerHTML = '';
                const placeholder = new Option('Associer une comparaison…', '');
                manifestSelect.appendChild(placeholder);

                manifestOptions.forEach(opt => {
                    const value = manifestOptionKey(opt.comparison_id, opt.role);
                    const optionEl = new Option(buildManifestOptionLabel(opt), value);
                    manifestOptionElements.set(value, optionEl);
                    manifestSelect.appendChild(optionEl);
                });
                manifestSelect.disabled = false;
            }

            renderManifestList(previousKey);

            const reapplyKey = previousKey && manifestOptionElements.has(previousKey) ? previousKey : null;
            const desiredKey = [focusKey, pendingManifestFocus, reapplyKey].find(key => key && manifestOptionElements.has(key));
            if (desiredKey) {
                focusManifest(desiredKey);
            } else {
                manifestActiveKey = null;
                manifestSelectedSet = new Set();
                manifestOriginalSet = new Set();
                if (manifestSelect) manifestSelect.value = '';
                updateGallery();
                updateManifestSummary(null);
                if (manifestSummary) {
                    manifestSummary.textContent = 'Sélectionnez une comparaison pour personnaliser son manifeste.';
                }
                renderManifestList(null);
                updateManifestButtons();
                pendingManifestFocus = null;
            }
        } catch (err) {
            console.error('Could not load manifest options', err);
            if (requestId === manifestRequestToken) {
                resetManifestControls({ summary: 'Impossible de charger les manifestes pour cette version.' });
                if (manifestList) {
                    manifestList.innerHTML = '<div class="text-danger small">Erreur lors du chargement des manifestes.</div>';
                }
            }
        } finally {
            if (requestId === manifestRequestToken) {
                updateManifestButtons();
            }
        }
    }

    async function saveManifestSelection() {
        if (!manifestActiveKey || !currentVersionId) {
            return;
        }
        const option = getManifestOption(manifestActiveKey);
        if (!option) {
            return;
        }

        const [comparisonIdStr, role] = manifestActiveKey.split(':');
        const comparisonId = Number(comparisonIdStr);
        if (!Number.isFinite(comparisonId)) {
            return;
        }

        const payload = {
            role,
            images: Array.from(manifestSelectedSet),
        };

        const originalSaveLabel = manifestSaveBtn ? manifestSaveBtn.innerHTML : '';

        manifestBusy = true;
        updateManifestButtons();
        if (manifestSaveBtn) {
            manifestSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        }
        if (manifestSelect) manifestSelect.disabled = true;
        if (manifestSummary) manifestSummary.textContent = 'Enregistrement en cours…';

        try {
            const res = await fetch(withBasePath(`/api/versions/${currentVersionId}/manifests/${comparisonId}`), {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            const text = await res.text();
            let data = {};
            if (text) {
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    console.error('Invalid JSON response', err, text);
                }
            }
            if (!res.ok) {
                const message = data?.message || data?.error || `HTTP ${res.status}`;
                throw new Error(message);
            }

            const updatedSelected = Array.isArray(data.selected) ? data.selected : Array.from(manifestSelectedSet);
            manifestSelectedSet = new Set(updatedSelected);
            manifestOriginalSet = new Set(updatedSelected);

            const optionRef = getManifestOption(manifestActiveKey);
            if (optionRef) {
                optionRef.selected = updatedSelected;
                optionRef.count = Number.isFinite(data.count) ? data.count : updatedSelected.length;
                optionRef.exists = data.exists !== undefined ? !!data.exists : true;
                optionRef.inferred = data.inferred !== undefined ? !!data.inferred : false;
                optionRef.file = data.file ?? optionRef.file ?? null;
                optionRef.updated_at = data.updated_at ?? optionRef.updated_at ?? Math.floor(Date.now() / 1000);
            }

            updateGallery();
            updateManifestSummary(optionRef || option);
            renderManifestList(manifestActiveKey);
            updateManifestButtons();

            const manifestDetail = {
                count: optionRef?.count ?? updatedSelected.length,
                exists: optionRef?.exists ?? true,
                file: optionRef?.file ?? null,
                updated_at: optionRef?.updated_at ?? null,
                inferred: optionRef?.inferred ?? false,
                selected: updatedSelected,
            };

            document.dispatchEvent(new CustomEvent('comparisonManifestUpdated', {
                detail: {
                    comparisonId,
                    role,
                    workId: currentWorkId ?? null,
                    versionId: currentVersionId ?? null,
                    versionName: currentVersionName ?? '',
                    count: manifestDetail.count,
                    manifest: manifestDetail,
                },
            }));
        } catch (err) {
            console.error('Manifest update failed', err);
            alert(err.message || 'Impossible de mettre à jour le manifeste.');
        } finally {
            manifestBusy = false;
            if (manifestSaveBtn) {
                manifestSaveBtn.innerHTML = originalSaveLabel || 'Enregistrer';
            }
            if (manifestSelect) manifestSelect.disabled = false;
            updateManifestButtons();
        }
    }

    function updateGallery() {
        if (!galleryFiles.length) {
            resetGallery('Aucun fac-similé pour cette version.');
            return;
        }

        const totalPages = Math.max(1, Math.ceil(galleryFiles.length / GALLERY_PAGE_SIZE));
        galleryPage = Math.min(Math.max(galleryPage, 1), totalPages);
        const startIndex = (galleryPage - 1) * GALLERY_PAGE_SIZE;
        const pageItems  = galleryFiles.slice(startIndex, startIndex + GALLERY_PAGE_SIZE);

        const manifestActive = !!manifestActiveKey;

        const markup = pageItems.map((f, idx) => {
            const metaParts = [];
            if (Number.isFinite(f.width) && Number.isFinite(f.height)) {
                metaParts.push(`${f.width}×${f.height}px`);
            }
            if (typeof f.size_human === 'string' && f.size_human) {
                metaParts.push(f.size_human);
            } else if (Number.isFinite(f.size_bytes)) {
                metaParts.push(formatBytes(f.size_bytes));
            }
            const metaHtml = metaParts.length
                ? `<div class="text-muted small text-center">${metaParts.join(' — ')}</div>`
                : '';

            const thumbWarning = !f.hasThumb
                ? '<div class="text-danger small text-center">⚠️ pas de miniature</div>'
                : '';
            const thumbSrc = f.thumb || f.big;
            const name = f.name || `file-${startIndex + idx}`;
            const isSelected = manifestActive && manifestSelectedSet.has(name);
            const checkboxId = `manifest-${startIndex + idx}`;

            const checkbox = manifestActive
                ? `<div class="form-check form-check-sm position-absolute top-0 start-0 m-1">
                        <input class="form-check-input manifest-toggle" type="checkbox" id="${checkboxId}" data-file="${name}" ${isSelected ? 'checked' : ''}>
                        <label class="visually-hidden" for="${checkboxId}">Associer ${name}</label>
                   </div>`
                : '';

            return `
            <div class="fac-item d-flex flex-column align-items-center ${manifestActive ? 'fac-item-selectable' : ''} ${isSelected ? 'fac-item-selected' : ''}" data-file="${name}">
                <div class="position-relative mb-1">
                    ${checkbox}
                    <a href="${f.big}" target="_blank" rel="noopener" class="d-block">
                        <img src="${thumbSrc}"
                             alt="${name}"
                             class="border rounded fac-thumb">
                    </a>
                </div>
                <div class="fac-caption text-truncate text-center" title="${name}">
                    ${name}
                </div>
                ${metaHtml}
                ${thumbWarning}
            </div>`;
        }).join('');

        gallery.innerHTML = markup;

        galleryMeta.textContent = `${currentVersionName ? currentVersionName + ' — ' : ''}${galleryFiles.length} image(s) · page ${galleryPage}/${totalPages}`;

        attachManifestEvents();
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        if (totalPages <= 1) {
            galleryPager.innerHTML = '';
            return;
        }

        const buttons = [];
        buttons.push(`<button type="button" class="btn btn-sm btn-outline-secondary" data-page="prev" ${galleryPage === 1 ? 'disabled' : ''}>‹</button>`);

        for (let p = 1; p <= totalPages; p++) {
            if (p === 1 || p === totalPages || Math.abs(p - galleryPage) <= 2) {
                buttons.push(`<button type="button" class="btn btn-sm ${p === galleryPage ? 'btn-primary' : 'btn-outline-secondary'}" data-page="${p}">${p}</button>`);
            } else if (buttons[buttons.length - 1] !== '…') {
                buttons.push('…');
            }
        }

        buttons.push(`<button type="button" class="btn btn-sm btn-outline-secondary" data-page="next" ${galleryPage === totalPages ? 'disabled' : ''}>›</button>`);

        galleryPager.innerHTML = buttons.map(btn => btn === '…' ? '<span class="px-2">…</span>' : btn).join('');

        galleryPager.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-page');
                if (target === 'prev') galleryPage = Math.max(1, galleryPage - 1);
                else if (target === 'next') galleryPage = Math.min(totalPages, galleryPage + 1);
                else galleryPage = Number(target);
                updateGallery();
            });
        });
    }

    async function loadGallery(versionId, versionName = '') {
        if (!versionId) {
            resetGallery();
            return;
        }

        setStatus(`Chargement des fac-similés pour ${versionName || 'cette version'}…`);
        resetGallery('<div class="text-muted">Chargement…</div>');

        try {
            const res = await fetch(withBasePath(`/api/facsimiles?version_id=${versionId}`), {
                headers: { Accept: 'application/json' }
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const files = await res.json();
            if (!Array.isArray(files) || !files.length) {
                setStatus(`Aucun fac-similé pour ${versionName || 'cette version'}.`, 'muted');
                resetGallery('Aucun fac-similé pour cette version.');
                return;
            }
            const label = versionName || 'cette version';
            setStatus(`Fac-similés pour ${label}.`, 'muted');
            renderGallery(files);
        } catch (err) {
            console.error(err);
            const detail = err?.message ? ` (${err.message})` : '';
            setStatus(`Erreur lors du chargement des fac-similés${detail}.`, 'danger');
            resetGallery('Impossible de charger les fac-similés.');
        }
    }

    document.addEventListener('workSelected', e => {
        currentWorkId     = e.detail?.workId ?? null;
        currentVersionId  = null;
        currentVersionName= '';
        resetManifestControls({ hideManager: true });
        setStatus('Sélectionnez une version pour afficher les fac-similés.');
        resetGallery();
    });

    document.addEventListener('facsimiles:select', e => {
        const { versionId, versionName } = e.detail || {};
        currentVersionId   = versionId || null;
        currentVersionName = versionName || '';
        resetManifestControls();
        if (!currentVersionId) {
            setStatus('Sélectionnez une version pour afficher les fac-similés.');
            resetGallery();
            return;
        }
        loadGallery(currentVersionId, currentVersionName);
        loadManifestOptions(currentVersionId, { focusKey: pendingManifestFocus });
    });

    document.addEventListener('facsimilesUploaded', e => {
        if (currentVersionId && e.detail?.versionId === currentVersionId) {
            loadGallery(currentVersionId, currentVersionName);
            loadManifestOptions(currentVersionId, { preserveSelection: true });
        }
    });

    if (manifestSelect) {
        manifestSelect.addEventListener('change', () => {
            const value = manifestSelect.value;
            if (!value) {
                manifestActiveKey = null;
                manifestSelectedSet = new Set();
                manifestOriginalSet = new Set();
                updateGallery();
                updateManifestSummary(null);
                updateManifestButtons();
                return;
            }
            applyManifestOption(value);
        });
    }

    if (manifestSaveBtn) {
        manifestSaveBtn.addEventListener('click', saveManifestSelection);
    }

    if (manifestCancelBtn) {
        manifestCancelBtn.addEventListener('click', () => {
            if (!manifestActiveKey) return;
            manifestSelectedSet = new Set(manifestOriginalSet);
            updateGallery();
            const option = getManifestOption(manifestActiveKey);
            updateManifestSummary(option);
            updateManifestButtons();
        });
    }

    document.addEventListener('facsimiles:focusManifest', e => {
        const detail = e.detail || {};
        const versionId = Number(detail.versionId);
        const versionName = detail.versionName || '';
        const comparisonId = Number(detail.comparisonId);
        const role = (detail.role === 'target') ? 'target' : 'source';
        if (!Number.isFinite(versionId) || !Number.isFinite(comparisonId)) {
            return;
        }
        const key = manifestOptionKey(comparisonId, role);
        pendingManifestFocus = key;
        if (currentVersionId !== versionId) {
            document.dispatchEvent(new CustomEvent('facsimiles:select', {
                detail: { versionId, versionName }
            }));
        } else {
            if (!focusManifest(key)) {
                loadManifestOptions(versionId, { preserveSelection: true, focusKey: key });
            }
        }
    });

    function renderPagination(totalPages) {
        if (totalPages <= 1) {
            galleryPager.innerHTML = '';
            return;
        }

        const buttons = [];
        buttons.push(`<button type="button" class="btn btn-sm btn-outline-secondary" data-page="prev" ${galleryPage === 1 ? 'disabled' : ''}>‹</button>`);

        for (let p = 1; p <= totalPages; p++) {
            if (p === 1 || p === totalPages || Math.abs(p - galleryPage) <= 2) {
                buttons.push(`<button type="button" class="btn btn-sm ${p === galleryPage ? 'btn-primary' : 'btn-outline-secondary'}" data-page="${p}">${p}</button>`);
            } else if (buttons[buttons.length - 1] !== '…') {
                buttons.push('…');
            }
        }

        buttons.push(`<button type="button" class="btn btn-sm btn-outline-secondary" data-page="next" ${galleryPage === totalPages ? 'disabled' : ''}>›</button>`);

        galleryPager.innerHTML = buttons.map(btn => btn === '…' ? '<span class="px-2">…</span>' : btn).join('');

        galleryPager.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-page');
                if (target === 'prev') galleryPage = Math.max(1, galleryPage - 1);
                else if (target === 'next') galleryPage = Math.min(totalPages, galleryPage + 1);
                else galleryPage = Number(target);
                updateGallery();
            });
        });
    }
});
</script>
@endpush
