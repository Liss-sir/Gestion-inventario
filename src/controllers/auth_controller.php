<?php
// src/controllers/auth_controller.php?accion=check --- Verifica si la sesión del usuario es válida y activa.
// esto es para lo de desactivar el usuario y cerrar sesión en todos lados.
header("Content-Type: application/json; charset=utf-8");

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../Config/database.php';

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

$accion = $_GET['accion'] ?? 'check';
if ($accion !== 'check') {
  http_response_code(400);
  echo json_encode(["error" => "Acción no válida"], JSON_UNESCAPED_UNICODE);
  exit;
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['token_sesion'])) {
  echo json_encode(["ok" => false, "logout" => true, "reason" => "no_session"], JSON_UNESCAPED_UNICODE);
  exit;
}

$ACTIVE_WINDOW_SECONDS = 6;

$uid = (int)$_SESSION['usuario_id'];
$token = (string)$_SESSION['token_sesion'];

try {
  $stmt = $conn->prepare("SELECT estado FROM usuarios WHERE id_usuario = :id LIMIT 1");
  $stmt->execute([":id" => $uid]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$u || !_estadoActivo($u['estado'])) {
    _destroySession();
    echo json_encode(["ok" => false, "logout" => true, "reason" => "disabled"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt2 = $conn->prepare("
    SELECT activa
    FROM sesiones_usuarios
    WHERE id_usuario = :id
      AND token_sesion = :token
    LIMIT 1
  ");
  $stmt2->execute([":id" => $uid, ":token" => $token]);
  $s = $stmt2->fetch(PDO::FETCH_ASSOC);

  if (!$s || (int)$s['activa'] !== 1) {
    $reason = "session_closed";

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
      // si falla esta verificación extra, dejar reason por defecto
    }

    _destroySession();
    echo json_encode(["ok" => false, "logout" => true, "reason" => $reason], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Mantener heartbeat de actividad reciente para este token.
  try {
    $stmtBeat = $conn->prepare("\n      UPDATE sesiones_usuarios\n      SET fecha_ultima_actividad = NOW()\n      WHERE id_usuario = :id\n        AND token_sesion = :token\n        AND activa = 1\n    ");
    $stmtBeat->execute([':id' => $uid, ':token' => $token]);
  } catch (Throwable $e) {
    // no romper check por falla puntual
  }

  try {
    $stmtTouch = $conn->prepare("\n      UPDATE sesiones_usuarios\n      SET fecha_ultima_actividad = NOW()\n      WHERE id_usuario = :id\n        AND token_sesion = :token\n        AND activa = 1\n      LIMIT 1\n    ");
    $stmtTouch->execute([":id" => $uid, ":token" => $token]);
  } catch (Throwable $e) {
    // no interrumpir check por fallo de touch
  }

  echo json_encode(["ok" => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  // Si falla el check, no tumbes al usuario por falso positivo:
  echo json_encode(["ok" => true], JSON_UNESCAPED_UNICODE);
}
