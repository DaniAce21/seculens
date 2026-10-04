<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relaciona incidentes con las alertas que agrupan.
 *
 * La relacion es de muchos a muchos porque una alerta puede requerir
 * más de un incidente en el tiempo (por ejemplo, una alerta de fuerza
 * bruta recurrent que se reinvestiga cada vez) y un incidente puede
 * agrupar alertas distintas.
 *
 * La clave primaria compuesta impide vincular dos veces la misma alerta
 * al mismo incidente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_alert', function (Blueprint $table) {
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();

            // Momento en que el analista agrupo la alerta al incidente.
            $table->timestamp('linked_at');

            $table->timestamps();

            $table->primary(['incident_id', 'alert_id'], 'incident_alert_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_alert');
    }
};
