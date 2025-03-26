<!-- resources/views/components/main/description.blade.php -->

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Description</span>
        <div class="d-flex gap-1">
            <button id="edit-button" type="button" class="btn btn-success btn-sm">Éditer</button>
            <button id="save-button" type="button" class="btn btn-success btn-sm" style="display:none;">Enregistrer</button>
            <button id="cancel-button" type="button" class="btn btn-secondary btn-sm" style="display:none;">Annuler</button>
        </div>
    </div>
    <div class="card-body">
        <!-- Our textarea for CKEditor -->
        <textarea id="desc-editor" rows="10"></textarea>
    </div>
</div>

@push('scripts')
<!-- 1) Load CKEditor 5 from CDN (Classic build) -->
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<script>
    let ckeditorInstance = null;      // CKEditor instance
    let currentWorkId = null;         // Track selected work ID
    let isEditing = false;           // Whether user is in 'edit' mode

    document.addEventListener('DOMContentLoaded', () => {

        // 2) Initialize CKEditor on our textarea
        ClassicEditor
            .create(document.querySelector('#desc-editor'), {
                // optional config – specify toolbar items, language, etc.
                toolbar: [
                    'heading','|','bold','italic','link','bulletedList','numberedList','undo','redo'
                ]
            })
            .then(editor => {
                ckeditorInstance = editor;
                // Start in read-only mode
                ckeditorInstance.enableReadOnlyMode('initialLoad');
            })
            .catch(error => console.error('CKEditor error:', error));

        // 3) Hook up the top buttons
        const editBtn   = document.getElementById('edit-button');
        const saveBtn   = document.getElementById('save-button');
        const cancelBtn = document.getElementById('cancel-button');

        editBtn.addEventListener('click', () => enterEditMode());
        saveBtn.addEventListener('click', () => saveDescription());
        cancelBtn.addEventListener('click', () => cancelEdit());

        // 4) Listen for when a work is selected
        document.addEventListener('workSelected', event => {
            const { workId } = event.detail;
            if (workId) {
                currentWorkId = workId;
                fetchDescription(workId);
            }
        });
    });

    // ======================
    //       EDIT MODE
    // ======================
    function enterEditMode() {
        if (!ckeditorInstance) return;
        // Switch to 'editing' UI
        isEditing = true;
        ckeditorInstance.disableReadOnlyMode('initialLoad'); // now user can type

        // Show/hide relevant buttons
        document.getElementById('edit-button').style.display = 'none';
        document.getElementById('save-button').style.display = 'inline-block';
        document.getElementById('cancel-button').style.display = 'inline-block';
    }

    function cancelEdit() {
        if (!ckeditorInstance) return;
        // revert to read-only
        isEditing = false;
        ckeditorInstance.enableReadOnlyMode('initialLoad');
        // revert text to last known state
        // just re-fetch from server or store old content in a variable
        fetchDescription(currentWorkId);

        // Show/hide relevant buttons
        document.getElementById('edit-button').style.display = 'inline-block';
        document.getElementById('save-button').style.display = 'none';
        document.getElementById('cancel-button').style.display = 'none';
    }

    function saveDescription() {
        if (!ckeditorInstance) return;
        if (!currentWorkId) return;

        // 1) Get HTML from editor
        const updatedDesc = ckeditorInstance.getData();
        // 2) Save to DB
        fetch(`/works/${currentWorkId}/description`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ desc: updatedDesc })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to save description');
            }
            return response.json();
        })
        .then(data => {
            console.log('Description saved successfully:', data);
            alert('Description mise à jour !');
        })
        .catch(error => console.error('Error saving description:', error))
        .finally(() => {
            // read-only again
            isEditing = false;
            ckeditorInstance.enableReadOnlyMode('initialLoad');
            document.getElementById('edit-button').style.display = 'inline-block';
            document.getElementById('save-button').style.display = 'none';
            document.getElementById('cancel-button').style.display = 'none';
        });
    }

    // ======================
    //  FETCH DESCRIPTION
    // ======================
    function fetchDescription(workId) {
        if (!workId) return;
        fetch(`/works/${workId}/description`)
            .then(response => response.json())
            .then(data => {
                if (!ckeditorInstance) return;
                const description = data.description || '';
                ckeditorInstance.setData(description);
            })
            .catch(error => console.error('Error fetching description:', error));
    }
</script>
@endpush
