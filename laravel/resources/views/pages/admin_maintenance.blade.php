<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Variance — Maintenance</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inconsolata:wght@400;500;600;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-serif: "Open Sans", "Segoe UI", sans-serif;
            --font-sans: "Inconsolata", "Courier New", monospace;
            --paper: #f5f2ed;
            --card: rgba(255, 255, 255, 0.92);
            --ink: #3c3a36;
            --muted: #6f6d6a;
            --line: rgba(117, 107, 94, 0.18);
            --accent: #8f6b3a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.8), rgba(255,255,255,0.9)),
                radial-gradient(circle at top left, rgba(210, 165, 74, 0.16), transparent 28%),
                linear-gradient(135deg, #f8f5ef, #efebe4);
            color: var(--ink);
            font-family: var(--font-sans);
        }
        .maintenance-card {
            width: min(760px, 100%);
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: 0 28px 70px rgba(72, 63, 53, 0.12);
            overflow: hidden;
        }
        .maintenance-header {
            padding: 1.4rem 1.6rem 1.1rem;
            border-bottom: 1px solid var(--line);
            background: rgba(111, 109, 106, 0.08);
        }
        .maintenance-brand {
            margin: 0;
            font-family: var(--font-serif);
            font-size: 0.98rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #495057;
        }
        .maintenance-body {
            padding: 2rem 1.6rem 1.8rem;
        }
        .maintenance-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 1rem;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            border: 1px solid rgba(143, 107, 58, 0.24);
            background: rgba(143, 107, 58, 0.1);
            color: var(--accent);
            font-size: 0.92rem;
            font-weight: 600;
        }
        h1 {
            margin: 0 0 0.7rem;
            font-family: var(--font-serif);
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            line-height: 1.15;
        }
        p {
            margin: 0;
            font-size: 1.08rem;
            line-height: 1.7;
            color: var(--muted);
        }
        .maintenance-meta {
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
            display: grid;
            gap: 0.5rem;
            font-size: 0.98rem;
            color: var(--muted);
        }
        .maintenance-meta strong {
            color: var(--ink);
        }
        .maintenance-actions {
            margin-top: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
        }
        .maintenance-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.8rem;
            padding: 0 1rem;
            border-radius: 0.7rem;
            border: 1px solid var(--line);
            text-decoration: none;
            color: var(--ink);
            background: #fff;
        }
        .maintenance-link--primary {
            border-color: rgba(143, 107, 58, 0.3);
            background: rgba(143, 107, 58, 0.08);
            color: #6e5330;
        }
    </style>
</head>
<body>
    @php
        $state = $maintenanceState ?? ['message' => null, 'until' => null];
        $until = !empty($state['until']) ? \Illuminate\Support\Carbon::parse($state['until'])->setTimezone('Europe/Zurich') : null;
    @endphp
    <main class="maintenance-card">
        <header class="maintenance-header">
            <p class="maintenance-brand">Variance — Atelier éditorial</p>
        </header>
        <section class="maintenance-body">
            <div class="maintenance-tag">Maintenance planifiée</div>
            <h1>Interface d’édition momentanément indisponible</h1>
            <p>{{ $state['message'] ?? 'Maintenance en cours. L’atelier éditorial Variance sera de retour dans quelques minutes.' }}</p>

            <div class="maintenance-meta">
                <div><strong>Portée :</strong> seule l’interface Laravel d’édition est suspendue. Le site public Variance reste accessible.</div>
                @if($until)
                    <div><strong>Retour estimé :</strong> {{ $until->format('d/m/Y H:i') }} (Europe/Zurich)</div>
                @endif
            </div>

            <div class="maintenance-actions">
                <a class="maintenance-link maintenance-link--primary" href="{{ legacy_url() }}">Aller au site public</a>
                <a class="maintenance-link" href="{{ admin_path('health') }}">État technique</a>
            </div>
        </section>
    </main>
</body>
</html>
