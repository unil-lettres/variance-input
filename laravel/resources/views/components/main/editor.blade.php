@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2>XML Editor for: {{ $version->name }}</h2>
    <p>File: {{ $version->folder }}</p>

    <button id="save-xml" class="btn btn-success mb-2">Save</button>

    <div class="flex gap-2">
      <div class="row">
        <div class="col col-8">
          <div id="editor-container" style="border:1px solid #ccc; height:500px;" class="overflow-scroll"></div>
        </div>
        <div class="col col-4">
          <div class="d-flex flex-column gap-2">
            <div class="col">
              <button class="btn btn-primary btn-sm mb-1" onclick="window.editor.insertAtCursor('<i></i>')">Italic</button>
            </div>
            
            <div class="col">
              @foreach ($version->getFacsimiles() ?? [] as $facsimile)
                <button class="btn btn-primary btn-sm mb-1" onclick="window.editor.insertAtCursor('<tag{{ $facsimile['name'] }}>')">Insert &lt;tag{{ $facsimile['name'] }}&gt;</button>
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
});
</script>
@endpush
