<?php
$dbConfig = ['connection' => 'mysql:host=127.0.0.1;dbname=sambaprod_db', 'user' => 'samba_user', 'pass' => 'g9CL8,5cvD*V3:6K'];
if ($url = getenv('STACKHERO_MYSQL_DATABASE_URL')) {
    $parsed = parse_url($url);
    $dbConfig = [
        'connection' => sprintf('mysql:host=%s;port=%d;dbname=%s', $parsed['host'], $parsed['port'] ?? 3306, getenv('DB_NAME') ?: ltrim($parsed['path'] ?? '', '/')),
        'user' => $parsed['user'] ?? 'root',
        'pass' => isset($parsed['pass']) ? urldecode($parsed['pass']) : '',
    ];
}

return [
    'appId'          => getenv('SHOPER_APP_ID') ?: 'f5c279aa12340ce0ce4b6311029817bb',
    'appSecret'      => getenv('SHOPER_APP_SECRET') ?: '58ca6bf82798fe43e2c7d0535d3b5e1c8cb89c1b',
    'appstoreSecret' => getenv('SHOPER_APPSTORE_SECRET') ?: 'f2f3b0027f06c29eb2968f8ab9955f3ee5429e5c',
    'db'      => $dbConfig,
    'debug'   => false,
    'logFile' => getenv('STACKHERO_MYSQL_DATABASE_URL') ? null : __DIR__ . '/../../logs/application.log',
    'timezone' => 'Europe/Warsaw',
    'php'     => ['display_errors' => 'off'],
];
