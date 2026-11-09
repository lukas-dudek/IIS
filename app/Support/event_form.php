<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Validator;

function event_form_data(Request $request): array
{
    return [
        'title' => trim($request->postString('title')),
        'description' => trim($request->postString('description')),
        'starts_at' => trim($request->postString('starts_at')),
        'ends_at' => trim($request->postString('ends_at')),
        'venue_id' => trim($request->postString('venue_id')),
        'category_id' => trim($request->postString('category_id')),
        'capacity' => trim($request->postString('capacity')),
        'payment_note' => trim($request->postString('payment_note')),
    ];
}

function event_ticket_rows(Request $request): array
{
    $names = $request->post('ticket_name', []);
    $ids = $request->post('ticket_id', []);
    $prices = $request->post('ticket_price', []);
    $capacities = $request->post('ticket_capacity', []);

    if (!is_array($names)) {
        return [];
    }

    $count = max(
        count($names),
        is_array($ids) ? count($ids) : 0,
        is_array($prices) ? count($prices) : 0,
        is_array($capacities) ? count($capacities) : 0
    );

    $scalar = static function (array $values, int $index): string {
        $value = $values[$index] ?? '';
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value)) {
            return (string) $value;
        }

        return '';
    };

    $rows = [];
    for ($index = 0; $index < $count; $index++) {
        $idRaw = $scalar(is_array($ids) ? $ids : [], $index);
        $capacityRaw = $scalar(is_array($capacities) ? $capacities : [], $index);
        $rows[] = [
            'id' => $idRaw === '' ? null : (int) $idRaw,
            'name' => $scalar(is_array($names) ? $names : [], $index),
            'price' => $scalar(is_array($prices) ? $prices : [], $index),
            'capacity' => $capacityRaw === '' ? null : (int) $capacityRaw,
        ];
    }

    return $rows;
}

function event_form_errors(array $data): array
{
    $validator = new Validator($data, [
        'title' => 'Název události',
        'description' => 'Popis',
        'starts_at' => 'Začátek',
        'ends_at' => 'Konec',
        'venue_id' => 'Místo konání',
        'category_id' => 'Kategorie',
        'capacity' => 'Kapacita',
        'payment_note' => 'Platební pokyny',
    ]);
    $validator->required('title')->length('title', 3, 140)
        ->required('description')->length('description', 20, 5000)
        ->required('starts_at')->dateTime('starts_at')
        ->required('ends_at')->dateTime('ends_at')
        ->required('venue_id')->integer('venue_id', 1)
        ->required('category_id')->integer('category_id', 1)
        ->integer('capacity', 1, 100000)
        ->length('payment_note', 0, 1000);

    return $validator->errors();
}

function event_ticket_errors(array $rows): array
{
    $errors = [];
    $names = [];

    foreach ($rows as $index => $row) {
        $name = trim((string) $row['name']);
        $id = $row['id'];
        $price = trim((string) $row['price']);
        $capacity = $row['capacity'];

        if ($id === null && $name === '' && $price === '' && $capacity === null) {
            continue;
        }

        if ($name === '') {
            if ($id === null && ($price !== '' || $capacity !== null)) {
                $errors['ticket_name_' . $index] = 'Zadejte název typu vstupenky.';
            }
            continue;
        }

        if (mb_strlen($name) > 60) {
            $errors['ticket_name_' . $index] = 'Název typu vstupenky může mít nejvýše 60 znaků.';
        }
        $key = mb_strtolower($name);
        if (isset($names[$key])) {
            $errors['ticket_name_' . $index] = 'Název typu vstupenky musí být v rámci události unikátní.';
        }
        $names[$key] = true;

        $normalizedPrice = str_replace(',', '.', $price);
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $normalizedPrice) !== 1
            || (float) $normalizedPrice > 100000) {
            $errors['ticket_price_' . $index] = 'Cena musí být 0–100000 s nejvýše dvěma desetinnými místy.';
        }

        if ($capacity !== null && (int) $capacity < 1) {
            $errors['ticket_capacity_' . $index] = 'Kapacita typu musí být alespoň 1.';
        }
    }

    return $errors;
}
