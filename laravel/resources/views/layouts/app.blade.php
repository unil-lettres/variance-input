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

    <!-- Custom Font -->
    <link href="https://fonts.googleapis.com/css2?family=Special+Elite&display=swap" rel="stylesheet">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $basePath = $appBasePath ?? admin_base_prefix();
        $baseUrl  = $appBaseUrl ?? rtrim(admin_url(), '/');
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
        $adminBannerColor = '#8a8f92';
        $adminBannerStyle = 'background-color: ' . $adminBannerColor . ';'
            . ' background-image: url("' . asset('images/admin_banner.jpg') . '");'
            . ' background-repeat: repeat;';
    @endphp
    <style>
        .admin-banner {
            background-color: {{ $adminBannerColor }};
            background-image: url("{{ asset('images/admin_banner.jpg') }}");
            background-repeat: repeat;
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
        .admin-main-page footer {
            display: none;
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
    </style>
</head>
<body class="d-flex flex-column min-vh-100 @yield('body-class')">
    <header class="mb-4">
        <div class="admin-banner container py-3 d-flex justify-content-between align-items-center text-white shadow-sm"
             style="{{ $adminBannerStyle }}">
            <a href="{{ admin_path() }}">
                <img src="{{ admin_asset('images/full_logo_white.svg') }}" alt="Variance Logo" style="height: 48px;">
            </a>

            <div class="d-flex align-items-center gap-3">
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
                                    <span class="dropdown-item-text text-muted fst-italic">Administrateur</span>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ admin_path('users') }}">Gérer les utilisateurs</a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            @endif
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
    </header>

    <main class="container-fluid flex-grow-1 d-flex flex-column @yield('main-class')">
        @yield('content')
    </main>
    <footer class="mt-4">
        <div class="admin-banner container py-2 text-center small text-white shadow-sm"
             style="{{ $adminBannerStyle }}">
            Variance-Input &copy; UNIL/SIER 2026 · Laravel {{ app()->version() }} · sier@unil.ch
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
    </script>
</body>
</html>
