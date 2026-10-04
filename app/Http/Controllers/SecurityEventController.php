<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Http\Requests\IndexSecurityEventRequest;
use App\Models\SecurityEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Explorador de eventos de seguridad.
 *
 * Los eventos son inmutables y de solo lectura. No existe ninguna accion
 * de creacion, edicion o borrado en este controller, y esa ausencia es
 * intencionada: los eventos los registra el sistema al ocurrir, no un
 * operador que edita un registro. Exponer una operacion de escritura
 * permitiria fabricar evidencia retrospectiva.
 */
class SecurityEventController extends Controller
{
    /**
     * Listado de eventos con filtros en el servidor.
     */
    public function index(IndexSecurityEventRequest $request): View
    {
        $this->authorize('viewAny', SecurityEvent::class);

        $events = SecurityEvent::query()
            ->ofTypes($this->selectedTypes($request))
            ->fromIp($request->validated('source_ip'))
            ->byUsername($request->validated('username'))
            ->withResult($request->validated('result'))
            ->occurredBetween(
                $this->parseDate($request->validated('date_from'), false),
                $this->parseDate($request->validated('date_to'), true),
            )
            ->orderBy($request->sortColumn(), $request->sortDirection())
            ->paginate($request->perPage())
            ->withQueryString();

        return view('events.index', [
            'events' => $events,
            'filters' => $request->validated(),
            'types' => EventType::cases(),
        ]);
    }

    /**
     * Detalle de un evento, con su metadata en crudo.
     */
    public function show(Request $request, SecurityEvent $event): View
    {
        $this->authorize('view', $event);

        return view('events.show', [
            'event' => $event,
            /*
             * Eventos cercanos de la misma direccion IP.
             *
             * Es la vista que mas ayuda a un analista: ver que mas hizo
             * esa IP en el mismo periodo distingue un ataque automatizado
             * de un fallo de contrasena aislado.
             */
            'relatedEvents' => $event->source_ip === null
                ? collect()
                : SecurityEvent::query()
                    ->where('source_ip', $event->source_ip)
                    ->where('id', '!=', $event->id)
                    ->orderByDesc('occurred_at')
                    ->limit(20)
                    ->get(),
            'relatedAlerts' => $event->alerts()->orderByDesc('last_seen_at')->get(),
        ]);
    }

    /**
     * Tipos de evento a filtrar.
     *
     * Si el usuario no selecciona ninguno, se muestran todos. La
     * ausencia de filtro significa "no me importa el tipo", no "busco
     * eventos sin tipo", que seria un conjunto vacio.
     *
     * @return list<EventType>
     */
    private function selectedTypes(IndexSecurityEventRequest $request): array
    {
        $selected = $request->validated('event_type');

        if (blank($selected)) {
            return EventType::cases();
        }

        return array_values(array_map(
            EventType::from(...),
            (array) $selected,
        ));
    }

    /**
     * Convierte un filtro de fecha a Carbon.
     *
     * El flag de fin de dia existe porque '2026-10-01' como fecha final
     * se interpretaria a las 00:00 y excluiria todo el resto de ese
     * dia. Sin esto, filtrar por el dia de un ataque devolveria los
     * eventos hasta medianoche y se perderia justo lo que se busca.
     */
    private function parseDate(?string $value, bool $endOfDay): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        $date = Carbon::parse($value);

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }
}
