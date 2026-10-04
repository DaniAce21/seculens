<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validacion de la asignacion de un incidente.
 *
 * El campo admite un valor nulo explicito para devolver el incidente a
 * la cola. Se usa 'nullable' en lugar de 'sometimes' porque la
 * desasignacion es una accion distinta de la asignacion y ambas deben
 * poder ejecutarse desde el mismo formulario.
 */
class AssignIncidentRequest extends FormRequest
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
            'assigned_to' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_to.present' => 'Debe indicar un analista o una desasignacion explicita.',
            'assigned_to.exists' => 'El analista indicado no existe.',
        ];
    }

    public function assigneeId(): ?int
    {
        $id = $this->validated('assigned_to');

        return $id === null ? null : (int) $id;
    }
}
