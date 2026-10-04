<?php

namespace App\Http\Requests;

use App\Enums\AlertStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validacion del cambio de estado de una alerta.
 *
 * Los Form Requests son el lugar donde vive la validacion real. El
 * JavaScript puede avisar al usuario antes de enviar el formulario,
 * pero la unica comprobacion que importa es la que se ejecuta aqui, en
 * el servidor, donde no se puede eludir.
 */
class UpdateAlertStatusRequest extends FormRequest
{
    /**
     * La autorizacion se resuelve en el controller mediante la policy.
     *
     * Devolver true aqui de forma incondicional no la omite: Laravel
     * evalua primero authorize() y luego rules(). La policy es la
     * fuente unica de la decision, y duplicar la comprobacion aqui
     * abriria la puerta a que ambas se desincronizasen.
     */
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
            'status' => [
                'required',
                Rule::enum(AlertStatus::class),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Debe indicar el estado de la alerta.',
            'status.enum' => 'El estado indicado no es válido.',
        ];
    }
}
