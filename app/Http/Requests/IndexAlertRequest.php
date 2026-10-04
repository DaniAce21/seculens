<?php

namespace App\Http\Requests;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validacion del listado de alertas.
 *
 * Los filtros se aplican en el servidor por dos motivos: la tabla de
 * alertas crecera de forma continua y descargarla entera para filtrar
 * en el navegador no escala, y una respuesta completa expondría todos
 * los hallazgos a cualquier usuario autenticado, incluidos los que su
 * rol no le permite ver en detalle.
 *
 * per_page tiene un techo porque un parametro manipulado con un valor
 * enorme degradaria la base de datos sin aportar nada.
 */
class IndexAlertRequest extends FormRequest
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
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::enum(AlertStatus::class)],
            'severity' => ['nullable', 'array'],
            'severity.*' => ['string', Rule::enum(AlertSeverity::class)],
            'source_ip' => ['nullable', 'ip'],
            'detection_rule' => ['nullable', 'string', 'max:80'],
            'search' => ['nullable', 'string', 'max:150'],
            'sort' => ['nullable', Rule::in(['first_seen_at', 'last_seen_at', 'severity', 'event_count'])],
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
            'status.*.enum' => 'Uno de los estados indicados no es válido.',
            'severity.*.enum' => 'Una de las severidades indicadas no es válida.',
            'source_ip.ip' => 'La dirección IP indicada no es válida.',
            'per_page.max' => 'No se pueden mostrar más de 100 alertas por página.',
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 15);
    }

    public function sortColumn(): string
    {
        return $this->validated('sort', 'last_seen_at');
    }

    public function sortDirection(): string
    {
        return $this->validated('direction', 'desc');
    }

    /**
     * Termino de busqueda en titulo y descripcion.
     */
    public function searchTerm(): ?string
    {
        $term = $this->validated('search');

        return filled($term) ? trim((string) $term) : null;
    }
}
