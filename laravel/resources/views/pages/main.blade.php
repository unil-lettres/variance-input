@extends('layouts.app')

@section('title', 'Home')

@section('content')
<div class="container mt-4">

    {{-- Sélecteur d’œuvre --}}
    <div id="zone-0" class="mb-4">
        @include('components.main.work_selector')
    </div>

    {{-- Statut (masqué par défaut) --}}
    <div id="zone-1" class="mb-4" style="display:none;">
        @include('components.main.status')
    </div>

    {{-- Description --}}
    <div id="zone-2" class="mb-4">
        @include('components.main.description')
    </div>

    {{-- Médias généraux (affiches, audio, etc.) --}}
    <div id="zone-3" class="mb-4">
        @include('components.main.media')
    </div>

    {{-- Versions --}}
    <div id="zone-4" class="mb-4">
        @include('components.main.versions')
    </div>

    {{-- ▼ NOUVEAU : fac-similés (dépend d’une version) --}}
    <div id="zone-4b" class="mb-4">
        @include('components.main.facsimiles')
    </div>

    {{-- Comparaisons --}}
    <div id="zone-5" class="mb-4">
        @include('components.main.comparisons')
    </div>

    {{-- Lancement de MEDITE --}}
    <div id="zone-6" class="mb-4">
        @include('components.main.medite')
    </div>

    {{-- Génération XHTML (Saxon) --}}
    <div id="zone-7" class="mb-4">
        @include('components.main.saxon')
    </div>

    {{-- Publication des fichiers --}}
    <div id="zone-8" class="mb-4">
        @include('components.main.publish')
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    /* Petit rappel : si vous souhaitez masquer/afficher des zones
       en fonction de la sélection d’œuvre ou de version, ajoutez
       votre logique ici. */
});
</script>
@endpush
@endsection
