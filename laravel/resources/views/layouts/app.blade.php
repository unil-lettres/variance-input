<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Variance')</title>

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
        $mediteStatusUrl = env('MEDITE_STATUS_URL', 'http://localhost:5000/');
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
            background: transparent;
            color: #fff;
            padding: 0 0.25rem;
            font-size: 0.875rem;
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
            height: 22px;
            width: auto;
            filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.35));
        }
        .admin-brand-text {
            font-family: var(--font-serif);
            font-size: 1.2rem;
            font-weight: 500;
            letter-spacing: 0.08em;
            color: #ffffff;
            line-height: 1;
            display: inline-block;
        }
        .admin-user-toggle:focus {
            box-shadow: none;
        }
        .admin-user-menu {
            font-size: 0.85rem;
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
                    <a href="{{ admin_path() }}">
                        <span class="admin-brand-text">variance/admin</span>
                    </a>
                </div>

                <div class="d-flex align-items-center gap-3">
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
                                        <a class="dropdown-item" href="{{ $mediteStatusUrl }}" target="_blank" rel="noopener">Tâches Medite</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ admin_path('tasks') }}">Tâches Laravel</a>
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
                            Sites
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end py-1 admin-user-menu" aria-labelledby="admin-sites-menu">
                            <li>
                                <a class="dropdown-item" href="{{ legacy_url() }}" data-site-scope="prod" data-site-label="Site public">Site public</a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ legacy_url('dev') }}" data-site-scope="dev" data-site-label="Site dev">Site dev</a>
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
                                {{ Auth::user()->display_name }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end py-1 admin-user-menu" aria-labelledby="admin-user-menu">
                                @if(Auth::user()->is_admin)
                                    <li>
                                        <span class="dropdown-item-text text-muted fst-italic">Admin</span>
                                    </li>
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
            <div class="container admin-page-shell text-end small">
                © 2026 Variance — Tous droits réservés
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
                const devLabel = devLink.dataset.siteLabel || 'Site dev';

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
                    if (!res.ok) {
                        setStatus('fail', 'État du système indisponible');
                        return;
                    }
                    const data = await res.json();
                    const status = data?.status || 'unknown';
                    const label = status === 'ok'
                        ? 'État du système : OK'
                        : (status === 'degraded' ? 'État du système : Dégradé' : 'État du système : Problème');
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
