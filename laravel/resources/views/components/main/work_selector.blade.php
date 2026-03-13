<!-- work_selector.blade.php -->

<div class="card" id ="container-work-selector">
    <div class="card-header fw-semibold d-flex align-items-start gap-2">
        <span class="admin-card-heading-text">
            <span class="admin-card-title">Œuvre</span>
            <span class="admin-card-subtitle">Choisissez l’auteur et l’œuvre à éditer</span>
        </span>
    </div>
    <div class="card-body">
        <div class="work-selector-grid">
            <section class="work-selector-panel" id="author-selector-panel">
                <div class="work-selector-panel-head">
                    <div class="work-selector-panel-kicker">Entrée</div>
                    <h3 class="work-selector-panel-title">Auteur</h3>
                </div>
                <div class="work-selector-panel-row">
                    <select id="author-selector" class="form-select flex-grow-1">
                        <option value="" disabled selected>Sélectionner un auteur</option>
                    </select>
                    <div class="btn-group flex-nowrap flex-shrink-0" role="group" aria-label="Actions auteur">
                        <button id="add-author-btn" class="btn btn-outline-success" data-bs-toggle="tooltip" title="Ajouter un auteur"><i class="bi bi-person-plus"></i></button>
                        <button id="edit-author-btn" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Modifier le nom de l'auteur" disabled><i class="bi bi-pencil-square"></i></button>
                        <button id="delete-author-btn" class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Supprimer l'auteur sélectionné" disabled><i class="bi bi-trash3"></i></button>
                    </div>
                </div>
            </section>

            <section class="work-selector-panel work-selector-panel--work is-inactive" id="work-selector-panel">
                <div class="work-selector-panel-head">
                    <div class="work-selector-panel-kicker">Contexte éditorial</div>
                    <h3 class="work-selector-panel-title">Œuvre</h3>
                </div>
                <div class="work-selector-panel-row">
                    <select id="work-selector" class="form-select flex-grow-1" disabled>
                        <option value="" disabled selected>Sélectionner une œuvre</option>
                    </select>
                    <div class="btn-group flex-nowrap flex-shrink-0" role="group" aria-label="Actions œuvre">
                        <button id="add-work-btn" class="btn btn-outline-success" data-bs-toggle="tooltip" title="Ajouter une œuvre" disabled><i class="bi bi-journal-plus"></i></button>
                        <button id="edit-work-btn" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Modifier le nom de l’œuvre" disabled><i class="bi bi-pencil-square"></i></button>
                        <button id="delete-work-btn" class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Supprimer l’œuvre sélectionnée" disabled><i class="bi bi-trash3"></i></button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<!-- Add Author Modal -->
<div class="modal fade" tabindex="-1" id="addAuthorModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ajouter un auteur</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="text" class="form-control" id="new-author-name" placeholder="Nom de l'auteur">
        <div id="author-exists-msg" class="text-danger mt-2" style="display: none;">Cet auteur existe déjà.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-success" id="save-author-btn">Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Author Modal -->
<div class="modal fade" tabindex="-1" id="editAuthorModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Modifier le nom d'auteur</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- We'll store the author ID in a hidden input if needed -->
        <input type="hidden" id="edit-author-id">
        <input type="text" class="form-control" id="edit-author-name" placeholder="Nom de l'auteur">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary" id="update-author-btn">Mettre à jour</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Work Modal -->
<div class="modal fade" tabindex="-1" id="editWorkModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Modifier l’œuvre</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit-work-id">
        
        <div class="mb-3">
          <label for="edit-work-title" class="form-label">Titre de l’œuvre</label>
          <input type="text" class="form-control" id="edit-work-title" placeholder="Titre">
        </div>

        <p class="form-text mb-0">
          Nom abrégé (non modifiable) :
          <span id="edit-work-short-title-label" class="fw-semibold"></span>
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary" id="update-work-btn">Mettre à jour</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Work Modal -->
<div class="modal fade" tabindex="-1" id="addWorkModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ajouter une œuvre</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="text" class="form-control mb-2" id="new-work-title" placeholder="Titre de l’œuvre">
        <input type="text" class="form-control" id="new-work-short" placeholder="Titre abrégé (2 à 10 lettres minuscules)" maxlength="10">
        <div id="work-exists-msg" class="text-danger mt-2" style="display: none;">Cette œuvre existe déjà pour cet auteur.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-success" id="save-work-btn">Enregistrer</button>
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
  #container-work-selector .work-selector-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
  }
  #container-work-selector .work-selector-panel {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    padding: 1rem 1.05rem;
    border: 1px solid #ddd6ca;
    border-radius: 0.95rem;
    background: linear-gradient(180deg, #fbfaf7 0%, #f3efe8 100%);
    transition: opacity .2s ease, filter .2s ease;
  }
  #container-work-selector .work-selector-panel--work {
    background: linear-gradient(180deg, #f8f6f1 0%, #efeae2 100%);
  }
  #container-work-selector .work-selector-panel.is-inactive {
    opacity: 0.72;
  }
  #container-work-selector .work-selector-panel-head {
    display: grid;
    gap: 0.2rem;
  }
  #container-work-selector .work-selector-panel-kicker {
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #7a7165;
  }
  #container-work-selector .work-selector-panel-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #463f38;
  }
  #container-work-selector .work-selector-panel-row {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 0.75rem;
  }
  #container-work-selector .legacy-disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  @media (max-width: 991.98px) {
    #container-work-selector .work-selector-grid {
      grid-template-columns: 1fr;
    }
  }
  @media (max-width: 767.98px) {
    #container-work-selector .work-selector-panel-row {
      flex-wrap: wrap;
    }
  }
</style>
@endpush

@push('scripts')

<script>
  const tooltipTriggerList = document.getElementById('container-work-selector').querySelectorAll('[data-bs-toggle="tooltip"]');
  [...tooltipTriggerList].map(
    tooltipTriggerEl => new bootstrap.Tooltip(
      tooltipTriggerEl,
      {
        trigger: 'hover',
        delay: { "show": 300, "hide": 0 }
      }
    )
  );
</script>

<!-- Reference *only* the single JS file, no inline duplication. -->
<script src="{{ admin_asset('js/work_selector.js') }}"></script>
@endpush
