@extends('layouts.app')

@section('title', 'Documentación API')
@section('page-title', 'Documentación de la API')
@section('page-subtitle', 'API REST para sensores IDS')

{{--
    Documentacion de la API real (routes/api.php). Acceso solo ADMIN
    (ApiDocumentationController); cada visita queda en la auditoria.
--}}

@section('content')
    {{-- ===== Autenticacion ===== --}}
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-key-fill"></i> Autenticación</h3>
        </div>
        <div class="card-body">
            <p class="mb-4" style="color: var(--color-text-secondary);">
                Todas las peticiones a <code>/api/v1/*</code> requieren el token de un sensor registrado en la cabecera
                <code>Authorization</code>. Sin token, o con un token revocado, la respuesta es <code>401</code>.
                Límite: <strong>120 peticiones por minuto</strong> (si se supera, <code>429</code>).
            </p>
            <pre class="code-block">Authorization: Bearer ids_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json</pre>
            <p class="mt-note mb-2" style="color: var(--color-text-secondary);">Gestión de sensores (desde la terminal, en la carpeta <code>backend</code>):</p>
            <pre class="code-block">php artisan ids:sensor-create sensor-dmz   # crea el sensor y muestra su token UNA sola vez
php artisan ids:sensor-list                # sensores, último uso y estado
php artisan ids:sensor-revoke sensor-dmz   # el token deja de funcionar al instante</pre>
        </div>
    </div>

    {{-- ===== Endpoints ===== --}}
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-signpost-split-fill"></i> Endpoints</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container" style="border: none; border-radius: 0;">
                <table class="table">
                    <thead><tr><th>Método</th><th>Ruta</th><th>Descripción</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><span class="badge badge-new">GET</span></td>
                            <td><code>/api/v1/alerts</code></td>
                            <td>Listado paginado. Parámetros opcionales: <code>severity</code>, <code>status</code>, <code>per_page</code> (1–200, por defecto 50), <code>page</code>.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-resolved">POST</span></td>
                            <td><code>/api/v1/alerts</code></td>
                            <td>Registra una alerta. Responde <code>201</code> con la alerta creada, o <code>422</code> si los datos no son válidos.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-new">GET</span></td>
                            <td><code>/api/v1/alerts/stats</code></td>
                            <td>Totales por severidad y estado, y las 5 alertas más recientes.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===== Campos de POST /api/v1/alerts ===== --}}
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-input-cursor-text"></i> Campos de <code>POST /api/v1/alerts</code></h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container" style="border: none; border-radius: 0;">
                <table class="table">
                    <thead><tr><th>Campo</th><th>Obligatorio</th><th>Valores</th></tr></thead>
                    <tbody>
                        <tr><td><code>severity</code></td><td>Sí</td><td><code>critical</code>, <code>high</code>, <code>medium</code>, <code>low</code></td></tr>
                        <tr><td><code>alert_type</code></td><td>Sí</td><td>Texto, máximo 100 caracteres (p. ej. "Port Scan")</td></tr>
                        <tr><td><code>source_ip</code></td><td>Sí</td><td>IPv4 o IPv6 válida</td></tr>
                        <tr><td><code>destination_ip</code></td><td>Sí</td><td>IPv4 o IPv6 válida</td></tr>
                        <tr><td><code>signature</code></td><td>No</td><td>Firma de detección, máximo 2000 caracteres</td></tr>
                        <tr><td><code>status</code></td><td>No</td><td><code>Nueva</code> (por defecto), <code>En Investigación</code>, <code>Mitigada</code></td></tr>
                        <tr><td><code>created_at</code></td><td>No</td><td>Fecha ISO 8601; por defecto, el momento de recepción</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===== Ejemplo ===== --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-terminal-fill"></i> Ejemplo</h3>
        </div>
        <div class="card-body">
            <pre class="code-block">curl -X POST {{ url('/api/v1/alerts') }} \
  -H "Authorization: Bearer ids_xxxxxxxx" \
  -H "Accept: application/json" \
  -d severity=high -d alert_type="Port Scan" \
  -d source_ip=45.33.32.156 -d destination_ip=10.0.0.5 \
  -d signature="ET SCAN Nmap SYN Scan"</pre>
            <p class="mt-note mb-0 text-tertiary">
                La alerta aparece al instante en <a href="{{ route('ids.index') }}" class="table-link">Alertas IDS</a>
                (el panel se refresca solo cada 10 segundos).
            </p>
        </div>
    </div>
@endsection
