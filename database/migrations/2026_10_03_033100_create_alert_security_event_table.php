<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relaciona alertas con los eventos de seguridad que las originaron.
     *
     * Por que existe una tabla pivote y no se deduce la relacion con una
     * consulta por IP y rango de fechas:
     *
     * 1. Auditoria. Una plataforma de seguridad debe poder demostrar
     *    exactamente que eventos provocaron una alerta concreta. Una
     *    relacion inferida se rompe en cuanto la misma IP genera dos
     *    ventanas de deteccion que se solapan.
     *
     * 2. Trazabilidad. Permite la consulta inversa ("que alertas se
     *    generaron a partir de este evento"), que es la que necesita un
     *    analista al investigar un evento puntual.
     *
     * La relacion es de muchos a muchos: una alerta agrupa varios
     * eventos y un evento puede pertenecer a mas de una alerta si fue
     * evaluado por reglas distintas.
     */
    public function up(): void
    {
        Schema::create('alert_security_event', function (Blueprint $table) {
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('security_event_id')->constrained()->cascadeOnDelete();

            /*
             * Marca el momento en que el evento fue ligado a la alerta.
             * Conserva la evidencia temporal aunque mas adelante se
             * modifiquen los campos first_seen_at o last_seen_at.
             */
            $table->timestamp('linked_at');

            /*
             * Clave primaria compuesta: impide que el mismo evento se
             * registre dos veces dentro de la misma alerta, que es
             * justamente el tipo de duplicado que generaria conteos
             * erroneos en event_count.
             */
            $table->primary(['alert_id', 'security_event_id'], 'alert_security_event_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_security_event');
    }
};
