<?php
// INICIO DEL ARCHIVO - PRIMERAS LÍNEAS

// Deshabilitar salida de errores HTML
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

// Configurar log de errores
$logFile = __DIR__ . '/../../logs/php_errors.log';
if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0777, true);
}
ini_set('error_log', $logFile);

// Solo mostrar JSON
header('Content-Type: application/json');

// Capturar errores y excepciones
function handleShutdown() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error interno del servidor',
            'debug' => ENVIRONMENT === 'development' ? $error['message'] : null
        ]);
        exit;
    }
}
register_shutdown_function('handleShutdown');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function($e) {
    error_log("PHP Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'debug' => ENVIRONMENT === 'development' ? $e->getMessage() : null
    ]);
    exit;
});

// Definir ambiente
define('ENVIRONMENT', 'development'); // Cambiar a 'production' en producción

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/solicitudes.php";


class SolicitudMaterialController {

    private $model;
    
    private function obtenerOCrearActividad($id_ficha, $id_rae, $id_instructor = 1)
    {
        // 🔥 OBTEN LA CONEXIÓN DIRECTAMENTE (porque ahora $db es public)
        $db = $this->model->db;
        
        try {
            // 1. Primero verificar si la tabla tiene algún registro
            $check = $db->query("SELECT COUNT(*) as total FROM actividades_formacion");
            $result = $check->fetch(PDO::FETCH_ASSOC);
            
            if ($result['total'] == 0) {
                // Tabla vacía, crear primera actividad
                $sql = "INSERT INTO actividades_formacion 
                        (id_ficha, id_rae, id_instructor, nombre_actividad, 
                         descripcion, tipo_trabajo, fecha_inicio, fecha_fin, estado)
                        VALUES (?, ?, ?, 'Actividad General para Solicitudes', 
                               'Actividad creada automáticamente para el sistema de inventario', 
                               'Individual', CURDATE(), 
                               DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'Activa')";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([$id_ficha, $id_rae, $id_instructor]);
                
                $id = $db->lastInsertId();
                error_log("📝 Primera actividad creada con ID: " . $id);
                return $id;
            }

            // 2. Buscar actividad existente para esta ficha y rae
            $sql = "SELECT id_actividad FROM actividades_formacion 
                    WHERE id_ficha = ? AND id_rae = ? 
                    LIMIT 1";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$id_ficha, $id_rae]);
            $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($actividad) {
                error_log("✅ Actividad encontrada: " . $actividad['id_actividad']);
                return $actividad['id_actividad'];
            }
            
            // 3. Si no existe, crear una nueva
            $sql = "INSERT INTO actividades_formacion 
                    (id_ficha, id_rae, id_instructor, nombre_actividad, 
                     descripcion, tipo_trabajo, fecha_inicio, fecha_fin, estado)
                    VALUES (?, ?, ?, 'Actividad para Solicitud de Materiales', 
                           'Actividad generada automáticamente para gestionar materiales de formación', 
                           'Individual', CURDATE(), 
                           DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'Activa')";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$id_ficha, $id_rae, $id_instructor]);
            
            $id = $db->lastInsertId();
            error_log("📝 Nueva actividad creada con ID: " . $id);
            return $id;
            
        } catch (Exception $e) {
            error_log("❌ Error en obtenerOCrearActividad: " . $e->getMessage());
            
            // Último recurso: buscar cualquier actividad
            $sql = "SELECT id_actividad FROM actividades_formacion LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($actividad) {
                error_log("⚠️ Usando actividad existente como fallback: " . $actividad['id_actividad']);
                return $actividad['id_actividad'];
            }
            
            // Si todo falla, intentar insertar con valores por defecto
            try {
                $sql = "INSERT INTO actividades_formacion 
                        (id_ficha, id_rae, id_instructor, nombre_actividad, estado)
                        VALUES (1, 1, 1, 'Actividad de Emergencia', 'Activa')";
                $db->exec($sql);
                return $db->lastInsertId();
            } catch (Exception $e2) {
                error_log("❌❌ Error crítico: No se pudo crear actividad: " . $e2->getMessage());
                return 1; // Valor por defecto
            }
        }
    }

    public function __construct($conn)
    {
        $this->model = new SolicitudMaterialModel($conn);
    }

    // Create request with details
    public function crear($data)
    {
        // Basic validation (business rule)
        if (empty($data['materiales']) || !is_array($data['materiales'])) {
            return [
                "success" => false,
                "error" => "La solicitud debe contener al menos un material."
            ];
        }

        // Validación de datos requeridos
        if (empty($data['id_ficha']) || empty($data['id_rae']) || empty($data['id_programa']) || empty($data['id_usuario'])) {
            return [
                "success" => false,
                "error" => "Faltan datos requeridos: ficha, rae, programa o usuario."
            ];
        }

        try {
            // 🔥 Obtener o crear actividad automáticamente
            if (empty($data['id_actividad']) || $data['id_actividad'] <= 0) {
                $data['id_actividad'] = $this->obtenerOCrearActividad(
                    $data['id_ficha'],
                    $data['id_rae'],
                    $data['id_usuario'] // Usa el ID del usuario como instructor
                );
                error_log("✅ Actividad asignada/creada: " . $data['id_actividad']);
            }

            $this->model->begin();

            $idSolicitud = $this->model->createSolicitudes($data);
            $this->model->addDetalle($idSolicitud, $data['materiales']);

            $this->model->commit();

            return [
                "success" => true,
                "message" => "Solicitud creada correctamente.",
                "id_solicitud" => $idSolicitud,
                "id_actividad_creada" => $data['id_actividad']
            ];

        } catch (Exception $e) {
            $this->model->rollback();
            error_log("❌ Error en crear(): " . $e->getMessage());

            return [
                "success" => false,
                "error" => "Error al crear la solicitud: " . $e->getMessage()
            ];
        }
    }

    // Approve or reject request
    public function responder($data)
    {
        $ok = $this->model->responderSolicitud(
            $data['id_solicitud'],
            $data['estado'],
            $data['id_usuario_aprobador'],
            $data['observaciones'] ?? null
        );
    
        if (!$ok) {
            return [
                "success" => false,
                "error" => "No se pudo responder la solicitud. Verifique su estado."
            ];
        }
    
        return [
            "success" => true,
            "message" => "La solicitud fue actualizada correctamente."
        ];
    }

    // Mark request as delivered
    public function entregar($data)
    {
        $ok = $this->model->marcarEntregada(
            $data['id_solicitud'],
            $data['id_usuario']
        );
    
        if (!$ok) {
            return [
                "success" => false,
                "error" => "La solicitud no puede marcarse como entregada."
            ];
        }
    
        return [
            "success" => true,
            "message" => "La solicitud fue marcada como entregada correctamente."
        ];
    }

    public function obtener($id)
    {
        return $this->model->getById($id);
    }

    public function obtenerCompleta($id)
    {
        return $this->model->getSolicitudCompleta($id);
    }

    // List all requests
    public function listar()
    {
        return $this->model->getAll();
    }

    // ============================================
    // FUNCIONES PARA LOS SELECTORES
    // ============================================
    
    public function obtenerProgramas()
    {
        return $this->model->getProgramas();
    }

    public function obtenerRaes($programaId)
    {
        return $this->model->getRaesPorPrograma($programaId);
    }

    public function obtenerFichas($programaId)
    {
        return $this->model->getFichasPorPrograma($programaId);
    }

    public function obtenerMateriales()
    {
        return $this->model->getMateriales();
    }
}

/* ACTION HANDLER */

$controller = new SolicitudMaterialController($conn);

$accion = $_GET['accion'] ?? null;

function sendJSON($data)
{
    header("Content-Type: application/json");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($accion) {

    case "crear":
        $data = json_decode(file_get_contents("php://input"), true);
        sendJSON($controller->crear($data));
        break;

    case "obtener":
        sendJSON($controller->obtener($_GET['id']));
        break;

    case "responder":
        $data = json_decode(file_get_contents("php://input"), true);
        sendJSON($controller->responder($data));
        break;
    
    case "entregar":
        $data = json_decode(file_get_contents("php://input"), true);
        sendJSON($controller->entregar($data));
        break;
    
    case "obtenerCompleta":
        sendJSON($controller->obtenerCompleta($_GET['id']));
        break;

    case "listar":
        sendJSON($controller->listar());
        break;

    // ============================================
    // CASOS PARA LOS SELECTORES
    // ============================================
    
    case "programas":
        sendJSON($controller->obtenerProgramas());
        break;

    case "raes":
        $programaId = isset($_GET['programa']) ? (int) $_GET['programa'] : 0;
        sendJSON($controller->obtenerRaes($programaId));
        break;

    case "fichas":
        $programaId = isset($_GET['programa']) ? (int) $_GET['programa'] : 0;
        sendJSON($controller->obtenerFichas($programaId));
        break;

    case "materiales":
        sendJSON($controller->obtenerMateriales());
        break;

    default:
        sendJSON([
            "success" => false,
            "error" => "Acción no válida."
        ]);
}
?>