{{--
    Fila de la tabla del panel IDS.

    Se usa en dos sitios:
      - ids/opse-panel.blade.php (carga inicial de la pagina)
      - IdsWebController@feed    (filas nuevas del refresco en tiempo real)
    Asi ambas se ven igual y Blade se encarga siempre del escapado.

    Variables: $alert (IdsAlert), $isNew (bool, opcional: resalta la fila).
--}}
@php
    $severityLabels = ['critical' => 'CRÍTICA', 'high' => 'ALTA', 'medium' => 'MEDIA', 'low' => 'BAJA'];
    $severityLabel = $severityLabels[$alert->severity] ?? strtoupper($alert->severity);

    // Nivel en la lista de vigilancia de cada IP (null si no esta).
    $srcWatch = \App\Models\WatchedIp::levelFor($alert->source_ip);
    $dstWatch = \App\Models\WatchedIp::levelFor($alert->destination_ip);

    // Datos que necesita el modal (solo los campos que muestra) + URLs de acciones.
    $modalData = [
        'id' => $alert->id,
        'created_at' => $alert->created_at?->format('Y-m-d H:i:s'),
        'severity' => $alert->severity,
        'severity_label' => $severityLabel,
        'alert_type' => $alert->alert_type,
        'source_ip' => $alert->source_ip,
        'destination_ip' => $alert->destination_ip,
        'signature' => $alert->signature,
        'status' => $alert->status,
        'status_url' => route('ids.status', $alert),
        'incident_url' => route('ids.incident', $alert),
    ];
@endphp
{{--
    data-alert lleva la alerta en JSON para el modal. @json escapa comillas
    simples, por lo que es seguro dentro de un atributo con comillas simples.
--}}
<tr class="alert-row{{ ($isNew ?? false) ? ' row-new' : '' }}"
    tabindex="0"
    data-id="{{ $alert->id }}"
    data-severity="{{ $alert->severity }}"
    data-alert='@json($modalData)'>
    <td class="mono-text">{{ $alert->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
    <td><span class="severity-badge severity-{{ $alert->severity }}">{{ $severityLabel }}</span></td>
    <td>{{ $alert->alert_type }}</td>
    <td class="mono-text">
        {{ $alert->source_ip }}
        @if ($srcWatch)
            <span class="ip-watch ip-watch-{{ $srcWatch }}" title="IP en la lista de vigilancia">{{ $srcWatch === 'blocked' ? 'BLOQUEADA' : 'VIGILADA' }}</span>
        @endif
    </td>
    <td class="mono-text">
        {{ $alert->destination_ip }}
        @if ($dstWatch)
            <span class="ip-watch ip-watch-{{ $dstWatch }}" title="IP en la lista de vigilancia">{{ $dstWatch === 'blocked' ? 'BLOQUEADA' : 'VIGILADA' }}</span>
        @endif
    </td>
    <td class="text-muted">{{ $alert->status }}</td>
    <td><button type="button" class="action-btn inspect-btn">Inspeccionar</button></td>
</tr>
