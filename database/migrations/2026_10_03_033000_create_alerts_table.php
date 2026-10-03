<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de alertas.
     *
     * Una alerta es la consecuencia de que una regla de deteccion se
     * cumplio. No es un evento: un evento es un hecho aislado, mientras
     * que una alerta agrega un conjunto de eventos que comparten la misma
     * causa raiz (por ejemplo, cinco intentos fallidos desde una IP).
     */
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();

            // Titulo corto generado por el servicio a partir de la regla.
            $table->string('title');

            // Explicacion legible del hallazgo para el analista.
            $table->text('description');

            /*
             * Severidad y estado usan el enum de PHP, pero se persisten
             * como varchar. Guardar el enum nativo de Postgres obligaria
             * a replicar los valores en la base de datos y complicaria
             * las migraciones. El enum de PHP ya garantiza que solo
             * existan valores validos, que es la garantia que importa.
             */
            $table->string('severity', 20)->default('LOW');
            $table->string('status', 20)->default('OPEN');

            /*
             * IP origen del ataque. Usa el tipo inet de PostgreSQL para
             * que la base de datos normalice y valide las direcciones:
             * esto evita que entradas malformadas lleguen a la capa de
             * aplicacion y permite comparar IPs de forma canonica.
             */
            $table->ipAddress('source_ip');

            // Identificador de la regla que genero la alerta.
            $table->string('detection_rule', 100);

            /*
             * Ventana temporal que la regla evaluo. first_seen_at y
             * last_seen_at acotan los eventos que sustentan la alerta,
             * que puede ser distinto del rango de eventos original si
             * la alerta se actualiza con actividad posterior.
             */
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            // Cantidad de eventos que sustentan la deteccion.
            $table->unsignedInteger('event_count')->default(0);

            $table->timestamps();

            /*
             * Indices pensados para las consultas reales de la
             * aplicacion, no para el theorico completo de filtros.
             */
            $table->index(['status', 'severity'], 'alerts_status_severity_index');
            $table->index('detection_rule', 'alerts_detection_rule_index');
            $table->index('last_seen_at', 'alerts_last_seen_at_index');
            $table->index('source_ip', 'alerts_source_ip_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
