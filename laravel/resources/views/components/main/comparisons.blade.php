<div class="card mb-3">
    <div class="card-header fw-semibold">Comparaisons</div>

    <div class="card-body">
        <p class="fst-italic text-muted small mb-3">
            Retrouvez ici toutes les comparaisons produites avec Medite pour l'œuvre sélectionnée. Vous pouvez suivre leur état, accéder aux résultats ou relancer la pagination si nécessaire.
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

@push('styles')
<style>
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
  .source-cell .role-wrapper,
  .target-cell .role-wrapper {
    margin-top: 0.5rem;
  }
</style>
@endpush

@push('scripts')
<script src="{{ admin_asset('js/comparisons.js') }}"></script>
@endpush
