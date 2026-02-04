<?php
// ================= CONFIGURACIÓN INICIAL =================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// ================= DEPENDENCIAS =================
require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/solicitudes.php";

// ================= CONTROLLER =================
class SolicitudMaterialController {

    private $model;

    public function __construct($conn) {
        $this->model = new SolicitudMaterialModel($conn);
    }

    // ================= NOTIFICACIONES =================
    private function enviarNotificacion($id_usuario, $titulo, $mensaje, $tipo, $referencia_id) {
        try {
            $sql = "INSERT INTO notificaciones
                    (id_usuario, titulo, mensaje, tipo, referencia_id, leida, fecha_creacion)
                    VALUES (?, ?, ?, ?, ?, 0, NOW())";

            $stmt = $this->model->db->prepare($sql);
            $stmt->execute([
                $id_usuario,
                $titulo,
                $mensaje,
                $tipo,
                $referencia_id
            ]);
            return true;
        } catch (Exception $e) {
            error_log("❌ Error en enviarNotificacion: " . $e->getMessage());
            return false;
        }
    }

    // ================= CREAR SOLICITUD =================
    public function crear($data) {
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
            // 🔥 Obtener o crear actividad automáticamente si no viene
            if (empty($data['id_actividad']) || $data['id_actividad'] <= 0) {
                $data['id_actividad'] = $this->obtenerOCrearActividad(
                    $data['id_ficha'],
                    $data['id_rae'],
                    $data['id_usuario']
                );
                error_log("✅ Actividad asignada/creada: " . $data['id_actividad']);
            }

            $this->model->begin();

            $idSolicitud = $this->model->createSolicitudes($data);
            $this->model->addDetalle($idSolicitud, $data['materiales']);

            $this->model->commit();

            // 🔔 Notificar coordinador y encargado de bodega
            $nombre = $_SESSION['usuario_nombre'] ?? 'Instructor';
            $this->enviarNotificacionMultiple(
                ['coordinador', 'encargado_bodega'],
                "Nueva Solicitud #$idSolicitud",
                "$nombre creó una nueva solicitud de materiales",
                "SOLICITUD_NUEVA",
                $idSolicitud
            );

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

    // ================= RESPONDER SOLICITUD =================
    public function responder($data) {
        error_log("📝 responder() invocado: " . json_encode($data));
        
        // Obtener la solicitud antes de cambiar el estado
        $solicitud = $this->model->getById($data['id_solicitud']);
        if (!$solicitud) {
            return [
                "success" => false,
                "error" => "Solicitud no encontrada"
            ];
        }

        // Cambiar el estado
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

        // Enviar notificación al solicitante
        $estado = strtolower($data['estado']);
        $titulo = "Solicitud " . ucfirst($estado);
        $mensaje = "Tu solicitud #{$data['id_solicitud']} fue " . $estado;
        $tipo = ($estado === 'aprobada') ? "SOLICITUD_APROBADA" : "SOLICITUD_RECHAZADA";
        
        $this->enviarNotificacion(
            $solicitud['id_usuario_solicitante'],
            $titulo,
            $mensaje,
            $tipo,
            $data['id_solicitud']
        );

        return [
            "success" => true,
            "message" => "La solicitud fue actualizada correctamente y se notificó al solicitante."
        ];
    }

    // ================= ENTREGAR SOLICITUD =================
    public function entregar($data) {
        // 1️⃣ Traemos la solicitud antes de cambiar el estado
        $solicitud = $this->model->getById($data['id_solicitud']);

        if (!$solicitud) {
            return [
                "success" => false,
                "error" => "Solicitud no encontrada"
            ];
        }

        // 2️⃣ Marcamos como entregada
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

        // 3️⃣ Enviamos notificación al solicitante
        $nombre = $_SESSION['usuario_nombre'] ?? 'Bodega';

        $this->enviarNotificacion(
            $solicitud['id_usuario_solicitante'],
            "Material entregado",
            "$nombre entregó el material de la solicitud #{$data['id_solicitud']}",
            "SOLICITUD_ENTREGADA",
            $data['id_solicitud']
        );

        return [
            "success" => true,
            "message" => "La solicitud fue marcada como entregada y notificada."
        ];
    }

    // ================= HELPER: Notificar múltiples usuarios =================
    private function enviarNotificacionMultiple($cargos, $titulo, $mensaje, $tipo, $referencia_id) {
        try {
            $placeholders = implode(',', array_fill(0, count($cargos), '?'));
            $sql = "SELECT id_usuario FROM usuarios WHERE cargo IN ($placeholders) AND estado = 'activo'";
            
            $stmt = $this->model->db->prepare($sql);
            $stmt->execute($cargos);
            $receptores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($receptores as $r) {
                $this->enviarNotificacion(
                    $r['id_usuario'],
                    $titulo,
                    $mensaje,
                    $tipo,
                    $referencia_id
                );
            }
        } catch (Exception $e) {
            error_log("❌ Error en enviarNotificacionMultiple: " . $e->getMessage());
        }
    }

    // ================= HELPER: Obtener o crear actividad =================
    private function obtenerOCrearActividad($id_ficha, $id_rae, $id_instructor = 1) {
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

                return $db->lastInsertId();
            }

            // 2. Buscar actividad existente para esta ficha y rae
            $sql = "SELECT id_actividad FROM actividades_formacion 
                    WHERE id_ficha = ? AND id_rae = ? 
                    LIMIT 1";

            $stmt = $db->prepare($sql);
            $stmt->execute([$id_ficha, $id_rae]);
            $actividad = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($actividad) {
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

            return $db->lastInsertId();

        } catch (Exception $e) {
            error_log("❌ Error en obtenerOCrearActividad: " . $e->getMessage());

            // Último recurso: buscar cualquier actividad
            $sql = "SELECT id_actividad FROM actividades_formacion LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $actividad = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($actividad) {
                return $actividad['id_actividad'];
            }

            return 1; // Valor por defecto
        }
    }

    // ================= OBTENER ACTIVIDADES =================
    public function obtenerActividades($id_ficha, $id_rae) {
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

    // ================= LISTADOS =================
    public function listar() {
        return $this->model->getAll();
    }

    public function obtenerCompleta($id) {
        return $this->model->getSolicitudCompleta($id);
    }

    public function obtener($id) {
        return $this->model->getById($id);
    }

    public function programas() {
        return $this->model->getProgramas();
    }

    public function programasPorUsuario($id_usuario) {
        return $this->model->getProgramasUsuario($id_usuario);
    }

    public function raes($id) {
        return $this->model->getRaesPorPrograma($id);
    }

    public function fichas($id) {
        return $this->model->getFichasPorPrograma($id);
    }

    public function materiales($bodega, $subbodega) {
        if ($bodega > 0) {
            if ($subbodega > 0) {
                $sql = "SELECT mf.*, ss.stock_actual
                        FROM material_formacion mf
                        INNER JOIN stock_subbodega ss ON ss.id_material = mf.id_material
                        WHERE ss.id_subbodega = ?
                        AND mf.estado = 'Disponible'";

                $stmt = $this->model->db->prepare($sql);
                $stmt->execute([$subbodega]);

            } else {
                $sql = "SELECT mf.*, sb.stock_actual
                        FROM material_formacion mf
                        INNER JOIN stock_bodega sb ON sb.id_material = mf.id_material
                        WHERE sb.id_bodega = ?
                        AND mf.estado = 'Disponible'";

                $stmt = $this->model->db->prepare($sql);
                $stmt->execute([$bodega]);
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $this->model->getMateriales();
    }

    // ================= BODEGAS Y SUBBODEGAS =================
    public function bodegas() {
        $db = $this->model->db;
        try {
            $sql = "SELECT id_bodega, codigo_bodega, nombre FROM bodegas ORDER BY nombre ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("❌ Error obtenerBodegas(): " . $e->getMessage());
            return [];
        }
    }

    public function subbodegas($idBodega) {
        $db = $this->model->db;
        try {
            $sql = "SELECT id_subbodega, codigo_subbodega, nombre_subbodega
                    FROM subbodegas
                    WHERE id_bodega = ?
                    ORDER BY nombre_subbodega ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$idBodega]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("❌ Error obtenerSubBodegas(): " . $e->getMessage());
            return [];
        }
    }

    // ================= MATERIALES FILTRADOS (MEJORADO) =================
    public function obtenerMaterialesFiltrados($idBodega, $idSubBodega = 0) {
        $db = $this->model->db;

        try {
            // ✅ Si hay subbodega seleccionada => CONSULTAR SOLO stock_subbodega
            if ((int)$idSubBodega > 0) {
                if ($this->tableExists("stock_subbodega") && $this->tableExists("material_formacion")) {
                    $sql = "SELECT 
                            mf.id_material,
                            mf.nombre,
                            mf.codigo_inventario,
                            mf.descripcion,
                            mf.unidad_medida,
                            mf.clasificacion,
                            mf.estado,
                            COALESCE(SUM(ss.stock_actual), 0) AS stock_actual
                        FROM material_formacion mf
                        INNER JOIN stock_subbodega ss ON ss.id_material = mf.id_material
                        WHERE mf.estado = 'Disponible'
                            AND ss.id_subbodega = ?
                        GROUP BY mf.id_material
                        ORDER BY mf.nombre ASC";

                    $stmt = $db->prepare($sql);
                    $stmt->execute([$idSubBodega]);
                    return $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                return [];
            }

            // ✅ Caso solo bodega => stock_bodega
            if ((int)$idBodega > 0 && $this->tableExists("stock_bodega") && $this->tableExists("material_formacion")) {
                $sql = "SELECT 
                        mf.id_material,
                        mf.nombre,
                        mf.codigo_inventario,
                        mf.descripcion,
                        mf.unidad_medida,
                        mf.clasificacion,
                        mf.estado,
                        COALESCE(SUM(sb.stock_actual), 0) AS stock_actual
                    FROM material_formacion mf
                    INNER JOIN stock_bodega sb ON sb.id_material = mf.id_material
                    WHERE mf.estado = 'Disponible'
                        AND sb.id_bodega = ?
                    GROUP BY mf.id_material
                    ORDER BY mf.nombre ASC";

                $stmt = $db->prepare($sql);
                $stmt->execute([$idBodega]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // ✅ fallback sin romper el front
            return $this->materiales(0, 0);

        } catch (Exception $e) {
            error_log("❌ Error obtenerMaterialesFiltrados(): " . $e->getMessage());
            return $this->materiales(0, 0);
        }
    }

    // ================= HELPER: Verificar si tabla existe =================
    private function tableExists($tableName) {
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
}

// ================= ROUTER =================
$controller = new SolicitudMaterialController($conn);
$accion = $_GET['accion'] ?? null;

ob_clean();

function sendJSON($data) {
    header("Content-Type: application/json");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($accion) {
    case "crear":
        $data = json_decode(file_get_contents("php://input"), true);
        sendJSON($controller->crear($data));
        break;

    case "entregar":
        $data = json_decode(file_get_contents("php://input"), true);
        sendJSON($controller->entregar($data));
        break;

    case "responder":
        $data = json_decode(file_get_contents("php://input"), true);
        sendJSON($controller->responder($data));
        break;

    case "listar":
        sendJSON($controller->listar());
        break;

    case "obtener":
        sendJSON($controller->obtener($_GET['id']));
        break;

    case "obtenerCompleta":
        sendJSON($controller->obtenerCompleta($_GET['id']));
        break;

     case "programasPorUsuario":
        sendJSON($controller->programasPorUsuario($_GET['usuario']));
        break;

    case "programas":
        sendJSON($controller->programas());
        break;

    case "raes":
        sendJSON($controller->raes($_GET['programa'] ?? 0));
        break;

    case "fichas":
        sendJSON($controller->fichas($_GET['programa'] ?? 0));
        break;

    case "materiales":
        $bodegaId = isset($_GET['bodega']) ? (int) $_GET['bodega'] : 0;
        $subId    = isset($_GET['subbodega']) ? (int) $_GET['subbodega'] : 0;

        if ($bodegaId > 0) {
            sendJSON($controller->obtenerMaterialesFiltrados($bodegaId, $subId));
        } else {
            sendJSON($controller->materiales(0, 0));
        }
        break;

    case "bodegas":
        sendJSON($controller->bodegas());
        break;

    case "subbodegas":
        $bodegaId = isset($_GET['bodega']) ? (int) $_GET['bodega'] : 0;
        sendJSON($controller->subbodegas($bodegaId));
        break;

    case "actividades":
        $fichaId = isset($_GET['ficha']) ? (int) $_GET['ficha'] : 0;
        $raeId   = isset($_GET['rae']) ? (int) $_GET['rae'] : 0;
        sendJSON($controller->obtenerActividades($fichaId, $raeId));
        break;

    default:
        sendJSON(["success" => false, "error" => "Acción no válida"]);
}

ob_end_flush();
exit;