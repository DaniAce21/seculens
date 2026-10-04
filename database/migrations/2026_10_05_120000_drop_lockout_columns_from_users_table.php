<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina las columnas del bloqueo de cuentas por intentos fallidos.
 *
 * La aplicacion funciona en modo single-user (middleware AutoLogin): no hay
 * pantalla de login, asi que no hay intentos fallidos que contar ni cuentas
 * que bloquear. El codigo de login (AuthController, AuthService,
 * LoginRequest) ya se elimino; estas columnas quedaron sin uso.
 *
 * La columna 'role' se mantiene: la siguen usando las policies.
 */
return new class extends Migration
{
    public function up(): void
    {
        // El CHECK depende de failed_login_attempts; se quita antes que la columna.
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_failed_attempts_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['failed_login_attempts', 'locked_until', 'last_login_at']);
        });
    }

    /**
     * Restaura las columnas (vacias) por si hiciera falta volver atras.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('remember_token');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_failed_attempts_check CHECK (failed_login_attempts >= 0)');
    }
};
