<!-- resources/views/components/main/media.blade.php -->

<div class="card mb-3">
    <div class="card-header">
        <span>Médias</span>
    </div>
    <div class="card-body">
        <!-- Vignette Upload -->
        <div class="mb-3">
            <label class="form-label">Image vignette</label>
            <div id="vignette-dropzone" class="dropzone border border-2 border-secondary p-3 text-center">
                <p>Glissez une image ici ou cliquez pour sélectionner un fichier</p>
                <input type="file" id="vignette-input" accept="image/*" class="d-none" />
            </div>
            <div id="vignette-preview" class="mt-2"></div>
        </div>

        <!-- PDF Upload -->
        <div class="mb-3">
            <label class="form-label">Fichier PDF</label>
            <div id="pdf-dropzone" class="dropzone border border-2 border-secondary p-3 text-center">
                <p>Glissez un PDF ici ou cliquez pour sélectionner un fichier</p>
                <input type="file" id="pdf-input" accept="application/pdf" class="d-none" />
            </div>
            <div id="pdf-preview" class="mt-2"></div>
        </div>
    </div>
</div>

@push('scripts')
<style>
    .dropzone {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    .dropzone.hover {
        background-color: #f8f9fa;
    }
</style>

<script>
    'use strict';

//let currentWorkId = null;
let currentShortTitle = null;

function showMediaFromServer(previewId, fileUrl, type) {
    const preview = document.getElementById(previewId);
    preview.innerHTML = '';

    if (!fileUrl) return;

    if (type === 'vignette') {
        const img = document.createElement('img');
        img.src = `/${fileUrl}`;
        img.style.maxWidth = '150px';
        preview.appendChild(img);

        const name = document.createElement('p');
        name.textContent = fileUrl.split('/').pop();
        preview.appendChild(name);
    } else if (type === 'pdf') {
        const link = document.createElement('a');
        link.href = `/${fileUrl}`;
        link.target = '_blank';
        link.textContent = fileUrl.split('/').pop();
        preview.appendChild(link);
    }

    const btn = document.createElement('button');
    btn.textContent = `Supprimer ${type}`;
    btn.className = 'btn btn-sm btn-danger mt-2';
    btn.addEventListener('click', () => deleteMedia(type));
    preview.appendChild(btn);
}

document.addEventListener('DOMContentLoaded', () => {
    console.log("[MediaBlade] DOM loaded, setting up dropzones...");

    setupDropzone(
        'vignette-dropzone',
        'vignette-input',
        'image/',
        file => {
            console.log("[MediaBlade] Vignette file selected:", file.name);
            showPreview('vignette-dropzone', file);
            uploadFileImmediately(file, 'vignette', 'vignette-preview');
        }
    );

    setupDropzone(
        'pdf-dropzone',
        'pdf-input',
        'application/pdf',
        file => {
            console.log("[MediaBlade] PDF file selected:", file.name);
            showPreview('pdf-dropzone', file);
            uploadFileImmediately(file, 'pdf', 'pdf-preview');
        }
    );

    document.addEventListener('workSelected', e => {
        currentWorkId = e.detail.workId;
        currentShortTitle = e.detail.short_title || null;

        console.log("[MediaBlade] workSelected event ->", {
            workId: currentWorkId,
            shortTitle: currentShortTitle
        });

        if (currentWorkId) {
            fetch(`/works/${currentWorkId}/media`)
                .then(res => res.json())
                .then(data => {
                    showMediaFromServer('vignette-preview', data.image_url, 'vignette');
                    showMediaFromServer('pdf-preview', data.pdf_url, 'pdf');
                })
                .catch(err => console.error("Erreur chargement médias", err));
        }
    });
});

function uploadFileImmediately(file, fieldName, previewId) {
    if (!currentWorkId || !currentShortTitle) return;

    const formData = new FormData();
    formData.append(fieldName, file);

    fetch(`/api/works/${currentWorkId}/media?short_title=${encodeURIComponent(currentShortTitle)}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        console.log(`${fieldName} uploaded`, data);
        // Fetch latest media path and re-render
        return fetch(`/works/${currentWorkId}/media`);
    })
    .then(res => res.json())
    .then(data => {
        if (fieldName === 'vignette') {
            showMediaFromServer('vignette-preview', data.image_url, 'vignette');
        } else if (fieldName === 'pdf') {
            showMediaFromServer('pdf-preview', data.pdf_url, 'pdf');
        }
    })
    .catch(err => console.error("Upload error", err));
}

function deleteMedia(type) {
    if (!currentWorkId) return;

    fetch(`/api/works/${currentWorkId}/media/${type}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    })
    .then(res => {
        if (!res.ok) throw new Error("Suppression échouée");
        return res.json();
    })
    .then(data => {
        console.log(`${type} supprimé`, data);
        if (type === 'vignette') {
            document.getElementById('vignette-preview').innerHTML = '';
        } else if (type === 'pdf') {
            document.getElementById('pdf-preview').innerHTML = '';
        }
    })
    .catch(err => console.error('Erreur suppression', err));
}

function setupDropzone(dropzoneId, inputId, fileType, onFileSelect) {
    const dropzone = document.getElementById(dropzoneId);
    const input = document.getElementById(inputId);

    dropzone.addEventListener('click', () => input.click());

    dropzone.addEventListener('dragover', e => {
        e.preventDefault();
        dropzone.classList.add('hover');
    });
    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('hover');
    });

    dropzone.addEventListener('drop', e => {
        e.preventDefault();
        dropzone.classList.remove('hover');
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith(fileType)) {
            onFileSelect(file);
        } else {
            alert('Type de fichier invalide.');
        }
    });

    input.addEventListener('change', e => {
        const file = e.target.files[0];
        if (file && file.type.startsWith(fileType)) {
            onFileSelect(file);
        } else {
            alert('Type de fichier invalide.');
        }
    });
}

function showPreview(previewId, file) {
    const preview = document.getElementById(previewId.replace('-dropzone', '-preview'));
    preview.innerHTML = '';

    // ======================
    //       IMAGE
    // ======================
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = () => {
            // Show the local image
            const img = document.createElement('img');
            img.src = reader.result;
            img.style.maxWidth = '150px';
            preview.appendChild(img);

            // ✅ Display the file name too
            const nameElem = document.createElement('p');
            nameElem.textContent = file.name;
            preview.appendChild(nameElem);
            console.log("Image name appended =>", file.name);
        };
        reader.readAsDataURL(file);

    // ======================
    //        PDF
    // ======================
    } else if (file.type === 'application/pdf') {
        // Show the PDF filename
        const name = document.createElement('p');
        name.textContent = file.name; 
        preview.appendChild(name);
    }
}





    // ======================
    //  DRAG & DROP SETUP
    // ======================
    function setupDropzone(dropzoneId, inputId, fileType, onFileSelect) {
        const dropzone = document.getElementById(dropzoneId);
        const fileInput = document.getElementById(inputId);

        if (!dropzone || !fileInput) {
            console.warn("[MediaBlade] Missing elements:", dropzoneId, inputId);
            return;
        }

        // Click opens file picker
        dropzone.addEventListener("click", () => {
            console.log("[MediaBlade] dropzone clicked:", dropzoneId);
            fileInput.click();
        });

        // Drag hover style
        dropzone.addEventListener("dragover", e => {
            e.preventDefault();
            dropzone.classList.add("hover");
        });

        dropzone.addEventListener("dragleave", () => {
            dropzone.classList.remove("hover");
        });

        // Drop event
        dropzone.addEventListener("drop", e => {
            e.preventDefault();
            dropzone.classList.remove("hover");
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith(fileType)) {
                onFileSelect(file);
            } else {
                alert("Type de fichier invalide.");
            }
        });

        // Manual input
        fileInput.addEventListener("change", e => {
            const file = e.target.files[0];
            if (file && file.type.startsWith(fileType)) {
                onFileSelect(file);
            } else {
                alert("Type de fichier invalide.");
            }
        });
    }

    // ======================
    //  UPLOAD IMMEDIATELY
    // ======================
    function uploadFileImmediately(file, fieldName) {
        if (!currentWorkId) {
            alert('Aucune œuvre sélectionnée (workId).');
            return;
        }
        if (!currentShortTitle) {
            alert('Le short_title n’est pas défini pour cette œuvre.');
            return;
        }

        console.log("[MediaBlade] Uploading file for:", fieldName);

        const formData = new FormData();
        formData.append(fieldName, file);

        fetch(`/api/works/${currentWorkId}/media?short_title=${encodeURIComponent(currentShortTitle)}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content
            },
            body: formData
        })
        .then(res => {
            if (!res.ok) throw new Error(`Erreur lors de l’upload: HTTP ${res.status}`);
            return res.json();
        })
        .then(data => {
            console.log(`[MediaBlade] Fichier ${fieldName} enregistré:`, data);
        })
        .catch(err => console.error('[MediaBlade] Upload error', err));
    }
</script>
@endpush
