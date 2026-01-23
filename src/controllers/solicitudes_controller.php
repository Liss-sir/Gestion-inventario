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

    // ✅ NUEVO: utilidades internas (sin tocar tu model)
    private function tableExists($tableName)
    {
        // Usamos la conexión pública de tu modelo como ya haces arriba
        $db = $this->model->db;

        try {
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            return $stmt->fetchColumn() ? true : false;
        } catch (Exception $e) {
            error_log("❌ Error tableExists($tableName): " . $e->getMessage());
            return false;
        }
    }

    private function obtenerOCrearActividad($id_ficha, $id_rae, $id_instructor = 1)
    {
        //  OBTEN LA CONEXIÓN DIRECTAMENTE (porque ahora $db es public)
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
                error_log(" Primera actividad creada con ID: " . $id);
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
                error_log(" Actividad encontrada: " . $actividad['id_actividad']);
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
            error_log(" Nueva actividad creada con ID: " . $id);
            return $id;

        } catch (Exception $e) {
            error_log(" Error en obtenerOCrearActividad: " . $e->getMessage());

            // Último recurso: buscar cualquier actividad
            $sql = "SELECT id_actividad FROM actividades_formacion LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $actividad = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($actividad) {
                error_log(" Usando actividad existente como fallback: " . $actividad['id_actividad']);
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
                error_log(" Error crítico: No se pudo crear actividad: " . $e2->getMessage());
                return 1; // Valor por defecto
            }
        }
    }

    public function __construct($conn)
    {
        $this->model = new SolicitudMaterialModel($conn);
    }

    public function obtenerActividades($id_ficha, $id_rae)
{
    try {
        $id_ficha = (int)$id_ficha;
        $id_rae   = (int)$id_rae;

        if ($id_ficha <= 0 || $id_rae <= 0) {
            return [];
        }

        $db = $this->model->db;

        $sql = "SELECT id_actividad, nombre_actividad
                FROM actividades_formacion
                WHERE id_ficha = ?
                  AND id_rae = ?
                  AND (estado = 'Activa' OR estado = 'Activo')
                ORDER BY nombre_actividad ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute([$id_ficha, $id_rae]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log('❌ Error obtenerActividades(): ' . $e->getMessage());
        return [];
    }
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
        file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " [CONTROLLER] responder() invocado: " . json_encode($data) . "\n", FILE_APPEND);
        
        $ok = $this->model->responderSolicitud(
            $data['id_solicitud'],
            $data['estado'],
            $data['id_usuario_aprobador'],
            $data['observaciones'] ?? null
        );

        if (!$ok) {
            file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " ❌ [CONTROLLER] responderSolicitud retornó false\n", FILE_APPEND);
            return [
                "success" => false,
                "error" => "No se pudo responder la solicitud. Verifique su estado."
            ];
        }

        file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " ✅ [CONTROLLER] Solicitud respondida exitosamente\n", FILE_APPEND);
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

    // ============================================================
    // ✅ NUEVO: BODEGAS / SUBBODEGAS / MATERIALES FILTRADOS
    // ============================================================

    public function obtenerBodegas()
    {
        $db = $this->model->db;

        try {
            if (!$this->tableExists("bodegas")) {
                return [];
            }

            $sql = "
                SELECT 
                    id_bodega,
                    codigo_bodega,
                    nombre
                FROM bodegas
                ORDER BY nombre ASC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("❌ Error obtenerBodegas(): " . $e->getMessage());
            return [];
        }
    }

    public function obtenerSubBodegas($idBodega)
    {
        $db = $this->model->db;

        try {
            $tabla = null;
            $candidatas = ["subbodegas", "sub_bodegas", "sub_bodega"];

            foreach ($candidatas as $t) {
                if ($this->tableExists($t)) {
                    $tabla = $t;
                    break;
                }
            }

            if (!$tabla) {
                return [];
            }

            $sql = "
                SELECT 
                    id_subbodega,
                    codigo_subbodega,
                    nombre_subbodega
                FROM {$tabla}
                WHERE id_bodega = ?
                ORDER BY nombre_subbodega ASC
            ";

            try {
                $stmt = $db->prepare($sql);
                $stmt->execute([$idBodega]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $inner) {
                error_log("⚠️ Fallback subbodegas: " . $inner->getMessage());

                $sql2 = "
                    SELECT 
                        id_sub_bodega AS id_subbodega,
                        codigo_sub_bodega AS codigo_subbodega,
                        nombre AS nombre_subbodega
                    FROM {$tabla}
                    WHERE id_bodega = ?
                    ORDER BY nombre ASC
                ";
                $stmt2 = $db->prepare($sql2);
                $stmt2->execute([$idBodega]);
                return $stmt2->fetchAll(PDO::FETCH_ASSOC);
            }

        } catch (Exception $e) {
            error_log("❌ Error obtenerSubBodegas(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ FIX REAL: materiales filtrados por bodega/subbodega
     *
     * - Solo bodega => stock_bodega
     * - Bodega + subbodega => stock_subbodega (NO debe traer lo de bodega si subbodega está vacía)
     */
    public function obtenerMaterialesFiltrados($idBodega, $idSubBodega = 0)
    {
        $db = $this->model->db;

        try {

            // ✅ Si hay subbodega seleccionada => CONSULTAR SOLO stock_subbodega
            if ((int)$idSubBodega > 0) {

                if ($this->tableExists("stock_subbodega") && $this->tableExists("material_formacion")) {

                    // ✅ Opcional: validamos que la subbodega pertenezca a la bodega
                    // (esto evita inconsistencias si mandan IDs cruzados)
                    if ($this->tableExists("subbodegas")) {
                        $sqlCheck = "SELECT id_subbodega FROM subbodegas WHERE id_subbodega = ? AND id_bodega = ? LIMIT 1";
                        $stCheck = $db->prepare($sqlCheck);
                        $stCheck->execute([$idSubBodega, $idBodega]);
                        $ok = $stCheck->fetch(PDO::FETCH_ASSOC);

                        if (!$ok) {
                            // Subbodega no pertenece a esa bodega -> retorna vacío
                            return [];
                        }
                    }

                                        $sql = "
                                                SELECT 
                                                        mf.id_material,
                                                        mf.nombre,
                                                        mf.codigo_inventario,
                                                        mf.descripcion,
                                                        mf.unidad_medida,
                                                        mf.clasificacion,
                                                        mf.estado,
                                                        GREATEST(COALESCE(SUM(ss.stock_actual), 0), 0) AS stock_actual
                                                FROM material_formacion mf
                                                INNER JOIN stock_subbodega ss ON ss.id_material = mf.id_material
                                                WHERE mf.estado = 'Disponible'
                                                    AND ss.id_subbodega = ?
                                                GROUP BY mf.id_material
                                                ORDER BY mf.nombre ASC
                                        ";

                    $stmt = $db->prepare($sql);
                    $stmt->execute([$idSubBodega]);
                    return $stmt->fetchAll(PDO::FETCH_ASSOC);
                }

                // ✅ Si no existe stock_subbodega, retornamos vacío (no bodega) para no causar error lógico
                error_log("⚠️ No existe stock_subbodega o material_formacion. Retornando vacío para subbodega.");
                return [];
            }

            // ✅ Caso solo bodega => stock_bodega
            if ((int)$idBodega > 0 && $this->tableExists("stock_bodega") && $this->tableExists("material_formacion")) {

                                $sql = "
                                        SELECT 
                                                mf.id_material,
                                                mf.nombre,
                                                mf.codigo_inventario,
                                                mf.descripcion,
                                                mf.unidad_medida,
                                                mf.clasificacion,
                                                mf.estado,
                                                GREATEST(COALESCE(SUM(sb.stock_actual), 0), 0) AS stock_actual
                                        FROM material_formacion mf
                                        INNER JOIN stock_bodega sb ON sb.id_material = mf.id_material
                                        WHERE mf.estado = 'Disponible'
                                            AND sb.id_bodega = ?
                                        GROUP BY mf.id_material
                                        ORDER BY mf.nombre ASC
                                ";

                $stmt = $db->prepare($sql);
                $stmt->execute([$idBodega]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // ✅ fallback sin romper el front
            error_log("⚠️ No existe stock_bodega o material_formacion. Fallback a obtenerMateriales()");
            return $this->obtenerMateriales();

        } catch (Exception $e) {
            error_log("❌ Error obtenerMaterialesFiltrados(): " . $e->getMessage());
            return $this->obtenerMateriales();
        }
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

    // ✅ materiales filtra por bodega o subbodega (subbodega manda a stock_subbodega)
    case "materiales":
        $bodegaId = isset($_GET['bodega']) ? (int) $_GET['bodega'] : 0;
        $subId    = isset($_GET['subbodega']) ? (int) $_GET['subbodega'] : 0;

        if ($bodegaId > 0) {
            sendJSON($controller->obtenerMaterialesFiltrados($bodegaId, $subId));
        } else {
            sendJSON($controller->obtenerMateriales());
        }
        break;

    case "bodegas":
        sendJSON($controller->obtenerBodegas());
        break;

    case "subbodegas":
        $bodegaId = isset($_GET['bodega']) ? (int) $_GET['bodega'] : 0;
        sendJSON($controller->obtenerSubBodegas($bodegaId));
        break;

    case "actividades":
        $fichaId = isset($_GET['ficha']) ? (int) $_GET['ficha'] : 0;
        $raeId   = isset($_GET['rae']) ? (int) $_GET['rae'] : 0;
        sendJSON($controller->obtenerActividades($fichaId, $raeId));
        break;

    default:
        sendJSON([
            "success" => false,
            "error" => "Acción no válida."
        ]);
}
?>