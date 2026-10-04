<?php

namespace App\Http\Requests;

use App\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validacion de los filtros del Event Explorer.
 *
 * Los filtros se ejecutan en el servidor. Cargar la tabla completa y
 * filtrar en el navegador permitiria descargar la base de datos entera
 * con una sola peticion y expondría el contenido a cualquier usuario
 * que inspeccione con las herramientas de desarrollo.
 *
 * Todas las reglas son opcionales: el listado sin filtros es un caso
 * valido y frecuente.
 */
class IndexSecurityEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_type' => ['nullable', 'array'],
            'event_type.*' => [Rule::enum(EventType::class)],
            'source_ip' => ['nullable', 'ip'],
            'username' => ['nullable', 'string', 'max:150'],
            'result' => ['nullable', Rule::in(['SUCCESS', 'FAILURE', 'BLOCKED'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['occurred_at', 'event_type', 'source_ip', 'username'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event_type.*.enum' => 'Uno de los tipos de evento indicados no es válido.',
            'source_ip.ip' => 'La dirección IP indicada no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'per_page.max' => 'No se pueden mostrar más de 100 registros por página.',
        ];
    }

    /**
     * Direccion de ordenamiento efectiva.
     *
     * Por defecto se ordena del evento mas reciente al mas antiguo: en
     * un panel de seguridad, lo relevante es lo que acaba de ocurrir.
     */
    public function sortDirection(): string
    {
        return $this->validated('direction', 'desc');
    }

    /**
     * Columna de ordenamiento efectiva.
     */
    public function sortColumn(): string
    {
        return $this->validated('sort', 'occurred_at');
    }

    /**
     * Registros por pagina.
     *
     * El maximo de 100 evita que un parametro manipulado genere una
     * consulta que devuelva la tabla completa.
     */
    public function perPage(): int
    {
        return (int) $this->validated('per_page', 25);
    }
}
