<div class="card">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center comparisons-toggle"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#comparisonsCollapse"
         aria-expanded="true"
         aria-controls="comparisonsCollapse">
        <div class="d-flex align-items-center gap-2">
            <span class="collapse-chevron" aria-hidden="true"></span>
            <span>Comparaisons</span>
        </div>
        <div class="d-flex align-items-center gap-1">
            <span id="comparisons-count-published" class="badge text-bg-success comparisons-count-pill">0</span>
            <span id="comparisons-count-sep" class="text-muted">/</span>
            <span id="comparisons-count-total" class="badge text-bg-secondary comparisons-count-pill">0</span>
        </div>
    </div>

    <div id="comparisonsCollapse" class="collapse show">
    <div class="card-body">
        <p class="fst-italic text-muted small mb-2">
            Retrouvez ici toutes les comparaisons produites avec Medite pour l'œuvre sélectionnée. Vous pouvez suivre leur état, accéder aux résultats ou relancer la pagination si nécessaire.
        </p>
        <p class="text-muted small mb-3">
            Pagination : deux workflows possibles. (1) Importer un fichier <code>_lignes</code> pour une version, puis « Injecter la pagination ». (2) Insérer des balises <code>&lt;pb&gt;</code> dans l’éditeur de version, lancer Medite, cliquer sur « Créer le sidecar (pb) » puis « Injecter la pagination ». Les boutons ci‑dessous centralisent toutes les opérations (génération, injection, restauration).
        </p>
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
                    <th>Source</th>
                    <th>Cible</th>
                    <th>Dossier</th>
                    <th>Paramètres Medite</th>
                    <th>Composants</th>
                    <th>Publié</th>
                    <th>Publier</th>
                    <th>Date</th>
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
  .comparisons-count-pill {
    min-width: 32px;
    text-align: center;
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
  .comparison-param-chip strong {
    color: #1d2340;
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
