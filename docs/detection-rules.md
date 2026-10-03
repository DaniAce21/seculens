# Reglas de deteccion

Cada regla responde una pregunta concreta sobre los eventos registrados.
Una regla no crea alertas: devuelve un veredicto. `AlertService` decide
que hacer con el.

## BRUTE_FORCE

**Estado:** activa (Fase 1)

### Definicion

```
5 o mas eventos LOGIN_FAILED
desde la misma direccion IP
dentro de una ventana de 5 minutos
```

### Parametros

| Parametro | Valor | Ubicacion |
|---|---|---|
| Umbral | 5 | `DetectionService::BRUTE_FORCE_THRESHOLD` |
| Ventana | 5 minutos | `DetectionService::BRUTE_FORCE_WINDOW_MINUTES` |
| Tipo de evento | `LOGIN_FAILED` | constante en el metodo |

Ambos parametros son constantes de clase, no numeros incrustados en la
consulta. Cambiar el umbral es editar una constante, y el cambio queda
visible en el historial de Git.

### Veredicto

```php
[
    'detected'       => bool,
    'rule'           => 'BRUTE_FORCE',
    'severity'       => AlertSeverity::HIGH | null,
    'source_ip'      => string,
    'event_count'    => int,
    'window_minutes' => 5,
    'first_seen_at'  => Carbon | null,
    'last_seen_at'   => Carbon | null,
]
```

`severity` es `null` cuando la regla no se cumple. Se usa el enum y no
una cadena suelta para que una comparacion invalida falle en desarrollo
en lugar de persistir un valor que ningun analisis reconoce.

`first_seen_at` y `last_seen_at` acotan exactamente el periodo evaluado.
Un analista necesita ese rango para saber que afirmo y que no afirmo la
regla.

### Implementacion

```php
public function detectBruteForce(
    string $sourceIp,
    ?Carbon $referenceTime = null
): array
```

El metodo acepta una referencia temporal opcional. No es un detalle de
implementacion:

- **En pruebas** permite validar el comportamiento de forma determinista
  sin manipular el reloj del sistema ni esperar minutos reales.
- **En operacion** permite reevaluar una ventana historica concreta.

El parametro `$referenceTime` se compara con el instante de referencia,
no con `now()`, de modo que el resultado es identico si la misma
evaluacion se repite.

La consulta se apoya en `security_events_detection_index`, un indice
compuesto sobre `(event_type, source_ip, occurred_at)`, que permite
resolver el filtro sin recorrer la tabla completa.

### Casos límite

| Caso | Comportamiento | Motivo |
|---|---|---|
| 4 intentos | No detecta | El umbral es 5. Cuatro fallos suelen ser un error de tecleo. |
| 5 intentos | Detecta | El umbral es inclusivo. |
| Evento justo en el borde de la ventana | Cuenta | `whereBetween` es inclusivo en ambos extremos. |
| Evento 1 ms fuera de la ventana | No cuenta | El factor temporal es parte de la definicion. |
| Eventos `LOGIN_SUCCESS` | No cuentan | La regla filtra por tipo, no por volumen de autenticacion. |
| 6 intentos en 3 IPs distintas | No detecta desde ninguna | La agrupacion es por IP. Volumen agregado no es un ataque desde un origen. |

Las cuatro ultimas filas tienen prueba automatica en
`tests/Unit/DetectionServiceTest.php`.

### Sobre `Carbon` y la mutacion

Los metodos `sub*` de Carbon **mutan** la instancia. El servicio calcula
la ventana asi:

```php
$windowEnd = $referenceTime ?? now();
$windowStart = $windowEnd->copy()->subMinutes(5);
```

Sin `copy()`, `$windowEnd` quedaria desplazada y la ventana seria
incorrecta. Los metodos `sub*` con varios argumentos, como
`subMinutes(4, 0)`, lanzan una excepcion: la segunda posicion es la
unidad, no otro intervalo.

## Reglas futuras

Cada regla nueva debe:

1. Vivir en `DetectionService` como metodo propio, con su constante de
   umbral y ventana.
2. Devolver la misma estructura de veredicto.
3. Tener pruebas que cubran el limite inferior, el superior, el borde de
   la ventana y los tipos de evento que debe ignorar.
4. Documentarse en este archivo.

Una regla que no puede explicarse en cinco lineas probablemente sea dos
reglas.

La especificacion menciona `SUSPICIOUS_ACTIVITY` y `ACCOUNT_LOCKED`
como tipos de evento. Son candidatos naturales a reglas futuras, pero no
se implementan todavia: una regla sin criterio de deteccion definido y
probado es una regla que produce ruido.
