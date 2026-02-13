<!-- resources/views/components/main/description.blade.php -->

<div class="card">
    <div
        class="card-header d-flex justify-content-between align-items-center fw-semibold description-toggle"
        role="button"
        data-bs-toggle="collapse"
        data-bs-target="#descriptionCollapse"
        aria-expanded="true"
        aria-controls="descriptionCollapse"
    >
        <div class="d-flex align-items-center gap-2">
            <span class="collapse-chevron" aria-hidden="true"></span>
            <span>Description</span>
        </div>
        <span id="description-status-check" class="admin-card-check" aria-label="Statut description">&#10003;</span>
    </div>
    <div id="descriptionCollapse" class="collapse show">
    <div class="card-body">
        <p class="fst-italic text-muted small mb-3">
            Saisissez ici la description de l'oeuvre qui sera visible dans la fiche d'oeuvre du catalogue public.
        </p>
        <!-- CKEditor textarea -->
        <textarea id="desc-editor" rows="10"></textarea>
        <!-- Action buttons -->
        <div class="mt-3" id="desc-controls">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button id="save-button" type="button" class="btn btn-success btn-sm" style="display:none;">Enregistrer les modifications</button>
                <button id="cancel-button" type="button" class="btn btn-outline-secondary btn-sm" style="display:none;">Annuler</button>
            </div>
            <div id="desc-status" class="small text-muted mt-2" style="display:none;"></div>
        </div>
    </div>
    </div>
</div>

@push('styles')
<style>
  .description-toggle .collapse-chevron::before {
    content: "\25BC";
    display: inline-block;
    transition: transform .2s ease;
  }
  .description-toggle[aria-expanded="false"] .collapse-chevron::before {
    transform: rotate(-90deg);
  }
  #descriptionCollapse,
  #descriptionCollapse *,
  #descriptionCollapse.show,
  #descriptionCollapse.show * {
    visibility: visible !important;
  }
</style>
@endpush

@push('scripts')
<!-- 1) Load CKEditor 5 from CDN (Classic build) -->
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<script>
const STATUS_HIDE_DELAY = 2500;
let ckeditorInstance = null;
let currentWorkId = null;
let isEditing = false;
let lastSavedContent = '';
let queuedWorkId = null;
let statusHideTimer = null;
let saveBtn = null;
let cancelBtn = null;
let statusEl = null;
let dirtyIndicatorVisible = false;
let statusCheck = null;
const setDescriptionLoading = (state) => {
    if (typeof window.setBladeLoading === 'function') {
        window.setBladeLoading('descriptionCollapse', state);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
    }, 1500);
    saveBtn   = document.getElementById('save-button');
    cancelBtn = document.getElementById('cancel-button');
    statusEl  = document.getElementById('desc-status');
    statusCheck = document.getElementById('description-status-check');
    updateStatusCheck(false, true);

    ClassicEditor
        .create(document.querySelector('#desc-editor'), {
            toolbar: [
                'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'undo', 'redo'
            ]
        })
        .then(editor => {
            ckeditorInstance = editor;
            ckeditorInstance.enableReadOnlyMode('initialLoad');

            const editableEl = editor.ui.view.editable.element;
            editableEl.addEventListener('focus', enterEditMode);
            editableEl.addEventListener('click', enterEditMode);

            editor.model.document.on('change:data', () => {
                if (!isEditing) return;
                const currentData = ckeditorInstance.getData();
                const hasChanges = currentData !== lastSavedContent;
                toggleDirtyIndicator(hasChanges);
            });

            if (queuedWorkId !== null) {
                if (queuedWorkId) {
                    currentWorkId = queuedWorkId;
                    fetchDescription(queuedWorkId);
                } else {
                    ckeditorInstance.setData('');
                }
                queuedWorkId = null;
            }
        })
        .catch(error => console.error('CKEditor error:', error));

    if (saveBtn) {
        saveBtn.addEventListener('click', saveDescription);
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', cancelEdit);
    }

    document.addEventListener('workSelected', event => {
            const { workId } = event.detail;
        currentWorkId = workId || null;

        if (!ckeditorInstance) {
            queuedWorkId = currentWorkId;
            return;
        }

        if (!currentWorkId) {
            lastSavedContent = '';
            ckeditorInstance.setData('');
            ckeditorInstance.enableReadOnlyMode('initialLoad');
            exitEditMode();
            toggleDirtyIndicator(false);
            updateStatusCheck(false, true);
            setStatus('');
            setDescriptionLoading(false);
            return;
        }

        if (isEditing) {
            cancelEdit();
        }

        fetchDescription(currentWorkId);
    });
});

function enterEditMode() {
    if (!ckeditorInstance || !currentWorkId || isEditing) return;
    isEditing = true;
    ckeditorInstance.disableReadOnlyMode('initialLoad');
    if (saveBtn)   saveBtn.style.display   = 'inline-block';
    if (cancelBtn) cancelBtn.style.display = 'inline-block';
    toggleDirtyIndicator(false);
    setStatus('');
}

function exitEditMode() {
    if (!ckeditorInstance) return;
    isEditing = false;
    ckeditorInstance.enableReadOnlyMode('initialLoad');
    if (saveBtn)   saveBtn.style.display   = 'none';
    if (cancelBtn) cancelBtn.style.display = 'none';
}

function saveDescription() {
    if (!ckeditorInstance || !currentWorkId) return;
    const updatedDesc = ckeditorInstance.getData();
    setStatus('Enregistrement…');

    fetch(withBasePath(`/works/${currentWorkId}/description`), {
        method : 'POST',
        headers: {
            'Content-Type' : 'application/json',
            'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body   : JSON.stringify({ desc: updatedDesc })
    })
    .then(async res => {
        const contentType = res.headers.get('content-type') || '';
        let payload = null;
        if (contentType.includes('application/json')) {
            payload = await res.json().catch(() => null);
        } else {
            const text = await res.text();
            payload = text ? { message: text } : null;
        }
        if (!res.ok) {
            const message = payload?.error || payload?.message || "Erreur lors de l'enregistrement";
            throw new Error(message);
        }
        return payload;
    })
    .then(() => {
        lastSavedContent = updatedDesc;
        toggleDirtyIndicator(false);
        updateStatusCheck(!!lastSavedContent);
        setStatus('Modifications enregistrées', 'success', true);
        exitEditMode();
    })
    .catch(err => {
        console.error('Error saving description:', err);
        setStatus(err?.message || "Erreur lors de l'enregistrement", 'error');
    });
}

function cancelEdit() {
    if (!ckeditorInstance) return;
    ckeditorInstance.setData(lastSavedContent || '');
    toggleDirtyIndicator(false);
    updateStatusCheck(!!lastSavedContent);
    setStatus('Modifications annulées', 'muted', true);
    exitEditMode();
}

function fetchDescription(workId) {
    if (!ckeditorInstance || !workId) return;
    setStatus('Chargement…');
    setDescriptionLoading(true);

    fetch(withBasePath(`/works/${workId}/description`))
        .then(res => res.json())
        .then(data => {
            lastSavedContent = data.description || '';
            ckeditorInstance.setData(lastSavedContent);
            exitEditMode();
            toggleDirtyIndicator(false);
            updateStatusCheck(!!lastSavedContent);
            setStatus('', 'muted');
        })
        .catch(err => {
            console.error('Error fetching description:', err);
            setStatus('Erreur lors du chargement', 'error');
        })
        .finally(() => {
            setDescriptionLoading(false);
        });
}

function setStatus(message = '', variant = 'muted', autoHide = false) {
    if (!statusEl) return;
    if (statusHideTimer) {
        clearTimeout(statusHideTimer);
        statusHideTimer = null;
    }

    if (!message) {
        statusEl.style.display = 'none';
        statusEl.textContent = '';
        statusEl.className = 'small text-muted mt-2';
        return;
    }

    const base = ['small', 'mt-2'];
    const cls  = variant === 'success' ? 'text-success'
               : variant === 'error'   ? 'text-danger'
               : 'text-muted';
    statusEl.className = [...base, cls].join(' ');
    statusEl.textContent = message;
    statusEl.style.display = 'block';

    if (autoHide) {
        statusHideTimer = setTimeout(() => setStatus(''), STATUS_HIDE_DELAY);
    }
}

function toggleDirtyIndicator(hasChanges) {
    if (!statusEl) return;
    if (hasChanges) {
        dirtyIndicatorVisible = true;
        statusEl.className = 'small text-danger mt-2';
        statusEl.textContent = 'Le texte contient des modifications non enregistrées';
        statusEl.style.display = 'block';
        if (statusHideTimer) {
            clearTimeout(statusHideTimer);
            statusHideTimer = null;
        }
        return;
    }

    dirtyIndicatorVisible = false;
    if (statusEl.classList.contains('text-danger')) {
        statusEl.style.display = 'none';
        statusEl.textContent = '';
    }
}

let tootltipTitle = "";
if (document.getElementById('description-status-check')) {
    new bootstrap.Tooltip(document.getElementById('description-status-check'), {
        title: () => tootltipTitle,
        trigger: 'hover',
        delay: { "show": 500, "hide": 0 }
    });
}

function updateStatusCheck(hasContent, hide = false) {
    if (!statusCheck) return;

    statusCheck.className = 'admin-card-check';
    statusCheck.innerHTML = '&#10003;';

    if (hide) {
        statusCheck.classList.add('d-none');
        tootltipTitle = '';
        return;
    }

    if (hasContent) {
        statusCheck.classList.add('admin-card-check--done');
        tootltipTitle = 'La description est complétée';
    } else {
        tootltipTitle = 'La description est vide';
    }
}
</script>
@endpush
