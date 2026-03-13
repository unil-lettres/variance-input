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
        $adminBannerColor = '#6f6d6a';
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
            border: 0;
            color: #fff;
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.65rem;
            font-size: 0.875rem;
            line-height: 1.1;
            text-decoration: none;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.3);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
            transition: background-color 120ms ease, box-shadow 120ms ease;
        }
        .admin-user-toggle:hover {
            background: rgba(0, 0, 0, 0.42);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
            color: #fff;
        }
        .admin-brand {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .admin-brand a {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.6rem;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.42);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
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
            outline: 2px solid rgba(255, 255, 255, 0.75);
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
            --banner-ink: rgba(0, 0, 0, 0.22);
            --banner-wash: rgba(255, 255, 255, 0.1);
            background-image:
                radial-gradient(150px 110px at 10% 65%, var(--banner-ink), rgba(0, 0, 0, 0) 60%),
                radial-gradient(220px 170px at 24% 28%, rgba(0, 0, 0, 0.18), rgba(0, 0, 0, 0) 70%),
                linear-gradient(180deg, var(--banner-wash), rgba(0, 0, 0, 0.08)),
                repeating-linear-gradient(
                    45deg,
                    rgba(255, 255, 255, 0.025) 0 2px,
                    rgba(0, 0, 0, 0.02) 2px 4px
                );
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.08);
        }
        .admin-main-page header .admin-banner {
            padding-top: 1rem !important;
            padding-bottom: 1rem !important;
        }
        .admin-main-page .admin-brand a {
            background: rgba(0, 0, 0, 0.24);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
        }
        .admin-main-page .admin-brand-text {
            font-size: 1.08rem;
        }
        .admin-main-page .admin-brand-logo {
            height: 28px;
            max-width: min(15rem, 38vw);
        }
        .admin-main-page .admin-brand-role {
            background: rgba(255, 255, 255, 0.1);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
            font-size: 0.72rem;
        }
        .admin-main-page .admin-user-toggle {
            background: rgba(0, 0, 0, 0.2);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1);
        }
        .admin-main-page .admin-user-toggle:hover {
            background: rgba(0, 0, 0, 0.3);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.14);
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
            --page-max-width: 1120px;
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
                </style>
</head>
<body class="d-flex flex-column min-vh-100 @yield('body-class') {{ $legacySkin ? 'legacy-skin' : '' }}">
    <header class="mb-4 admin-banner-shell">
        <div class="admin-banner py-4 text-white shadow-sm" style="{{ $adminBannerStyle }}">
            <div class="container admin-page-shell d-flex justify-content-between align-items-center">
                <div class="admin-brand">
                    <a href="{{ rtrim(admin_path(), '/') . '/' }}">
                        <img class="admin-brand-logo" src="{{ legacy_url('img/full_logo_white.svg') }}" alt="Variance">
                    </a>
                </div>

                <div class="d-flex align-items-center gap-2">
                    @auth
                        @if(Auth::user()->is_admin)
                            <div class="dropdown">
                                <button class="admin-user-toggle dropdown-toggle"
                                        type="button"
                                        id="admin-tasks-menu"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                    Système <span id="system-status-dot" class="system-status-dot" aria-hidden="true"></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end py-1 admin-user-menu" aria-labelledby="admin-tasks-menu">
                                    <li>
                                        <a class="dropdown-item" href="{{ $mediteStatusUrl }}" target="_blank" rel="noopener">Traitements Medite</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ admin_path('tasks') }}">File Laravel</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ admin_path('health/report') }}">État système</a>
                                    </li>
                                </ul>
                            </div>
                        @endif
	                    @endauth
	                    <div class="dropdown">
	                        <button class="admin-user-toggle dropdown-toggle"
	                                type="button"
	                                id="admin-sites-menu"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">
                            Aller à
		                        </button>
		                        <ul class="dropdown-menu dropdown-menu-end py-1 admin-user-menu" aria-labelledby="admin-sites-menu">
                                    <li>
                                        <span class="dropdown-item-text text-muted small">Site public</span>
                                    </li>
		                            <li>
		                                <a class="dropdown-item" href="{{ legacy_url() }}" data-site-scope="prod" data-site-label="Site public">Site public</a>
		                            </li>
		                            <li>
		                                <a class="dropdown-item" href="{{ legacy_url('dev') }}" data-site-scope="dev" data-site-label="Site de travail">Site de travail</a>
		                            </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <span class="dropdown-item-text text-muted small">Historique</span>
                                </li>
                                <li class="px-2 pb-1" id="admin-history-container">
                                    <ul class="list-unstyled mb-1" id="admin-history-list">
                                        <li>
                                            <span class="dropdown-item-text text-muted small">Aucun historique</span>
                                        </li>
                                    </ul>
                                    <button type="button" class="dropdown-item text-danger" id="admin-history-clear">Effacer l'historique</button>
                                </li>
	                        </ul>
	                    </div>

                    @auth
                        <div class="dropdown">
                            <button class="admin-user-toggle dropdown-toggle"
                                    type="button"
                                    id="admin-user-menu"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                Réglages
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end py-1 admin-user-menu" aria-labelledby="admin-user-menu">
                                @php
                                    $roleLabel = Auth::user()->is_admin ? 'Admin' : 'Utilisateur';
                                @endphp
                                <li>
                                    <span class="dropdown-item-text">
                                        <span class="fw-semibold">{{ Auth::user()->display_name }}</span>
                                        <span class="text-muted">· {{ $roleLabel }}</span>
                                    </span>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                @if(Auth::user()->is_admin)
                                    <li>
                                        <a class="dropdown-item" href="{{ admin_path('users') }}">Gérer les utilisateurs</a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                @endif
                                <li>
                                    <a class="dropdown-item" href="{{ admin_path('account/password') }}">Changer mon mot de passe</a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ admin_path('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item">Se déconnecter</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </header>

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

                    const link = event.target?.closest?.('a[data-history-author-id][data-history-work-id]');
                    if (!link) return;
                    if (!document.getElementById('admin-main')) return; // only soft-select on main page
                    if (typeof window.varianceSelectWork !== 'function') return;

                    const authorId = link.getAttribute('data-history-author-id');
                    const workId = link.getAttribute('data-history-work-id');
                    if (!authorId || !workId) return;

                    event.preventDefault();
                    window.varianceSelectWork(authorId, workId);

                    // Close dropdown after selection
                    const dropdown = link.closest('.dropdown');
                    const toggle = dropdown?.querySelector?.('[data-bs-toggle="dropdown"]');
                    if (toggle && window.bootstrap && bootstrap.Dropdown) {
                        bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
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
