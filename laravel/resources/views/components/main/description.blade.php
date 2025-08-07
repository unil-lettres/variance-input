<!-- resources/views/components/main/description.blade.php -->

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Description</span>
        <div class="d-flex gap-1">
            <button id="edit-button" type="button" class="btn btn-success btn-sm" style="display:none;">Éditer</button>
            <button id="save-button" type="button" class="btn btn-success btn-sm" style="display:none;">Enregistrer</button>
            <button id="cancel-button" type="button" class="btn btn-secondary btn-sm" style="display:none;">Annuler</button>
        </div>
    </div>
    <div class="card-body">
        <!-- CKEditor textarea -->
        <textarea id="desc-editor" rows="10"></textarea>
    </div>
</div>

@push('scripts')
<!-- 1) Load CKEditor 5 from CDN (Classic build) -->
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<script>
    let ckeditorInstance = null;   // CKEditor instance
    let currentWorkId    = null;   // currently selected work ID
    let isEditing        = false;  // tracks edit state

    document.addEventListener('DOMContentLoaded', () => {
        // 2) Initialise CKEditor on our textarea
        ClassicEditor
            .create(document.querySelector('#desc-editor'), {
                toolbar: [
                    'heading','|','bold','italic','link','bulletedList','numberedList','undo','redo'
                ]
            })
            .then(editor => {
                ckeditorInstance = editor;
                ckeditorInstance.enableReadOnlyMode('initialLoad'); // start read‑only
            })
            .catch(error => console.error('CKEditor error:', error));

        // 3) Cache action buttons
        const editBtn   = document.getElementById('edit-button');
        const saveBtn   = document.getElementById('save-button');
        const cancelBtn = document.getElementById('cancel-button');

        editBtn.addEventListener('click', enterEditMode);
        saveBtn.addEventListener('click', saveDescription);
        cancelBtn.addEventListener('click', cancelEdit);

        // 4) Global listener – reacts to work selection changes
        document.addEventListener('workSelected', event => {
            const { workId } = event.detail;

            // ── nothing selected: clear + lock editor ──
            if (!workId) {
                currentWorkId = null;

                if (isEditing) cancelEdit();

                if (ckeditorInstance) {
                    ckeditorInstance.setData('');
                    ckeditorInstance.enableReadOnlyMode('initialLoad');
                }

                editBtn.style.display   = 'none';
                saveBtn.style.display   = 'none';
                cancelBtn.style.display = 'none';
                return;
            }

            // ── a real work was selected ──
            if (isEditing) cancelEdit();

            currentWorkId = workId;
            fetchDescription(workId);

            editBtn.style.display = 'inline-block';
        });
    });

    // ======================
    //       EDIT MODE
    // ======================
    function enterEditMode() {
        if (!ckeditorInstance) return;
        isEditing = true;
        ckeditorInstance.disableReadOnlyMode('initialLoad');

        document.getElementById('edit-button').style.display   = 'none';
        document.getElementById('save-button').style.display   = 'inline-block';
        document.getElementById('cancel-button').style.display = 'inline-block';
    }

    function cancelEdit() {
        if (!ckeditorInstance) return;
        isEditing = false;
        ckeditorInstance.enableReadOnlyMode('initialLoad');

        // reload last saved content
        if (currentWorkId) fetchDescription(currentWorkId);
        else ckeditorInstance.setData('');

        document.getElementById('edit-button').style.display   = currentWorkId ? 'inline-block' : 'none';
        document.getElementById('save-button').style.display   = 'none';
        document.getElementById('cancel-button').style.display = 'none';
    }

    // ======================
    //   SAVE DESCRIPTION
    // ======================
    function saveDescription() {
        if (!ckeditorInstance || !currentWorkId) return;

        const updatedDesc = ckeditorInstance.getData();

        fetch(`/works/${currentWorkId}/description`, {
            method : 'POST',
            headers: {
                'Content-Type' : 'application/json',
                'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body   : JSON.stringify({ desc: updatedDesc })
        })
        .then(res => {
            if (!res.ok) throw new Error('Failed to save description');
            return res.json();
        })
        .then(() => alert('Description mise à jour !'))
        .catch(err => console.error('Error saving description:', err))
        .finally(() => {
            isEditing = false;
            ckeditorInstance.enableReadOnlyMode('initialLoad');

            document.getElementById('edit-button').style.display   = 'inline-block';
            document.getElementById('save-button').style.display   = 'none';
            document.getElementById('cancel-button').style.display = 'none';
        });
    }

    // ======================
    //   FETCH DESCRIPTION
    // ======================
    function fetchDescription(workId) {
        if (!workId || !ckeditorInstance) return;

        fetch(`/works/${workId}/description`)
            .then(res => res.json())
            .then(data => {
                ckeditorInstance.setData(data.description || '');
            })
            .catch(err => console.error('Error fetching description:', err));
    }
</script>
@endpush
