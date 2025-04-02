@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2>XML Editor for: {{ $version->name }}</h2>
    <p>File: {{ $version->folder }}</p>

    <button id="save-xml" class="btn btn-success mb-2">Save</button>

    <!-- The CodeMirror container -->
    <div id="editor-container" style="border:1px solid #ccc; height:500px;"></div>
</div>
@endsection

@push('scripts')
<script type="module">
import { xml } from "https://esm.sh/@codemirror/lang-xml@0.19.3?target=es2022&pin";
import { EditorState } from "https://esm.sh/@codemirror/state@0.19.3?target=es2022&pin";
import { EditorView, basicSetup } from "https://esm.sh/@codemirror/basic-setup@0.19.3?target=es2022&pin";
import { oneDark } from "https://esm.sh/@codemirror/theme-one-dark@0.19.3?target=es2022&pin";

document.addEventListener('DOMContentLoaded', () => {
    const editorContainer = document.getElementById('editor-container');
    const saveBtn = document.getElementById('save-xml');

    // This is the initial XML from the controller
    const initialXml = @json($xmlContent);
    const versionId = {{ $version->id }};

    // Initialize CodeMirror with basicSetup, XML mode, One Dark theme, and line wrapping
    const startState = EditorState.create({
        doc: initialXml,
        extensions: [
            basicSetup,
            xml(),
            oneDark,
            EditorView.lineWrapping
        ]
    });

    const editorView = new EditorView({
        state: startState,
        parent: editorContainer
    });

    // Handle "Save" button click: send updated XML to the server
    saveBtn.addEventListener('click', async () => {
        const updatedXml = editorView.state.doc.toString();

        try {
            const resp = await fetch(`/versions/${versionId}/editor`, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/xml',
                    'Accept': 'application/json'
                },
                body: updatedXml
            });
            if (!resp.ok) throw new Error('Failed to save XML');

            const data = await resp.json();
            alert(data.message || 'XML saved successfully!');
        } catch (err) {
            console.error('Error saving XML:', err);
            alert('Error saving XML: ' + err.message);
        }
    });
});
</script>
@endpush
