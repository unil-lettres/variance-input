import initEditor from './codemirror-editor';

document.addEventListener('DOMContentLoaded', () => {
    const TOOLTIPS_STORAGE_KEY = 'variance:version-editor-tooltips:v1';
    const {
        xmlContent,
        lazyLoadEnabled,
        urlDocumentLoad,
        urlFileSave,
        urlToggleIgnored,
        versionId,
        urlLignesUpload,
        urlLignesProgress,
        urlFacsimilesUpload,
        urlFacsimilesProgress,
    } = window.editorParams;

    // Initialize Bootstrap tooltips.
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"], [data-bs-toggle="modal"]');
    const bootstrapLib = window.bootstrap;
    if (!bootstrapLib) {
        console.warn('Bootstrap JavaScript library is not available on window.bootstrap. Tooltips and modal helpers are disabled.');
    }

    // DOM Elements
    const elements = {
        saveBtn: document.getElementById('save-xml'),
        fileStatus: document.getElementById('file-status'),
        toggleBtn: document.getElementById('toggle-readonly'),
        toggleTagsBtn: document.getElementById('toggle-tags'),
        toggleLineNumbersBtn: document.getElementById('toggle-line-numbers'),
        toggleTooltipsInput: document.getElementById('toggle-tooltips'),
        uploadLignesBtn: document.getElementById('upload-lignes-btn'),
        uploadLignesInput: document.getElementById('upload-lignes-input'),
        uploadLignesSpinner: document.getElementById('upload-lignes-spinner'),
        uploadFacsimilesBtn: document.getElementById('upload-facsimiles-btn'),
        uploadFacsimilesInput: document.getElementById('upload-facsimiles-input'),
        uploadFacsimilesSpinner: document.getElementById('upload-facsimiles-spinner'),
        selectPrevFacsimileBtn: document.getElementById('select-prev-facsimile'),
        selectNextFacsimileBtn: document.getElementById('select-next-facsimile'),
        generatePageNumbersBtn: document.getElementById('generate-page-numbers'),
        toggleIgnoredPageBtn: document.getElementById('toggle-ignored-page'),
        removePageMarkerBtn: document.getElementById('remove-page-marker'),
        clearAllPageMarkersBtn: document.getElementById('clear-all-page-markers'),
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
        facsimilesEmptyState: document.getElementById('facsimiles-empty-state'),
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
        INSERT: 'btn-insert',
        INSERTED: 'btn-success',
        NOT_NAMED: 'btn-warning',
    };
    const MAX_FAC_BATCH_FILES = 10;
    const MAX_FAC_BATCH_BYTES = 7.5 * 1024 * 1024;

    // State - only one active button at a time, either in insert or delete mode
    let activeButton = null;
    const tooltipsMap = new Map();

    const itemsPerPage = 39;
    let tagsWereHiddenBeforeEdit = true;
    let hasUnsavedChanges = false;
    let isEditMode = false;
    let documentLoaded = typeof xmlContent === 'string' && xmlContent.length > 0;
    let initialXmlContent = documentLoaded ? xmlContent : '';
    let fullXmlContent = documentLoaded ? xmlContent : '';
    let bodyPreviewActive = false;
    let refreshButtonStatesTimeout = null;
    let lignesPoller = null;
    let facsimilesPoller = null;
    let facsimilesUploadInProgress = false;
    let tooltipsEnabled = localStorage.getItem(TOOLTIPS_STORAGE_KEY) === 'true';
    let initialReadonlyViewApplied = false;

    window.areVersionEditorTooltipsEnabled = () => tooltipsEnabled;

    const staticTooltipTargets = Array.from(tooltipTriggerList);

    const captureTooltipTitle = (element) => {
        if (!element) return;
        if (!element.dataset.tooltipTitle && element.getAttribute('title')) {
            element.dataset.tooltipTitle = element.getAttribute('title');
        }
        element.removeAttribute('title');
    };

    const createStandardTooltip = (element) => {
        if (!bootstrapLib?.Tooltip) return null;

        return new bootstrapLib.Tooltip(
            element,
            {
                title: () => element.dataset.tooltipTitle || '',
                delay: { "show": 500, "hide": 100 },
                trigger: 'hover',
                offset: [0, 6],
            }
        );
    };

    const syncStaticTooltip = (element) => {
        if (!element) return;
        if (!bootstrapLib?.Tooltip) return;
        captureTooltipTitle(element);
        const instance = bootstrapLib.Tooltip.getInstance(element);
        const tooltipTitle = element.dataset.tooltipTitle || '';

        if (!tooltipsEnabled || !tooltipTitle) {
            instance?.dispose();
            return;
        }

        if (instance) {
            return;
        }

        createStandardTooltip(element);
    };

    const syncStaticTooltips = () => {
        staticTooltipTargets.forEach(syncStaticTooltip);
    };

    const syncInlineWidgetTooltips = () => {
        if (!bootstrapLib?.Tooltip) return;

        document.querySelectorAll('.cm-italic-tag, .cm-page-number-mark').forEach((element) => {
            const instance = bootstrapLib.Tooltip.getInstance(element);

            if (!tooltipsEnabled) {
                instance?.dispose();
                return;
            }

            if (instance) {
                return;
            }

            if (element.classList.contains('cm-italic-tag')) {
                new bootstrapLib.Tooltip(element, {
                    title: 'Cliquez pour supprimer',
                    trigger: 'hover',
                    offset: [0, 10],
                });
                return;
            }

            const badgeText = element.querySelector('.cm-page-number-mark-badge')?.textContent?.trim() || '';
            new bootstrapLib.Tooltip(element, {
                title: badgeText === '?'
                    ? 'Cliquez pour numéroter la page'
                    : 'Cliquez pour modifier le numéro de page',
                trigger: 'hover',
                offset: [0, 10],
            });
        });
    };

    staticTooltipTargets.forEach(captureTooltipTitle);
    if (elements.toggleTooltipsInput) {
        elements.toggleTooltipsInput.checked = tooltipsEnabled;
    }

    const editor = initEditor(document.getElementById('editor-container'), documentLoaded ? xmlContent : '');

    const setDocumentLoadingState = (isLoading, message = 'Chargement du document…') => {
        elements.editorContainer?.classList.toggle('is-loading', !!isLoading);
        elements.editorContainer?.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        if (elements.editorContainer) {
            elements.editorContainer.dataset.loadingMessage = isLoading ? message : '';
        }
        if (elements.noPreviewText && isLoading) {
            elements.noPreviewText.style.display = 'none';
        }
    };

    const buildInlineFallbackUrl = () => {
        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.set('editor_mode', 'inline');
        return nextUrl.toString();
    };

    const fallbackToInlineMode = () => {
        window.location.assign(buildInlineFallbackUrl());
    };

    // Track changes in the editor
    editor.onContentChanged(() => {
        const currentContent = editor.view.state.doc.toString();
        hasUnsavedChanges = currentContent !== initialXmlContent;

        // Debounce refreshButtonStates to avoid too many calls during rapid changes
        if (refreshButtonStatesTimeout) {
            clearTimeout(refreshButtonStatesTimeout);
        }
        refreshButtonStatesTimeout = setTimeout(() => {
            refreshButtonStates();
        }, 200);

        // Update save button appearance
        if (hasUnsavedChanges) {
            elements.saveBtn.classList.remove('btn-success');
            elements.saveBtn.classList.add('btn-danger');
            
            // Auto save when in read-only mode
            if (!isEditMode) {
                saveFile();
            }
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
            const isIgnored = button.getAttribute('data-ignored') === 'true';

            if (!insertedMarkers.has(imageName) && !isIgnored) {
                allInserted = false;
            }

            const count = markerCounts.get(imageName) || 0;
            if (count > 1) {
                hasDuplicates = true;
            }

            const pageNum = editor.getPageNumber(imageName);
            if ((!pageNum || pageNum === '?') && !isIgnored) {
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
        const hasFacsimiles = allButtons.length > 0;

        if (elements.facsimilesEmptyState) {
            elements.facsimilesEmptyState.style.display = hasFacsimiles ? 'none' : 'block';
        }
        if (elements.generatePageNumbersBtn) {
            elements.generatePageNumbersBtn.disabled = !hasFacsimiles;
            elements.generatePageNumbersBtn.dataset.tooltipTitle = hasFacsimiles
                ? 'Générer les numéros de page'
                : 'Aucun fac-similé importé pour cette version';
            syncStaticTooltip(elements.generatePageNumbersBtn);
        }

        if (!hasFacsimiles) {
            if (elements.pagination?.parentElement) {
                elements.pagination.parentElement.style.display = 'none';
            }
            return;
        }

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

    const getTagButtons = () => Array.from(document.querySelectorAll('.editor [data-tag]'));

    const syncFacsimileNavigationButtons = () => {
        const buttons = getTagButtons();
        const activeIndex = activeButton ? buttons.indexOf(activeButton) : -1;

        if (elements.selectPrevFacsimileBtn) {
            elements.selectPrevFacsimileBtn.disabled = activeIndex <= 0;
        }

        if (elements.selectNextFacsimileBtn) {
            elements.selectNextFacsimileBtn.disabled = activeIndex === -1 || activeIndex >= buttons.length - 1;
        }
    };

    const createFacsimileButtonTooltip = (button) => {
        if (!bootstrapLib?.Tooltip) return null;

        return new bootstrapLib.Tooltip(button, {
            title: () => {
                const imageName = button.getAttribute('data-tag');
                const isInserted = editor.isPageMarkerInserted(imageName);
                const isIgnored = button.getAttribute('data-ignored') === 'true';

                if (isIgnored) {
                    return 'Cette page est ignorée';
                }

                if (isInserted) {
                    return '';
                }

                if (activeButton === button) {
                    return 'Cliquez dans le texte pour insérer ce marqueur de page';
                }

                return 'Cliquez pour sélectionner ce marqueur de page';
            },
            trigger: 'hover',
            delay: { "show": 300, "hide": 0 },
            offset: [0, 10],
        });
    };

    const syncFacsimileButtonTooltips = () => {
        if (!bootstrapLib?.Tooltip) return;

        getTagButtons().forEach((button) => {
            const tooltip = tooltipsMap.get(button);

            if (!tooltipsEnabled) {
                tooltip?.dispose();
                tooltipsMap.delete(button);
                return;
            }

            if (!tooltip) {
                tooltipsMap.set(button, createFacsimileButtonTooltip(button));
            }
        });
    };

    const applyTooltipsState = () => {
        syncStaticTooltips();
        syncFacsimileButtonTooltips();
        syncInlineWidgetTooltips();
    };

    // Utility functions
    const setButtonState = (button, state, active = false) => {
        // Remove all button state classes.
        const btnClasses = new Set(Object.values(BUTTON_STATES));
        btnClasses.add('btn-outlined');
        button.classList.remove(...(Array.from(button.classList).filter(c => btnClasses.has(c))));

        button.classList.add(state);

        if (active) {
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

    const setSpinnerVisible = (spinnerEl, visible) => {
        if (!spinnerEl) return;
        spinnerEl.classList.toggle('d-none', !visible);
    };

    const setLignesBusy = (isBusy) => {
        setSpinnerVisible(elements.uploadLignesSpinner, isBusy);
        if (elements.uploadLignesBtn) {
            elements.uploadLignesBtn.disabled = !!isBusy;
        }
        if (elements.uploadLignesInput) {
            elements.uploadLignesInput.disabled = !!isBusy;
        }
    };

    const stopLignesPolling = () => {
        if (lignesPoller) {
            window.clearInterval(lignesPoller);
            lignesPoller = null;
        }
    };

    const stopFacsimilesPolling = () => {
        if (facsimilesPoller) {
            window.clearInterval(facsimilesPoller);
            facsimilesPoller = null;
        }
    };

    const pollLignesProgress = async () => {
        if (!urlLignesProgress) return;
        try {
            const response = await fetch(`${urlLignesProgress}${urlLignesProgress.includes('?') ? '&' : '?'}ts=${Date.now()}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            const status = payload?.status || 'idle';
            if (status === 'queued') {
                setLignesBusy(true);
                return;
            }
            if (status === 'running') {
                setLignesBusy(true);
                return;
            }
            if (status === 'completed' || status === 'done') {
                stopLignesPolling();
                setLignesBusy(true);
                window.setTimeout(() => window.location.reload(), 350);
                return;
            }
            if (status === 'failed') {
                stopLignesPolling();
                setLignesBusy(false);
                alert(payload?.message || 'Le traitement _lignes a échoué.');
                return;
            }

            stopLignesPolling();
            setLignesBusy(false);
        } catch (err) {
            stopLignesPolling();
            setLignesBusy(false);
            alert('Impossible de suivre le traitement _lignes.');
        }
    };

    const startLignesPolling = () => {
        stopLignesPolling();
        pollLignesProgress();
        lignesPoller = window.setInterval(pollLignesProgress, 1500);
    };

    const updateFacsimilesUploadState = (isBusy) => {
        facsimilesUploadInProgress = !!isBusy;
        setSpinnerVisible(elements.uploadFacsimilesSpinner, facsimilesUploadInProgress);
        if (elements.uploadFacsimilesBtn) {
            elements.uploadFacsimilesBtn.disabled = facsimilesUploadInProgress;
        }
        if (elements.uploadFacsimilesInput) {
            elements.uploadFacsimilesInput.disabled = facsimilesUploadInProgress;
        }
    };

    const isDsStoreFile = (file) => {
        const rawName = file?.name || '';
        const rawPath = file?.webkitRelativePath || '';
        const path = String(rawPath || rawName);
        const parts = path.split('/');
        const base = parts.length ? parts[parts.length - 1] : path;
        return base === '.DS_Store';
    };

    const sortFacsimileFiles = (files) => {
        return [...files].sort((a, b) => {
            const left = (a.webkitRelativePath || a.name || '').toLowerCase();
            const right = (b.webkitRelativePath || b.name || '').toLowerCase();
            return left.localeCompare(right, undefined, { numeric: true, sensitivity: 'base' });
        });
    };

    const pollFacsimilesProgress = async () => {
        if (!urlFacsimilesProgress) return;
        try {
            const response = await fetch(`${urlFacsimilesProgress}${urlFacsimilesProgress.includes('?') ? '&' : '?'}ts=${Date.now()}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            const queueCount = Number(payload?.queue_count ?? 0);
            const sourceCount = Number(payload?.source_count ?? 0);

            if (queueCount > 0) {
                updateFacsimilesUploadState(true);
                return;
            }

            stopFacsimilesPolling();
            updateFacsimilesUploadState(false);

            if (sourceCount > 0) {
                updateFacsimilesUploadState(true);
                window.setTimeout(() => window.location.reload(), 350);
                return;
            }
        } catch (err) {
            stopFacsimilesPolling();
            updateFacsimilesUploadState(false);
            alert('Impossible de suivre le traitement des fac-similés.');
        }
    };

    const startFacsimilesPolling = () => {
        stopFacsimilesPolling();
        pollFacsimilesProgress();
        facsimilesPoller = window.setInterval(pollFacsimilesProgress, 1500);
    };

    const uploadFacsimilesFolder = async (files) => {
        if (!urlFacsimilesUpload || !Number.isFinite(Number(versionId))) return;

        const selected = sortFacsimileFiles(
            [...files].filter((file) => !isDsStoreFile(file))
        );

        if (!selected.length) {
            alert('Aucun fichier image exploitable dans ce dossier.');
            return;
        }

        updateFacsimilesUploadState(true);

        let cursor = 0;
        let batchIndex = 0;

        try {
            while (cursor < selected.length) {
                let batchSize = 0;
                let byteTotal = 0;
                const chunk = [];

                while (cursor < selected.length && batchSize < MAX_FAC_BATCH_FILES) {
                    const file = selected[cursor];
                    const tentative = byteTotal + (file.size || 0);
                    if (batchSize > 0 && tentative > MAX_FAC_BATCH_BYTES) break;

                    chunk.push(file);
                    byteTotal = tentative;
                    batchSize += 1;
                    cursor += 1;

                    if (byteTotal >= MAX_FAC_BATCH_BYTES) break;
                }

                if (!chunk.length) {
                    chunk.push(selected[cursor]);
                    cursor += 1;
                }

                const start = cursor - chunk.length + 1;
                const end = cursor;

                const form = new FormData();
                form.append('version_id', String(versionId));
                form.append('reset', batchIndex === 0 ? '1' : '0');
                chunk.forEach((file) => form.append('images[]', file));

                const response = await fetch(urlFacsimilesUpload, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: form,
                });

                let payload = {};
                try {
                    payload = await response.json();
                } catch (err) {
                    payload = {};
                }

                if (!response.ok) {
                    throw new Error(payload?.message || payload?.error || `HTTP ${response.status}`);
                }

                if (elements.uploadFacsimilesBtn) {
                    elements.uploadFacsimilesBtn.title = `Envoi des fac-similés ${start}-${end} / ${selected.length}`;
                }

                batchIndex += 1;
            }

            startFacsimilesPolling();
        } catch (err) {
            updateFacsimilesUploadState(false);
            alert(err.message || 'Échec de l’import des fac-similés.');
        }
    };

    const uploadLignesFile = async (file) => {
        if (!file || !urlLignesUpload) return;
        if (hasUnsavedChanges) {
            alert('Veuillez d’abord sauvegarder les modifications XML avant d’importer un fichier _lignes.');
            return;
        }

        setLignesBusy(true);

        try {
            const form = new FormData();
            form.append('lignes', file);

            const response = await fetch(urlLignesUpload, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: form,
            });

            let payload = {};
            try {
                payload = await response.json();
            } catch (err) {
                payload = {};
            }

            if (!response.ok) {
                throw new Error(payload?.message || `HTTP ${response.status}`);
            }

            startLignesPolling();
        } catch (err) {
            setLignesBusy(false);
            alert(err.message || 'Échec de l’import du fichier _lignes.');
        }
    };

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
            const isIgnored = button.getAttribute('data-ignored') === 'true';
            let iconClass = isIgnored ? 'bi-eye-slash' : 'bi-file-earmark';
            
            const span = button.querySelector('span');
            const existingIcon = span.querySelector(`i.${iconClass}`);
            span.textContent = insertedPageNumber ? '?' : '';

            if (!existingIcon) {
              const i = document.createElement('i');
              i.className = `bi ${iconClass} mr-1`;
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

            elements.toggleIgnoredPageBtn.setAttribute('disabled', 'true');
            elements.removePageMarkerBtn.setAttribute('disabled', 'true');
            syncFacsimileNavigationButtons();
        }
    };

    const loadImage = (imgSrc) => {
        if (!imgSrc) return;

        if (elements.noPreviewText) elements.noPreviewText.style.display = 'none';

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

    elements.uploadLignesBtn?.addEventListener('click', () => {
        if (hasUnsavedChanges) {
            alert('Veuillez d’abord sauvegarder les modifications XML avant d’importer un fichier _lignes.');
            return;
        }
        if (elements.uploadLignesInput) {
            elements.uploadLignesInput.value = '';
            elements.uploadLignesInput.click();
        }
    });

    elements.uploadLignesInput?.addEventListener('change', () => {
        const file = elements.uploadLignesInput?.files?.[0];
        if (!file) return;
        uploadLignesFile(file);
    });

    elements.uploadFacsimilesBtn?.addEventListener('click', () => {
        if (elements.uploadFacsimilesInput) {
            elements.uploadFacsimilesInput.value = '';
            elements.uploadFacsimilesInput.click();
        }
    });

    elements.uploadFacsimilesInput?.addEventListener('change', () => {
        const files = Array.from(elements.uploadFacsimilesInput?.files || []);
        if (!files.length) return;
        uploadFacsimilesFolder(files);
    });

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

        const { insertedMarkers } = editor.getAllMarkers();
        const totalInsertedMarkers = insertedMarkers.size;

        let hasTagNumberNotInserted = false;

        document.querySelectorAll('.editor [data-tag]').forEach(button => {
            const imageName = button.getAttribute('data-tag');
            const isInserted = insertedMarkers.has(imageName);
            const isIgnored = button.getAttribute('data-ignored') === 'true';
            const pageNumber = editor.getPageNumber(imageName);
            const buttonPageNumber = button.getAttribute('data-tag-page-number');

            if (buttonPageNumber && buttonPageNumber !== '?' && !isInserted) {
                hasTagNumberNotInserted = true;
            }

            if (activeButton === button) {

                if (isInserted) {
                    setButtonState(button, BUTTON_STATES.INSERTED, true);
                    elements.removePageMarkerBtn.removeAttribute('disabled');
                } else {
                    setButtonState(button, BUTTON_STATES.INSERT, true);
                    elements.removePageMarkerBtn.setAttribute('disabled', 'true');
                }

                if (isIgnored) {
                    elements.toggleIgnoredPageBtn.classList.add('active');
                } else {
                    elements.toggleIgnoredPageBtn.classList.remove('active');
                }
            } else {
                if (isInserted) {
                    const isNamed = pageNumber && pageNumber !== '?';
                    setButtonState(button, isNamed ? BUTTON_STATES.INSERTED : BUTTON_STATES.NOT_NAMED);
                } else {
                    setButtonState(button, BUTTON_STATES.INACTIVE);
                }
            }

            refreshButtonName(button);

            if (isInserted) {
                button.setAttribute('data-inserted', 'true');
            } else {
                button.removeAttribute('data-inserted');
            }
        });

        // Show/hide warning icon for unsaved page numbers
        const warningIcon = document.getElementById('page-number-warning');
        if (warningIcon) {
            warningIcon.style.display = hasTagNumberNotInserted ? 'inline' : 'none';
        }

        if (elements.clearAllPageMarkersBtn) {
            if (totalInsertedMarkers > 0) {
                elements.clearAllPageMarkersBtn.removeAttribute('disabled');
            } else {
                elements.clearAllPageMarkersBtn.setAttribute('disabled', 'true');
            }
        }

        updateTagCountBadges();
        updatePaginationColors();
        syncFacsimileNavigationButtons();
        editor.resumeEnsureCacheUpdate();
    };

    const updateTagsButtonUI = (tagsHidden) => {
        elements.toggleTagsBtn.classList.toggle('active', !tagsHidden);
    };

    const extractBodyInnerXml = (xml) => {
        if (!xml) return '';
        const match = xml.match(/<body\b[^>]*>([\s\S]*?)<\/body>/i);
        return match ? match[1] : xml;
    };

    const mergeBodyIntoFullXml = (xml, bodyInnerXml) => {
        if (!xml) return bodyInnerXml;
        if (!/<body\b[^>]*>[\s\S]*<\/body>/i.test(xml)) {
            return bodyInnerXml;
        }

        return xml.replace(
            /(<body\b[^>]*>)([\s\S]*?)(<\/body>)/i,
            `$1${bodyInnerXml}$3`
        );
    };

    const syncHiddenBodyIntoFullXml = () => {
        if (!bodyPreviewActive) {
            return fullXmlContent;
        }

        const currentBody = editor.view.state.doc.toString();
        fullXmlContent = mergeBodyIntoFullXml(fullXmlContent, currentBody);
        return fullXmlContent;
    };

    const formatXmlForReadonlyDisplay = (xml) => {
        if (!xml) return '';

        const normalized = xml
            .replace(/\r\n?/g, '\n')
            .replace(/>\s*</g, '>\n<');

        const lines = normalized.split('\n');
        let indent = 0;

        return lines
            .map((line) => {
                const trimmed = line.trim();
                if (trimmed === '') {
                    return '';
                }

                if (/^<\//.test(trimmed)) {
                    indent = Math.max(0, indent - 1);
                }

                const rendered = `${'  '.repeat(indent)}${trimmed}`;

                if (
                    /^<[^!?/][^>]*>$/.test(trimmed) &&
                    !/\/>$/.test(trimmed) &&
                    !/<[^>]+>.*<\/[^>]+>$/.test(trimmed)
                ) {
                    indent += 1;
                }

                return rendered;
            })
            .join('\n');
    };

    const applyTagsHiddenView = () => {
        fullXmlContent = editor.view.state.doc.toString();
        editor.replaceDocument(extractBodyInnerXml(fullXmlContent), { silent: true });
        if (!editor.getTagVisibility()) {
            editor.toggleTagVisibility();
        }
        elements.editorContainer?.classList.add('body-preview-mode');
        bodyPreviewActive = true;
        updateTagsButtonUI(true);
    };

    const restoreFullXmlView = () => {
        if (!bodyPreviewActive) {
            if (editor.getTagVisibility()) {
                editor.toggleTagVisibility();
            }
            updateTagsButtonUI(false);
            return;
        }
        syncHiddenBodyIntoFullXml();
        editor.replaceDocument(fullXmlContent, { silent: true });
        if (editor.getTagVisibility()) {
            editor.toggleTagVisibility();
        }
        elements.editorContainer?.classList.remove('body-preview-mode');
        bodyPreviewActive = false;
        updateTagsButtonUI(false);
    };

    const applyInitialReadonlyView = () => {
        if (initialReadonlyViewApplied || !documentLoaded) {
            return;
        }
        initialReadonlyViewApplied = true;
        applyTagsHiddenView();
        setDocumentLoadingState(false);
    };

    const hydrateEditorDocument = (content) => {
        initialXmlContent = content;
        fullXmlContent = content;
        documentLoaded = true;
        editor.replaceDocument(content, { silent: true });
        refreshButtonStates();
        const initialFacsimileButton = findInitialFacsimileButton();
        if (initialFacsimileButton) {
            activateFacsimileButton(initialFacsimileButton);
        }
        requestAnimationFrame(() => {
            applyInitialReadonlyView();
        });
    };

    const loadEditorDocument = async () => {
        if (!lazyLoadEnabled || documentLoaded || !urlDocumentLoad) {
            return;
        }

        setDocumentLoadingState(true);

        try {
            const response = await fetch(`${urlDocumentLoad}${urlDocumentLoad.includes('?') ? '&' : '?'}ts=${Date.now()}`, {
                headers: {
                    Accept: 'application/xml,text/plain;q=0.9,*/*;q=0.8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const content = await response.text();
            if (!content) {
                throw new Error('Empty editor document');
            }

            hydrateEditorDocument(content);
        } catch (error) {
            console.error('Impossible de charger le document de l’éditeur en mode différé.', error);
            fallbackToInlineMode();
        }
    };

    const updateSaveButtonUI = (isEditMode) => {
        // Show manual save button only in edit mode
        if (isEditMode) {
            elements.saveBtn.classList.remove('d-none');
            elements.fileStatus.classList.add('d-none');
        } else {
            elements.saveBtn.classList.add('d-none');
            elements.fileStatus.classList.remove('d-none');
        }
    };

    // Event handlers
    elements.toggleBtn.addEventListener('click', () => {
        const isReadOnly = editor.toggleReadOnly();
        isEditMode = !isReadOnly;
        elements.toggleBtn.classList.toggle('active', !isReadOnly);
        elements.toggleTagsBtn.disabled = !isReadOnly;
        updateSaveButtonUI(!isReadOnly);

        if (isReadOnly) {
            // Auto save when switching to read-only mode
            if (hasUnsavedChanges) {
                saveFile();
            }
            refreshButtonStates();
            if (tagsWereHiddenBeforeEdit) {
                applyTagsHiddenView();
            }
        } else {
            tagsWereHiddenBeforeEdit = bodyPreviewActive || editor.getTagVisibility();
            deactivateActiveButton();

            if (tagsWereHiddenBeforeEdit) {
                restoreFullXmlView();
            }
        }
    });

    elements.toggleTagsBtn.addEventListener('click', () => {
        if (bodyPreviewActive) {
            restoreFullXmlView();
        } else {
            applyTagsHiddenView();
        }
    });

    elements.toggleLineNumbersBtn.addEventListener('click', () => {
        const lineNumbersShown = editor.toggleLineNumbers();
        localStorage.setItem('editor-line-numbers', lineNumbersShown);
        elements.toggleLineNumbersBtn.classList.toggle('active', lineNumbersShown);
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
                    const modal = bootstrapLib?.Modal?.getInstance(elements.italicErrorsModal);
                    modal?.hide();

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
        let ignoredCount = 0;

        document.querySelectorAll('.editor [data-tag]').forEach(button => {
            const isIgnored = button.getAttribute('data-ignored') === 'true';
            
            // Count position in the list (including ignored pages for startPageNum check)
            currentPageNum++;
            
            if (isIgnored) {
                ignoredCount++;
                return;
            }

            const imageName = button.getAttribute('data-tag');
            const isInserted = insertedMarkers.has(imageName);
            const effectivePosition = currentPageNum - ignoredCount;

            if (startPageNum >= effectivePosition) {
                if (!isInserted) {
                    button.setAttribute('data-tag-page-number', '?');
                }
            } else {
                if (!isInserted) {
                    const pageNumber = String(effectivePosition - startPageNum).padStart(leadingZerosNum, '0');
                    button.setAttribute('data-tag-page-number', pageNumber);
                }
            }
        });

        // Refresh button states to update display
        refreshButtonStates();

        // Close modal
        const modal = bootstrapLib?.Modal?.getInstance(document.getElementById('generatePageNumbersModal'));
        modal?.hide();
    });

    // Toggle ignored page button
    if (elements.toggleIgnoredPageBtn && urlToggleIgnored) {
        elements.toggleIgnoredPageBtn.addEventListener('click', async () => {
            if (!activeButton) return;

            const button = activeButton;
            const filename = button.getAttribute('data-tag');
            const buttonItem = button.closest('.button-item');

            try {
                const response = await fetch(urlToggleIgnored, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ filename })
                });

                const data = await response.json();

                if (response.ok && data.status === 'ok') {
                    const isNowIgnored = data.ignored;
                    button.setAttribute('data-ignored', isNowIgnored ? 'true' : 'false');
                    
                    if (buttonItem) {
                        buttonItem.classList.toggle('page-ignored', isNowIgnored);
                    }

                    if (isNowIgnored) {
                        button.removeAttribute('data-tag-page-number');
                        if (editor.isPageMarkerInserted(filename)) {
                            editor.removePageMarker(filename);
                        }
                        refreshButtonStates();
                        deactivateActiveButton();
                    } else {
                        elements.toggleIgnoredPageBtn.classList.remove('active');
                        refreshButtonName(button);
                        updatePaginationColors();
                    }
                } else {
                    alert(data.error || 'Une erreur est survenue.');
                }
            } catch (error) {
                alert('Une erreur est survenue lors de la mise à jour.');
            }
        });
    }

    // Remove page marker button
    if (elements.removePageMarkerBtn) {
        elements.removePageMarkerBtn.addEventListener('click', () => {
            if (!activeButton) return;

            const button = activeButton;
            const filename = button.getAttribute('data-tag');

            if (editor.isPageMarkerInserted(filename)) {
                editor.removePageMarker(filename);
                refreshButtonStates();
            }
        });
    }

    if (elements.clearAllPageMarkersBtn) {
        elements.clearAllPageMarkersBtn.addEventListener('click', () => {
            const { insertedMarkers } = editor.getAllMarkers();
            if (!insertedMarkers.size) return;

            const confirmed = window.confirm('Supprimer tous les marqueurs de pagination <pb/> du texte ?');
            if (!confirmed) return;

            editor.removeAllPageMarkers();
            refreshButtonStates();
            deactivateActiveButton();
        });
    }

    const activateFacsimileButton = (button) => {
        if (!button) return;

        const imgSrc = button.getAttribute('data-img-src');
        const imageName = button.getAttribute('data-tag');
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

            const buttonItem = button.closest('.button-item');
            const page = parseInt(buttonItem?.getAttribute('data-page') || '1', 10);
            showPage(page);

            activeButton = button;
            loadImage(imgSrc);
            elements.toggleIgnoredPageBtn.removeAttribute('disabled');

            const isIgnored = button.getAttribute('data-ignored') === 'true';
            if (isIgnored) {
                elements.toggleIgnoredPageBtn.classList.add('active');
            } else {
                elements.toggleIgnoredPageBtn.classList.remove('active');
            }

            if (isInserted) {
                elements.removePageMarkerBtn.removeAttribute('disabled');
                editor.scrollToPageMarker(imageName);
                setButtonState(button, BUTTON_STATES.INSERTED, true);
            } else {
                elements.removePageMarkerBtn.setAttribute('disabled', 'true');
                setButtonState(button, BUTTON_STATES.INSERT, true);
                refreshButtonName(button);
                elements.editorContainer.classList.add('insert-page');
            }
        }

        syncFacsimileNavigationButtons();
        tooltipsMap.get(button)?.show();
    };

    const navigateFacsimile = (delta) => {
        const buttons = getTagButtons();
        if (!buttons.length) return;

        const currentIndex = activeButton ? buttons.indexOf(activeButton) : -1;
        const targetIndex = currentIndex === -1
            ? (delta > 0 ? 0 : buttons.length - 1)
            : currentIndex + delta;

        if (targetIndex < 0 || targetIndex >= buttons.length) return;

        activateFacsimileButton(buttons[targetIndex]);
    };

    const findInitialFacsimileButton = () => {
        const buttons = getTagButtons();
        if (!buttons.length) return null;

        const firstInserted = buttons.find((button) => {
            const imageName = button.getAttribute('data-tag');
            return imageName && editor.isPageMarkerInserted(imageName);
        });

        return firstInserted || buttons[0] || null;
    };

    // Insert buttons
    document.querySelectorAll('.editor [data-tag]').forEach(button => {
        const imgSrc = button.getAttribute('data-img-src');
        const imageName = button.getAttribute('data-tag');

        if (tooltipsEnabled) {
            tooltipsMap.set(button, createFacsimileButtonTooltip(button));
        }

        button.addEventListener('click', () => {
            activateFacsimileButton(button);
        });
    });

    elements.selectPrevFacsimileBtn?.addEventListener('click', () => {
        navigateFacsimile(-1);
    });

    elements.selectNextFacsimileBtn?.addEventListener('click', () => {
        navigateFacsimile(1);
    });

    // Editor click to insert tag
    elements.editorContainer.addEventListener('click', (e) => {
        // Ignore clicks on the search panel
        if (e.target.closest('.cm-search')) {
            return;
        }

        // Only insert page marker if we have an active button in insert mode
        if (activeButton) {
            const imageName = activeButton.getAttribute('data-tag');
            const pageNumber = activeButton.getAttribute('data-tag-page-number') || '?';
            const isIgnored = activeButton.getAttribute('data-ignored') === 'true';

            if (isIgnored) {
                alert('Cette page est ignorée. Désactivez l\'option "Ignorer" pour pouvoir l\'insérer.');
                return;
            }

            if (!editor.isPageMarkerInserted(imageName)) {
              const success = editor.insertPageMarker(imageName, pageNumber);
              if (success) {
                  refreshButtonStates();
              }
            }
        }
    });

    // Close insert mode when clicking anywhere on the page (except on buttons or editor)
    document.addEventListener('click', (e) => {
        if (activeButton) {
            const isClickOnEditor = elements.editorContainer.contains(e.target);
            const isClickOnButton = e.target.closest('[data-tag]');
            const isClickOnImageUrl = e.target.id === 'image-name';
            const isClickOnToggleIgnored = e.target.closest('#toggle-ignored-page');
            const isClickOnSearchBtn = e.target.closest('#search-btn');
            const isClickOnSearchPanel = e.target.closest('.cm-search');
            const isClickOnToggleTagsBtn = e.target.closest('#toggle-tags');
            const isClickOnRemovePageMarkerBtn = e.target.closest('#remove-page-marker');
            const isClickOnClearAllPageMarkersBtn = e.target.closest('#clear-all-page-markers');
            const isClickOnToggleReadonlyBtn = e.target.closest('#toggle-readonly');
            const isClickOnPrevFacsimileBtn = e.target.closest('#select-prev-facsimile');
            const isClickOnNextFacsimileBtn = e.target.closest('#select-next-facsimile');

            if (!isClickOnEditor && !isClickOnButton && !isClickOnImageUrl &&
                !isClickOnToggleIgnored && !isClickOnSearchBtn && !isClickOnSearchPanel && !isClickOnToggleTagsBtn && 
                !isClickOnRemovePageMarkerBtn && !isClickOnClearAllPageMarkersBtn && !isClickOnToggleReadonlyBtn &&
                !isClickOnPrevFacsimileBtn && !isClickOnNextFacsimileBtn
            ) {
                deactivateActiveButton();
            }
        }
    });

    const saveFile = async () => {
        const updatedXml = bodyPreviewActive
            ? syncHiddenBodyIntoFullXml()
            : editor.view.state.doc.toString();
        
        // Trigger saving animation
        const animTarget = elements.fileStatus.classList.contains('d-none') ? elements.saveBtn : elements.fileStatus;
        animTarget.classList.add('saving');
        animTarget.addEventListener('animationend', () => {
            animTarget.classList.remove('saving');
        }, { once: true });
        
        // Update save status icon and tooltip
        const updateFileStatus = (success, errorMessage = null) => {
            const icon = elements.fileStatus.querySelector('i');
            let tooltipMessage = '';
            if (success) {
                tooltipMessage = 'Le fichier a été sauvegardé et est à jour';
                elements.fileStatus.classList.remove('btn-danger');
                elements.fileStatus.classList.add('btn-success');
                icon.className = 'bi bi-check-circle-fill';
            } else {
                tooltipMessage = errorMessage || 'Erreur lors de la sauvegarde';
                elements.fileStatus.classList.remove('btn-success');
                elements.fileStatus.classList.add('btn-danger');
                icon.className = 'bi bi-x-circle-fill';
                alert(tooltipMessage);
            }
            elements.fileStatus.dataset.tooltipTitle = tooltipMessage;
            syncStaticTooltip(elements.fileStatus);
        };
        
        try {
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
                updateFileStatus(false, data.error || 'Modification non autorisée : cette comparaison est publiée.');
                return false;
            } else if (response.ok) {
                hasUnsavedChanges = false;
                initialXmlContent = updatedXml;
                fullXmlContent = updatedXml;

                // Reset button to success state
                elements.saveBtn.classList.remove('btn-danger');
                elements.saveBtn.classList.add('btn-success');
                updateFileStatus(true);
                return true;
            } else {
                updateFileStatus(false, data.error || 'Une erreur est survenue lors de la sauvegarde.');
                return false;
            }
        } catch (error) {
            console.error('Error saving file:', error);
            updateFileStatus(false, 'Une erreur est survenue lors de la sauvegarde.');
            return false;
        }
    };

    elements.toggleTooltipsInput?.addEventListener('change', (event) => {
        tooltipsEnabled = !!event.target.checked;
        localStorage.setItem(TOOLTIPS_STORAGE_KEY, tooltipsEnabled ? 'true' : 'false');
        applyTooltipsState();
    });

    applyTooltipsState();

    elements.saveBtn.addEventListener('click', async () => {
        await saveFile();
    });

    initPagination();
    refreshButtonStates();
    if (documentLoaded) {
        const initialFacsimileButton = findInitialFacsimileButton();
        if (initialFacsimileButton) {
            activateFacsimileButton(initialFacsimileButton);
        }
    } else if (lazyLoadEnabled) {
        setDocumentLoadingState(true);
    }

    // Initialize line numbers button state from localStorage
    const initialLineNumbersState = localStorage.getItem('editor-line-numbers') === 'true';
    elements.toggleLineNumbersBtn.classList.toggle('active', initialLineNumbersState);
    syncFacsimileNavigationButtons();

    // Use CodeMirror's ready event to hide tags after initial render
    // This fixes Firefox's slow rendering when tags are hidden from the start
    editor.onEditorReady(() => {
        applyInitialReadonlyView();
    });

    loadEditorDocument();
});
