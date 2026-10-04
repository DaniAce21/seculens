<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\Alert;
use App\Models\Incident;
use App\Models\SecurityEvent;
use App\Services\AuditService;
use App\Services\CsvExportService;
use App\Support\EventFilters;
use App\Support\Period;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga CSV de incidentes, eventos, alertas y alertas IDS por periodo.
 *
 * Periodos: dia, semana, mes y año, calculados a partir de una fecha de
 * referencia (por defecto hoy). Ejemplo: /export/events?period=month&date=2026-09-15
 * descarga todos los eventos de septiembre de 2026.
 *
 * El contenido del CSV lo genera CsvExportService (compartido con el
 * informe semanal por correo). Aqui solo se autoriza, se audita y se
 * envia la descarga en streaming.
 */
class ExportController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly CsvExportService $csv,
    ) {}

    /** GET /export/incidents?period=week&date=2026-10-03 */
    public function incidents(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Incident::class);

        return $this->download($request, 'incidents');
    }

    /**
     * GET /export/events?period=month&type=LOGIN_FAILED&ip=185.
     *
     * Los filtros del explorador llegan como campos ocultos del formulario
     * y se aplican igual que en pantalla.
     */
    public function events(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', SecurityEvent::class);

        return $this->download($request, 'events', EventFilters::fromRequest($request));
    }

    /** GET /export/alerts?period=month */
    public function alerts(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Alert::class);

        return $this->download($request, 'alerts');
    }

    /** GET /export/ids-alerts?period=week */
    public function idsAlerts(Request $request): StreamedResponse
    {
        // Mismo criterio de acceso que las alertas de deteccion.
        $this->authorize('viewAny', Alert::class);

        return $this->download($request, 'ids-alerts');
    }

    /**
     * Valida el periodo, registra la exportacion en la auditoria y
     * devuelve la descarga.
     *
     * @param  array<string, string>  $filters
     */
    private function download(Request $request, string $resource, array $filters = []): StreamedResponse
    {
        $period = Period::fromRequest($request);

        // Exportar datos en bloque es lo que haria un atacante: queda auditado.
        $this->audit->tryLog(AuditAction::DATA_EXPORTED, $request->user(), null, [
            'resource' => $resource,
            'period' => $period->unit,
            'from' => $period->from->toDateTimeString(),
            'to' => $period->to->toDateTimeString(),
            'filters' => $filters,
            'rows' => $this->csv->count($resource, $period, $filters),
        ]);

        return response()->streamDownload(function () use ($resource, $period, $filters) {
            $out = fopen('php://output', 'w');
            $this->csv->write($out, $resource, $period, $filters);
            fclose($out);
        }, $this->csv->filename($resource, $period), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
