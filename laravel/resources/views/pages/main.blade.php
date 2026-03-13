@extends('layouts.app')

@section('title', 'Variance — Atelier éditorial')
@php
    $initialSelection = $initialSelection ?? null;
    $hasInitialWork = !empty($initialSelection['workId'] ?? null);
@endphp
@section('body-class', ($hasInitialWork ? 'admin-loading ' : '') . 'admin-main-page')

@section('content')
@php $initialSelection = $initialSelection ?? null; @endphp

<div id="admin-main"
     class="container admin-main-stack"
     data-initial-author-id="{{ $initialSelection['authorId'] ?? '' }}"
     data-initial-author-slug="{{ $initialSelection['authorSlug'] ?? '' }}"
     data-initial-work-id="{{ $initialSelection['workId'] ?? '' }}"
     data-initial-work-slug="{{ $initialSelection['workSlug'] ?? '' }}"
     data-user-id="{{ Auth::id() ?? '' }}"
     data-user-is-admin="{{ (Auth::check() && Auth::user()->is_admin) ? '1' : '0' }}">

    <section class="editorial-journey" id="editorial-journey" aria-labelledby="editorial-journey-title">
        <div class="editorial-journey-nav">
            <div class="work-selector-intro editorial-work-context">
                <div class="work-selector-context-title" id="work-selector-context-title">Aucune œuvre sélectionnée</div>
                <div class="work-selector-context-text" id="work-selector-context-text">Sélectionnez d’abord un auteur, puis une œuvre pour charger l’atelier éditorial correspondant.</div>
            </div>
            <div class="editorial-journey-controls" role="tablist" aria-label="Étapes de l’atelier">
                <button type="button" class="editorial-step-chip is-active" id="editorial-step-chip-0" data-editorial-step-target="0" role="tab" aria-selected="true" aria-controls="editorial-step-0">
                    <span class="editorial-step-chip-number">1</span>
                    <span class="editorial-step-chip-copy">
                        <span class="editorial-step-chip-label">Choisir l’œuvre</span>
                        <span class="editorial-step-chip-detail">Auteur et contexte</span>
                    </span>
                </button>
                <button type="button" class="editorial-step-chip" id="editorial-step-chip-1" data-editorial-step-target="1" role="tab" aria-selected="false" aria-controls="editorial-step-1">
                    <span class="editorial-step-chip-number">2</span>
                    <span class="editorial-step-chip-copy">
                        <span class="editorial-step-chip-label">Décrire l’œuvre</span>
                        <span class="editorial-step-chip-detail">Présentation et notice</span>
                    </span>
                </button>
                <button type="button" class="editorial-step-chip" id="editorial-step-chip-2" data-editorial-step-target="2" role="tab" aria-selected="false" aria-controls="editorial-step-2">
                    <span class="editorial-step-chip-number">3</span>
                    <span class="editorial-step-chip-copy">
                        <span class="editorial-step-chip-label">Préparer les témoins</span>
                        <span class="editorial-step-chip-detail">Versions et fac-similés</span>
                    </span>
                </button>
                <button type="button" class="editorial-step-chip" id="editorial-step-chip-3" data-editorial-step-target="3" role="tab" aria-selected="false" aria-controls="editorial-step-3">
                    <span class="editorial-step-chip-number">4</span>
                    <span class="editorial-step-chip-copy">
                        <span class="editorial-step-chip-label">Comparer et publier</span>
                        <span class="editorial-step-chip-detail">Alignement et résultats</span>
                    </span>
                </button>
            </div>
        </div>

        <div class="editorial-carousel">
            <div class="editorial-carousel-viewport" id="editorial-carousel-viewport">
                <div class="editorial-carousel-track" id="editorial-carousel-track">
                    <section class="editorial-step-panel is-active" id="editorial-step-0" data-editorial-step="0" role="tabpanel" aria-labelledby="editorial-step-chip-0">
                        <div class="editorial-step-content">
                            <div id="zone-0">
                                @include('components.main.work_selector')
                            </div>

                            <div id="zone-1" style="display:none;">
                                @include('components.main.status')
                            </div>
                        </div>
                    </section>

                    <section class="editorial-step-panel" id="editorial-step-1" data-editorial-step="1" role="tabpanel" aria-labelledby="editorial-step-chip-1" aria-hidden="true">
                        <div class="editorial-step-content">
                            <div id="zone-2">
                                @include('components.main.description')
                            </div>

                            <div id="zone-3">
                                @include('components.main.media')
                            </div>
                        </div>
                    </section>

                    <section class="editorial-step-panel" id="editorial-step-2" data-editorial-step="2" role="tabpanel" aria-labelledby="editorial-step-chip-2" aria-hidden="true">
                        <div class="editorial-step-content">
                            <div id="zone-4">
                                @include('components.main.versions')
                            </div>

                            <div id="zone-4b">
                                @include('components.main.facsimiles')
                            </div>
                        </div>
                    </section>

                    <section class="editorial-step-panel" id="editorial-step-3" data-editorial-step="3" role="tabpanel" aria-labelledby="editorial-step-chip-3" aria-hidden="true">
                        <div class="editorial-step-content">
                            <div id="zone-6">
                                @include('components.main.medite')
                            </div>

                            <div id="zone-5">
                                @include('components.main.comparisons')
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="editorial-carousel-actions">
                <button type="button" class="editorial-carousel-arrow" id="editorial-step-prev" aria-label="Étape précédente" title="Étape précédente">
                    <span aria-hidden="true">‹</span>
                </button>
                <button type="button" class="editorial-carousel-arrow" id="editorial-step-next" aria-label="Étape suivante" title="Étape suivante">
                    <span aria-hidden="true">›</span>
                </button>
            </div>
        </div>
    </section>

</div>

@push('styles')
<style>
    .admin-main-stack {
        display: grid;
        row-gap: 24px;
    }
    .admin-main-stack > [id^="zone-"] {
        margin: 0;
    }
    .editorial-journey {
        display: grid;
        gap: 1.1rem;
    }
    .editorial-journey-nav {
        display: grid;
        gap: 0.85rem;
        padding: 1rem 1.15rem;
        border: 1px solid rgba(117, 107, 94, 0.18);
        border-radius: 1.1rem;
        background: linear-gradient(180deg, #fcfbf8 0%, #f2eee7 100%);
        box-shadow: 0 10px 24px rgba(76, 63, 46, 0.06);
    }
    .editorial-work-context {
        margin: 0;
        justify-self: center;
        width: min(100%, 44rem);
        padding: 0.65rem 0.9rem;
        border: 1px solid #ddd6ca;
        border-radius: 0.85rem;
        background: linear-gradient(180deg, #fcfbf8 0%, #f5f1ea 100%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.55);
        text-align: center;
    }
    .editorial-work-context .work-selector-context-title {
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.3;
        color: #463f38;
    }
    .editorial-work-context .work-selector-context-text {
        margin-top: 0.08rem;
        font-size: 0.8rem;
        line-height: 1.35;
        color: #655d53;
    }
    .editorial-work-context .work-selector-context-text.is-warning {
        color: #9a4d45;
        font-weight: 600;
    }
    .editorial-journey-controls {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.8rem;
    }
    .editorial-step-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.7rem;
        width: 100%;
        padding: 0.8rem 0.95rem;
        border: 1px solid rgba(117, 107, 94, 0.18);
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.78);
        color: #5f564b;
        text-align: left;
        transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease, background 0.28s ease;
    }
    .editorial-step-chip:hover {
        border-color: rgba(104, 92, 78, 0.32);
        box-shadow: 0 8px 18px rgba(94, 78, 56, 0.08);
        transform: translateY(-1px);
    }
    .editorial-step-chip.is-active {
        border-color: rgba(91, 116, 173, 0.26);
        background: linear-gradient(180deg, #ffffff 0%, #eef3ff 100%);
        box-shadow: 0 10px 20px rgba(69, 101, 163, 0.12);
    }
    .editorial-step-chip-number {
        flex: 0 0 auto;
        display: grid;
        place-items: center;
        width: 2rem;
        height: 2rem;
        border-radius: 999px;
        border: 1px solid rgba(117, 107, 94, 0.18);
        background: linear-gradient(180deg, #f5f2ec 0%, #ebe6dd 100%);
        font-size: 0.9rem;
        font-weight: 700;
        color: #5f564b;
    }
    .editorial-step-chip.is-active .editorial-step-chip-number {
        border-color: rgba(91, 116, 173, 0.22);
        background: linear-gradient(180deg, #f8fbff 0%, #e6eefb 100%);
        color: #38548f;
    }
    .editorial-step-chip-label {
        min-width: 0;
        font-size: 0.92rem;
        font-weight: 700;
        line-height: 1.25;
    }
    .editorial-step-chip-copy {
        display: grid;
        gap: 0.1rem;
        min-width: 0;
    }
    .editorial-step-chip-detail {
        font-size: 0.75rem;
        line-height: 1.25;
        color: #7a7165;
        white-space: nowrap;
    }
    .editorial-step-chip.is-active .editorial-step-chip-detail {
        color: #586f9d;
    }
    .editorial-carousel {
        display: grid;
        gap: 1rem;
    }
    .editorial-carousel-viewport {
        position: relative;
        overflow: hidden;
        border-radius: 1.2rem;
        transition: height 0.42s ease;
    }
    .editorial-carousel-track {
        display: flex;
        align-items: flex-start;
        width: 100%;
        transition: transform 0.42s ease;
        will-change: transform;
    }
    .editorial-step-panel {
        flex: 0 0 100%;
        width: 100%;
        padding-inline: 0.1rem;
        opacity: 0.55;
        transition: opacity 0.36s ease;
    }
    .editorial-step-panel.is-active {
        opacity: 1;
    }
    .editorial-step-content {
        display: grid;
        gap: 24px;
    }
    .editorial-carousel-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .editorial-carousel-arrow {
        flex: 0 0 auto;
        display: grid;
        place-items: center;
        width: 2.5rem;
        height: 2.5rem;
        border: 1px solid rgba(117, 107, 94, 0.18);
        border-radius: 999px;
        background: linear-gradient(180deg, #f5f2ec 0%, #ebe6dd 100%);
        color: #5f564b;
        box-shadow: 0 6px 14px rgba(94, 78, 56, 0.08);
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease, color 0.22s ease;
    }
    .editorial-carousel-arrow:hover:not([disabled]) {
        transform: translateY(-1px);
        border-color: rgba(104, 92, 78, 0.3);
        box-shadow: 0 10px 18px rgba(94, 78, 56, 0.12);
    }
    .editorial-carousel-arrow:focus-visible {
        outline: 2px solid rgba(69, 101, 163, 0.4);
        outline-offset: 2px;
    }
    .editorial-carousel-arrow span {
        font-size: 1.35rem;
        line-height: 1;
        font-weight: 700;
        transform: translateY(-0.04rem);
    }
    .editorial-carousel-arrow[disabled] {
        opacity: 0.45;
        box-shadow: none;
        cursor: default;
    }
    #admin-main .card-header[role="button"] {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
        min-height: 3.35rem;
    }
    #admin-main .card-header {
        background: linear-gradient(180deg, #f1f0ec 0%, #e6e3de 100%);
        color: #3f3c36;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        letter-spacing: 0.02em;
    }
    #admin-main .card-header .collapse-chevron::before {
        color: rgba(63, 60, 54, 0.75);
    }
    #admin-main .admin-card-heading {
        min-width: 0;
        flex: 1 1 auto;
    }
    #admin-main .admin-card-heading-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
        line-height: 1.15;
    }
    #admin-main .admin-card-title {
        font-size: 0.98rem;
        font-weight: 700;
        color: #403a34;
    }
    #admin-main .admin-card-subtitle {
        margin-top: 0.12rem;
        font-size: 0.72rem;
        font-weight: 500;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #7a7165;
        white-space: normal;
    }
    #admin-main .admin-card-checks {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        flex-wrap: wrap;
        justify-content: flex-end;
        align-self: center;
        min-height: 100%;
    }
    #admin-main .admin-card-check {
        min-width: 1.1rem;
        text-align: center;
        font-size: 1.05rem;
        line-height: 1;
        font-weight: 700;
        color: #1b1b1b;
        align-self: center;
    }
    #admin-main .admin-card-check--done {
        color: #198754;
    }
    #admin-main .admin-card-check--missing {
        color: #b85450;
        opacity: 0.85;
    }
    #admin-main .media-status-pill {
        min-width: 0;
        font-size: 0.82rem;
        font-weight: 600;
        line-height: 1.2;
        letter-spacing: 0.01em;
    }
    .blade-disabled {
        opacity: 0.55;
        filter: grayscale(0.6);
        pointer-events: none;
    }
    .blade-loading {
        position: relative;
    }
    .blade-loading .card-body {
        opacity: 0.45;
        filter: grayscale(0.4);
    }
    .blade-loading .blade-loading-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        pointer-events: none;
        background: rgba(255, 255, 255, 0.35);
        z-index: 2;
    }
    .blade-loading .blade-loading-overlay .spinner-border {
        width: 1.1rem;
        height: 1.1rem;
        color: #6c757d;
    }
    .blade-loading .blade-loading-overlay .loading-label {
        font-size: 0.85rem;
        color: #6c757d;
    }
    @media (max-width: 991.98px) {
        .editorial-journey-controls {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 767.98px) {
        .editorial-journey-controls {
            grid-template-columns: 1fr;
        }
        .editorial-carousel-actions {
            flex-direction: column-reverse;
            align-items: stretch;
        }
    }
</style>
@endpush

@push('scripts')
<script>
const BLADE_COLLAPSE_IDS = [
    'descriptionCollapse',
    'mediaCollapse',
    'versionsCollapse',
    'comparisonsCollapse',
    'facsimilesCollapse',
];

window.setBladeLoading = (collapseId, isLoading) => {
    const el = document.getElementById(collapseId);
    const card = el ? el.closest('.card') : null;
    if (!card) return;
    card.classList.toggle('blade-loading', !!isLoading);
    let overlay = card.querySelector('.blade-loading-overlay');
    if (isLoading) {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'blade-loading-overlay';
            overlay.innerHTML = '<div class="spinner-border spinner-border-sm" role="status" aria-label="Chargement"></div><span class="loading-label">Chargement...</span>';
            card.appendChild(overlay);
        }
    } else if (overlay) {
        overlay.remove();
    }
};

window.setAllBladesLoading = (isLoading) => {
    BLADE_COLLAPSE_IDS.forEach((id) => window.setBladeLoading(id, isLoading));
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#admin-main .modal').forEach((modalEl) => {
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
    });

    // Persist collapse state per work so deep links reopen cards as last seen
    const PERSISTED_COLLAPSES = [
        'descriptionCollapse',
        'mediaCollapse',
        'versionsCollapse',
        'comparisonsCollapse',
        'facsimilesCollapse',
    ];
    const STORAGE_PREFIX = 'variance:collapse-state:';
    const DEFAULT_WORK_KEY = 'no-work';
    const collapseInstances = new Map();
    const adminMain = document.getElementById('admin-main');
    const viewport = document.getElementById('editorial-carousel-viewport');
    const track = document.getElementById('editorial-carousel-track');
    const stepPanels = Array.from(document.querySelectorAll('[data-editorial-step]'));
    const stepButtons = Array.from(document.querySelectorAll('[data-editorial-step-target]'));
    const prevButton = document.getElementById('editorial-step-prev');
    const nextButton = document.getElementById('editorial-step-next');
    const orderedSteps = stepPanels
        .map((panel) => Number(panel.dataset.editorialStep))
        .filter((step) => Number.isFinite(step))
        .sort((a, b) => a - b);
    let currentWorkId = (adminMain?.dataset?.initialWorkId || '').trim() || null;
    let currentStep = currentWorkId ? 1 : 0;
    if (currentWorkId) {
        window.setAllBladesLoading(true);
        window.setBladeLoading('facsimilesCollapse', false);
    }

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

    const updateViewportHeight = () => {
        if (!viewport) return;
        const activePanel = stepPanels.find((panel) => Number(panel.dataset.editorialStep) === currentStep) || stepPanels[0];
        if (!activePanel) return;
        viewport.style.height = `${Math.ceil(activePanel.offsetHeight)}px`;
    };

    const updateStepUi = () => {
        if (!track) return;
        const index = Math.max(0, orderedSteps.indexOf(currentStep));
        track.style.transform = `translateX(-${index * 100}%)`;
        stepPanels.forEach((panel) => {
            const panelStep = Number(panel.dataset.editorialStep);
            const isActive = panelStep === currentStep;
            panel.classList.toggle('is-active', isActive);
            panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });
        stepButtons.forEach((button) => {
            const buttonStep = Number(button.dataset.editorialStepTarget);
            const isActive = buttonStep === currentStep;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        if (prevButton) prevButton.disabled = index <= 0;
        if (nextButton) nextButton.disabled = index >= orderedSteps.length - 1;
        updateViewportHeight();
    };

    const openEditorialStep = (step, options = {}) => {
        const parsedStep = Number(step);
        if (!Number.isFinite(parsedStep) || !orderedSteps.includes(parsedStep)) return;
        currentStep = parsedStep;
        updateStepUi();
        if (options.focusPanel !== false) {
            const target = stepPanels.find((panel) => Number(panel.dataset.editorialStep) === parsedStep);
            const focusTarget = target?.querySelector('.admin-card-title, .work-selector-context-title');
            focusTarget?.focus?.({ preventScroll: true });
        }
        try {
            history.replaceState(null, '', `#etape-${parsedStep}`);
        } catch (err) {
            // Ignore history failures.
        }
    };

    window.openEditorialStep = openEditorialStep;

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => openEditorialStep(button.dataset.editorialStepTarget));
    });
    prevButton?.addEventListener('click', () => {
        const index = orderedSteps.indexOf(currentStep);
        if (index > 0) openEditorialStep(orderedSteps[index - 1]);
    });
    nextButton?.addEventListener('click', () => {
        const index = orderedSteps.indexOf(currentStep);
        if (index > -1 && index < orderedSteps.length - 1) openEditorialStep(orderedSteps[index + 1]);
    });
    window.addEventListener('resize', updateViewportHeight);
    window.addEventListener('load', updateViewportHeight);

    const hashMatch = window.location.hash.match(/^#etape-(\d)$/);
    if (hashMatch) {
        const hashStep = Number(hashMatch[1]);
        if (orderedSteps.includes(hashStep)) {
            currentStep = hashStep;
        }
    }

    PERSISTED_COLLAPSES.forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('shown.bs.collapse', () => persistState(id, true));
        el.addEventListener('hidden.bs.collapse', () => persistState(id, false));
        el.addEventListener('shown.bs.collapse', updateViewportHeight);
        el.addEventListener('hidden.bs.collapse', updateViewportHeight);
    });

    if (typeof ResizeObserver !== 'undefined') {
        const resizeObserver = new ResizeObserver(() => updateViewportHeight());
        stepPanels.forEach((panel) => resizeObserver.observe(panel));
    }

    document.addEventListener('workSelected', (event) => {
        currentWorkId = (event?.detail?.workId ?? '').toString().trim() || null;
        toggleBladesDisabled(!currentWorkId);
        if (currentWorkId) {
            window.setAllBladesLoading(true);
            window.setBladeLoading('facsimilesCollapse', false);
        } else {
            window.setAllBladesLoading(false);
        }
        applyStateForWork(currentWorkId);
        updateViewportHeight();
    });

    toggleBladesDisabled(!currentWorkId);
    applyStateForWork(currentWorkId);
    updateStepUi();
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            document.body.classList.remove('admin-loading');
            updateViewportHeight();
        });
    });
});
</script>
@endpush
@endsection
