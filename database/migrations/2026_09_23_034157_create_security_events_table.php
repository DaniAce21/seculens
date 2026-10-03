<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla utilizada para almacenar eventos de seguridad.
     *
     * Un evento representa una acción que ocurrió en un sistema,
     * por ejemplo un inicio de sesión exitoso o fallido.
     */
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();

            // Tipo de evento: LOGIN_FAILED, LOGIN_SUCCESS, LOGOUT, etc.
            $table->string('event_type', 100);

            // Usuario asociado al evento, si existe.
            $table->string('username', 150)->nullable();

            // Dirección IP desde la que se originó el evento.
            $table->ipAddress('source_ip')->nullable();

            // Información del cliente que generó el evento.
            $table->text('user_agent')->nullable();

            // Resultado del evento, por ejemplo SUCCESS o FAILURE.
            $table->string('result', 50)->nullable();

            // Fecha y hora en que ocurrió realmente el evento.
            $table->timestamp('occurred_at');

            // Información adicional específica del evento.
            $table->json('metadata')->nullable();

            $table->timestamps();

            /*
             * La primera regla de detección analizará múltiples
             * intentos fallidos desde una misma IP dentro de
             * una ventana temporal. Este índice ayudará a PostgreSQL
             * a consultar esos eventos de forma más eficiente.
             */
            $table->index(
                ['event_type', 'source_ip', 'occurred_at'],
                'security_events_detection_index'
            );
        });
    }

    /**
     * Elimina la tabla cuando se revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};