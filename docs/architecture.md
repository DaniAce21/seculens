# Arquitectura

## Objetivo

SecuLens es una plataforma de monitoreo y analisis de seguridad. Registra
eventos de seguridad, aplica reglas de deteccion sobre ellos, genera
alertas y permite a un analista investigar y resolver el hallazgo.

## Flujo principal

```
SECURITY EVENT
      |
      v
DETECTION RULE          DetectionService
      |                 "¿este conjunto de eventos cumple un patron?"
      v
ALERT                   AlertService
      |                 "traduzco la deteccion en un registro persistente"
      v
INCIDENT                (Fase 3)
      |
      v
INVESTIGATION -> RESOLUTION
```

Los eventos son hechos inmutables. Las alertas son la agrupacion de
eventos que comparten una causa raiz. Los incidentes seran el contenedor
de trabajo del analista sobre una o mas alertas.

## Capas

```
routes/            Solo define URLs y middleware. Sin logica de negocio.
app/Http/          Controllers: reciben peticion, delegan, devuelven respuesta.
app/Http/Requests  Validacion de entrada. La validacion real vive aqui.
app/Services/      Logica de negocio: deteccion, alertas, incidentes.
app/Models/        Entidades, relaciones, scopes y casts.
app/Policies/      Autorizacion. Se evalua en el servidor, nunca en la vista.
app/Enums/         Conjuntos cerrados de valores del dominio.
```

La regla que gobierna el flujo: **los controllers no contienen reglas de
negocio y los services no contienen nada de presentacion**. Un controller
que decide si una alerta es grave, o un service que devuelve HTML, indican
que la frontera se cruzo en algun punto.

## Frontend

Blade + JavaScript vanilla + CSS puro. Sin SPA, sin framework de
componentes.

| Capa | Responsabilidad |
|---|---|
| Blade | estructura, contenido, formularios, tablas, navegacion |
| JavaScript | interaccion, eventos, `fetch`, modales, filtros dinamicos |
| CSS | color, layout, espaciado, tipografia, responsive, estados |
| PHP | negocio, autenticacion, autorizacion, datos, validacion |

Prohibido: estilos inline, bloques `<style>` o `<script>` grandes dentro
de Blade, SQL en vistas y reglas de negocio en JavaScript.

La autorizacion nunca se resuelve en el navegador. Ocultar un boton es
una cortesia visual; el rechazo real ocurre en el servidor.

## Base de datos

PostgreSQL, no SQLite. La eleccion es tecnica, no de preferencia:

- La columna `source_ip` usa el tipo `inet`, que normaliza y valida
  direcciones IPv4 e IPv6 en el motor.
- La migracion `alerts_active_unique` crea un indice unico **parcial**
  mediante SQL nativo, para garantizar que no existan dos alertas activas
  con la misma regla e IP. El constructor de esquemas de Laravel no
  genera indices parciales.
- Por el mismo motivo, `phpunit.xml` apunta las pruebas a una base
  PostgreSQL dedicada (`seculens_testing`). Verificar el comportamiento
  real del motor de produccion vale mas que la velocidad de SQLite.

### Zona horaria

`app.timezone` es UTC mientras que el servidor PostgreSQL opera en
America/Santiago. Las columnas son `timestamp without time zone`.

Consecuencia: todos los valores que Laravel escribe son UTC, y las
consultas que construye tambien interpretan los parametros como UTC. El
sistema es coherente mientras todas las consultas pasen por Eloquent o
por el constructor de Laravel. **Al escribir SQL crudo hay que castear
explicitamente** (`?::timestamptz`) para no comparar fechas de zonas
distintas.

Todas las marcas de tiempo de la aplicacion son UTC por convencion. Es
la practica habitual en investigacion forense, donde comparar el tiempo
de un evento con la hora local del analista genera conclusiones erroneas.

## Detalle de PostgreSQL: el tipo inet

`source_ip` es `inet`. Postgres normaliza la direccion, de modo que
`192.168.100.50`, `192.168.100.050` y la forma con CIDR se reducen a
formas canonicas comparables, y una entrada invalida es rechazada en la
capa de base de datos.

Al comparar contra una constante en SQL crudo hay que castear el lado
derecho a `inet`; de lo contrario la comparacion puede resolverse contra
otro tipo y devolver `false` de forma silenciosa. Eloquent lo maneja
correctamente.

## Indices

| Indice | Proposito |
|---|---|
| `security_events_detection_index` | `(event_type, source_ip, occurred_at)`. Permite que la regla BRUTE_FORCE resuelva la consulta sin recorrer la tabla. |
| `alerts_active_unique` | Unico parcial sobre `(detection_rule, source_ip)` donde el estado no es `RESOLVED`. |
| `alerts_status_severity_index` | Filtros del tablero de alertas. |
| `alerts_last_seen_at_index` | Orden cronologico del listado de alertas. |

Cada indice responde a una consulta concreta de la aplicacion. No se
crean indices "por si acaso".

## Decisiones de concurrencia

`AlertService::createFromDetection()` opera dentro de una transaccion y
bloquea la fila de la alerta activa con `lockForUpdate`. Esto evita el
trabajo duplicado, pero **no** es la garantia de unicidad: entre el
`SELECT` y el `INSERT` puede ocurrir otra peticion. La garantia real es el
indice unico parcial, que PostgreSQL evalua de forma atomica.

El bloqueo existe para no repetir trabajo; el indice existe para que la
integridad no dependa de la disciplina del codigo.

## Decisiones de modelado

**Enums en PHP, varchar en la base.** `AlertSeverity` y `AlertStatus` son
enums respaldados por string. Persistirlos como `varchar` mantiene las
migraciones simples, y la garantia de que solo existan valores validos ya
la aporta el enum. Replicar los tipos en PostgreSQL duplicaria la fuente
de verdad sin aportar proteccion adicional frente a la entrada del
cliente, que se valida en el servidor.

**Asignacion masiva restrictiva.** `Alert::$fillable` solo admite `title`,
`description` y `status`. La severidad, la IP, la regla y la ventana
temporal los calcula el motor de deteccion y no deben poder llegar desde
una peticion. `AlertService` asigna esos campos atributo por atributo de
forma deliberada: usar `create()` con el mapa completo los descartaria en
silencio.

**La severidad nunca baja.** Si una deteccion posterior es menos grave,
la alerta conserva su severidad. El sistema no puede saber que el riesgo
se mitigo solo porque el trafico bajo, y bajarla sugeriria lo contrario.

**Una alerta por ataque en curso.** Las evaluaciones repetidas de la misma
regla e IP actualizan la alerta existente en vez de crear una nueva. Una
alerta resuelta libera el indice, de modo que un ataque que se repite
genera una alerta nueva y trazable.
