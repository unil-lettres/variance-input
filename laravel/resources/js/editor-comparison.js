import initEditor from './codemirror-editor-comparison';

document.addEventListener('DOMContentLoaded', () => {
    const components = window.editorParams?.components ?? [];
    const consistencyUrl = window.editorParams?.consistencyUrl;
    const returnUrl = window.editorParams?.returnUrl ?? null;
    if (!components.length) {
        return;
    }

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

    const editors = {};
    const componentMap = {};
    const unsavedChangesTracker = {};
    const initialContentTracker = {};
    const removeTransformationModalEl = document.getElementById('removeTransformationModal');
    const confirmRemoveTransformationBtn = document.getElementById('confirm-remove-transformation-btn');
    const removeTransformationRefIdEl = document.getElementById('remove-transformation-refid');
    const removeTransformationFileEl = document.getElementById('remove-transformation-file');
    const removeTransformationLineEl = document.getElementById('remove-transformation-line');
    const removeTransformationModal = removeTransformationModalEl
      ? new bootstrapLib.Modal(removeTransformationModalEl)
      : null;
    let pendingRemoval = null;
    
    const hasAnyUnsavedChanges = () => {
      return Object.values(unsavedChangesTracker).some(hasChanges => hasChanges);
    };

    // Warn user before leaving page if there are unsaved changes
    window.addEventListener('beforeunload', (event) => {
      if (hasAnyUnsavedChanges()) {
        event.preventDefault();
      }
    });

    const exitButton = document.getElementById('editor-exit-button');
    if (exitButton && returnUrl) {
      exitButton.addEventListener('click', () => {
        if (hasAnyUnsavedChanges()) {
          const confirmed = window.confirm(
            'Des modifications non sauvegardées seront perdues. Quitter l’éditeur ?'
          );
          if (!confirmed) {
            return;
          }
        }
        window.location.href = returnUrl;
      });
    }

    const setStatus = (type, label, statusClass = 'text-bg-secondary') => {
      const statusElement = document.getElementById(`editor-status-${type}`);
      if (!statusElement) {
        return;
      }
      statusElement.className = `badge ${statusClass}`;
      statusElement.textContent = label;
    };

    const setConsistencyBadge = (status, labelOverride = null) => {
      const badge = document.getElementById('consistency-status-badge');
      if (!badge) {
        return;
      }

      const map = {
        ok: { cls: 'text-bg-success', text: 'Cohérence: OK' },
        warning: { cls: 'text-bg-warning', text: 'Cohérence: avertissements' },
        error: { cls: 'text-bg-danger', text: 'Cohérence: erreurs' },
        loading: { cls: 'text-bg-secondary', text: 'Cohérence: vérification…' },
      };
      const meta = map[status] ?? map.loading;
      badge.className = `badge ${meta.cls}`;
      badge.textContent = labelOverride ?? meta.text;
    };

    const renderConsistency = (payload) => {
      const panel = document.getElementById('consistency-panel');
      const issuesList = document.getElementById('consistency-issues-list');
      const checkedAt = document.getElementById('consistency-checked-at');
      if (!panel || !issuesList || !checkedAt) {
        return;
      }

      const status = payload?.status ?? 'error';
      const issues = Array.isArray(payload?.issues) ? payload.issues : [];
      setConsistencyBadge(status);
      checkedAt.textContent = payload?.checked_at ? `Dernier contrôle: ${new Date(payload.checked_at).toLocaleString()}` : '';

      issuesList.innerHTML = '';
      if (!issues.length) {
        panel.classList.add('d-none');
        return;
      }

      panel.classList.remove('d-none');
      issues.forEach((issue) => {
        const li = document.createElement('li');
        const file = issue.file ? `${issue.file}: ` : '';
        li.textContent = `${file}${issue.message}`;

        const details = issue?.details;
        if (details && typeof details === 'object') {
          const detailParts = [];

          if (Array.isArray(details.missing_refs_sample) && details.missing_refs_sample.length) {
            detailParts.push(`Références manquantes (extraits): ${details.missing_refs_sample.join(', ')}`);
          }
          if (Array.isArray(details.orphan_ids_sample) && details.orphan_ids_sample.length) {
            detailParts.push(`IDs orphelins (extraits): ${details.orphan_ids_sample.join(', ')}`);
          }

          if (Array.isArray(details.direct_xml) && details.direct_xml.length) {
            detailParts.push(`Analyse XML directe: ${details.direct_xml.join(' | ')}`);
          }

          if (Array.isArray(details.wrapped_fragment) && details.wrapped_fragment.length) {
            detailParts.push(`Analyse fragment enveloppé: ${details.wrapped_fragment.join(' | ')}`);
          }

          if (detailParts.length) {
            const small = document.createElement('div');
            small.className = 'small text-muted mt-1';
            small.textContent = detailParts.join(' — ');
            li.appendChild(small);
          }
        }
        issuesList.appendChild(li);
      });
    };

    const refreshConsistency = async () => {
      if (!consistencyUrl) {
        return;
      }
      setConsistencyBadge('loading');
      try {
        const response = await fetch(consistencyUrl, {
          method: 'GET',
          headers: {
            'Accept': 'application/json'
          }
        });
        if (!response.ok) {
          throw new Error(`Statut HTTP ${response.status}`);
        }
        const payload = await response.json();
        renderConsistency(payload);
      } catch (error) {
        console.error('Consistency check failed:', error);
        setConsistencyBadge('error', 'Cohérence: erreur de contrôle');
      }
    };

    const removeTransformation = async ({ type, component, refId, removeBtnElement }) => {
      removeBtnElement.disabled = true;
      try {
        const response = await fetch(component.urlRemoveTransformation, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            type: type,
            ref_id: refId
          })
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
          throw new Error(payload?.error ?? `Statut HTTP ${response.status}`);
        }

        const summary = payload?.summary ?? {};
        alert(
          `Transformation supprimée (${refId}).\n` +
          `Liste: ${summary.list_removed ?? 0}, ` +
          `source: ${summary.source_removed ?? 0}, ` +
          `target: ${summary.target_removed ?? 0}.`
        );
        window.location.reload();
      } catch (error) {
        console.error('Error removing transformation:', error);
        alert(`Suppression impossible: ${error.message}`);
      } finally {
        removeBtnElement.disabled = false;
      }
    };

    const saveComponent = async (type, animateButton = false) => {
      const component = componentMap[type];
      const editor = editors[type];
      if (!component || !editor || !component.canEdit) {
        return false;
      }

      const saveBtnElement = document.getElementById(`editor-save-${type}`);
      const updatedXml = editor.view.state.doc.toString();

      if (animateButton && saveBtnElement) {
        saveBtnElement.classList.add('saving');
        saveBtnElement.addEventListener('animationend', () => {
          saveBtnElement.classList.remove('saving');
        }, { once: true });
      }

      setStatus(type, 'Sauvegarde...', 'text-bg-info');

      try {
        const response = await fetch(component.urlFileSave, {
          method: 'PUT',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/xml',
            'Accept': 'application/json'
          },
          body: updatedXml
        });

        if (!response.ok) {
          const payload = await response.json().catch(() => ({}));
          const errorMessage = payload?.error ?? `Statut HTTP ${response.status}`;
          throw new Error(errorMessage);
        }

        initialContentTracker[type] = updatedXml;
        unsavedChangesTracker[type] = false;
        if (saveBtnElement) {
          saveBtnElement.classList.remove('btn-danger');
          saveBtnElement.classList.add('btn-success');
        }
        setStatus(type, 'Sauvegardé', 'text-bg-success');
        await refreshConsistency();
        return true;
      } catch (error) {
        console.error(`Error saving component ${type}:`, error);
        setStatus(type, 'Erreur', 'text-bg-danger');
        alert(`Sauvegarde impossible pour ${component.filename}: ${error.message}`);
        return false;
      }
    };

    const initializeEditor = (component) => {
      const { type, xmlContent, canEdit } = component;
      const editorElement = document.getElementById(`editor-container-${type}`);
      const searchBtnElement = document.getElementById(`editor-search-${type}`);
      const saveBtnElement = document.getElementById(`editor-save-${type}`);
      const removeBtnElement = document.getElementById(`editor-remove-transfo-${type}`);
      if (!editorElement || !searchBtnElement) {
        return;
      }

      const editor = initEditor(editorElement, xmlContent ?? '');
      editors[type] = editor;
      componentMap[type] = component;
      initialContentTracker[type] = xmlContent ?? '';
      unsavedChangesTracker[type] = false;

      editor.onContentChanged(() => {
        const currentContent = editor.view.state.doc.toString();
        unsavedChangesTracker[type] = currentContent !== initialContentTracker[type];

        if (saveBtnElement) {
          if (unsavedChangesTracker[type]) {
            saveBtnElement.classList.remove('btn-success');
            saveBtnElement.classList.add('btn-danger');
          } else {
            saveBtnElement.classList.remove('btn-danger');
            saveBtnElement.classList.add('btn-success');
          }
        }

        setStatus(type, unsavedChangesTracker[type] ? 'Modifié' : 'Prêt', unsavedChangesTracker[type] ? 'text-bg-warning' : 'text-bg-secondary');
      });

      editor.onSearchPanelStateChanged((isOpen) => {
        searchBtnElement.classList.toggle('active', isOpen);
      });

      searchBtnElement.addEventListener('click', () => {
        editor.toggleSearch();
      });

      if (saveBtnElement && canEdit) {
        saveBtnElement.addEventListener('click', async () => {
          await saveComponent(type, true);
        });
      }

      if (removeBtnElement && canEdit) {
        removeBtnElement.addEventListener('click', async () => {
          const currentPos = editor.view.state.selection.main.head;
          const currentLine = editor.view.state.doc.lineAt(currentPos).text;
          const match = currentLine.match(/href=(["'])#([^"']+)\1/);
          if (!match) {
            alert('Aucune référence href="#..." détectée sur la ligne courante.');
            return;
          }

          const refId = match[2];
          if (!removeTransformationModal || !confirmRemoveTransformationBtn) {
            await removeTransformation({ type, component, refId, removeBtnElement });
            return;
          }

          pendingRemoval = { type, component, refId, removeBtnElement, currentLine };
          if (removeTransformationRefIdEl) {
            removeTransformationRefIdEl.textContent = refId;
          }
          if (removeTransformationFileEl) {
            removeTransformationFileEl.textContent = component.filename;
          }
          if (removeTransformationLineEl) {
            removeTransformationLineEl.textContent = currentLine;
          }
          confirmRemoveTransformationBtn.disabled = false;
          removeTransformationModal.show();
        });
      }
    };

    confirmRemoveTransformationBtn?.addEventListener('click', async () => {
      if (!pendingRemoval) return;
      confirmRemoveTransformationBtn.disabled = true;
      removeTransformationModal?.hide();
      const payload = pendingRemoval;
      pendingRemoval = null;
      await removeTransformation(payload);
    });

    removeTransformationModalEl?.addEventListener('hidden.bs.modal', () => {
      pendingRemoval = null;
      if (removeTransformationRefIdEl) {
        removeTransformationRefIdEl.textContent = '—';
      }
      if (removeTransformationFileEl) {
        removeTransformationFileEl.textContent = '—';
      }
      if (removeTransformationLineEl) {
        removeTransformationLineEl.textContent = '';
      }
      if (confirmRemoveTransformationBtn) {
        confirmRemoveTransformationBtn.disabled = false;
      }
    });

    const saveAllBtnElement = document.getElementById('editor-save-all');
    if (saveAllBtnElement) {
      saveAllBtnElement.addEventListener('click', async () => {
        const dirtyComponents = components.filter((component) => component.canEdit && unsavedChangesTracker[component.type]);
        if (!dirtyComponents.length) {
          return;
        }

        saveAllBtnElement.classList.add('saving');
        saveAllBtnElement.disabled = true;

        const failed = [];
        for (const component of dirtyComponents) {
          const ok = await saveComponent(component.type);
          if (!ok) {
            failed.push(component.filename);
          }
        }

        saveAllBtnElement.disabled = false;
        saveAllBtnElement.classList.remove('saving');

        if (!failed.length) {
          alert('Tous les fichiers modifiés ont été sauvegardés.');
        } else {
          alert(`Certains fichiers n'ont pas pu être sauvegardés: ${failed.join(', ')}`);
        }

        await refreshConsistency();
      });
    }

    components.forEach((component) => initializeEditor(component));
    refreshConsistency();
});
