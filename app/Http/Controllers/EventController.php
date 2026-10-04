<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Models\SecurityEvent;
use App\Support\EventFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Explorador de eventos de seguridad.
 */
class EventController extends Controller
{
    /**
     * Listado paginado con filtros por tipo, IP, usuario, resultado y fechas.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SecurityEvent::class);

        $filters = EventFilters::fromRequest($request);

        $events = EventFilters::apply(SecurityEvent::query(), $filters)
            ->orderByDesc('occurred_at')
            ->paginate(20)
            // Mantiene los filtros al cambiar de pagina.
            ->withQueryString();

        return view('events.index', [
            'events' => $events,
            'filters' => $filters,
            'types' => EventType::cases(),
            // Valores distintos de "resultado" para el desplegable.
            'results' => SecurityEvent::query()->whereNotNull('result')->distinct()->orderBy('result')->pluck('result'),
        ]);
    }

    public function show(SecurityEvent $event): View
    {
        $this->authorize('view', $event);

        return view('events.show', [
            'event' => $event,
        ]);
    }
}
