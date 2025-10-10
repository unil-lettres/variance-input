@extends('layouts.app')

@section('content')
<div class="container mt-4 editor">
    <h1>Edition de la version <b>{{ $version->name }}</b> pour la comparaison <b>#{{ $comparison->id }}</b></h1>
    
    @if(!$canEdit)
    <div class="alert alert-warning mt-3" role="alert">
        <strong>⚠️ Attention :</strong> Les modifications ne sont pas autorisées.
        @if($isPublished)
            Cette comparaison est actuellement publiée.
        @elseif(!$imagesData)
            Les facsimilés pour cette version ne sont pas publiés.
        @endif
    </div>
    @endif
    
    <ul class="nav nav-tabs mt-3">
        <li class="nav-item">
            <a href="{{ route('comparison.editor', ['comparison' => $comparison->id, 'type' => 'source']) }}" 
               class="nav-link {{ $isSource ? 'active' : '' }}">
                Source
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('comparison.editor', ['comparison' => $comparison->id, 'type' => 'target']) }}" 
               class="nav-link {{ !$isSource ? 'active' : '' }}">
                Cible
            </a>
        </li>
    </ul>
    <div class="border border-top-0 p-3">
      <button id="save-xml" class="btn btn-success mb-2" {{ !$canEdit ? 'disabled' : '' }}>Enregistrer</button>
      <button id="toggle-readonly" class="btn btn-warning mb-2" {{ !$canEdit ? 'disabled' : '' }}>Activer le mode édition</button>
      <button id="toggle-tags" class="btn btn-secondary mb-2">Afficher les balises</button>

      <div class="flex gap-2">
        <div class="row">
          <div class="col col-8">
            <div id="editor-container" style="border:1px solid #ccc; height:500px;" class="overflow-scroll"></div>
          </div>
          <div class="col col-4">
            <div class="d-flex flex-column gap-2">
              <div class="col">
                <button class="btn btn-primary btn-sm mb-1" data-tag="i" data-tag-text="<i></i>" data-enable-when-readonly {{ !$canEdit ? 'disabled' : '' }}>Italique</button>
                <button class="btn btn-danger btn-sm mb-1" data-tag-remove="i" data-enable-when-readonly {{ !$canEdit ? 'disabled' : '' }}>✖️</button>
              </div>
              
              <div class="col">
                @foreach ($imagesData ?? [] as $facsimile)
                  <button class="btn btn-primary btn-sm mb-1" data-tag="<tag{{ $facsimile['small'] }}/>" data-enable-when-readonly {{ !$canEdit ? 'disabled' : '' }}>Insérer &lt;tag{{ $facsimile['small'] }}&gt;</button>
                  <button class="btn btn-danger btn-sm mb-1" data-tag-remove="<tag{{ $facsimile['small'] }}/>" data-enable-when-readonly {{ !$canEdit ? 'disabled' : '' }}>✖️</button>
                  <span class="badge bg-secondary ms-1" data-tag-count="<tag{{ $facsimile['small'] }}/>" style="display: none;"></span>
                @endforeach
              </div>
          </div>
        </div>
      </div>
    </div>
</div>
@endsection

@push('scripts')
@vite(['resources/js/app.js'])

<script>
document.addEventListener('DOMContentLoaded', () => {
    const xmlContent = @json($xmlContent);
    const comparisonId  = {{ $comparison->id }};
    const fileType = '{{ $isSource ? "source" : "target" }}';
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
                    badge.title = `This tag appears ${count} times in the document`;
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
        toggleBtn.textContent = isReadOnly ? 'Enable Edit Mode' : 'Enable Read-Only';
        toggleBtn.classList.toggle('btn-warning');
        toggleBtn.classList.toggle('btn-info');
        
        // Disable/enable buttons with data-enable-when-readonly attribute
        document.querySelectorAll('[data-enable-when-readonly]').forEach(btn => {
            btn.disabled = !isReadOnly;
        });
        
        if (isReadOnly) {
            refreshButtonStates();
        }
    });

    // Handle toggle tags visibility button
    toggleTagsBtn.addEventListener('click', () => {
        const tagsHidden = window.editor.toggleTagVisibility();
        toggleTagsBtn.textContent = tagsHidden ? 'Show Tags' : 'Hide Tags';
        toggleTagsBtn.classList.toggle('btn-secondary');
        toggleTagsBtn.classList.toggle('btn-info');
    });

    // Handle insert buttons
    document.querySelectorAll('.editor [data-tag]').forEach(button => {
        const tagName = button.getAttribute('data-tag');

        button.addEventListener('click', () => {
            const inserted = button.getAttribute('data-inserted') === 'true';

            if (inserted) {
                window.editor.scrollToTag(tagName);
            } else {
                window.editor.insertAtCursor(tagName);
                refreshButtonStates();
            }
        });
    });
    
    // Handle remove buttons
    document.querySelectorAll('.editor [data-tag-remove]').forEach(removeBtn => {
        removeBtn.addEventListener('click', async () => {
            const tagName = removeBtn.getAttribute('data-tag-remove');
            const removed = await window.editor.removeTag(tagName);
            
            if (removed) {
                refreshButtonStates();
            } else {
                console.error(`Tag "${tagName}" not found in the editor`);
            }
        });
    });

    refreshButtonStates();
});
</script>
@endpush
