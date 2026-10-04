{{--
    Panel IDS - Plataforma de Monitoreo.

    Datos: IdsWebController@index (tabla ids_alerts).
    Estilos: public/css/ids-panel.css
    Comportamiento: public/js/ids-panel.js

    IMPORTANTE: la cabecera Content-Security-Policy (SecurityHeaders) solo
    permite JavaScript desde archivos propios. Por eso aqui NO hay
    <script> en linea ni atributos onclick/onchange: el navegador los
    bloquearia y los filtros y el modal dejarian de funcionar.
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma de Monitoreo IDS - Panel de Alertas</title>

    {{-- Tipografias (permitidas en la CSP). Si no cargan, se usa la fuente del sistema. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    {{-- filemtime() en ?v= invalida la cache del navegador cuando cambia el archivo --}}
    <link rel="stylesheet" href="{{ asset('css/ids-panel.css') }}?v={{ filemtime(public_path('css/ids-panel.css')) }}">
</head>

{{--
    data-* del body: configuracion que lee ids-panel.js para el refresco en
    tiempo real (asi no hace falta un <script> en linea, prohibido por la CSP).
--}}
<body data-feed-url="{{ route('ids.feed') }}"
      data-last-id="{{ $lastId }}"
      data-range="{{ $range }}">
    {{-- ===== Barra superior ===== --}}
    <nav class="navbar">
        {{-- El logo es un enlace: al pulsarlo vuelve al dashboard principal --}}
        <a href="{{ route('dashboard') }}" class="navbar-brand" title="Volver al dashboard principal">
            <div class="brand-logo">IDS</div>
            <div class="brand-text">
                <div class="brand-name">Plataforma de Monitoreo IDS</div>
                <div class="brand-subtitle">Creado por Daniel Alejandro Aceitón Sepúlveda</div>
            </div>
        </a>

        <div class="navbar-center">
            <div class="status-indicator">
                <span class="status-dot"></span>
                <span>MONITOREO 24/7 ACTIVO</span>
            </div>
        </div>

        <div class="navbar-right">
            {{--
                Selector de rango temporal. Es un formulario GET normal: el JS lo
                envia al cambiar la opcion y el controlador filtra por ?range=.
                Antes el <select> no estaba conectado a nada.
            --}}
            <form method="GET" action="{{ route('ids.index') }}" class="range-form" id="rangeForm">
                <select class="time-selector" id="timeRange" name="range" aria-label="Rango temporal">
                    @foreach ([
                        'all' => 'Todo el historial',
                        '1h' => 'Última 1 hora',
                        '6h' => 'Últimas 6 horas',
                        '24h' => 'Últimas 24 horas',
                        '7d' => 'Últimos 7 días',
                        '30d' => 'Últimos 30 días',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected($range === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                {{-- Boton de respaldo por si el JS no carga --}}
                <noscript><button type="submit" class="action-btn">Aplicar</button></noscript>
            </form>

            <a href="{{ route('dashboard') }}" class="action-btn">← Volver al Dashboard</a>
        </div>
    </nav>

    <main class="main-content">
        {{-- Mensajes tras cambiar estado / errores de validacion --}}
        @if (session('status'))
            <div class="flash flash-ok" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash flash-error" role="alert">{{ $errors->first() }}</div>
        @endif

        {{-- ===== KPIs (todos calculados a partir de datos reales) ===== --}}
        <section class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-label">Total de Alertas</div>
                    <div class="kpi-icon">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                            <path d="M9 1.5L11.25 6.75H16.5L12.375 10.125L14.25 15.375L9 11.625L3.75 15.375L5.625 10.125L1.5 6.75H6.75L9 1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
                <div class="kpi-value" id="kpiTotal">{{ number_format($total) }}</div>
                <div class="kpi-meta">Registradas en el rango seleccionado</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-label">Alertas Críticas</div>
                    <div class="kpi-icon critical">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                            <path d="M9 6V9M9 12H9.0075M16.5 9C16.5 13.1421 13.1421 16.5 9 16.5C4.85786 16.5 1.5 13.1421 1.5 9C1.5 4.85786 4.85786 1.5 9 1.5C13.1421 1.5 16.5 4.85786 16.5 9Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
                <div class="kpi-value" id="kpiCritical">{{ number_format($critical) }}</div>
                <div class="kpi-meta">Requieren atención inmediata</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-label">Alta / Media / Baja</div>
                    <div class="kpi-icon info">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                            <path d="M3 15V9M9 15V3M15 15V6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>
                <div class="kpi-value" id="kpiDistribution">{{ $high }} / {{ $medium }} / {{ $low }}</div>
                <div class="kpi-meta">Distribución por severidad</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-label">Sin Revisar</div>
                    <div class="kpi-icon success">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                            <path d="M12.75 6.75L7.5 12L5.25 9.75M16.5 9C16.5 13.1421 13.1421 16.5 9 16.5C4.85786 16.5 1.5 13.1421 1.5 9C1.5 4.85786 4.85786 1.5 9 1.5C13.1421 1.5 16.5 4.85786 16.5 9Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
                <div class="kpi-value" id="kpiNuevas">{{ number_format($nuevas) }}</div>
                <div class="kpi-meta">Alertas en estado "Nueva"</div>
            </div>
        </section>

        {{-- ===== Tabla de alertas ===== --}}
        <section class="alerts-panel">
            <div class="panel-header">
                <div class="panel-title">
                    <span class="panel-title-dot"></span>
                    <span>FLUJO DE ALERTAS IDS</span>
                    {{-- Estado del refresco en tiempo real; pulsar pausa/reanuda --}}
                    <button type="button" class="live-toggle" id="liveToggle" aria-pressed="true" title="Pausar / reanudar el refresco automático">
                        <span class="live-dot"></span> <span id="liveText">EN VIVO</span>
                    </button>
                </div>

                {{-- Filtros de cliente: solo ocultan/muestran filas ya cargadas --}}
                <div class="filter-buttons" role="group" aria-label="Filtrar por severidad">
                    <button type="button" class="filter-btn active" data-filter="all">Todas</button>
                    <button type="button" class="filter-btn critical" data-filter="critical">Críticas</button>
                    <button type="button" class="filter-btn high" data-filter="high">Altas</button>
                    <button type="button" class="filter-btn medium" data-filter="medium">Medias</button>
                    <button type="button" class="filter-btn low" data-filter="low">Bajas</button>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="alerts-table" id="alertsTable">
                    <thead>
                        <tr>
                            <th>TIMESTAMP</th>
                            <th>SEVERIDAD</th>
                            <th>TIPO DE AMENAZA</th>
                            <th>IP ORIGEN</th>
                            <th>IP DESTINO</th>
                            <th>ESTADO</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($alerts as $alert)
                            @include('ids._row', ['alert' => $alert])
                        @empty
                            <tr class="empty-row">
                                <td colspan="7">No hay alertas en el rango seleccionado</td>
                            </tr>
                        @endforelse

                        {{-- Se muestra desde JS cuando un filtro no deja filas visibles --}}
                        <tr class="empty-row" id="filterEmptyRow" hidden>
                            <td colspan="7">Ninguna alerta coincide con el filtro</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="table-footer" id="tableStatus">
                Mostrando {{ $alerts->count() }} de {{ number_format($total) }} alertas
            </div>
        </section>

        {{-- ===== Exportacion CSV de alertas IDS por periodo ===== --}}
        <form method="GET" action="{{ route('export.ids-alerts') }}" class="export-bar">
            <span class="export-title">EXPORTAR ALERTAS IDS (CSV / EXCEL)</span>
            <select name="period" class="time-selector" aria-label="Periodo">
                <option value="day">Día</option>
                <option value="week">Semana</option>
                <option value="month" selected>Mes</option>
                <option value="year">Año</option>
            </select>
            <input type="date" name="date" class="time-selector" value="{{ now()->format('Y-m-d') }}" aria-label="Fecha incluida en el periodo">
            <button type="submit" class="action-btn">Descargar</button>
        </form>
    </main>

    <footer class="panel-footer">Creado por <strong>Daniel Alejandro Aceitón Sepúlveda</strong></footer>

    {{-- ===== Modal de inspeccion (se rellena desde ids-panel.js) ===== --}}
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">INSPECCIÓN TÉCNICA DE LA ALERTA</h2>
                <button type="button" class="modal-close" id="modalClose" aria-label="Cerrar">&times;</button>
            </div>

            <div class="modal-body">
                <div class="modal-grid">
                    <div class="detail-card">
                        <div class="detail-title">Metadatos</div>
                        <div class="detail-row">
                            <span class="detail-label">Timestamp</span>
                            <span class="detail-value" id="modalTimestamp">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Severidad</span>
                            <span class="detail-value" id="modalSeverity">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Tipo de Amenaza</span>
                            <span class="detail-value" id="modalAlertType">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Estado</span>
                            <span class="detail-value" id="modalStatus">-</span>
                        </div>
                    </div>

                    <div class="detail-card">
                        <div class="detail-title">Información de Red</div>
                        <div class="detail-row">
                            <span class="detail-label">IP Origen</span>
                            <span class="detail-value" id="modalSourceIp">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">IP Destino</span>
                            <span class="detail-value" id="modalDestIp">-</span>
                        </div>
                    </div>
                </div>

                {{--
                    Acciones sobre la alerta. Son formularios normales; ids-panel.js
                    solo rellena su "action" con la URL de la alerta abierta.
                --}}
                @if ($canWrite)
                    <div class="detail-card">
                        <div class="detail-title">Acciones</div>
                        <div class="modal-actions">
                            <form method="POST" id="statusForm" class="modal-action-form">
                                @csrf
                                @method('PATCH')
                                <select name="status" id="statusSelect" class="time-selector" aria-label="Nuevo estado">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}">{{ $status }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="action-btn">Cambiar estado</button>
                            </form>

                            <form method="POST" id="incidentForm" class="modal-action-form">
                                @csrf
                                <button type="submit" class="action-btn action-btn-primary">Crear incidente</button>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="detail-card">
                    <div class="detail-title">Firma de Detección (Signature)</div>
                    <div class="detail-value signature-text" id="modalSignature">-</div>
                </div>

                <div class="detail-card">
                    <div class="detail-title">Visor Hexadecimal - Firma codificada</div>
                    {{-- Volcado hex de la firma, generado en el navegador --}}
                    <div class="hex-viewer" id="hexViewer">-</div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/ids-panel.js') }}?v={{ filemtime(public_path('js/ids-panel.js')) }}"></script>
</body>
</html>
