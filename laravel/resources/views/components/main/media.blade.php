<!-- resources/views/components/main/media.blade.php -->
<div class="card mb-3" id="media-panel">
  <div class="card-header fw-bold">Médias</div>
  <div class="card-body">
    <!-- ROW 1 : Dropzones -->
    <div class="row g-4 mb-3">
      <div class="col-md-6">
        <label class="form-label">Vignette (jpg/png/webp ≤ 2 Mo)</label>
        <div id="vignette-dropzone" class="dropzone rounded-3 border border-2 text-center" role="button" aria-label="Charger une vignette">
          <p class="mb-0">Glissez une image ici ou cliquez pour sélectionner un fichier</p>
          <input type="file" id="vignette-input" accept="image/*" class="d-none" />
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Fichier PDF (≤ 10 Mo)</label>
        <div id="pdf-dropzone" class="dropzone rounded-3 border border-2 text-center" role="button" aria-label="Charger un PDF">
          <p class="mb-0">Glissez un PDF ici ou cliquez pour sélectionner un fichier</p>
          <input type="file" id="pdf-input" accept="application/pdf" class="d-none" />
        </div>
      </div>
    </div>

    <!-- ROW 2 : Previews -->
    <div class="row g-4 mb-2">
      <div class="col-md-6">
        <div id="vignette-preview" class="preview-box"></div>
      </div>
      <div class="col-md-6">
        <div id="pdf-preview" class="preview-box pdf"></div>
      </div>
    </div>

    <!-- ROW 3 : Filenames + Delete buttons -->
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
  :root { --media-box-h: 350px; }

  /* Dropzones hauteur fixe */
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

  /* Previews hauteur fixe */
  .preview-box {
    height: var(--media-box-h);
    border: 1px solid #ced4da;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }
  .preview-box img{ width:100%; height:100%; object-fit:contain; }
  .preview-box embed{ height:100%; width:auto; border:none; }
  .preview-box canvas{ max-width:100%; max-height:100%; object-fit:contain; }
</style>
@endpush

@push('scripts')
<!-- pdf.js (thumbnail render) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.8.162/pdf.min.js"></script>
<script>
(() => {
  'use strict';

  window.currentWorkId      ??= null;
  window.currentShortTitle  ??= null;

  // --------------------------------------------------
  // Helpers to clear & render media
  // --------------------------------------------------
  function clearMedia() {
    ['vignette','pdf'].forEach(t => {
      document.getElementById(`${t}-preview`).innerHTML = '';
      document.getElementById(`${t}-btn`).innerHTML     = '';
    });
  }

  function renderMedia(type, fileUrl) {
    const preview   = document.getElementById(`${type}-preview`);
    const btnHolder = document.getElementById(`${type}-btn`);
    preview.innerHTML = '';
    btnHolder.innerHTML = '';
    if (!fileUrl) return;

    if (type === 'vignette') {
      const img = document.createElement('img');
      img.src = '/' + fileUrl;
      preview.appendChild(img);
    } else {
      const canvas = document.createElement('canvas');
      canvas.style.width  = '100%';
      canvas.style.height = '100%';
      preview.appendChild(canvas);
      pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.8.162/pdf.worker.min.js';
      pdfjsLib.GlobalWorkerOptions.useWorkerFetch = true;
      pdfjsLib.getDocument({ url: '/' + fileUrl, useWorkerFetch:true }).promise
        .then(pdf => pdf.getPage(1))
        .then(page => {
          const boxH = preview.clientHeight;
          const boxW = preview.clientWidth;
          const vp   = page.getViewport({ scale: 1 });
          const scale= Math.min(boxW/vp.width, boxH/vp.height)*(window.devicePixelRatio||1);
          const view = page.getViewport({ scale });
          canvas.width  = view.width;
          canvas.height = view.height;
          canvas.style.width  = (view.width /(window.devicePixelRatio||1))+'px';
          canvas.style.height = (view.height/(window.devicePixelRatio||1))+'px';
          return page.render({ canvasContext: canvas.getContext('2d'), viewport: view }).promise;
        })
        .catch(err => { console.error(err); preview.textContent = 'Prévisualisation indisponible'; });
    }

    // filename link
    const link = document.createElement('a');
    link.href = '/' + fileUrl;
    link.target = '_blank';
    link.textContent = fileUrl.split('/').pop();
    btnHolder.appendChild(link);

    // delete button
    const del = document.createElement('button');
    del.className = 'btn btn-sm btn-danger mt-2';
    del.textContent = `Supprimer ${type}`;
    del.onclick = () => deleteMedia(type);
    btnHolder.appendChild(del);
  }

  // --------------------------------------------------
  // Dropzone logic
  // --------------------------------------------------
  function setupDropzone(dzId, inputId, acceptPrefix, onSelect) {
    const dz    = document.getElementById(dzId);
    const input = document.getElementById(inputId);
    if (!dz || !input) return;

    const isEnabled = () => !!window.currentWorkId;

    dz.addEventListener('click', () => { if(isEnabled()) input.click(); });
    dz.addEventListener('dragover', e => { if(!isEnabled()) return; e.preventDefault(); dz.classList.add('hover'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('hover'));
    dz.addEventListener('drop', e => {
      if(!isEnabled()) return;
      e.preventDefault(); dz.classList.remove('hover');
      const f = e.dataTransfer.files[0];
      if (f && f.type.startsWith(acceptPrefix)) onSelect(f); else alert('Type de fichier invalide');
    });

    input.addEventListener('change', e => {
      const f = e.target.files[0];
      if (f && f.type.startsWith(acceptPrefix)) onSelect(f); else alert('Type de fichier invalide');
      input.value = '';
    });
  }

  function localPreview(type, file) {
    const preview = document.getElementById(`${type}-preview`);
    preview.innerHTML = '';
    if (file.type.startsWith('image/')) {
      const r = new FileReader();
      r.onload = () => { const img=document.createElement('img'); img.src=r.result; preview.appendChild(img); };
      r.readAsDataURL(file);
    } else {
      preview.textContent = file.name;
    }
  }

  // --------------------------------------------------
  // Upload / delete / reload
  // --------------------------------------------------
  function upload(file, field) {
    if (!currentWorkId || !currentShortTitle) return alert('Sélectionnez d\'abord une œuvre');

    // enforce size limits: 2 Mo image / 10 Mo PDF
    if ((field==='vignette' && file.size>2*1024*1024) || (field==='pdf' && file.size>10*1024*1024)) {
      return alert('Fichier trop volumineux');
    }

    const fd=new FormData(); fd.append(field, file);
    fetch(`/api/works/${currentWorkId}/media?short_title=${encodeURIComponent(currentShortTitle)}`,{
      method:'POST',
      headers:{ 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json' },
      credentials:'same-origin',
      body:fd
    }).then(r=>{ if(!r.ok) throw Error(r.status); return r.json(); })
      .then(reload).catch(err=>{ console.error(err); alert('Échec de l\'upload'); });
  }

  function deleteMedia(type) {
    if (!currentWorkId) return;
    fetch(`/api/works/${currentWorkId}/media/${type}`,{
      method:'DELETE',
      headers:{ 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json' },
      credentials:'same-origin'
    }).then(r=>{ if(!r.ok) throw Error(r.status); return r.json(); })
      .then(reload).catch(err=>{ console.error(err); alert('Suppression impossible'); });
  }

  function reload() {
    if (!currentWorkId) { clearMedia(); return; }
    fetch(`/works/${currentWorkId}/media`,{ headers:{'Accept':'application/json'}, credentials:'same-origin' })
      .then(r=>r.json())
      .then(d=>{ renderMedia('vignette', d.image_url); renderMedia('pdf', d.pdf_url); })
      .catch(console.error);
  }

  // --------------------------------------------------
  // init
  // --------------------------------------------------
  document.addEventListener('DOMContentLoaded', () => {
    setupDropzone('vignette-dropzone','vignette-input','image/',          f=>{ localPreview('vignette',f); upload(f,'vignette'); });
    setupDropzone('pdf-dropzone',     'pdf-input',     'application/pdf', f=>{ localPreview('pdf',f);      upload(f,'pdf');      });

    document.addEventListener('workSelected', e => {
      currentWorkId     = e.detail.workId;
      currentShortTitle = e.detail.short_title || null;
      reload();
    });
  });
})();
</script>
@endpush
