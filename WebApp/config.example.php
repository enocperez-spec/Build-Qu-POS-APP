<?php
declare(strict_types=1);

return [
    'database' => [
        'enabled' => false,
        'host' => 'your-database-host.example.com',
        'port' => 3306,
        'name' => 'your_database_name',
        'username' => 'your_database_user',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'automation' => [
        'import_token' => '',
    ],
    'mail' => [
        'enabled' => false,
        'from_address' => 'no-reply@qupostech.com',
        'from_name' => 'QU POS Application Version Tools',
        'base_url' => 'https://quposapp.qupostech.com',
    ],
];
