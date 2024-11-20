<div class="card">
    <div class="card-header">
        Versions
    </div>
    <div class="card-body">
        <!-- Upload Form -->
        <form id="upload-form" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="xmlFile" class="form-label">Upload XML File</label>
                <input type="file" name="xmlFile" id="xmlFile" class="form-control" accept=".xml" required>
            </div>
            <div class="mb-3">
                <label for="versionName" class="form-label">Version Name</label>
                <input type="text" name="versionName" id="versionName" class="form-control" placeholder="Enter version name" required>
            </div>
            <button type="submit" class="btn btn-primary">Upload</button>
        </form>

        <hr>

        <!-- List of Versions -->
        <ul id="versions-list" class="list-group">
            <li class="list-group-item">Select a work to see versions</li>
        </ul>
    </div>
</div>

<script>
    let selectedWorkId = null;

    // Listen for workSelected event
    document.addEventListener('workSelected', (event) => {
        selectedWorkId = event.detail.workId;
        fetchVersions(selectedWorkId);
    });

    async function fetchVersions(workId) {
    const versionsList = document.getElementById('versions-list');
    versionsList.innerHTML = '<li class="list-group-item">Loading...</li>';

    try {
        const response = await fetch(`/versions?work_id=${workId}`);

        if (!response.ok) {
            // Handle non-200 responses
            throw new Error(`Failed to fetch versions: ${response.statusText}`);
        }

        const contentType = response.headers.get('Content-Type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Invalid JSON response from server');
        }

        const data = await response.json();

        versionsList.innerHTML = '';

        if (data.length === 0) {
            versionsList.innerHTML = '<li class="list-group-item">No versions available</li>';
        } else {
            data.forEach(version => {
                const listItem = document.createElement('li');
                listItem.className = 'list-group-item';
                listItem.innerHTML = `
                    ${version.name}
                    <a href="/storage/${version.folder}/${version.name}" target="_blank" class="btn btn-sm btn-secondary float-end">View</a>
                `;
                versionsList.appendChild(listItem);
            });
        }
    } catch (error) {
        console.error('Error fetching versions:', error);
        versionsList.innerHTML = '<li class="list-group-item text-danger">Failed to load versions</li>';
    }
}



    // Handle form submission for uploading a new version
    document.getElementById('upload-form').addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!selectedWorkId) {
            alert('Please select a work before uploading.');
            return;
        }

        const formData = new FormData();
        const xmlFile = document.getElementById('xmlFile').files[0];
        const versionName = document.getElementById('versionName').value;

        formData.append('work_id', selectedWorkId);
        formData.append('xmlFile', xmlFile);
        formData.append('name', versionName);

        try {
            const response = await fetch('/versions', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
            });

            if (!response.ok) throw new Error('Failed to upload version');

            const data = await response.json();
            alert('Version uploaded successfully!');
            fetchVersions(selectedWorkId);
        } catch (error) {
            console.error('Error uploading version:', error);
            alert('Failed to upload version. Please try again.');
        }
    });
</script>
