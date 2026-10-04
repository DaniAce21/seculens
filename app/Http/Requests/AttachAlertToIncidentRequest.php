<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validacion del vinculo de una alerta a un incidente.
 *
 * Solo se valida que la alerta exista. El estado del incidente no se
 * comprueba aqui sino en el controller, que es donde se dispone del
 * modelo cargado: la policy ya impide actuar sobre un incidente cerrado
 * y la comprobacion se repite alli para cubrir la ventana en la que el
 * incidente podria cerrarse entre la autorizacion de esta peticion y su
 * procesamiento.
 */
class AttachAlertToIncidentRequest extends FormRequest
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
            'alert_id' => [
                'required',
                'integer',
                Rule::exists('alerts', 'id'),
            ],
            'detach' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alert_id.required' => 'Debe indicar la alerta a vincular.',
            'alert_id.exists' => 'La alerta indicada no existe.',
        ];
    }
}
