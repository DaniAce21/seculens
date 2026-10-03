<?php

namespace Tests\Unit;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\SecurityEvent;
use App\Services\AlertService;
use App\Services\DetectionService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de la creacion y consolidacion de alertas.
 *
 * El foco esta en las garantias que el servicio debe uphold:
 * no duplicar alertas del mismo ataque, no perder evidencia y nunca
 * degradar una severidad ya alcanzada.
 */
class AlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private AlertService $service;

    private Carbon $reference;

    private const TEST_IP = '203.0.113.90';

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AlertService::class);
        $this->reference = Carbon::parse('2026-06-15 12:00:00');
    }

    /**
     * Crea eventos LOGIN_FAILED dentro de la ventana evaluada.
     */
    private function createFailedLogins(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            SecurityEvent::create([
                'event_type' => 'LOGIN_FAILED',
                'username' => 'admin',
                'source_ip' => self::TEST_IP,
                'result' => 'FAILURE',
                'occurred_at' => $this->reference->copy()->subMinutes($count - 1 - $i),
            ]);
        }
    }

    /**
     * Genera eventos fallidos y devuelve el resultado de la deteccion.
     *
     * @return array<string, mixed>
     */
    private function detectBruteForce(int $count): array
    {
        $this->createFailedLogins($count);

        return app(DetectionService::class)
            ->detectBruteForce(self::TEST_IP, $this->reference);
    }

    public function test_creates_no_alert_when_the_rule_is_not_met(): void
    {
        $detection = $this->detectBruteForce(4);

        $this->assertNull($this->service->createFromDetection($detection));
        $this->assertSame(0, Alert::count());
    }

    public function test_creates_an_alert_when_the_rule_is_met(): void
    {
        $detection = $this->detectBruteForce(5);

        $alert = $this->service->createFromDetection($detection);

        $this->assertNotNull($alert);
        $this->assertSame('BRUTE_FORCE', $alert->detection_rule);
        $this->assertSame(AlertSeverity::HIGH, $alert->severity);
        $this->assertSame(AlertStatus::OPEN, $alert->status);
        $this->assertSame(5, $alert->event_count);
        $this->assertSame(5, $alert->securityEvents()->count());
    }

    public function test_reuses_the_active_alert_instead_of_duplicating_it(): void
    {
        $this->service->createFromDetection($this->detectBruteForce(5));

        /*
         * Segunda evaluacion sobre los mismos eventos. El ataque sigue
         * en curso, asi que el servicio debe actualizar la alerta
         * existente en lugar de crear una segunda entrada identica.
         */
        $second = $this->service->createFromDetection($this->detectBruteForce(5));

        /*
         * El helper crea cinco eventos adicionales, por lo que la
         * deteccion observa diez. Lo relevante es que exista una sola
         * alerta y que su conteo refleje la evidencia acumulada.
         */
        $this->assertSame(1, Alert::count());
        $this->assertSame(10, $second->event_count);
        $this->assertSame(10, $second->securityEvents()->count());
    }

    public function test_database_rejects_two_active_alerts_for_the_same_rule_and_ip(): void
    {
        $this->service->createFromDetection($this->detectBruteForce(5));

        /*
         * Verifica la garantia a nivel de motor, no a nivel de
         * aplicacion. Si esta prueba falla, significa que el indice
         * unico parcial dejo de proteger contra la condicion de
         * carrera entre peticiones simultaneas.
         */
        $this->expectException(QueryException::class);

        Alert::create([
            'title' => 'Duplicada',
            'description' => 'Intento de insertar una segunda alerta activa.',
            'severity' => AlertSeverity::HIGH,
            'status' => AlertStatus::OPEN,
            'source_ip' => self::TEST_IP,
            'detection_rule' => 'BRUTE_FORCE',
            'first_seen_at' => $this->reference,
            'last_seen_at' => $this->reference,
            'event_count' => 5,
        ]);
    }

    public function test_allows_a_new_alert_after_the_previous_one_was_resolved(): void
    {
        $alert = $this->service->createFromDetection($this->detectBruteForce(5));
        $alert->update(['status' => AlertStatus::RESOLVED]);

        /*
         * Un ataque que se repite genera una alerta nueva y trazable.
         * Resolver una alerta no debe cerrar la puerta a detectar
         * recurrencias del mismo origen.
         */
        $this->service->createFromDetection($this->detectBruteForce(5));

        $this->assertSame(2, Alert::count());
    }

    public function test_never_downgrades_the_severity_of_an_existing_alert(): void
    {
        $alert = $this->service->createFromDetection($this->detectBruteForce(5));
        $this->assertSame(AlertSeverity::HIGH, $alert->severity);

        /*
         * Simulamos que otro proceso escalo la alerta a CRITICAL.
         * Se asigna la propiedad directamente porque severity no forma
         * parte de $fillable: el motor de deteccion es la unica fuente
         * autorizada para cambiar esa columna.
         */
        $alert->severity = AlertSeverity::CRITICAL;
        $alert->save();

        $this->service->createFromDetection($this->detectBruteForce(5));

        $this->assertSame(AlertSeverity::CRITICAL, $alert->fresh()->severity);
    }

    public function test_escalates_severity_when_a_worse_detection_arrives(): void
    {
        $alert = $this->service->createFromDetection($this->detectBruteForce(5));

        $alert->severity = AlertSeverity::LOW;
        $alert->save();

        $this->service->createFromDetection($this->detectBruteForce(5));

        $this->assertSame(AlertSeverity::HIGH, $alert->fresh()->severity);
    }

    public function test_extends_the_observed_window_when_new_evidence_arrives(): void
    {
        $this->createFailedLogins(5);

        $detection = app(DetectionService::class)
            ->detectBruteForce(self::TEST_IP, $this->reference);

        $alert = $this->service->createFromDetection($detection);
        $initialFirstSeen = $alert->first_seen_at;

        /*
         * Un evento dos minutos mas antiguo amplia first_seen_at hacia
         * atras. La alerta debe reflejar el periodo completo que la
         * regla evaluo, no solo la primera ventana.
         *
         * El evento cae dentro de la ventana de cinco minutos que
         * inspecciona BRUTE_FORCE. Un evento mas antiguo quedaria fuera
         * de esa ventana y correctamente no ampliaria first_seen_at,
         * porque la regla nunca lo observo.
         */
        SecurityEvent::create([
            'event_type' => 'LOGIN_FAILED',
            'username' => 'admin',
            'source_ip' => self::TEST_IP,
            'result' => 'FAILURE',
            'occurred_at' => $this->reference->copy()->subMinutes(5),
        ]);

        $secondDetection = app(DetectionService::class)
            ->detectBruteForce(self::TEST_IP, $this->reference);

        $this->service->createFromDetection($secondDetection);

        $this->assertTrue(
            $alert->fresh()->first_seen_at->lt($initialFirstSeen),
            'first_seen_at debe retroceder cuando aparece evidencia mas antigua.'
        );
    }

    public function test_rolls_back_when_a_detection_has_no_supporting_events(): void
    {
        /*
         * Una deteccion afirmada sin eventos que la respalden es
         * incoherente. El servicio debe abortar la transaccion en vez
         * de persistir evidencia incompleta.
         */
        $this->expectException(\RuntimeException::class);

        $this->service->createFromDetection([
            'detected' => true,
            'rule' => 'BRUTE_FORCE',
            'severity' => AlertSeverity::HIGH,
            'source_ip' => self::TEST_IP,
            'event_count' => 5,
            'window_minutes' => 5,
            'first_seen_at' => $this->reference->copy()->subMinutes(5),
            'last_seen_at' => $this->reference,
        ]);
    }

    public function test_active_scope_filters_out_resolved_alerts(): void
    {
        $this->service->createFromDetection($this->detectBruteForce(5));

        Alert::query()->first()->update(['status' => AlertStatus::RESOLVED]);

        $this->assertSame(0, Alert::query()->active()->count());
    }

    public function test_high_severity_scope_matches_high_and_critical(): void
    {
        Alert::factory()->severity(AlertSeverity::LOW)->create();
        Alert::factory()->severity(AlertSeverity::MEDIUM)->create();
        Alert::factory()->severity(AlertSeverity::HIGH)->create();
        Alert::factory()->severity(AlertSeverity::CRITICAL)->create();

        $this->assertSame(2, Alert::query()->withSeverityAtLeast(AlertSeverity::HIGH)->count());
    }
}
