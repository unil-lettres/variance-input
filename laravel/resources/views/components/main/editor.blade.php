@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2>XML Editor for: {{ $version->name }}</h2>
    <p>File: {{ $version->folder }}</p>

    <button id="save-xml" class="btn btn-success mb-2">Save</button>

    <div id="editor-container" style="border:1px solid #ccc; height:500px;"></div>
</div>
@endsection

@push('scripts')
@vite(['resources/js/app.js'])

<script>
document.addEventListener('DOMContentLoaded', () => {
    const xmlContent = @json($xmlContent);
    const versionId  = {{ $version->id }};
    window.initEditor(xmlContent, versionId);
});
</script>
@endpush
