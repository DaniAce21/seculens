<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuario de la plataforma.
 *
 * Los campos asignables y ocultos se declaran con atributos de PHP en
 * lugar de con $fillable y $hidden. Laravel 13 soporta atributos sobre
 * la clase, y declararlos aqui deja el modelo sin listas mutables que
 * alguien pueda alterar en caliente.
 *
 * Modo single-user: no hay login ni bloqueo de cuentas (las columnas
 * failed_login_attempts, locked_until y last_login_at se eliminaron).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Conversiones de tipo.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Incidentes asignados a este usuario.
     */
    public function assignedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'assigned_to');
    }

    /**
     * Notas escritas por este usuario.
     */
    public function incidentNotes(): HasMany
    {
        return $this->hasMany(IncidentNote::class);
    }

    /**
     * Acciones registradas en la bitacora de auditoria.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Indica si el usuario tiene permisos de administrador.
     */
    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    /**
     * Indica si el usuario puede modificar datos.
     */
    public function canWrite(): bool
    {
        return $this->role->canWrite();
    }

    /**
     * Filtra usuarios por rol.
     */
    public function scopeWithRole(Builder $query, UserRole $role): Builder
    {
        return $query->where('role', $role->value);
    }

    /**
     * Filtra usuarios que pueden modificar datos.
     */
    public function scopeWriters(Builder $query): Builder
    {
        return $query->whereIn('role', [
            UserRole::ADMIN->value,
            UserRole::ANALYST->value,
        ]);
    }

    /**
     * Iniciales para el avatar textual de la interfaz.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = array_map(
            fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)),
            array_slice($parts, 0, 2),
        );

        return implode('', $letters);
    }
}
