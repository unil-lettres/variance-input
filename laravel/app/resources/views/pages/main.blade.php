@extends('layouts.app')

@section('title', 'Home')

@section('content')
<div class="container mt-4">
    <div id="zone-0" class="mb-4">
        @include('components.main.work_selector')
    </div>

    <div id="zone-1" class="mb-4">
        @include('components.main.status')
    </div>

    <div id="zone-2" class="mb-4">
        @include('components.main.description')
    </div>

    <div id="zone-3" class="mb-4">
        @include('components.main.notice')
    </div>

    <div id="zone-4" class="mb-4">
        @include('components.main.vignette')
    </div>

    <div id="zone-5" class="mb-4">
        @include('components.main.versions')
    </div>

    <div id="zone-6" class="mb-4">
        @include('components.main.comparisons')
    </div>

    <div id="zone-7" class="mb-4">
        @include('components.main.medite')
    </div>
</div>
@endsection
