<?php

/*
|--------------------------------------------------------------------------
| Mensajes de validacion en español
|--------------------------------------------------------------------------
|
| Se activan con APP_LOCALE=es. Solo se traducen las reglas que usa la
| aplicacion; cualquier otra cae en el idioma de respaldo (ingles), de modo
| que nunca se muestra una clave sin traducir.
|
| Los mensajes especificos de cada formulario (p. ej. en los FormRequest)
| tienen prioridad sobre estos.
*/

return [
    'after_or_equal' => 'El campo :attribute debe ser una fecha posterior o igual a :date.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'date_format' => 'El campo :attribute debe tener el formato :format.',
    'enum' => 'El valor de :attribute no es válido.',
    'exists' => 'El :attribute seleccionado no existe.',
    'in' => 'El valor de :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'ip' => 'El campo :attribute debe ser una dirección IP válida.',
    'max' => [
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
        'file' => 'El archivo :attribute no puede pesar más de :max kilobytes.',
    ],
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El archivo :attribute debe pesar al menos :min kilobytes.',
    ],
    'present' => 'El campo :attribute debe estar presente.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'El valor de :attribute ya está registrado.',

    /*
    | Nombres legibles de los campos (sustituyen a :attribute).
    */
    'attributes' => [
        'severity' => 'severidad',
        'alert_type' => 'tipo de alerta',
        'source_ip' => 'IP de origen',
        'destination_ip' => 'IP de destino',
        'signature' => 'firma',
        'status' => 'estado',
        'created_at' => 'fecha',
        'period' => 'periodo',
        'date' => 'fecha',
        'from' => 'desde',
        'to' => 'hasta',
        'type' => 'tipo de evento',
        'ip' => 'IP',
        'username' => 'usuario',
        'result' => 'resultado',
        'level' => 'nivel',
        'reason' => 'motivo',
        'body' => 'nota',
        'assigned_to' => 'responsable',
        'alert_id' => 'alerta',
    ],
];
