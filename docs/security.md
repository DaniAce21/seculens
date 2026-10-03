# Seguridad

Measures aplicadas y pendientes en SecuLens. El objetivo de este
documento es que cada afirmacion sobre seguridad corresponda a una
decision concreta y verificable, no a una practica habitual.

## Aplicadas

### Integridad referencial en el motor

Las relaciones entre alertas y eventos se declara con claves foraneas
reales y `cascadeOnDelete`. Borrar una alerta elimina su evidencia
vinculada en la misma operacion, evitando registros huerfanos que
falsearian el conteo de eventos de una alerta.

La tabla pivote `alert_security_event` usa clave primaria compuesta
sobre `(alert_id, security_event_id)`, que impide que un evento se
registre dos veces dentro de la misma alerta. Sin esa restriccion, un
`event_count` inflado seria indistinguible de un ataque mas intenso.

### Unico parcial sobre alertas activas

```sql
CREATE UNIQUE INDEX alerts_active_unique
ON alerts (detection_rule, source_ip)
WHERE status IN ('OPEN', 'ACKNOWLEDGED')
```

Impide que existan dos alertas activas para el mismo ataque. Se resuelve
en el motor y no en el codigo de aplicacion, de modo que la integridad no
depende de que todos los caminos de escritura respeten la regla.

El indice es parcial a proposito: las alertas resueltas quedan fuera,
porque un ataque que se repite debe generar una alerta nueva y
trazable.

### Validacion de direcciones IP en la base

`source_ip` usa el tipo `inet` de PostgreSQL. El motor normaliza IPv4 e
IPv6 a formas canonicas y rechaza entradas invalidas antes de que
alcancen la capa de aplicacion.

Una comparacion entre una IP canonica y una forma alternativa resuelve
correctamente en lugar de fallar en silencio, lo que importa en un
sistema cuya funcion es detectar origenes.

### Asignacion masiva restrictiva

`Alert::$fillable` admite unicamente `title`, `description` y `status`.
La severidad, la IP de origen, la regla de deteccion y la ventana
temporal quedan fuera porque los calcula el motor de deteccion.

Un cliente no puede fabricar una alerta `CRITICAL` desde una IP que el
sistema nunca observo. `AlertService` asigna esos campos atributo por
atributo de forma deliberada.

### Evidencia obligatoria

`AlertService` lanza una excepcion si una deteccion confirmada no tiene
eventos que la respalden, y la transaccion revierte la alerta. Se
prefiere no tener registro a tener una alerta que un analista no puede
reconstruir.

### Las pruebas usan una base separada

`seculens_testing` es independiente de `seculens`. Las pruebas nunca
borran ni alteran los datos del laboratorio.

## Pendientes

### Fase 4 — Autenticacion

- [ ] Login y logout con sesiones
- [ ] Proteccion de rutas
- [ ] Hash de contrasenas (Laravel ya aplica bcrypt con `casts`)
- [ ] Rate limiting en el endpoint de login
- [ ] Bloqueo de cuenta tras intentos fallidos

### Fase 4 — RBAC

- [ ] Roles `ADMIN`, `ANALYST`, `VIEWER`
- [ ] Policies evaluadas en el servidor
- [ ] Ninguna decision de autorizacion en Blade ni en JavaScript

### Fase 10 — Endurecimiento

- [ ] Cabeceras de seguridad (CSP, X-Frame-Options, HSTS)
- [ ] `SESSION_ENCRYPT=true`
- [ ] `APP_DEBUG=false` en produccion
- [ ] Proteccion CSRF en formularios y peticiones AJAX
- [ ] Revision de exposure de excepciones
- [ ] Politica de registros: que se guarda y que no

### Fase 11 — Auditoria

- [ ] Registro de acciones sensibles: login, logout, cambios de estado
      de alerta, cambios de incidente, cambios de rol
- [ ] Registro append-only, sin modificacion posterior

## Reglas permanentes

1. **La autorizacion se resuelve en el servidor.** Ocultar un boton es
   una cortesia visual, nunca una proteccion.
2. **La validacion de JavaScript es solo UX.** La regla real la aplica
   Laravel.
3. **Ningun secreto en Git.** `.env` esta excluido y `APP_KEY` nunca se
   versiona.
4. **Toda entrada se valida en el servidor**, incluidos los endpoints
   consumidos por `fetch()`.
5. **Los registros no contienen credenciales.** Ni contrasenas, ni
   tokens, ni claves de API.
