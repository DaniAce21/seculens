<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\WatchedIp;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Lista de IPs en vigilancia o bloqueadas.
 *
 * Ademas de la gestion manual, sugiere IPs "reincidentes": las que mas
 * aparecen en eventos fallidos y alertas IDS y aun no estan en la lista.
 */
class WatchlistController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function index(): View
    {
        $watched = WatchedIp::query()
            ->with('author:id,name')
            ->orderByDesc('created_at')
            ->get();

        return view('watchlist.index', [
            'watched' => $watched,
            'suggestions' => $this->suggestions($watched->pluck('ip')->all()),
            'levels' => WatchedIp::LEVELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Solo usuarios con permiso de escritura (ADMIN / ANALYST).
        abort_unless($request->user()->canWrite(), 403);

        $data = $request->validate([
            'ip' => ['required', 'ip', Rule::unique('watched_ips', 'ip')],
            'level' => ['required', Rule::in(array_keys(WatchedIp::LEVELS))],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'ip.unique' => 'Esa IP ya está en la lista.',
            'ip.ip' => 'No es una dirección IP válida.',
        ]);

        $entry = new WatchedIp($data);
        $entry->created_by = $request->user()->id;
        $entry->save();

        $this->audit->tryLog(AuditAction::WATCHLIST_ADDED, $request->user(), $entry, [
            'ip' => $entry->ip,
            'level' => $entry->level,
        ]);

        return back()->with('status', "IP {$entry->ip} añadida a la lista.");
    }

    public function destroy(Request $request, WatchedIp $watchedIp): RedirectResponse
    {
        abort_unless($request->user()->canWrite(), 403);

        $ip = $watchedIp->ip;
        $watchedIp->delete();

        $this->audit->tryLog(AuditAction::WATCHLIST_REMOVED, $request->user(), null, ['ip' => $ip]);

        return back()->with('status', "IP {$ip} retirada de la lista.");
    }

    /**
     * IPs con mas actividad sospechosa que aun no estan vigiladas.
     *
     * Suma logins fallidos (security_events) y alertas IDS por IP origen.
     *
     * @param  list<string>  $exclude
     * @return list<array{ip: string, failed_logins: int, ids_alerts: int, total: int}>
     */
    private function suggestions(array $exclude, int $limit = 10): array
    {
        $failed = DB::table('security_events')
            ->where('event_type', 'LOGIN_FAILED')
            ->whereNotNull('source_ip')
            ->selectRaw('CAST(source_ip AS TEXT) as ip, COUNT(*) as total')
            ->groupBy('source_ip')
            ->pluck('total', 'ip');

        $ids = DB::table('ids_alerts')
            ->selectRaw('source_ip as ip, COUNT(*) as total')
            ->groupBy('source_ip')
            ->pluck('total', 'ip');

        $rows = [];

        foreach ($failed->keys()->merge($ids->keys())->unique() as $ip) {
            // PostgreSQL devuelve inet como "1.2.3.4/32" en algunos casos; se normaliza.
            $clean = explode('/', (string) $ip)[0];

            if (in_array($clean, $exclude, true)) {
                continue;
            }

            $f = (int) ($failed[$ip] ?? 0);
            $i = (int) ($ids[$ip] ?? 0);

            // Reincidente = al menos 3 apariciones en total.
            if ($f + $i >= 3) {
                $rows[] = ['ip' => $clean, 'failed_logins' => $f, 'ids_alerts' => $i, 'total' => $f + $i];
            }
        }

        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return array_slice($rows, 0, $limit);
    }
}
