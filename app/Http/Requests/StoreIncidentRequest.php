<?php

namespace App\Http\Requests;

use App\Enums\AlertSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validacion de la creacion de un incidente.
 *
 * Los alert_ids se validan como existentes en la tabla de alertas. Sin
 * esa comprobacion, un formulario manipulado podria agrupar al
 * incidente identificadores inexistentes y la investigacion arrancaria
 * con referencias rotas.
 */
class StoreIncidentRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:5', 'max:180'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'priority' => ['required', Rule::enum(AlertSeverity::class)],
            'alert_ids' => ['nullable', 'array', 'max:50'],
            'alert_ids.*' => ['integer', Rule::exists('alerts', 'id')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Debe indicar un titulo para el incidente.',
            'title.min' => 'El titulo debe tener al menos 5 caracteres.',
            'description.required' => 'Debe describir el incidente.',
            'description.min' => 'La descripción debe tener al menos 20 caracteres: es la primera pista de quien lo lea.',
            'alert_ids.exists' => 'Una de las alertas indicadas no existe.',
            'assigned_to.exists' => 'El analista indicado no existe.',
        ];
    }

    /**
     * @return list<int>
     */
    public function alertIds(): array
    {
        return array_values(array_map(
            'intval',
            $this->validated('alert_ids', []),
        ));
    }
}
