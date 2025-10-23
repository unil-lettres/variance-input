{{-- resources/views/components/main/facsimiles.blade.php --}}
<div class="card mb-3">
    <div class="card-header text-uppercase fw-semibold">Fac-similés illustratifs</div>
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
            <p class="fst-italic text-muted small mb-0">
                Sélectionnez une version depuis la liste ci-dessous pour parcourir les images du document original.
                Utilisez le bouton de téléversement associé à chaque version pour importer de nouveaux fac-similés.
            </p>
            <button type="button"
                    class="btn btn-outline-primary btn-sm"
                    id="facsimile-upload-btn"
                    disabled>
                Téléverser des fac-similés
            </button>
        </div>

        <div id="facsimile-status" class="text-muted small mb-3">
            Sélectionnez une version pour afficher les fac-similés.
        </div>

        <div id="gallery" class="d-flex flex-wrap gap-2"></div>
        <div id="gallery-meta" class="mt-2 text-center text-muted small"></div>
        <div id="gallery-pagination" class="mt-1 d-flex flex-wrap justify-content-center gap-1"></div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const gallery       = document.getElementById('gallery');
    const galleryMeta   = document.getElementById('gallery-meta');
    const galleryPager  = document.getElementById('gallery-pagination');
    const statusEl      = document.getElementById('facsimile-status');
    const shortcutBtn   = document.getElementById('facsimile-upload-btn');

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
        galleryFiles = files;
        galleryPage  = 1;
        updateGallery();
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

        galleryMeta.textContent = `${currentVersionName ? currentVersionName + ' — ' : ''}${galleryFiles.length} image(s) · page ${galleryPage}/${totalPages}`;

        const markup = pageItems.map(f => {
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

            return `
            <div class="d-flex flex-column align-items-center" style="width:125px">
                <a href="${f.big}" target="_blank" rel="noopener" class="d-block mb-1">
                    <img src="${thumbSrc}"
                         alt="${f.name}"
                         class="border rounded fac-thumb">
                </a>
                <div class="fac-caption text-truncate text-center" title="${f.name}">
                    ${f.name}
                </div>
                ${metaHtml}
                ${thumbWarning}
            </div>`;
        }).join('');

        gallery.innerHTML = markup;

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
                setStatus(`Aucun fac-similé pour ${versionName || 'cette version'}.`);
                resetGallery('Aucun fac-similé pour cette version.');
                return;
            }
            setStatus(`Fac-similés pour ${versionName || 'cette version'}.`, 'success');
            renderGallery(files);
        } catch (err) {
            console.error(err);
            setStatus('Erreur lors du chargement des fac-similés.', 'danger');
            resetGallery('Impossible de charger les fac-similés.');
        }
    }

    document.addEventListener('workSelected', e => {
        currentWorkId     = e.detail?.workId ?? null;
        currentVersionId  = null;
        currentVersionName= '';
        if (shortcutBtn) shortcutBtn.disabled = true;
        setStatus('Sélectionnez une version pour afficher les fac-similés.');
        resetGallery();
    });

    document.addEventListener('facsimiles:select', e => {
        const { versionId, versionName } = e.detail || {};
        currentVersionId   = versionId || null;
        currentVersionName = versionName || '';
        if (!currentVersionId) {
            if (shortcutBtn) shortcutBtn.disabled = true;
            setStatus('Sélectionnez une version pour afficher les fac-similés.');
            resetGallery();
            return;
        }
        if (shortcutBtn) shortcutBtn.disabled = false;
        loadGallery(currentVersionId, currentVersionName);
    });

    document.addEventListener('facsimilesUploaded', e => {
        if (currentVersionId && e.detail?.versionId === currentVersionId) {
            loadGallery(currentVersionId, currentVersionName);
        }
    });

    if (shortcutBtn) {
        shortcutBtn.addEventListener('click', () => {
            if (!currentVersionId) {
                alert('Sélectionnez d\'abord une version dans la liste des versions.');
                return;
            }
            document.dispatchEvent(new CustomEvent('facsimiles:requestUpload', {
                detail: { versionId: currentVersionId }
            }));
        });
    }
});
</script>
@endpush
