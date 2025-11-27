import initEditor from './codemirror-editor';

document.addEventListener('DOMContentLoaded', () => {
    const { xmlContent, urlFileSave, canEdit, markerType } = window.editorParams;

    // Initialize Bootstrap tooltips.
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"], [data-bs-toggle="modal"]');
    const bootstrapLib = window.bootstrap;
    if (!bootstrapLib) {
        console.error('Bootstrap JavaScript library is not available on window.bootstrap.');
        return;
    }

    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrapLib.Tooltip(
        tooltipTriggerEl,
        {
            delay: { "show": 500, "hide": 100 },
            trigger: 'hover',
            offset: [0, 6],
        }
    ));

    // DOM Elements
    const elements = {
        saveBtn: document.getElementById('save-xml'),
        toggleBtn: document.getElementById('toggle-readonly'),
        toggleTagsBtn: document.getElementById('toggle-tags'),
        generatePageNumbersBtn: document.getElementById('generate-page-numbers'),
        searchBtn: document.getElementById('search-btn'),
        italicOpenBtn: document.getElementById('italic-open-btn'),
        italicCloseBtn: document.getElementById('italic-close-btn'),
        italicReportBtn: document.getElementById('italic-report-btn'),
        previewImg: document.getElementById('facsimile-preview'),
        noPreviewText: document.getElementById('no-preview'),
        loadingSpinner: document.getElementById('loading-spinner'),
        editorContainer: document.getElementById('editor-container'),
        imageName: document.getElementById('image-name'),
        pagination: document.getElementById('pagination'),
        generatePageNumbersModal: document.getElementById('generatePageNumbersModal'),
        italicErrorsModal: document.getElementById('italicErrorsModal'),
        italicErrorsList: document.getElementById('italic-errors-list'),
    };

    // Constants
    const MESSAGES = {
        DEFAULT: 'Aperçu de la page sélectionnée',
        ERROR: 'Erreur lors du chargement de l\'image'
    };

    const BUTTON_STATES = {
        INACTIVE: 'btn-secondary',
        ACTIVE_INSERT: 'btn-insert',
        INSERTED: 'btn-success',
        NOT_NAMED: 'btn-warning',
    };

    // State - only one active button at a time, either in insert or delete mode
    let activeButton = null;
    const tooltipsMap = new Map();

    const itemsPerPage = 39;
    let tagsWereHiddenBeforeEdit = true;
    let hasUnsavedChanges = false;
    let initialXmlContent = xmlContent;

    const editor = initEditor(document.getElementById('editor-container'), xmlContent, markerType);

    if (!canEdit) {
        editor.setReadOnly(true);
    }

    // Track changes in the editor
    editor.onContentChanged(() => {
        const currentContent = editor.view.state.doc.toString();
        hasUnsavedChanges = currentContent !== initialXmlContent;

        refreshButtonStates();

        // Update save button appearance
        if (hasUnsavedChanges) {
            elements.saveBtn.classList.remove('btn-success');
            elements.saveBtn.classList.add('btn-danger');
        } else {
            elements.saveBtn.classList.remove('btn-danger');
            elements.saveBtn.classList.add('btn-success');
        }
    });

    editor.onPageNumberClicked(() => {
        refreshButtonStates();
    });

    editor.onSearchPanelStateChanged((isOpen) => {
        elements.searchBtn.classList.toggle('active', isOpen);
    });

    const getPageStatus = (pageNumber) => {
        const pageButtons = document.querySelectorAll(`.button-item[data-page="${pageNumber}"] button[data-tag]`);
        if (pageButtons.length === 0) return { hasDuplicates: false, isFullyInserted: false, hasPageWithoutNumber: false };

        const { insertedMarkers, markerCounts } = editor.getAllMarkers();
        let allInserted = true;
        let hasDuplicates = false;
        let hasPageWithoutNumber = false;

        pageButtons.forEach(button => {
            const imageName = button.getAttribute('data-tag');

            if (!insertedMarkers.has(imageName)) {
                allInserted = false;
            }

            const count = markerCounts.get(imageName) || 0;
            if (count > 1) {
                hasDuplicates = true;
            }

            const pageNum = editor.getPageNumber(imageName);
            if (!pageNum || pageNum === '?') {
                hasPageWithoutNumber = true;
            }
        });

        return { hasDuplicates, isFullyInserted: allInserted, hasPageWithoutNumber };
    };

    const updatePaginationColors = () => {
        document.querySelectorAll('#pagination .page-item').forEach((item, index) => {
            const pageNumber = index + 1;
            const link = item.querySelector('.page-link');
            const { hasDuplicates, isFullyInserted, hasPageWithoutNumber } = getPageStatus(pageNumber);

            link.classList.remove('page-fully-inserted');
            link.classList.remove('page-has-duplicates');
            link.classList.remove('page-has-without-number');

            if (hasDuplicates) {
                link.classList.add('page-has-duplicates');
            } else if (isFullyInserted && !hasPageWithoutNumber) {
                link.classList.add('page-fully-inserted');
            } else if (isFullyInserted && hasPageWithoutNumber) {
                link.classList.add('page-has-without-number');
            }
        });
    };

    const initPagination = () => {
        const allButtons = document.querySelectorAll('.button-item');
        const totalPages = Math.ceil(allButtons.length / itemsPerPage);

        allButtons.forEach((item, index) => {
            const itemPage = Math.ceil((index + 1) / itemsPerPage);
            item.setAttribute('data-page', itemPage);
        });

        if (totalPages <= 1) {
            elements.pagination.parentElement.style.display = 'none';
            showPage(1);
            return;
        }

        elements.pagination.innerHTML = '';

        for (let i = 1; i <= totalPages; i++) {
            const li = document.createElement('li');
            li.className = 'page-item';

            const link = document.createElement('a');
            link.className = 'page-link shadow-none';
            link.href = '#';
            link.textContent = i;
            link.addEventListener('click', (e) => {
                e.preventDefault();
                showPage(i);
            });

            li.appendChild(link);
            elements.pagination.appendChild(li);
        }

        showPage(1);
    };

    const showPage = (page) => {
        const allButtons = document.querySelectorAll('.button-item');
        allButtons.forEach((item) => {
            const itemPage = parseInt(item.getAttribute('data-page'));
            item.style.display = itemPage === page ? '' : 'none';
        });

        // Update pagination active state
        document.querySelectorAll('#pagination .page-item').forEach((item, index) => {
            if (index + 1 === page) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    };

    // Utility functions
    const setButtonState = (button, state) => {
        // Remove all button state classes.
        const btnClasses = new Set(Object.values(BUTTON_STATES));
        btnClasses.add('btn-outlined');
        button.classList.remove(...(Array.from(button.classList).filter(c => btnClasses.has(c))));

        button.classList.add(state);

        if (state === BUTTON_STATES.ACTIVE_INSERT) {
            button.classList.add('btn-outlined');
        }
    };

    const showMessage = (message) => {
        if (elements.noPreviewText) {
            elements.noPreviewText.textContent = message;
            elements.noPreviewText.style.display = 'block';
        }
    };
    showMessage(MESSAGES.DEFAULT);

    const refreshButtonName = (button) => {
        const imageName = button.getAttribute('data-tag');
        const insertedPageNumber = editor.getPageNumber(imageName);

        if (insertedPageNumber) {
            button.setAttribute('data-tag-page-number', insertedPageNumber);
        }

        const pageNumber = button.getAttribute('data-tag-page-number');
        if (pageNumber && pageNumber !== '?') {
            button.querySelector('span').textContent = pageNumber
        } else {
            const span = button.querySelector('span');
            const existingIcon = span.querySelector('i.bi-file-earmark');
            span.textContent = insertedPageNumber ? '?' : '';

            if (!existingIcon) {
              const i = document.createElement('i');
              i.className = 'bi bi-file-earmark mr-1';
              span.prepend(i);
            } else {
              span.prepend(existingIcon);
            }
        }
    }

    const deactivateActiveButton = () => {
        if (activeButton) {
            const imageName = activeButton.getAttribute('data-tag');
            const isInserted = editor.isPageMarkerInserted(imageName);
            const pageNumber = editor.getPageNumber(imageName);

            // Reset to appropriate state
            if (isInserted) {
                const isNamed = pageNumber && pageNumber !== '?';
                setButtonState(activeButton, isNamed ? BUTTON_STATES.INSERTED : BUTTON_STATES.NOT_NAMED);
            } else {
                setButtonState(activeButton, BUTTON_STATES.INACTIVE);
            }

            refreshButtonName(activeButton);

            activeButton = null;
            elements.editorContainer.classList.remove('insert-page');

            elements.noPreviewText.style.display = 'block';
            elements.previewImg.parentElement.style.display = 'none';
            elements.imageName.style.display = 'none';
        }
    };

    const loadImage = (imgSrc) => {
        if (!imgSrc) return;

        if (elements.noPreviewText) elements.noPreviewText.style.display = 'none';

        // Extract filename from URL
        const filename = imgSrc.split('/').pop();

        // Set a timeout to show spinner only if loading takes more than 500ms
        let spinnerTimeout = setTimeout(() => {
            elements.previewImg.parentElement.style.display = 'none';
            elements.loadingSpinner.style.display = 'block';
        }, 500);

        const img = new Image();
        img.onload = () => {
            clearTimeout(spinnerTimeout);
            elements.loadingSpinner.style.display = 'none';
            elements.previewImg.src = imgSrc;
            elements.previewImg.parentElement.style.display = 'block';

            // Show filename
            if (elements.imageName) {
                elements.imageName.textContent = filename;
                elements.imageName.href = imgSrc;
                elements.imageName.style.display = 'block';
            }
        };
        img.onerror = () => {
            clearTimeout(spinnerTimeout);
            elements.loadingSpinner.style.display = 'none';
            showMessage(MESSAGES.ERROR);

            // Hide filename on error
            if (elements.imageName) {
                elements.imageName.style.display = 'none';
                elements.imageName.href = '#';
            }
        };
        img.src = imgSrc;
    };

    const updateTagCountBadges = () => {
        const { markerCounts } = editor.getAllMarkers();

        document.querySelectorAll('[data-tag-count]').forEach(badge => {
            const imageName = badge.getAttribute('data-tag-count');
            const count = markerCounts.get(imageName) || 0;
            if (count > 1) {
                badge.textContent = `×${count}`;
                badge.style.display = 'inline';
                badge.title = `Cette balise apparaît ${count} fois dans le document`;
            } else {
                badge.style.display = 'none';
            }
        });
    };

    const refreshButtonStates = () => {
        editor.stopEnsureCacheUpdate();
        deactivateActiveButton();

        const { insertedMarkers } = editor.getAllMarkers();

        document.querySelectorAll('.editor [data-tag]').forEach(button => {
            const imageName = button.getAttribute('data-tag');
            const isInserted = insertedMarkers.has(imageName);
            const pageNumber = editor.getPageNumber(imageName);

            if (isInserted) {
                const isNamed = pageNumber && pageNumber !== '?';
                setButtonState(button, isNamed ? BUTTON_STATES.INSERTED : BUTTON_STATES.NOT_NAMED);
            } else {
                setButtonState(button, BUTTON_STATES.INACTIVE);
            }

            refreshButtonName(button);

            if (isInserted) {
                button.setAttribute('data-inserted', 'true');
            } else {
                button.removeAttribute('data-inserted');
            }
        });
        updateTagCountBadges();
        updatePaginationColors();
        editor.resumeEnsureCacheUpdate();
    };

    const updateTagsButtonUI = (tagsHidden) => {
        elements.toggleTagsBtn.classList.toggle('active', !tagsHidden);
    };

    // Event handlers
    elements.toggleBtn.addEventListener('click', () => {
        const isReadOnly = editor.toggleReadOnly();
        elements.toggleBtn.classList.toggle('active', !isReadOnly);
        elements.toggleTagsBtn.disabled = !isReadOnly;

        if (isReadOnly) {
            refreshButtonStates();
            if (tagsWereHiddenBeforeEdit) {
                if (!editor.getTagVisibility()) {
                    editor.toggleTagVisibility();
                    updateTagsButtonUI(true);
                }
            }
        } else {
            tagsWereHiddenBeforeEdit = editor.getTagVisibility();
            deactivateActiveButton();

            if (tagsWereHiddenBeforeEdit) {
                editor.toggleTagVisibility();
                updateTagsButtonUI(false);
            }
        }
    });

    elements.toggleTagsBtn.addEventListener('click', () => {
        const tagsHidden = editor.toggleTagVisibility();
        updateTagsButtonUI(tagsHidden);
    });

    elements.searchBtn.addEventListener('click', () => {
        editor.toggleSearch();
    });

    // Italic buttons handlers
    elements.italicOpenBtn.addEventListener('click', () => {
        editor.insertItalicOpenTag();
    });

    elements.italicCloseBtn.addEventListener('click', () => {
        editor.insertItalicCloseTag();
    });

    // Italic errors report handler
    elements.italicErrorsModal.addEventListener('show.bs.modal', () => {
        const errors = editor.validateItalicTags();

        if (errors.length === 0) {
            elements.italicErrorsList.innerHTML = `
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle-fill"></i> Aucune erreur détectée ! Tous les tags italiques sont valides.
                </div>
            `;
        } else {
            let html = `
                <div class="alert alert-warning" role="alert">
                    <strong>${errors.length} erreur(s) détectée(s)</strong>
                </div>
                <div class="list-group">
            `;

            errors.forEach((error, index) => {
                html += `
                    <button
                        type="button"
                        class="list-group-item list-group-item-action error-item"
                        data-error-pos="${error.pos}"
                    >
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">
                                <i class="bi bi-x-circle text-danger"></i>
                                Erreur ${index + 1}
                            </h6>
                            <small class="text-muted">Ligne ${error.lineNumber}</small>
                        </div>
                        <p class="mb-1">${error.message}</p>
                        <small class="text-muted">Cliquez pour localiser dans l'éditeur</small>
                    </button>
                `;
            });

            html += '</div>';
            elements.italicErrorsList.innerHTML = html;

            // Add click handlers to error items
            document.querySelectorAll('.error-item').forEach(item => {
                item.addEventListener('click', () => {
                    const pos = parseInt(item.getAttribute('data-error-pos'));

                    // Close modal
                    const modal = bootstrapLib.Modal.getInstance(elements.italicErrorsModal);
                    modal.hide();

                    // Navigate to error position
                    editor.scrollToPosition(pos);
                });
            });
        }
    });

    elements.generatePageNumbersModal.addEventListener('shown.bs.modal', () => {
        document.getElementById('leadingZeros').focus();
    });

    // Handle Enter key in modal inputs
    elements.generatePageNumbersModal.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('confirmGeneratePageNumbers').click();
        }
    });

    // Handle modal confirmation
    document.getElementById('confirmGeneratePageNumbers').addEventListener('click', () => {
        const leadingZerosInput = document.getElementById('leadingZeros');
        const startPageNumberInput = document.getElementById('startPageNumber');

        const leadingZerosNum = parseInt(leadingZerosInput.value);
        const startPageNum = parseInt(startPageNumberInput.value);

        // Validate inputs
        if (isNaN(leadingZerosNum) || leadingZerosNum < 0 || leadingZerosNum > 4) {
            alert("Veuillez entrer un nombre entre 0 et 4 pour les zéros de remplissage.");
            return;
        }

        if (isNaN(startPageNum) || startPageNum < 0) {
            alert("Veuillez entrer un nombre de pages valide.");
            return;
        }

        // Apply page numbers to all non-inserted buttons
        const { insertedMarkers } = editor.getAllMarkers();
        let currentPageNum = 0;

        document.querySelectorAll('.editor [data-tag]').forEach(button => {
            currentPageNum++;

            const imageName = button.getAttribute('data-tag');
            const isInserted = insertedMarkers.has(imageName);

            if (startPageNum >= currentPageNum) {
                if (!isInserted) {
                    button.setAttribute('data-tag-page-number', '?');
                }
            } else {
                if (!isInserted) {
                    const pageNumber = String(currentPageNum - startPageNum).padStart(leadingZerosNum, '0');
                    button.setAttribute('data-tag-page-number', pageNumber);
                }
            }
        });

        // Refresh button states to update display
        refreshButtonStates();

        // Close modal
        const modal = bootstrapLib.Modal.getInstance(document.getElementById('generatePageNumbersModal'));
        modal.hide();
    });

    // Insert buttons
    document.querySelectorAll('.editor [data-tag]').forEach(button => {
        const imgSrc = button.getAttribute('data-img-src');
        const imageName = button.getAttribute('data-tag');

        const tooltip = new bootstrapLib.Tooltip(button, {
            title: () => {
                const isInserted = editor.isPageMarkerInserted(imageName);
                if (isInserted) {
                    return 'Afficher ce marqueur de page dans l\'éditeur';
                } else {
                  if (activeButton === button) {
                      return 'Cliquez dans le texte pour insérer ce marqueur de page';
                  } else {
                      return 'Cliquez pour sélectionner ce marqueur de page';
                  }
                }
            },
            trigger: 'hover',
            delay: { "show": 300, "hide": 0 },
            offset: [0, 10],
        });
        tooltipsMap.set(button, tooltip);

        button.addEventListener('click', () => {
            const isInserted = editor.isPageMarkerInserted(imageName);

            if (activeButton === button) {
                if (isInserted) {
                    editor.scrollToPageMarker(imageName);
                } else {
                    deactivateActiveButton();
                }
            } else {
              if (activeButton) {
                  deactivateActiveButton();
              }

              activeButton = button;
              button.classList.add('btn-outlined');
              loadImage(imgSrc);

              if (isInserted) {
                  editor.scrollToPageMarker(imageName);
              } else {
                  setButtonState(button, BUTTON_STATES.ACTIVE_INSERT);
                  refreshButtonName(button);
                  elements.editorContainer.classList.add('insert-page');
              }
            }
            tooltipsMap.get(button)?.show();
        });
    });

    // Editor click to insert tag
    elements.editorContainer.addEventListener('click', (e) => {
        // Only insert page marker if we have an active button in insert mode
        if (activeButton) {
            const imageName = activeButton.getAttribute('data-tag');
            const pageNumber = activeButton.getAttribute('data-tag-page-number') || '?';

            if (!editor.isPageMarkerInserted(imageName)) {
              const success = editor.insertPageMarker(imageName, pageNumber);
              if (success) {
                  refreshButtonStates();
                  deactivateActiveButton();
              }
            } else {
              deactivateActiveButton();
            }
        }
    });

    // Close insert mode when clicking anywhere on the page (except on buttons or editor)
    document.addEventListener('click', (e) => {
        if (activeButton) {
            const isClickOnEditor = elements.editorContainer.contains(e.target);
            const isClickOnButton = e.target.closest('[data-tag]');
            const isClickOnImageUrl = e.target.id === 'image-name';

            if (!isClickOnEditor && !isClickOnButton && !isClickOnImageUrl) {
                deactivateActiveButton();
            }
        }
    });

    elements.saveBtn.addEventListener('click', async () => {
        const updatedXml = editor.view.state.doc.toString();
        const response = await fetch(urlFileSave, {
            method: 'PUT',
            headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/xml',
            'Accept': 'application/json'
            },
            body: updatedXml
        });

        const data = await response.json();

        if (response.status === 403) {
            alert(data.error || 'Modification non autorisée : cette comparaison est publiée.');
        } else if (response.ok) {
            alert(data.message || 'Fichier sauvegardé avec succès !');
            hasUnsavedChanges = false;
            initialXmlContent = updatedXml;

            // Reset button to success state
            elements.saveBtn.classList.remove('btn-danger');
            elements.saveBtn.classList.add('btn-success');
        } else {
            alert(data.error || 'Une erreur est survenue lors de la sauvegarde.');
        }
    });

    // Warn user before leaving page if there are unsaved changes
    window.addEventListener('beforeunload', (e) => {
        if (hasUnsavedChanges) {
            e.preventDefault();
        }
    });

    // Image zoom on mouse hover
    if (elements.previewImg) {
        elements.previewImg.addEventListener('mousemove', (e) => {
            const rect = elements.previewImg.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;

            elements.previewImg.style.transformOrigin = `${x}% ${y}%`;
            elements.previewImg.style.transform = 'scale(4)';
            elements.previewImg.style.cursor = 'zoom-in';
        });

        elements.previewImg.addEventListener('mouseleave', () => {
            elements.previewImg.style.transform = 'scale(1)';
            elements.previewImg.style.transformOrigin = 'center center';
            elements.previewImg.style.cursor = 'default';
        });
    }

    initPagination();
    refreshButtonStates();

    // Use CodeMirror's ready event to hide tags after initial render
    // This fixes Firefox's slow rendering when tags are hidden from the start
    editor.onEditorReady(() => {
        if (editor.getTagVisibility() === false) {
            editor.toggleTagVisibility();
            updateTagsButtonUI(true);
        }
    });
});
