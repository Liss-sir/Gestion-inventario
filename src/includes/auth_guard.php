<?php
// src/includes/auth_guard.php
// Cierra sesión si el usuario fue desactivado o si su token_sesion ya no está activo.
// Agrega cierre automático por inactividad.

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
  // ✅ FIX: redirigir al login real (evita caer en el index de MAMP)
  if (defined("BASE_URL")) {
    header("Location: " . BASE_URL . "src/view/login/login.php?reason=" . urlencode($reason));
  } else {
    header("Location: src/view/login/login.php?reason=" . urlencode($reason));
  }
  exit;
}

// ----------------------------
// ✅ NUEVO: timeout por inactividad (15 minutos)
// ----------------------------
$INACTIVITY_LIMIT_SECONDS = 15 * 60; // 900s

if (isset($_SESSION['LAST_ACTIVITY'])) {
  $inactiveTime = time() - (int)$_SESSION['LAST_ACTIVITY'];

  if ($inactiveTime > $INACTIVITY_LIMIT_SECONDS) {
    // Marcar token como inactivo en BD si existe (para que no quede sesión "pegada")
    try {
      if (!empty($_SESSION['token_sesion'])) {
        $stmtOff = $conn->prepare("
          UPDATE sesiones_usuarios
          SET activa = 0
          WHERE token_sesion = :token
        ");
        $stmtOff->execute([
          ':token' => $_SESSION['token_sesion']
        ]);
      }
    } catch (Throwable $e) {
      // No romper el flujo por error en BD
    }

    _destroySession();
    _redirectLogin("idle_timeout");
  }
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
    try {
      $stmtOff2 = $conn->prepare("
        UPDATE sesiones_usuarios
        SET activa = 0
        WHERE token_sesion = :token
      ");
      $stmtOff2->execute([':token' => $token]);
    } catch (Throwable $e) {}

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

  // ----------------------------
  // ✅ actualizar tiempo de actividad
  // ----------------------------
  $_SESSION['LAST_ACTIVITY'] = time();

} catch (Throwable $e) {
  // Si falla BD, NO rompas el sistema; pero por seguridad puedes cerrar.
  // _destroySession();
  // _redirectLogin("check_error");
}
