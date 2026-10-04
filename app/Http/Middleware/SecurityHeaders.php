<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anade cabeceras de seguridad a todas las respuestas HTTP.
 *
 * Se aplica globalmente en lugar de configurarse en el servidor web o
 * en el reverse proxy por una razon concreta: si acompan a la
 * aplicacion, sigue presente aunque el despliegue no lo haya
 * configurado. Una cabecera de seguridad que depende de que alguien la
 * recuerde en la infraestructura es una cabecera que acabara faltando.
 *
 * Content-Security-Policy se construye en modo restrictivo: los scripts
 * solo pueden venir de la propia aplicacion. Como excepcion se permiten
 * estilos y fuentes de Google Fonts y jsDelivr (Bootstrap Icons), que
 * son los unicos recursos de terceros que usan las vistas.
 */
class SecurityHeaders
{
    /**
     * Ejecuta el middleware.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /*
         * nosniff impide que el navegador interprete una respuesta con
         * Content-Type distinto del declarado, lo que reduce el impacto
         * de un archivo subido que se sirviera como texto ejecutable.
         */
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        /*
         * DENY impide el framing. Para una herramienta de seguridad es
         * lo correcto: un panel de analisis no debe poder incrustarse
         * en un sitio de phishing para capturar los clics del analista.
         */
        $response->headers->set('X-Frame-Options', 'DENY');

        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=()'
        );

        /*
         * Politica de contenido.
         *
         * 'unsafe-inline' en estilos es necesario porque Vite inyecta una
         * hoja de estilos en linea durante el desarrollo. En produccion
         * los assets se sirven como archivos externos, de modo que la
         * politica puede endurecerse quitando esta excepcion.
         *
         * 'unsafe-inline' en scripts no se permite: el JavaScript de la
         * aplicacion se carga siempre desde archivos propios (por eso el
         * panel /ids carga public/js/ids-panel.js en vez de un <script>
         * en linea, y no se usan atributos onclick/onsubmit).
         *
         * Origenes externos permitidos SOLO para estilos y fuentes:
         *  - fonts.googleapis.com / fonts.gstatic.com: tipografias Inter
         *    y JetBrains Mono.
         *  - cdn.jsdelivr.net: hoja de estilos y fuente de Bootstrap Icons.
         * Antes la politica los bloqueaba y toda la interfaz se veia sin
         * iconos ni tipografia. Ningun origen externo puede ejecutar JS.
         */
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "img-src 'self' data:",
            "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]));

        /*
         * HSTS solo en producción. En local, enviar esta cabecera
         * y dejarian de poder alcanzar la aplicacion por http, lo que
         * complicaria el desarrollo.
         */
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
