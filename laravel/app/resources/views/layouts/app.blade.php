<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Variance')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom Font -->
    <link href="https://fonts.googleapis.com/css2?family=Special+Elite&display=swap" rel="stylesheet">
</head>
<body>
    <header class="bg-light shadow-sm mb-4">
        <div class="container py-3 d-flex justify-content-between align-items-center">
            <h1 class="h4 mb-0" style="font-family: 'Special Elite', cursive; font-size: 3rem; letter-spacing: 2px; margin: 0;">
                variance
            </h1>

            @auth
                <div class="d-flex align-items-center">
                    <span class="me-3">{{ Auth::user()->name }}</span>
                    
                    @if(Auth::user()->is_admin)
                        <span class="badge bg-success me-3">Admin</span>
                    @endif

                    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Logout</button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    <main class="container">
        @yield('content')
    </main>

    <!-- Bootstrap JavaScript Bundle with Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Optional: Stack for Page-Specific Scripts -->
    @stack('scripts')
</body>
</html>
