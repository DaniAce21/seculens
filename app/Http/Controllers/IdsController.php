<?php

namespace App\Http\Controllers;

use App\Models\IdsAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controlador para gestión de alertas IDS.
 *
 * Expone endpoints REST para consumo del front-end (estilo corporativo OPSE)
 * y para inyección de alertas desde sensores IDS.
 */
class IdsController extends Controller
{
    /**
     * Obtiene listado paginado de alertas IDS.
     */
    public function index(Request $request): JsonResponse
    {
        $query = IdsAlert::query();

        if ($request->filled('severity')) {
            $query->where('severity', (string) $request->string('severity'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        $alerts = $query->orderByDesc('created_at')
            // per_page acotado a 1..200: sin limite, ?per_page=1000000 podia
            // forzar una consulta enorme.
            ->paginate(min(200, max(1, $request->integer('per_page', 50))));

        return response()->json([
            'status' => 'success',
            'data' => $alerts->items(),
            'pagination' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
            ],
        ]);
    }

    /**
     * Almacena una nueva alerta proveniente de sensores IDS.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'severity' => ['required', Rule::in(IdsAlert::SEVERITIES)],
            'alert_type' => ['required', 'string', 'max:100'],
            // 'ip' valida IPv4/IPv6 reales (antes aceptaba cualquier texto).
            'source_ip' => ['required', 'ip'],
            'destination_ip' => ['required', 'ip'],
            'signature' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(IdsAlert::STATUSES)],
            'created_at' => ['nullable', 'date'],
        ]);

        $alert = IdsAlert::create([
            'created_at' => $validated['created_at'] ?? now(),
            'severity' => $validated['severity'],
            'alert_type' => $validated['alert_type'],
            'source_ip' => $validated['source_ip'],
            'destination_ip' => $validated['destination_ip'],
            'signature' => $validated['signature'] ?? null,
            'status' => $validated['status'] ?? 'Nueva',
            // Sensor autenticado por el middleware sensor.token.
            'sensor_id' => $request->attributes->get('ids_sensor')?->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Alerta IDS registrada correctamente',
            'data' => $alert,
        ], 201);
    }

    /**
     * Devuelve estadísticas rápidas para paneles y dashboards.
     */
    public function stats(): JsonResponse
    {
        $total = IdsAlert::count();
        $critical = IdsAlert::where('severity', 'critical')->count();
        $high = IdsAlert::where('severity', 'high')->count();
        $medium = IdsAlert::where('severity', 'medium')->count();
        $low = IdsAlert::where('severity', 'low')->count();
        $nuevas = IdsAlert::where('status', 'Nueva')->count();
        $investigacion = IdsAlert::where('status', 'En Investigación')->count();
        $mitigadas = IdsAlert::where('status', 'Mitigada')->count();
        $ultimas = IdsAlert::orderByDesc('created_at')->limit(5)->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total' => $total,
                'critical' => $critical,
                'high' => $high,
                'medium' => $medium,
                'low' => $low,
                'nuevas' => $nuevas,
                'en_investigacion' => $investigacion,
                'mitigadas' => $mitigadas,
                'ultimas_alertas' => $ultimas,
            ],
        ]);
    }
}
