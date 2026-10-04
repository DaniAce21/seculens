<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Documentacion de la API interna.
 *
 * Se restringe a ADMIN porque el esquema describe toda la superficie
 * expuesta por la aplicacion, incluidos endpoints que manipulan roles
 * y auditoria. Exponerlo a otros roles aumentaria la superficie de
 * reconocimiento para un usuario con intenciones maliciosas.
 */
class ApiDocumentationController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        // Se pasa User::class para que Laravel resuelva la habilidad en
        // UserPolicy. Sin el segundo argumento busca un Gate llamado
        // 'viewApiDocumentation', que no existe, y siempre respondia 403.
        $this->authorize('viewApiDocumentation', User::class);

        /*
         * Registro que el administrador consulto la documentacion.
         *
         * No es una accion de escritura sobre datos de negocio, pero es
         * una accion de administracion sensible: saber quien accedio a la
         * especificacion de la API es util para reconstruir la cadena de
         * acciones tras una exploracion.
         */
        $this->audit->tryLog(AuditAction::API_DOCUMENTATION_VIEWED, $request->user());

        return view('api-docs.index');
    }
}
