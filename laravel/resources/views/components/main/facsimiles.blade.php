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
        gallery.innerHTML = '';      // on vide la galerie
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
        if (versionSelect.value) loadGallery(versionSelect.value);
        else gallery.innerHTML = '';
    });
    imgInput.addEventListener('change', toggleBtn);

    /* ----------------------------------------------------------
     * 3. Charger la galerie d’images existantes
     * -------------------------------------------------------- */
    async function loadGallery(versionId) {
    gallery.innerHTML = '<div class="text-muted">Chargement…</div>';

    try {
        const res   = await fetch(`/api/facsimiles?version_id=${versionId}`,
                                  { headers:{Accept:'application/json'} });
        const files = await res.json();   // [{big, thumb, name, hasThumb}]

        if (!files.length) {
            gallery.innerHTML =
                '<div class="text-muted">Aucune image</div>';
            return;
        }

        gallery.innerHTML = files.map(f => `
            <div class="d-flex flex-column align-items-center"
                 style="width:125px">
                <a href="${f.big}" target="_blank" class="d-block mb-1">
                    <img src="${f.thumb || f.big}"
                         alt="${f.name}"
                         class="border rounded fac-thumb">
                </a>

                <div class="fac-caption text-truncate text-center"
                     title="${f.name}">
                    ${f.name}
                </div>

                ${!f.hasThumb
                    ? '<div class="text-danger small text-center">⚠️ pas de miniature</div>'
                    : ''}
            </div>
        `).join('');

    } catch (err) {
        console.error(err);
        gallery.innerHTML =
            '<div class="text-danger">Erreur de chargement</div>';
    }
}




    /* ----------------------------------------------------------
     * 4. Envoi des images
     * -------------------------------------------------------- */
    uploadBtn.addEventListener('click', async () => {

        const files = imgInput.files;
        if (!files.length || !versionSelect.value) return;

        const form = new FormData();
        form.append('version_id', versionSelect.value);
        for (const f of files) form.append('images[]', f);

        spinner.style.display = 'inline-block';
        uploadBtn.disabled    = true;
        logDiv.textContent    = '';

        try {
            const res  = await fetch('/api/upload_facsimiles', {
                method : 'POST',
                body   : form
            });
            const json = await res.json();

            if (!res.ok) throw new Error(json.message ?? 'Erreur inconnue');

            logDiv.textContent =
                `✅ ${json.files_added} fichier(s) importé(s) dans ${json.stored_in}`;

            imgInput.value = '';          // reset
            toggleBtn();
            /* re-chargement de la galerie */
            loadGallery(versionSelect.value);

        } catch (err) {
            console.error(err);
            logDiv.textContent = '❌ ' + err.message;
            uploadBtn.disabled = false;

        } finally {
            spinner.style.display = 'none';
        }
    });

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
