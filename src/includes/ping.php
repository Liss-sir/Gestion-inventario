<?php
// src/includes/ping.php
// Mantiene la sesión viva (actualiza LAST_ACTIVITY) sin recargar páginas.
// Devuelve JSON para que JS sepa si la sesión sigue activa.

header("Content-Type: application/json; charset=utf-8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si no hay sesión válida, avisar al frontend
if (empty($_SESSION['usuario_id']) || empty($_SESSION['token_sesion'])) {
    echo json_encode([
        "success" => false,
        "expired" => true,
        "message" => "No session"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Actualizar actividad
$_SESSION['LAST_ACTIVITY'] = time();

// Actualizar actividad en BD (heartbeat de token)
try {
    require_once __DIR__ . '/../../Config/database.php';

    $stmtBeat = $conn->prepare("\n        UPDATE sesiones_usuarios\n        SET fecha_ultima_actividad = NOW()\n        WHERE id_usuario = :id\n          AND token_sesion = :token\n          AND activa = 1\n    ");
    $stmtBeat->execute([
        ':id' => (int)$_SESSION['usuario_id'],
        ':token' => (string)$_SESSION['token_sesion']
    ]);
} catch (Throwable $e) {
    // No romper ping por fallo de BD
}

echo json_encode([
    "success" => true,
    "expired" => false,
    "message" => "OK",
    "timestamp" => $_SESSION['LAST_ACTIVITY']
], JSON_UNESCAPED_UNICODE);
