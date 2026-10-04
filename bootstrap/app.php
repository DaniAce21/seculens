<?php

use App\Http\Middleware\AuthenticateSensor;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\AutoLogin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Alias para el control de acceso por rol.
         *
         * 'role' restringe rutas completas (por ejemplo, la gestion de
         * usuarios). Para acciones sobre una entidad concreta se usan
         * las policies, de modo que la regla de autorizacion de cada
         * recurso viva en un unico lugar.
         */
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'security.headers' => SecurityHeaders::class,
            'autologin' => AutoLogin::class,
            // Token por sensor para la API de alertas IDS (routes/api.php).
            'sensor.token' => AuthenticateSensor::class,
        ]);

        /*
         * Cabeceras de seguridad en todas las respuestas.
         *
         * Se aplican como middleware global y no en el reverse proxy:
         * acompanar a la aplicacion garantiza que sigan presentes si
         * el despliegue olvida configurarlas ahi.
         */
        $middleware->append(SecurityHeaders::class);

        /*
         * AutoLogin (modo single-user) es global para ejecutarse SIEMPRE antes
         * que el middleware 'auth' de las rutas (dentro de un grupo, Laravel lo
         * reordena por prioridad y 'auth' quedaria primero). El propio
         * middleware ignora las rutas api/*, que se autentican por token de sensor.
         */
        $middleware->append(AutoLogin::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
