# SecuLens

**Security Monitoring & Analytics Platform**

Plataforma de monitoreo y analisis de seguridad construida con Laravel y
PostgreSQL. Registra eventos de seguridad, aplica reglas de deteccion
sobre ellos, genera alertas y permite a un analista investigar y
resolver el hallazgo.

Proyecto de portafolio para Ingenieria en Ciberseguridad / Analista
Programador.

---

## Requisitos

| Componente | Version |
|---|---|
| PHP | 8.3 o superior (con `pdo_pgsql`) |
| Composer | 2.x |
| PostgreSQL | 14 o superior |
| Node.js | 20 o superior (solo para compilar assets) |

## Puesta en marcha

```bash
# 1. Dependencias de PHP
composer install

# 2. Configuracion
cp .env.example .env
php artisan key:generate

# 3. Base de datos
# Crear la base 'seculens' y ajustar DB_USERNAME / DB_PASSWORD en .env
php artisan migrate --seed

# 4. Frontend
npm install
npm run build

# 5. Servidor
php artisan serve
```

La aplicacion queda disponible en `http://localhost:8000`.

### Base de datos de pruebas

Las pruebas corren contra PostgreSQL, no contra SQLite. La razon esta
documentada en [docs/architecture.md](docs/architecture.md): la
migracion `alerts_active_unique` crea un indice unico parcial con
sintaxis nativa que SQLite no reproduce con fidelity, de modo que una
prueba sobre SQLite no ejercitaria la restriccion de integridad.

```bash
createdb -U postgres seculens_testing
php artisan test
```

## Estructura

```
app/
├── Enums/          Conjuntos cerrados: severidad, estado de alerta
├── Http/           Controllers, Requests, Middleware
├── Models/         Entidades, relaciones, scopes
├── Policies/       Autorizacion en el servidor
└── Services/       Deteccion, alertas, incidentes
database/
├── factories/      Datos de prueba
├── migrations/     Esquema
└── seeders/        Escenario de laboratorio
resources/views/    Blade: layouts, components, y vistas por modulo
public/
├── css/            CSS puro, separado por responsabilidad
└── js/             JavaScript vanilla, separado por modulo
tests/
├── Feature/        Pruebas que atraviesan HTTP y base de datos
└── Unit/           Pruebas de logica aislada
docs/               Documentacion tecnica
```

## Documentacion

| Documento | Contenido |
|---|---|
| [docs/architecture.md](docs/architecture.md) | Capas, flujo, decisiones de PostgreSQL, concurrencia |
| [docs/detection-rules.md](docs/detection-rules.md) | Reglas de deteccion, parametros y casos limite |
| [docs/security.md](docs/security.md) | Medidas de seguridad aplicadas y pendientes |

## Sensores IDS (API)

La API `/api/v1/alerts` exige el token de un sensor registrado. Solo se guarda
el hash del token; el token en claro se muestra una única vez al crearlo.

```bash
php artisan ids:sensor-create sensor-dmz     # crea el sensor y muestra su token
php artisan ids:sensor-list                  # sensores, último uso y estado
php artisan ids:sensor-revoke sensor-dmz     # el token deja de funcionar al instante
```

Ejemplo de envío de una alerta desde un sensor:

```bash
curl -X POST http://localhost:8000/api/v1/alerts \
  -H "Authorization: Bearer ids_xxxxxxxx" -H "Accept: application/json" \
  -d severity=high -d alert_type="Port Scan" \
  -d source_ip=45.33.32.156 -d destination_ip=10.0.0.5 -d signature="ET SCAN Nmap"
```

Límite: 120 peticiones por minuto. Sin token o con un token revocado la respuesta es `401`.

## Exportaciones e informes

- **CSV por día / semana / mes / año** en Incidentes, Eventos (respeta los filtros),
  Alertas y el panel IDS. Cada exportación queda registrada en Auditoría.
- **Informe imprimible** en *Análisis → Informes*: botón *Imprimir / Guardar PDF*.
- **Informe por correo** con los CSV adjuntos:

```bash
# .env:  REPORT_MAIL_TO="soc@empresa.com,jefe@empresa.com"
php artisan reports:send                          # semana anterior
php artisan reports:send --period=month --date=2026-09-01 --to=yo@empresa.com
php artisan schedule:work                         # envío automático cada lunes 08:00
```

## Pruebas

```bash
php artisan test
php artisan test --filter=DetectionServiceTest
```

Las pruebas de deteccion cubren los limites exactos de cada regla: el
umbral inferior, el superior, el borde de la ventana temporal y los tipos
de evento que la regla debe ignorar.

## Estado del proyecto

| Fase | Alcance | Estado |
|---|---|---|
| 0 | Auditoria del proyecto existente | Completada |
| 1 | Nucleo: alertas, indices, pruebas de deteccion | Completada |
| 2 | Gestion de alertas (CRUD, filtros, transiciones) | Completada |
| 3 | Incidentes | Completada |
| 4 | Autenticacion y RBAC | Completada |
| 5 | API REST y OpenAPI | Completada |
| 6-8 | Frontend Blade, JavaScript, CSS | Completada |
| 9 | Suite de pruebas completa | Completada |
| 10 | Endurecimiento de seguridad | Completada |
| 11 | Registro de auditoria | Completada |
| 12 | Documentacion final | Completada |

## Reglas de deteccion activas

**BRUTE_FORCE** — 5 o mas intentos `LOGIN_FAILED` desde la misma IP
dentro de 5 minutos. Severidad `HIGH`.

Detalle completo en [docs/detection-rules.md](docs/detection-rules.md).

## Licencia

MIT
