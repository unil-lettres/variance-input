{{-- resources/views/components/main/facsimiles_upload.blade.php --}}
<div class="card mb-3">
    <div class="card-header">Importer des fac-similés</div>

    <div class="card-body">

        {{-- Sélecteur de version --}}
        <div class="mb-2">
            <label for="version-select" class="form-label fw-bold">
                Version cible&nbsp;:
            </label>
            <select id="version-select" class="form-select form-select-sm">
                <option value="">— choisir —</option>
            </select>
        </div>

        {{-- Sélecteur de fichiers --}}
        <input  type="file" id="img-input" multiple accept="image/*"
                class="form-control form-control-sm mb-3"/>

        {{-- Bouton d’upload --}}
        <button id="upload-btn" class="btn btn-primary btn-sm" disabled>
            Importer les images
        </button>
        <div id="upload-spinner"
             class="spinner-border spinner-border-sm ms-2"
             style="display:none;"></div>

        {{-- Journal d’upload --}}
        <div id="upload-log" class="small text-muted mt-2 mb-2"></div>

        {{-- Galerie d’aperçu --}}
        <div id="gallery" class="d-flex flex-wrap gap-2"></div>
        <div id="gallery-meta" class="mt-2 text-center text-muted small"></div>
        <div id="gallery-pagination" class="mt-1 d-flex flex-wrap justify-content-center gap-1"></div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {

    const versionSelect = document.getElementById('version-select');
    const imgInput      = document.getElementById('img-input');
    const uploadBtn     = document.getElementById('upload-btn');
    const spinner       = document.getElementById('upload-spinner');
    const logDiv        = document.getElementById('upload-log');
    const gallery       = document.getElementById('gallery');
    const galleryMeta   = document.getElementById('gallery-meta');
    const galleryPager  = document.getElementById('gallery-pagination');

    const MAX_BATCH_FILES   = 10;               // safety cap against max_file_uploads
    const MAX_BATCH_BYTES   = 7.5 * 1024 * 1024; // stay under default 8MB post_max_size
    const GALLERY_PAGE_SIZE = 12;

    let galleryFiles = [];
    let galleryPage  = 1;

    /* ----------------------------------------------------------
     * 1. Charger les versions quand un work est sélectionné
     * -------------------------------------------------------- */
    document.addEventListener('workSelected', async e => {
        const workId = e.detail.workId;
        versionSelect.innerHTML =
            '<option value="">Chargement…</option>';
        try {
            const res = await fetch(`/api/versions?work_id=${workId}`,
                                    {headers:{Accept:'application/json'}});
            const versions = await res.json();
            versionSelect.innerHTML =
                '<option value="">— choisir —</option>' +
                versions.map(v =>
                    `<option value="${v.id}"
                             data-folder="${v.folder}"
                             >${v.name}</option>`).join('');
        } catch (err) {
            console.error(err);
            versionSelect.innerHTML =
                '<option value="">(erreur de chargement)</option>';
        }
        toggleBtn();                 // ré-évalue l’état du bouton
        resetGallery();
    });

    /* ----------------------------------------------------------
     * 2. Activer / désactiver le bouton d’upload
     * -------------------------------------------------------- */
    function toggleBtn() {
        uploadBtn.disabled =
            !(versionSelect.value && imgInput.files.length);
    }
    versionSelect.addEventListener('change', () => {
        toggleBtn();
        if (versionSelect.value) {
            loadGallery(versionSelect.value);
        } else {
            resetGallery();
        }
    });
    imgInput.addEventListener('change', toggleBtn);

    /* ----------------------------------------------------------
     * 3. Charger la galerie d’images existantes
     * -------------------------------------------------------- */
    async function loadGallery(versionId) {
        resetGallery('<div class="text-muted">Chargement…</div>');

        try {
            const res   = await fetch(`/api/facsimiles?version_id=${versionId}`,
                                      { headers:{Accept:'application/json'} });
            const files = await res.json();   // [{big, thumb, name, hasThumb}]

            if (!Array.isArray(files) || !files.length) {
                resetGallery('<div class="text-muted">Aucune image</div>');
                return;
            }

            renderGallery(files);

        } catch (err) {
            console.error(err);
            gallery.innerHTML = '<div class="text-danger">Erreur de chargement</div>';
        }
    }




    /* ----------------------------------------------------------
     * 4. Envoi des images
     * -------------------------------------------------------- */
    uploadBtn.addEventListener('click', async () => {

        const files = Array.from(imgInput.files || []);
        if (!files.length || !versionSelect.value) return;

        spinner.style.display = 'inline-block';
        uploadBtn.disabled    = true;
        logDiv.textContent    = '';

        const totalFiles   = files.length;
        let uploadedCount  = 0;
        const thumbIssues  = [];
        const batchErrors  = [];
        let lastStoredDir  = null;

        let cursor = 0;
        let batchIndex = 0;
        while (cursor < totalFiles) {
            let batchSize = 0;
            let byteTotal = 0;
            const chunk = [];

            while (cursor < totalFiles && batchSize < MAX_BATCH_FILES) {
                const file = files[cursor];
                const tentative = byteTotal + (file.size || 0);

                if (batchSize > 0 && tentative > MAX_BATCH_BYTES) {
                    break; // keep current chunk below byte ceiling
                }

                chunk.push(file);
                byteTotal = tentative;
                batchSize += 1;
                cursor += 1;

                if (byteTotal >= MAX_BATCH_BYTES) {
                    break;
                }
            }

            if (!chunk.length) {
                // fallback: force single file so we don't loop forever
                const file = files[cursor];
                chunk.push(file);
                cursor += 1;
                byteTotal = file.size || 0;
            }

            const start = cursor - chunk.length + 1;
            const end   = cursor;

            logDiv.textContent = `Envoi des images ${start} à ${end} sur ${totalFiles}…`;

            const form = new FormData();
            form.append('version_id', versionSelect.value);
            form.append('reset', batchIndex === 0 ? '1' : '0');
            chunk.forEach(file => form.append('images[]', file));

            try {
                const res  = await fetch('/api/upload_facsimiles', {
                    method : 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body   : form
                });

                const payload = await readJsonResponse(res);

                uploadedCount += payload.files_added ?? 0;
                if (payload.stored_in) {
                    lastStoredDir = payload.stored_in;
                }
                if (Array.isArray(payload.errors) && payload.errors.length) {
                    thumbIssues.push(...payload.errors);
                }

            } catch (err) {
                console.error(err);
                batchErrors.push({
                    range: `${start}-${end}`,
                    message: err.message
                });
            }

            batchIndex += 1;
        }

        const messages = [];
        if (uploadedCount) {
            messages.push(`✅ ${uploadedCount} fichier(s) importé(s) dans ${lastStoredDir ?? ''}`.trim());
        }
        if (thumbIssues.length) {
            messages.push(`⚠️ Miniatures non générées pour ${thumbIssues.length} fichier(s). Exemple : ${thumbIssues.slice(0, 3).join(', ')}${thumbIssues.length > 3 ? '…' : ''}`);
        }
        if (batchErrors.length) {
            const sample = batchErrors[0];
            messages.push(`❌ ${batchErrors.length} lot(s) en erreur (images ${sample.range}) : ${sample.message}`);
        }

        if (!messages.length) {
            messages.push('Aucun fichier importé.');
        }

        spinner.style.display = 'none';

        const summary = messages.join('\n');
        window.setTimeout(() => window.alert(summary), 0);
        logDiv.textContent = '';

        if (uploadedCount) {
            imgInput.value = '';
            toggleBtn();
            loadGallery(versionSelect.value);
        } else {
            // réactiver le bouton pour retenter un envoi
            uploadBtn.disabled = false;
        }
    });

    async function readJsonResponse(res) {
        const text = await res.text();
        if (!text) {
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }
            return {};
        }

        try {
            const json = JSON.parse(text);
            if (!res.ok) {
                throw new Error(json.message ?? `HTTP ${res.status}`);
            }
            return json;
        } catch (parseErr) {
            const preview = text.replace(/<[^>]*>/g, ' ')
                                .replace(/\s+/g, ' ')
                                .trim()
                                .slice(0, 200);
            throw new Error(res.ok ? preview : `HTTP ${res.status} — ${preview || 'Réponse invalide'}`);
        }
    }

    function resetGallery(initialMarkup = '') {
        galleryFiles = [];
        galleryPage  = 1;
        gallery.innerHTML = initialMarkup || '';
        galleryPager.innerHTML = '';
        galleryMeta.textContent = '';
        logDiv.textContent = '';
    }

    function renderGallery(files) {
        galleryFiles = files;
        galleryPage  = 1;
        updateGallery();
    }

    function updateGallery() {
        if (!galleryFiles.length) {
            gallery.innerHTML = '<div class="text-muted">Aucune image</div>';
            galleryPager.innerHTML = '';
            galleryMeta.textContent = '';
            return;
        }

        const totalPages = Math.max(1, Math.ceil(galleryFiles.length / GALLERY_PAGE_SIZE));
        galleryPage = Math.min(Math.max(galleryPage, 1), totalPages);

        const startIndex = (galleryPage - 1) * GALLERY_PAGE_SIZE;
        const pageItems  = galleryFiles.slice(startIndex, startIndex + GALLERY_PAGE_SIZE);

        galleryMeta.textContent = `Page ${galleryPage}/${totalPages} — ${galleryFiles.length} image(s)`;

        gallery.innerHTML = pageItems.map(f => `
            <div class=\"d-flex flex-column align-items-center\"
                 style=\"width:125px\">
                <a href=\"${f.big}\" target=\"_blank\" class=\"d-block mb-1\">
                    <img src=\"${f.thumb || f.big}\"
                         alt=\"${f.name}\"
                         class=\"border rounded fac-thumb\">
                </a>
                <div class=\"fac-caption text-truncate text-center\"
                     title=\"${f.name}\">
                    ${f.name}
                </div>
                ${!f.hasThumb
                    ? '<div class=\"text-danger small text-center\">⚠️ pas de miniature</div>'
                    : ''}
            </div>
        `).join('');

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

        galleryPager.querySelectorAll('button[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.page;
                const maxPage = Math.ceil(galleryFiles.length / GALLERY_PAGE_SIZE);
                if (target === 'prev') {
                    galleryPage = Math.max(1, galleryPage - 1);
                } else if (target === 'next') {
                    galleryPage = Math.min(maxPage, galleryPage + 1);
                } else {
                    galleryPage = parseInt(target, 10) || 1;
                }
                updateGallery();
            });
        });
    }

});
</script>
<style>
    /* centre horizontalement toutes les vignettes */
    #gallery          { justify-content: center; }

    /* l’image tient dans 120 px de haut, reste centrée  */
    .fac-thumb        { max-height: 120px; object-fit: contain; display:block; margin:0 auto; }

    /* légende sous l’image */
    .fac-caption      { font-size: .75rem; }
</style>

@endpush
