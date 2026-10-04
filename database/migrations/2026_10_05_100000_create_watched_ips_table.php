<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lista de IPs en vigilancia (reincidentes, sospechosas o bloqueadas).
 *
 * Las vistas marcan con una insignia cualquier IP de esta lista, de modo
 * que el analista la reconoce en eventos, alertas y alertas IDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watched_ips', function (Blueprint $table) {
            $table->id();

            // string y no ipAddress: asi la comparacion con source_ip de
            // otras tablas es texto contra texto en cualquier motor.
            $table->string('ip', 45)->unique();

            // 'watch' = vigilar, 'blocked' = bloqueada en el perimetro.
            $table->string('level', 20)->default('watch');

            $table->text('reason')->nullable();

            // Quien la anadio; si se borra el usuario se conserva la entrada.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watched_ips');
    }
};
