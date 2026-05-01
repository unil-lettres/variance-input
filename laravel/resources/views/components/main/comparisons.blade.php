<div class="card">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center flex-grow-1">
        <div class="d-flex align-items-start gap-2 admin-card-heading">
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

    <div id="comparisonsCollapse" class="show">
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
                        <th class="comparison-order-col">
                            <span class="comparison-column-icon" aria-hidden="true">
                                <i class="bi bi-arrow-up-short"></i><i class="bi bi-arrow-down-short"></i>
                            </span>
                        </th>
                        <th class="comparison-comment-col" title="Commentaires">💬</th>
                        <th class="comparison-chapters-col" title="Chapitres">
                            <span class="comparison-column-icon" aria-hidden="true">
                                <i class="bi bi-list-ul"></i>
                            </span>
                        </th>
                        <th class="comparison-folder-col">Désignation</th>
                        <th class="comparison-source-col">Source</th>
                        <th class="comparison-target-col">Cible</th>
                        <th class="comparison-params-col">Paramètres Medite</th>
                        <th class="comparison-data-col">Données</th>
                        <th class="comparison-metric-col">Suppr.</th>
                        <th class="comparison-metric-col">Insert.</th>
                        <th class="comparison-metric-col">Rempl.</th>
                        <th class="comparison-metric-col">Dépl.</th>
                        <th class="comparison-publish-col">Publication</th>
                        <th class="comparison-manage-col">Gérer</th>
                        <th class="comparison-action-col">Action</th>
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

<div class="modal fade" id="comparison-comment-modal" tabindex="-1" aria-labelledby="comparisonCommentLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="comparisonCommentLabel">Commentaire de la comparaison</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <textarea
          class="form-control"
          id="comparison-comment-input"
          rows="5"
          maxlength="10000"
          placeholder="Documenter ici les modifications manuelles effectuées dans cette comparaison."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary" id="comparison-comment-save-btn">Enregistrer</button>
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
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
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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
  .comparisons-table.compact-details th:nth-child(4),
  .comparisons-table.compact-details td:nth-child(4),
  .comparisons-table.compact-details th:nth-child(7),
  .comparisons-table.compact-details td:nth-child(7) {
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
    overflow: hidden;
    width: 100%;
    max-width: 100%;
  }
  .comparisons-table {
    table-layout: fixed;
    width: 100%;
  }
  .comparisons-table:not(.compact-details) td {
    vertical-align: top;
  }
  .comparisons-table:not(.compact-details) .comparison-results-compact {
    display: none !important;
  }
  .comparisons-table .comparison-order-col,
  .comparisons-table .comparison-order-cell {
    width: 2rem;
    min-width: 2rem;
    padding-left: 0.2rem;
    padding-right: 0.2rem;
  }
  .comparisons-table .comparison-comment-col,
  .comparisons-table .comparison-comment-cell {
    width: 2.4rem;
    min-width: 2.4rem;
    padding-left: 0.2rem;
    padding-right: 0.2rem;
  }
  .comparisons-table .comparison-chapters-col,
  .comparisons-table .comparison-chapters-cell {
    width: 2.4rem;
    min-width: 2.4rem;
    padding-left: 0.2rem;
    padding-right: 0.2rem;
  }
  .comparisons-table.compact-details .comparison-publish-col,
  .comparisons-table.compact-details .comparison-publish-cell {
    width: 7.25rem;
    min-width: 7.25rem;
  }
  .comparisons-table.compact-details .comparison-manage-col,
  .comparisons-table.compact-details .comparison-manage-cell {
    width: 11.8rem;
    min-width: 11.8rem;
  }
  .comparisons-table:not(.compact-details) .comparison-folder-col,
  .comparisons-table:not(.compact-details) .comparison-folder-cell {
    width: 12%;
    min-width: 0;
  }
  .comparisons-table:not(.compact-details) .comparison-source-col,
  .comparisons-table:not(.compact-details) .comparison-source-cell,
  .comparisons-table:not(.compact-details) .comparison-target-col,
  .comparisons-table:not(.compact-details) .comparison-target-cell {
    width: 12%;
    min-width: 0;
  }
  .comparisons-table:not(.compact-details) .comparison-params-col,
  .comparisons-table:not(.compact-details) .comparison-params-cell {
    width: 10%;
    min-width: 0;
  }
  .comparisons-table:not(.compact-details) .comparison-data-col,
  .comparisons-table:not(.compact-details) .comparison-data-cell {
    width: 12%;
    min-width: 0;
  }
  .comparisons-table:not(.compact-details) .comparison-publish-col,
  .comparisons-table:not(.compact-details) .comparison-publish-cell {
    width: 7%;
    min-width: 0;
  }
  .comparisons-table:not(.compact-details) .comparison-manage-col,
  .comparisons-table:not(.compact-details) .comparison-manage-cell {
    width: 12%;
    min-width: 0;
  }
  .comparisons-table:not(.compact-details) .comparison-action-col,
  .comparisons-table:not(.compact-details) .comparison-action-cell {
    width: 7%;
    min-width: 0;
  }
  .comparisons-table td:nth-child(9),
  .comparisons-table td:nth-child(10),
  .comparisons-table td:nth-child(11),
  .comparisons-table td:nth-child(12),
  .comparisons-table td:nth-child(13),
  .comparisons-table td:nth-child(14),
  .comparisons-table td:nth-child(15) {
    text-align: center;
  }
  .comparisons-table th:nth-child(9),
  .comparisons-table th:nth-child(10),
  .comparisons-table th:nth-child(11),
  .comparisons-table td:nth-child(9),
  .comparisons-table td:nth-child(10),
  .comparisons-table td:nth-child(11),
  .comparisons-table th:nth-child(12),
  .comparisons-table td:nth-child(12) {
    width: 4.5rem;
    min-width: 4.5rem;
  }
  .comparisons-table .comparison-folder-cell,
  .comparisons-table .comparison-source-cell,
  .comparisons-table .comparison-target-cell,
  .comparisons-table .comparison-params-cell,
  .comparisons-table .comparison-data-cell,
  .comparisons-table .comparison-manage-cell,
  .comparisons-table .comparison-action-cell {
    min-width: 0;
  }
  .comparison-comment-btn {
    width: 1.8rem;
    height: 1.8rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .comparison-comment-btn i {
    font-size: 0.9rem;
  }
  .comparison-chapters-btn {
    width: 1.8rem;
    height: 1.8rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .comparison-chapters-btn i {
    font-size: 0.9rem;
  }
  .comparison-chapters-btn--filled {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
  }
  .comparison-chapters-btn--filled:hover,
  .comparison-chapters-btn--filled:focus {
    background-color: #0b5ed7;
    border-color: #0a58ca;
    color: #fff;
  }
  .comparison-comment-btn--filled {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
  }
  .comparison-comment-btn--filled:hover,
  .comparison-comment-btn--filled:focus {
    background-color: #0b5ed7;
    border-color: #0a58ca;
    color: #fff;
  }
  .comparison-column-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    line-height: 1;
    color: #495057;
  }
  .comparison-column-icon i + i {
    margin-left: -0.2rem;
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
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .comparison-data-col .comparison-results {
    align-items: stretch;
  }
  .comparisons-table:not(.compact-details) .comparison-data-col .comparison-results {
    gap: 0.2rem;
  }
  .comparison-data-col .comparison-results-line {
    justify-content: flex-start;
    text-align: left;
    white-space: nowrap;
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
    white-space: nowrap;
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
  .comparison-version-label {
    display: block;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .comparisons-table .comparison-folder-cell,
  .comparisons-table .source-cell,
  .comparisons-table .target-cell,
  .comparisons-table .comparison-params-cell,
  .comparisons-table .comparison-data-cell {
    overflow: hidden;
  }
  .source-cell,
  .target-cell,
  .source-cell .role-wrapper,
  .target-cell .role-wrapper,
  .comparison-role-details,
  .comparison-results-slot {
    text-align: center;
  }
  .comparisons-table:not(.compact-details) .source-cell .role-wrapper,
  .comparisons-table:not(.compact-details) .target-cell .role-wrapper {
    margin-top: 0.45rem;
  }
  .comparisons-table:not(.compact-details) .comparison-role-details {
    gap: 0.25rem;
  }
  .comparisons-table:not(.compact-details) .comparison-role-details .badge {
    font-size: 0.72rem;
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
    width: 11.2rem;
    min-width: 11.2rem;
    max-width: 11.2rem;
    margin-left: auto;
    margin-right: auto;
  }
  .comparisons-table:not(.compact-details) .publish-control {
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
  .publish-scope {
    width: 4.9rem;
    min-width: 4.9rem;
  }
  .publish-action-btn {
    width: 5.95rem;
    min-width: 0;
    padding: 0.22rem 0.45rem;
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
    flex: 1 1 0;
    min-width: 0;
    font-size: 0.7rem;
    padding: 0.15rem 0.45rem;
    white-space: nowrap;
  }
  .comparisons-table:not(.compact-details) .comparison-results-slot .comparison-results-line {
    justify-content: center;
    text-align: center;
  }
  .comparisons-table:not(.compact-details) .comparison-action-bar {
    margin-top: 0.15rem;
    gap: 0.2rem;
  }
  .comparison-reorder-bar {
    display: inline-flex;
    flex-direction: column;
    justify-content: center;
    width: 1.8rem;
    min-height: 1.8rem;
    gap: 0.1rem;
  }
  .comparison-reorder-bar .comparison-action-btn {
    width: 1.8rem;
    height: 0.82rem;
    min-width: 1.8rem;
    font-size: 0.56rem;
    border-radius: 0.28rem;
    padding: 0;
  }
  .comparison-order-cell {
    vertical-align: middle !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    text-align: center;
  }
  .comparison-comment-cell {
    vertical-align: middle !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    text-align: center;
  }
  .comparison-chapters-cell {
    vertical-align: middle !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    text-align: center;
  }
  .comparison-order-cell,
  .comparison-comment-cell,
  .comparison-chapters-cell {
    height: 100%;
  }
  .comparison-order-cell > .comparison-action-bar,
  .comparison-order-cell > .comparison-reorder-bar,
  .comparison-comment-cell > .comparison-comment-btn,
  .comparison-chapters-cell > .comparison-chapters-btn {
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .comparison-comment-cell > .comparison-comment-btn {
    vertical-align: middle;
  }
  .comparison-chapters-cell > .comparison-chapters-btn {
    vertical-align: middle;
  }
  .comparisons-table:not(.compact-details) .comparison-action-cell .d-flex {
    width: 100%;
  }
  @media (max-width: 1280px) {
    .comparisons-table th,
    .comparisons-table td {
      font-size: 0.82rem;
    }
    .comparisons-table th:nth-child(7),
    .comparisons-table td:nth-child(7),
    .comparisons-table th:nth-child(8),
    .comparisons-table td:nth-child(8) {
      display: none;
    }
    .comparisons-table:not(.compact-details) .comparison-folder-col,
    .comparisons-table:not(.compact-details) .comparison-folder-cell {
      width: 14%;
    }
    .comparisons-table:not(.compact-details) .comparison-source-col,
    .comparisons-table:not(.compact-details) .comparison-source-cell,
    .comparisons-table:not(.compact-details) .comparison-target-col,
    .comparisons-table:not(.compact-details) .comparison-target-cell {
      width: 14%;
    }
    .comparisons-table:not(.compact-details) .comparison-publish-col,
    .comparisons-table:not(.compact-details) .comparison-publish-cell,
    .comparisons-table:not(.compact-details) .comparison-action-col,
    .comparisons-table:not(.compact-details) .comparison-action-cell {
      width: 9%;
    }
    .comparisons-table:not(.compact-details) .comparison-manage-col,
    .comparisons-table:not(.compact-details) .comparison-manage-cell {
      width: 13%;
    }
  }
  @media (max-width: 1080px) {
    .comparisons-table th,
    .comparisons-table td {
      font-size: 0.76rem;
    }
    .comparisons-table th:nth-child(4),
    .comparisons-table td:nth-child(4),
    .comparisons-table th:nth-child(14),
    .comparisons-table td:nth-child(14) {
      display: none;
    }
    .comparison-action-btn,
    .comparison-comment-btn,
    .comparison-chapters-btn {
      width: 1.7rem;
      height: 1.7rem;
    }
    .publish-action-btn {
      min-width: 0;
      font-size: 0.68rem;
      padding: 0.18rem 0.4rem;
    }
  }
  @media (max-width: 920px) {
    .comparisons-table th:nth-child(9),
    .comparisons-table td:nth-child(9),
    .comparisons-table th:nth-child(10),
    .comparisons-table td:nth-child(10),
    .comparisons-table th:nth-child(11),
    .comparisons-table td:nth-child(11),
    .comparisons-table th:nth-child(12),
    .comparisons-table td:nth-child(12) {
      display: none;
    }
    .comparisons-table:not(.compact-details) .comparison-source-col,
    .comparisons-table:not(.compact-details) .comparison-source-cell,
    .comparisons-table:not(.compact-details) .comparison-target-col,
    .comparisons-table:not(.compact-details) .comparison-target-cell {
      width: 16%;
    }
    .comparisons-table:not(.compact-details) .comparison-publish-col,
    .comparisons-table:not(.compact-details) .comparison-publish-cell,
    .comparisons-table:not(.compact-details) .comparison-action-col,
    .comparisons-table:not(.compact-details) .comparison-action-cell {
      width: 11%;
    }
  }
</style>
@endpush

@push('scripts')
<script src="{{ admin_asset('js/comparisons.js') }}"></script>
@endpush
