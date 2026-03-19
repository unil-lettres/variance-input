<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Variance — Atelier éditorial')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <link rel="preload" as="image" href="{{ asset('images/admin_banner.jpg') }}">

    <!-- Custom Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inconsolata:wght@400;500;600;700&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $basePath = $appBasePath ?? admin_base_prefix();
        $baseUrl  = $appBaseUrl ?? rtrim(admin_url(), '/');
        $legacySkin = filter_var(env('LEGACY_SKIN', 'true'), FILTER_VALIDATE_BOOLEAN);
    @endphp
    <script>
        window.APP_BASE_PATH = @json($basePath);
        window.APP_BASE_URL = @json($baseUrl);
        window.withBasePath = function (path) {
            if (typeof path !== 'string') return path;
            if (!path.startsWith('/')) {
                path = '/' + path;
            }
            var base = window.APP_BASE_PATH || '';
            if (!base) {
                return path;
            }
            if (base.endsWith('/')) {
                base = base.slice(0, -1);
            }
            return base + path;
        };
        window.withBaseUrl = function (path) {
            var baseUrl = window.APP_BASE_URL || '';
            if (!baseUrl) {
                return window.withBasePath(path);
            }
            if (baseUrl.endsWith('/')) {
                baseUrl = baseUrl.slice(0, -1);
            }
            return baseUrl + window.withBasePath(path);
        };
    </script>

    <!-- Vite Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    @php
        $adminBannerColor = 'rgba(111, 109, 106, 0.14)';
        $adminBannerStyle = 'background-color: ' . $adminBannerColor . ';';
        $mediteStatusUrl = env('MEDITE_STATUS_URL', '/medite/');
    @endphp
    <style>
        :root {
            --font-serif: "Open Sans", "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            --font-sans: "Inconsolata", "Courier New", Courier, monospace;
        }
        body {
            font-family: var(--font-sans);
        }
        h1, h2, h3, h4, h5, h6,
        .card-header,
        .admin-banner {
            font-family: var(--font-serif);
            letter-spacing: 0.01em;
        }
        .admin-user-toggle,
        .admin-user-menu {
            font-family: var(--font-sans);
            letter-spacing: 0;
        }
        .admin-banner {
            --banner-ink: rgba(0, 0, 0, 0.35);
            --banner-wash: rgba(255, 255, 255, 0.06);
            background-color: {{ $adminBannerColor }};
            background-image:
                radial-gradient(160px 120px at 10% 65%, var(--banner-ink), rgba(0, 0, 0, 0) 60%),
                radial-gradient(260px 200px at 26% 30%, rgba(0, 0, 0, 0.25), rgba(0, 0, 0, 0) 70%),
                linear-gradient(180deg, var(--banner-wash), rgba(0, 0, 0, 0.12)),
                repeating-linear-gradient(
                    45deg,
                    rgba(255, 255, 255, 0.04) 0 2px,
                    rgba(0, 0, 0, 0.03) 2px 4px
                );
            background-blend-mode: multiply, multiply, normal, normal;
            background-size: auto, auto, auto, 6px 6px;
        }
        .admin-banner-shell {
            border-bottom: 1px solid rgba(210, 165, 74, 0.65);
        }
        header .admin-banner {
            background-image: none;
            background-color: rgba(111, 109, 106, 0.14) !important;
            color: #6a6157 !important;
            box-shadow: none;
            border-bottom: 1px solid rgba(117, 107, 94, 0.12);
        }
        .login-bg {
            background-color: #f4f1ed;
            background-image:
                linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(255, 255, 255, 0.88)),
                url("{{ legacy_url('uploads_images/assommoir_emile_zola_couv.jpg') }}"),
                url("{{ legacy_url('uploads_images/la_vendetta.png') }}"),
                url("{{ legacy_url('uploads_images/peau_de_chagrin.jpg') }}"),
                url("{{ legacy_url('uploads_images/les_signes_parmi_nous.jpg') }}"),
                url("{{ legacy_url('uploads_images/colonel_chabert.jpg') }}"),
                url("{{ legacy_url('uploads_images/rene_boylesve.jpg') }}"),
                url("{{ legacy_url('uploads_images/sarrasine_couv.jpeg') }}"),
                url("{{ legacy_url('uploads_images/mysteres_de_marseille_couv.jpeg') }}");
            background-repeat: repeat, repeat, repeat, repeat, repeat, repeat, repeat, repeat, repeat;
            background-size: auto, 220px auto, 200px auto, 240px auto, 210px auto, 190px auto, 230px auto, 205px auto, 215px auto;
            background-position: center, 0 0, 120px 40px, 40px 140px, 180px 220px, 80px 260px, 200px 320px, 30px 360px, 140px 420px;
            background-attachment: scroll, fixed, fixed, fixed, fixed, fixed, fixed, fixed, fixed;
        }
        .login-page main {
            background: transparent;
        }
        .login-page #admin-sites-menu {
            font-size: 1rem;
        }
        .login-page .admin-user-menu {
            font-size: 0.95rem;
        }
        .admin-user-toggle {
            border: 1px solid #ced4da;
            color: #495057;
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.65rem;
            font-size: 0.875rem;
            line-height: 1.1;
            text-decoration: none;
            border-radius: 0.375rem;
            background: #fff;
            box-shadow: none;
            transition: background-color 120ms ease, border-color 120ms ease, color 120ms ease;
        }
        .admin-user-toggle:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
            color: #343a40;
        }
        .admin-chrome {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.9rem;
            width: 100%;
        }
        .admin-chrome--embedded {
            justify-content: space-between;
        }
        .admin-embedded-title {
            flex: 0 0 auto;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: #495057;
            white-space: nowrap;
        }
        .admin-chrome-actions {
            margin-left: auto;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .admin-brand {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .admin-brand a {
            display: inline-flex;
            align-items: center;
            padding: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            text-decoration: none;
        }
        .admin-brand img {
            display: block;
            height: 26px;
            width: auto;
            filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.35));
        }
        .admin-brand-logo {
            display: block;
            height: 30px;
            width: auto;
            max-width: min(17rem, 42vw);
            filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.28));
        }
        .admin-wordmark {
            display: inline-flex;
            align-items: center;
            margin: 0;
            font-family: var(--font-serif);
            font-size: clamp(1.35rem, 1.8vw, 1.8rem);
            font-weight: 700;
            font-style: normal;
            letter-spacing: 0.18em;
            line-height: 1;
            color: #495057;
            text-transform: uppercase;
        }
        .admin-wordmark__ghost {
            display: none;
        }
        .admin-wordmark__core {
            display: inline;
        }
        .admin-brand-text {
            font-family: var(--font-serif);
            font-size: 1.2rem;
            font-weight: 600;
            font-variant-caps: normal;
            letter-spacing: 0.02em;
            color: #ffffff;
            line-height: 1;
            display: inline-block;
            white-space: nowrap;
        }
        .admin-brand-role {
            display: inline-flex;
            align-items: center;
            margin-left: 0.55rem;
            padding: 0.18rem 0.55rem 0.2rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
            font-family: var(--font-sans);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .admin-user-toggle:focus {
            box-shadow: none;
        }
        .admin-user-toggle:focus-visible {
            outline: 2px solid rgba(117, 107, 94, 0.5);
            outline-offset: 2px;
        }
        .admin-user-toggle.dropdown-toggle::after {
            margin-left: 0.55rem;
            border-top: 0.42em solid;
            border-right: 0.34em solid transparent;
            border-left: 0.34em solid transparent;
            vertical-align: 0.12em;
            opacity: 0.9;
        }
        .admin-user-menu {
            font-size: 0.85rem;
            border-radius: 14px;
            border: 1px solid rgba(0, 0, 0, 0.12);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        .admin-user-menu .dropdown-item {
            padding: 0.45rem 0.95rem;
        }
        .admin-user-menu .dropdown-divider {
            margin: 0.35rem 0;
        }
        .admin-user-menu .dropdown-item:hover,
        .admin-user-menu .dropdown-item:focus {
            background-color: rgba(0, 0, 0, 0.08);
        }
        .admin-public-menu {
            width: max-content;
            min-width: 30rem;
            max-width: none;
            max-height: none;
            overflow: visible;
        }
        .admin-public-menu-section {
            padding: 0.15rem 0;
        }
        .admin-public-menu-heading {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.95rem 0.3rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .admin-public-menu-heading-link {
            padding: 0;
            text-decoration: none;
            color: inherit;
        }
        .admin-public-menu-heading-link:hover,
        .admin-public-menu-heading-link:focus {
            background-color: rgba(0, 0, 0, 0.04);
        }
        .admin-public-menu-heading-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.05rem;
            height: 1.05rem;
            margin-right: 0.45rem;
            flex: 0 0 auto;
        }
        .admin-public-menu-heading-icon svg {
            display: block;
            width: 100%;
            height: 100%;
        }
        .admin-public-pair {
            position: relative;
        }
        .admin-public-pair-toggle {
            width: 100%;
            border: 0;
            background: transparent;
            text-align: left;
            padding: 0.45rem 0.95rem;
            font-size: 0.9rem;
            color: #3f3c36;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .admin-public-pair-toggle:hover,
        .admin-public-pair-toggle:focus {
            background-color: rgba(0, 0, 0, 0.06);
        }
        .admin-public-pair-toggle[aria-expanded="true"] {
            font-weight: 700;
        }
        .admin-public-pair-toggle::after {
            content: "›";
            font-size: 1rem;
            line-height: 1;
            opacity: 0.65;
        }
        .admin-public-comparisons {
            position: absolute;
            top: -0.35rem;
            left: calc(100% - 0.35rem);
            width: max-content;
            min-width: 28rem;
            max-width: none;
            max-height: none;
            overflow: visible;
            padding: 0.35rem 0;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.12);
            border-radius: 14px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            z-index: 1085;
        }
        .admin-public-comparisons[hidden] {
            display: none !important;
        }
        .admin-public-comparison-link {
            display: block;
            padding: 0.45rem 0.95rem;
            font-size: 0.84rem;
            color: #5b554c;
            text-decoration: none;
            white-space: normal;
        }
        .admin-public-comparison-link:hover,
        .admin-public-comparison-link:focus {
            background-color: rgba(0, 0, 0, 0.05);
            color: #2f2b26;
        }
        .admin-public-empty {
            padding: 0.35rem 0.95rem;
            font-size: 0.84rem;
            color: #7a7165;
        }
        .admin-public-comparisons-heading {
            padding: 0.15rem 0.95rem 0.35rem;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #8b8173;
        }
        @media (max-width: 991.98px) {
            .admin-public-menu {
                width: min(92vw, 30rem);
                min-width: 0;
                max-width: 92vw;
            }
            .admin-public-comparisons {
                position: static;
                left: auto;
                top: auto;
                width: auto;
                min-width: 0;
                max-width: none;
                max-height: none;
                margin: 0 0.6rem 0.45rem;
                border-radius: 12px;
            }
        }
        .system-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            display: inline-block;
            margin-left: 6px;
            background: #adb5bd;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.12);
            vertical-align: middle;
        }
        .system-status-dot--ok {
            background: #28a745;
        }
        .system-status-dot--degraded {
            background: #f0ad4e;
        }
        .system-status-dot--fail {
            background: #dc3545;
        }
        .admin-main-page footer {
            display: block;
        }
        .admin-main-page header {
            margin-bottom: 1rem !important;
        }
        .admin-main-page .admin-banner-shell {
            border-bottom: none;
        }
        .admin-main-page .admin-banner {
            background-image: none;
            box-shadow: none;
        }
        .admin-main-page header .admin-banner {
            padding-top: 1rem !important;
            padding-bottom: 1rem !important;
        }
        .admin-main-page .admin-brand a {
            background: transparent;
            box-shadow: none;
        }
        .admin-main-page .admin-brand-text {
            font-size: 1.08rem;
        }
        .admin-main-page .admin-brand-logo {
            height: 28px;
            max-width: min(15rem, 38vw);
        }
        .admin-main-page .admin-wordmark {
            font-size: clamp(1.25rem, 1.7vw, 1.6rem);
        }
        .admin-main-page .admin-user-toggle {
            background: #fff;
            box-shadow: none;
        }
        .admin-main-page .admin-user-toggle:hover {
            background: #f8f9fa;
            box-shadow: none;
        }
        .admin-main-page main {
            padding-top: 0.15rem;
        }
        .admin-main-page footer {
            margin-top: 1.25rem !important;
        }
        .admin-main-page footer .admin-banner {
            background-image: none;
            background-color: rgba(111, 109, 106, 0.14) !important;
            color: #6a6157 !important;
            box-shadow: none;
            border-top: 1px solid rgba(117, 107, 94, 0.12);
        }
        .admin-main-page footer .admin-page-shell {
            font-size: 0.78rem;
            letter-spacing: 0.03em;
        }
        .admin-loading-overlay {
            position: fixed;
            inset: 0;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            z-index: 2000;
        }
        .admin-loading header,
        .admin-loading main,
        .admin-loading footer {
            visibility: hidden;
        }
        .admin-loading .admin-loading-overlay {
            opacity: 1;
            pointer-events: auto;
        }
        .admin-loading .collapse,
        .admin-loading .collapsing {
            transition: none !important;
        }
        .admin-health header {
            margin-bottom: 0.5rem;
        }
        .admin-health main {
            margin-top: 0 !important;
        }
        .health-status-badge {
            font-size: 0.7rem;
            line-height: 1;
            padding: 0.3rem 0.5rem;
        }
        .admin-health .admin-banner-shell {
            margin-bottom: 0.5rem !important;
        }
        /* Legacy public site skin (toggle with LEGACY_SKIN=false or remove body class). */
        body.legacy-skin {
            --page-max-width: 1360px;
            --page-gutter: 24px;
            background-color: #f5f2ec;
            background-image:
                linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(245, 242, 236, 0.9));
            background-size: auto;
            background-position: 0 0;
            background-repeat: repeat;
            background-attachment: fixed;
            color: #2d2b27;
        }
        body.legacy-skin .admin-page-shell {
            max-width: var(--page-max-width);
            width: 100%;
            margin-left: auto;
            margin-right: auto;
            padding-left: var(--page-gutter);
            padding-right: var(--page-gutter);
        }
        body.legacy-skin .card {
            background: rgba(255, 255, 255, 0.92);
            border-color: #d6d0c6;
            box-shadow: 0 8px 20px rgba(30, 26, 20, 0.08);
        }
        body.legacy-skin .card-header {
            background: linear-gradient(180deg, #f7f5f1 0%, #ece7df 100%);
            color: #3f3b35;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            letter-spacing: 0.02em;
        }
        body.legacy-skin .table {
            background: rgba(255, 255, 255, 0.96);
        }
        body.legacy-skin .table th {
            background: #f3f0ea;
            color: #3d3a34;
        }
        body.legacy-skin .form-control,
        body.legacy-skin .form-select,
        body.legacy-skin .input-group-text {
            background: #fdfcf9;
            border-color: #cfc7bc;
            color: #3a3833;
        }
        body.legacy-skin .btn-outline-secondary {
            border-color: #7f7a71;
            color: #4a4741;
        }
        body.legacy-skin .admin-banner-shell {
            border-bottom-color: rgba(199, 186, 160, 0.75);
        }
        body.legacy-skin .admin-banner-shell {
            border-bottom: none;
        }
        @media (max-width: 767.98px) {
            .admin-brand a {
                padding: 0;
            }
            .admin-wordmark {
                font-size: clamp(1.1rem, 6vw, 1.45rem);
                letter-spacing: 0.14em;
            }
            .admin-embedded-title {
                font-size: 0.88rem;
                letter-spacing: 0.16em;
            }
        }
        @media (max-width: 991.98px) {
            .admin-chrome {
                flex-direction: column;
                align-items: stretch;
            }
            .admin-chrome-actions {
                justify-content: flex-start;
                margin-left: 0;
            }
        }
                </style>
</head>
@php
    $bodyClassValue = trim($__env->yieldContent('body-class'));
    $isAdminMainPage = str_contains($bodyClassValue, 'admin-main-page');
    $isLoginPage = str_contains($bodyClassValue, 'login-page');
    $useEmbeddedChrome = $isLoginPage
        || request()->routeIs('version.editor')
        || request()->routeIs('comparison.editor')
        || request()->routeIs('admin.users.index');
@endphp
<body class="d-flex flex-column min-vh-100 @yield('body-class') {{ $legacySkin ? 'legacy-skin' : '' }}">
    @unless($isAdminMainPage)
        <header class="mb-4 admin-banner-shell">
            <div class="admin-banner py-4 text-white shadow-sm" style="{{ $adminBannerStyle }}">
                <div class="container admin-page-shell">
                    @include('components.admin.chrome_controls', ['embedded' => $useEmbeddedChrome])
                </div>
            </div>
        </header>
    @endunless

    <main class="flex-grow-1 d-flex flex-column @yield('main-class')">
        <div class="container-fluid admin-page-shell">
            @yield('content')
        </div>
    </main>
    <footer class="mt-4">
        <div class="admin-banner py-2 text-white shadow-sm" style="{{ $adminBannerStyle }}">
            <div class="container admin-page-shell text-center small">
                Variance — Atelier éditorial
            </div>
        </div>
    </footer>
    <div class="admin-loading-overlay" aria-hidden="true">
        <div class="text-center text-muted">
            <div class="spinner-border" role="status" aria-hidden="true"></div>
            <div class="mt-2 small">Chargement…</div>
        </div>
    </div>

    <!-- Bootstrap JavaScript Bundle with Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Page-specific JS -->
    @stack('scripts')

    <script>
	        (function () {
	            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
	            tooltipTriggerList.forEach((tooltipTriggerEl) => {
	                new bootstrap.Tooltip(tooltipTriggerEl);
	            });
	        })();

            (function () {
                const HISTORY_KEY = 'variance:history:v1';
                const listEl = document.getElementById('admin-history-list');

                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');

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

                const buildSelectUrl = (entry) => {
                    const authorSlug = String(entry?.authorSlug ?? '').trim();
                    const workSlug = String(entry?.workSlug ?? '').trim();
                    if (!authorSlug || !workSlug) return null;
                    const path = `/select/${encodeURIComponent(authorSlug)}/${encodeURIComponent(workSlug)}`;
                    return typeof window.withBasePath === 'function' ? window.withBasePath(path) : path;
                };

                const render = () => {
                    if (!listEl) return;
                    const items = readHistory().filter(Boolean);
                    if (items.length === 0) {
                        listEl.innerHTML = '<li><span class="dropdown-item-text text-muted small">Aucun historique</span></li>';
                        return;
                    }

                    const rows = items.slice(0, 12).map((entry) => {
                        const authorId = String(entry?.authorId ?? '').trim();
                        const workId = String(entry?.workId ?? '').trim();
                        const authorLabel = entry?.authorLabel || 'Auteur';
                        const workLabel = entry?.workLabel || 'Œuvre';
                        const href = buildSelectUrl(entry) || (typeof window.withBasePath === 'function' ? window.withBasePath('/') : '/');

                        return `
                            <li>
                                <a class="dropdown-item"
                                   href="${escapeHtml(href)}"
                                   target="_blank"
                                   rel="noopener"
                                   data-history-author-id="${escapeHtml(authorId)}"
                                   data-history-work-id="${escapeHtml(workId)}">
                                    <div class="fw-semibold">${escapeHtml(workLabel)}</div>
                                    <div class="small text-muted">${escapeHtml(authorLabel)}</div>
                                </a>
                            </li>
                        `;
                    }).join('');
                    listEl.innerHTML = rows;
                };

                document.addEventListener('DOMContentLoaded', render);
                document.addEventListener('workSelected', render);

                document.addEventListener('click', (event) => {
                    const clearBtn = event.target?.closest?.('#admin-history-clear');
                    if (clearBtn) {
                        event.preventDefault();
                        try { localStorage.removeItem(HISTORY_KEY); } catch (err) {}
                        render();
                        return;
                    }
                });
            })();
	
	        (function () {
	            const buildLabel = (base, count) => {
	                const total = Number(count) || 0;
	                const suffix = total === 1 ? ' comparaison' : ' comparaisons';
                return `${base} (${total}${suffix})`;
            };

            const updateCounts = async () => {
                const prodLink = document.querySelector('[data-site-scope="prod"]');
                const devLink = document.querySelector('[data-site-scope="dev"]');
                if (!prodLink || !devLink) {
                    return;
                }
                const prodLabel = prodLink.dataset.siteLabel || 'Site public';
                const devLabel = devLink.dataset.siteLabel || 'Site de travail';

                try {
                    const res = await fetch(withBasePath('/api/comparisons/publication-counts'), {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) {
                        return;
                    }
                    const data = await res.json();
                    prodLink.textContent = buildLabel(prodLabel, data.prod);
                    devLink.textContent = buildLabel(devLabel, data.dev);
                } catch (err) {
                    console.warn('Could not refresh publication counts', err);
                }
            };

            document.addEventListener('DOMContentLoaded', updateCounts);
            document.addEventListener('publicationCountsChanged', updateCounts);
        })();

        (function () {
            const menu = document.querySelector('.admin-public-menu');
            if (!menu) return;

            const scopeContainers = {
                prod: menu.querySelector('[data-public-scope="prod"]'),
                dev: menu.querySelector('[data-public-scope="dev"]'),
            };

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');

            const closeSubmenus = (scope) => {
                const root = scope ? scopeContainers[scope] : menu;
                if (!root) return;
                root.querySelectorAll('[data-public-pair-toggle]').forEach((toggle) => {
                    toggle.setAttribute('aria-expanded', 'false');
                });
                root.querySelectorAll('.admin-public-comparisons').forEach((panel) => {
                    panel.hidden = true;
                });
            };

            const renderScope = (scope, items) => {
                const container = scopeContainers[scope];
                if (!container) return;
                if (!Array.isArray(items) || items.length === 0) {
                    container.innerHTML = '<div class="admin-public-empty">Aucune comparaison publiée.</div>';
                    return;
                }

                container.innerHTML = items.map((item) => {
                    const comparisons = Array.isArray(item.comparisons) ? item.comparisons : [];
                    const comparisonsMarkup = comparisons.length
                        ? comparisons.map((comparison) =>
                            `<a class="admin-public-comparison-link" href="${escapeHtml(comparison.url)}" target="_blank" rel="noopener">${escapeHtml(comparison.label)}</a>`
                        ).join('')
                        : '<div class="admin-public-empty">Aucune comparaison publiée.</div>';

                    return `
                        <div class="admin-public-pair">
                            <button type="button"
                                    class="admin-public-pair-toggle"
                                    data-public-pair-toggle
                                    aria-expanded="false">
                                ${escapeHtml(item.pair_label)}
                            </button>
                            <div class="admin-public-comparisons" hidden>
                                <div class="admin-public-comparisons-heading">${escapeHtml(item.pair_label)}</div>
                                ${comparisonsMarkup}
                            </div>
                        </div>
                    `;
                }).join('');
            };

            const loadMenu = async () => {
                try {
                    const res = await fetch(withBasePath('/api/comparisons/public-menu'), {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const data = await res.json();
                    renderScope('prod', data?.prod || []);
                    renderScope('dev', data?.dev || []);
                } catch (err) {
                    Object.values(scopeContainers).forEach((container) => {
                        if (container) {
                            container.innerHTML = '<div class="admin-public-empty">Menu indisponible.</div>';
                        }
                    });
                }
            };

            menu.addEventListener('click', (event) => {
                const toggle = event.target.closest('[data-public-pair-toggle]');
                if (toggle) {
                    event.preventDefault();
                    const content = toggle.nextElementSibling;
                    const expanded = toggle.getAttribute('aria-expanded') === 'true';
                    closeSubmenus();
                    if (!expanded) {
                        toggle.setAttribute('aria-expanded', 'true');
                        if (content) {
                            content.hidden = false;
                        }
                    }
                    return;
                }

                if (!event.target.closest('.admin-public-comparisons')) {
                    closeSubmenus();
                }
            });

            menu.addEventListener('mouseover', (event) => {
                const toggle = event.target.closest('[data-public-pair-toggle]');
                if (!toggle) return;
                const content = toggle.nextElementSibling;
                if (!content) return;
                closeSubmenus();
                toggle.setAttribute('aria-expanded', 'true');
                content.hidden = false;
            });

            menu.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeSubmenus();
                }
            });

            document.addEventListener('click', (event) => {
                if (!menu.contains(event.target)) {
                    closeSubmenus();
                }
            });

            document.getElementById('admin-public-sites-menu')?.addEventListener('hidden.bs.dropdown', () => {
                closeSubmenus();
            });

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', loadMenu);
            } else {
                loadMenu();
            }
        })();

        (function () {
            const dot = document.getElementById('system-status-dot');
            if (!dot) return;

            const setStatus = (status, title) => {
                dot.classList.remove('system-status-dot--ok', 'system-status-dot--degraded', 'system-status-dot--fail');
                if (status === 'ok') {
                    dot.classList.add('system-status-dot--ok');
                } else if (status === 'degraded') {
                    dot.classList.add('system-status-dot--degraded');
                } else if (status === 'fail') {
                    dot.classList.add('system-status-dot--fail');
                } else {
                    dot.classList.add('system-status-dot--degraded');
                }
                if (title) {
                    dot.setAttribute('title', title);
                }
            };

            const refreshStatus = async () => {
                try {
                    const res = await fetch(withBasePath('/health'), { headers: { 'Accept': 'application/json' } });
                    let data = null;
                    try {
                        data = await res.json();
                    } catch (_) {
                        data = null;
                    }
                    const status = data?.status || (res.ok ? 'degraded' : 'fail');
                    const label = status === 'ok'
                        ? 'État du système : OK'
                        : (status === 'degraded' ? 'État du système : Avertissement' : 'État du système : Critique');
                    setStatus(status, label);
                } catch (err) {
                    setStatus('fail', 'État du système indisponible');
                }
            };

            document.addEventListener('DOMContentLoaded', refreshStatus);
        })();
    </script>
</body>
</html>
