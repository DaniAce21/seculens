/**
 * Panel IDS (vista Blade /ids) - comportamiento en el navegador.
 *
 * Funciones:
 *   1. Enviar el selector de rango temporal al cambiar de opcion.
 *   2. Filtrar las filas de la tabla por severidad.
 *   3. Abrir el modal de inspeccion al pulsar una fila o "Inspeccionar",
 *      y preparar los formularios de acciones (cambiar estado / crear incidente).
 *   4. Cerrar el modal (boton X, clic fuera, tecla Escape).
 *   5. Refresco en tiempo real: cada 10 s pide al servidor las alertas nuevas.
 *
 * Va en archivo externo porque la Content-Security-Policy de la app
 * (script-src 'self') bloquea los <script> en linea y los onclick.
 */
(function () {
    'use strict';

    // ------------------------------------------------------------
    // Referencias al DOM y configuracion
    // ------------------------------------------------------------
    const rangeForm = document.getElementById('rangeForm');
    const rangeSelect = document.getElementById('timeRange');
    const filterButtons = document.querySelectorAll('.filter-btn');
    const tbody = document.querySelector('#alertsTable tbody');
    const filterEmptyRow = document.getElementById('filterEmptyRow');
    const tableStatus = document.getElementById('tableStatus');
    const modalOverlay = document.getElementById('modalOverlay');
    const modalClose = document.getElementById('modalClose');
    const statusForm = document.getElementById('statusForm');       // puede no existir (solo lectura)
    const statusSelect = document.getElementById('statusSelect');
    const incidentForm = document.getElementById('incidentForm');
    const liveToggle = document.getElementById('liveToggle');
    const liveText = document.getElementById('liveText');

    // Configuracion que el servidor deja en <body data-...>.
    const FEED_URL = document.body.dataset.feedUrl;
    const RANGE = document.body.dataset.range || 'all';
    let lastId = parseInt(document.body.dataset.lastId || '0', 10);

    const POLL_MS = 10000;   // intervalo del refresco en vivo
    const MAX_ROWS = 100;    // filas maximas en la tabla (las mas antiguas se quitan)

    // Color CSS asociado a cada severidad (variables definidas en ids-panel.css).
    const SEVERITY_COLORS = {
        critical: 'var(--color-critical)',
        high: 'var(--color-high)',
        medium: 'var(--color-medium)',
        low: 'var(--color-low)',
    };

    let currentFilter = 'all';
    let lastFocused = null;  // foco previo al abrir el modal, para devolverlo
    let live = true;         // refresco activo
    let polling = false;     // evita peticiones solapadas

    /** Filas de alertas actuales (se consulta cada vez: la tabla cambia en vivo). */
    function alertRows() {
        return tbody.querySelectorAll('tr.alert-row');
    }

    // ------------------------------------------------------------
    // 1. Rango temporal: recarga la pagina con ?range=...
    // ------------------------------------------------------------
    if (rangeForm && rangeSelect) {
        rangeSelect.addEventListener('change', () => rangeForm.submit());
    }

    // ------------------------------------------------------------
    // 2. Filtros por severidad (solo en cliente, sin recargar)
    // ------------------------------------------------------------
    function applyFilter() {
        const rows = alertRows();
        let visible = 0;

        rows.forEach((row) => {
            const show = currentFilter === 'all' || row.dataset.severity === currentFilter;
            row.hidden = !show;
            if (show) visible++;
        });

        // Mensaje cuando hay alertas cargadas pero ninguna coincide.
        if (filterEmptyRow) {
            filterEmptyRow.hidden = rows.length === 0 || visible > 0;
        }

        if (tableStatus && rows.length > 0) {
            tableStatus.textContent = `Mostrando ${visible} de ${rows.length} alertas cargadas`;
        }
    }

    filterButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            filterButtons.forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            applyFilter();
        });
    });

    // ------------------------------------------------------------
    // 3. Modal de inspeccion
    // ------------------------------------------------------------

    /** Asigna texto a un elemento por id (textContent evita inyeccion HTML). */
    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value || '-';
    }

    /**
     * Genera un volcado hexadecimal estilo `hexdump -C` de un texto:
     * offset | 16 bytes en hex | representacion ASCII.
     */
    function toHexDump(text) {
        const bytes = new TextEncoder().encode(text);
        if (bytes.length === 0) return 'Sin datos';

        const lines = [];
        for (let offset = 0; offset < bytes.length; offset += 16) {
            const chunk = bytes.slice(offset, offset + 16);

            const hex = Array.from(chunk)
                .map((b) => b.toString(16).padStart(2, '0').toUpperCase())
                .join(' ')
                .padEnd(47, ' ');

            // Bytes no imprimibles se muestran como punto.
            const ascii = Array.from(chunk)
                .map((b) => (b >= 0x20 && b < 0x7f ? String.fromCharCode(b) : '.'))
                .join('');

            lines.push(`${offset.toString(16).padStart(8, '0')}  ${hex}  ${ascii}`);
        }
        return lines.join('\n');
    }

    function openModal(row) {
        let alert;
        try {
            alert = JSON.parse(row.dataset.alert);
        } catch (e) {
            console.error('No se pudo leer la alerta de la fila', e);
            return;
        }

        setText('modalTimestamp', alert.created_at);
        setText('modalAlertType', alert.alert_type);
        setText('modalStatus', alert.status);
        setText('modalSourceIp', alert.source_ip);
        setText('modalDestIp', alert.destination_ip);
        setText('modalSignature', alert.signature || 'Sin firma específica registrada');
        setText('hexViewer', toHexDump(alert.signature || ''));

        const severityEl = document.getElementById('modalSeverity');
        severityEl.textContent = alert.severity_label || String(alert.severity).toUpperCase();
        severityEl.style.color = SEVERITY_COLORS[alert.severity] || '';

        // Formularios de acciones: apuntan a la alerta abierta.
        if (statusForm) {
            statusForm.action = alert.status_url;
            statusSelect.value = alert.status;
        }
        if (incidentForm) {
            incidentForm.action = alert.incident_url;
        }

        lastFocused = document.activeElement;
        modalOverlay.classList.add('active');
        document.body.style.overflow = 'hidden'; // bloquea el scroll de fondo
        modalClose.focus();
    }

    function closeModal() {
        modalOverlay.classList.remove('active');
        document.body.style.overflow = '';
        if (lastFocused) lastFocused.focus();
    }

    // Delegacion de eventos en el <tbody>: funciona tambien con las filas
    // que llegan despues por el refresco en vivo.
    tbody.addEventListener('click', (e) => {
        const row = e.target.closest('tr.alert-row');
        if (row) openModal(row);
    });

    // Accesibilidad: Enter sobre una fila enfocada tambien abre el modal.
    tbody.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches('tr.alert-row')) openModal(e.target);
    });

    // ------------------------------------------------------------
    // 4. Cierre del modal
    // ------------------------------------------------------------
    modalClose.addEventListener('click', closeModal);

    // Clic en el fondo oscuro (fuera del cuadro) cierra el modal.
    modalOverlay.addEventListener('click', (e) => {
        if (e.target === modalOverlay) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalOverlay.classList.contains('active')) closeModal();
    });

    // ------------------------------------------------------------
    // 5. Refresco en tiempo real
    // ------------------------------------------------------------

    /** Actualiza los KPIs con los datos del servidor. */
    function updateStats(stats) {
        const fmt = (n) => Number(n).toLocaleString('es');
        setText('kpiTotal', fmt(stats.total));
        setText('kpiCritical', fmt(stats.critical));
        setText('kpiDistribution', `${stats.high} / ${stats.medium} / ${stats.low}`);
        setText('kpiNuevas', fmt(stats.nuevas));
    }

    async function poll() {
        // Sin peticiones si esta en pausa, la pestaña no se ve o el modal esta abierto
        // (para no mover la tabla mientras se inspecciona una alerta).
        if (!live || polling || document.hidden || modalOverlay.classList.contains('active') || !FEED_URL) {
            return;
        }

        polling = true;
        try {
            const url = `${FEED_URL}?after_id=${lastId}&range=${encodeURIComponent(RANGE)}`;
            const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const data = await res.json();

            if (data.count > 0) {
                // Se quita el aviso "No hay alertas..." si estaba.
                tbody.querySelectorAll('tr.empty-row:not(#filterEmptyRow)').forEach((r) => r.remove());

                // El HTML lo genera y escapa Blade en el servidor (parcial ids._row).
                tbody.insertAdjacentHTML('afterbegin', data.html);

                // Limite de filas: se eliminan las mas antiguas (al final).
                const rows = alertRows();
                for (let i = MAX_ROWS; i < rows.length; i++) rows[i].remove();

                lastId = data.last_id;
                applyFilter();
            }

            updateStats(data.stats);
            setLiveState(true);
        } catch (err) {
            console.warn('Refresco en vivo fallido:', err);
            if (liveText) liveText.textContent = 'SIN CONEXIÓN';
        } finally {
            polling = false;
        }
    }

    function setLiveState(on) {
        live = on;
        if (liveToggle) liveToggle.setAttribute('aria-pressed', String(on));
        if (liveText) liveText.textContent = on ? 'EN VIVO' : 'PAUSADO';
    }

    if (liveToggle) {
        liveToggle.addEventListener('click', () => {
            setLiveState(!live);
            if (live) poll(); // al reanudar, se consulta en el acto
        });
    }

    setInterval(poll, POLL_MS);
})();
