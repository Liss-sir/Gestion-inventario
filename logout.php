<?php
session_start();

// ✅ necesario para que exista $conn (logout.php está en la RAÍZ del proyecto)
require_once __DIR__ . '/Config/database.php';

// ✅ guardar el token antes de limpiar la sesión
$tokenSesion = $_SESSION['token_sesion'] ?? null;

if ($tokenSesion) {
    try {
        $stmt = $conn->prepare("
            UPDATE sesiones_usuarios
            SET activa = 0
            WHERE token_sesion = :token
        ");
        $stmt->execute([
            ':token' => $tokenSesion
        ]);
    } catch (Throwable $e) {
        // No romper el logout si falla la BD
    }
}

session_unset();

// ✅ borrar cookie de sesión si existe (extra seguro)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"], $params["secure"], $params["httponly"]
    );
}

session_destroy();

// ✅ NUEVO: si el logout fue por inactividad, mandar al login REAL
$reason = $_GET['reason'] ?? '';
if ($reason === 'idle_timeout') {
    header("Location: src/view/login/login.php?reason=idle_timeout");
    exit;
}

// ✅ Logout normal
header("Location: index.php?page=landing");
exit;
