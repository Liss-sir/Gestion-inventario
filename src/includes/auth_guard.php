<?php
// src/includes/auth_guard.php
// Cierra sesión si el usuario fue desactivado o si su token_sesion ya no está activo.

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../Config/database.php'; // ✅ desde src/includes -> Config

function _estadoActivo($raw) {
  if ($raw === null) return false;
  $val = strtolower(trim((string)$raw));
  return ($val === 'activo' || $val === '1' || $val === 'true');
}

function _destroySession() {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params["path"], $params["domain"], $params["secure"], $params["httponly"]
    );
  }
  session_destroy();
}

function _redirectLogin($reason = "disabled") {
  // Como tú navegas con index.php?page=...
  header("Location: index.php?page=login&reason=" . urlencode($reason));
  exit;
}

// Si no hay sesión, mandar al login
if (empty($_SESSION['usuario_id'])) {
  _destroySession();
  _redirectLogin("no_session");
}

$uid = (int)$_SESSION['usuario_id'];
$token = $_SESSION['token_sesion'] ?? null;

// Si no hay token (por cualquier razón), fuera
if (!$token) {
  _destroySession();
  _redirectLogin("no_token");
}

try {
  // 1) validar estado del usuario
  $stmt = $conn->prepare("SELECT estado FROM usuarios WHERE id_usuario = :id LIMIT 1");
  $stmt->execute([":id" => $uid]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$u || !_estadoActivo($u['estado'])) {
    // usuario no existe o está inactivo
    _destroySession();
    _redirectLogin("disabled");
  }

  // 2) validar sesión única (token activo)
  $stmt2 = $conn->prepare("
    SELECT activa
    FROM sesiones_usuarios
    WHERE id_usuario = :id
      AND token_sesion = :token
    LIMIT 1
  ");
  $stmt2->execute([
    ":id" => $uid,
    ":token" => $token
  ]);
  $s = $stmt2->fetch(PDO::FETCH_ASSOC);

  // Si no existe o no está activa => cerrar
  if (!$s || (int)$s['activa'] !== 1) {
    _destroySession();
    _redirectLogin("session_revoked");
  }
} catch (Throwable $e) {
  // Si falla BD, NO rompas el sistema; pero por seguridad puedes cerrar.
  // _destroySession();
  // _redirectLogin("check_error");
}
