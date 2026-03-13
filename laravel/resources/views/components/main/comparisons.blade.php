<div class="card">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center comparisons-toggle"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#comparisonsCollapse"
         aria-expanded="true"
         aria-controls="comparisonsCollapse">
        <div class="d-flex align-items-start gap-2 admin-card-heading">
            <span class="collapse-chevron" aria-hidden="true"></span>
            <span class="admin-card-heading-text">
                <span class="admin-card-title">Comparaisons textuelles <span id="comparisons-title-count">(0)</span></span>
                <span class="admin-card-subtitle">Résultats produits à partir des versions</span>
            </span>
        </div>
    </div>

    <div id="comparisonsCollapse" class="collapse show">
    <div class="card-body">
        <p class="fst-italic text-muted small mb-2">
            Retrouvez ici les comparaisons textuelles produites pour l’œuvre sélectionnée.
        </p>
        <p class="text-muted small mb-3">
            En pratique : suivez l’état des traitements, ouvrez les résultats, puis injectez la pagination après préparation des données dans la section Versions textuelles.
        </p>

        <div class="comparisons-summary-band">
            <div class="comparisons-summary-text">
                <div class="comparisons-summary-title" id="comparisons-summary-title">Section en attente de sélection</div>
                <div class="comparisons-summary-subtitle" id="comparisons-summary-subtitle">Sélectionnez une œuvre pour afficher les comparaisons textuelles disponibles.</div>
            </div>
            <div class="comparisons-summary-metrics">
                <span class="comparisons-summary-pill" id="comparisons-summary-total">0 comparaison</span>
                <span class="comparisons-summary-pill" id="comparisons-summary-published">0 publiée</span>
                <span class="comparisons-summary-pill" id="comparisons-summary-draft">0 éditoriale</span>
            </div>
        </div>

        <!-- Spinner while loading -->
        <div id="comparisons-loading" class="mb-3" style="display:none;">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            Chargement des comparaisons...
        </div>

        <!-- Table -->
        <table class="table table-sm table-bordered comparisons-table" id="comparisons-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Dossier</th>
                    <th>Source</th>
                    <th>Cible</th>
                    <th>Paramètres Medite</th>
                    <th>Publier</th>
                    <th>Résultats</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

        <!-- Empty state -->
        <div id="no-comparisons" style="display:none;" class="text-muted">
            Aucune comparaison trouvée pour cette œuvre.
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
  .comparisons-summary-band {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem 1rem;
    margin-bottom: 1rem;
    padding: 0.95rem 1.05rem;
    border: 1px solid #ddd6ca;
    border-radius: 0.95rem;
    background: linear-gradient(180deg, #fbfaf7 0%, #f3efe8 100%);
  }
  .comparisons-summary-text {
    min-width: 0;
    flex: 1 1 20rem;
  }
  .comparisons-summary-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #463f38;
  }
  .comparisons-summary-subtitle {
    margin-top: 0.2rem;
    font-size: 0.88rem;
    line-height: 1.45;
    color: #655d53;
  }
  .comparisons-summary-metrics {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
  }
  .comparisons-summary-pill {
    display: inline-flex;
    align-items: center;
    min-height: 2rem;
    padding: 0.25rem 0.7rem;
    border: 1px solid #d8d0c4;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.82);
    font-size: 0.8rem;
    font-weight: 600;
    color: #5d5449;
    white-space: nowrap;
  }
  .comparisons-table th {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: #444;
    background-color: #f8f9fa;
  }
  .comparisons-table td {
    vertical-align: top;
    font-size: 0.92rem;
  }
  .comparisons-table td:nth-child(1),
  .comparisons-table td:nth-child(8) {
    text-align: center;
  }
  .comparison-params {
    display: inline-flex;
    flex-wrap: wrap;
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
    margin-top: 0.5rem;
  }
  .publish-control {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
  }
  .publish-scope .btn {
    font-size: 0.7rem;
    padding: 0.15rem 0.45rem;
  }
</style>
@endpush

@push('scripts')
<script src="{{ admin_asset('js/comparisons.js') }}"></script>
@endpush
