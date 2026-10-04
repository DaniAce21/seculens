<?php

namespace App\Http\Controllers;

use App\Enums\AlertStatus;
use App\Http\Requests\IndexAlertRequest;
use App\Http\Requests\UpdateAlertStatusRequest;
use App\Models\Alert;
use App\Models\SecurityEvent;
use App\Services\AlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestion de alertas de seguridad.
 *
 * El controller no contiene reglas de negocio. Solo traduce la
 * peticion a una llamada de servicio y elige la respuesta. Las
 * transiciones de estado las valida AlertService, y el permiso lo
 * resuelve AlertPolicy: si una regla estuviera aqui, el mismo flujo
 * tendria que duplicarse en la API.
 */
class AlertController extends Controller
{
    public function __construct(
        private readonly AlertService $alerts,
    ) {}

    /**
     * Listado de alertas con filtros aplicados en el servidor.
     *
     * Todos los usuarios autenticados pueden ver alertas: un VIEWER
     * existe para consultar el panel.
     */
    public function index(IndexAlertRequest $request): View
    {
        $this->authorize('viewAny', Alert::class);

        $alerts = Alert::query()
            /*
             * withCount en lugar de with sobre securityEvents.
             *
             * El listado solo necesita saber cuantos eventos respaldan
             * cada alerta. Cargar los eventos para despues no
             * mostrarlos multiplicaria las filas por el numero de
             * intentos de autenticacion de cada ataque, que es
             * precisamente la parte voluminosa de la base de datos.
             */
            ->withCount('securityEvents')
            ->when($request->validated('status'), fn ($query, $statuses) => $query->whereIn('status', $statuses))
            ->when($request->validated('severity'), fn ($query, $severities) => $query->whereIn('severity', $severities))
            ->when($request->validated('source_ip'), fn ($query, $ip) => $query->where('source_ip', $ip))
            ->when($request->validated('detection_rule'), fn ($query, $rule) => $query->where('detection_rule', $rule))
            ->when(
                $request->searchTerm(),
                fn ($query, $term) => $query->where(function ($inner) use ($term) {
                    /*
                     * El comodin se escapa porque el termino lo escribe
                     * el usuario. Sin este escape, buscar "%" devolveria
                     * todas las alertas en lugar de una busqueda sin
                     * resultados, y el operador % revela de forma
                     * involuntaria el volumen de datos almacenado.
                     */
                    $escaped = SecurityEvent::escapeLike($term);

                    $inner->where('title', 'ilike', '%'.$escaped.'%')
                        ->orWhere('description', 'ilike', '%'.$escaped.'%');
                })
            )
            ->orderBy($request->sortColumn(), $request->sortDirection())
            ->paginate($request->perPage())
            ->withQueryString();

        return view('alerts.index', [
            'alerts' => $alerts,
            'filters' => $request->validated(),
        ]);
    }

    /**
     * Detalle de una alerta con su evidencia.
     *
     * Los eventos se cargan de forma paginada: una alerta de fuerza
     * bruta puede tener cientos de intentos asociados y mostrarlos
     * todos haria la pagina inutilizable.
     */
    public function show(Request $request, Alert $alert): View
    {
        $this->authorize('view', $alert);

        $events = $alert->securityEvents()
            ->orderByDesc('occurred_at')
            ->paginate(20);

        return view('alerts.show', [
            'alert' => $alert,
            'events' => $events,
            'incidents' => $alert->incidents()->with('assignee')->get(),
            'canAcknowledge' => $request->user()->can('acknowledge', $alert),
            'canResolve' => $request->user()->can('resolve', $alert),
            'canReopen' => $request->user()->can('reopen', $alert),
        ]);
    }

    /**
     * Reconoce una alerta: el analista la ha visto y la tiene en curso.
     */
    public function acknowledge(Request $request, Alert $alert): RedirectResponse
    {
        $this->authorize('acknowledge', $alert);

        $this->alerts->changeStatus($alert, AlertStatus::ACKNOWLEDGED, $request->user());

        return back()->with('status', 'Alerta reconocida.');
    }

    /**
     * Resuelve una alerta.
     *
     * El motivo es obligatorio: una alerta resuelta sin explicacion
     * deja al siguiente analista sin el criterio que aplico el
     * anterior, y esa es la informacion que hace reutilizable la
     * bitacora.
     */
    public function resolve(UpdateAlertStatusRequest $request, Alert $alert): RedirectResponse
    {
        $this->authorize('resolve', $alert);

        $this->alerts->changeStatus($alert, AlertStatus::RESOLVED, $request->user());

        return back()->with('status', 'Alerta resuelta.');
    }

    /**
     * Reabre una alerta resuelta.
     *
     * Reservado a ADMIN por policy: reabrir contradice una evaluacion
     * previa, y permitirlo a cualquiera debilitaria la confianza en la
     * bitacora de alertas.
     */
    public function reopen(Request $request, Alert $alert): RedirectResponse
    {
        $this->authorize('reopen', $alert);

        $this->alerts->changeStatus($alert, AlertStatus::OPEN, $request->user());

        return back()->with('status', 'Alerta reabierta.');
    }
}
