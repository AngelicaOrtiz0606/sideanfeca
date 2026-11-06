<?php

/*
 * Mensajes de validación en español.
 *
 * Solo incluye las reglas que usa SIDEANFECA hasta ahora. Las que falten
 * se muestran en inglés (idioma de respaldo) hasta que se agreguen aquí.
 */
return [
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'email' => ':Attribute debe ser un correo electrónico válido.',
    'in' => 'El valor de :attribute no es válido.',
    'max' => [
        'string' => ':Attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'string' => ':Attribute debe tener al menos :min caracteres.',
    ],
    'password' => [
        'letters' => ':Attribute debe tener al menos una letra.',
        'mixed' => ':Attribute debe tener al menos una mayúscula y una minúscula.',
        'numbers' => ':Attribute debe tener al menos un número.',
        'symbols' => ':Attribute debe tener al menos un símbolo.',
        'uncompromised' => ':Attribute aparece en una filtración de datos. Elige otra.',
    ],
    'required' => ':Attribute es obligatorio.',
    'string' => ':Attribute debe ser texto.',

    'attributes' => [
        'contrasenia' => 'la contraseña',
        'correo' => 'el correo electrónico',
        'genero' => 'el género',
        'nombre' => 'el nombre',
        'primer_apellido' => 'el primer apellido',
        'segundo_apellido' => 'el segundo apellido',
    ],
];
