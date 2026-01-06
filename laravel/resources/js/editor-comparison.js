import initEditor from './codemirror-editor-comparison';

document.addEventListener('DOMContentLoaded', () => {
    const { source, target } = window.editorParams;

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

    // Track unsaved changes across both editors
    const unsavedChangesTracker = {};
    
    const hasAnyUnsavedChanges = () => {
      return Object.values(unsavedChangesTracker).some(hasChanges => hasChanges);
    };

    // Warn user before leaving page if there are unsaved changes
    window.addEventListener('beforeunload', (event) => {
      if (hasAnyUnsavedChanges()) {
        event.preventDefault();
      }
    });

    const initializeEditor = (id, xmlContent, urlFileSave) => {
      const editorElement = document.getElementById(`editor-container-${id}`);
      const searchBtnElement = document.getElementById(`editor-search-${id}`);
      const saveBtnElement = document.getElementById(`editor-save-${id}`);
      
      const editor = initEditor(editorElement, xmlContent);

      let initialXmlContent = xmlContent;
      unsavedChangesTracker[id] = false;

      // Track changes in the editor
      editor.onContentChanged(() => {
          const currentContent = editor.view.state.doc.toString();
          unsavedChangesTracker[id] = currentContent !== initialXmlContent;

          // Update save button appearance
          if (unsavedChangesTracker[id]) {
              saveBtnElement.classList.remove('btn-success');
              saveBtnElement.classList.add('btn-danger');
          } else {
              saveBtnElement.classList.remove('btn-danger');
              saveBtnElement.classList.add('btn-success');
          }
      });

      editor.onSearchPanelStateChanged((isOpen) => {
          searchBtnElement.classList.toggle('active', isOpen);
      });

      searchBtnElement.addEventListener('click', () => {
          editor.toggleSearch();
      });

      if (saveBtnElement) {
        saveBtnElement.addEventListener('click', async () => {
            const updatedXml = editor.view.state.doc.toString();
            
            // Trigger saving animation
            saveBtnElement.classList.add('saving');
            saveBtnElement.addEventListener('animationend', () => {
                saveBtnElement.classList.remove('saving');
            }, { once: true });
            
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

                if (response.ok) {
                  initialXmlContent = updatedXml;
                  unsavedChangesTracker[id] = false;

                  // Reset button to success state
                  saveBtnElement.classList.remove('btn-danger');
                  saveBtnElement.classList.add('btn-success');
                } else {
                  alert('Un problème est survenu lors de la sauvegarde du fichier.');
                }
            } catch (error) {
                console.error('Error saving file:', error);
            }
        });
      };
    };

    initializeEditor(source.id, source.xmlContent, source.urlFileSave);
    initializeEditor(target.id, target.xmlContent, target.urlFileSave);
});
