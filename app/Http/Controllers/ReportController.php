<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use App\Services\ReportService;
use App\Support\Period;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Informe de seguridad de un periodo (dia, semana, mes o año).
 *
 * Es una pagina HTML preparada para imprimir: el boton "Imprimir / PDF"
 * abre el dialogo del navegador, donde se elige "Guardar como PDF". Asi
 * no hace falta ninguna libreria de PDF en el servidor.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SecurityEvent::class);

        // Sin parametros se muestra la semana actual.
        $period = Period::fromRequest($request, defaultUnit: 'week');

        return view('reports.index', $this->reports->build($period) + [
            'periodLabels' => Period::LABELS,
            'date' => $request->query('date', now()->format('Y-m-d')),
        ]);
    }
}
