@php
    $embedded = $embedded ?? false;
    $mediteStatusUrl = $mediteStatusUrl ?? env('MEDITE_STATUS_URL', '/medite/');
    $publicSiteUrl = legacy_url();
    $devSiteUrl = legacy_url('dev');
@endphp

<div class="admin-chrome{{ $embedded ? ' admin-chrome--embedded' : '' }}">
    <div class="d-flex align-items-center gap-2">
        @if($embedded)
            <div class="admin-embedded-title" aria-label="Variance">VARIANCE</div>
        @else
            <div class="admin-brand">
                <a href="{{ rtrim(admin_path(), '/') . '/' }}">
                    <span class="admin-wordmark" aria-label="Variance">
                        <span class="admin-wordmark__ghost" aria-hidden="true">Variance</span>
                        <span class="admin-wordmark__core">Variance</span>
                    </span>
                </a>
            </div>
        @endif

        <div class="dropdown">
            <button class="admin-user-toggle dropdown-toggle"
                    type="button"
                    id="admin-public-sites-menu"
                    data-bs-toggle="dropdown"
                    aria-expanded="false">
                Site public
            </button>
            <div class="dropdown-menu py-1 admin-user-menu admin-public-menu" aria-labelledby="admin-public-sites-menu">
                <div class="admin-public-menu-section">
                    <a class="dropdown-item admin-public-menu-heading-link" href="{{ $devSiteUrl }}" target="_blank" rel="noopener">
                        <span class="admin-public-menu-heading">
                            <span class="admin-public-menu-heading-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                    <defs>
                                        <linearGradient id="publicMenuGlobeGradientDev" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" stop-color="#66b7ff" />
                                            <stop offset="100%" stop-color="#2f7fd8" />
                                        </linearGradient>
                                    </defs>
                                    <circle cx="12" cy="12" r="9" fill="url(#publicMenuGlobeGradientDev)" />
                                    <path d="M12 3c2.1 2.2 3.3 5.5 3.3 9S14.1 18.8 12 21c-2.1-2.2-3.3-5.5-3.3-9S9.9 5.2 12 3Z" fill="none" stroke="#ffffff" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M4.3 9.2h15.4M4.3 14.8h15.4" fill="none" stroke="#ffffff" stroke-width="1.3" stroke-linecap="round"/>
                                    <circle cx="12" cy="12" r="9" fill="none" stroke="rgba(39, 77, 128, 0.35)" stroke-width="1" />
                                </svg>
                            </span>
                            Site de travail ({{ $devSiteUrl }})
                        </span>
                    </a>
                    <div class="admin-public-menu-list" data-public-scope="dev">
                        <div class="admin-public-empty">Chargement…</div>
                    </div>
                </div>
                <div><hr class="dropdown-divider"></div>
                <div class="admin-public-menu-section">
                    <a class="dropdown-item admin-public-menu-heading-link" href="{{ $publicSiteUrl }}" target="_blank" rel="noopener">
                        <span class="admin-public-menu-heading">
                            <span class="admin-public-menu-heading-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                    <defs>
                                        <linearGradient id="publicMenuGlobeGradientProd" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" stop-color="#7fd0ff" />
                                            <stop offset="100%" stop-color="#3f8de3" />
                                        </linearGradient>
                                    </defs>
                                    <circle cx="12" cy="12" r="9" fill="url(#publicMenuGlobeGradientProd)" />
                                    <path d="M12 3c2.1 2.2 3.3 5.5 3.3 9S14.1 18.8 12 21c-2.1-2.2-3.3-5.5-3.3-9S9.9 5.2 12 3Z" fill="none" stroke="#ffffff" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M4.3 9.2h15.4M4.3 14.8h15.4" fill="none" stroke="#ffffff" stroke-width="1.3" stroke-linecap="round"/>
                                    <circle cx="12" cy="12" r="9" fill="none" stroke="rgba(39, 77, 128, 0.35)" stroke-width="1" />
                                </svg>
                            </span>
                            Site public ({{ $publicSiteUrl }})
                        </span>
                    </a>
                    <div class="admin-public-menu-list" data-public-scope="prod">
                        <div class="admin-public-empty">Chargement…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 admin-chrome-actions">
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
