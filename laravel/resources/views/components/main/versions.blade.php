<div class="card">
    <div class="card-header">
        Versions
    </div>
    <div class="card-body">
        <!-- Upload Form -->
        <form id="upload-version-form" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="versionFile" class="form-label">Sélectionner un ficher .txt à téléverser</label>
                <input type="file" name="versionFile" id="versionFile" class="form-control" accept=".txt" required>
            </div>

            <!-- 1) Version ID (used in final filename) -->
            <div class="mb-3">
                <label for="versionId" class="form-label">Identifiant de version</label>
                <input type="text" name="versionId" id="versionId" class="form-control" placeholder="1, 2, 3, ..." required>
            </div>

            <!-- 2) Edition Name (stored in DB 'name' field) -->
            <div class="mb-3">
                <label for="editionName" class="form-label">Editeur, année</label>
                <input type="text" name="editionName" id="editionName" class="form-control" placeholder="Grasset (1913)" required>
            </div>

            <button type="submit" class="btn btn-primary">Téléverser</button>
        </form>

        <hr>

        <!-- List of Versions -->
        <ul id="versions-list" class="list-group">
            <li class="list-group-item">Sélectionner une oeuvre pour voir les versions</li>
        </ul>
    </div>
</div>

<!-- EDIT Version Modal -->
<div class="modal fade" id="editVersionModal" tabindex="-1" aria-labelledby="editVersionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editVersionModalLabel">Editer le nom d'édition</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit-version-id">
        <!-- The DB 'name' field is the *edition name*, not the numeric ID -->
        <div class="mb-3">
            <label for="edit-version-name" class="form-label">Editeur/année</label>
            <input type="text" id="edit-version-name" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="update-version-btn">Update</button>
      </div>
    </div>
  </div>
</div>

<!-- DELETE Confirmation Modal -->
<div class="modal fade" id="deleteVersionConfirm" tabindex="-1" aria-labelledby="deleteVersionConfirmLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteVersionConfirmLabel">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete this version?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirm-delete-version">Delete</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<style>
    /* Make table headers match the card title styling */
    .version-table th {
        font-weight: normal;
        font-size: 1rem; /* Match Bootstrap's .card-header size */
        color: #333;
    }

    .version-table td {
        vertical-align: middle;
    }
</style>

<script>
let selectedWorkId = null;
let shortTitle = null;   // We'll use it for filenames: {versionId}{shortTitle}.txt
let authorId = null;
let versionToDelete = null;

document.addEventListener('DOMContentLoaded', () => {
    // 1) Listen for workSelected
    document.addEventListener('workSelected', event => {
        selectedWorkId = event.detail.workId;
        authorId = event.detail.authorId;
        shortTitle = event.detail.short_title || null; 
        fetchVersions(selectedWorkId);
    });

    // 2) Handle Upload
    document.getElementById('upload-version-form').addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!selectedWorkId) {
            alert('Please select a work before uploading a version.');
            return;
        }

        const file = document.getElementById('versionFile').files[0];
        const versionIdValue = document.getElementById('versionId').value.trim();
        const editionNameValue = document.getElementById('editionName').value.trim();

        if (!file || !versionIdValue || !editionNameValue) {
            alert('Please fill all fields.');
            return;
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('work_id', selectedWorkId);
        formData.append('versionFile', file);
        formData.append('version_id', versionIdValue);   // used to rename => {versionId}{shortTitle}.txt
        formData.append('name', editionNameValue);       // DB name field
        if (shortTitle) formData.append('short_title', shortTitle);

        try {
            const response = await fetch('/api/versions', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            });
            if (!response.ok) {
            const errorDetails = await response.json();
            console.error("Validation errors:", errorDetails);
            throw new Error(`Upload failed: ${response.status}`);
        }

            const data = await response.json();
            alert('Version uploaded successfully!');
            document.getElementById('upload-version-form').reset();
            fetchVersions(selectedWorkId);
        } catch (error) {
            console.error('Error uploading version:', error);
            alert('Failed to upload version. Please try again.');
        }
    });

    // 3) Edit
    document.getElementById('update-version-btn').addEventListener('click', updateVersionName);

    // 4) Delete
    document.getElementById('confirm-delete-version').addEventListener('click', doDeleteVersion);
});

async function fetchVersions(workId) {
    const versionsList = document.getElementById('versions-list');
    versionsList.innerHTML = '<div class="text-muted p-2">Loading versions...</div>';

    try {
        const response = await fetch(`/api/versions?work_id=${workId}`);
        if (!response.ok) throw new Error(`Failed to fetch versions: ${response.statusText}`);

        const data = await response.json();
        versionsList.innerHTML = '';

        if (data.length === 0) {
            versionsList.innerHTML = '<div class="text-muted p-2">No versions available</div>';
        } else {
            const table = document.createElement('table');
            table.className = 'table table-bordered table-hover table-sm version-table';

            // Table header
            table.innerHTML = `
                <thead class="table-light">
                    <tr>
                        <th>Editeur</th>
                        <th>Nom du fichier</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            `;

            const tbody = table.querySelector('tbody');

            data.forEach(version => {
                const tr = document.createElement('tr');

                // Edition name
                const nameTd = document.createElement('td');
                nameTd.textContent = version.name;
                tr.appendChild(nameTd);

                // Filename (extracted from folder path)
                const filename = version.folder.split('/').pop();
                const fileTd = document.createElement('td');
                fileTd.textContent = filename;
                tr.appendChild(fileTd);

                // Actions
                const actionsTd = document.createElement('td');
                actionsTd.className = 'text-end';

                const viewBtn = document.createElement('a');
                viewBtn.href = '/' + version.folder;
                viewBtn.target = '_blank';
                viewBtn.className = 'btn btn-sm btn-secondary me-1';
                viewBtn.textContent = 'View';

                const editBtn = document.createElement('button');
                editBtn.className = 'btn btn-sm btn-primary me-1';
                editBtn.textContent = 'Edit';
                editBtn.addEventListener('click', () => openEditModal(version));

                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'btn btn-sm btn-danger';
                deleteBtn.textContent = 'Delete';
                deleteBtn.addEventListener('click', () => confirmDeleteVersion(version));

                actionsTd.appendChild(viewBtn);
                actionsTd.appendChild(editBtn);
                actionsTd.appendChild(deleteBtn);

                tr.appendChild(actionsTd);
                tbody.appendChild(tr);
            });

            versionsList.appendChild(table);
        }
    } catch (error) {
        console.error('Error fetching versions:', error);
        versionsList.innerHTML = '<div class="text-danger p-2">Failed to load versions</div>';
    }
}


// ======================= OPEN EDIT MODAL
function openEditModal(version) {
    document.getElementById('edit-version-id').value = version.id;
    document.getElementById('edit-version-name').value = version.name; // edition name
    const editModal = new bootstrap.Modal(document.getElementById('editVersionModal'));
    editModal.show();
}

// ======================= UPDATE VERSION
async function updateVersionName() {
    const versionId = document.getElementById('edit-version-id').value;
    const newName = document.getElementById('edit-version-name').value.trim();
    if (!newName) {
        alert('Edition Name cannot be empty');
        return;
    }

    try {
        const response = await fetch(`/api/versions/${versionId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content
            },
            body: JSON.stringify({ name: newName })
        });
        if (!response.ok) throw new Error(`Update failed: ${response.status}`);

        const data = await response.json();
        console.log('Version updated =>', data);

        // close modal
        bootstrap.Modal.getInstance(document.getElementById('editVersionModal')).hide();
        fetchVersions(selectedWorkId);
    } catch (err) {
        console.error('Error updating version:', err);
        alert('Could not update version name.');
    }
}

// ======================= DELETE
function confirmDeleteVersion(version) {
    versionToDelete = version.id;
    const delModal = new bootstrap.Modal(document.getElementById('deleteVersionConfirm'));
    delModal.show();
}

async function doDeleteVersion() {
    if (!versionToDelete) return;

    try {
        const response = await fetch(`/api/versions/${versionToDelete}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content
            }
        });
        if (!response.ok) throw new Error(`Delete failed: ${response.status}`);

        const data = await response.json();
        console.log('Version deleted =>', data);

        // close the modal
        bootstrap.Modal.getInstance(document.getElementById('deleteVersionConfirm')).hide();
        fetchVersions(selectedWorkId);
    } catch (err) {
        console.error('Error deleting version:', err);
        alert('Could not delete version.');
    }
}
</script>
@endpush
