<?php

namespace App\Enums;

/**
 * Tipos de evento de seguridad registrables.
 *
 * La lista es cerrada a proposito. Aceptar cadenas libres permitiria
 * que un endpoint de ingestion guardara 'LOGIN_FALED' por un error de
 * tipeo y el filtro del Event Explorer no lo mostraria nunca, dejando
 * actividad invisible en un panel de seguridad.
 */
enum EventType: string
{
    case LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    case LOGIN_FAILED = 'LOGIN_FAILED';
    case LOGOUT = 'LOGOUT';
    case PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    case ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';
    case API_REQUEST = 'API_REQUEST';
    case SUSPICIOUS_ACTIVITY = 'SUSPICIOUS_ACTIVITY';

    /**
     * Indica si el evento representa un intento fallido.
     *
     * La regla BRUTE_FORCE filtra por event_type en SQL, no por este
     * metodo: filtrar en el motor es la unica forma de no traer miles de
     * filas para descartar despues en PHP. Este metodo existe para las
     * consultas ya acotadas.
     */
    public function isFailure(): bool
    {
        return $this === self::LOGIN_FAILED;
    }

    /**
     * Etiqueta legible para la interfaz.
     */
    public function label(): string
    {
        return match ($this) {
            self::LOGIN_SUCCESS => 'Inicio de sesión exitoso',
            self::LOGIN_FAILED => 'Inicio de sesión fallido',
            self::LOGOUT => 'Cierre de sesión',
            self::PASSWORD_CHANGED => 'Contraseña modificada',
            self::ACCOUNT_LOCKED => 'Cuenta bloqueada',
            self::API_REQUEST => 'Petición a la API',
            self::SUSPICIOUS_ACTIVITY => 'Actividad sospechosa',
        };
    }
}
