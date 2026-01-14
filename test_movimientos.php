<?php
require_once 'Config/database.php';
require_once 'src/models/movimiento.php';

header('Content-Type: application/json');

try {
    // Verificar conexión
    if (!$conn) {
        throw new Exception('No hay conexión a BD');
    }

    // Contar movimientos
    $result = $conn->query('SELECT COUNT(*) as total FROM movimientos');
    $count = $result->fetch(PDO::FETCH_ASSOC);
    
    // Listar movimientos
    $model = new MovimientoModel($conn);
    $movimientos = $model->listarMovimientos();
    
    echo json_encode([
        'success' => true,
        'total_registros' => (int)$count['total'],
        'movimientos' => $movimientos,
        'count_fetch' => count($movimientos)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
