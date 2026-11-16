<?php
require __DIR__ . '/config.php';

$mensaje = '';
try {
    $db = conectar();

    // Alta de contacto (consulta preparada -> evita inyección SQL)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'alta') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $tel    = trim($_POST['telefono'] ?? '');
        if ($nombre !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $st = $db->prepare('INSERT INTO contactos (nombre, email, telefono) VALUES (?, ?, ?)');
            $st->execute([$nombre, $email, $tel]);
            $mensaje = 'Contacto añadido.';
        } else {
            $mensaje = 'Nombre obligatorio y email válido.';
        }
    }

    // Borrado
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'borrar') {
        $st = $db->prepare('DELETE FROM contactos WHERE id = ?');
        $st->execute([(int)($_POST['id'] ?? 0)]);
        $mensaje = 'Contacto borrado.';
    }

    $contactos = $db->query('SELECT id, nombre, email, telefono, creado FROM contactos ORDER BY id')->fetchAll();
    $version   = $db->query('SELECT VERSION() AS v')->fetch()['v'];
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Error BD: ' . $e->getMessage());     // el detalle va al log, no al usuario
    die('<h1>Error de conexión con la base de datos</h1>');
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agenda - PHP + MySQL</title>
  <style>
    body { font-family: sans-serif; margin: 3rem; }
    table { border-collapse: collapse; }
    th, td { border: 1px solid #999; padding: .4rem .8rem; }
    th { background: #eee; }
    .msg { color: #0a6; font-weight: bold; }
  </style>
</head>
<body>
  <h1>Agenda de contactos</h1>
  <p>PHP <?= PHP_VERSION ?> conectado a <?= e($version) ?> (BD <code>agenda</code>)</p>
  <?php if ($mensaje): ?><p class="msg"><?= e($mensaje) ?></p><?php endif; ?>

  <table>
    <tr><th>ID</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Alta</th><th></th></tr>
    <?php foreach ($contactos as $c): ?>
    <tr>
      <td><?= (int)$c['id'] ?></td>
      <td><?= e($c['nombre']) ?></td>
      <td><?= e($c['email']) ?></td>
      <td><?= e($c['telefono']) ?></td>
      <td><?= e($c['creado']) ?></td>
      <td>
        <form method="post" style="margin:0">
          <input type="hidden" name="accion" value="borrar">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button type="submit">Borrar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <h2>Nuevo contacto</h2>
  <form method="post">
    <input type="hidden" name="accion" value="alta">
    <p><label>Nombre: <input name="nombre" required></label></p>
    <p><label>Email: <input name="email" type="email" required></label></p>
    <p><label>Teléfono: <input name="telefono"></label></p>
    <button type="submit">Añadir</button>
  </form>
</body>
</html>
