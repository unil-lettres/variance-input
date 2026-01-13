document.addEventListener("DOMContentLoaded", () => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const buildUrl = (path) => {
    if (typeof path !== 'string') return path;
    if (typeof window.withBasePath === 'function') {
      return window.withBasePath(path);
    }
    if (!path.startsWith('/')) {
      return '/' + path;
    }
    return path;
  };

  // Grab elements
  const authorSelector  = document.getElementById("author-selector");
  const workSelector    = document.getElementById("work-selector");

  const addAuthorBtn    = document.getElementById("add-author-btn");
  const editAuthorBtn   = document.getElementById("edit-author-btn");
  const deleteAuthorBtn = document.getElementById("delete-author-btn");

  const addWorkBtn      = document.getElementById("add-work-btn");
  const editWorkBtn     = document.getElementById("edit-work-btn");
  const deleteWorkBtn   = document.getElementById("delete-work-btn");

  const saveAuthorBtn   = document.getElementById("save-author-btn");
  const updateAuthorBtn = document.getElementById("update-author-btn");
  const saveWorkBtn     = document.getElementById("save-work-btn");
  const updateWorkBtn   = document.getElementById("update-work-btn");
  const editWorkShortTitleLabel = document.getElementById("edit-work-short-title-label");

  const mainContainer = document.getElementById("admin-main");
  const initialSelection = (() => {
    const parseId = (raw) => {
      const normalized = String(raw ?? '').trim();
      return /^\d+$/.test(normalized) ? normalized : null;
    };
    const cleanSlug = (raw) => {
      const normalized = String(raw ?? '').trim();
      return normalized !== '' ? normalized : null;
    };

    if (!mainContainer) {
      return { authorId: null, authorSlug: null, workId: null, workSlug: null };
    }

    const { initialAuthorId, initialAuthorSlug, initialWorkId, initialWorkSlug } = mainContainer.dataset;

    return {
      authorId: parseId(initialAuthorId),
      authorSlug: cleanSlug(initialAuthorSlug),
      workId: parseId(initialWorkId),
      workSlug: cleanSlug(initialWorkSlug),
    };
  })();


  // ============
  // UTILITIES
  // ============
  const JSON_HEADERS = { 'Accept': 'application/json' };
  const JSON_X_CSRF  = { ...JSON_HEADERS, 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken };

  let readyForDispatch = false;

  function dispatchWorkSelected(workId, authorId, shortTitle = null) {
    const authorOption = authorId
      ? authorSelector.querySelector(`option[value="${authorId}"]`)
      : null;
    const workOption = workId
      ? workSelector.querySelector(`option[value="${workId}"]`)
      : null;

    const authorFolder = authorOption?.dataset.folder || null;
    const workFolder   = workOption?.dataset.folder || null;
    const authorLabel  = authorOption?.textContent?.trim() || null;
    const workLabel    = workOption?.textContent?.trim() || null;

    document.dispatchEvent(new CustomEvent('workSelected', {
      detail: {
        workId,
        authorId,
        short_title: shortTitle,
        author_folder: authorFolder,
        work_folder: workFolder,
        author_label: authorLabel,
        work_label: workLabel,
      }
    }));
  }

  function resetWorksUI(authorId = null) {
    workSelector.innerHTML = '<option value="" disabled selected>Sélectionner une oeuvre</option>';
    workSelector.value     = '';
    workSelector.disabled  = !authorId;
    toggleWorkButtons();
  }

  function initialReset() {
    // No author/work yet: disable things and tell blades to clear themselves
    authorSelector.value = '';
    resetWorksUI(null, null);
    toggleAuthorButtons();
    dispatchWorkSelected(null, null);
  }

  // ============
  // LOAD + TOGGLE
  // ============
  initialReset();   // <— important: notify blades at startup

  if (initialSelection.authorId && initialSelection.workId) {
    readyForDispatch = true;
  }

  loadAuthors(initialSelection.authorId, initialSelection.workId);    // then load data

  // ——— AUTHOR CHANGE ———
  authorSelector.addEventListener("change", () => {
    toggleAuthorButtons();

    const authorId = authorSelector.value || null;

    // Always clear works UI first
    resetWorksUI(authorId);

    // Tell every blade: “no work currently selected for this author”
    dispatchWorkSelected(null, authorId);

    // Then (optionally) load that author’s works
    if (authorId) {
      reflectSelectionInUrl();
      loadWorks(authorId);
    } else {
      reflectSelectionInUrl();
    }
  });

  // ——— WORK CHANGE ———
  workSelector.addEventListener("change", () => {
    toggleWorkButtons();

    const workId   = workSelector.value || null;
    const authorId = authorSelector.value || null;

    if (!workId) {                 // cleared selection
      dispatchWorkSelected(null, authorId);
      reflectSelectionInUrl();
      return;
    }

    if (!authorId) {
      dispatchWorkSelected(workId, null);
      reflectSelectionInUrl();
      return;
    }

    // Normal case: a real work was chosen
    const selectedOption = workSelector.options[workSelector.selectedIndex];
    const shortTitle     = selectedOption?.getAttribute('data-short-title') || null;

    dispatchWorkSelected(workId, authorId, shortTitle);
    reflectSelectionInUrl();
  });

  // ==============
  //  ADD AUTHOR
  // ==============
  addAuthorBtn.addEventListener("click", () => {
    document.getElementById("new-author-name").value = "";
    document.getElementById("author-exists-msg").style.display = "none";
    new bootstrap.Modal(document.getElementById("addAuthorModal")).show();
  });

  saveAuthorBtn.addEventListener("click", () => {
    const name = document.getElementById("new-author-name").value.trim();

    if (name.length < 3) {
      alert("Le nom de l'auteur doit contenir au moins 3 caractères.");
      return;
    }

    // Check for duplicates in existing authors
    const exists = Array.from(authorSelector.options).some(
      option => option.text.toLowerCase() === name.toLowerCase()
    );
    if (exists) {
      document.getElementById("author-exists-msg").style.display = "block";
      return;
    }

    fetch(buildUrl('/api/authors'), {
      method: 'POST',
      headers: JSON_X_CSRF,
      body: JSON.stringify({ name })
    })
    .then(async response => {
      if (response.status === 409) {
        document.getElementById("author-exists-msg").style.display = "block";
        throw new Error("Author already exists");
      }
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return response.json();
    })
    .then(data => {
      bootstrap.Modal.getInstance(document.getElementById("addAuthorModal")).hide();
      loadAuthors(data.id);
    })
    .catch(err => console.error(err));
  });

  // ==============
  //  EDIT AUTHOR
  // ==============
  editAuthorBtn.addEventListener("click", () => {
    const authorId = authorSelector.value;
    if (!authorId) return;
    const authorName = authorSelector.options[authorSelector.selectedIndex].text;
    document.getElementById("edit-author-name").value = authorName;
    document.getElementById("edit-author-id").value = authorId;
    new bootstrap.Modal(document.getElementById("editAuthorModal")).show();
  });

  updateAuthorBtn.addEventListener("click", () => {
    const id = document.getElementById("edit-author-id").value;
    const name = document.getElementById("edit-author-name").value.trim();

    if (name.length < 3) {
      alert("Le nom de l'auteur doit contenir au moins 3 caractères.");
      return;
    }

    const exists = Array.from(authorSelector.options).some(option =>
      option.value !== id && option.text.toLowerCase() === name.toLowerCase()
    );
    if (exists) {
      alert("Un auteur avec ce nom existe déjà.");
      return;
    }

    fetch(buildUrl(`/api/authors/${id}`), {
      method: 'PUT',
      headers: JSON_X_CSRF,
      body: JSON.stringify({ name })
    })
    .then(res => {
      if (!res.ok) throw new Error("Erreur lors de la mise à jour");
      return res.json();
    })
    .then(() => {
      bootstrap.Modal.getInstance(document.getElementById("editAuthorModal")).hide();
      loadAuthors(id);
    })
    .catch(err => {
      alert("Erreur : impossible de mettre à jour l'auteur.");
      console.error(err);
    });
  });

  // ==============
  // DELETE AUTHOR
  // ==============
  deleteAuthorBtn.addEventListener("click", () => {
    const id = authorSelector.value;
    if (!id) return;

    const name = authorSelector.options[authorSelector.selectedIndex].text;
    if (!confirm(`Supprimer cet auteur ?\n${name}`)) return;

    fetch(buildUrl(`/api/authors/${id}`), {
      method : 'DELETE',
      headers: { 'X-CSRF-TOKEN': csrfToken, ...JSON_HEADERS }
    })
    .then(async response => {
      if (!response.ok) throw new Error("Erreur lors de la suppression de l'auteur.");

      // refresh authors list (no author pre-selected)
      loadAuthors();

      // reset works dropdown UI
      resetWorksUI(null);

      // 🔔 notify all blades to clear their work-dependent state
      dispatchWorkSelected(null, null);
    })
    .catch(error => {
      console.error(error);
      alert("Impossible de supprimer cet auteur. Vérifiez qu'il n'a pas encore d'oeuvres associées.");
    });
  });

  // ==============
  //  ADD WORK
  // ==============
  addWorkBtn.addEventListener("click", () => {
    document.getElementById("new-work-title").value = "";
    document.getElementById("new-work-short").value = "";
    document.getElementById("work-exists-msg").style.display = "none";
    new bootstrap.Modal(document.getElementById("addWorkModal")).show();
  });

  saveWorkBtn.addEventListener("click", () => {
    const title       = document.getElementById("new-work-title").value.trim();
    const shortInput = document.getElementById("new-work-short");
    const short_title = shortInput.value.trim().toLowerCase();
    shortInput.value = short_title;
    const author_id   = authorSelector.value;

    if (!author_id) return;

    // Validations
    if (title.length < 3) {
      alert("Le titre de l'œuvre doit contenir au moins 3 caractères.");
      return;
    }
    if (short_title.length < 2 || short_title.length > 8) {
      alert("Le titre abrégé doit contenir entre 2 et 8 caractères.");
      return;
    }
    const validShortTitle = /^[a-z]+$/.test(short_title);
    if (!validShortTitle) {
      alert("Le titre abrégé ne peut contenir que des lettres minuscules (a à z).");
      return;
    }
    // Check if short_title already exists for the same author
    const duplicate = Array.from(workSelector.options).some(option =>
      option.textContent.includes(`[${short_title}]`)
    );
    if (duplicate) {
      document.getElementById("work-exists-msg").style.display = "block";
      return;
    }

    fetch(buildUrl('/api/works'), {
      method: 'POST',
      headers: JSON_X_CSRF,
      body: JSON.stringify({ title, short_title, author_id })
    })
    .then(async response => {
      if (response.status === 409) {
        document.getElementById("work-exists-msg").style.display = "block";
        throw new Error("Work exists");
      }
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return response.json();
    })
    .then(data => {
      bootstrap.Modal.getInstance(document.getElementById("addWorkModal")).hide();
      loadWorks(author_id, data.id);
    })
    .catch(err => console.error(err));
  });

  // ==============
  //  EDIT WORK
  // ==============
  editWorkBtn.addEventListener("click", () => {
    const workId = workSelector.value;
    if (!workId) return;
    fetch(buildUrl(`/api/works/${workId}`), { headers: JSON_HEADERS })
      .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then(work => {
        document.getElementById("edit-work-id").value = work.id;
        document.getElementById("edit-work-title").value = work.title;
        if (editWorkShortTitleLabel) {
          editWorkShortTitleLabel.textContent = work.short_title || '';
        }
        new bootstrap.Modal(document.getElementById("editWorkModal")).show();
      })
      .catch(err => {
        console.error(err);
        alert("Impossible de charger l'œuvre.");
      });
  });

  updateWorkBtn.addEventListener("click", () => {
    const id          = document.getElementById("edit-work-id").value;
    const title       = document.getElementById("edit-work-title").value.trim();
    const author_id   = authorSelector.value;

    if (title.length < 3) {
      alert("Le titre de l'œuvre doit contenir au moins 3 caractères.");
      return;
    }

    fetch(buildUrl(`/api/works/${id}`), {
      method: 'PUT',
      headers: JSON_X_CSRF,
      body: JSON.stringify({ title })
    })
    .then(res => {
      if (!res.ok) throw new Error("Erreur serveur");
      return res.json();
    })
    .then(() => {
      bootstrap.Modal.getInstance(document.getElementById("editWorkModal")).hide();
      // Refresh the works dropdown
      loadWorks(author_id, id);
      // Notify the rest of the app that versions may have changed
      document.dispatchEvent(new CustomEvent('versionsUpdated', {
        detail: { workId: id }
      }));
    })
    .catch(err => {
      alert("Erreur lors de la mise à jour de l'œuvre.");
      console.error(err);
    });
  });

  // ============
  // DELETE WORK
  // ============
  deleteWorkBtn.addEventListener("click", () => {
    const id = workSelector.value;
    if (!id) return;

    const name = workSelector.options[workSelector.selectedIndex].text;
    if (!confirm(`Supprimer cette œuvre ?\n${name}`)) return;

    fetch(buildUrl(`/api/works/${id}`), {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': csrfToken, ...JSON_HEADERS }
    })
    .then(async response => {
      if (!response.ok) {
        // Try to parse JSON error; if not JSON, fall back to generic message
        let msg = "Erreur lors de la suppression de l'œuvre.";
        try { msg = (await response.json()).error || msg; } catch {}
        throw new Error(msg);
      }

      const authorId = authorSelector.value || null;
      loadWorks(authorId);
      // 🔔 clear description + other blades
      dispatchWorkSelected(null, authorId);
    })
    .catch(error => {
      console.error(error);
      alert(error.message);
    });
  });

  // ==============
  // HELPER FUNCS
  // ==============
  function loadAuthors(selectedAuthorId = null, selectedWorkId = null) {
    fetch(buildUrl('/api/authors'), { headers: JSON_HEADERS })
      .then(r => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then(authors => {
        authorSelector.innerHTML = '<option value="" disabled selected>Sélectionner un auteur</option>';

        const authorSortKey = (name) => {
          const cleaned = String(name || '').trim();
          if (!cleaned) return '';
          const parts = cleaned.split(/\s+/);
          const last = parts[parts.length - 1] || cleaned;
          return `${last} ${cleaned}`;
        };

        const sortedAuthors = [...authors].sort((a, b) => {
          const keyA = authorSortKey(a?.name);
          const keyB = authorSortKey(b?.name);
          return keyA.localeCompare(keyB, 'fr', { sensitivity: 'base' });
        });

        sortedAuthors.forEach(author => {
          const opt = document.createElement('option');
          opt.value = author.id;
          opt.textContent = author.name;
          opt.dataset.folder = author.folder || '';
          authorSelector.appendChild(opt);
        });

        let targetAuthorId = null;
        if (selectedAuthorId) {
          const option = authorSelector.querySelector(`option[value="${selectedAuthorId}"]`);
          if (option) {
            authorSelector.value = selectedAuthorId;
            targetAuthorId = selectedAuthorId;
          }
        }

        toggleAuthorButtons();

        if (targetAuthorId) {
          // 🔔 clear dependent blades only when we're not restoring an initial selection
          if (!readyForDispatch) {
            dispatchWorkSelected(null, targetAuthorId);
          }
          reflectSelectionInUrl();

          // repopulate works list (will start empty)
          loadWorks(targetAuthorId, selectedWorkId);
        } else {
          reflectSelectionInUrl();
        }
      })
      .catch(console.error);
  }

  function loadWorks(authorId, selectedWorkId = null) {
    if (!authorId) return;

    fetch(buildUrl(`/api/author/${authorId}/works`), { headers: JSON_HEADERS })
      .then(r => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then(works => {
        resetWorksUI(authorId, selectedWorkId);

        const workSortKey = (title) => {
          const cleaned = String(title || '').trim();
          if (!cleaned) return '';
          let stripped = cleaned.replace(/^l['’]\s*/i, '');
          stripped = stripped.replace(/^(le|la|les|un|une|des)\s+/i, '');
          return `${stripped} ${cleaned}`;
        };

        const sortedWorks = [...works].sort((a, b) => {
          const keyA = workSortKey(a?.title);
          const keyB = workSortKey(b?.title);
          return keyA.localeCompare(keyB, 'fr', { sensitivity: 'base' });
        });

        sortedWorks.forEach(work => {
          const opt = document.createElement('option');
          opt.value = work.id;
          opt.textContent = work.short_title
            ? `${work.title} [${work.short_title}]`
            : work.title;
          opt.setAttribute('data-short-title', work.short_title || '');
          opt.dataset.folder = work.folder || '';
          workSelector.appendChild(opt);
        });

        workSelector.disabled = false;
        addWorkBtn.disabled   = !authorId;

        let targetWorkId = null;
        if (selectedWorkId) {
          const option = workSelector.querySelector(`option[value="${selectedWorkId}"]`);
          if (option) {
            workSelector.value = selectedWorkId;
            targetWorkId = selectedWorkId;
          } else if (readyForDispatch && works.length > 0) {
            // Initial selection might reference a work that no longer exists;
            // fall back to the first available work.
            const fallbackOption = workSelector.querySelector(`option[value="${works[0].id}"]`);
            if (fallbackOption) {
              workSelector.value = works[0].id;
              targetWorkId = works[0].id;
            }
          }
        }

        if (targetWorkId) {
          // 🔔 let every listening blade refresh itself (description, versions, …)
          const shortTitle = workSelector.options[workSelector.selectedIndex]
                               ?.getAttribute('data-short-title') || null;

          dispatchWorkSelected(targetWorkId, authorId, shortTitle);
          readyForDispatch = false;
        } else if (readyForDispatch && works.length > 0) {
          const first = works[0];
          const option = workSelector.querySelector(`option[value=\"${first.id}\"]`);
          if (option) {
            workSelector.value = first.id;
            const shortTitle = option.getAttribute('data-short-title') || null;
            dispatchWorkSelected(first.id, authorId, shortTitle);
            readyForDispatch = false;
          }
        }

        // keep edit / delete buttons in sync
        toggleWorkButtons();
        reflectSelectionInUrl();
      })
      .catch(console.error);
  }

  function toggleAuthorButtons() {
    const hasValue = !!authorSelector.value;
    editAuthorBtn.disabled   = !hasValue;
    deleteAuthorBtn.disabled = !hasValue;
  }

  function reflectSelectionInUrl() {
    if (!window?.history || typeof window.history.replaceState !== 'function') {
      return;
    }

    const authorOption = authorSelector.value
      ? authorSelector.options[authorSelector.selectedIndex]
      : null;
    const workOption = workSelector.value
      ? workSelector.options[workSelector.selectedIndex]
      : null;

    let targetPath = '/';
    const authorSlug = authorOption?.dataset?.folder || null;

    if (authorSlug) {
      targetPath = `/select/${authorSlug}`;
      const workSlug = workOption?.dataset?.folder || null;
      if (workSlug) {
        targetPath = `/select/${authorSlug}/${workSlug}`;
      }
    }

    const baseAdjusted = typeof window.withBasePath === 'function'
      ? window.withBasePath(targetPath)
      : targetPath;

    if (typeof baseAdjusted !== 'string') {
      return;
    }

    if ((window.location.pathname || '') === baseAdjusted) {
      return;
    }

    const finalUrl = baseAdjusted + (window.location.search || '') + (window.location.hash || '');
    window.history.replaceState({}, '', finalUrl);
  }

  function toggleWorkButtons() {
    const hasValue = !!workSelector.value;
    editWorkBtn.disabled   = !hasValue;
    deleteWorkBtn.disabled = !hasValue;
  }
});
