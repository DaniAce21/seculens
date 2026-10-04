<?php

namespace App\Providers;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Policies\AlertPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\IncidentPolicy;
use App\Policies\SecurityEventPolicy;
use App\Policies\UserPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Configuracion de la aplicacion.
 *
 * Las policies se registran de forma explicita en lugar de depender de
 * la convencion de nombres de Laravel. La convencion funciona, pero un
 * archivo renombrado deja de aplicar autorizacion sin fallar: el
 * controller recibiria null y la comprobacion pasaria a evaluarse
 * contra un valor inesperado. El registro explicito convierte ese
 * silencio en un error visible.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Mapa de modelo a policy.
     *
     * @var array<class-string, class-string>
     */
    private array $policies = [
        Alert::class => AlertPolicy::class,
        Incident::class => IncidentPolicy::class,
        SecurityEvent::class => SecurityEventPolicy::class,
        User::class => UserPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerGates();

        // Plantilla de paginacion propia (en español y con las clases de app.css).
        Paginator::defaultView('vendor.pagination.seculens');
    }

    /**
     * Registra las policies de autorizacion.
     */
    private function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Registra las capacidades globales de la aplicacion.
     *
     * Los gates de alcance global cubren preguntas que no dependen de
     * una entidad concreta: "es administrador" no es una propiedad de
     * una alerta ni de un incidente.
     */
    private function registerGates(): void
    {
        Gate::define('access-api-documentation', function (User $user) {
            // Delegamos en UserPolicy indicando la clase del modelo.
            return $user->can('viewApiDocumentation', User::class);
        });
    }
}
