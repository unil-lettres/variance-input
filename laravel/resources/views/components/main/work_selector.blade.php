<!-- work_selector.blade.php -->

<div class="card mb-3">
    <div class="card-header">Sélection de l'oeuvre</div>
    <div class="card-body">
        <div class="row">
            <!-- Author controls -->
            <div class="col-md-6">
                <div class="form-group">
                    <label for="author-selector">Auteur</label>
                    <div class="input-group">
                        <select id="author-selector" class="form-control">
                            <option value="">Sélectionner un auteur</option>
                        </select>
                        <button id="add-author-btn" class="btn btn-outline-success ms-1">+</button>
                        <button id="edit-author-btn" class="btn btn-outline-primary ms-1" disabled>✏️</button>
                        <button id="delete-author-btn" class="btn btn-outline-danger ms-1" disabled>🗑️</button>
                    </div>
                </div>
            </div>

            <!-- Work controls -->
            <div class="col-md-6">
                <div class="form-group">
                    <label for="work-selector">Oeuvre</label>
                    <div class="input-group">
                        <select id="work-selector" class="form-control" disabled>
                            <option value="">Sélectionner une oeuvre</option>
                        </select>
                        <button id="add-work-btn" class="btn btn-outline-success ms-1" disabled>+</button>
                        <button id="edit-work-btn" class="btn btn-outline-primary ms-1" disabled>✏️</button>
                        <button id="delete-work-btn" class="btn btn-outline-danger ms-1" disabled>🗑️</button>
                    </div>
                </div>
            </div>
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
        <h5 class="modal-title">Modifier l'oeuvre</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit-work-id">
        
        <div class="mb-3">
          <label for="edit-work-title" class="form-label">Titre de l'oeuvre</label>
          <input type="text" class="form-control" id="edit-work-title" placeholder="Titre">
        </div>

        <div>
          <label for="edit-work-short-title" class="form-label">Nom abrégé d'oeuvre</label>
          <input type="text" class="form-control" id="edit-work-short-title" placeholder="Titre abrégé" maxlength="10">
        </div>
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
        <h5 class="modal-title">Ajouter une oeuvre</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="text" class="form-control mb-2" id="new-work-title" placeholder="Titre de l'oeuvre">
        <input type="text" class="form-control" id="new-work-short" placeholder="Titre abrégé (max 10 caractères)" maxlength="10">
        <div id="work-exists-msg" class="text-danger mt-2" style="display: none;">Cette oeuvre existe déjà pour cet auteur.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-success" id="save-work-btn">Enregistrer</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<!-- Reference *only* the single JS file, no inline duplication. -->
<script src="/js/work_selector.js"></script>
@endpush
