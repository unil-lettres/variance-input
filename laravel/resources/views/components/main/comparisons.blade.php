<div class="card mb-3">
    <div class="card-header">Comparaisons</div>
    <div class="card-body">
        <div id="comparisons-loading" class="mb-3" style="display:none;">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            Chargement des comparaisons...
        </div>

        <table class="table table-sm table-bordered align-middle comparisons-table" id="comparisons-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Source</th>
                    <th>Cible</th>
                    <th>Folder</th>
                    <th>Ratio</th>
                    <th>Seuil</th>
                    <th>Pivot</th>
                    <th>Sens. Casse</th>
                    <th>Sens. Diac.</th>
                    <th>Date</th>
                    <th>Résultats</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

        <div id="no-comparisons" style="display:none;" class="text-muted">
            Aucune comparaison trouvée pour cette œuvre.
        </div>
    </div>
</div>

@push('scripts')
<style>
    .comparisons-table th {
        font-weight: normal;
        font-size: 1rem; /* Match Bootstrap card headers */
        color: #333;
    }

    .comparisons-table td {
        vertical-align: middle;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const comparisonsTable = document.querySelector('#comparisons-table tbody');
    const loading = document.getElementById('comparisons-loading');
    const noComparisons = document.getElementById('no-comparisons');

    /**
     * Load comparisons for a specific work,
     * and populate the table.
     */
    async function loadComparisons(workId) {
        loading.style.display = 'block';
        comparisonsTable.innerHTML = '';
        noComparisons.style.display = 'none';

        try {
            const res = await fetch(`/comparisons/by-work?work_id=${workId}`);
            const comparisons = await res.json();

            if (comparisons.length === 0) {
                noComparisons.style.display = 'block';
                return;
            }

            comparisons.forEach(comp => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${comp.id}</td>
                    <td>${comp.source_version?.name || comp.source_id}</td>
                    <td>${comp.target_version?.name || comp.target_id}</td>
                    <td>${comp.folder}</td>
                    <td>${comp.ratio}</td>
                    <td>${comp.seuil}</td>
                    <td>${comp.lg_pivot}</td>
                    <td>${comp.case_sensitive ? 'yes' : 'no'}</td>
                    <td>${comp.diacri_sensitive ? 'yes' : 'no'}</td>
                    <td>${new Date(comp.created_at).toLocaleString()}</td>
                    <td>
                        <a href="/storage/${comp.folder}/${comp.id}.html" 
                           class="btn btn-sm btn-outline-primary" 
                           target="_blank">
                           HTML
                        </a>
                        <a href="/storage/${comp.folder}/${comp.id}.xml" 
                           class="btn btn-sm btn-outline-secondary" 
                           target="_blank">
                           XML
                        </a>
                        <button class="btn btn-sm btn-outline-danger ms-1 delete-comparison-btn" 
                                data-id="${comp.id}">
                            🗑️
                        </button>
                    </td>
                `;
                comparisonsTable.appendChild(tr);
            });
        } catch (err) {
            console.error('Erreur lors du chargement des comparaisons:', err);
        } finally {
            loading.style.display = 'none';
        }
    }

    // Listen for a selected work
    document.addEventListener('workSelected', e => {
        const workId = e.detail.workId;
        loadComparisons(workId);
    });

    // Listen for a new comparison
    document.addEventListener('comparisonCreated', e => {
        const workId = e.detail.workId;
        loadComparisons(workId);
    });
    
    // When a version is added / deleted / renamed, refresh comparisons
    document.addEventListener('versionsUpdated', e => {
        const workId = e.detail.workId;
        loadComparisons(workId);
    });

    /**
     * Delete Comparison Handler
     *  - uses event delegation
     *  - if .delete-comparison-btn is clicked, do an AJAX DELETE
     */
    document.addEventListener('click', async (event) => {
        const deleteBtn = event.target.closest('.delete-comparison-btn');
        if (!deleteBtn) return;

        const comparisonId = deleteBtn.dataset.id;
        const confirmDelete = confirm(`Voulez-vous vraiment supprimer la comparaison #${comparisonId}?`);
        if (!confirmDelete) return;

        try {
            const response = await fetch(`/comparisons/${comparisonId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const errorBody = await response.text();
                throw new Error(errorBody);
            }

            // Remove the row from the table
            deleteBtn.closest('tr').remove();

        } catch (err) {
            console.error('Erreur lors de la suppression de la comparaison:', err);
            alert('Impossible de supprimer cette comparaison. Voir la console pour plus de détails.');
        }
    });
});
</script>
@endpush
