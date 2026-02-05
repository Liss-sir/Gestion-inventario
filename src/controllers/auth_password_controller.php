<?php
// src/controllers/tokens_correo_controller.php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../Config/database.php'; // $conn PDO

define('RESET_TTL_MINUTES', 30);

function baseLoginUrl(): string {
  $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
  $host     = $_SERVER['HTTP_HOST'];
  $script   = $_SERVER['SCRIPT_NAME']; // /.../src/controllers/tokens_correo_controller.php
  $dir      = str_replace(basename($script), '', $script); // /.../src/controllers/
  return $protocol . $host . $dir . '../view/login/'; // /.../src/view/login/
}

function redirectTo(string $url) { header("Location: $url"); exit; }

$accion = $_GET['accion'] ?? '';

try {
  if (!isset($conn) || !($conn instanceof PDO)) {
    throw new Exception("No hay conexión PDO en \$conn.");
  }

  // =====================================================
  // 1) REQUEST RESET: genera token y lo guarda
  // =====================================================
  if ($accion === 'request_reset') {
    $correo = trim($_POST['correo'] ?? '');

    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
      $_SESSION['reset_err'] = "Ingresa un correo válido.";
      redirectTo(baseLoginUrl() . "recuperar_contrasena.php");
    }

    // Mensaje genérico para no revelar existencia del correo
    $msgOk = "Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.";

    // Buscar usuario
    $st = $conn->prepare("SELECT id_usuario, correo FROM usuarios WHERE correo = :c LIMIT 1");
    $st->execute([':c' => $correo]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
      $_SESSION['reset_ok'] = $msgOk;
      redirectTo(baseLoginUrl() . "recuperar_contrasena.php");
    }

    $idUsuario = (int)$user['id_usuario'];

    // Invalidar tokens previos de reset (opcional)
    $conn->prepare("
      UPDATE tokens_correo
      SET usado = 1
      WHERE id_usuario = :uid AND tipo = 'reset_password' AND usado = 0
    ")->execute([':uid' => $idUsuario]);

    // Token seguro (guardado tal cual en DB para tu estructura)
    // Si quieres extra seguridad: guardar hash en vez del token plano (te lo adapto)
    $token = bin2hex(random_bytes(32));

    $fechaExp = (new DateTime('now'))
      ->modify('+' . RESET_TTL_MINUTES . ' minutes')
      ->format('Y-m-d H:i:s');

    // Insert token
    $ins = $conn->prepare("
      INSERT INTO tokens_correo (id_usuario, token, tipo, fecha_expiracion, usado)
      VALUES (:uid, :t, 'reset_password', :exp, 0)
    ");
    $ins->execute([
      ':uid' => $idUsuario,
      ':t'   => $token,
      ':exp' => $fechaExp
    ]);

    // Link
    $resetLink = baseLoginUrl() . "reset_password.php?token=" . urlencode($token);

    // ✅ Aquí conectas PHPMailer
    // enviarCorreoReset($correo, $resetLink);

    // DEV (para probar sin correo):
    // $_SESSION['reset_ok'] = $msgOk . " (DEV: $resetLink)";
    $_SESSION['reset_ok'] = $msgOk;

    redirectTo(baseLoginUrl() . "recuperar_contrasena.php");
  }

  // =====================================================
  // 2) RESET PASSWORD: valida token y cambia password
  // =====================================================
  if ($accion === 'reset_password') {
    $token = trim($_POST['token'] ?? '');
    $p1    = (string)($_POST['password'] ?? '');
    $p2    = (string)($_POST['password2'] ?? '');

    if ($token === '') {
      $_SESSION['reset_err'] = "Token inválido.";
      redirectTo(baseLoginUrl() . "recuperar_contrasena.php");
    }

    if ($p1 === '' || strlen($p1) < 8) {
      $_SESSION['reset_err'] = "La contraseña debe tener mínimo 8 caracteres.";
      redirectTo(baseLoginUrl() . "reset_password.php?token=" . urlencode($token));
    }

    if ($p1 !== $p2) {
      $_SESSION['reset_err'] = "Las contraseñas no coinciden.";
      redirectTo(baseLoginUrl() . "reset_password.php?token=" . urlencode($token));
    }

    // Buscar token válido
    $q = $conn->prepare("
      SELECT id_token, id_usuario, fecha_expiracion, usado
      FROM tokens_correo
      WHERE token = :t AND tipo = 'reset_password'
      LIMIT 1
    ");
    $q->execute([':t' => $token]);
    $row = $q->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $_SESSION['reset_err'] = "El enlace es inválido o ya fue usado.";
      redirectTo(baseLoginUrl() . "recuperar_contrasena.php");
    }

    if ((int)$row['usado'] === 1) {
      $_SESSION['reset_err'] = "Este enlace ya fue usado. Solicita uno nuevo.";
      redirectTo(baseLoginUrl() . "recuperar_contrasena.php");
    }

    if (strtotime($row['fecha_expiracion']) < time()) {
      // marcar usado para no reutilizar
      $conn->prepare("UPDATE tokens_correo SET usado = 1 WHERE id_token = :id")
           ->execute([':id' => (int)$row['id_token']]);

      $_SESSION['reset_err'] = "Este enlace expiró. Solicita uno nuevo.";
      redirectTo(baseLoginUrl() . "recuperar_contrasena.php");
    }

    $idUsuario = (int)$row['id_usuario'];
    $idToken   = (int)$row['id_token'];

    // Cambiar password en usuarios
    $newHash = password_hash($p1, PASSWORD_DEFAULT);

    $up = $conn->prepare("UPDATE usuarios SET password = :ph WHERE id_usuario = :uid LIMIT 1");
    $up->execute([
      ':ph'  => $newHash,
      ':uid' => $idUsuario
    ]);

    // Marcar token como usado
    $conn->prepare("UPDATE tokens_correo SET usado = 1 WHERE id_token = :id LIMIT 1")
         ->execute([':id' => $idToken]);

    $_SESSION['reset_ok'] = "Contraseña actualizada correctamente. Ya puedes iniciar sesión.";
    redirectTo(baseLoginUrl() . "login.php");
  }

  http_response_code(400);
  echo "Acción inválida.";
  exit;

} catch (Throwable $e) {
  $_SESSION['reset_err'] = "Error: " . $e->getMessage();
  redirectTo(baseLoginUrl() . "recuperar_contrasena.php");
}
