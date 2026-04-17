<!-- work_selector.blade.php -->
@php
    $plannedMaintenance = app(\App\Services\AdminMaintenanceMode::class)->currentAnnouncement();
    $formatPlannedMaintenanceDate = static function (?string $value): ?string {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)
                ->setTimezone('Europe/Zurich')
                ->format('d/m/Y H:i');
        } catch (\Throwable) {
            return null;
        }
    };
    $plannedStartsAt = $formatPlannedMaintenanceDate($plannedMaintenance['starts_at'] ?? null);
    $plannedUntil = $formatPlannedMaintenanceDate($plannedMaintenance['until'] ?? null);
@endphp

<div class="card" id ="container-work-selector">
    <div class="card-header work-selector-card-header fw-semibold">
        @include('components.admin.chrome_controls', ['embedded' => true])
    </div>
    <div class="card-body">
        <div class="work-selector-grid">
            <section class="work-selector-panel" id="author-selector-panel">
                <div class="work-selector-panel-row">
                    <button id="clear-selection-btn" class="btn btn-outline-secondary work-selector-clear-btn" type="button" title="Réinitialiser la sélection" aria-label="Réinitialiser la sélection" disabled>
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <div class="dropdown flex-grow-1 work-selector-dropdown-wrap">
                        <button id="author-selector-toggle" class="btn btn-outline-secondary dropdown-toggle work-selector-dropdown-btn w-100 text-start" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Sélectionner un auteur
                        </button>
                        <ul id="author-selector-menu" class="dropdown-menu w-100 work-selector-dropdown-menu"></ul>
                        <select id="author-selector" class="form-select d-none" aria-hidden="true" tabindex="-1">
                            <option value="" disabled selected>Sélectionner un auteur</option>
                        </select>
                    </div>
                    <div class="btn-group flex-nowrap flex-shrink-0" role="group" aria-label="Actions auteur">
                        <button id="add-author-btn" class="btn btn-outline-success" data-bs-toggle="tooltip" title="Ajouter un auteur"><i class="bi bi-person-plus"></i></button>
                        <button id="edit-author-btn" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Modifier le nom de l'auteur" disabled><i class="bi bi-pencil-square"></i></button>
                        <button id="delete-author-btn" class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Supprimer l'auteur sélectionné" disabled><i class="bi bi-trash3"></i></button>
                    </div>
                </div>
            </section>

            <section class="work-selector-panel work-selector-panel--work is-inactive" id="work-selector-panel">
                <div class="work-selector-panel-row">
                    <div class="dropdown flex-grow-1 work-selector-dropdown-wrap">
                        <button id="work-selector-toggle" class="btn btn-outline-secondary dropdown-toggle work-selector-dropdown-btn w-100 text-start" type="button" data-bs-toggle="dropdown" aria-expanded="false" disabled>
                            Sélectionner une œuvre
                        </button>
                        <ul id="work-selector-menu" class="dropdown-menu w-100 work-selector-dropdown-menu"></ul>
                        <select id="work-selector" class="form-select d-none" aria-hidden="true" tabindex="-1" disabled>
                            <option value="" disabled selected>Sélectionner une œuvre</option>
                        </select>
                    </div>
                    <div class="btn-group flex-nowrap flex-shrink-0" role="group" aria-label="Actions œuvre">
                        <button id="add-work-btn" class="btn btn-outline-success" data-bs-toggle="tooltip" title="Ajouter une œuvre" disabled><i class="bi bi-journal-plus"></i></button>
                        <button id="edit-work-btn" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Modifier le nom de l’œuvre" disabled><i class="bi bi-pencil-square"></i></button>
                        <button id="delete-work-btn" class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Supprimer l’œuvre sélectionnée" disabled><i class="bi bi-trash3"></i></button>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <div class="work-selector-editorial-context">
        <div class="editorial-current-work" id="editorial-current-work" aria-live="polite" hidden>
            <span class="editorial-current-work-value" id="editorial-current-work-value"></span>
            <div class="editorial-current-work-meta" id="editorial-current-work-meta" hidden></div>
        </div>
        <div class="editorial-welcome" id="editorial-welcome-message">
            <div class="editorial-welcome-text">Bienvenue dans l'interface de publication Variance.<br>Sélectionnez une oeuvre pour démarrer le processus éditorial.</div>
            @if($plannedMaintenance['enabled'] ?? false)
                <div class="editorial-maintenance-notice" id="editorial-maintenance-notice" role="status" aria-live="polite">
                    <div class="editorial-maintenance-notice__badge">Maintenance annoncée</div>
                    <div class="editorial-maintenance-notice__text">{{ $plannedMaintenance['message'] }}</div>
                    @if($plannedStartsAt || $plannedUntil)
                        <div class="editorial-maintenance-notice__meta">
                            @if($plannedStartsAt)
                                <span><strong>Début prévu :</strong> {{ $plannedStartsAt }}</span>
                            @endif
                            @if($plannedUntil)
                                <span><strong>Fin estimée :</strong> {{ $plannedUntil }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
    <div class="work-selector-steps" role="tablist" aria-label="Étapes de l’atelier">
        <button type="button" class="editorial-step-chip is-active" id="editorial-step-chip-0" data-editorial-step-target="0" role="tab" aria-selected="true" aria-controls="editorial-step-0">
            <span class="editorial-step-chip-label">Choisir l’œuvre</span>
        </button>
        <button type="button" class="editorial-step-chip" id="editorial-step-chip-1" data-editorial-step-target="1" role="tab" aria-selected="false" aria-controls="editorial-step-1">
            <span class="editorial-step-chip-label">Description</span>
        </button>
        <button type="button" class="editorial-step-chip" id="editorial-step-chip-2" data-editorial-step-target="2" role="tab" aria-selected="false" aria-controls="editorial-step-2">
            <span class="editorial-step-chip-label">Versions</span>
        </button>
        <button type="button" class="editorial-step-chip" id="editorial-step-chip-3" data-editorial-step-target="3" role="tab" aria-selected="false" aria-controls="editorial-step-3">
            <span class="editorial-step-chip-label">Comparaisons</span>
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
    padding: 0.75rem 1rem;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
  }
  #container-work-selector .work-selector-steps {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.6rem;
    padding: 0 1rem 0.85rem;
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
  #container-work-selector .work-selector-editorial-context {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 6.4rem;
    padding: 0 1rem 0.95rem;
  }
  #container-work-selector .editorial-current-work {
    width: min(50rem, calc(100% - 1rem));
    min-height: 4.1rem;
    margin: 0 auto;
    padding: 0.65rem 1rem 0.6rem;
    display: grid;
    align-content: center;
    gap: 0.16rem;
    text-align: center;
    background: linear-gradient(180deg, #fbfcfd 0%, #f1f4f7 100%);
    border: 1px solid #d8e0e7;
    border-radius: 0.8rem;
    box-shadow: 0 10px 24px -22px rgba(15, 23, 42, 0.55);
  }
  #container-work-selector .editorial-current-work-value {
    font-size: 1.18rem;
    font-weight: 700;
    line-height: 1.35;
    letter-spacing: 0.01em;
    color: #3f352b;
  }
  #container-work-selector .editorial-current-work-author {
    font-style: normal;
    font-weight: 700;
    color: #3f352b;
  }
  #container-work-selector .editorial-current-work-work {
    font-style: normal;
    font-weight: 700;
    color: #3f352b;
  }
  #container-work-selector .editorial-current-work-meta {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.45rem 0.8rem;
    margin-top: 0.1rem;
    font-size: 0.8rem;
    line-height: 1.35;
    color: #5b6574;
  }
  #container-work-selector .editorial-current-work-meta-item strong {
    color: #3a4758;
    font-weight: 700;
  }
  #container-work-selector .editorial-welcome {
    width: min(50rem, calc(100% - 1rem));
    min-height: 4.1rem;
    margin: 0 auto;
    padding: 0.75rem 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.04rem;
    font-weight: 700;
    line-height: 1.45;
    color: #1f2933;
    text-align: center;
    background: linear-gradient(180deg, #f8f9fa 0%, #eef2f5 100%);
    border: 1px solid #dbe3ea;
    border-radius: 0.75rem;
    box-shadow: 0 10px 24px -18px rgba(15, 23, 42, 0.45);
  }
  #container-work-selector .editorial-welcome-text {
    max-width: 100%;
    text-align: center;
  }
  #container-work-selector .editorial-maintenance-notice {
    width: min(44rem, 100%);
    margin-top: 0.9rem;
    padding: 0.9rem 1rem;
    border: 1px solid rgba(191, 145, 56, 0.28);
    border-radius: 0.9rem;
    background: linear-gradient(180deg, rgba(191, 145, 56, 0.1), rgba(191, 145, 56, 0.04));
    color: #6a5530;
    text-align: left;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.42);
  }
  #container-work-selector .editorial-maintenance-notice__badge {
    display: inline-flex;
    align-items: center;
    margin-bottom: 0.45rem;
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    background: rgba(191, 145, 56, 0.18);
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }
  #container-work-selector .editorial-maintenance-notice__text {
    font-size: 0.98rem;
    line-height: 1.5;
  }
  #container-work-selector .editorial-maintenance-notice__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem 1rem;
    margin-top: 0.45rem;
    font-size: 0.84rem;
    color: #7a6441;
  }
  #container-work-selector .work-selector-dropdown-btn {
    min-height: 2.4rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  #container-work-selector .work-selector-dropdown-btn::after {
    margin-left: auto;
    flex-shrink: 0;
  }
  #container-work-selector .work-selector-dropdown-menu .dropdown-item {
    white-space: normal;
    line-height: 1.35;
  }
  #container-work-selector .work-selector-dropdown-menu .dropdown-item.active,
  #container-work-selector .work-selector-dropdown-menu .dropdown-item:active {
    background-color: #0d6efd;
    color: #fff;
  }
  #container-work-selector .work-selector-dropdown-empty {
    padding: 0.45rem 0.85rem;
    color: #6c757d;
    font-size: 0.88rem;
  }
  #container-work-selector .editorial-step-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 2.75rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    background: #fff;
    color: #495057;
    text-align: center;
    transition: border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease;
  }
  #container-work-selector .editorial-step-chip:hover {
    border-color: #adb5bd;
  }
  #container-work-selector .editorial-step-chip.is-active {
    border-color: #0d6efd;
    background: #f8fbff;
    color: #0a58ca;
  }
  #container-work-selector .editorial-step-chip.is-disabled {
    opacity: 0.65;
    color: #8b949e;
    border-color: #e9ecef;
    background: #f8f9fa;
    cursor: not-allowed;
    pointer-events: none;
  }
  #container-work-selector .editorial-step-chip.is-disabled:hover {
    border-color: #e9ecef;
  }
  #container-work-selector .editorial-step-chip-label {
    min-width: 0;
    font-size: 0.88rem;
    font-weight: 700;
    white-space: nowrap;
    line-height: 1.2;
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
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    background: #fff;
    transition: opacity .2s ease, filter .2s ease;
  }
  #container-work-selector .work-selector-panel--work {
    background: #fff;
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
  #container-work-selector .work-selector-clear-btn {
    width: 2.35rem;
    min-width: 2.35rem;
    height: 2.35rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    flex: 0 0 auto;
  }
  #container-work-selector .legacy-disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  #container-work-selector.work-selector-redesign {
    --selector-shell-bg: linear-gradient(180deg, #f8f5ef 0%, #f2ede3 100%);
    --selector-shell-border: #d8cec0;
    --selector-shell-shadow: 0 18px 40px -30px rgba(56, 45, 31, 0.45);
    --selector-panel-bg: linear-gradient(180deg, rgba(255,255,255,0.96) 0%, rgba(248,244,236,0.94) 100%);
    --selector-panel-border: #d8cec0;
    --selector-panel-shadow: inset 0 1px 0 rgba(255,255,255,0.85);
    --selector-ink: #2f3640;
    --selector-muted: #6f675d;
    --selector-accent: #2f6c90;
    --selector-accent-soft: #eef5f8;
    --selector-step-active: linear-gradient(180deg, #f5fbff 0%, #e8f2f8 100%);
  }
  #container-work-selector.work-selector-redesign {
    border: 1px solid var(--selector-shell-border);
    border-radius: 0.95rem;
    overflow: hidden;
    background: var(--selector-shell-bg);
    box-shadow: var(--selector-shell-shadow);
  }
  #container-work-selector.work-selector-redesign .card-body {
    padding: 1rem 1rem 0.85rem;
  }
  #container-work-selector.work-selector-redesign .work-selector-card-header {
    background: linear-gradient(180deg, #fbfaf6 0%, #f1ebdf 100%);
    border-bottom-color: #ddd4c8;
  }
  #container-work-selector.work-selector-redesign .work-selector-grid {
    gap: 0.85rem;
  }
  #container-work-selector.work-selector-redesign .work-selector-panel {
    border-color: var(--selector-panel-border);
    border-radius: 0.8rem;
    background: var(--selector-panel-bg);
    box-shadow: var(--selector-panel-shadow);
  }
  #container-work-selector.work-selector-redesign .work-selector-panel.is-inactive {
    opacity: 0.82;
    filter: saturate(0.8);
  }
  #container-work-selector.work-selector-redesign .work-selector-dropdown-btn,
  #container-work-selector.work-selector-redesign .work-selector-clear-btn {
    border-color: #cfc4b5;
    background: rgba(255, 255, 255, 0.92);
    color: var(--selector-ink);
  }
  #container-work-selector.work-selector-redesign .work-selector-dropdown-btn:hover,
  #container-work-selector.work-selector-redesign .work-selector-clear-btn:hover {
    border-color: #b6a894;
    background: #fffdf9;
  }
  #container-work-selector.work-selector-redesign .work-selector-dropdown-btn.text-muted {
    color: var(--selector-muted) !important;
  }
  #container-work-selector.work-selector-redesign .work-selector-dropdown-menu {
    border-color: #d3c7b8;
    border-radius: 0.8rem;
    box-shadow: 0 14px 32px -24px rgba(56, 45, 31, 0.55);
    padding-block: 0.4rem;
    background: #fffdf9;
  }
  #container-work-selector.work-selector-redesign .work-selector-dropdown-menu .dropdown-item {
    font-size: 0.95rem;
    color: var(--selector-ink);
  }
  #container-work-selector.work-selector-redesign .work-selector-dropdown-menu .dropdown-item:hover {
    background: #f4ede2;
  }
  #container-work-selector.work-selector-redesign .work-selector-editorial-context {
    gap: 0.75rem;
    padding: 0 1rem 1rem;
    border-top-color: #e4ddd2;
  }
  #container-work-selector.work-selector-redesign .editorial-current-work {
    width: min(56rem, calc(100% - 0.5rem));
    background: linear-gradient(180deg, #fffdf9 0%, #f6f1e7 100%);
    border-color: #d9cfbf;
    box-shadow: 0 12px 28px -24px rgba(56, 45, 31, 0.52);
  }
  #container-work-selector.work-selector-redesign .editorial-current-work-label {
    color: #7a7267;
    letter-spacing: 0.14em;
  }
  #container-work-selector.work-selector-redesign .editorial-current-work-value {
    color: #3f352b;
    font-size: 1.22rem;
  }
  #container-work-selector.work-selector-redesign .editorial-current-work-meta {
    color: #5f6772;
  }
  #container-work-selector.work-selector-redesign .editorial-welcome {
    width: min(48rem, calc(100% - 0.5rem));
    padding: 0.8rem 1.1rem;
    font-size: 1.02rem;
    font-weight: 650;
    line-height: 1.55;
    color: #2a3340;
    background: linear-gradient(180deg, #fbfaf7 0%, #f2ede4 100%);
    border-color: #d9cfbf;
    box-shadow: none;
  }
  #container-work-selector.work-selector-redesign .editorial-maintenance-notice {
    border-color: rgba(166, 125, 43, 0.24);
    background: linear-gradient(180deg, rgba(191, 145, 56, 0.08), rgba(191, 145, 56, 0.03));
    box-shadow: none;
  }
  #container-work-selector.work-selector-redesign .editorial-step-zero-actions {
    gap: 0.6rem;
  }
  #container-work-selector.work-selector-redesign .editorial-step-zero-actions .btn {
    min-width: 12rem;
    border-color: #6f9c79;
    color: #245b33;
    background: rgba(255,255,255,0.78);
  }
  #container-work-selector.work-selector-redesign .editorial-step-zero-actions .btn:hover {
    background: #f5fbf5;
    border-color: #4d7f58;
    color: #1f4f2c;
  }
  #container-work-selector.work-selector-redesign .work-selector-steps {
    gap: 0.5rem;
    padding: 0 1rem 1rem;
    border-top-color: #ddd5c8;
  }
  #container-work-selector.work-selector-redesign .editorial-step-chip {
    min-height: 2.55rem;
    border-color: #d1c5b3;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.76);
    color: #4a524f;
  }
  #container-work-selector.work-selector-redesign .editorial-step-chip:hover {
    border-color: #af9f88;
    background: rgba(255, 255, 255, 0.96);
  }
  #container-work-selector.work-selector-redesign .editorial-step-chip.is-active {
    border-color: #90aec1;
    background: var(--selector-step-active);
    color: #234f6a;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.72);
  }
  #container-work-selector.work-selector-redesign .editorial-step-chip-label {
    font-size: 0.84rem;
    letter-spacing: 0.02em;
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
