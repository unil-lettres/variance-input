<!-- resources/views/components/main/media.blade.php -->
<div class="card" id="media-panel">
  <div
      class="card-header fw-semibold d-flex justify-content-between align-items-center"
  >
    <div class="d-flex align-items-start gap-2 admin-card-heading">
      <span class="admin-card-heading-text">
        <span class="admin-card-title">Media d'accompagnement</span>
      </span>
    </div>
    <div class="d-flex align-items-center gap-2 admin-card-checks" id="media-status-pills">
      <span id="media-status-vignette" class="admin-card-check media-status-pill d-none" aria-label="Statut description"></span>
      <span id="media-status-pdf" class="admin-card-check media-status-pill d-none" aria-label="Statut notice"></span>
    </div>
    </div>
    <div class="card-body">
    <p class="fst-italic text-muted small mb-3">
      Téléversez ici la vignette et la notice d’œuvre.
    </p>

    <div class="media-doc-grid">
      <section class="media-doc-card">
        <div class="media-doc-header">
          <div class="media-doc-kicker">Document</div>
          <h3 class="media-doc-title">Vignette</h3>
          <p class="media-doc-text">Image affichée dans la fiche publique de l’œuvre.</p>
        </div>
        <label class="form-label media-doc-label">Format jpg/png/webp, max. 2&nbsp;Mo</label>
        <div id="vignette-dropzone" class="dropzone rounded-3 border border-2 text-center disabled" role="button" aria-label="Charger une vignette">
          <p class="mb-0">Glissez une image ici ou cliquez pour sélectionner un fichier</p>
          <input type="file" id="vignette-input" accept="image/*" class="d-none" />
        </div>
        <div id="vignette-preview" class="preview-box"></div>
        <div id="vignette-btn"></div>
      </section>

      <section class="media-doc-card">
        <div class="media-doc-header">
          <div class="media-doc-kicker">Document</div>
          <h3 class="media-doc-title">Notice d’œuvre</h3>
          <p class="media-doc-text">PDF téléchargeable depuis la fiche publique.</p>
        </div>
        <label class="form-label media-doc-label">Format PDF, max. 10&nbsp;Mo</label>
        <div id="pdf-dropzone" class="dropzone rounded-3 border border-2 text-center disabled" role="button" aria-label="Charger un PDF">
          <p class="mb-0">Glissez un PDF ici ou cliquez pour sélectionner un fichier</p>
          <input type="file" id="pdf-input" accept="application/pdf" class="d-none" />
        </div>
        <div id="pdf-preview" class="preview-box pdf"></div>
        <div id="pdf-btn"></div>
      </section>
    </div>
  </div>
</div>

@push('styles')
<style>
  :root {
    --media-dropzone-height: 110px;
    --media-preview-max-width: 240px;
    --media-preview-max-height: 220px;
    --media-preview-max-height-pdf: 250px;
  }
  .media-doc-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
  }
  .media-doc-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1rem 1.05rem;
    border: 1px solid #ddd6ca;
    border-radius: 0.95rem;
    background: linear-gradient(180deg, #fbfaf7 0%, #f3efe8 100%);
  }
  .media-doc-header {
    display: grid;
    gap: 0.2rem;
  }
  .media-doc-kicker {
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #7a7165;
  }
  .media-doc-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #463f38;
  }
  .media-doc-text {
    margin: 0;
    font-size: 0.88rem;
    line-height: 1.45;
    color: #655d53;
  }
  .media-doc-label {
    margin-bottom: 0;
    font-size: 0.79rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #60584e;
  }
  .dropzone {
    min-height: var(--media-dropzone-height);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0.85rem;
    background: rgba(255, 255, 255, 0.75);
    transition: background .2s, border-color .2s, box-shadow .2s;
  }
  .dropzone.hover {
    background: #f8f9fa;
    border-color: rgba(13, 110, 253, 0.35) !important;
    box-shadow: 0 0 0 0.18rem rgba(13, 110, 253, 0.08);
  }
  .dropzone.disabled { cursor: not-allowed; opacity:.5; }
  .preview-box {
    width: min(100%, var(--media-preview-max-width));
    height: var(--media-preview-max-height);
    border: 1px solid #ced4da;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #fff;
    padding: .75rem;
    margin: 0 auto;
  }
  #vignette-btn,
  #pdf-btn {
    text-align: center;
    min-height: 2rem;
  }
  .preview-box img,
  .preview-box canvas,
  .preview-box embed {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
  }
  .preview-box.pdf {
    height: var(--media-preview-max-height-pdf);
  }
  .preview-box.pdf .pdf-preview-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    text-decoration: none;
    cursor: pointer;
  }
  .media-toggle .collapse-chevron::before {
    content: "\25BC";
    display: inline-block;
    transition: transform .2s ease;
  }
  .media-toggle[aria-expanded="false"] .collapse-chevron::before {
    transform: rotate(-90deg);
  }
  #mediaCollapse,
  #mediaCollapse *,
  #mediaCollapse.show,
  #mediaCollapse.show * {
    visibility: visible !important;
  }
  @media (max-width: 767.98px) {
    .media-doc-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.8.162/pdf.min.js"></script>
<script>
(() => {
  'use strict';
  let currentWorkId = null;
  let currentShortTitle = null;
  const setMediaLoading = (state) => {
    if (typeof window.setBladeLoading === 'function') {
      window.setBladeLoading('mediaCollapse', state);
    }
  };

  const statusLabels = { vignette: 'Vignette', pdf: 'Notice' };
  const statusMissingLabels = {
    vignette: 'Vignette manquante',
    pdf: 'Notice manquante'
  };
  const statusChecks = {
    vignette: document.getElementById('media-status-vignette'),
    pdf: document.getElementById('media-status-pdf')
  };
  const statusTooltip = { vignette: "", pdf: "" };

  new bootstrap.Tooltip(statusChecks.vignette, {
    title: () => statusTooltip.vignette,
    trigger: 'hover',
    delay: { "show": 500, "hide": 0 }
  });
  new bootstrap.Tooltip(statusChecks.pdf, {
    title: () => statusTooltip.pdf,
    trigger: 'hover',
    delay: { "show": 500, "hide": 0 }
  });

  updateMediaStatus('vignette', false, true);
  updateMediaStatus('pdf', false, true);

  function updateMediaStatus(type, hasFile, hide = false) {
    const check = statusChecks[type];
    if (!check) return;

    const label = statusLabels[type] || type;
    check.className = 'admin-card-check media-status-pill';
    check.textContent = '';

    if (hide) {
      check.classList.add('d-none');
      statusTooltip[type] = '';
      return;
    }

    if (hasFile) {
      statusTooltip[type] = `${label} : ajouté`;
      check.classList.add('d-none');
    } else {
      check.classList.add('admin-card-check--missing');
      check.textContent = statusMissingLabels[type] || `${label} manquante`;
      statusTooltip[type] = `${label} : manquant`;
    }
  }

  function clearMedia() {
    ['vignette','pdf'].forEach(type => {
      document.getElementById(`${type}-preview`).innerHTML = '';
      document.getElementById(`${type}-btn`).innerHTML = '';
      updateMediaStatus(type, false);
    });
  }

  function renderMedia(type, fileUrl) {
    const preview = document.getElementById(`${type}-preview`);
    const btnHolder = document.getElementById(`${type}-btn`);
    preview.innerHTML = '';
    btnHolder.innerHTML = '';
    updateMediaStatus(type, !!fileUrl);
    if (!fileUrl) return;

    if (type === 'vignette') {
      const img = document.createElement('img');
      img.src = fileUrl;
      preview.appendChild(img);
    } else {
      const link = document.createElement('a');
      link.href = fileUrl;
      link.target = '_blank';
      link.rel = 'noopener';
      link.className = 'pdf-preview-link';
      link.setAttribute('aria-label', 'Voir le PDF');
      link.title = 'Voir le PDF';
      const canvas = document.createElement('canvas');
      canvas.style.maxWidth = '100%';
      canvas.style.maxHeight = '100%';
      link.appendChild(canvas);
      preview.appendChild(link);
      pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.8.162/pdf.worker.min.js';
      pdfjsLib.GlobalWorkerOptions.useWorkerFetch = true;
      pdfjsLib.getDocument({ url: fileUrl, useWorkerFetch: true }).promise
        .then(pdf => pdf.getPage(1))
        .then(page => {
          const dpr = window.devicePixelRatio || 1;
          const styles = getComputedStyle(preview);
          const padX = parseFloat(styles.paddingLeft || 0) + parseFloat(styles.paddingRight || 0);
          const padY = parseFloat(styles.paddingTop || 0) + parseFloat(styles.paddingBottom || 0);
          const boxWidth = Math.max(preview.clientWidth - padX, 1);
          const boxHeight = Math.max(preview.clientHeight - padY, 1);
          const viewport = page.getViewport({ scale: 1 });
          const scaleForWidth = (boxWidth * dpr) / viewport.width;
          const scaleForHeight = (boxHeight * dpr) / viewport.height;
          const scale = Math.min(scaleForWidth, scaleForHeight);
          const view = page.getViewport({ scale });
          canvas.width = view.width;
          canvas.height = view.height;
          canvas.style.width = (view.width / dpr) + 'px';
          canvas.style.height = (view.height / dpr) + 'px';
          return page.render({ canvasContext: canvas.getContext('2d'), viewport: view }).promise;
        })
        .catch(() => { preview.textContent = 'Prévisualisation indisponible'; });
    }

    if (type === 'vignette') {
      const meta = document.createElement('div');
      meta.className = 'small text-muted text-center mb-1';
      meta.textContent = 'Image';
      btnHolder.appendChild(meta);
      updateVignetteMeta(meta, fileUrl);
    }
    if (type === 'pdf') {
      const meta = document.createElement('div');
      meta.className = 'small text-muted text-center mb-1';
      meta.textContent = 'PDF';
      btnHolder.appendChild(meta);
      updatePdfMeta(meta, fileUrl);
    }

    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'btn btn-sm btn-danger mt-2';
    del.textContent = type === 'vignette' ? 'Supprimer la vignette' : 'Supprimer la notice';
    del.addEventListener('click', () => deleteMedia(type));
    btnHolder.appendChild(del);
  }

  function setupDropzone(dzId, inputId, mimePrefix, onSelect) {
    const dz = document.getElementById(dzId);
    const input = document.getElementById(inputId);
    if (!dz || !input) return;
    const isEnabled = () => !!currentWorkId;
    dz.addEventListener('click', () => { if(isEnabled()) input.click(); });
    dz.addEventListener('dragover', e => { if(!isEnabled()) return; e.preventDefault(); dz.classList.add('hover'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('hover'));
    dz.addEventListener('drop', e => {
      if(!isEnabled()) return; e.preventDefault(); dz.classList.remove('hover');
      const file = e.dataTransfer.files[0];
      if(file && file.type.startsWith(mimePrefix)) onSelect(file); else alert('Type de fichier invalide');
    });
    input.addEventListener('change', e => {
      const file = e.target.files[0];
      if(file && file.type.startsWith(mimePrefix)) onSelect(file); else alert('Type de fichier invalide');
      input.value = '';
    });
  }

  function localPreview(type, file) {
    const preview = document.getElementById(`${type}-preview`);
    preview.innerHTML = '';
    if(file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = () => {
        const img = document.createElement('img');
        img.src = reader.result;
        img.style.maxWidth = '100%';
        img.style.maxHeight = '100%';
        preview.appendChild(img);
      };
      reader.readAsDataURL(file);
    } else preview.textContent = file.name;
  }

  const formatBytes = (size) => {
    const value = Number(size);
    if (!Number.isFinite(value) || value <= 0) return '';
    const units = ['o','Ko','Mo','Go'];
    let idx = 0;
    let current = value;
    while (current >= 1024 && idx < units.length - 1) {
      current /= 1024;
      idx++;
    }
    const precision = idx === 0 ? 0 : 1;
    return `${current.toFixed(precision)} ${units[idx]}`;
  };

  const detectImageFormat = (contentType, fileUrl) => {
    const type = (contentType || '').toLowerCase();
    if (type.includes('jpeg') || type.includes('jpg')) return 'JPG';
    if (type.includes('png')) return 'PNG';
    if (type.includes('webp')) return 'WEBP';
    const extMatch = (fileUrl || '').toLowerCase().match(/\.([a-z0-9]+)(?:\?|#|$)/);
    if (!extMatch) return 'Image';
    return extMatch[1].toUpperCase();
  };

  async function updateVignetteMeta(metaEl, fileUrl) {
    if (!metaEl) return;
    try {
      const res = await fetch(fileUrl, { method: 'HEAD' });
      if (!res.ok) {
        metaEl.textContent = detectImageFormat('', fileUrl);
        return;
      }
      const bytes = Number(res.headers.get('content-length') || 0);
      const format = detectImageFormat(res.headers.get('content-type'), fileUrl);
      const sizeLabel = formatBytes(bytes);
      metaEl.textContent = sizeLabel ? `${format} · ${sizeLabel}` : format;
    } catch (err) {
      metaEl.textContent = detectImageFormat('', fileUrl);
    }
  }

  async function updatePdfMeta(metaEl, fileUrl) {
    if (!metaEl) return;
    try {
      const res = await fetch(fileUrl, { method: 'HEAD' });
      if (!res.ok) {
        metaEl.textContent = 'PDF';
        return;
      }
      const bytes = Number(res.headers.get('content-length') || 0);
      const sizeLabel = formatBytes(bytes);
      metaEl.textContent = sizeLabel ? `PDF · ${sizeLabel}` : 'PDF';
    } catch (err) {
      metaEl.textContent = 'PDF';
    }
  }

  async function compressImageToJpeg(file, maxBytes) {
    const img = await loadImageBitmap(file);
    let scale = 1;
    let quality = 0.85;
    const minQuality = 0.5;
    const minScale = 0.5;
    let blob = null;

    for (let attempt = 0; attempt < 12; attempt++) {
      const canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.round(img.width * scale));
      canvas.height = Math.max(1, Math.round(img.height * scale));
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

      blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));
      if (blob && blob.size <= maxBytes) {
        break;
      }

      if (quality > minQuality) {
        quality = Math.max(minQuality, quality - 0.1);
      } else if (scale > minScale) {
        scale = Math.max(minScale, scale - 0.1);
        quality = 0.85;
      } else {
        break;
      }
    }

    if (!blob || blob.size > maxBytes) {
      throw new Error('compression_failed');
    }

    const baseName = file.name.replace(/\.[^.]+$/, '') || 'vignette';
    return new File([blob], `${baseName}.jpg`, { type: 'image/jpeg' });
  }

  function loadImageBitmap(file) {
    if (window.createImageBitmap) {
      return createImageBitmap(file);
    }
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = reject;
      const reader = new FileReader();
      reader.onload = () => { img.src = reader.result; };
      reader.onerror = reject;
      reader.readAsDataURL(file);
    });
  }

  async function handleVignetteSelection(file) {
    const max = 2 * 1024 * 1024;
    let uploadFile = file;
    if (file.size > max) {
      try {
        uploadFile = await compressImageToJpeg(file, max);
      } catch (err) {
        alert('Impossible de réduire la vignette sous 2 Mo. Veuillez choisir une image plus légère.');
        return;
      }
    }
    localPreview('vignette', uploadFile);
    upload(uploadFile, 'vignette');
  }

  function handlePdfSelection(file) {
    const max = 10 * 1024 * 1024;
    if (file.size > max) {
      const preview = document.getElementById('pdf-preview');
      if (preview) preview.innerHTML = '';
      alert('Notice PDF trop volumineuse (max. 10 Mo). Merci de compresser le fichier avant de téléverser.');
      return;
    }
    localPreview('pdf', file);
    upload(file, 'pdf');
  }

  function upload(file, field) {
    if(!currentWorkId || !currentShortTitle) return alert('Sélectionnez d\'abord une œuvre');
    const max = field==='vignette'?2*1024*1024:10*1024*1024;
    if(file.size>max) {
      if (field === 'pdf') {
        alert('Notice PDF trop volumineuse (max. 10 Mo). Merci de compresser le fichier avant de téléverser.');
      } else {
        alert('Fichier trop volumineux');
      }
      return;
    }
    const fd = new FormData(); fd.append(field,file);
    fetch(withBasePath(`/api/works/${currentWorkId}/media?short_title=${encodeURIComponent(currentShortTitle)}`),{
      method:'POST', headers:{ 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content }, credentials:'same-origin', body:fd
    })
    .then(res=>{ if(!res.ok) throw new Error(); return res.json(); })
    .then(()=>reload()).catch(()=>alert('Échec de l\'upload'));
  }

  function deleteMedia(type) {
    if(!currentWorkId) return;
    fetch(withBasePath(`/api/works/${currentWorkId}/media/${type}`),{
      method:'DELETE', headers:{ 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content }, credentials:'same-origin'
    })
    .then(res=>{ if(!res.ok) throw new Error(); return res.json(); })
    .then(()=>reload()).catch(()=>alert('Suppression impossible'));
  }

  function reload() {
    if(!currentWorkId) {
      clearMedia();
      updateMediaStatus('vignette', false, true);
      updateMediaStatus('pdf', false, true);
      setMediaLoading(false);
      return;
    }
    setMediaLoading(true);
    fetch(withBasePath(`/works/${currentWorkId}/media`),{ credentials:'same-origin' })
      .then(res=>res.json())
      .then(d=>{ renderMedia('vignette', d.image_url); renderMedia('pdf', d.pdf_url); })
      .catch(console.error)
      .finally(()=>{ setMediaLoading(false); });
  }

  const dropzoneIds = ['vignette-dropzone','pdf-dropzone'];
  function updateDropzonesEnabled() {
    dropzoneIds.forEach(id=>{
      const dz = document.getElementById(id);
      if(!dz)return;
      dz.classList.toggle('disabled',!currentWorkId);
    });
  }

  document.addEventListener('DOMContentLoaded',()=>{
    setupDropzone('vignette-dropzone','vignette-input','image/', f=>{ handleVignetteSelection(f); });
    setupDropzone('pdf-dropzone','pdf-input','application/pdf', f=>{ handlePdfSelection(f); });
    updateDropzonesEnabled();
    document.addEventListener('workSelected', e=>{
      currentWorkId=e.detail.workId;
      currentShortTitle=e.detail.short_title||null;
      updateDropzonesEnabled();
      if(!currentWorkId){
        updateMediaStatus('vignette', false, true);
        updateMediaStatus('pdf', false, true);
      } else {
        reload();
      }
    });
  });
})();
</script>
@endpush
