<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sensores IDS autorizados a usar la API (/api/v1/*).
 *
 * Cada sensor tiene su propio token. Solo se guarda el hash SHA-256: si
 * la base de datos se filtra, los tokens no se pueden reutilizar. El token
 * en claro se muestra una unica vez, al crearlo (php artisan ids:sensor-create).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ids_sensors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            // Revocar en vez de borrar: se conserva el historial de quien envio que.
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // Que sensor envio cada alerta (null en las alertas anteriores a este cambio).
        Schema::table('ids_alerts', function (Blueprint $table) {
            $table->foreignId('sensor_id')->nullable()->constrained('ids_sensors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ids_alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sensor_id');
        });

        Schema::dropIfExists('ids_sensors');
    }
};
