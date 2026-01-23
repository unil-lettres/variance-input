@extends('layouts.app')

@section('title', 'Home')
@section('body-class', 'admin-loading admin-main-page')

@section('content')
@php
    $initialSelection = $initialSelection ?? null;
@endphp

<div id="admin-main"
     class="container admin-main-stack"
     data-initial-author-id="{{ $initialSelection['authorId'] ?? '' }}"
     data-initial-author-slug="{{ $initialSelection['authorSlug'] ?? '' }}"
     data-initial-work-id="{{ $initialSelection['workId'] ?? '' }}"
     data-initial-work-slug="{{ $initialSelection['workSlug'] ?? '' }}">

    {{-- Sélecteur d’œuvre --}}
    <div id="zone-0">
        @include('components.main.work_selector')
    </div>

    {{-- Statut (masqué par défaut) --}}
    <div id="zone-1" style="display:none;">
        @include('components.main.status')
    </div>

    {{-- Description --}}
    <div id="zone-2">
        @include('components.main.description')
    </div>

    {{-- Médias --}}
    <div id="zone-3">
        @include('components.main.media')
    </div>

    {{-- Versions --}}
    <div id="zone-4">
        @include('components.main.versions')
    </div>

    {{-- Comparaisons --}}
    <div id="zone-5">
        @include('components.main.comparisons')
    </div>

    {{-- Fac-similés --}}
    <div id="zone-4b">
        @include('components.main.facsimiles')
    </div>

    {{-- Lancement de MEDITE --}}
    <div id="zone-6">
        @include('components.main.medite')
    </div>

</div>

<div class="admin-banner container py-2 text-center small text-white shadow-sm mt-4 admin-bottom-banner">
    Variance-Input &copy; UNIL/SIER 2026 · Laravel {{ app()->version() }} · sier@unil.ch
</div>

@push('styles')
<style>
    .admin-main-stack {
        display: grid;
        row-gap: 24px;
    }
    .admin-main-page .admin-bottom-banner {
        margin-top: 1.5rem;
    }
    .admin-main-stack > [id^="zone-"] {
        margin: 0;
    }
    #admin-main .card-header[role="button"] {
        padding-top: 0;
        padding-bottom: 0;
        height: 2rem;
        min-height: 2rem;
    }
    .blade-disabled {
        opacity: 0.55;
        filter: grayscale(0.6);
        pointer-events: none;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Persist collapse state per work so deep links reopen cards as last seen
    const PERSISTED_COLLAPSES = [
        'descriptionCollapse',
        'mediaCollapse',
        'versionsCollapse',
        'comparisonsCollapse',
        'facsimilesCollapse',
        'mediteCollapse',
    ];
    const STORAGE_PREFIX = 'variance:collapse-state:';
    const DEFAULT_WORK_KEY = 'no-work';
    const collapseInstances = new Map();
    const adminMain = document.getElementById('admin-main');
    let currentWorkId = (adminMain?.dataset?.initialWorkId || '').trim() || null;

    const storageKey = (workId) => `${STORAGE_PREFIX}${workId ?? DEFAULT_WORK_KEY}`;

    const readState = (workId) => {
        try {
            const raw = localStorage.getItem(storageKey(workId));
            if (!raw) return {};
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (err) {
            return {};
        }
    };

    const writeState = (workId, state) => {
        try {
            localStorage.setItem(storageKey(workId), JSON.stringify(state || {}));
        } catch (err) {
            // Ignore storage failures (private mode, quotas…)
        }
    };

    const getCollapse = (id) => {
        const el = document.getElementById(id);
        if (!el) return null;
        if (!collapseInstances.has(id)) {
            const instance = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
            collapseInstances.set(id, instance);
        }
        return collapseInstances.get(id);
    };

    const applyStateForWork = (workId) => {
        const state = readState(workId);
        PERSISTED_COLLAPSES.forEach((id) => {
            const instance = getCollapse(id);
            if (!instance) return;
            const desired = state[id];
            if (desired === false) {
                instance.hide();
            } else if (desired === true) {
                instance.show();
            } else {
                instance.show();
            }
        });
    };

    const persistState = (collapseId, isOpen) => {
        const state = readState(currentWorkId);
        state[collapseId] = isOpen;
        writeState(currentWorkId, state);
    };

    const toggleBladesDisabled = (disabled) => {
        PERSISTED_COLLAPSES.forEach((id) => {
            const card = document.getElementById(id)?.closest('.card');
            if (card) {
                card.classList.toggle('blade-disabled', disabled);
            }
            if (disabled) {
                const instance = getCollapse(id);
                instance?.hide();
            }
        });
    };

    PERSISTED_COLLAPSES.forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('shown.bs.collapse', () => persistState(id, true));
        el.addEventListener('hidden.bs.collapse', () => persistState(id, false));
    });

    document.addEventListener('workSelected', (event) => {
        currentWorkId = (event?.detail?.workId ?? '').toString().trim() || null;
        toggleBladesDisabled(!currentWorkId);
        applyStateForWork(currentWorkId);
    });

    toggleBladesDisabled(!currentWorkId);
    applyStateForWork(currentWorkId);
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            document.body.classList.remove('admin-loading');
        });
    });
});
</script>
@endpush
@endsection
