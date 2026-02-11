<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
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
        echo json_encode(['error' => $message]);
        exit;
    }
    
    private function logDebug($message, $data = null) {
        error_log('[NOTIFICACIONES] ' . $message . ($data ? ': ' . print_r($data, true) : ''));
    }
    
    public function handleRequest() {
        $accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
        
        if (!isset($_SESSION['usuario_id'])) {
            $this->sendError('No autorizado. Inicie sesión.');
            return;
        }
        
        $usuarioId = $_SESSION['usuario_id'];
        $cargo = $_SESSION['usuario_cargo'] ?? 'Usuario';

        $esCoordinador = ($cargo === 'Coordinador' || $cargo === 'Subcoordinador');
        
        switch ($accion) {

            case 'contador':
                $this->obtenerContador($usuarioId, $esCoordinador);
                break;

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

    private function obtenerContador($usuarioId, $esCoordinador) {
        try {
            if ($esCoordinador) {
                $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas,
                    SUM(CASE WHEN prioridad = 'CRITICA' AND leida = 0 THEN 1 ELSE 0 END) as criticas,
                    SUM(CASE WHEN tipo = 'STOCK_BAJO' AND leida = 0 THEN 1 ELSE 0 END) as stock_bajo,
                    SUM(CASE WHEN tipo IN ('CAMBIO_DATOS','ROL_ASIGNADO') AND leida = 0 THEN 1 ELSE 0 END) as cambios_datos
                    FROM notificaciones";
                $stmt = $this->conn->prepare($query);
            } else {
                $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas,
                    SUM(CASE WHEN tipo IN ('CAMBIO_DATOS','ROL_ASIGNADO') AND leida = 0 THEN 1 ELSE 0 END) as cambios_datos
                    FROM notificaciones 
                    WHERE id_usuario = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(1, $usuarioId);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'total' => (int)($result['total'] ?? 0),
                'no_leidas' => (int)($result['no_leidas'] ?? 0),
                'criticas' => (int)($result['criticas'] ?? 0),
                'stock_bajo' => (int)($result['stock_bajo'] ?? 0),
                'cambios_datos' => (int)($result['cambios_datos'] ?? 0),
                'esCoordinador' => $esCoordinador
            ]);
            
        } catch (PDOException $e) {
            $this->sendError('Error BD contador: ' . $e->getMessage());
        }
    }

    private function obtenerNotificacionesDashboard($usuarioId, $esCoordinador) {
        try {
            if ($esCoordinador) {
                $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas,
                    SUM(CASE WHEN prioridad = 'CRITICA' AND leida = 0 THEN 1 ELSE 0 END) as criticas,
                    SUM(CASE WHEN tipo = 'STOCK_BAJO' AND leida = 0 THEN 1 ELSE 0 END) as stock_bajo,
                    SUM(CASE WHEN tipo IN ('CAMBIO_DATOS','ROL_ASIGNADO') AND leida = 0 THEN 1 ELSE 0 END) as cambios_pendientes
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
            
            echo json_encode([
                'success' => true,
                'total' => (int)($stats['total'] ?? 0),
                'no_leidas' => (int)($stats['no_leidas'] ?? 0),
                'criticas' => (int)($stats['criticas'] ?? 0),
                'stock_bajo' => (int)($stats['stock_bajo'] ?? 0),
                'cambios_pendientes' => (int)($stats['cambios_pendientes'] ?? 0),
                'esCoordinador' => $esCoordinador
            ]);
            
        } catch (PDOException $e) {
            $this->sendError('Error BD dashboard: ' . $e->getMessage());
        }
    }

    private function listarNotificaciones($usuarioId, $esCoordinador) {
        try {
            if ($esCoordinador) {
                $query = "SELECT n.*, u.nombre_completo as usuario_nombre 
                          FROM notificaciones n
                          LEFT JOIN usuarios u ON n.id_usuario = u.id_usuario
                          ORDER BY n.fecha_creacion DESC 
                          LIMIT 50";
                $stmt = $this->conn->prepare($query);
            } else {
                $query = "SELECT n.*, u.nombre_completo as usuario_nombre 
                          FROM notificaciones n
                          LEFT JOIN usuarios u ON n.id_usuario = u.id_usuario
                          WHERE n.id_usuario = ?
                          ORDER BY n.fecha_creacion DESC 
                          LIMIT 50";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(1, $usuarioId);
            }
            
            $stmt->execute();
            $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'notificaciones' => $notificaciones,
                'total' => count($notificaciones),
                'esCoordinador' => $esCoordinador
            ]);
            
        } catch (PDOException $e) {
            $this->sendError('Error BD listar: ' . $e->getMessage());
        }
    }

    private function marcarComoLeida($idNotificacion, $usuarioId, $esCoordinador) {
        try {
            if ($esCoordinador) {
                $query = "UPDATE notificaciones SET leida = 1 WHERE id_notificacion = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(1, $idNotificacion);
            } else {
                $query = "UPDATE notificaciones SET leida = 1 WHERE id_notificacion = ? AND id_usuario = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(1, $idNotificacion);
                $stmt->bindParam(2, $usuarioId);
            }
            
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Notificación marcada como leída'
            ]);
            
        } catch (PDOException $e) {
            $this->sendError('Error BD marcar leída: ' . $e->getMessage());
        }
    }

    private function marcarTodasLeidas($usuarioId, $esCoordinador) {
        try {
            if ($esCoordinador) {
                $query = "UPDATE notificaciones SET leida = 1 WHERE leida = 0";
                $stmt = $this->conn->prepare($query);
            } else {
                $query = "UPDATE notificaciones SET leida = 1 WHERE id_usuario = ? AND leida = 0";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(1, $usuarioId);
            }
            
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Todas las notificaciones marcadas como leídas'
            ]);
            
        } catch (PDOException $e) {
            $this->sendError('Error BD marcar todas: ' . $e->getMessage());
        }
    }
}

// Ejecutar controlador
try {
    $controller = new NotificacionController();
    $controller->handleRequest();
} catch (Exception $e) {
    echo json_encode(['error' => 'Error fatal: ' . $e->getMessage()]);
}
