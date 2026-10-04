{{--
    Correo del informe de seguridad (App\Mail\SecurityReportMail).
    Los clientes de correo ignoran las hojas de estilo externas y muchas
    reglas CSS, por eso aqui los estilos van en linea y con tablas.
--}}
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Segoe UI,Arial,sans-serif;color:#111827;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:8px;">
        <tr>
            <td style="padding:24px 28px;border-bottom:3px solid #3b82f6;">
                <h1 style="margin:0;font-size:20px;">SecuLens — Informe de Seguridad</h1>
                <p style="margin:6px 0 0;color:#6b7280;font-size:14px;">{{ $period->description() }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:20px 28px;">
                <table width="100%" cellpadding="8" cellspacing="0" style="font-size:14px;border-collapse:collapse;">
                    @foreach ([
                        'Eventos registrados' => $totals['events'],
                        'Logins fallidos' => $totals['failed_logins'],
                        'Alertas detectadas' => $totals['alerts'],
                        'Alertas IDS' => $totals['ids_alerts'],
                        'Incidentes abiertos' => $totals['incidents_opened'],
                        'Incidentes cerrados' => $totals['incidents_closed'],
                    ] as $label => $value)
                        <tr style="border-bottom:1px solid #e5e7eb;">
                            <td>{{ $label }}</td>
                            <td align="right"><strong>{{ number_format($value) }}</strong></td>
                        </tr>
                    @endforeach
                </table>

                @if (! empty($top_ips))
                    <h2 style="font-size:16px;margin:24px 0 8px;">IPs más activas</h2>
                    <table width="100%" cellpadding="6" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
                        <tr style="background:#f9fafb;text-align:left;">
                            <th>IP</th><th>Logins fallidos</th><th>Alertas IDS</th>
                        </tr>
                        @foreach (array_slice($top_ips, 0, 5) as $row)
                            <tr style="border-bottom:1px solid #e5e7eb;">
                                <td style="font-family:Consolas,monospace;">{{ $row['ip'] }}</td>
                                <td>{{ $row['failed_logins'] }}</td>
                                <td>{{ $row['ids_alerts'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                <p style="margin:24px 0 0;font-size:13px;color:#6b7280;">
                    Se adjuntan los CSV de incidentes, eventos, alertas y alertas IDS del periodo.
                    Informe completo:
                    <a href="{{ route('reports.index', ['period' => $period->unit, 'date' => $period->from->format('Y-m-d')]) }}">ver en SecuLens</a>.
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:14px 28px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;text-align:center;">
                Creado por Daniel Alejandro Aceitón Sepúlveda
            </td>
        </tr>
    </table>
</body>
</html>
