@extends('layouts.app')

@section('content')
    <div class="container mt-4 editor">
        <h1>Edition de la version <b>{{ $version->name }}</b> pour la comparaison <b>#{{ $comparison->id }}</b></h1>

        @if (!$canEdit)
            <div
                class="alert alert-warning mt-3"
                role="alert"
            >
                <strong>⚠️ Attention :</strong> Les modifications ne sont pas autorisées.
                @if ($isPublished)
                    Cette comparaison est actuellement publiée.
                @elseif(!$imagesData)
                    Les facsimilés pour cette version ne sont pas publiés.
                @endif
            </div>
        @endif

        <ul class="nav nav-tabs mt-3">
            <li class="nav-item">
                <a
                    href="{{ route('comparison.editor', ['comparison' => $comparison->id, 'type' => 'source']) }}"
                    class="nav-link {{ $isSource ? 'active' : '' }}"
                >
                    Source
                </a>
            </li>
            <li class="nav-item">
                <a
                    href="{{ route('comparison.editor', ['comparison' => $comparison->id, 'type' => 'target']) }}"
                    class="nav-link {{ !$isSource ? 'active' : '' }}"
                >
                    Cible
                </a>
            </li>
        </ul>
        <div class="border border-top-0 p-3">
            <button
                id="save-xml"
                class="btn btn-success mb-2"
                {{ !$canEdit ? 'disabled' : '' }}
            >Enregistrer</button>
            <button
                id="toggle-readonly"
                class="btn btn-warning mb-2"
                {{ !$canEdit ? 'disabled' : '' }}
            >Activer le mode édition</button>
            <button
                id="toggle-tags"
                class="btn btn-secondary mb-2"
            >Afficher les balises</button>
            <div class="flex gap-2">
                <div class="row">
                    <div class="col col-6">
                        <div
                            id="editor-container"
                            style="border:1px solid #ccc; height:500px;"
                            class="overflow-scroll"
                        ></div>
                    </div>
                    <div class="col col-2">
                        @foreach ($imagesData ?? [] as $facsimile)
                          <div class="d-flex gap-2 align-items-center">
                              <button
                                  class="btn btn-primary btn-sm mb-1"
                                  data-tag="{{ $loop->iteration }}"
                                  data-img-src="{{ Storage::url($facsimile['big']) }}"
                                  data-enable-when-readonly
                                  {{ !$canEdit ? 'disabled' : '' }}
                              >Insérer {{ $loop->iteration }}</button>
                              <button
                                  class="btn btn-danger btn-sm mb-1"
                                  data-tag-remove="{{ $loop->iteration }}"
                                  data-enable-when-readonly
                                  {{ !$canEdit ? 'disabled' : '' }}
                              >✖️</button>
                              <span
                                  class="badge bg-secondary ms-1"
                                  data-tag-count="{{ $loop->iteration }}"
                                  style="display: none;"
                              ></span>
                          </div>
                        @endforeach
                    </div>
                    <div class="col col-4">
                      <div id="loading-spinner" class="text-center" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                          <span class="visually-hidden">Chargement...</span>
                        </div>
                      </div>
                      <img 
                        id="facsimile-preview" 
                        src="" 
                        alt="Aperçu du facsimilé" 
                        style="max-width: 100%; display: none; border: 1px solid #ccc; padding: 5px;"
                      >
                      <p id="no-preview" class="text-muted"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const xmlContent = @json($xmlContent);
            const comparisonId = {{ $comparison->id }};
            const fileType = '{{ $isSource ? 'source' : 'target' }}';
            const canEdit = {{ $canEdit ? 'true' : 'false' }};

            // DOM Elements
            const elements = {
                toggleBtn: document.getElementById('toggle-readonly'),
                toggleTagsBtn: document.getElementById('toggle-tags'),
                previewImg: document.getElementById('facsimile-preview'),
                noPreviewText: document.getElementById('no-preview'),
                loadingSpinner: document.getElementById('loading-spinner'),
                editorContainer: document.getElementById('editor-container')
            };

            // Constants
            const MESSAGES = {
                DEFAULT: 'Cliquez sur un bouton "Insérer..." pour activer l\'insertion',
                ERROR: 'Erreur lors du chargement de l\'image'
            };

            const BUTTON_STATES = {
                INACTIVE: { add: 'btn-primary', remove: 'btn-warning' },
                ACTIVE: { add: 'btn-warning', remove: ['btn-primary', 'btn-success'] },
                INSERTED: { add: 'btn-success', remove: 'btn-primary' }
            };

            // State
            let activeButton = null;
            let lastImageSrc = null; // Track the last image displayed

            // Initialize editor
            window.initEditor(xmlContent, comparisonId, fileType);
            if (!canEdit && window.editor) {
                window.editor.setReadOnly(true);
            }

            // Utility functions
            const setButtonState = (button, state) => {
                if (Array.isArray(state.remove)) {
                    button.classList.remove(...state.remove);
                } else {
                    button.classList.remove(state.remove);
                }
                button.classList.add(state.add);
            };

            const showMessage = (message) => {
                if (elements.noPreviewText) {
                    elements.noPreviewText.textContent = message;
                    elements.noPreviewText.style.display = 'block';
                }
            };
            showMessage(MESSAGES.DEFAULT);

            const hidePreview = () => {
                // Only hide if no image has been displayed yet
                if (!lastImageSrc) {
                    elements.previewImg.style.display = 'none';
                    showMessage(MESSAGES.DEFAULT);
                }
            };

            const deactivateInsertMode = () => {
                if (activeButton) {
                    setButtonState(activeButton, BUTTON_STATES.INACTIVE);
                    activeButton = null;
                }
            };

            const loadImage = (imgSrc) => {
                if (!imgSrc) return;
                
                lastImageSrc = imgSrc;
                if (elements.noPreviewText) elements.noPreviewText.style.display = 'none';
                
                // Set a timeout to show spinner only if loading takes more than 500ms
                let spinnerTimeout = setTimeout(() => {
                    elements.previewImg.style.display = 'none';
                    elements.loadingSpinner.style.display = 'block';
                }, 500);
                
                const img = new Image();
                img.onload = () => {
                    clearTimeout(spinnerTimeout);
                    elements.loadingSpinner.style.display = 'none';
                    elements.previewImg.src = imgSrc;
                    elements.previewImg.style.display = 'block';
                };
                img.onerror = () => {
                    clearTimeout(spinnerTimeout);
                    elements.loadingSpinner.style.display = 'none';
                    showMessage(MESSAGES.ERROR);
                };
                img.src = imgSrc;
            };

            const updateTagCountBadges = () => {
                document.querySelectorAll('[data-tag-count]').forEach(badge => {
                    const imageName = badge.getAttribute('data-tag-count');
                    if (window.editor) {
                        const count = window.editor.countPageMarkerOccurrences(imageName);
                        if (count > 1) {
                            badge.textContent = `×${count}`;
                            badge.style.display = 'inline';
                            badge.title = `Cette balise apparaît ${count} fois dans le document`;
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                });
            };

            const refreshButtonStates = () => {
                document.querySelectorAll('.editor [data-tag]').forEach(button => {
                    const imageName = button.getAttribute('data-tag');
                    const state = (window.editor && window.editor.isPageMarkerInserted(imageName)) 
                        ? BUTTON_STATES.INSERTED 
                        : BUTTON_STATES.INACTIVE;
                    setButtonState(button, state);
                    if (state === BUTTON_STATES.INSERTED) {
                        button.setAttribute('data-inserted', 'true');
                    } else {
                        button.removeAttribute('data-inserted');
                    }
                });
                updateTagCountBadges();
            };

            // Event handlers
            elements.toggleBtn.addEventListener('click', () => {
                const isReadOnly = window.editor.toggleReadOnly();
                elements.toggleBtn.textContent = isReadOnly ? 'Activer le mode édition' : 'Activer le mode lecture seule';
                elements.toggleBtn.classList.toggle('btn-warning');
                elements.toggleBtn.classList.toggle('btn-info');

                document.querySelectorAll('[data-enable-when-readonly]').forEach(btn => {
                    btn.disabled = !isReadOnly;
                });

                if (isReadOnly) {
                    refreshButtonStates();
                } else {
                    deactivateInsertMode();
                }
            });

            elements.toggleTagsBtn.addEventListener('click', () => {
                const tagsHidden = window.editor.toggleTagVisibility();
                elements.toggleTagsBtn.textContent = tagsHidden ? 'Afficher les balises' : 'Masquer les balises';
                elements.toggleTagsBtn.classList.toggle('btn-secondary');
                elements.toggleTagsBtn.classList.toggle('btn-info');
            });

            // Insert buttons
            document.querySelectorAll('.editor [data-tag]').forEach(button => {
                const imgSrc = button.getAttribute('data-img-src');

                button.addEventListener('click', () => {
                    const imageName = button.getAttribute('data-tag');

                    // Si le tag est déjà inséré, scroll vers celui-ci
                    if (window.editor && window.editor.isPageMarkerInserted(imageName)) {
                        window.editor.scrollToPageMarker(imageName);
                        return;
                    }

                    // Si le bouton est déjà actif, on le désactive
                    if (activeButton === button) {
                        deactivateInsertMode();
                        return;
                    }

                    if (activeButton) {
                        setButtonState(activeButton, BUTTON_STATES.INACTIVE);
                    }

                    setButtonState(button, BUTTON_STATES.ACTIVE);
                    activeButton = button;
                    loadImage(imgSrc);
                });

                // Hover to show image preview
                button.addEventListener('mouseenter', () => {
                    if (imgSrc) {
                        loadImage(imgSrc);
                    }
                });

                // On mouse leave, restore active button image or keep last image
                button.addEventListener('mouseleave', () => {
                    if (activeButton && activeButton !== button) {
                        // Restore active button image
                        const activeImgSrc = activeButton.getAttribute('data-img-src');
                        loadImage(activeImgSrc);
                    }
                    // If no active button, keep the last image displayed (no hiding)
                });
            });

            // Editor click to insert tag
            if (elements.editorContainer) {
                elements.editorContainer.addEventListener('click', () => {
                    if (activeButton && window.editor) {
                        const imageName = activeButton.getAttribute('data-tag');
                        const pageNumber = '001'; // Hard coded for now
                        window.editor.insertPageMarker(imageName, pageNumber);
                        deactivateInsertMode();
                        refreshButtonStates();
                    }
                });
            }

            // Remove buttons
            document.querySelectorAll('.editor [data-tag-remove]').forEach(removeBtn => {
                removeBtn.addEventListener('click', async () => {
                    const imageName = removeBtn.getAttribute('data-tag-remove');
                    const removed = await window.editor.removePageMarker(imageName);

                    if (removed) {
                        refreshButtonStates();
                    } else {
                        console.error(`Balise pour l'image "${imageName}" introuvable dans l'éditeur`);
                    }
                });
            });

            refreshButtonStates();
        });
    </script>
@endpush
