<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bitacora de auditoria.
 *
 * La bitacora es append-only y de solo lectura. En esta vista el
 * administrador puede reconstruir la cronologia de acciones sensibles,
 * desde un inicio de sesion hasta el cambio de rol de un usuario. No se
 * expone ninguna accion de edicion ni de borrado.
 */
class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $auditLogs = AuditLog::query()
            ->with('user:id,name')
            ->when(
                $request->filled('action'),
                fn ($query) => $query->forAction(AuditAction::tryFrom((string) $request->input('action')) ?? AuditAction::LOGIN),
            )
            ->when(
                $request->filled('user_id'),
                fn ($query) => $query->where('user_id', $request->integer('user_id')),
            )
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $auditLogs,
            'actions' => AuditAction::cases(),
            'filters' => $request->only(['action', 'user_id']),
        ]);
    }
}
