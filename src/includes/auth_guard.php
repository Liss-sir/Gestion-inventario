<?php
// src/includes/auth_guard.php
// Cierra sesión si el usuario fue desactivado o si su token_sesion ya no está activo.
// Agrega cierre automático por inactividad.

// ✅ NUEVO: refresca datos de sesión desde BD para reflejar cambios sin re-login

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
    $reason = "session_closed";
    $ACTIVE_WINDOW_SECONDS = 90;

    try {
      $stmt3 = $conn->prepare("\n        SELECT 1\n        FROM sesiones_usuarios\n        WHERE id_usuario = :id\n          AND activa = 1\n          AND token_sesion <> :token\n          AND TIMESTAMPDIFF(SECOND, COALESCE(fecha_ultima_actividad, fecha_inicio), NOW()) <= :w\n        LIMIT 1\n      ");
      $stmt3->bindValue(':id', $uid, PDO::PARAM_INT);
      $stmt3->bindValue(':token', $token, PDO::PARAM_STR);
      $stmt3->bindValue(':w', $ACTIVE_WINDOW_SECONDS, PDO::PARAM_INT);
      $stmt3->execute();

      if ((bool)$stmt3->fetchColumn()) {
        $reason = "session_revoked";
      }
    } catch (Throwable $e) {
      // si falla verificación extra, conservar reason por defecto
    }

    _destroySession();
    _redirectLogin($reason);
  }

  // -----------------------------------------------------------
  // ✅ NUEVO: sincronizar datos del usuario logueado desde la BD
  // Esto hace que cambios hechos por otro rol se vean sin re-login
  // -----------------------------------------------------------
  $SYNC_EVERY_SECONDS = 15; // puedes subirlo a 30 o 60 si quieres menos consultas
  $lastSync = isset($_SESSION['__LAST_USER_SYNC__']) ? (int)$_SESSION['__LAST_USER_SYNC__'] : 0;

  if ((time() - $lastSync) >= $SYNC_EVERY_SECONDS) {

    $stmtSync = $conn->prepare("
      SELECT
        id_usuario,
        nombre_completo,
        tipo_documento,
        numero_documento,
        telefono,
        cargo,
        correo,
        direccion,
        foto_perfil,
        estado
      FROM usuarios
      WHERE id_usuario = :id
      LIMIT 1
    ");
    $stmtSync->execute([":id" => $uid]);
    $fresh = $stmtSync->fetch(PDO::FETCH_ASSOC);

    // Si el usuario desaparece o se desactiva, cerrar
    if (!$fresh || !_estadoActivo($fresh['estado'] ?? null)) {

      try {
        $stmtOff3 = $conn->prepare("
          UPDATE sesiones_usuarios
          SET activa = 0
          WHERE token_sesion = :token
        ");
        $stmtOff3->execute([':token' => $token]);
      } catch (Throwable $e) {}

      _destroySession();
      _redirectLogin("disabled");
    }

    // ✅ Actualizar claves de sesión usadas en tu sistema
    $_SESSION['usuario_id']               = (int)($fresh['id_usuario'] ?? $uid);
    $_SESSION['usuario_nombre']           = $fresh['nombre_completo'] ?? ($_SESSION['usuario_nombre'] ?? '');
    $_SESSION['usuario_correo']           = $fresh['correo'] ?? ($_SESSION['usuario_correo'] ?? '');
    $_SESSION['usuario_cargo']            = $fresh['cargo'] ?? ($_SESSION['usuario_cargo'] ?? '');

    // ✅ Datos extra usados en perfil / cabeceras
    $_SESSION['usuario_tipo_documento']   = $fresh['tipo_documento'] ?? ($_SESSION['usuario_tipo_documento'] ?? '');
    $_SESSION['usuario_numero_documento'] = $fresh['numero_documento'] ?? ($_SESSION['usuario_numero_documento'] ?? '');
    $_SESSION['usuario_telefono']         = $fresh['telefono'] ?? ($_SESSION['usuario_telefono'] ?? '');
    $_SESSION['usuario_direccion']        = $fresh['direccion'] ?? ($_SESSION['usuario_direccion'] ?? '');

    // Foto perfil
    $_SESSION['usuario_foto']             = $fresh['foto_perfil'] ?? ($_SESSION['usuario_foto'] ?? null);

    // Si tú también usas $_SESSION['usuario'] (lo haces en login), lo sincronizamos
    if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario'])) {
      $_SESSION['usuario'] = [];
    }

    $_SESSION['usuario']['id']     = (int)($fresh['id_usuario'] ?? $uid);
    $_SESSION['usuario']['nombre'] = $fresh['nombre_completo'] ?? ($_SESSION['usuario']['nombre'] ?? '');
    $_SESSION['usuario']['correo'] = $fresh['correo'] ?? ($_SESSION['usuario']['correo'] ?? '');
    $_SESSION['usuario']['cargo']  = $fresh['cargo'] ?? ($_SESSION['usuario']['cargo'] ?? '');

    // Marca de sincronización
    $_SESSION['__LAST_USER_SYNC__'] = time();
  }

  // ----------------------------
  // ✅ actualizar tiempo de actividad (PHP + BD)
  // ----------------------------
  $_SESSION['LAST_ACTIVITY'] = time();
  
  try {
    $stmtUpdateActivity = $conn->prepare("
      UPDATE sesiones_usuarios
      SET fecha_ultima_actividad = NOW()
      WHERE token_sesion = :token
    ");
    $stmtUpdateActivity->execute([':token' => $token]);
  } catch (Throwable $e) {
    // Ignorar si falla, no rompe la sesión activa
  }

} catch (Throwable $e) {
  // Si falla BD, NO rompas el sistema; pero por seguridad puedes cerrar.
  // _destroySession();
  // _redirectLogin("check_error");
}
