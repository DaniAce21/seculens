<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anade control de acceso y proteccion de cuentas a la tabla users.
 *
 * Se modifica la migracion existente en lugar de crear una nueva tabla
 * porque estos campos forman parte de la identidad del usuario y deben
 * estar disponibles en cualquier consulta de autorizacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            /*
             * Rol con valor por defecto VIEWER.
             *
             * El default mas restrictivo es deliberado: si una alta de
             * usuario olvida el campo, la cuenta nace sin permisos en
             * lugar de nacer con permisos de administrador. Un default
             * permisivo convertiria cada omision en una escalada de
             * privilegios.
             */
            $table->string('role', 20)
                ->default(UserRole::VIEWER->value)
                ->after('password');

            /*
             * Contadores de bloqueo de cuenta.
             *
             * Son parte de la defensa contra fuerza bruta a nivel de
             * cuenta, complementaria a la regla BRUTE_FORCE que opera
             * por IP. Las dos son necesarias: un atacante distribuye
             * intentos entre IPs para evadir el umbral por IP, y a la
             * vez rota cuentas para evadir un bloqueo por cuenta.
             */
            $table->unsignedTinyInteger('failed_login_attempts')
                ->default(0)
                ->after('remember_token');

            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');

            $table->timestamp('last_login_at')->nullable()->after('locked_until');
        });

        /*
         * Restriccion a nivel de motor: un rol invalido no puede
         * escribirse ni siquiera desde SQL crudo. El enum de PHP ya
         * protege la aplicacion; esto cierra la via directa.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_role_check
            CHECK (role IN ('ADMIN', 'ANALYST', 'VIEWER'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_failed_attempts_check
            CHECK (failed_login_attempts >= 0)
        SQL);

        $this->seedDefaultAdmin();
    }

    /**
     * Crea el usuario administrador inicial.
     *
     * Sin esta cuenta la aplicacion quedaria inaccesible tras instalar
     * el proyecto. La contrasena se lee del entorno y solo se usa si
     * el operador la define de forma explicita: adivinar una
     * contrasena por defecto seria una puerta trasera conocida.
     */
    private function seedDefaultAdmin(): void
    {
        $email = env('SECULENS_ADMIN_EMAIL');
        $password = env('SECULENS_ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            return;
        }

        DB::table('users')->insert([
            'name' => env('SECULENS_ADMIN_NAME', 'Administrador'),
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'role' => UserRole::ADMIN->value,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_failed_attempts_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'failed_login_attempts', 'locked_until', 'last_login_at']);
        });
    }
};
