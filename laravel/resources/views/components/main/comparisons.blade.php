<div class="card mb-3">
    <div class="card-header text-uppercase fw-semibold">Comparaisons générées</div>

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
        <table class="table table-sm table-bordered align-middle comparisons-table" id="comparisons-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Source</th>
                    <th>Cible</th>
          <th>Folder</th>
          <th>Ratio</th>
          <th>Pivot</th>
          <th>Sens. Casse</th>
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
    font-weight: normal;
    font-size: 1rem;
    color: #333;
  }
  .comparisons-table td { vertical-align: middle; }
</style>
@endpush

@push('scripts')
<script src="{{ admin_asset('js/comparisons.js') }}"></script>
@endpush
