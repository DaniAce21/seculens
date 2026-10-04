<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Vistas web (Blade) de las alertas de seguridad.
 *
 * Antes referenciaba un modelo App\Models\SecurityAlert que no existe,
 * lo que provocaba un error 500 en /alerts. El modelo real es Alert y
 * la relacion con la evidencia es securityEvents(), no events().
 */
class SecurityAlertController extends Controller
{
    /**
     * Listado paginado de alertas con filtros opcionales.
     */
    public function index(Request $request): View
    {
        $query = Alert::query()
            // La vista muestra el numero de eventos por alerta; withCount
            // lo resuelve en la misma consulta y evita el problema N+1.
            ->withCount('securityEvents')
            ->orderByDesc('last_seen_at');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', (string) $request->string('severity'));
        }

        return view('alerts.index', [
            // withQueryString conserva los filtros al cambiar de pagina.
            'alerts' => $query->paginate(20)->withQueryString(),
        ]);
    }

    /**
     * Detalle de una alerta y los eventos que la sustentan.
     */
    public function show(Alert $alert): View
    {
        // La vista espera $events paginado (usa ->total() y ->links()).
        $events = $alert->securityEvents()
            ->orderByDesc('occurred_at')
            ->paginate(20);

        return view('alerts.show', [
            'alert' => $alert,
            'events' => $events,
        ]);
    }
}
