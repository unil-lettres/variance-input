<div class="card">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center gap-3">
        <div class="comparisons-toggle d-flex align-items-center flex-grow-1"
             role="button"
             data-bs-toggle="collapse"
             data-bs-target="#comparisonsCollapse"
             aria-expanded="true"
             aria-controls="comparisonsCollapse">
        <div class="d-flex align-items-start gap-2 admin-card-heading">
            <span class="collapse-chevron" aria-hidden="true"></span>
            <span class="admin-card-heading-text">
                <span class="admin-card-title">Comparaisons</span>
            </span>
        </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="form-check form-switch comparison-details-toggle mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="comparison-details-toggle">
                <label class="form-check-label small fw-semibold" for="comparison-details-toggle">Détails</label>
            </div>
            <button type="button"
                    class="btn btn-sm btn-primary"
                    id="open-medite-modal-btn"
                    data-bs-toggle="modal"
                    data-bs-target="#mediteModal"
                    aria-label="Lancer un alignement">
                Lancer un alignement
            </button>
        </div>
    </div>

    <div id="comparisonsCollapse" class="collapse show">
    <div class="card-body">
        <!-- Spinner while loading -->
        <div id="comparisons-loading" class="mb-3" style="display:none;">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            Chargement des comparaisons...
        </div>

        <!-- Table -->
        <div class="table-responsive comparisons-table-wrap">
            <table class="table table-sm table-bordered comparisons-table" id="comparisons-table">
                <thead>
                    <tr>
                        <th>Désignation</th>
                        <th>Source</th>
                        <th>Cible</th>
                        <th>Paramètres Medite</th>
                        <th class="comparison-data-col">Données</th>
                        <th>Suppr.</th>
                        <th>Insert.</th>
                        <th>Rempl.</th>
                        <th>Dépl.</th>
                        <th>Publication</th>
                        <th>Gérer</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- Empty state -->
        <div id="no-comparisons" style="display:none;" class="text-muted text-center">
            Aucune comparaison n'a encore été établie pour cette œuvre. Cliquez sur "Lancer un alignement" pour lancer une comparaison Medite.
        </div>
    </div>
    </div>
</div>

<div class="modal fade" id="pagination-warning-modal" tabindex="-1" aria-labelledby="paginationWarningLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paginationWarningLabel">Pagination manquante</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        La comparaison que vous allez publier ne contient aucune marque de pagination, les facsimilés ne s'afficheront pas dans la partie publique. Voulez-vous insérer un marqueur par défaut de manière à ce que les facsimilés s'affichent?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-pagination-choice="continue">
          Non, continuer sans affichage des facsimilés
        </button>
        <button type="button" class="btn btn-primary" data-pagination-choice="insert">
          Oui, ajouter un marqueur
        </button>
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
  .comparisons-toggle .collapse-chevron::before {
    content: "\25BC";
    display: inline-block;
    transition: transform .2s ease;
  }
  .comparisons-toggle[aria-expanded="false"] .collapse-chevron::before {
    transform: rotate(-90deg);
  }
  #comparisonsCollapse,
  #comparisonsCollapse *,
  #comparisonsCollapse.show,
  #comparisonsCollapse.show * {
    visibility: visible !important;
  }
  .comparisons-table th {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: #444;
    background-color: #f8f9fa;
    text-align: center;
  }
  .comparisons-table td {
    vertical-align: middle;
    font-size: 0.92rem;
    text-align: center;
  }
  .comparisons-table td.comparison-action-cell {
    vertical-align: middle;
  }
  .comparison-details-toggle .form-check-input {
    cursor: pointer;
  }
  .comparison-details-toggle .form-check-label {
    cursor: pointer;
  }
  .comparisons-table.compact-details th:nth-child(1),
  .comparisons-table.compact-details td:nth-child(1),
  .comparisons-table.compact-details th:nth-child(4),
  .comparisons-table.compact-details td:nth-child(4) {
    display: none;
  }
  .comparisons-table.compact-details .comparison-role-details,
  .comparisons-table.compact-details .comparison-results-details,
  .comparisons-table.compact-details .comparison-data-col {
    display: none !important;
  }
  .comparisons-table.compact-details .source-cell .role-wrapper,
  .comparisons-table.compact-details .target-cell .role-wrapper {
    display: none !important;
  }
  .comparisons-table-wrap {
    overflow-x: auto;
  }
  .comparisons-table:not(.compact-details) {
    table-layout: fixed;
    width: 100%;
  }
  .comparisons-table:not(.compact-details) .comparison-results-compact {
    display: none !important;
  }
  .comparisons-table:not(.compact-details) th:nth-child(10),
  .comparisons-table:not(.compact-details) td:nth-child(10),
  .comparisons-table:not(.compact-details) th:nth-child(11),
  .comparisons-table:not(.compact-details) td:nth-child(11),
  .comparisons-table:not(.compact-details) th:nth-child(12),
  .comparisons-table:not(.compact-details) td:nth-child(12) {
    width: 9rem;
    min-width: 9rem;
  }
  .comparisons-table td:nth-child(6),
  .comparisons-table td:nth-child(7),
  .comparisons-table td:nth-child(8),
  .comparisons-table td:nth-child(9),
  .comparisons-table td:nth-child(10),
  .comparisons-table td:nth-child(11),
  .comparisons-table td:nth-child(12) {
    text-align: center;
  }
  .comparisons-table th:nth-child(6),
  .comparisons-table th:nth-child(7),
  .comparisons-table th:nth-child(8),
  .comparisons-table th:nth-child(9),
  .comparisons-table td:nth-child(6),
  .comparisons-table td:nth-child(7),
  .comparisons-table td:nth-child(8),
  .comparisons-table td:nth-child(9) {
    width: 5.5rem;
    min-width: 5.5rem;
  }
  .comparisons-table .comparison-data-col {
    min-width: 10rem;
    width: 10rem;
  }
  .comparison-metric-cell {
    display: inline-block;
    width: 100%;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
  }
  .comparison-results {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    width: 100%;
  }
  .comparison-results-line {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    width: 100%;
    font-size: 0.78rem;
    line-height: 1.2;
    color: #44566c;
    white-space: nowrap;
  }
  .comparison-data-col .comparison-results {
    align-items: stretch;
  }
  .comparison-data-col .comparison-results-line {
    justify-content: flex-start;
    text-align: left;
    white-space: normal;
  }
  .comparison-results-line strong {
    color: #1d2340;
  }
  .comparison-results-line--running {
    color: #7a5512;
  }
  .comparison-results-line--muted {
    color: #6c757d;
  }
  .comparison-results-compact {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.2;
    color: #7a5512;
    white-space: nowrap;
  }
  .comparison-results-compact .comparison-running-spinner {
    width: 0.95rem;
    height: 0.95rem;
  }
  .comparison-results-compact:empty {
    display: none !important;
  }
  .comparisons-table:not(.compact-details) .comparison-results-slot .comparison-results {
    align-items: stretch;
  }
  .comparisons-table:not(.compact-details) .comparison-results-slot .comparison-results-line {
    justify-content: flex-start;
    text-align: left;
    white-space: normal;
  }
  .comparison-role-details {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 0.3rem;
  }
  .comparison-role-details .badge {
    margin: 0 !important;
  }
  .comparison-action-bar {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.25rem;
  }
  .comparison-action-btn {
    width: 1.95rem;
    height: 1.95rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    font-size: 0.72rem;
    line-height: 1;
  }
  .comparison-params {
    display: inline-flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.35rem;
  }
  .comparison-param-chip {
    background: #eef2f6;
    border-radius: 999px;
    padding: 0.15rem 0.6rem;
    font-size: 0.78rem;
    color: #44566c;
    font-weight: 500;
  }
  .comparison-param-chip--input {
    background: #eef2f6;
    color: #2f3d4f;
  }
  .comparison-param-chip--output {
    background: #e7f4ea;
    color: #1f4d2f;
  }
  .comparison-param-chip--running {
    background: #fff4db;
    color: #7a5512;
    border: 1px solid #f0d49a;
  }
  .comparison-param-chip strong {
    color: #1d2340;
  }
  .comparison-param-chip--running strong {
    color: #6a470e;
  }
  .comparison-running-spinner {
    display: inline-block;
    width: 0.82rem;
    height: 0.82rem;
    margin-right: 0.35rem;
    border: 0.12rem solid rgba(122, 85, 18, 0.25);
    border-top-color: #c58a1f;
    border-radius: 999px;
    vertical-align: -0.12rem;
    animation: comparison-running-spin 0.85s linear infinite;
  }
  @keyframes comparison-running-spin {
    to { transform: rotate(360deg); }
  }
  .manifest-json-pill {
    cursor: pointer;
  }
  .manifest-json-pill:focus-visible {
    outline: 2px solid rgba(13, 110, 253, 0.4);
    outline-offset: 2px;
  }
  .legacy-disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  .source-cell .role-wrapper,
  .target-cell .role-wrapper {
    margin-top: 0.35rem;
  }
  .source-cell,
  .target-cell,
  .source-cell .role-wrapper,
  .target-cell .role-wrapper,
  .comparison-role-details,
  .comparison-results-slot {
    text-align: center;
  }
  .comparisons-table tr[data-legacy="1"] .source-cell,
  .comparisons-table tr[data-legacy="1"] .target-cell {
    vertical-align: middle !important;
  }
  .publish-control {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    flex-wrap: nowrap;
    white-space: nowrap;
  }
  .comparisons-table:not(.compact-details) .publish-control {
    flex-direction: column;
    gap: 0.25rem;
    white-space: normal;
  }
  .comparisons-table:not(.compact-details) .comparison-publish-status {
    white-space: normal;
  }
  .comparisons-table:not(.compact-details) .publish-scope {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
  }
  .publish-action-btn {
    min-width: 5.7rem;
    padding: 0.22rem 0.55rem;
    font-size: 0.76rem;
    white-space: nowrap;
  }
  .comparison-publish-pill {
    white-space: nowrap;
    border: 1px solid transparent;
  }
  .comparison-publish-pill--draft {
    background: #e9ecef;
    color: #495057;
    border-color: #d0d7de;
  }
  .comparison-publish-pill--dev {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #bfdbfe;
  }
  .comparison-publish-pill--prod {
    background: #dcfce7;
    color: #166534;
    border-color: #bbf7d0;
  }
  .comparison-publish-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    white-space: nowrap;
  }
  .publish-scope .btn {
    font-size: 0.7rem;
    padding: 0.15rem 0.45rem;
    white-space: nowrap;
  }
</style>
@endpush

@push('scripts')
<script src="{{ admin_asset('js/comparisons.js') }}"></script>
@endpush
