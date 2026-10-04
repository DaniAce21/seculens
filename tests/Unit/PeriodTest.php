<?php

namespace Tests\Unit;

use App\Support\Period;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Limites de los periodos usados por la exportacion CSV y los informes.
 *
 * Un limite mal calculado haria que un CSV "mensual" perdiera el ultimo
 * dia del mes o incluyera el primero del siguiente.
 */
class PeriodTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function periods(): array
    {
        // [unidad, fecha de referencia, inicio esperado, fin esperado]
        return [
            'dia' => ['day', '2026-10-03', '2026-10-03 00:00:00', '2026-10-03 23:59:59'],
            // 03/10/2026 es sabado: la semana va del lunes 28/09 al domingo 04/10.
            'semana' => ['week', '2026-10-03', '2026-09-28 00:00:00', '2026-10-04 23:59:59'],
            'mes con 30 dias' => ['month', '2026-09-15', '2026-09-01 00:00:00', '2026-09-30 23:59:59'],
            'febrero' => ['month', '2026-02-10', '2026-02-01 00:00:00', '2026-02-28 23:59:59'],
            'año' => ['year', '2026-06-01', '2026-01-01 00:00:00', '2026-12-31 23:59:59'],
        ];
    }

    #[DataProvider('periods')]
    public function test_calcula_los_limites_del_periodo(string $unit, string $date, string $from, string $to): void
    {
        $period = Period::containing($unit, CarbonImmutable::parse($date));

        $this->assertSame($from, $period->from->format('Y-m-d H:i:s'));
        $this->assertSame($to, $period->to->format('Y-m-d H:i:s'));
    }

    public function test_sufijo_de_archivo_sin_tildes(): void
    {
        $period = Period::containing('year', CarbonImmutable::parse('2026-06-01'));

        $this->assertSame('anio_2026-01-01_a_2026-12-31', $period->fileSuffix());
    }
}
