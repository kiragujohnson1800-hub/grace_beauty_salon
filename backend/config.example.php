<?php
declare(strict_types=1);

return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'grace_beauty_salon',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your-gmail-address@gmail.com',
        'password' => 'paste-your-google-app-password-here',
        'encryption' => 'tls',
        'from_email' => 'your-gmail-address@gmail.com',
        'from_name' => 'Grace Beauty Salon',
    ],
];