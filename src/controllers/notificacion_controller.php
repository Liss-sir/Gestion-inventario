<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Establecer headers JSON explícitamente
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Evitar cualquier salida antes del JSON
ob_clean();

class NotificacionController {
    private $conn;
    
    public function __construct() {
        try {
            $database = new Database();
            $this->conn = $database->getConnection();
        } catch (Exception $e) {
            $this->sendError('Error de conexión a BD: ' . $e->getMessage());
        }
    }
    
    private function sendError($message) {
        echo json_encode(['error' => $message, 'debug' => debug_backtrace()]);
        exit;
    }
    
    private function logDebug($message, $data = null) {
        error_log('[NOTIFICACIONES] ' . $message . ($data ? ': ' . print_r($data, true) : ''));
    }
    
    public function handleRequest() {
        $accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
        
        $this->logDebug('Acción solicitada', $accion);
        $this->logDebug('Sesión actual', $_SESSION);
        
        // Verificar sesión
        if (!isset($_SESSION['usuario_id'])) {
            $this->sendError('No autorizado. Inicie sesión. Sesión ID: ' . session_id());
            return;
        }
        
        $usuarioId = $_SESSION['usuario_id'];
        $cargo = $_SESSION['usuario_cargo'] ?? 'Usuario';

        $esCoordinador = ($cargo === 'Coordinador' || $cargo === 'Subcoordinador');
        
        $this->logDebug('Usuario', [
            'id' => $usuarioId,
            'cargo' => $cargo,
            'esCoordinador' => $esCoordinador
        ]);
        
        switch ($accion) {
            case 'contador':

    if (!isset($_SESSION['usuario_id'])) {
        echo json_encode([
            'success' => false,
            'no_leidas' => 0,
            'total' => 0
        ]);
        exit;
    }

    $idUsuario = $_SESSION['usuario_id'];

    $stmt = $this->conn->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) AS no_leidas,
            SUM(CASE WHEN tipo = 'CAMBIO_DATOS' AND leida = 0 THEN 1 ELSE 0 END) AS cambios_datos
        FROM notificaciones
        WHERE id_usuario = ?
    ");
    $stmt->execute([$idUsuario]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'       => true,
        'no_leidas'     => (int)($row['no_leidas'] ?? 0),
        'total'         => (int)($row['total'] ?? 0),
        'criticas'      => 0,
        'stock_bajo'    => 0,
        'cambios_datos' => (int)($row['cambios_datos'] ?? 0),
        'esCoordinador' => ($_SESSION['usuario_cargo'] ?? '') === 'Coordinador'
    ]);
    exit;

                
            case 'listar':
                $this->listarNotificaciones($usuarioId, $esCoordinador);
                break;
                
            case 'obtener_notificaciones':
                $this->obtenerNotificacionesDashboard($usuarioId, $esCoordinador);
                break;
                
            case 'marcar-leida':
                $idNotificacion = $_POST['id_notificacion'] ?? 0;
                $this->marcarComoLeida($idNotificacion, $usuarioId, $esCoordinador);
                break;
                
            case 'marcar-todas':
                $this->marcarTodasLeidas($usuarioId, $esCoordinador);
                break;
                
            default:
                $this->sendError('Acción no válida: ' . $accion);
                break;
        }
    }
    
    private function obtenerContador($usuarioId, $esCoordinador, $cargo) {
        try {
            $this->logDebug('Obteniendo contador', ['usuarioId' => $usuarioId, 'esCoordinador' => $esCoordinador]);
            
            if ($esCoordinador) {
                // Coordinador ve todas las notificaciones no leídas
                $query = "SELECT 
                         COUNT(*) as total,
                         SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas,
                         SUM(CASE WHEN prioridad = 'CRITICA' AND leida = 0 THEN 1 ELSE 0 END) as criticas,
                         SUM(CASE WHEN tipo = 'STOCK_BAJO' AND leida = 0 THEN 1 ELSE 0 END) as stock_bajo,
                         SUM(CASE WHEN tipo = 'CAMBIO_DATOS' AND leida = 0 THEN 1 ELSE 0 END) as cambios_datos
                         FROM notificaciones";
                $stmt = $this->conn->prepare($query);
            } else {
                // Usuario normal ve solo sus notificaciones
                $query = "SELECT 
                         COUNT(*) as total,
                         SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas
                         FROM notificaciones 
                         WHERE id_usuario = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(1, $usuarioId);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->logDebug('Resultado contador', $result);
            
            $response = [
                'success' => true,
                'total' => (int)($result['total'] ?? 0),
                'no_leidas' => (int)($result['no_leidas'] ?? 0),
                'criticas' => (int)($result['criticas'] ?? 0),
                'stock_bajo' => (int)($result['stock_bajo'] ?? 0),
                'cambios_datos' => (int)($result['cambios_datos'] ?? 0),
                'esCoordinador' => $esCoordinador,
                'rol' => $cargo,
                'debug' => [
                    'usuario_id' => $usuarioId,
                    'query' => $query
                ]
            ];
            
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (PDOException $e) {
            $this->sendError('Error en la base de datos: ' . $e->getMessage());
        }
    }
    
    private function obtenerNotificacionesDashboard($usuarioId, $esCoordinador) {
        try {
            $this->logDebug('Obteniendo dashboard', ['usuarioId' => $usuarioId, 'esCoordinador' => $esCoordinador]);
            
            // Esta función es específica para el dashboard del coordinador
            if ($esCoordinador) {
                $query = "SELECT 
                         COUNT(*) as total,
                         SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas,
                         SUM(CASE WHEN prioridad = 'CRITICA' AND leida = 0 THEN 1 ELSE 0 END) as criticas,
                         SUM(CASE WHEN tipo = 'STOCK_BAJO' AND leida = 0 THEN 1 ELSE 0 END) as stock_bajo,
                         SUM(CASE WHEN tipo = 'CAMBIO_DATOS' AND leida = 0 THEN 1 ELSE 0 END) as cambios_pendientes
                         FROM notificaciones";
                $stmt = $this->conn->prepare($query);
            } else {
                $query = "SELECT 
                         COUNT(*) as total,
                         SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas
                         FROM notificaciones 
                         WHERE id_usuario = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(1, $usuarioId);
            }
            
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->logDebug('Estadísticas dashboard', $stats);
            
            $response = [
                'success' => true,
                'total' => (int)($stats['total'] ?? 0),
                'no_leidas' => (int)($stats['no_leidas'] ?? 0),
                'criticas' => (int)($stats['criticas'] ?? 0),
                'stock_bajo' => (int)($stats['stock_bajo'] ?? 0),
                'cambios_pendientes' => (int)($stats['cambios_pendientes'] ?? 0),
                'esCoordinador' => $esCoordinador,
                'debug' => [
                    'usuario_id' => $usuarioId,
                    'query' => $query
                ]
            ];
            
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (PDOException $e) {
            $this->sendError('Error en la base de datos: ' . $e->getMessage());
        }
    }
    
    private function listarNotificaciones($usuarioId, $esCoordinador) {
        try {
            $this->logDebug('Listando notificaciones', ['usuarioId' => $usuarioId, 'esCoordinador' => $esCoordinador]);
            
            if ($esCoordinador) {
                // Coordinador ve todas las notificaciones
                $query = "SELECT n.*, u.nombre_completo as usuario_nombre 
                         FROM notificaciones n
                         LEFT JOIN usuarios u ON n.id_usuario = u.id_usuario
                         ORDER BY n.created_at DESC 
                         LIMIT 20";
                $stmt = $this->conn->prepare($query);
            } else {
                // Usuario normal ve solo sus notificaciones
                $query = "SELECT n.*, u.nombre_completo as usuario_nombre 
                         FROM notificaciones n
                         LEFT JOIN usuarios u ON n.id_usuario = u.id_usuario
                         WHERE n.id_usuario = ?
                         ORDER BY n.created_at DESC 
                         LIMIT 20";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(1, $usuarioId);
            }
            
            $stmt->execute();
            $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->logDebug('Notificaciones encontradas', count($notificaciones));
            
            $response = [
                'success' => true,
                'notificaciones' => $notificaciones,
                'total' => count($notificaciones),
                'esCoordinador' => $esCoordinador,
                'debug' => [
                    'usuario_id' => $usuarioId,
                    'query' => $query
                ]
            ];
            
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (PDOException $e) {
            $this->sendError('Error en la base de datos: ' . $e->getMessage());
        }
    }
    
    private function marcarComoLeida($idNotificacion, $usuarioId, $esCoordinador) {
        try {
            $this->logDebug('Marcando como leída', ['id_notificacion' => $idNotificacion, 'usuarioId' => $usuarioId]);
            
            // Marcar como leída
            if ($esCoordinador) {
                $query = "UPDATE notificaciones SET leida = 1 WHERE id_notificacion = ?";
            } else {
                $query = "UPDATE notificaciones SET leida = 1 WHERE id_notificacion = ? AND id_usuario = ?";
            }
            
            $stmt = $this->conn->prepare($query);
            
            if ($esCoordinador) {
                $stmt->bindParam(1, $idNotificacion);
            } else {
                $stmt->bindParam(1, $idNotificacion);
                $stmt->bindParam(2, $usuarioId);
            }
            
            $stmt->execute();
            
            $response = [
                'success' => true,
                'message' => 'Notificación marcada como leída',
                'debug' => [
                    'id_notificacion' => $idNotificacion,
                    'rows_affected' => $stmt->rowCount()
                ]
            ];
            
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (PDOException $e) {
            $this->sendError('Error en la base de datos: ' . $e->getMessage());
        }
    }
    
    private function marcarTodasLeidas($usuarioId, $esCoordinador) {
        try {
            $this->logDebug('Marcando todas como leídas', ['usuarioId' => $usuarioId, 'esCoordinador' => $esCoordinador]);
            
            if ($esCoordinador) {
                $query = "UPDATE notificaciones SET leida = 1 WHERE leida = 0";
                $stmt = $this->conn->prepare($query);
            } else {
                $query = "UPDATE notificaciones SET leida = 1 WHERE id_usuario = ? AND leida = 0";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(1, $usuarioId);
            }
            
            $stmt->execute();
            
            $response = [
                'success' => true,
                'message' => 'Todas las notificaciones han sido marcadas como leídas',
                'rows_affected' => $stmt->rowCount()
            ];
            
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (PDOException $e) {
            $this->sendError('Error en la base de datos: ' . $e->getMessage());
        }
    }
}

// Ejecutar controlador
try {
    $controller = new NotificacionController();
    $controller->handleRequest();
} catch (Exception $e) {
    echo json_encode(['error' => 'Error fatal: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>