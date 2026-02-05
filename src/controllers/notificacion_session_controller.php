<?php
session_start();

// HEADERS PRIMERO
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Limpiar buffer
if (ob_get_level()) {
    ob_clean();
}

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado. Inicie sesión.',
        'session_id' => session_id()
    ]);
    exit;
}

// Incluir tu sistema de notificaciones de sesión
require_once __DIR__ . '/../utils/notificaciones_sin_db.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? 'contador';
$usuarioId = $_SESSION['usuario_id'];
$cargo = $_SESSION['usuario_cargo'] ?? 'Usuario';

$esCoordinador = ($cargo === 'Coordinador' || $cargo === 'Subcoordinador');

try {
    switch ($accion) {
        case 'contador':
            $resumen = NotificacionSesion::obtenerResumen();
            
            echo json_encode([
                'success' => true,
                'total' => $resumen['total'] ?? 0,
                'no_leidas' => $resumen['no_leidas'] ?? 0,
                'criticas' => $resumen['por_color']['danger'] ?? 0,
                'stock_bajo' => $resumen['por_color']['warning'] ?? 0,
                'cambios_datos' => $resumen['por_tipo']['solicitud_cambio_datos'] ?? 0,
                'esCoordinador' => $esCoordinador,
                'rol' => $cargo,
                'usuario_id' => $usuarioId
            ]);
            break;
            
        case 'obtener_notificaciones':
            $resumen = NotificacionSesion::obtenerResumen();
            
            echo json_encode([
                'success' => true,
                'total' => $resumen['total'] ?? 0,
                'no_leidas' => $resumen['no_leidas'] ?? 0,
                'criticas' => $resumen['por_color']['danger'] ?? 0,
                'stock_bajo' => $resumen['por_color']['warning'] ?? 0,
                'cambios_pendientes' => $resumen['por_tipo']['solicitud_cambio_datos'] ?? 0,
                'esCoordinador' => $esCoordinador
            ]);
            break;
            
        case 'listar':
            $notificaciones = NotificacionSesion::obtenerNotificaciones(null, 50);
            
            echo json_encode([
                'success' => true,
                'notificaciones' => $notificaciones,
                'total' => count($notificaciones),
                'esCoordinador' => $esCoordinador
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Acción no válida',
                'acciones_validas' => ['contador', 'obtener_notificaciones', 'listar']
            ]);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error interno',
        'message' => $e->getMessage()
    ]);
}
?>