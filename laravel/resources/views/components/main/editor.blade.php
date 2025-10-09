@extends('layouts.app')

@section('content')
<div class="container mt-4 editor">
    <h2>XML Editor for: {{ $version->name }}</h2>
    <p>File: {{ $isSource ? 'Source' : 'Cible' }}</p>

    <button id="save-xml" class="btn btn-success mb-2">Save</button>
    <button id="toggle-readonly" class="btn btn-warning mb-2">Enable Edit Mode</button>

    <div class="flex gap-2">
      <div class="row">
        <div class="col col-8">
          <div id="editor-container" style="border:1px solid #ccc; height:500px;" class="overflow-scroll"></div>
        </div>
        <div class="col col-4">
          <div class="d-flex flex-column gap-2">
            <div class="col">
              <button class="btn btn-primary btn-sm mb-1" data-tag="i" data-tag-text="<i></i>" data-enable-when-readonly>Italic</button>
              <button class="btn btn-danger btn-sm mb-1" data-tag-remove="i" data-enable-when-readonly>Remove</button>
            </div>
            
            <div class="col">
              @foreach ($version->getFacsimiles() ?? [] as $facsimile)
                <button class="btn btn-primary btn-sm mb-1" data-tag="<tag{{ $facsimile['name'] }}/>" data-enable-when-readonly>Insert &lt;tag{{ $facsimile['name'] }}&gt;</button>
                <button class="btn btn-danger btn-sm mb-1" data-tag-remove="<tag{{ $facsimile['name'] }}/>" data-enable-when-readonly>Remove</button>
                <span class="badge bg-secondary ms-1" data-tag-count="<tag{{ $facsimile['name'] }}/>" style="display: none;"></span>
              @endforeach
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

    window.initEditor(xmlContent, comparisonId);

    // Handle toggle readonly button
    const toggleBtn = document.getElementById('toggle-readonly');
    
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
