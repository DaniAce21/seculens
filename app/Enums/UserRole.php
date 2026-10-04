<?php

namespace App\Enums;

/**
 * Rol del usuario dentro de la plataforma.
 *
 * El rol define el alcance maximo de acciones permitidas. Los permisos
 * finos se resuelven en las Policies, no aqui: mantener el control de
 * acceso en un unico lugar evita la situacion habitual en la que el
 * middleware y la policy discrepan y alguien asume el permiso mas
 * amplio de los dos.
 */
enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case ANALYST = 'ANALYST';
    case VIEWER = 'VIEWER';

    /**
     * Indica si el rol puede modificar datos.
     *
     * VIEWER es de solo lectura. Se usa para decidir si una accion es
     * destructiva o restrictiva sin enumerar cada rol.
     */
    public function canWrite(): bool
    {
        return $this !== self::VIEWER;
    }

    /**
     * Indica si el rol administra la plataforma.
     *
     * Solo ADMIN gestiona usuarios, roles y documentacion de la API.
     */
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Indica si el rol opera incidentes.
     *
     * ADMIN puede hacerlo tambien, de forma implicita.
     */
    public function canHandleIncidents(): bool
    {
        return $this === self::ADMIN || $this === self::ANALYST;
    }

    /**
     * Etiqueta legible para la interfaz.
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrador',
            self::ANALYST => 'Analista',
            self::VIEWER => 'Observador',
        };
    }

    /**
     * Descripcion de las capacidades del rol.
     *
     * Se muestra en la interfaz para que un usuario nuevo entienda que
     * puede hacer sin tener que leer el codigo.
     */
    public function description(): string
    {
        return match ($this) {
            self::ADMIN => 'Acceso completo: usuarios, roles, alertas, incidentes y configuración.',
            self::ANALYST => 'Investiga alertas, gestiona incidentes y agrega notas.',
            self::VIEWER => 'Solo lectura sobre eventos, alertas e incidentes.',
        };
    }
}
