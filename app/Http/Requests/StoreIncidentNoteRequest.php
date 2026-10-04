<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validacion de una nota de investigacion.
 *
 * El limite inferior de 3 caracteres existe para descartar envios
 * accidentales de un espacio o un caracter de salto. Una nota vacia en
 * la cronologia informa de que el analista abrio el formulario y no
 * escribio nada, lo que en una auditoria posterior se lee como una
 * accion realizada sin contenido.
 */
class StoreIncidentNoteRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Debe escribir el contenido de la nota.',
            'body.min' => 'La nota debe tener al menos 3 caracteres.',
            'body.max' => 'La nota no puede superar los 5000 caracteres.',
        ];
    }
}
