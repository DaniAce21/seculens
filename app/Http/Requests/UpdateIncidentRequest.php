<?php

namespace App\Http\Requests;

use App\Enums\AlertSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validacion de la edicion de los campos descriptivos del incidente.
 *
 * status y assigned_to no se aceptan aqui a proposito: los gestiona
 * IncidentService mediante operaciones propias. Aceptarlos en un
 * formulario de edicion abriria la puerta a registrar un estado
 * incoherente con las marcas temporales, porque el servicio solo
 * deriva opened_at, resolved_at y closed_at cuando el cambio pasa por
 * su metodo.
 */
class UpdateIncidentRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'min:5', 'max:180'],
            'description' => ['sometimes', 'required', 'string', 'min:20', 'max:5000'],
            'priority' => ['sometimes', 'required', Rule::enum(AlertSeverity::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.min' => 'El titulo debe tener al menos 5 caracteres.',
            'description.min' => 'La descripción debe tener al menos 20 caracteres.',
        ];
    }
}
