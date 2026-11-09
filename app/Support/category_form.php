<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Validator;

function category_form_data(Request $request): array
{
    $parentRaw = trim($request->postString('parent_id'));

    return [
        'name' => trim($request->postString('name')),
        'parent_id' => $parentRaw === '' ? null : (int) $parentRaw,
        'description' => trim($request->postString('description')),
    ];
}

function category_form_errors(array $data): array
{
    $validator = new Validator($data, [
        'name' => 'Název kategorie',
        'parent_id' => 'Rodičovská kategorie',
        'description' => 'Popis',
    ]);
    $validator->required('name')->length('name', 2, 80)
        ->integer('parent_id', 1)
        ->length('description', 0, 2000);

    return $validator->errors();
}
