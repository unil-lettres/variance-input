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
    <style>
        .admin-banner {
            background-color: rgb(66, 71, 74);
            background-image: url("{{ asset('images/admin_banner.jpg') }}");
            background-repeat: repeat;
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
    </style>
</head>
<body>
    <header class="mb-4">
        <div class="admin-banner container py-3 d-flex justify-content-between align-items-center text-white shadow-sm">
            <a href="{{ admin_path() }}">
                <img src="{{ admin_asset('images/full_logo_white.svg') }}" alt="Variance Logo" style="height: 48px;">
            </a>

            @auth
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
                                <a class="dropdown-item" href="{{ legacy_url() }}">Site public</a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ legacy_url('dev') }}">Site dev</a>
                            </li>
                        </ul>
                    </div>

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
                                    <a class="dropdown-item" href="{{ admin_path('users') }}">Gérer les utilisateurs</a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><span class="dropdown-item-text text-muted fst-italic">Administrateur</span></li>
                            @endif
                            <li>
                                <form action="{{ admin_path('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Se déconnecter</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            @endauth
        </div>
    </header>

    <main class="container-fluid">
        @yield('content')
    </main>
    <footer class="mt-4">
        <div class="admin-banner container py-2 text-center small text-white shadow-sm">
            &copy; UNIL/SIER 2026 · Laravel {{ app()->version() }}
        </div>
    </footer>

    <!-- Bootstrap JavaScript Bundle with Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Page-specific JS -->
    @stack('scripts')
</body>
</html>
