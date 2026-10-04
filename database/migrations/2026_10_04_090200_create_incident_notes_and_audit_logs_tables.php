<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla de notas de incidente y la de auditoria.
 *
 * Se agrupan en una sola migracion porque ambas son estructura de
 * soporte del flujo de investigacion y no tienen dependencia entre si.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();

            /*
             * Autor de la nota.
             *
             * nullOnDelete por la misma razon que en incidents: la
             * nota forma parte del historial del incidente y debe
             * sobrevivir a la eliminacion de la cuenta que la escribio.
             */
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->text('body');

            /*
             * Registro del estado del incidente al momento de la nota.
             *
             * Sin esto, el historial mostraria notas en orden pero sin
             * poder responder "en que estado estaba el incidente cuando
             * se escribio esto", que es la pregunta habitual al
             * reconstruir una investigacion.
             */
            $table->string('incident_status_at_time', 20)->nullable();

            $table->timestamps();

            $table->index(['incident_id', 'created_at'], 'incident_notes_timeline_index');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            /*
             * Actor de la accion.
             *
             * nullOnDelete porque un registro de auditoria nunca debe
             * eliminarse por un cambio en la cuenta. Es evidencia
             * independiente de quien la produjo.
             */
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('action', 50);

            // Entidad afectada: 'Alert', 'Incident', 'User'.
            $table->string('subject_type', 50)->nullable();
            $table->bigInteger('subject_id')->nullable();

            /*
             * Rol del actor en el momento de la accion.
             *
             * Se copia en lugar de consultarse a traves de la
             * relacion: si el rol del usuario cambia, la bitacora debe
             * seguir reflejando que permisos tenia cuando ocurrio.
             */
            $table->string('user_role', 20)->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            /*
             * Contexto adicional en JSON: valores anteriores y nuevos,
             * motivo del cambio, filtros aplicados.
             *
             * Nunca se guardan contrasenas, tokens ni claves. Ese es un
             * requisito del formato, no una convencion: el servicio
             * filtra las claves sensibles antes de persistir.
             */
            $table->json('context')->nullable();

            $table->timestamp('created_at')->useCurrent();

            /*
             * La bitacora se consulta por fecha descendente y por
             * entidad afectada. Es la consulta principal de la vista de
             * auditoria.
             */
            $table->index(['created_at'], 'audit_logs_created_at_index');
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_index');
            $table->index('action', 'audit_logs_action_index');
            $table->index('user_id', 'audit_logs_user_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE incidents
            ADD CONSTRAINT incidents_status_check
            CHECK (status IN ('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE incidents
            ADD CONSTRAINT incidents_priority_check
            CHECK (priority IN ('LOW', 'MEDIUM', 'HIGH', 'CRITICAL'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE incident_notes
            ADD CONSTRAINT incident_notes_status_check
            CHECK (incident_status_at_time IS NULL OR incident_status_at_time IN ('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE incident_notes DROP CONSTRAINT IF EXISTS incident_notes_status_check');
        DB::statement('ALTER TABLE incidents DROP CONSTRAINT IF EXISTS incidents_priority_check');
        DB::statement('ALTER TABLE incidents DROP CONSTRAINT IF EXISTS incidents_status_check');

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('incident_notes');
    }
};
