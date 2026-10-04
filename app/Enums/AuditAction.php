<?php

namespace App\Enums;

/**
 * Acciones sensibles que quedan registradas en el log de auditoria.
 *
 * El criterio de inclusion es: cualquier accion que cambie el estado de
 * la plataforma o que un atacante querria ejecutar sin dejar rastro.
 * Las lecturas no se registran porque llenarian la tabla sin aportar
 * informacion util para reconstruir una intrusion.
 */
enum AuditAction: string
{
    case LOGIN = 'LOGIN';
    case LOGIN_FAILED = 'LOGIN_FAILED';
    case LOGOUT = 'LOGOUT';

    case ALERT_ACKNOWLEDGED = 'ALERT_ACKNOWLEDGED';
    case ALERT_REOPENED = 'ALERT_REOPENED';
    case ALERT_RESOLVED = 'ALERT_RESOLVED';

    case INCIDENT_CREATED = 'INCIDENT_CREATED';
    case INCIDENT_UPDATED = 'INCIDENT_UPDATED';
    case INCIDENT_STATUS_CHANGED = 'INCIDENT_STATUS_CHANGED';
    case INCIDENT_ASSIGNED = 'INCIDENT_ASSIGNED';
    case INCIDENT_NOTE_ADDED = 'INCIDENT_NOTE_ADDED';

    case USER_CREATED = 'USER_CREATED';
    case USER_UPDATED = 'USER_UPDATED';
    case ROLE_CHANGED = 'ROLE_CHANGED';

    case API_DOCUMENTATION_VIEWED = 'API_DOCUMENTATION_VIEWED';

    // Exportacion masiva de datos (incidentes/eventos a CSV). Se audita
    // porque sacar datos en bloque es justo lo que haria un atacante.
    case DATA_EXPORTED = 'DATA_EXPORTED';

    // Lista de IPs en vigilancia.
    case WATCHLIST_ADDED = 'WATCHLIST_ADDED';
    case WATCHLIST_REMOVED = 'WATCHLIST_REMOVED';

    // Cambio de estado de una alerta IDS (Nueva / En Investigacion / Mitigada).
    case IDS_ALERT_STATUS_CHANGED = 'IDS_ALERT_STATUS_CHANGED';

    // Alta o revocacion de un sensor IDS con acceso a la API.
    case SENSOR_CREATED = 'SENSOR_CREATED';
    case SENSOR_REVOKED = 'SENSOR_REVOKED';

    /**
     * Indica si la accion modifica datos.
     *
     * Permite presentar las entradas de escritura de forma destacada en
     * la vista de auditoria.
     */
    public function isMutation(): bool
    {
        return ! in_array($this, [self::LOGIN, self::LOGOUT, self::API_DOCUMENTATION_VIEWED, self::DATA_EXPORTED], true);
    }

    /**
     * Agrupa la accion por area funcional.
     *
     * Permite filtrar la bitacora sin recorrer todas las acciones.
     */
    public function category(): string
    {
        return match ($this) {
            self::LOGIN, self::LOGIN_FAILED, self::LOGOUT => 'authentication',
            self::ALERT_ACKNOWLEDGED, self::ALERT_REOPENED, self::ALERT_RESOLVED => 'alerts',
            self::INCIDENT_CREATED, self::INCIDENT_UPDATED, self::INCIDENT_STATUS_CHANGED,
            self::INCIDENT_ASSIGNED, self::INCIDENT_NOTE_ADDED => 'incidents',
            self::USER_CREATED, self::USER_UPDATED, self::ROLE_CHANGED => 'users',
            self::API_DOCUMENTATION_VIEWED, self::DATA_EXPORTED,
            self::SENSOR_CREATED, self::SENSOR_REVOKED => 'system',
            self::WATCHLIST_ADDED, self::WATCHLIST_REMOVED => 'watchlist',
            self::IDS_ALERT_STATUS_CHANGED => 'alerts',
        };
    }

    /**
     * Descripcion legible para la bitacora.
     */
    public function label(): string
    {
        return match ($this) {
            self::LOGIN => 'Inicio de sesión',
            self::LOGIN_FAILED => 'Intento de sesión fallido',
            self::LOGOUT => 'Cierre de sesión',
            self::ALERT_ACKNOWLEDGED => 'Alerta reconocida',
            self::ALERT_REOPENED => 'Alerta reabierta',
            self::ALERT_RESOLVED => 'Alerta resuelta',
            self::INCIDENT_CREATED => 'Incidente creado',
            self::INCIDENT_UPDATED => 'Incidente actualizado',
            self::INCIDENT_STATUS_CHANGED => 'Estado de incidente cambiado',
            self::INCIDENT_ASSIGNED => 'Incidente asignado',
            self::INCIDENT_NOTE_ADDED => 'Nota agregada al incidente',
            self::USER_CREATED => 'Usuario creado',
            self::USER_UPDATED => 'Usuario actualizado',
            self::ROLE_CHANGED => 'Rol modificado',
            self::API_DOCUMENTATION_VIEWED => 'Documentación de la API consultada',
            self::DATA_EXPORTED => 'Datos exportados',
            self::WATCHLIST_ADDED => 'IP añadida a vigilancia',
            self::WATCHLIST_REMOVED => 'IP retirada de vigilancia',
            self::IDS_ALERT_STATUS_CHANGED => 'Estado de alerta IDS cambiado',
            self::SENSOR_CREATED => 'Sensor IDS registrado',
            self::SENSOR_REVOKED => 'Sensor IDS revocado',
        };
    }
}
