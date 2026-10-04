<?php

namespace App\Http\Controllers;

use App\Enums\AlertSeverity;
use App\Enums\IncidentStatus;
use App\Http\Requests\AssignIncidentRequest;
use App\Http\Requests\AttachAlertToIncidentRequest;
use App\Http\Requests\IndexIncidentRequest;
use App\Http\Requests\StoreIncidentNoteRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentRequest;
use App\Http\Requests\UpdateIncidentStatusRequest;
use App\Models\Alert;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Gestion de incidentes.
 *
 * Cada accion comprueba su policy antes de tocar el modelo. La
 * comprobacion vive en el controller y no en el service porque responde
 * a quien puede actuar, mientras que el service se ocupa de como se
 * ejecuta la accion una vez autorizada.
 */
class IncidentController extends Controller
{
    /**
     * Expresion SQL que ordena por severidad de negocio.
     *
     * priority se persiste como texto, de modo que un ORDER BY directo
     * ordenaria alfabeticamente y "CRITICAL" quedaria antes que "HIGH"
     * solo por azar. El CASE traduce la severidad a su posicion real
     * en la escala.
     */
    private const PRIORITY_ORDER = <<<'SQL'
        CASE priority
            WHEN 'CRITICAL' THEN 1
            WHEN 'HIGH' THEN 2
            WHEN 'MEDIUM' THEN 3
            ELSE 4
        END
        SQL;

    public function __construct(
        private readonly IncidentService $incidents,
    ) {}

    /**
     * Tablero de incidentes.
     */
    public function index(IndexIncidentRequest $request): View
    {
        $this->authorize('viewAny', Incident::class);

        $incidents = Incident::query()
            ->with('assignee:id,name')
            ->withCount('alerts')
            ->when(
                $request->validated('status'),
                fn ($query, $statuses) => $query->whereIn('status', $statuses),
            )
            ->when($request->validated('priority'), fn ($query, $priorities) => $query->whereIn('priority', $priorities))
            ->when($request->validated('assigned_to'), fn ($query, $id) => $query->where('assigned_to', $id))
            /*
             * Los filtros "mis incidentes" y "sin asignar" son excluyentes
             * por construccion: el primero busca assigned_to igual al id
             * del usuario y el segundo busca la columna nula, asi que
             * ninguno puede cuadrar a la vez. FormRequest se encarga de
             * rechazar la combinacion contradictoria.
             */
            ->when($request->scope(), fn ($query, $scope) => $query->when(
                $scope === 'mine',
                fn ($q) => $q->where('assigned_to', $request->user()->id),
            )->when(
                $scope === 'unassigned',
                fn ($q) => $q->whereNull('assigned_to'),
            ))
            ->orderBy(DB::raw(self::PRIORITY_ORDER))
            ->orderByDesc('opened_at')
            ->paginate($request->perPage())
            ->withQueryString();

        return view('incidents.index', [
            'incidents' => $incidents,
            'filters' => $request->validated(),
            'analysts' => $this->analystOptions(),
        ]);
    }

    /**
     * Ficha completa de un incidente.
     *
     * La relacion notes ya viene ordenada de forma ascendente desde el
     * modelo, porque una cronologia de investigacion que empieza por lo
     * mas reciente obliga a reconstruir el hilo leyendola al reves.
     */
    public function show(Request $request, Incident $incident): View
    {
        $this->authorize('view', $incident);

        $incident->load(['assignee', 'alerts', 'notes.author']);

        return view('incidents.show', [
            'incident' => $incident,
            'canEdit' => $request->user()->can('update', $incident),
            'canChangeStatus' => $request->user()->can('changeStatus', $incident),
            'canAddNote' => $request->user()->can('addNote', $incident),
            'canAssign' => $request->user()->can('assign', $incident),
            'canManageAlerts' => $request->user()->can('manageAlerts', $incident),
            'analysts' => $this->analystOptions(),
            'availableAlerts' => $this->availableAlerts($incident),
        ]);
    }

    /**
     * Abre un incidente nuevo.
     */
    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $this->authorize('create', Incident::class);

        $incident = $this->incidents->create(
            [
                'title' => (string) $request->validated('title'),
                'description' => (string) $request->validated('description'),
                'priority' => AlertSeverity::from((string) $request->validated('priority')),
            ],
            $request->user(),
            $request->alertIds(),
        );

        return redirect()
            ->route('incidents.show', $incident)
            ->with('status', 'Incidente creado.');
    }

    /**
     * Edita los campos descriptivos del incidente.
     */
    public function update(UpdateIncidentRequest $request, Incident $incident): RedirectResponse
    {
        $this->authorize('update', $incident);

        $data = $request->validated();

        if (isset($data['priority'])) {
            $data['priority'] = AlertSeverity::from((string) $data['priority']);
        }

        $this->incidents->update($incident, $data, $request->user());

        return back()->with('status', 'Incidente actualizado.');
    }

    /**
     * Cambia el estado del incidente.
     *
     * Una transicion invalida se responde con un error de validacion y
     * no con una excepcion: el cliente envio un estado que la maquina
     * de estados no admite, y eso es una entrada invalida, no un fallo
     * del servidor.
     */
    public function changeStatus(UpdateIncidentStatusRequest $request, Incident $incident): RedirectResponse
    {
        $this->authorize('changeStatus', $incident);

        $target = IncidentStatus::from((string) $request->validated('status'));

        if (! $incident->canTransitionTo($target)) {
            return back()->withErrors([
                'status' => sprintf(
                    'No se puede pasar de %s a %s.',
                    $incident->status->label(),
                    $target->label(),
                ),
            ]);
        }

        $this->incidents->changeStatus($incident, $target, $request->user());

        return back()->with('status', 'Incidente actualizado a '.$target->label().'.');
    }

    /**
     * Asigna el incidente a un analista o lo devuelve a la cola.
     */
    public function assign(AssignIncidentRequest $request, Incident $incident): RedirectResponse
    {
        $this->authorize('assign', $incident);

        $assigneeId = $request->assigneeId();

        $assignee = $assigneeId === null ? null : User::findOrFail($assigneeId);

        $this->incidents->assign($incident, $assignee, $request->user());

        return back()->with('status', 'Asignación actualizada.');
    }

    /**
     * Anade una nota a la cronologia de la investigacion.
     */
    public function addNote(StoreIncidentNoteRequest $request, Incident $incident): RedirectResponse
    {
        $this->authorize('addNote', $incident);

        $this->incidents->addNote(
            $incident,
            $request->user(),
            (string) $request->validated('body'),
        );

        return back()->with('status', 'Nota añadida.');
    }

    /**
     * Vincula o desvincula una alerta del incidente.
     */
    public function attachAlert(AttachAlertToIncidentRequest $request, Incident $incident): RedirectResponse
    {
        $this->authorize('manageAlerts', $incident);

        $alert = Alert::findOrFail((int) $request->validated('alert_id'));

        if ($request->boolean('detach')) {
            $this->incidents->detachAlert($incident, $alert);

            return back()->with('status', 'Alerta desvinculada del incidente.');
        }

        $this->incidents->attachAlerts($incident, [$alert->id]);

        return back()->with('status', 'Alerta vinculada al incidente.');
    }

    /**
     * Analistas que pueden recibir un incidente.
     *
     * La consulta se ejecuta una vez por peticion y no por fila: el
     * selector de responsables aparece en la ficha de cada incidente, y
     * repetirla por incidente convertiria el tablero en una prueba de
     * carga sin ganar nada.
     *
     * @return Collection<int, User>
     */
    private function analystOptions(): Collection
    {
        return User::query()
            ->writers()
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    /**
     * Alertas activas que aun no estan vinculadas a este incidente.
     *
     * El limite de 50 evita que un incidente abierta al final de un
     * ataque masivo cargue miles de filas en el selector. Un analista
     * que necesite una alerta mas antigua la encuentra por su pantalla
     * de alertas.
     *
     * @return Collection<int, Alert>
     */
    private function availableAlerts(Incident $incident): Collection
    {
        return Alert::query()
            ->active()
            ->whereDoesntHave('incidents', fn ($query) => $query->where('incidents.id', $incident->id))
            ->orderByDesc('last_seen_at')
            ->limit(50)
            ->get(['id', 'title', 'severity', 'source_ip']);
    }
}
