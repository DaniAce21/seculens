<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\View\View;

/**
 * Panel principal de la plataforma.
 *
 * El dashboard es el primer vistazo de un analista al estado del
 * sistema. No calcula agregaciones aqui: las delega a DashboardService
 * porque son consultas pesadas que deben ejecutarse de forma eficiente
 * y reutilizarse si en el futuro aparece una API equivalente.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function index(): View
    {
        return view('dashboard.index', [
            'summary' => $this->dashboard->summary(),
            'alertsBySeverity' => $this->dashboard->alertsBySeverity(),
            'alertsByStatus' => $this->dashboard->alertsByStatus(),
            'eventsByType' => $this->dashboard->eventsByType(),
            'incidentsByStatus' => $this->dashboard->incidentsByStatus(),
            'topFailedLoginIps' => $this->dashboard->topFailedLoginIps(),
            'dailyEventVolume' => $this->dashboard->dailyEventVolume(),
        ]);
    }
}
