<?php
// Datos de conexión. En producción, mejor fuera del document root o en variables de entorno.
define('DB_HOST', 'localhost');
define('DB_NAME', 'agenda');
define('DB_USER', 'agenda_user');
define('DB_PASS', 'Asir2026!');

function conectar(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}
