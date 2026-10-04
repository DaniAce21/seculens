/**
 * CYBER_DEFENSE IDS — Global Network Monitor
 * Script principal: simulador de tráfico, filtros, modal con Hex Viewer.
 * JavaScript puro, sin dependencias externas.
 */

(function () {
    'use strict';

    // ============================================================
    // DATOS BASE
    // ============================================================

    const ALERT_TYPES = [
        'SQL Injection Attempt',
        'Port Scan Detected',
        'Brute Force Attack',
        'XSS Payload Detected',
        'DDoS Traffic Spike',
        'Malware C2 Beacon',
        'Privilege Escalation',
        'Data Exfiltration',
        'Buffer Overflow Attempt',
        'Ransomware Signature',
        'Trojan Downloader',
        'SSH Brute Force',
        'DNS Tunneling',
        'ARP Spoofing',
        'Session Hijacking',
        'Zero-Day Exploit Pattern',
        'Crypto Mining Pool Traffic',
        'Botnet Command Signal',
        'Fileless Malware Execution',
        'Lateral Movement Attempt'
    ];

    const SIGNATURES = [
        'ET TROJAN Cobalt Strike Beacon',
        'ET EXPLOIT Apache Log4j RCE',
        'SNORT SQL Injection Union Select',
        'ET SCAN Nmap SYN Scan',
        'SURICATA HTTP Suspicious UA',
        'ET POLICY Outbound Connection',
        'VRT SSH Brute Force Attempt',
        'ET MALWARE Win32 Trickbot',
        'ET WEB_SERVER Possible SQL Injection',
        'SNORT XSS Cross-Site Scripting',
        'ET INFO P2P Coin Mining',
        'VRT DOS TCP Flood',
        'ET TROJAN Emotet C2 Traffic',
        'SNORT DNS Tunneling Detected',
        'ET ATTACK_RESPONSE Code Execution',
        'SURICATA TLS Suspicious JA3',
        'ET EXPLOIT SMB EternalBlue',
        'VRT APP-PCAP Suspicious Traffic',
        'ET MALWARE Agent Tesla Keylogger',
        'SNORT Policy Violation Inbound'
    ];

    const PROTOCOLS = ['TCP', 'UDP', 'ICMP', 'HTTP', 'HTTPS', 'DNS', 'SSH', 'FTP', 'SMTP', 'RDP'];

    const ACTIONS = [
        'BLOQUEADO',
        'EN CUARENTENA',
        'SOLO ALERTA',
        'LIMITADO',
        'CONEXIÓN REINICIADA',
        'PATRÓN DETECTADO — ESCALADO'
    ];

    const SEVERITIES = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];

    const SEVERITY_WEIGHTS = [0.18, 0.32, 0.30, 0.20]; // Probabilidad acumulativa

    // Etiqueta en español de cada severidad (el valor interno sigue en inglés
    // porque lo usan las clases CSS y los filtros).
    const SEVERITY_LABELS = { CRITICAL: 'CRÍTICA', HIGH: 'ALTA', MEDIUM: 'MEDIA', LOW: 'BAJA' };

    // ============================================================
    // ESTADO GLOBAL
    // ============================================================

    let allAlerts = [];
    let currentFilter = 'ALL';
    let isPaused = false;
    let alertIdCounter = 10000;
    let packetsInspected = 48291047;
    let threatsBlocked = 12847;
    let simulationInterval = null;

    // ============================================================
    // UTILIDADES
    // ============================================================

    function randomInt(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function randomFloat(min, max) {
        return (Math.random() * (max - min) + min).toFixed(2);
    }

    function randomIP() {
        return `${randomInt(1, 223)}.${randomInt(0, 255)}.${randomInt(0, 255)}.${randomInt(1, 254)}`;
    }

    function randomPort() {
        const commonPorts = [22, 53, 80, 443, 445, 3389, 8080, 8443, 3306, 5432, 6379, 27017, 1433, 25, 110, 143, 993, 995, 21, 23];
        if (Math.random() < 0.7) {
            return commonPorts[randomInt(0, commonPorts.length - 1)];
        }
        return randomInt(1, 65535);
    }

    function pickSeverity() {
        const r = Math.random();
        let cumulative = 0;
        for (let i = 0; i < SEVERITY_WEIGHTS.length; i++) {
            cumulative += SEVERITY_WEIGHTS[i];
            if (r < cumulative) return SEVERITIES[i];
        }
        return 'LOW';
    }

    function pick(arr) {
        return arr[randomInt(0, arr.length - 1)];
    }

    function formatTimestamp(date) {
        const y = date.getFullYear();
        const mo = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        const h = String(date.getHours()).padStart(2, '0');
        const mi = String(date.getMinutes()).padStart(2, '0');
        const s = String(date.getSeconds()).padStart(2, '0');
        const ms = String(date.getMilliseconds()).padStart(3, '0');
        return `${y}-${mo}-${d} ${h}:${mi}:${s}.${ms}`;
    }

    function generatePayload(length) {
        const bytes = [];
        for (let i = 0; i < length; i++) {
            bytes.push(randomInt(0, 255));
        }
        return bytes;
    }

    // ============================================================
    // GENERACIÓN DE ALERTAS
    // ============================================================

    function createAlert() {
        const severity = pickSeverity();
        const alertType = pick(ALERT_TYPES);
        const srcIp = randomIP();
        let dstIp = randomIP();
        // Evitar que source y destination sean la misma IP
        while (dstIp === srcIp) {
            dstIp = randomIP();
        }
        const port = randomPort();
        const proto = pick(PROTOCOLS);
        const signature = pick(SIGNATURES);
        const action = pick(ACTIONS);
        const confidence = randomInt(60, 99);
        const payloadLength = randomInt(64, 256);
        const payload = generatePayload(payloadLength);

        return {
            id: `INC-${++alertIdCounter}`,
            timestamp: new Date(),
            severity: severity,
            alertType: alertType,
            sourceIp: srcIp,
            destIp: dstIp,
            signature: signature,
            protocol: proto,
            port: port,
            action: action,
            confidence: confidence,
            payload: payload
        };
    }

    // ============================================================
    // RENDERIZADO — TABLA DE ALERTAS
    // ============================================================

    const alertTableBody = document.getElementById('alertTableBody');
    const tableStatus = document.getElementById('tableStatus');
    const globalAlertCount = document.getElementById('globalAlertCount');

    function getFilteredAlerts() {
        if (currentFilter === 'ALL') {
            return allAlerts;
        }
        return allAlerts.filter(a => a.severity === currentFilter);
    }

    function severityBadgeClass(severity) {
        return 'sev-badge ' + severity.toLowerCase();
    }

    function renderTable(animateNew) {
        const filtered = getFilteredAlerts();

        // Mantener solo las últimas 200 alertas en el DOM
        const displayAlerts = filtered.slice(0, 200);

        alertTableBody.innerHTML = '';

        displayAlerts.forEach((alert, index) => {
            const tr = document.createElement('tr');
            tr.className = 'row-' + alert.severity.toLowerCase();
            tr.setAttribute('data-alert-id', alert.id);

            // Animar solo la primera fila si es nueva
            if (animateNew && index === 0) {
                tr.classList.add('row-enter');
            }

            tr.innerHTML = `
                <td>${formatTimestamp(alert.timestamp)}</td>
                <td><span class="${severityBadgeClass(alert.severity)}">${SEVERITY_LABELS[alert.severity]}</span></td>
                <td class="type-text">${alert.alertType}</td>
                <td class="ip-source">${alert.sourceIp}</td>
                <td class="ip-dest">${alert.destIp}</td>
                <td class="sig-text">${alert.signature}</td>
            `;

            tr.addEventListener('click', function () {
                openModal(alert);
            });

            alertTableBody.appendChild(tr);
        });

        // Actualizar contadores
        tableStatus.textContent = `Mostrando ${displayAlerts.length} de ${allAlerts.length} alertas`;
        globalAlertCount.textContent = allAlerts.length;
    }

    // ============================================================
    // FILTROS POR SEVERIDAD
    // ============================================================

    const filterButtonsContainer = document.getElementById('filterButtons');

    filterButtonsContainer.addEventListener('click', function (e) {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;

        const filter = btn.getAttribute('data-filter');
        currentFilter = filter;

        // Actualizar clases activas
        filterButtonsContainer.querySelectorAll('.filter-btn').forEach(b => {
            b.classList.remove('active');
        });
        btn.classList.add('active');

        renderTable(false);
    });

    // ============================================================
    // BOTÓN PAUSAR / REANUDAR
    // ============================================================

    const pauseBtn = document.getElementById('pauseBtn');
    const pauseIcon = document.getElementById('pauseIcon');
    const pauseText = document.getElementById('pauseText');

    pauseBtn.addEventListener('click', function () {
        isPaused = !isPaused;

        if (isPaused) {
            // simulationInterval guarda un setTimeout (ver startSimulation), por eso clearTimeout.
            clearTimeout(simulationInterval);
            simulationInterval = null;
            pauseIcon.textContent = '▶';
            pauseText.textContent = 'Reanudar';
            pauseBtn.classList.add('paused');
        } else {
            startSimulation();
            pauseIcon.textContent = '⏸';
            pauseText.textContent = 'Pausar';
            pauseBtn.classList.remove('paused');
        }
    });

    // ============================================================
    // MODAL DE INSPECCIÓN + HEX VIEWER
    // ============================================================

    const modalOverlay = document.getElementById('modalOverlay');
    const modalCloseBtn = document.getElementById('modalClose');

    function openModal(alert) {
        // Llenar datos del incidente
        document.getElementById('modalIncidentId').textContent = alert.id;
        document.getElementById('modalTimestamp').textContent = formatTimestamp(alert.timestamp);
        document.getElementById('modalAlertType').textContent = alert.alertType;
        document.getElementById('modalSourceIp').textContent = alert.sourceIp;
        document.getElementById('modalDestIp').textContent = alert.destIp;
        document.getElementById('modalSignature').textContent = alert.signature;
        document.getElementById('modalProtocol').textContent = alert.protocol;
        document.getElementById('modalPort').textContent = alert.port;
        document.getElementById('modalAction').textContent = alert.action;
        document.getElementById('modalConfidence').textContent = alert.confidence + '%';

        // Severidad con color
        const sevEl = document.getElementById('modalSeverity');
        sevEl.textContent = SEVERITY_LABELS[alert.severity];
        sevEl.className = 'detail-value sev-' + alert.severity.toLowerCase();

        // Generar Hex Viewer
        renderHexViewer(alert.payload);

        // Abrir modal
        modalOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modalOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    modalCloseBtn.addEventListener('click', closeModal);

    modalOverlay.addEventListener('click', function (e) {
        if (e.target === modalOverlay) {
            closeModal();
        }
    });

    // Cerrar modal con Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modalOverlay.classList.contains('active')) {
            closeModal();
        }
    });

    // ============================================================
    // HEX VIEWER
    // ============================================================

    function renderHexViewer(payload) {
        const hexViewer = document.getElementById('hexViewer');
        hexViewer.innerHTML = '';

        const BYTES_PER_ROW = 16;

        for (let offset = 0; offset < payload.length; offset += BYTES_PER_ROW) {
            const rowBytes = payload.slice(offset, offset + BYTES_PER_ROW);

            const row = document.createElement('div');
            row.className = 'hex-row';

            // Offset
            const offsetSpan = document.createElement('span');
            offsetSpan.className = 'hex-offset';
            offsetSpan.textContent = '0x' + offset.toString(16).toUpperCase().padStart(4, '0');
            row.appendChild(offsetSpan);

            // Bytes hexadecimales
            const bytesContainer = document.createElement('div');
            bytesContainer.className = 'hex-bytes';

            rowBytes.forEach(byteVal => {
                const byteSpan = document.createElement('span');
                byteSpan.className = 'hex-byte';

                if (byteVal === 0) {
                    byteSpan.classList.add('zero');
                } else if (byteVal >= 32 && byteVal <= 126) {
                    byteSpan.classList.add('printable');
                } else {
                    byteSpan.classList.add('special');
                }

                byteSpan.textContent = byteVal.toString(16).toUpperCase().padStart(2, '0');
                bytesContainer.appendChild(byteSpan);
            });

            // Rellenar la fila si tiene menos de 16 bytes
            for (let i = rowBytes.length; i < BYTES_PER_ROW; i++) {
                const emptySpan = document.createElement('span');
                emptySpan.className = 'hex-byte zero';
                emptySpan.textContent = '··';
                emptySpan.style.opacity = '0.2';
                bytesContainer.appendChild(emptySpan);
            }

            row.appendChild(bytesContainer);

            // ASCII
            const asciiContainer = document.createElement('div');
            asciiContainer.className = 'hex-ascii';

            rowBytes.forEach(byteVal => {
                const asciiChar = document.createElement('span');
                if (byteVal >= 32 && byteVal <= 126) {
                    asciiChar.className = 'ascii-printable';
                    asciiChar.textContent = String.fromCharCode(byteVal);
                } else {
                    asciiChar.className = 'ascii-nonprint';
                    asciiChar.textContent = '·';
                }
                asciiContainer.appendChild(asciiChar);
            });

            row.appendChild(asciiContainer);
            hexViewer.appendChild(row);
        }
    }

    // ============================================================
    // KPIs — Actualización periódica
    // ============================================================

    function updateKPIs() {
        packetsInspected += randomInt(1200, 8500);
        threatsBlocked += randomInt(0, 15);

        const latency = randomFloat(2.1, 12.8);
        const bandwidth = randomFloat(1.2, 9.8);

        document.getElementById('kpiPackets').textContent = packetsInspected.toLocaleString();
        document.getElementById('kpiLatency').textContent = latency + ' ms';
        document.getElementById('kpiBandwidth').textContent = bandwidth + ' Gbps';
        document.getElementById('kpiThreatsBlocked').textContent = threatsBlocked.toLocaleString();
    }

    // ============================================================
    // SEVERITY DISTRIBUTION BARS
    // ============================================================

    function updateSeverityBars() {
        const counts = { CRITICAL: 0, HIGH: 0, MEDIUM: 0, LOW: 0 };
        allAlerts.forEach(a => {
            counts[a.severity]++;
        });

        const total = Math.max(allAlerts.length, 1);

        document.getElementById('sevCritical').textContent = counts.CRITICAL;
        document.getElementById('sevHigh').textContent = counts.HIGH;
        document.getElementById('sevMedium').textContent = counts.MEDIUM;
        document.getElementById('sevLow').textContent = counts.LOW;

        document.getElementById('sevCriticalBar').style.width = ((counts.CRITICAL / total) * 100) + '%';
        document.getElementById('sevHighBar').style.width = ((counts.HIGH / total) * 100) + '%';
        document.getElementById('sevMediumBar').style.width = ((counts.MEDIUM / total) * 100) + '%';
        document.getElementById('sevLowBar').style.width = ((counts.LOW / total) * 100) + '%';
    }

    // ============================================================
    // THREAT ACTORS (Top 5 IPs más activas)
    // ============================================================

    function updateThreatActors() {
        const ipCounts = {};
        allAlerts.forEach(a => {
            ipCounts[a.sourceIp] = (ipCounts[a.sourceIp] || 0) + 1;
        });

        const sorted = Object.entries(ipCounts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 5);

        const container = document.getElementById('threatActorsList');
        container.innerHTML = '';

        if (sorted.length === 0) {
            container.innerHTML = '<div style="color: var(--text-muted); font-family: var(--mono); font-size: 0.75rem; padding: 0.5rem;">Sin datos de actores de amenazas aún...</div>';
            return;
        }

        const maxCount = sorted[0][1];

        sorted.forEach(([ip, count], index) => {
            const row = document.createElement('div');
            row.className = 'threat-actor-row';

            const barWidth = Math.max((count / maxCount) * 100, 10);

            row.innerHTML = `
                <span class="threat-actor-rank">#${index + 1}</span>
                <span class="threat-actor-ip">${ip}</span>
                <span class="threat-actor-count">${count} alertas</span>
                <div class="threat-actor-bar-track">
                    <div class="threat-actor-bar-fill" style="width: ${barWidth}%"></div>
                </div>
            `;

            container.appendChild(row);
        });
    }

    // ============================================================
    // FECHA Y HORA EN VIVO (header)
    // ============================================================

    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentDateTime').textContent = formatTimestamp(now);
    }

    // ============================================================
    // SIMULADOR DE TRÁFICO EN VIVO
    // ============================================================

    function simulateAlert() {
        const alert = createAlert();
        allAlerts.unshift(alert);

        // Mantener máximo 500 alertas en memoria
        if (allAlerts.length > 500) {
            allAlerts = allAlerts.slice(0, 500);
        }

        // Renderizar solo la primera fila como nueva (animación)
        renderTable(true);
        updateSeverityBars();
        updateThreatActors();
    }

    function startSimulation() {
        // Intervalo aleatorio entre 1.5s y 4s para variar el flujo
        function scheduleNext() {
            const delay = randomInt(1500, 4000);
            simulationInterval = setTimeout(function () {
                if (!isPaused) {
                    simulateAlert();
                    scheduleNext();
                }
            }, delay);
        }
        scheduleNext();
    }

    // ============================================================
    // INICIALIZACIÓN
    // ============================================================

    function init() {
        // Generar alertas iniciales para que la tabla no esté vacía.
        // Se acumula un desfase hacia atrás para que los timestamps queden
        // estrictamente ordenados (antes un randomInt por fila podía
        // desordenarlos). push() deja la más reciente arriba.
        let offsetMs = 0;
        for (let i = 0; i < 25; i++) {
            const alert = createAlert();
            offsetMs += randomInt(2000, 8000);
            alert.timestamp = new Date(Date.now() - offsetMs);
            allAlerts.push(alert);
        }

        renderTable(false);
        updateSeverityBars();
        updateThreatActors();
        updateKPIs();
        updateDateTime();

        // Actualizar KPIs cada 3 segundos
        setInterval(updateKPIs, 3000);

        // Actualizar reloj cada segundo
        setInterval(updateDateTime, 1000);

        // Iniciar simulador
        startSimulation();
    }

    // Esperar a que el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
