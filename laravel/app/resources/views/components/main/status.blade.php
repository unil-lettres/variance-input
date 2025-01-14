<div class="card mb-3" id="work-container" data-current-work-id="">
    <div class="row g-0">
        <div class="col-md-6">
            <div class="card-header d-flex align-items-center">
                <strong class="me-2">Statut:</strong>
                <span class="badge bg-warning text-dark me-2" style="border-radius: 0;">En cours d'élaboration</span>
                <p id="edit-rights-message" class="mb-0" style="display: none; color: black;">
                    🔒 Lecture seule
                </p>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Statut</th>
                            <th scope="col">Déposé</th>
                            <th scope="col">Prêt publication</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Description</td>
                            <td>✔️</td>
                            <td><input type="checkbox" class="form-check-input" id="description-toggle"></td>
                        </tr>
                        <tr>
                            <td>Notice</td>
                            <td>✔️</td>
                            <td><input type="checkbox" class="form-check-input" id="notice-toggle" checked></td>
                        </tr>
                        <tr>
                            <td>Vignette principale</td>
                            <td></td>
                            <td><input type="checkbox" class="form-check-input" id="vignette-toggle" disabled></td>
                        </tr>
                        <tr>
                            <td>Comparaisons</td>
                            <td>✔️</td>
                            <td><input type="checkbox" class="form-check-input" id="comparisons-toggle"></td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="btn btn-outline-primary">Publier</button>
            </div>
        </div>

        <div class="col-md-6">
        </div>
    </div>
</div>


@push('scripts')
<script>
 // Listen for the workSelected event
document.addEventListener('workSelected', function (event) {
    const { workId, canEdit } = event.detail;

    // Store workId in the container's data attribute
    const workContainer = document.getElementById('work-container');
    workContainer.setAttribute('data-current-work-id', workId);

    // Handle edit rights message
    const messageElement = document.getElementById('edit-rights-message');
    if (canEdit) {
        messageElement.style.display = "none";
        enableToggles(true);
    } else {
        messageElement.style.display = "inline";
        enableToggles(false);
    }

    // Set toggles based on work status
    setToggles(workId, canEdit);
});

function setToggles(workId, canEdit) {
    fetch(`/works/${workId}/status`)
        .then((response) => response.json())
        .then((data) => {
            document.getElementById('description-toggle').checked = !!data.desc_status;
            document.getElementById('notice-toggle').checked = !!data.notice_status;
            document.getElementById('vignette-toggle').checked = !!data.image_status;
            document.getElementById('comparisons-toggle').checked = !!data.comparison_status;

            enableToggles(canEdit);
        })
        .catch((error) => console.error('Error fetching work status:', error));
}

function enableToggles(canEdit) {
    document.querySelectorAll('.form-check-input').forEach((toggle) => {
        toggle.disabled = !canEdit;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    // Add event listeners to each toggle to send updates
    const toggles = [
        { id: 'description-toggle', field: 'desc_status' },
        { id: 'notice-toggle', field: 'notice_status' },
        { id: 'vignette-toggle', field: 'image_status' },
        { id: 'comparisons-toggle', field: 'comparison_status' },
    ];

    toggles.forEach((toggle) => {
        const element = document.getElementById(toggle.id);

        if (element) {
            element.addEventListener('change', function () {
                // Get the current workId from the container
                const workContainer = document.getElementById('work-container');
                const workId = workContainer.getAttribute('data-current-work-id');

                if (!workId) {
                    console.error('No work ID available for update.');
                    return;
                }

                const body = {};
                body[toggle.field] = element.checked ? 1 : 0;

                updateStatus(workId, body);
            });
        }
    });
});

function updateStatus(workId, body) {
    fetch(`/works/${workId}/status`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify(body),
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Failed to update status');
            }
            return response.json();
        })
        .then((data) => {
            console.log('Status updated successfully:', data);
        })
        .catch((error) => console.error('Error updating status:', error));
}

</script>
@endpush
