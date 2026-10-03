<?php

namespace Tests\Unit;

use App\Enums\AlertSeverity;
use App\Models\SecurityEvent;
use App\Services\DetectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de la regla BRUTE_FORCE.
 *
 * Se prueban los limites exactos de la regla porque son el nucleo de la
 * logica de deteccion. Un umbral mal calculado por un solo intento
 * genera falsos negativos en un sistema cuyo unico valor es detectar
 * ataques.
 *
 * Todas las pruebas usan una referencia temporal fija. Es la razon por la
 * que detectBruteForce acepta un segundo argumento: permite validar el
 * comportamiento sin depender del reloj ni esperar minutos reales.
 */
class DetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DetectionService $service;

    /**
     * Instante fijo que actua como "ahora" para todas las pruebas.
     *
     * Fijarlo aqui evita que cada prueba tenga que inicializarlo y
     * garantiza que la ventana temporal evaluada sea siempre la misma.
     */
    private Carbon $reference;

    /**
     * IP usada en las pruebas. Pertenece al rango reservado para
     * documentacion (RFC 5737), por lo que nunca resuelve a un host real.
     */
    private const TEST_IP = '203.0.113.77';

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DetectionService::class);
        $this->reference = Carbon::parse('2026-06-15 12:00:00');
    }

    /**
     * Crea eventos LOGIN_FAILED dentro de la ventana evaluada.
     *
     * @param  int  $count  Cantidad de eventos
     * @param  string  $ip  Direccion IP de origen
     */
    private function createFailedLogins(int $count, string $ip = self::TEST_IP): void
    {
        for ($i = 0; $i < $count; $i++) {
            SecurityEvent::create([
                'event_type' => 'LOGIN_FAILED',
                'username' => 'admin',
                'source_ip' => $ip,
                'user_agent' => 'phpunit',
                'result' => 'FAILURE',

                /*
                 * Un evento por minuto, terminando en el instante
                 * de referencia. Con cinco eventos el primero queda
                 * cuatro minutos antes, dentro de la ventana.
                 *
                 * copy() es obligatorio: los métodos sub* de Carbon
                 * mutan la instancia. Sin la copia, cada iteracion
                 * restaria minutos sobre la ya modificada y los
                 * eventos se desplazarian fuera de la ventana.
                 */
                'occurred_at' => $this->reference->copy()->subMinutes($count - 1 - $i),
            ]);
        }
    }

    public function test_detects_brute_force_with_five_attempts(): void
    {
        $this->createFailedLogins(5);

        $result = $this->service->detectBruteForce(self::TEST_IP, $this->reference);

        $this->assertTrue($result['detected']);
        $this->assertSame('BRUTE_FORCE', $result['rule']);
        $this->assertSame(AlertSeverity::HIGH, $result['severity']);
        $this->assertSame(5, $result['event_count']);
    }

    public function test_does_not_detect_with_four_attempts(): void
    {
        $this->createFailedLogins(4);

        $result = $this->service->detectBruteForce(self::TEST_IP, $this->reference);

        /*
         * El limite es inclusivo en cinco. Con cuatro intentos la
         * regla no debe dispararse: ese es exactamente el caso que
         * separa un ataque de un usuario que escribio mal su
         * contrasena un par de veces.
         */
        $this->assertFalse($result['detected']);
        $this->assertNull($result['severity']);
        $this->assertSame(4, $result['event_count']);
    }

    public function test_does_not_detect_outside_the_time_window(): void
    {
        /*
         * Cinco eventos, pero repartidos en veinticuatro horas en lugar
         * de concentrarse. Mismo volumen, patron distinto: la regla
         * debe seguir sin dispararse porque el factor temporal es
         * parte de la definicion.
         *
         * El primer evento se ubica una hora antes de la referencia y
         * no sobre ella: la ventana evaluada incluye su borde
         * superior, de modo que un evento en el instante exacto seria
         * contado correctamente. Ese caso limite se cubre aparte en
         * test_detects_exactly_at_the_window_boundary.
         */
        for ($i = 0; $i < 5; $i++) {
            SecurityEvent::create([
                'event_type' => 'LOGIN_FAILED',
                'username' => 'admin',
                'source_ip' => self::TEST_IP,
                'result' => 'FAILURE',
                'occurred_at' => $this->reference->copy()->subHours(1 + 6 * $i),
            ]);
        }

        $result = $this->service->detectBruteForce(self::TEST_IP, $this->reference);

        $this->assertFalse($result['detected']);
        $this->assertSame(0, $result['event_count']);
    }

    public function test_only_counts_login_failed_events(): void
    {

        /*
         * Cinco eventos de autenticacion, pero exitosos. La regla debe
         * ignorar por completo el tipo de evento, no contar el
         * volumen de actividad de autenticacion en general.
         */
        for ($i = 0; $i < 5; $i++) {
            SecurityEvent::create([
                'event_type' => 'LOGIN_SUCCESS',
                'username' => 'admin',
                'source_ip' => self::TEST_IP,
                'result' => 'SUCCESS',
                'occurred_at' => $this->reference->copy()->subMinutes(4 - $i),
            ]);
        }

        $result = $this->service->detectBruteForce(self::TEST_IP, $this->reference);

        $this->assertFalse($result['detected']);
        $this->assertSame(0, $result['event_count']);
    }

    public function test_different_ips_do_not_trigger_the_same_rule(): void
    {

        /*
         * Tres IPs distintas con dos intentos cada una. Ninguna llega al
         * umbral, y el reparto importa: si la regla agrupara por
         * username en lugar de por IP, contaria seis eventos y
         * dispararia de forma incorrecta.
         */
        $this->createFailedLogins(2, '203.0.113.10');
        $this->createFailedLogins(2, '203.0.113.20');
        $this->createFailedLogins(2, '203.0.113.30');

        foreach (['203.0.113.10', '203.0.113.20', '203.0.113.30'] as $ip) {
            $result = $this->service->detectBruteForce($ip, $this->reference);

            $this->assertFalse($result['detected'], "La IP {$ip} no debe disparar la regla.");
            $this->assertSame(2, $result['event_count']);
        }
    }

    public function test_returns_the_event_window_of_the_detection(): void
    {
        $this->createFailedLogins(5);

        $result = $this->service->detectBruteForce(self::TEST_IP, $this->reference);

        /*
         * first_seen_at y last_seen_at acotan la evidencia. Un analista
         * necesita saber que periodo exacto examino la regla para no
         * atribuirle eventos que no vio.
         */
        $this->assertTrue(
            $result['first_seen_at']->equalTo($this->reference->copy()->subMinutes(4))
        );
        $this->assertTrue($result['last_seen_at']->equalTo($this->reference));
    }

    public function test_does_not_detect_when_no_events_exist(): void
    {

        $result = $this->service->detectBruteForce('198.51.100.1', $this->reference);

        $this->assertFalse($result['detected']);
        $this->assertSame(0, $result['event_count']);
        $this->assertNull($result['first_seen_at']);
        $this->assertNull($result['last_seen_at']);
    }

    public function test_detects_exactly_at_the_window_boundary(): void
    {

        /*
         * El quinto evento cae justo en el borde de la ventana. El
         * limite se define con whereBetween, que es inclusivo, por lo
         * que este caso debe detectarse. Documenta la decision de
         * diseno para que un cambio futuro sea deliberado.
         */
        for ($i = 0; $i < 4; $i++) {
            SecurityEvent::create([
                'event_type' => 'LOGIN_FAILED',
                'source_ip' => self::TEST_IP,
                'result' => 'FAILURE',
                'occurred_at' => $this->reference->copy()->subMinutes(1 + $i),
            ]);
        }

        SecurityEvent::create([
            'event_type' => 'LOGIN_FAILED',
            'source_ip' => self::TEST_IP,
            'result' => 'FAILURE',
            'occurred_at' => $this->reference->copy()->subMinutes(5),
        ]);

        $result = $this->service->detectBruteForce(self::TEST_IP, $this->reference);

        $this->assertTrue($result['detected']);
        $this->assertSame(5, $result['event_count']);
    }
}
