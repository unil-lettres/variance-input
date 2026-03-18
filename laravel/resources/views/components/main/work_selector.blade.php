<!-- work_selector.blade.php -->

<div class="card" id ="container-work-selector">
    <div class="card-header work-selector-card-header fw-semibold">
        @include('components.admin.chrome_controls', ['embedded' => true])
    </div>
    <div class="card-body">
        <div class="work-selector-grid">
            <section class="work-selector-panel" id="author-selector-panel">
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
    <div class="work-selector-steps" role="tablist" aria-label="Étapes de l’atelier">
        <button type="button" class="editorial-step-chip is-active" id="editorial-step-chip-0" data-editorial-step-target="0" role="tab" aria-selected="true" aria-controls="editorial-step-0">
            <span class="editorial-step-chip-number">1</span>
            <span class="editorial-step-chip-copy">
                <span class="editorial-step-chip-label">Choisir l’œuvre</span>
                <span class="editorial-step-chip-detail">Auteur et contexte</span>
            </span>
        </button>
        <button type="button" class="editorial-step-chip" id="editorial-step-chip-1" data-editorial-step-target="1" role="tab" aria-selected="false" aria-controls="editorial-step-1">
            <span class="editorial-step-chip-number">2</span>
            <span class="editorial-step-chip-copy">
                <span class="editorial-step-chip-label">Décrire l’œuvre</span>
                <span class="editorial-step-chip-detail">Présentation et notice</span>
            </span>
        </button>
        <button type="button" class="editorial-step-chip" id="editorial-step-chip-2" data-editorial-step-target="2" role="tab" aria-selected="false" aria-controls="editorial-step-2">
            <span class="editorial-step-chip-number">3</span>
            <span class="editorial-step-chip-copy">
                <span class="editorial-step-chip-label">Préparer les témoins</span>
                <span class="editorial-step-chip-detail">Versions et fac-similés</span>
            </span>
        </button>
        <button type="button" class="editorial-step-chip" id="editorial-step-chip-3" data-editorial-step-target="3" role="tab" aria-selected="false" aria-controls="editorial-step-3">
            <span class="editorial-step-chip-number">4</span>
            <span class="editorial-step-chip-copy">
                <span class="editorial-step-chip-label">Comparer et publier</span>
                <span class="editorial-step-chip-detail">Alignement et résultats</span>
            </span>
        </button>
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
  #container-work-selector .work-selector-card-header {
    padding: 0.6rem 0.85rem;
  }
  #container-work-selector .work-selector-steps {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.8rem;
    padding: 0 0.95rem 0.95rem;
  }
  #container-work-selector .admin-chrome {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.9rem;
    width: 100%;
  }
  #container-work-selector .admin-chrome--embedded {
    justify-content: space-between;
  }
  #container-work-selector .admin-embedded-title {
    flex: 0 0 auto;
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.38em;
    text-transform: uppercase;
    color: #6b6258;
    white-space: nowrap;
  }
  #container-work-selector .admin-chrome-actions {
    margin-left: auto;
    flex-wrap: wrap;
    justify-content: flex-end;
  }
  #container-work-selector .admin-brand {
    min-width: 0;
  }
  #container-work-selector .admin-brand a {
    padding: 0.16rem 0.42rem;
  }
  #container-work-selector .admin-wordmark {
    font-size: clamp(1.35rem, 2.1vw, 1.95rem);
  }
  #container-work-selector .admin-user-toggle {
    padding: 0.28rem 0.58rem;
    font-size: 0.82rem;
  }
  #container-work-selector .editorial-step-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.7rem;
    width: 100%;
    padding: 0.8rem 0.95rem;
    border: 1px solid rgba(117, 107, 94, 0.18);
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.78);
    color: #5f564b;
    text-align: left;
    transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease, background 0.28s ease;
  }
  #container-work-selector .editorial-step-chip:hover {
    border-color: rgba(104, 92, 78, 0.32);
    box-shadow: 0 8px 18px rgba(94, 78, 56, 0.08);
    transform: translateY(-1px);
  }
  #container-work-selector .editorial-step-chip.is-active {
    border-color: rgba(91, 116, 173, 0.26);
    background: linear-gradient(180deg, #ffffff 0%, #eef3ff 100%);
    box-shadow: 0 10px 20px rgba(69, 101, 163, 0.12);
  }
  #container-work-selector .editorial-step-chip-number {
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    border: 1px solid rgba(117, 107, 94, 0.18);
    background: linear-gradient(180deg, #f5f2ec 0%, #ebe6dd 100%);
    font-size: 0.9rem;
    font-weight: 700;
    color: #5f564b;
  }
  #container-work-selector .editorial-step-chip.is-active .editorial-step-chip-number {
    border-color: rgba(91, 116, 173, 0.22);
    background: linear-gradient(180deg, #f8fbff 0%, #e6eefb 100%);
    color: #38548f;
  }
  #container-work-selector .editorial-step-chip-label {
    min-width: 0;
    font-size: 0.92rem;
    font-weight: 700;
    line-height: 1.25;
  }
  #container-work-selector .editorial-step-chip-copy {
    display: grid;
    gap: 0.1rem;
    min-width: 0;
  }
  #container-work-selector .editorial-step-chip-detail {
    font-size: 0.75rem;
    line-height: 1.25;
    color: #7a7165;
    white-space: nowrap;
  }
  #container-work-selector .editorial-step-chip.is-active .editorial-step-chip-detail {
    color: #586f9d;
  }
  #container-work-selector .work-selector-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
  }
  #container-work-selector .work-selector-panel {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.9rem 1rem;
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
    #container-work-selector .work-selector-steps {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    #container-work-selector .admin-chrome {
      flex-direction: column;
      align-items: stretch;
    }
    #container-work-selector .admin-chrome-actions {
      justify-content: flex-start;
    }
    #container-work-selector .work-selector-grid {
      grid-template-columns: 1fr;
    }
  }
  @media (max-width: 767.98px) {
    #container-work-selector .work-selector-card-header {
      padding: 0.55rem 0.7rem;
    }
    #container-work-selector .admin-wordmark {
      font-size: clamp(1.18rem, 6.2vw, 1.5rem);
      letter-spacing: 0.04em;
    }
    #container-work-selector .admin-embedded-title {
      font-size: 0.88rem;
      letter-spacing: 0.24em;
    }
    #container-work-selector .work-selector-steps {
      grid-template-columns: 1fr;
      padding: 0 0.7rem 0.7rem;
    }
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
