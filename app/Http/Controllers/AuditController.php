<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Vista web de la bitacora de auditoria.
 */
class AuditController extends Controller
{
    /**
     * Listado paginado de registros de auditoria, del mas reciente al
     * mas antiguo, con filtro opcional por usuario.
     */
    public function index(Request $request): View
    {
        $query = AuditLog::query()
            // Se precarga el usuario para no lanzar una consulta por fila.
            ->with('user')
            ->latest('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return view('audit.index', [
            // La vista itera sobre $logs; antes se enviaba $audits y la
            // pagina fallaba con "Undefined variable $logs".
            'logs' => $query->paginate(20)->withQueryString(),
        ]);
    }
}
