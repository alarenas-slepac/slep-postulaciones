<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mensajes de validación
    |--------------------------------------------------------------------------
    */

    'accepted' => 'Debes aceptar este campo.',
    'active_url' => 'La URL no es válida.',
    'after' => 'Debe ser una fecha posterior a :date.',
    'after_or_equal' => 'Debe ser una fecha posterior o igual a :date.',
    'alpha' => 'Solo puede contener letras.',
    'alpha_dash' => 'Solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num' => 'Solo puede contener letras y números.',
    'array' => 'Debe ser un arreglo.',
    'before' => 'Debe ser una fecha anterior a :date.',
    'before_or_equal' => 'Debe ser una fecha anterior o igual a :date.',
    'between' => [
        'numeric' => 'Debe estar entre :min y :max.',
        'file' => 'Debe tener entre :min y :max kilobytes.',
        'string' => 'Debe tener entre :min y :max caracteres.',
        'array' => 'Debe tener entre :min y :max elementos.',
    ],
    'boolean' => 'Debe ser verdadero o falso.',
    'confirmed' => 'La confirmación no coincide.',
    'current_password' => 'La contraseña es incorrecta.',
    'date' => 'No es una fecha válida.',
    'date_equals' => 'Debe ser una fecha igual a :date.',
    'date_format' => 'No coincide con el formato :format.',
    'different' => 'Debe ser diferente a :other.',
    'digits' => 'Debe tener :digits dígitos.',
    'digits_between' => 'Debe tener entre :min y :max dígitos.',
    'dimensions' => 'Dimensiones de imagen inválidas.',
    'distinct' => 'Tiene un valor duplicado.',
    'email' => 'Ingresa un correo válido.',
    'ends_with' => 'Debe terminar con uno de los siguientes: :values.',
    'exists' => 'El valor seleccionado no es válido.',
    'file' => 'Debe ser un archivo.',
    'filled' => 'Este campo debe tener un valor.',
    'gt' => [
        'numeric' => 'Debe ser mayor que :value.',
        'file' => 'Debe ser mayor que :value kilobytes.',
        'string' => 'Debe tener más de :value caracteres.',
        'array' => 'Debe tener más de :value elementos.',
    ],
    'gte' => [
        'numeric' => 'Debe ser mayor o igual que :value.',
        'file' => 'Debe ser mayor o igual que :value kilobytes.',
        'string' => 'Debe tener :value caracteres o más.',
        'array' => 'Debe tener :value elementos o más.',
    ],
    'image' => 'Debe ser una imagen.',
    'in' => 'La selección no es válida.',
    'integer' => 'Debe ser un número entero.',
    'ip' => 'Debe ser una dirección IP válida.',
    'ipv4' => 'Debe ser una dirección IPv4 válida.',
    'ipv6' => 'Debe ser una dirección IPv6 válida.',
    'json' => 'Debe ser una cadena JSON válida.',
    'lt' => [
        'numeric' => 'Debe ser menor que :value.',
        'file' => 'Debe ser menor que :value kilobytes.',
        'string' => 'Debe tener menos de :value caracteres.',
        'array' => 'Debe tener menos de :value elementos.',
    ],
    'lte' => [
        'numeric' => 'Debe ser menor o igual que :value.',
        'file' => 'Debe ser menor o igual que :value kilobytes.',
        'string' => 'Debe tener como máximo :value caracteres.',
        'array' => 'No debe tener más de :value elementos.',
    ],
    'max' => [
        'numeric' => 'No debe ser mayor que :max.',
        'file' => 'No debe exceder :max kilobytes.',
        'string' => 'No debe exceder :max caracteres.',
        'array' => 'No debe tener más de :max elementos.',
    ],
    'mimes' => 'Debe ser un archivo de tipo: :values.',
    'mimetypes' => 'Debe ser un archivo de tipo: :values.',
    'min' => [
        'numeric' => 'Debe ser al menos :min.',
        'file' => 'Debe tener al menos :min kilobytes.',
        'string' => 'Debe tener al menos :min caracteres.',
        'array' => 'Debe tener al menos :min elementos.',
    ],
    'multiple_of' => 'Debe ser múltiplo de :value.',
    'not_in' => 'La selección no es válida.',
    'not_regex' => 'Formato inválido.',
    'numeric' => 'Debe ser un número.',
    'password' => [
        'letters' => 'Debe contener al menos una letra.',
        'mixed' => 'Debe contener al menos una mayúscula y una minúscula.',
        'numbers' => 'Debe contener al menos un número.',
        'symbols' => 'Debe contener al menos un símbolo.',
        'uncompromised' => 'Esta contraseña aparece en una filtración. Elige otra.',
    ],
    'present' => 'Debe estar presente.',
    'prohibited' => 'Este campo está prohibido.',
    'prohibited_if' => 'Este campo está prohibido cuando :other es :value.',
    'prohibited_unless' => 'Este campo está prohibido a menos que :other esté en :values.',
    'prohibits' => 'Este campo prohíbe que :other esté presente.',
    'regex' => 'Formato inválido.',
    'required' => 'Este campo es obligatorio.',
    'required_array_keys' => 'Debe contener entradas para: :values.',
    'required_if' => 'Este campo es obligatorio cuando :other es :value.',
    'required_unless' => 'Este campo es obligatorio a menos que :other esté en :values.',
    'required_with' => 'Este campo es obligatorio cuando :values está presente.',
    'required_with_all' => 'Este campo es obligatorio cuando :values están presentes.',
    'required_without' => 'Este campo es obligatorio cuando :values no está presente.',
    'required_without_all' => 'Este campo es obligatorio cuando ninguno de :values está presente.',
    'same' => 'Debe coincidir con :other.',
    'size' => [
        'numeric' => 'Debe ser :size.',
        'file' => 'Debe pesar :size kilobytes.',
        'string' => 'Debe tener :size caracteres.',
        'array' => 'Debe contener :size elementos.',
    ],
    'starts_with' => 'Debe comenzar con uno de los siguientes: :values.',
    'string' => 'Debe ser una cadena de texto.',
    'timezone' => 'Debe ser una zona horaria válida.',
    'unique' => 'Este valor ya está registrado.',
    'uploaded' => 'No se pudo subir el archivo.',
    'url' => 'Ingresa una URL válida.',
    'uuid' => 'Debe ser un UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensajes personalizados por campo.regla (opcional)
    |--------------------------------------------------------------------------
    */
    'custom' => [
        'rut' => [
            'regex' => 'Formato de RUT inválido.',
            'unique' => 'Este RUT ya está registrado.',
        ],
        'email' => [
            'unique' => 'Este email ya está registrado.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nombres de atributos (para que se vean bonitos si se usan en mensajes)
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        'rut' => 'RUT',
        'nombres' => 'nombres',
        'apellido_paterno' => 'apellido paterno',
        'apellido_materno' => 'apellido materno',
        'email' => 'email',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'rol' => 'rol',
    ],

];
