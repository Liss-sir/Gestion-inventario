<?php
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../Config/database.php';

$id_usuario = $_SESSION['usuario_id'] ?? 0;
if (!$id_usuario) {
  echo json_encode(["success" => false, "message" => "No autenticado"]);
  exit;
}

$accion = $_GET["accion"] ?? "stats";
$limit  = (int)($_GET["limit"] ?? 50);

try {

  // ✅ KPIs
  if ($accion === "stats") {
    $stmt = $conn->prepare("
      SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas,
        SUM(CASE WHEN tipo = 'STOCK_BAJO' THEN 1 ELSE 0 END) as stock_bajo,
        SUM(CASE WHEN tipo = 'CAMBIO_DATOS' THEN 1 ELSE 0 END) as cambios_datos
      FROM notificaciones
      WHERE id_usuario = ?
    ");
    $stmt->execute([$id_usuario]);
    $resumen = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
      "success" => true,
      "stats" => [
        "total" => (int)($resumen["total"] ?? 0),
        "unread" => (int)($resumen["no_leidas"] ?? 0),
        "low" => (int)($resumen["stock_bajo"] ?? 0),
        "cambios" => (int)($resumen["cambios_datos"] ?? 0),
      ]
    ]);
    exit;
  }

  // ✅ LISTADO
  if ($accion === "list") {
    $stmt = $conn->prepare("
      SELECT n.*, u.nombre_completo as usuario_nombre 
      FROM notificaciones n
      LEFT JOIN usuarios u ON n.referencia_id = u.id_usuario
      WHERE n.id_usuario = ?
      ORDER BY n.fecha_creacion DESC
      LIMIT {$limit}
    ");
    $stmt->execute([$id_usuario]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
      "success" => true,
      "notificaciones" => $rows
    ]);
    exit;
  }

  echo json_encode(["success" => false, "message" => "Acción inválida"]);
  exit;

} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => "Error servidor"]);
  exit;
}
