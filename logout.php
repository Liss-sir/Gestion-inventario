<?php
session_start();

// ✅ necesario para que exista $conn (logout.php está en la RAÍZ del proyecto)
require_once __DIR__ . '/Config/database.php';

// ✅ guardar el token antes de limpiar la sesión
$tokenSesion = $_SESSION['token_sesion'] ?? null;

if ($tokenSesion) {
    $stmt = $conn->prepare("
        UPDATE sesiones_usuarios
        SET activa = 0
        WHERE token_sesion = :token
    ");
    $stmt->execute([
        ':token' => $tokenSesion
    ]);
}

session_unset();
session_destroy();

// ✅ NUEVO: si el logout fue por inactividad, mandar a login
$reason = $_GET['reason'] ?? '';
if ($reason === 'idle_timeout') {
    header("Location: index.php?page=login&reason=idle_timeout");
    exit;
}

// Redirigir a la landing (logout normal)
header("Location: index.php?page=landing");
exit;
