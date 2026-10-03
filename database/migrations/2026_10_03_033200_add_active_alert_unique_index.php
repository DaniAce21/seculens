<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Impide que exista mas de una alerta activa para la misma combinacion
 * de regla e IP de origen.
 *
 * Esta restriccion se expresa con SQL nativo porque el constructor de
 * esquemas de Laravel no genera indices parciales, y son precisamente
 * los indices parciales los que permiten modelar "unicidad solo entre
 * las alertas que siguen abiertas".
 *
 * Por que importa:
 *
 * Si dos peticiones simultaneas evaluan la misma IP y ninguna encuentra
 * una alerta abierta, ambas crearian una alerta nueva. La aplicacion
 * mostraria dos alertas identicas para el mismo ataque y el contador de
 * alertas abiertas seria incorrecto. El enum de PHP no puede evitar
 * esta condicion de carrera porque la comprobacion y la escritura no
 * ocurren de forma atomica.
 *
 * La restriccion se delega al indice unico parcial, que PostgreSQL
 * evalua de forma atomica dentro de la misma transaccion.
 *
 * Nota sobre el estado RESOLVED: se excluye del indice de forma
 * deliberada. Una alerta resuelta puede coexistir con una nueva alerta
 * del mismo tipo, porque un ataque puede repetirse mas adelante y eso
 * debe generar una alerta distinta y trazable.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX alerts_active_unique
            ON alerts (detection_rule, source_ip)
            WHERE status IN ('OPEN', 'ACKNOWLEDGED')
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS alerts_active_unique');
    }
};
