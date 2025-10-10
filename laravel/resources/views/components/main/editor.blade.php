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
                      <p id="no-preview" class="text-muted">Cliquez sur un bouton "Insérer..." pour activer l'insertion</p>
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
            const isPublished = {{ $isPublished ? 'true' : 'false' }};
            const imagesData = @json($imagesData ?? null); // TODO not used yet

            window.initEditor(xmlContent, comparisonId, fileType);

            if (!canEdit && window.editor) {
                window.editor.setReadOnly(true);
            }

            // Handle toggle readonly button
            const toggleBtn = document.getElementById('toggle-readonly');
            const toggleTagsBtn = document.getElementById('toggle-tags');

            const setTagInserted = (button) => {
                button.classList.remove('btn-primary');
                button.classList.add('btn-success');
                button.setAttribute('data-inserted', 'true');
            };

            const setTagNotInserted = (button) => {
                button.classList.remove('btn-success');
                button.classList.add('btn-primary');
                button.removeAttribute('data-inserted');
            };

            const updateTagCountBadges = () => {
                document.querySelectorAll('[data-tag-count]').forEach(badge => {
                    const tagName = badge.getAttribute('data-tag-count');
                    if (window.editor) {
                        const count = window.editor.countTagOccurrences(tagName);
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
                    const tagName = button.getAttribute('data-tag');
                    if (window.editor && window.editor.isTagInserted(tagName)) {
                        setTagInserted(button);
                    } else {
                        setTagNotInserted(button);
                    }
                });
                updateTagCountBadges();
            };

            toggleBtn.addEventListener('click', () => {
                const isReadOnly = window.editor.toggleReadOnly();
                toggleBtn.textContent = isReadOnly ? 'Activer le mode édition' : 'Activer le mode lecture seule';
                toggleBtn.classList.toggle('btn-warning');
                toggleBtn.classList.toggle('btn-info');

                // Disable/enable buttons with data-enable-when-readonly attribute
                document.querySelectorAll('[data-enable-when-readonly]').forEach(btn => {
                    btn.disabled = !isReadOnly;
                });

                if (isReadOnly) {
                    refreshButtonStates();
                } else {
                    // Désactiver le mode d'insertion quand on active le mode édition
                    if (activeButton) {
                        activeButton.classList.remove('btn-warning');
                        activeButton.classList.add('btn-primary');
                        activeButton = null;
                        
                        // Masquer l'image
                        previewImg.style.display = 'none';
                        if (noPreviewText) {
                            noPreviewText.textContent = 'Cliquez sur un bouton "Insérer..." pour activer l\'insertion';
                            noPreviewText.style.display = 'block';
                        }
                    }
                }
            });

            // Handle toggle tags visibility button
            toggleTagsBtn.addEventListener('click', () => {
                const tagsHidden = window.editor.toggleTagVisibility();
                toggleTagsBtn.textContent = tagsHidden ? 'Afficher les balises' : 'Masquer les balises';
                toggleTagsBtn.classList.toggle('btn-secondary');
                toggleTagsBtn.classList.toggle('btn-info');
            });

            // Handle insert buttons - toggle mode
            let activeButton = null;

            document.querySelectorAll('.editor [data-tag]').forEach(button => {
                const tagName = button.getAttribute('data-tag');
                const imgSrc = button.getAttribute('data-img-src');

                button.addEventListener('click', () => {
                    // Si le bouton est déjà actif, on le désactive
                    if (activeButton === button) {
                        button.classList.remove('btn-warning');
                        button.classList.add('btn-primary');
                        activeButton = null;
                        
                        // Masquer l'image
                        previewImg.style.display = 'none';
                        if (noPreviewText) {
                            noPreviewText.textContent = 'Cliquez sur un bouton "Insérer..." pour activer l\'insertion';
                            noPreviewText.style.display = 'block';
                        }
                        return;
                    }

                    // Désactiver le bouton précédent s'il existe
                    if (activeButton) {
                        activeButton.classList.remove('btn-warning');
                        activeButton.classList.add('btn-primary');
                    }

                    // Activer le bouton actuel
                    button.classList.remove('btn-primary', 'btn-success');
                    button.classList.add('btn-warning');
                    activeButton = button;

                    // Afficher l'image
                    if (imgSrc) {
                        if (noPreviewText) noPreviewText.style.display = 'none';
                        loadingSpinner.style.display = 'block';
                        
                        const img = new Image();
                        img.onload = () => {
                            loadingSpinner.style.display = 'none';
                            previewImg.src = imgSrc;
                            previewImg.style.display = 'block';
                        };
                        img.onerror = () => {
                            loadingSpinner.style.display = 'none';
                            if (noPreviewText) {
                                noPreviewText.textContent = 'Erreur lors du chargement de l\'image';
                                noPreviewText.style.display = 'block';
                            }
                        };
                        img.src = imgSrc;
                    }
                });
            });

            // Gérer le clic dans l'éditeur pour insérer le tag
            const editorContainer = document.getElementById('editor-container');
            if (editorContainer) {
                editorContainer.addEventListener('click', () => {
                    if (activeButton && window.editor) {
                        const tagName = activeButton.getAttribute('data-tag');
                        window.editor.insertAtCursor(tagName);
                        
                        // Désactiver le bouton
                        activeButton.classList.remove('btn-warning');
                        activeButton.classList.add('btn-primary');
                        activeButton = null;
                        
                        // Masquer l'image
                        previewImg.style.display = 'none';
                        if (noPreviewText) {
                            noPreviewText.textContent = 'Cliquez sur un bouton "Insérer..." pour activer l\'insertion';
                            noPreviewText.style.display = 'block';
                        }
                        
                        refreshButtonStates();
                    }
                });
            }

            // Handle remove buttons
            document.querySelectorAll('.editor [data-tag-remove]').forEach(removeBtn => {
                removeBtn.addEventListener('click', async () => {
                    const tagName = removeBtn.getAttribute('data-tag-remove');
                    const removed = await window.editor.removeTag(tagName);

                    if (removed) {
                        refreshButtonStates();
                    } else {
                        console.error(`Balise "${tagName}" introuvable dans l'éditeur`);
                    }
                });
            });

            refreshButtonStates();

            // Handle image preview on hover (removed - now handled by button click)
            const previewImg = document.getElementById('facsimile-preview');
            const noPreviewText = document.getElementById('no-preview');
            const loadingSpinner = document.getElementById('loading-spinner');
        });
    </script>
@endpush
