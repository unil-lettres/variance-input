<!-- resources/views/components/main/media.blade.php -->
<div class="card mb-3" id="media-panel">
  <div class="card-header fw-bold">Médias</div>
  <div class="card-body">
    <!-- ROW 1 : Dropzones -->
    <div class="row g-4 mb-3">
      <div class="col-md-6">
        <label class="form-label">Vignette (jpg/png/webp ≤ 2 Mo)</label>
        <div id="vignette-dropzone" class="dropzone rounded-3 border border-2 text-center disabled" role="button" aria-label="Charger une vignette">
          <p class="mb-0">Glissez une image ici ou cliquez pour sélectionner un fichier</p>
          <input type="file" id="vignette-input" accept="image/*" class="d-none" />
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Fichier PDF (≤ 10 Mo)</label>
        <div id="pdf-dropzone" class="dropzone rounded-3 border border-2 text-center disabled" role="button" aria-label="Charger un PDF">
          <p class="mb-0">Glissez un PDF ici ou cliquez pour sélectionner un fichier</p>
          <input type="file" id="pdf-input" accept="application/pdf" class="d-none" />
        </div>
      </div>
    </div>

    <!-- ROW 2 : Previews -->
    <div class="row g-4 mb-2">
      <div class="col-md-6">
        <div id="vignette-preview" class="preview-box"></div>
      </div>
      <div class="col-md-6">
        <div id="pdf-preview" class="preview-box pdf"></div>
      </div>
    </div>

    <!-- ROW 3 : Filenames + Delete buttons -->
    <div class="row g-4">
      <div class="col-md-6 text-center">
        <div id="vignette-btn"></div>
      </div>
      <div class="col-md-6 text-center">
        <div id="pdf-btn"></div>
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
  :root {
    --media-box-h: 220px;
    --media-preview-w: 220px;
    --media-preview-h: 300px;
  }
  .dropzone {
    height: var(--media-box-h);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background .2s;
  }
  .dropzone.hover { background: #f8f9fa; }
  .dropzone.disabled { cursor: not-allowed; opacity:.5; }
  .preview-box {
    width: var(--media-preview-w);
    height: var(--media-preview-h);
    border: 1px solid #ced4da;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #fff;
    padding: .75rem;
    margin: 0 auto;
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
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.8.162/pdf.min.js"></script>
<script>
(() => {
  'use strict';
  let currentWorkId = null;
  let currentShortTitle = null;

  function clearMedia() {
    ['vignette','pdf'].forEach(type => {
      document.getElementById(`${type}-preview`).innerHTML = '';
      document.getElementById(`${type}-btn`).innerHTML = '';
    });
  }

  function renderMedia(type, fileUrl) {
    const preview = document.getElementById(`${type}-preview`);
    const btnHolder = document.getElementById(`${type}-btn`);
    preview.innerHTML = '';
    btnHolder.innerHTML = '';
    if (!fileUrl) return;

    if (type === 'vignette') {
      const img = document.createElement('img');
      img.src = fileUrl;
      preview.appendChild(img);
    } else {
      const canvas = document.createElement('canvas');
      canvas.style.maxWidth = '100%';
      canvas.style.maxHeight = '100%';
      preview.appendChild(canvas);
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

    const link = document.createElement('a');
    link.href = fileUrl;
    link.target = '_blank';
    link.textContent = fileUrl.split('/').pop();
    btnHolder.appendChild(link);

    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'btn btn-sm btn-danger mt-2';
    del.textContent = `Supprimer ${type}`;
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

  function upload(file, field) {
    if(!currentWorkId || !currentShortTitle) return alert('Sélectionnez d\'abord une œuvre');
    const max = field==='vignette'?2*1024*1024:10*1024*1024;
    if(file.size>max) return alert('Fichier trop volumineux');
    const fd = new FormData(); fd.append(field,file);
    fetch(`/api/works/${currentWorkId}/media?short_title=${encodeURIComponent(currentShortTitle)}`,{
      method:'POST', headers:{ 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content }, credentials:'same-origin', body:fd
    })
    .then(res=>{ if(!res.ok) throw new Error(); return res.json(); })
    .then(()=>reload()).catch(()=>alert('Échec de l\'upload'));
  }

  function deleteMedia(type) {
    if(!currentWorkId) return;
    fetch(`/api/works/${currentWorkId}/media/${type}`,{
      method:'DELETE', headers:{ 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content }, credentials:'same-origin'
    })
    .then(res=>{ if(!res.ok) throw new Error(); return res.json(); })
    .then(()=>reload()).catch(()=>alert('Suppression impossible'));
  }

  function reload() {
    if(!currentWorkId) { clearMedia(); return; }
    fetch(`/works/${currentWorkId}/media`,{ credentials:'same-origin' })
      .then(res=>res.json())
      .then(d=>{ renderMedia('vignette', d.image_url); renderMedia('pdf', d.pdf_url); })
      .catch(console.error);
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
    setupDropzone('vignette-dropzone','vignette-input','image/', f=>{ localPreview('vignette',f); upload(f,'vignette'); });
    setupDropzone('pdf-dropzone','pdf-input','application/pdf', f=>{ localPreview('pdf',f); upload(f,'pdf'); });
    updateDropzonesEnabled();
    document.addEventListener('workSelected', e=>{
      currentWorkId=e.detail.workId;
      currentShortTitle=e.detail.short_title||null;
      updateDropzonesEnabled();
      reload();
    });
  });
})();
</script>
@endpush
