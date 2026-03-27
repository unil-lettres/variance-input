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

    <div class="admin-main-work-selector">
        @include('components.main.work_selector')
    </div>

    <section class="editorial-journey" id="editorial-journey" aria-labelledby="editorial-journey-title">
        <div class="editorial-carousel">
            <div class="editorial-carousel-viewport" id="editorial-carousel-viewport">
                <div class="editorial-carousel-track" id="editorial-carousel-track">
                    <section class="editorial-step-panel is-active" id="editorial-step-0" data-editorial-step="0" role="tabpanel" aria-labelledby="editorial-step-chip-0">
                        <div class="editorial-step-content">
                            <div class="editorial-welcome">
                                <span>Bienvenue dans l'interface de publication Variance.</span>
                                <span>Sélectionnez une oeuvre pour démarrer le processus éditorial.</span>
                            </div>
                            <div class="editorial-history" aria-labelledby="editorial-history-title">
                                <div class="editorial-history-title" id="editorial-history-title">Oeuvres récemment ouvertes en édition</div>
                                <ul class="editorial-history-list" id="editorial-history-list">
                                    <li class="editorial-history-empty">Aucun historique</li>
                                </ul>
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
    .admin-main-work-selector {
        margin-top: 0;
    }
    .admin-main-stack > [id^="zone-"] {
        margin: 0;
    }
    .editorial-journey {
        display: grid;
        gap: 0.9rem;
    }
    .editorial-carousel {
        display: grid;
        gap: 1rem;
    }
    .editorial-carousel-viewport {
        position: relative;
        overflow: hidden;
        border-radius: 0.5rem;
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
    .editorial-welcome {
        width: min(72%, 52rem);
        margin: 0 auto;
        padding: 0.9rem 1.2rem;
        font-size: 1.14rem;
        font-weight: 700;
        line-height: 1.6;
        color: #1f2933;
        text-align: center;
        background: linear-gradient(180deg, #f8f9fa 0%, #eef2f5 100%);
        border: 1px solid #dbe3ea;
        border-radius: 0.75rem;
        box-shadow: 0 10px 24px -18px rgba(15, 23, 42, 0.45);
    }
    .editorial-welcome span {
        display: block;
    }
    .editorial-history {
        width: min(60%, 44rem);
        margin: 0 auto;
        padding: 0 0.2rem;
    }
    .editorial-history-title {
        margin-bottom: 0.55rem;
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6c757d;
        text-align: center;
    }
    .editorial-history-list {
        margin: 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 0.55rem;
    }
    .editorial-history-list a,
    .editorial-history-entry {
        display: block;
        padding: 0.7rem 0.85rem;
        padding-right: 2.6rem;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        background: #fff;
        text-decoration: none;
    }
    .editorial-history-entry {
        padding-right: 0.85rem;
    }
    .editorial-history-entry > a {
        padding: 0;
        border: 0;
        border-radius: 0;
        background: transparent;
    }
    .editorial-history-entry > a:hover {
        border-color: transparent;
    }
    .editorial-history-list a:hover {
        border-color: #adb5bd;
    }
    .editorial-history-item {
        position: relative;
    }
    .editorial-history-work {
        display: block;
        font-weight: 700;
        color: #495057;
        line-height: 1.35;
    }
    .editorial-history-author {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.75rem;
        margin-top: 0.08rem;
        font-size: 0.84rem;
        color: #6c757d;
        line-height: 1.35;
    }
    .editorial-history-opened-at {
        display: inline-block;
        margin-top: 0;
        font-size: 0.76rem;
        color: #6c757d;
        line-height: 1.3;
        text-align: right;
        white-space: nowrap;
    }
    .editorial-history-remove {
        position: absolute;
        top: 0.7rem;
        right: 0.75rem;
        width: 1.5rem;
        height: 1.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 999px;
        background: transparent;
        color: #6c757d;
        font-size: 1rem;
        line-height: 1;
        cursor: pointer;
        transition: background-color 120ms ease, color 120ms ease;
    }
    .editorial-history-remove:hover,
    .editorial-history-remove:focus-visible {
        background: #e9ecef;
        color: #495057;
        outline: none;
    }
    .editorial-history-empty {
        padding: 0.1rem 0;
        font-size: 0.95rem;
        color: #6c757d;
        font-style: italic;
        text-align: center;
    }
    .editorial-history-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-top: 0.08rem;
        font-size: 0.84rem;
        color: #6c757d;
        line-height: 1.35;
    }
    .editorial-history-count {
        font-size: 0.76rem;
        color: #6c757d;
        white-space: nowrap;
    }
    .editorial-history-comparisons {
        margin-top: 0.55rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }
    .editorial-history-comparison {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        padding: 0.22rem 0.5rem;
        border: 1px solid #dee2e6;
        border-radius: 999px;
        background: #f8f9fa;
        font-size: 0.76rem;
        line-height: 1.25;
        color: #495057;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .editorial-history-comparison-empty {
        margin-top: 0.55rem;
        font-size: 0.8rem;
        color: #6c757d;
        font-style: italic;
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
        border: 1px solid #ced4da;
        border-radius: 999px;
        background: #fff;
        color: #495057;
        transition: border-color 0.18s ease, color 0.18s ease, background-color 0.18s ease;
    }
    .editorial-carousel-arrow:hover:not([disabled]) {
        border-color: #adb5bd;
        background: #f8f9fa;
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
        cursor: default;
    }
    #admin-main .card-header[role="button"] {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
        min-height: 3.35rem;
    }
    #admin-main .card-header {
        background: #f8f9fa;
        color: #495057;
        border-bottom: 1px solid #dee2e6;
        box-shadow: none;
        letter-spacing: 0.01em;
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
        color: #495057;
    }
    #admin-main .admin-card-subtitle {
        margin-top: 0.12rem;
        font-size: 0.72rem;
        font-weight: 500;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #6c757d;
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
    }
    @media (max-width: 767.98px) {
        .editorial-history {
            width: 100%;
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
    const HISTORY_KEY = 'variance:history:v1';
    const historyTitle = document.getElementById('editorial-history-title');
    const historyList = document.getElementById('editorial-history-list');

    const readHistory = () => {
        try {
            const raw = localStorage.getItem(HISTORY_KEY);
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (err) {
            return [];
        }
    };

    const writeHistory = (items) => {
        try {
            localStorage.setItem(HISTORY_KEY, JSON.stringify(Array.isArray(items) ? items : []));
        } catch (err) {
            // Ignore storage failures.
        }
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const buildSelectUrl = (entry) => {
        const authorSlug = String(entry?.authorSlug ?? '').trim();
        const workSlug = String(entry?.workSlug ?? '').trim();
        if (!authorSlug || !workSlug) return null;
        const path = `/select/${encodeURIComponent(authorSlug)}/${encodeURIComponent(workSlug)}`;
        return typeof window.withBasePath === 'function' ? window.withBasePath(path) : path;
    };

    const formatOpenedAt = (value) => {
        const timestamp = Number(value);
        if (!Number.isFinite(timestamp) || timestamp <= 0) return '';
        try {
            return new Intl.DateTimeFormat('fr-CH', {
                dateStyle: 'short',
                timeStyle: 'short',
            }).format(new Date(timestamp));
        } catch (err) {
            return '';
        }
    };

    const selectedAuthorOption = () => {
        const authorSelector = document.getElementById('author-selector');
        if (!authorSelector || !authorSelector.value) return null;
        return authorSelector.options[authorSelector.selectedIndex] || null;
    };

    const buildSelectUrlFromSlugs = (authorSlug, workSlug, stepHash = '') => {
        const normalizedAuthorSlug = String(authorSlug ?? '').trim();
        const normalizedWorkSlug = String(workSlug ?? '').trim();
        if (!normalizedAuthorSlug || !normalizedWorkSlug) return null;
        const path = `/select/${encodeURIComponent(normalizedAuthorSlug)}/${encodeURIComponent(normalizedWorkSlug)}`;
        const base = typeof window.withBasePath === 'function' ? window.withBasePath(path) : path;
        return `${base}${stepHash}`;
    };

    let currentAuthorId = null;
    let currentAuthorSlug = null;
    let currentAuthorLabel = null;
    let currentWorkId = null;
    let authorOverviewRequest = 0;

    const resolveCurrentAuthorLabel = () => {
        const option = selectedAuthorOption();
        if (option?.textContent?.trim()) {
            currentAuthorLabel = option.textContent.trim();
        }
        return currentAuthorLabel || 'auteur sélectionné';
    };

    const resolveCurrentAuthorSlug = () => {
        const option = selectedAuthorOption();
        if (option?.dataset?.folder?.trim()) {
            currentAuthorSlug = option.dataset.folder.trim();
        }
        return currentAuthorSlug || null;
    };

    const buildComparisonLabel = (comparison) => {
        const sourceName = comparison?.source_version?.name ?? `Version ${comparison?.source_id ?? '?'}`;
        const targetName = comparison?.target_version?.name ?? `Version ${comparison?.target_id ?? '?'}`;
        return `${sourceName} - ${targetName}`;
    };

    const renderRecentHistory = () => {
        if (!historyList) return;
        if (historyTitle) {
            historyTitle.textContent = 'Oeuvres récemment ouvertes en édition';
        }
        const items = readHistory().filter(Boolean);
        if (items.length === 0) {
            historyList.innerHTML = '<li class="editorial-history-empty">Aucun historique</li>';
            return;
        }

        historyList.innerHTML = items.slice(0, 5).map((entry) => {
            const href = buildSelectUrl(entry) || (typeof window.withBasePath === 'function' ? window.withBasePath('/') : '/');
            const workLabel = entry?.workLabel || 'Œuvre';
            const authorLabel = entry?.authorLabel || 'Auteur';
            const openedAt = formatOpenedAt(entry?.updatedAt);
            const authorId = String(entry?.authorId ?? '').trim();
            const workId = String(entry?.workId ?? '').trim();
            return `
                <li class="editorial-history-item">
                    <a href="${escapeHtml(href)}"
                       data-history-author-id="${escapeHtml(authorId)}"
                       data-history-work-id="${escapeHtml(workId)}">
                        <span class="editorial-history-work">${escapeHtml(workLabel)}</span>
                        <span class="editorial-history-author">
                            <span>${escapeHtml(authorLabel)}</span>
                            ${openedAt ? `<span class="editorial-history-opened-at">${escapeHtml(openedAt)}</span>` : ''}
                        </span>
                    </a>
                    <button type="button"
                            class="editorial-history-remove"
                            data-history-remove
                            data-history-author-id="${escapeHtml(authorId)}"
                            data-history-work-id="${escapeHtml(workId)}"
                            aria-label="Retirer cette entrée de la liste"
                            title="Retirer cette entrée de la liste">
                        ×
                    </button>
                </li>
            `;
        }).join('');
    };

    const renderAuthorOverview = async () => {
        if (!historyList) return;
        if (!currentAuthorId || currentWorkId) {
            renderRecentHistory();
            return;
        }

        const requestId = ++authorOverviewRequest;
        const authorLabel = resolveCurrentAuthorLabel();
        const authorSlug = resolveCurrentAuthorSlug();

        if (historyTitle) {
            historyTitle.textContent = `Oeuvres et comparaisons de ${authorLabel}`;
        }
        historyList.innerHTML = '<li class="editorial-history-empty">Chargement…</li>';

        try {
            const worksUrl = typeof window.withBasePath === 'function'
                ? window.withBasePath(`/api/author/${encodeURIComponent(currentAuthorId)}/works`)
                : `/api/author/${encodeURIComponent(currentAuthorId)}/works`;
            const worksRes = await fetch(worksUrl, { headers: { Accept: 'application/json' } });
            if (!worksRes.ok) throw new Error(`HTTP ${worksRes.status}`);
            const works = await worksRes.json();

            if (requestId !== authorOverviewRequest) return;

            if (!Array.isArray(works) || works.length === 0) {
                historyList.innerHTML = '<li class="editorial-history-empty">Aucune oeuvre pour cet auteur.</li>';
                return;
            }

            const sortedWorks = [...works].sort((a, b) => String(a?.title ?? '').localeCompare(String(b?.title ?? ''), 'fr', { sensitivity: 'base' }));

            const entries = await Promise.all(sortedWorks.map(async (work) => {
                try {
                    const comparisonsUrl = typeof window.withBasePath === 'function'
                        ? window.withBasePath(`/comparisons/by-work?work_id=${encodeURIComponent(work.id)}&light=1`)
                        : `/comparisons/by-work?work_id=${encodeURIComponent(work.id)}&light=1`;
                    const response = await fetch(comparisonsUrl, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    const comparisons = await response.json();
                    return { work, comparisons: Array.isArray(comparisons) ? comparisons : [], failed: false };
                } catch (err) {
                    return { work, comparisons: [], failed: true };
                }
            }));

            if (requestId !== authorOverviewRequest) return;

            historyList.innerHTML = entries.map(({ work, comparisons, failed }) => {
                const count = comparisons.length;
                const workHref = buildSelectUrlFromSlugs(authorSlug, work?.folder)
                    || (typeof window.withBasePath === 'function' ? window.withBasePath('/') : '/');
                const comparisonsMarkup = failed
                    ? '<div class="editorial-history-comparison-empty">Comparaisons indisponibles pour le moment.</div>'
                    : count > 0
                        ? `<div class="editorial-history-comparisons">${comparisons.map((comparison) => `<span class="editorial-history-comparison" title="${escapeHtml(buildComparisonLabel(comparison))}">${escapeHtml(buildComparisonLabel(comparison))}</span>`).join('')}</div>`
                        : '<div class="editorial-history-comparison-empty">Aucune comparaison pour cette oeuvre.</div>';

                return `
                    <li class="editorial-history-item">
                        <div class="editorial-history-entry">
                            <a href="${escapeHtml(workHref)}">
                                <span class="editorial-history-work">${escapeHtml(work?.title || 'Œuvre')}</span>
                            </a>
                            <div class="editorial-history-meta">
                                <span>${escapeHtml(authorLabel)}</span>
                                <span class="editorial-history-count">${count} comparaison${count > 1 ? 's' : ''}</span>
                            </div>
                            ${comparisonsMarkup}
                        </div>
                    </li>
                `;
            }).join('');
        } catch (err) {
            if (requestId !== authorOverviewRequest) return;
            historyList.innerHTML = '<li class="editorial-history-empty">Impossible de charger les oeuvres de cet auteur.</li>';
        }
    };

    const renderStepOneList = () => {
        if (currentAuthorId && !currentWorkId) {
            renderAuthorOverview();
            return;
        }
        renderRecentHistory();
    };

    historyList?.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-history-remove]');
        if (!removeButton) return;
        event.preventDefault();
        event.stopPropagation();

        const authorId = String(removeButton.getAttribute('data-history-author-id') ?? '').trim();
        const workId = String(removeButton.getAttribute('data-history-work-id') ?? '').trim();
        if (!authorId || !workId) return;

        const key = `${authorId}:${workId}`;
        const nextItems = readHistory().filter((entry) => {
            const entryKey = `${String(entry?.authorId ?? '').trim()}:${String(entry?.workId ?? '').trim()}`;
            return entryKey !== key;
        });
        writeHistory(nextItems);
        renderStepOneList();
    });

    document.addEventListener('recentWorksHistoryChanged', () => {
        renderStepOneList();
    });

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
    currentAuthorId = (adminMain?.dataset?.initialAuthorId || '').trim() || null;
    currentAuthorSlug = (adminMain?.dataset?.initialAuthorSlug || '').trim() || null;
    currentWorkId = (adminMain?.dataset?.initialWorkId || '').trim() || null;
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
        const hasWorkSelected = !!currentWorkId;
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
            const isDisabled = !hasWorkSelected && buttonStep > 0;
            button.classList.toggle('is-active', isActive);
            button.classList.toggle('is-disabled', isDisabled);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            button.disabled = isDisabled;
            button.setAttribute('aria-disabled', isDisabled ? 'true' : 'false');
        });
        if (prevButton) prevButton.disabled = index <= 0;
        if (nextButton) nextButton.disabled = !hasWorkSelected || index >= orderedSteps.length - 1;
        updateViewportHeight();
    };

    const openEditorialStep = (step, options = {}) => {
        const parsedStep = Number(step);
        if (!Number.isFinite(parsedStep) || !orderedSteps.includes(parsedStep)) return;
        if (!currentWorkId && parsedStep > 0) return;
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
        currentAuthorId = (event?.detail?.authorId ?? '').toString().trim() || null;
        currentAuthorSlug = (event?.detail?.author_folder ?? '').toString().trim() || null;
        currentAuthorLabel = (event?.detail?.author_label ?? '').toString().trim() || null;
        currentWorkId = (event?.detail?.workId ?? '').toString().trim() || null;
        toggleBladesDisabled(!currentWorkId);
        if (currentWorkId) {
            window.setBladeLoading('facsimilesCollapse', false);
            if (currentStep === 0) {
                openEditorialStep(1, { focusPanel: false });
            }
        } else {
            currentStep = 0;
            window.setAllBladesLoading(false);
        }
        applyStateForWork(currentWorkId);
        updateStepUi();
        renderStepOneList();
        updateViewportHeight();
    });

    toggleBladesDisabled(!currentWorkId);
    applyStateForWork(currentWorkId);
    renderStepOneList();
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
