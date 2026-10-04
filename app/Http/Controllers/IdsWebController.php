<?php

namespace App\Http\Controllers;

use App\Enums\AlertSeverity;
use App\Enums\AuditAction;
use App\Models\IdsAlert;
use App\Models\Incident;
use App\Services\AuditService;
use App\Services\IncidentService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controlador web para el panel IDS (vista Blade ids.opse-panel).
 *
 * Muestra las alertas reales de la tabla ids_alerts. Las alertas llegan
 * desde los sensores a traves de la API (POST /api/v1/alerts).
 *
 * Acciones:
 *  - index:          pagina del panel con KPIs y tabla.
 *  - feed:           JSON con las alertas nuevas (refresco en tiempo real).
 *  - updateStatus:   Nueva -> En Investigacion -> Mitigada (auditado).
 *  - createIncident: abre un incidente a partir de una alerta IDS.
 */
class IdsWebController extends Controller
{
    /**
     * Rangos temporales que ofrece el selector del panel.
     * Clave = valor del parametro ?range=, valor = horas hacia atras.
     */
    private const RANGES = [
        '1h' => 1,
        '6h' => 6,
        '24h' => 24,
        '7d' => 24 * 7,
        '30d' => 24 * 30,
        'all' => null, // sin limite temporal
    ];

    /**
     * Numero maximo de filas que se pintan en la tabla.
     */
    private const TABLE_LIMIT = 50;

    /**
     * Correspondencia severidad IDS (minusculas) -> prioridad de incidente.
     */
    private const PRIORITY_MAP = [
        'critical' => AlertSeverity::CRITICAL,
        'high' => AlertSeverity::HIGH,
        'medium' => AlertSeverity::MEDIUM,
        'low' => AlertSeverity::LOW,
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly IncidentService $incidents,
    ) {}

    public function index(Request $request): View
    {
        // Un valor desconocido en ?range= cae en 'all' en vez de fallar.
        $range = array_key_exists($request->query('range'), self::RANGES)
            ? $request->query('range')
            : 'all';

        $base = $this->baseQuery($range);

        $alerts = (clone $base)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::TABLE_LIMIT)
            ->get();

        return view('ids.opse-panel', [
            'alerts' => $alerts,
            'range' => $range,
            'statuses' => IdsAlert::STATUSES,
            // Mayor id mostrado: el refresco en tiempo real pide solo los posteriores.
            'lastId' => (int) IdsAlert::max('id'),
            'canWrite' => $request->user()->canWrite(),
        ] + $this->stats($base));
    }

    /**
     * GET /ids/feed?after_id=123&range=24h
     *
     * Devuelve las alertas con id mayor que after_id, ya renderizadas como
     * filas HTML (mismo parcial que la tabla, asi el aspecto es identico y
     * el escapado lo hace Blade), mas los KPIs actualizados.
     */
    public function feed(Request $request): JsonResponse
    {
        $afterId = max(0, $request->integer('after_id'));
        $range = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : 'all';

        $new = IdsAlert::query()
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(self::TABLE_LIMIT)
            ->get();

        $html = $new->reverse() // la mas reciente arriba
            ->map(fn (IdsAlert $alert) => view('ids._row', [
                'alert' => $alert,
                'isNew' => true,
            ])->render())
            ->implode('');

        return response()->json([
            'html' => $html,
            'count' => $new->count(),
            'last_id' => (int) ($new->max('id') ?? $afterId),
            'stats' => $this->stats($this->baseQuery($range)),
        ]);
    }

    /**
     * PATCH /ids/{idsAlert}/status
     */
    public function updateStatus(Request $request, IdsAlert $idsAlert): RedirectResponse
    {
        abort_unless($request->user()->canWrite(), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(IdsAlert::STATUSES)],
        ]);

        $previous = $idsAlert->status;

        if ($previous !== $data['status']) {
            $idsAlert->update(['status' => $data['status']]);

            $this->audit->tryLog(AuditAction::IDS_ALERT_STATUS_CHANGED, $request->user(), $idsAlert, [
                'from' => $previous,
                'to' => $data['status'],
            ]);
        }

        return back()->with('status', "Alerta IDS #{$idsAlert->id}: estado \"{$data['status']}\".");
    }

    /**
     * POST /ids/{idsAlert}/incident
     *
     * Crea un incidente con los datos de la alerta IDS y pasa la alerta a
     * "En Investigacion". El incidente queda asignado a quien lo crea.
     */
    public function createIncident(Request $request, IdsAlert $idsAlert): RedirectResponse
    {
        $this->authorize('create', Incident::class);

        $incident = $this->incidents->create(
            [
                'title' => "IDS: {$idsAlert->alert_type} desde {$idsAlert->source_ip}",
                'description' => implode("\n", [
                    "Incidente creado desde la alerta IDS #{$idsAlert->id}.",
                    'Fecha: '.$idsAlert->created_at?->format('Y-m-d H:i:s'),
                    "Severidad: {$idsAlert->severity}",
                    "Tipo: {$idsAlert->alert_type}",
                    "IP origen: {$idsAlert->source_ip}",
                    "IP destino: {$idsAlert->destination_ip}",
                    'Firma: '.($idsAlert->signature ?? 'sin firma'),
                ]),
                'priority' => self::PRIORITY_MAP[$idsAlert->severity] ?? AlertSeverity::MEDIUM,
            ],
            $request->user(),
            // Las alertas IDS no son registros de la tabla alerts, por eso no se vinculan.
        );

        if ($idsAlert->status === 'Nueva') {
            $idsAlert->update(['status' => 'En Investigación']);
        }

        return redirect()
            ->route('incidents.show', $incident)
            ->with('status', "Incidente creado desde la alerta IDS #{$idsAlert->id}.");
    }

    /**
     * Consulta base filtrada por rango temporal.
     */
    private function baseQuery(string $range): Builder
    {
        $hours = self::RANGES[$range];

        return IdsAlert::query()
            ->when($hours !== null, fn ($q) => $q->where('created_at', '>=', now()->subHours($hours)));
    }

    /**
     * KPIs del panel para una consulta base.
     *
     * @return array{total: int, critical: int, high: int, medium: int, low: int, nuevas: int}
     */
    private function stats(Builder $base): array
    {
        // Un solo GROUP BY en lugar de una consulta COUNT por severidad.
        $bySeverity = (clone $base)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        return [
            'total' => (int) $bySeverity->sum(),
            'critical' => (int) ($bySeverity['critical'] ?? 0),
            'high' => (int) ($bySeverity['high'] ?? 0),
            'medium' => (int) ($bySeverity['medium'] ?? 0),
            'low' => (int) ($bySeverity['low'] ?? 0),
            'nuevas' => (clone $base)->where('status', 'Nueva')->count(),
        ];
    }
}
