<?php

namespace Tests\Unit;

use App\Services\CsvExportService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Proteccion contra inyeccion de formulas en los CSV exportados.
 *
 * Los eventos contienen texto controlado por un atacante (usuario, user
 * agent). Si una celda empieza por =, +, - o @, Excel la ejecuta como
 * formula al abrir el archivo.
 */
class CsvSanitizeTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function cells(): array
    {
        return [
            'formula' => ['=HYPERLINK("http://evil")', '\'=HYPERLINK("http://evil")'],
            'suma' => ['+1+1', "'+1+1"],
            'resta' => ['-2+3', "'-2+3"],
            'arroba' => ['@SUM(A1)', "'@SUM(A1)"],
            'texto normal' => ['admin', 'admin'],
            'ip' => ['185.234.219.101', '185.234.219.101'],
            'null' => [null, ''],
            'entero' => [42, '42'],
        ];
    }

    #[DataProvider('cells')]
    public function test_neutraliza_formulas(mixed $input, string $expected): void
    {
        // sanitizeCell es privado: se prueba por reflexion para no exponerlo.
        $method = new ReflectionMethod(CsvExportService::class, 'sanitizeCell');

        $this->assertSame($expected, $method->invoke(new CsvExportService, $input));
    }
}
