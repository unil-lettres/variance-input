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
        <div class="d-flex align-items-start gap-2 admin-card-heading">
            <span class="collapse-chevron" aria-hidden="true"></span>
            <span class="admin-card-heading-text">
                <span class="admin-card-title">Présentation de l’œuvre</span>
                <span class="admin-card-subtitle">Texte affiché dans la fiche publique</span>
            </span>
        </div>
        <span id="description-status-check" class="admin-card-check media-status-pill d-none" aria-label="Statut présentation"></span>
    </div>
    <div id="descriptionCollapse" class="collapse show">
    <div class="card-body">
        <p class="fst-italic text-muted small mb-3">
            Saisissez ici la présentation de l’œuvre telle qu’elle apparaîtra dans la fiche publique.
        </p>
        <div class="description-editor-shell" id="description-editor-shell">
            <div class="description-state-bar">
                <div class="description-state-copy">
                    <div class="description-state-kicker">État éditorial</div>
                    <div class="description-state-title" id="description-state-title">Aucune présentation enregistrée</div>
                    <div class="description-state-subtitle" id="description-state-subtitle">Commencez la rédaction pour préparer la fiche publique de l’œuvre.</div>
                </div>
                <div id="desc-status" class="description-state-feedback" style="display:none;"></div>
            </div>

            <div class="description-editor-frame">
                <textarea id="desc-editor" rows="10"></textarea>
            </div>

            <div class="description-action-bar" id="desc-controls">
                <div class="description-action-copy">Les modifications restent locales tant qu’elles ne sont pas enregistrées.</div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button id="save-button" type="button" class="btn btn-success btn-sm" style="display:none;">Enregistrer les modifications</button>
                    <button id="cancel-button" type="button" class="btn btn-outline-secondary btn-sm" style="display:none;">Annuler</button>
                </div>
            </div>
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
  .description-editor-shell {
    border: 1px solid #ddd6ca;
    border-radius: 0.95rem;
    background: linear-gradient(180deg, #fbfaf7 0%, #f3efe8 100%);
    overflow: hidden;
  }
  .description-state-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem 1rem;
    padding: 0.95rem 1.05rem;
    border-bottom: 1px solid #e0d9ce;
    background: rgba(255, 255, 255, 0.58);
  }
  .description-state-copy {
    min-width: 0;
    flex: 1 1 22rem;
  }
  .description-state-kicker {
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #7a7165;
  }
  .description-state-title {
    margin-top: 0.18rem;
    font-size: 0.95rem;
    font-weight: 700;
    color: #463f38;
  }
  .description-state-subtitle {
    margin-top: 0.2rem;
    font-size: 0.88rem;
    line-height: 1.45;
    color: #655d53;
  }
  .description-state-feedback {
    min-height: 2rem;
    padding: 0.28rem 0.75rem;
    border-radius: 999px;
    border: 1px solid #ddd6ca;
    background: rgba(255, 255, 255, 0.82);
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
  }
  .description-editor-frame {
    padding: 1rem 1.05rem 0;
  }
  .description-action-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem 1rem;
    padding: 0.95rem 1.05rem 1rem;
  }
  .description-action-copy {
    font-size: 0.84rem;
    line-height: 1.45;
    color: #62594f;
  }
  #description-editor-shell.is-editing .description-state-title {
    color: #3f3a34;
  }
  #description-editor-shell.has-dirty-state .description-state-bar {
    background: rgba(255, 248, 244, 0.9);
  }
  @media (max-width: 767.98px) {
    .description-state-feedback {
        white-space: normal;
    }
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
let stateTitleEl = null;
let stateSubtitleEl = null;
let editorShellEl = null;
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
    stateTitleEl = document.getElementById('description-state-title');
    stateSubtitleEl = document.getElementById('description-state-subtitle');
    editorShellEl = document.getElementById('description-editor-shell');
    updateStatusCheck(false, true);
    updateEditorStateSummary('empty');

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
            updateEditorStateSummary('empty');
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
    updateEditorStateSummary(lastSavedContent ? 'editing' : 'editing-empty');
}

function exitEditMode() {
    if (!ckeditorInstance) return;
    isEditing = false;
    ckeditorInstance.enableReadOnlyMode('initialLoad');
    if (saveBtn)   saveBtn.style.display   = 'none';
    if (cancelBtn) cancelBtn.style.display = 'none';
    updateEditorStateSummary(lastSavedContent ? 'saved' : 'empty');
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
        updateEditorStateSummary(lastSavedContent ? 'saved' : 'empty');
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
    updateEditorStateSummary(lastSavedContent ? 'saved' : 'empty');
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
            updateEditorStateSummary(lastSavedContent ? 'saved' : 'empty');
        })
        .catch(err => {
            console.error('Error fetching description:', err);
            setStatus('Erreur lors du chargement', 'error');
            updateEditorStateSummary('error');
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
        statusEl.className = 'description-state-feedback';
        return;
    }

    const base = ['description-state-feedback'];
    const cls  = variant === 'success' ? 'text-success'
               : variant === 'error'   ? 'text-danger'
               : variant === 'warning' ? 'text-danger'
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
        statusEl.className = 'description-state-feedback text-danger';
        statusEl.textContent = 'Le texte contient des modifications non enregistrées';
        statusEl.style.display = 'block';
        if (editorShellEl) {
            editorShellEl.classList.add('has-dirty-state');
        }
        updateEditorStateSummary('dirty');
        if (statusHideTimer) {
            clearTimeout(statusHideTimer);
            statusHideTimer = null;
        }
        return;
    }

    dirtyIndicatorVisible = false;
    if (editorShellEl) {
        editorShellEl.classList.remove('has-dirty-state');
    }
    if (statusEl.classList.contains('text-danger')) {
        statusEl.style.display = 'none';
        statusEl.textContent = '';
    }
}

function updateEditorStateSummary(state) {
    if (editorShellEl) {
        editorShellEl.classList.toggle('is-editing', state === 'editing' || state === 'editing-empty' || state === 'dirty');
    }
    if (!stateTitleEl || !stateSubtitleEl) return;

    if (state === 'saved') {
        stateTitleEl.textContent = 'Présentation prête pour la fiche publique';
        stateSubtitleEl.textContent = 'Le texte enregistré sera affiché dans la fiche publique de l’œuvre.';
        return;
    }
    if (state === 'editing') {
        stateTitleEl.textContent = 'Rédaction en cours';
        stateSubtitleEl.textContent = 'Vous modifiez une présentation déjà enregistrée.';
        return;
    }
    if (state === 'editing-empty') {
        stateTitleEl.textContent = 'Rédaction en cours';
        stateSubtitleEl.textContent = 'Vous préparez la première présentation publique de l’œuvre.';
        return;
    }
    if (state === 'dirty') {
        stateTitleEl.textContent = 'Modifications non enregistrées';
        stateSubtitleEl.textContent = 'Enregistrez ou annulez vos changements avant de quitter la rédaction.';
        return;
    }
    if (state === 'error') {
        stateTitleEl.textContent = 'Chargement incomplet';
        stateSubtitleEl.textContent = 'La présentation n’a pas pu être récupérée correctement pour cette œuvre.';
        return;
    }
    stateTitleEl.textContent = 'Aucune présentation enregistrée';
    stateSubtitleEl.textContent = 'Commencez la rédaction pour préparer la fiche publique de l’œuvre.';
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

    statusCheck.className = 'admin-card-check media-status-pill';
    statusCheck.textContent = '';

    if (hide) {
        statusCheck.classList.add('d-none');
        tootltipTitle = '';
        return;
    }

    if (hasContent) {
        statusCheck.classList.add('d-none');
        tootltipTitle = 'La présentation est complétée';
    } else {
        statusCheck.classList.add('admin-card-check--missing');
        statusCheck.textContent = 'Présentation manquante';
        tootltipTitle = 'La présentation est vide';
    }
}
</script>
@endpush
