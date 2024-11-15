<!-- work_selector.blade.php -->

<div class="card mb-3">
    <div class="card-header">Sélection de l'oeuvre</div>
    <div class="card-body">
        <div class="row">
            <!-- Left Column: Author Dropdown and Add Author Button -->
            <div class="col-md-6">
                <div class="form-group">
                    <label for="author-selector">Auteur</label>
                    <div class="input-group">
                        <select id="author-selector" class="form-control">
                            <option value="">Sélectionner un auteur</option>
                        </select>
                        <div class="input-group-append">
                            <button id="add-author-btn" class="btn btn-outline-success">+</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Work Dropdown and Add Work Button -->
            <div class="col-md-6">
                <div class="form-group">
                    <label for="work-selector">Oeuvre</label>
                    <div class="input-group">
                        <select id="work-selector" class="form-control" disabled>
                            <option value="">Sélectionner une oeuvre</option>
                        </select>
                        <div class="input-group-append">
                            <button id="add-work-btn" class="btn btn-outline-success" disabled>+</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Popup for Adding Author -->
<div id="authorPopup" class="popup-overlay" style="display: none;">
    <div class="popup-content">
        <span class="close-btn" id="closeAuthorPopup">&times;</span>
        <h5 class="mb-4">Ajouter un nouvel auteur</h5>
        <form id="add-author-form">
            <div class="form-group mb-3">
                <label for="author-name">Nom de l'auteur</label>
                <input type="text" class="form-control" id="author-name" required>
            </div>
            <button type="button" id="save-author-btn" class="btn btn-success w-100">Enregistrer Auteur</button>
        </form>
    </div>
</div>

<!-- Popup for Adding Work -->
<div id="workPopup" class="popup-overlay" style="display: none;">
    <div class="popup-content">
        <span class="close-btn" id="closeWorkPopup">&times;</span>
        <h5 class="mb-4">Ajouter une nouvelle oeuvre</h5>
        <form id="add-work-form">
            <div class="form-group mb-3">
                <label for="work-title">Titre de l'oeuvre</label>
                <input type="text" class="form-control" id="work-title" required>
            </div>
            <button type="button" id="save-work-btn" class="btn btn-success w-100">Enregistrer Oeuvre</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", () => {
    const authorPopup = document.getElementById("authorPopup");
    const addAuthorBtn = document.getElementById("add-author-btn");
    const closeAuthorPopup = document.getElementById("closeAuthorPopup");
    const saveAuthorBtn = document.getElementById("save-author-btn");

    const workPopup = document.getElementById("workPopup");
    const addWorkBtn = document.getElementById("add-work-btn");
    const closeWorkPopup = document.getElementById("closeWorkPopup");
    const saveWorkBtn = document.getElementById("save-work-btn");

    const authorSelector = document.getElementById("author-selector");
    const workSelector = document.getElementById("work-selector");

    const csrfToken = "{{ csrf_token() }}";

    function loadAuthors(selectedAuthorId = null) {
        fetch('/api/authors', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(authors => {
            authorSelector.innerHTML = '<option value="">Sélectionner un auteur</option>';
            authors.forEach(author => {
                const option = document.createElement("option");
                option.value = author.id;
                option.textContent = author.name;
                authorSelector.appendChild(option);
            });
            if (selectedAuthorId) {
                authorSelector.value = selectedAuthorId;
                loadWorks(selectedAuthorId);
            }
        })
        .catch(error => console.error("Error loading authors:", error));
    }

    function loadWorks(authorId, selectedWorkId = null) {
    fetch(`/api/author/${authorId}/works`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(works => {
        // Populate the works dropdown
        workSelector.innerHTML = '<option value="">Sélectionner une oeuvre</option>';
        works.forEach(work => {
            const option = document.createElement("option");
            option.value = work.id;
            option.textContent = work.title;
            workSelector.appendChild(option);
        });

        // Enable the works dropdown and the add button
        workSelector.disabled = false;
        addWorkBtn.disabled = false;

        // If there's only one work, select it and check permissions
        if (works.length === 1) {
            workSelector.value = works[0].id;
            handleWorkSelection(works[0].id);  // Trigger permission check if auto-selected
        }

        if (selectedWorkId) {
            workSelector.value = selectedWorkId;
            handleWorkSelection(selectedWorkId);
        }

        workSelector.onchange = function () {
            handleWorkSelection(workSelector.value);
        };
    })
    .catch(error => console.error("Error loading works:", error));
    }

    // Quand une oeuvre est sélectionnée, check permissions et
    // émettre événement "workSelected" avec JSON: workId, canEdit
    function handleWorkSelection(workId) {
    if (!workId) return;

    fetch(`/works/${workId}/can-edit`)
        .then(response => response.json())
        .then(data => {
            const event = new CustomEvent('workSelected', {
                detail: { workId: workId, canEdit: data.canEdit }
            });
            document.dispatchEvent(event);
        })
        .catch(error => console.error('Error checking edit permissions:', error));
    }

    loadAuthors();

    authorSelector.addEventListener("change", () => {
        const authorId = authorSelector.value;
        if (authorId) {
            loadWorks(authorId);
        } else {
            workSelector.innerHTML = '<option value="">Sélectionner une oeuvre</option>';
            workSelector.disabled = true;
            addWorkBtn.disabled = true;
        }
    });

    // Show the author popup
    addAuthorBtn.addEventListener("click", () => {
        authorPopup.style.display = "flex";
    });

    // Close author popup
    closeAuthorPopup.addEventListener("click", () => {
        authorPopup.style.display = "none";
    });

    // Show the work popup
    addWorkBtn.addEventListener("click", () => {
        workPopup.style.display = "flex";
    });

    // Close work popup
    closeWorkPopup.addEventListener("click", () => {
        workPopup.style.display = "none";
    });

    // Handle form submission (for both Enter and button click)
    document.getElementById("add-author-form").addEventListener("submit", (event) => {
        event.preventDefault();
        saveAuthor();
    });

    // Handle button click explicitly (to ensure it also triggers saving)
    document.getElementById("save-author-btn").addEventListener("click", (event) => {
        event.preventDefault();
        saveAuthor();
    });

    // Function to save the author
    function saveAuthor() {
        const authorName = document.getElementById("author-name").value;

        fetch('/api/authors', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ name: authorName })
        })
        .then(response => response.json())
        .then(author => {
            document.getElementById("add-author-form").reset();
            authorPopup.style.display = "none";
            loadAuthors(author.id);
        })
        .catch(error => console.error("Error saving author:", error));
    }

    // Handle form submission (for both Enter and button click)
    document.getElementById("add-work-form").addEventListener("submit", (event) => {
        event.preventDefault();
        saveWork();
    });

    // Handle button click explicitly (to ensure it also triggers saving)
    document.getElementById("save-work-btn").addEventListener("click", (event) => {
        event.preventDefault();
        saveWork();
    });

    // Function to save the work
    function saveWork() {
        const workTitle = document.getElementById("work-title").value;
        const authorId = document.getElementById("author-selector").value;

        fetch('/api/works', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ title: workTitle, author_id: authorId })
        })
        .then(response => response.json())
        .then(work => {
            document.getElementById("add-work-form").reset();
            workPopup.style.display = "none";
            loadWorks(authorId, work.id);
        })
        .catch(error => console.error("Error saving work:", error));
    }

    // Close popup when clicking outside
    window.addEventListener("click", (e) => {
        if (e.target === authorPopup) {
            authorPopup.style.display = "none";
        } else if (e.target === workPopup) {
            workPopup.style.display = "none";
        }
    });
});
</script>

@endpush

<!-- CSS for Popup Styling -->
<style>
/* Overlay for the popup */
.popup-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

/* Popup box styling */
.popup-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    width: 400px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
    position: relative;
    font-size: 1rem;
}

/* Close button in top right of popup */
.close-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 1.5rem;
    color: #333;
    cursor: pointer;
}
</style> 
