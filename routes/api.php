<?php

use App\Http\Controllers\IdsController;
use Illuminate\Support\Facades\Route;

// Nota: se elimino la ruta /api/user con auth:sanctum porque Sanctum no esta
// instalado en el proyecto y la peticion terminaba siempre en error 500.

/*
 * API de sensores IDS.
 *
 * - sensor.token: exige "Authorization: Bearer <token>" de un sensor activo
 *   (se crean con: php artisan ids:sensor-create <nombre>).
 * - throttle:120,1: maximo 120 peticiones por minuto por cliente, para que
 *   un sensor defectuoso o un token robado no inunde la base de datos.
 */
Route::prefix('v1')->middleware(['sensor.token', 'throttle:120,1'])->group(function () {
    Route::get('/alerts', [IdsController::class, 'index']);
    Route::post('/alerts', [IdsController::class, 'store']);
    Route::get('/alerts/stats', [IdsController::class, 'stats']);
});
