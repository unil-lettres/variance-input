import initEditor from './codemirror-editor';

document.addEventListener('DOMContentLoaded', () => {
    const { xmlContent, comparisonId, fileType, canEdit } = window.editorParams;

    // DOM Elements
    const elements = {
        saveBtn: document.getElementById('save-xml'),
        toggleBtn: document.getElementById('toggle-readonly'),
        toggleTagsBtn: document.getElementById('toggle-tags'),
        generatePageNumbersBtn: document.getElementById('generate-page-numbers'),
        searchBtn: document.getElementById('search-btn'),
        italicOpenBtn: document.getElementById('italic-open-btn'),
        italicCloseBtn: document.getElementById('italic-close-btn'),
        previewImg: document.getElementById('facsimile-preview'),
        noPreviewText: document.getElementById('no-preview'),
        loadingSpinner: document.getElementById('loading-spinner'),
        editorContainer: document.getElementById('editor-container'),
        imageName: document.getElementById('image-name'),
        pagination: document.getElementById('pagination'),
        generatePageNumbersModal: document.getElementById('generatePageNumbersModal'),
    };

    // Constants
    const MESSAGES = {
        DEFAULT: 'Aperçu de la page sélectionnée',
        ERROR: 'Erreur lors du chargement de l\'image'
    };

    const BUTTON_STATES = {
        INACTIVE: { add: 'btn-primary', remove: ['btn-warning', 'btn-success', 'btn-danger'] },
        ACTIVE_INSERT: { add: 'btn-warning', remove: ['btn-primary', 'btn-success', 'btn-danger'] },
        INSERTED: { add: 'btn-success', remove: ['btn-primary', 'btn-warning', 'btn-danger'] },
        ACTIVE_DELETE: { add: 'btn-danger', remove: ['btn-primary', 'btn-warning', 'btn-success'] }
    };

    // State - only one active button at a time, either in insert or delete mode
    let activeButton = null;
    let isDeleteMode = false; // true = delete mode, false = insert mode

    const itemsPerPage = 40;
    let tagsWereHiddenBeforeEdit = true;
    let hasUnsavedChanges = false;
    let initialXmlContent = xmlContent;

    const editor = initEditor(document.getElementById('editor-container'), xmlContent);

    if (!canEdit) {
        editor.setReadOnly(true);
    }

    // Track changes in the editor
    editor.onContentChanged(() => {
        const currentContent = editor.view.state.doc.toString();
        hasUnsavedChanges = currentContent !== initialXmlContent;
        
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

    editor.onPageNumbersChanged((pageNumbers) => {
        pageNumbers.forEach(({ imageName, content }) => {
            const button = document.querySelector(`button[data-tag="${imageName}"]`);
            if (button) {
                button.setAttribute('data-tag-page-number', content);
                button.querySelector('span').textContent = content;
            }
        });

        updatePaginationColors();
    });

    editor.onSearchPanelStateChanged((isOpen) => {
        elements.searchBtn.classList.toggle('active', isOpen);
    });

    // Pagination functions
    const checkIfPageFullyInserted = (pageNumber) => {
        const pageButtons = document.querySelectorAll(`.button-item[data-page="${pageNumber}"] button[data-tag]`);
        if (pageButtons.length === 0) return false;

        const { insertedMarkers } = editor.getAllMarkers();
        let allInserted = true;
        pageButtons.forEach(button => {
            const imageName = button.getAttribute('data-tag');
            if (!insertedMarkers.has(imageName)) {
                allInserted = false;
            }
        });

        return allInserted;
    };

    const checkIfPageHasDuplicates = (pageNumber) => {
        const pageButtons = document.querySelectorAll(`.button-item[data-page="${pageNumber}"] button[data-tag]`);

        const { markerCounts } = editor.getAllMarkers();
        for (let button of pageButtons) {
            const imageName = button.getAttribute('data-tag');
            const count = markerCounts.get(imageName) || 0;
            if (count > 1) {
                return true;
            }
        }

        return false;
    };

    const updatePaginationColors = () => {
        document.querySelectorAll('#pagination .page-item').forEach((item, index) => {
            const pageNumber = index + 1;
            const link = item.querySelector('.page-link');
            const hasDuplicates = checkIfPageHasDuplicates(pageNumber);
            const isFullyInserted = checkIfPageFullyInserted(pageNumber);

            if (hasDuplicates) {
                link.classList.add('page-has-duplicates');
                link.classList.remove('page-fully-inserted');
            } else if (isFullyInserted) {
                link.classList.add('page-fully-inserted');
                link.classList.remove('page-has-duplicates');
            } else {
                link.classList.remove('page-fully-inserted');
                link.classList.remove('page-has-duplicates');
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

    const refreshButtonName = (button) => {
        const imageName = button.getAttribute('data-tag');
        const insertedPageNumber = editor.getPageNumber(imageName);

        if (insertedPageNumber) {
            button.setAttribute('data-tag-page-number', insertedPageNumber);
        }

        const pageNumber = button.getAttribute('data-tag-page-number');
        button.querySelector('span').textContent = pageNumber || '?';
    }

    const deactivateActiveButton = () => {
        if (activeButton) {
            const imageName = activeButton.getAttribute('data-tag');
            const isInserted = editor.isPageMarkerInserted(imageName);

            // Reset to appropriate state
            if (isInserted) {
                setButtonState(activeButton, BUTTON_STATES.INSERTED);
            } else {
                setButtonState(activeButton, BUTTON_STATES.INACTIVE);
            }

            refreshButtonName(activeButton);

            activeButton = null;
            isDeleteMode = false;
            elements.editorContainer.style.cursor = 'default';
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
        deactivateActiveButton();

        const { insertedMarkers } = editor.getAllMarkers();

        document.querySelectorAll('.editor [data-tag]').forEach(button => {
            const imageName = button.getAttribute('data-tag');
            const isInserted = insertedMarkers.has(imageName);

            const state = isInserted ? BUTTON_STATES.INSERTED : BUTTON_STATES.INACTIVE;
            setButtonState(button, state);

            refreshButtonName(button);

            if (isInserted) {
                button.setAttribute('data-inserted', 'true');
            } else {
                button.removeAttribute('data-inserted');
            }
        });
        updateTagCountBadges();
        updatePaginationColors();
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
        const modal = bootstrap.Modal.getInstance(document.getElementById('generatePageNumbersModal'));
        modal.hide();
    });

    // Insert buttons
    document.querySelectorAll('.editor [data-tag]').forEach(button => {
        const imgSrc = button.getAttribute('data-img-src');
        const imageName = button.getAttribute('data-tag');

        button.addEventListener('click', () => {
            const isInserted = editor.isPageMarkerInserted(imageName);

            if (activeButton === button) {
                if (isDeleteMode) {
                    editor.removePageMarker(imageName);
                    refreshButtonStates();
                } else {
                    deactivateActiveButton();
                }
                return;
            }

            if (activeButton) {
                deactivateActiveButton();
            }

            activeButton = button;
            loadImage(imgSrc);

            if (isInserted) {
                isDeleteMode = true;
                setButtonState(button, BUTTON_STATES.ACTIVE_DELETE);
                button.querySelector('span').textContent = '🗑️';
                editor.scrollToPageMarker(imageName);
                elements.editorContainer.style.cursor = 'default';
            } else {
                isDeleteMode = false;
                setButtonState(button, BUTTON_STATES.ACTIVE_INSERT);
                refreshButtonName(button);
                elements.editorContainer.style.cursor = 'crosshair';
            }
        });

        // Hover to show image preview
        button.addEventListener('mouseenter', () => {
            if (imgSrc) {
                loadImage(imgSrc);
            }
        });

        // On mouse leave, restore active button image if there is one
        button.addEventListener('mouseleave', () => {
            // If leaving the active button in delete mode, deactivate it
            if (activeButton === button && isDeleteMode) {
                deactivateActiveButton();
            } else if (activeButton && activeButton !== button) {
                const activeImgSrc = activeButton.getAttribute('data-img-src');
                loadImage(activeImgSrc);
            }
        });
    });

    // Editor click to insert tag
    if (elements.editorContainer) {
        elements.editorContainer.addEventListener('click', (e) => {
            // Only insert page marker if we have an active button in insert mode
            if (activeButton && !isDeleteMode) {
                const imageName = activeButton.getAttribute('data-tag');
                const pageNumber = activeButton.getAttribute('data-tag-page-number') || '001';
                editor.insertPageMarker(imageName, pageNumber);
                refreshButtonStates();
                deactivateActiveButton();
            }
        });
    }

    elements.saveBtn.addEventListener('click', async () => {
        const updatedXml = editor.view.state.doc.toString();
        const response = await fetch(`/comparison/${comparisonId}/editor?type=${fileType}`, {
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
