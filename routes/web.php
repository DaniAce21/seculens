<?php

use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\IdsWebController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SecurityAlertController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

// Modo single-user: no hay pantalla de login. Se nombra 'login' porque el
// middleware 'auth' redirige a route('login') si no hay sesion.
Route::get('/login', function () {
    return redirect('/');
})->name('login');

Route::middleware(['auth', 'security.headers', 'autologin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::get('/alerts', [SecurityAlertController::class, 'index'])->name('alerts.index');
    Route::get('/alerts/{alert}', [SecurityAlertController::class, 'show'])->name('alerts.show');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    // Gestion del incidente (los metodos ya existian en el controlador pero
    // no tenian ruta, asi que desde la ficha no se podia hacer nada).
    Route::patch('/incidents/{incident}/status', [IncidentController::class, 'changeStatus'])->name('incidents.status');
    Route::patch('/incidents/{incident}/assign', [IncidentController::class, 'assign'])->name('incidents.assign');
    Route::post('/incidents/{incident}/notes', [IncidentController::class, 'addNote'])->name('incidents.notes');
    Route::post('/incidents/{incident}/alerts', [IncidentController::class, 'attachAlert'])->name('incidents.alerts');

    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');

    // Exportacion CSV por periodo: ?period=day|week|month|year&date=YYYY-MM-DD
    Route::get('/export/incidents', [ExportController::class, 'incidents'])->name('export.incidents');
    Route::get('/export/events', [ExportController::class, 'events'])->name('export.events');
    Route::get('/export/alerts', [ExportController::class, 'alerts'])->name('export.alerts');
    Route::get('/export/ids-alerts', [ExportController::class, 'idsAlerts'])->name('export.ids-alerts');

    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    // La documentacion vive en su propio controlador (UserController no tiene apiDocs()).
    // Se nombra 'api-docs.index' porque es el nombre que usa el menu lateral.
    Route::get('/api-docs', [ApiDocumentationController::class, 'index'])->name('api-docs.index');

    // Panel IDS renderizado por Blade con datos reales de la tabla ids_alerts.
    // El panel estatico (simulador) esta en public/ids-panel/index.html y lo
    // sirve directamente el servidor web, sin pasar por estas rutas.
    Route::get('/ids', [IdsWebController::class, 'index'])->name('ids.index');
    // JSON con las alertas nuevas para el refresco en tiempo real del panel.
    Route::get('/ids/feed', [IdsWebController::class, 'feed'])->name('ids.feed');
    // Acciones desde el modal de inspeccion.
    Route::patch('/ids/{idsAlert}/status', [IdsWebController::class, 'updateStatus'])->name('ids.status');
    Route::post('/ids/{idsAlert}/incident', [IdsWebController::class, 'createIncident'])->name('ids.incident');

    // Informe imprimible por periodo (?period=day|week|month|year&date=...).
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // Lista de IPs en vigilancia.
    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::delete('/watchlist/{watchedIp}', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');
});
