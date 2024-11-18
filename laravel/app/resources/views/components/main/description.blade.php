<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Description</span>
        <div class="d-flex gap-1">
            <button id="edit-button" type="button" class="btn btn-success btn-sm" onclick="toggleEditMode()">Éditer</button>
            <button id="save-button" type="button" class="btn btn-success btn-sm" style="display:none;" onclick="saveChanges()">Enregistrer</button>
            <button id="cancel-button" type="button" class="btn btn-secondary btn-sm" style="display:none;" onclick="cancelChanges()">Annuler</button>
        </div>
    </div>
    <div class="card-body">
        <textarea id="xml-editor" name="xml_content" rows="4" readonly>{{ old('xml_content', $xmlContent ?? '') }}</textarea>
    </div>
</div>

@push('scripts')
<script>
    var editor;
    document.addEventListener("DOMContentLoaded", function() {
        editor = CodeMirror.fromTextArea(document.getElementById("xml-editor"), {
            lineNumbers: false,
            lineWrapping: true,
            mode: "xml",
            theme: "default",
            readOnly: true
        });
    });

    function toggleEditMode() {
        editor.setOption("readOnly", false);
        toggleButtons(true);
    }

    function saveChanges() {

        const updatedDescription = editor.getValue();

        document.getElementById("xml-editor").value = updatedDescription;

        if (workId) {
            saveDescriptionToDB(workId, updatedDescription);
        }

        editor.setOption("readOnly", true);
        toggleButtons(false);
    }

    function saveDescriptionToDB(workId, description) {
        fetch(`/works/${workId}/description`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ desc: description })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to save description');
            }
            return response.json();
        })
        .then(data => {
            console.log('Description saved successfully:', data);
        })
        .catch(error => console.error('Error saving description:', error));
    }

    function cancelChanges() {
        editor.setValue(document.getElementById("xml-editor").value);
        editor.setOption("readOnly", true);
        toggleButtons(false);
    }

    function toggleButtons(isEditing) {
        document.getElementById("edit-button").style.display = isEditing ? "none" : "inline";
        document.getElementById("save-button").style.display = isEditing ? "inline" : "none";
        document.getElementById("cancel-button").style.display = isEditing ? "inline" : "none";
    }
    
    // Fetch description when work is selected
    document.addEventListener('workSelected', function(event) {
        workId = event.detail.workId;
        if (workId) {
            fetchDescription(workId);
        }
    });

    function fetchDescription(workId) {
        if (!workId) return;

        fetch(`/works/${workId}/description`)
            .then(response => response.json())
            .then(data => {
                editor.setValue(data.description);
            })
            .catch(error => console.error('Error fetching description:', error));
    }

</script>
<style>
    .CodeMirror.CodeMirror-readonly .CodeMirror-cursor {
        display: none !important;
        pointer-events: none;
        color: transparent;
    }
</style>
@endpush
