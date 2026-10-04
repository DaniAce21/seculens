<?php

namespace App\Http\Requests;

use App\Enums\AlertSeverity;
use App\Enums\IncidentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validacion de los filtros del tablero de incidentes.
 *
 * El campo scope sustituye a los parametros separados "mine" y
 * "unassigned" porque estos son excluyentes entre si: un incidente
 * no puede estar simultaneamente asignado al usuario que filtra y sin
 * asignar. Modelarlo como un unico valor con opciones hace que el
 * conflicto sea imposible de expresar en lugar de detectable despues.
 */
class IndexIncidentRequest extends FormRequest
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
            'status.*' => [Rule::enum(IncidentStatus::class)],
            'priority' => ['nullable', 'array'],
            'priority.*' => [Rule::enum(AlertSeverity::class)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'scope' => ['nullable', Rule::in(['all', 'mine', 'unassigned'])],
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
            'priority.*.enum' => 'Una de las prioridades indicadas no es válida.',
            'scope.in' => 'El ámbito de incidentes indicado no es válido.',
            'per_page.max' => 'No se pueden mostrar más de 100 incidentes por página.',
        ];
    }

    /**
     * Ambito de trabajo solicitado.
     *
     * "all" equivale a no filtrar, y es el valor por defecto para que la
     * vista del tablero no dependa de que el filtro llegue informado.
     */
    public function scope(): string
    {
        return (string) $this->validated('scope', 'all');
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 15);
    }
}
