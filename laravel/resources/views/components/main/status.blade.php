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
(function () {
  function isValidWorkId(id) {
    const s = String(id ?? '').trim();
    return /^\d+$/.test(s) && Number(s) > 0;
  }

  function resetStatusUI() {
    ['description-toggle','notice-toggle','vignette-toggle','comparisons-toggle'].forEach(id => {
      const el = document.getElementById(id);
      if (el) { el.checked = false; el.disabled = true; }
    });
    const msg = document.getElementById('edit-rights-message');
    if (msg) msg.style.display = 'none'; // hide “Lecture seule” when no selection
    const wc = document.getElementById('work-container');
    if (wc) wc.setAttribute('data-current-work-id', '');
  }

  async function setToggles(workId, canEdit) {
    if (!isValidWorkId(workId)) { resetStatusUI(); return; }

    try {
      const res = await fetch(`/works/${workId}/status`, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      document.getElementById('description-toggle').checked  = !!data.desc_status;
      document.getElementById('notice-toggle').checked       = !!data.notice_status;
      document.getElementById('vignette-toggle').checked     = !!data.image_status;
      document.getElementById('comparisons-toggle').checked  = !!data.comparison_status;

      enableToggles(!!canEdit); // keep your rights logic
    } catch (err) {
      console.error('Error fetching work status:', err);
      resetStatusUI();
    }
  }

  function enableToggles(canEdit) {
    document.querySelectorAll('#work-container .form-check-input').forEach(t => {
      t.disabled = !canEdit;
    });
  }

  // Listen for selection changes from work_selector.js
  document.addEventListener('workSelected', function (event) {
    const { workId, canEdit } = event.detail || {};
    const workContainer = document.getElementById('work-container');
    const messageElement = document.getElementById('edit-rights-message');

    // Remember current workId (blank if invalid)
    workContainer.setAttribute('data-current-work-id', isValidWorkId(workId) ? String(workId) : '');

    // No valid selection → reset and quit
    if (!isValidWorkId(workId)) {
      if (messageElement) messageElement.style.display = 'none';
      resetStatusUI();
      return;
    }

    // With a selection: update UI + rights hint
    if (messageElement) messageElement.style.display = canEdit ? 'none' : 'inline';
    enableToggles(!!canEdit);
    setToggles(workId, !!canEdit);
  });

  // Attach change handlers for the 4 toggles
  document.addEventListener('DOMContentLoaded', function () {
    const toggles = [
      { id: 'description-toggle', field: 'desc_status' },
      { id: 'notice-toggle',      field: 'notice_status' },
      { id: 'vignette-toggle',    field: 'image_status' },
      { id: 'comparisons-toggle', field: 'comparison_status' },
    ];

    toggles.forEach(({ id, field }) => {
      const el = document.getElementById(id);
      if (!el) return;

      el.addEventListener('change', function () {
        const workContainer = document.getElementById('work-container');
        const workId = workContainer.getAttribute('data-current-work-id');

        if (!isValidWorkId(workId)) {
          console.error('No valid work ID available for update.');
          return;
        }

        const body = {};
        body[field] = el.checked ? 1 : 0;
        updateStatus(workId, body);
      });
    });
  });

  function updateStatus(workId, body) {
    if (!isValidWorkId(workId)) return;

    fetch(`/works/${workId}/status`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json'
      },
      body: JSON.stringify(body),
    })
    .then((response) => {
      if (!response.ok) throw new Error('Failed to update status');
      return response.json();
    })
    .then((data) => {
      console.log('Status updated successfully:', data);
    })
    .catch((error) => console.error('Error updating status:', error));
  }
})();
</script>
@endpush
