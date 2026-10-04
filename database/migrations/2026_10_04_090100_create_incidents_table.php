<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla de incidentes.
 *
 * Un incidente es la unidad de trabajo del analista. Agrupa una o mas
 * alertas relacionadas, lleva el seguimiento de quien lo atiende y
 * conserva la cronologia de la investigacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();

            // Titulo redactado por el analista que abre el incidente.
            $table->string('title');

            // Contexto inicial: que se observa y por que importa.
            $table->text('description');

            /*
             * Estado del flujo de investigacion. El enum de PHP valida
             * los valores en la aplicacion y el CHECK de la migracion
             * de auditoria los refuerza en el motor.
             */
            $table->string('status', 20)->default('OPEN');

            // Prioridad asignada por el analista segun el impacto.
            $table->string('priority', 20)->default('MEDIUM');

            /*
             * Analista responsable.
             *
             * onDelete('set null') en lugar de cascade: si se elimina
             * la cuenta de un analista, el incidente debe conservarse
             * con su historial. Perder la investigacion porque el
             * analista dejo la organizacion seria perder evidencia.
             */
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Marcas temporales del ciclo de vida del incidente.
            $table->timestamp('opened_at')->default(now());
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            /*
             * Indice compuesto para el tablero: filtra por estado y
             * ordena por fecha de apertura.
             */
            $table->index(['status', 'opened_at'], 'incidents_status_opened_index');
            $table->index('assigned_to', 'incidents_assigned_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
