<?php
// Web dinámica con PHP: el contenido se genera en cada petición.
$nombre = isset($_GET['nombre']) ? htmlspecialchars($_GET['nombre'], ENT_QUOTES, 'UTF-8') : 'visitante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Web PHP - php.asir.local</title>
</head>
<body style="font-family:sans-serif; margin:3rem;">
  <h1>Hola, <?= $nombre ?></h1>
  <ul>
    <li>Fecha y hora del servidor: <?= date('d/m/Y H:i:s') ?></li>
    <li>Versión de PHP: <?= PHP_VERSION ?></li>
    <li>Servidor web: <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '') ?></li>
    <li>Interfaz (SAPI): <?= php_sapi_name() ?></li>
    <li>Tu IP: <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '') ?></li>
  </ul>
  <form method="get">
    <label>Tu nombre: <input name="nombre"></label>
    <button type="submit">Saludar</button>
  </form>
</body>
</html>
