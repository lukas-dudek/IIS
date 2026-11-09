<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Validator;

function venue_form_data(Request $request): array
{
    return [
        'name' => trim($request->postString('name')),
        'street' => trim($request->postString('street')),
        'city' => trim($request->postString('city')),
        'postal_code' => trim($request->postString('postal_code')),
        'description' => trim($request->postString('description')),
    ];
}

function venue_form_errors(array $data): array
{
    $validator = new Validator($data, [
        'name' => 'Název místa',
        'street' => 'Ulice a číslo',
        'city' => 'Město',
        'postal_code' => 'PSČ',
        'description' => 'Popis',
    ]);
    $validator->required('name')->length('name', 2, 120)
        ->required('street')->length('street', 2, 120)
        ->required('city')->length('city', 2, 80)
        ->length('postal_code', 0, 10)
        ->regex('postal_code', '/^\d{3}\s?\d{2}$/', 'musí mít formát 123 45.')
        ->length('description', 0, 2000);

    return $validator->errors();
}
