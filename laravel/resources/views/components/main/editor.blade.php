@extends('layouts.app')

@section('content')
<div class="container mt-4 editor">
    <h2>XML Editor for: {{ $version->name }}</h2>
    <p>File: {{ $version->folder }}</p>

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
    const versionId  = {{ $version->id }};

    window.initEditor(xmlContent, versionId);

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
    
    const refreshButtonStates = () => {
        document.querySelectorAll('.editor [data-tag]').forEach(button => {
            const tagName = button.getAttribute('data-tag');
            if (window.editor && window.editor.isTagInserted(tagName)) {
                setTagInserted(button);
            } else {
                setTagNotInserted(button);
            }
        });
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

        // Change button color if tag is already inserted
        if (window.editor && window.editor.isTagInserted(tagName)) {
            setTagInserted(button);
        }
        
        button.addEventListener('click', () => {
            const inserted = button.getAttribute('data-inserted') === 'true';

            if (inserted) {
                window.editor.scrollToTag(tagName);
            } else {
                window.editor.insertAtCursor(tagName);
                setTagInserted(button);
            }
        });
    });
    
    // Handle remove buttons
    document.querySelectorAll('.editor [data-tag-remove]').forEach(removeBtn => {
        removeBtn.addEventListener('click', () => {
            const tagName = removeBtn.getAttribute('data-tag-remove');
            const removed = window.editor.removeTag(tagName);
            
            if (removed) {
                // Reset the corresponding insert button
                const insertBtn = document.querySelector(`[data-tag="${tagName}"]`);
                if (insertBtn) {
                    insertBtn.classList.remove('btn-success');
                    insertBtn.classList.add('btn-primary');
                    insertBtn.removeAttribute('data-inserted');
                }
            } else {
                console.error(`Tag "${tagName}" not found in the editor`);
            }
        });
    });
});
</script>
@endpush
