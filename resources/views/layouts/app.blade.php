<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') · SecuLens</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    @stack('styles')
</head>

<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand">
                    <div class="brand-icon">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <div class="brand-text">
                        <h1 class="brand-title">SecuLens</h1>
                        <span class="brand-subtitle">Monitor de seguridad</span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}"
                            class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="bi bi-grid-1x2-fill nav-icon"></i>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('alerts.index') }}"
                            class="nav-link {{ request()->routeIs('alerts.*') ? 'active' : '' }}">
                            <i class="bi bi-exclamation-triangle-fill nav-icon"></i>
                            <span class="nav-text">Alertas</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('incidents.index') }}"
                            class="nav-link {{ request()->routeIs('incidents.*') ? 'active' : '' }}">
                            <i class="bi bi-clipboard-data-fill nav-icon"></i>
                            <span class="nav-text">Incidentes</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('events.index') }}"
                            class="nav-link {{ request()->routeIs('events.*') ? 'active' : '' }}">
                            <i class="bi bi-activity nav-icon"></i>
                            <span class="nav-text">Eventos</span>
                        </a>
                    </li>

                    {{-- Panel IDS con datos reales de la base (ruta Blade /ids) --}}
                    <li class="nav-item">
                        <a href="{{ route('ids.index') }}"
                            class="nav-link {{ request()->routeIs('ids.*') ? 'active' : '' }}">
                            <i class="bi bi-broadcast nav-icon"></i>
                            <span class="nav-text">Alertas IDS</span>
                        </a>
                    </li>

                    {{--
                        Panel IDS estatico (simulador en public/ids-panel): genera
                        trafico INVENTADO para demostraciones; los datos reales
                        estan en "Alertas IDS".
                        Se enlaza a index.html explicitamente: url('/ids-panel/')
                        elimina la barra final y rompia la carga de CSS/JS.
                    --}}
                    <li class="nav-item">
                        <a href="{{ asset('ids-panel/index.html') }}" class="nav-link">
                            <i class="bi bi-shield-exclamation nav-icon"></i>
                            <span class="nav-text">Simulador IDS</span>
                        </a>
                    </li>

                    <li class="nav-section">
                        <span class="nav-section-title">Análisis</span>
                    </li>

                    {{-- Informe imprimible por dia / semana / mes / año --}}
                    <li class="nav-item">
                        <a href="{{ route('reports.index') }}"
                            class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                            <i class="bi bi-file-earmark-bar-graph-fill nav-icon"></i>
                            <span class="nav-text">Informes</span>
                        </a>
                    </li>

                    {{-- Lista de IPs reincidentes / bloqueadas --}}
                    <li class="nav-item">
                        <a href="{{ route('watchlist.index') }}"
                            class="nav-link {{ request()->routeIs('watchlist.*') ? 'active' : '' }}">
                            <i class="bi bi-eye-fill nav-icon"></i>
                            <span class="nav-text">IPs vigiladas</span>
                        </a>
                    </li>

                    @if (auth()->check() && auth()->user()->role === \App\Enums\UserRole::ADMIN)
                        <li class="nav-section">
                            <span class="nav-section-title">Administración</span>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('audit.index') }}"
                                class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}">
                                <i class="bi bi-journal-bookmark-fill nav-icon"></i>
                                <span class="nav-text">Auditoría</span>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('users.index') }}"
                                class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                <i class="bi bi-people-fill nav-icon"></i>
                                <span class="nav-text">Usuarios</span>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('api-docs.index') }}"
                                class="nav-link {{ request()->routeIs('api-docs.*') ? 'active' : '' }}">
                                <i class="bi bi-code-square nav-icon"></i>
                                <span class="nav-text">Documentación API</span>
                            </a>
                        </li>
                    @endif
                </ul>
            </nav>

            <div class="sidebar-footer">
                @auth
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <div class="user-details">
                            <span class="user-name">{{ auth()->user()->name }}</span>
                            <span class="user-role">{{ auth()->user()->role?->label() ?? 'N/A' }}</span>
                        </div>
                    </div>
                    {{--
                        Antes era un <form method="POST"> con onsubmit inline. La CSP
                        (script-src 'self') bloquea los manejadores inline, asi que el
                        formulario hacia POST a "/" y devolvia 405. En modo single-user
                        no hay logout: basta un enlace al inicio.
                    --}}
                    <a href="{{ route('dashboard') }}" class="btn-logout" title="Inicio">
                        <i class="bi bi-house-door"></i>
                    </a>
                @endauth
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <header class="topbar">
                <div class="topbar-left">
                    <h2 class="page-title">@yield('page-title', 'Dashboard')</h2>
                    <span class="page-subtitle">@yield('page-subtitle', 'Vista general del sistema')</span>
                </div>
                <div class="topbar-right">
                    <div class="status-indicator">
                        <span class="status-dot online"></span>
                        <span class="status-text">Sistema operativo</span>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="content">
                @if (session('status'))
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill alert-icon"></i>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle-fill alert-icon"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @yield('content')
            </main>

            {{-- Pie de pagina comun a todas las vistas que usan este layout --}}
            <footer class="app-footer">
                <span>Creado por <strong>Daniel Alejandro Aceitón Sepúlveda</strong></span>
                <span class="app-footer-sep">·</span>
                <span>SecuLens &copy; {{ date('Y') }}</span>
            </footer>
        </div>
    </div>

    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>

</html>
