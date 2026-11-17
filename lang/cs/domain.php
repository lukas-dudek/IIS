<?php

declare(strict_types=1);

return [
    'catalog' => [
        'venue' => 'Místo konání',
        'category' => 'Kategorie',
        'event' => 'Událost',
        'ticket_type' => 'Typ vstupenky',
        'registration' => 'Registrace',
        'comment' => 'Komentář',
        'user' => 'Uživatel',
    ],
    'status' => [
        'event' => [
            'draft' => 'Koncept',
            'pending' => 'Čeká na schválení',
            'approved' => 'Schválená',
            'rejected' => 'Zamítnutá',
            'cancelled' => 'Zrušená',
        ],
        'catalog' => [
            'pending' => 'Čeká na schválení',
            'approved' => 'Schváleno',
            'rejected' => 'Zamítnuto',
        ],
        'registration' => [
            'pending' => 'Čeká na platbu',
            'confirmed' => 'Potvrzená',
            'cancelled' => 'Zrušená',
        ],
        'comment' => [
            'visible' => 'Zobrazený',
            'hidden' => 'Skrytý',
        ],
    ],
    'role' => [
        'admin' => 'Administrátor',
        'moderator' => 'Moderátor',
        'user' => 'Uživatel',
    ],
];
