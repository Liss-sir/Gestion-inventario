<?php

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/evidencia.php";

class EvidenciaController {

    public $model;
    private $conn; // 👈 AÑADIR

    public function __construct(PDO $conn) {
        $this->conn = $conn;       // 👈 AÑADIR
        $this->model = new EvidenciaModel($conn);
    }

    /* GET - List evidences */
    public function index() {
        $filters = [];
        // Read basic GET filters
        if (isset($_GET['id_programa']) && $_GET['id_programa'] !== '') {
            $filters['id_programa'] = (int)$_GET['id_programa'];
        }
        if (isset($_GET['id_ficha']) && $_GET['id_ficha'] !== '') {
            $filters['id_ficha'] = (int)$_GET['id_ficha'];
        }

        // If the logged user is Instructor, restrict results to their programs/fichas
        session_start();
        $cargo = $_SESSION['usuario_cargo'] ?? $_SESSION['cargo'] ?? null;
        if ($cargo && strtolower($cargo) === 'instructor') {
            // Expect session arrays: usuario_programas and usuario_fichas
            $sessionPrograms = $_SESSION['usuario_programas'] ?? [];
            $sessionFichas = $_SESSION['usuario_fichas'] ?? [];

            // Normalize to arrays of IDs
            $progIds = [];
            if (is_array($sessionPrograms)) {
                foreach ($sessionPrograms as $p) {
                    if (is_array($p) && isset($p['id_programa'])) $progIds[] = (int)$p['id_programa'];
                    elseif (is_object($p) && isset($p->id_programa)) $progIds[] = (int)$p->id_programa;
                    elseif (is_scalar($p)) $progIds[] = (int)$p;
                }
            }

            $fichaIds = [];
            if (is_array($sessionFichas)) {
                foreach ($sessionFichas as $f) {
                    if (is_array($f) && isset($f['id_ficha'])) $fichaIds[] = (int)$f['id_ficha'];
                    elseif (is_object($f) && isset($f->id_ficha)) $fichaIds[] = (int)$f->id_ficha;
                    elseif (is_scalar($f)) $fichaIds[] = (int)$f;
                }
            }

            // If instructor has no linked programs AND no linked fichas, return empty result
            if (empty($progIds) && empty($fichaIds)) {
                sendJSON([]);
            }

            // If client requested an id_programa, ensure it's within allowed list; otherwise return empty
            if (isset($filters['id_programa']) && !empty($progIds) && !in_array($filters['id_programa'], $progIds)) {
                sendJSON([]);
            }

            if (isset($filters['id_ficha']) && !empty($fichaIds) && !in_array($filters['id_ficha'], $fichaIds)) {
                sendJSON([]);
            }

            // Pass array constraints to model so it only returns instructor-linked rows
            if (!empty($progIds)) $filters['id_programas'] = array_values(array_unique($progIds));
            if (!empty($fichaIds)) $filters['id_fichas'] = array_values(array_unique($fichaIds));
        }

        sendJSON($this->model->listar($filters));
    }

    /* GET - Get evidence by ID */
    public function show($id) {
        $resultado = $this->model->obtenerPorId($id);

        if ($resultado) {
            sendJSON($resultado);
        } else {
            sendJSON(["mensaje" => "Evidencia no encontrada"], 404);
        }
    }

    /* POST - Create evidence */
    public function store() {
    try {
        // 1. Validar datos
        if (
            !isset($_POST["id_usuario"]) ||
            !isset($_POST["id_movimiento_salida"]) ||
            !isset($_POST["descripcion_obra"]) ||
            !isset($_FILES["foto"])
        ) {
            sendJSON(["mensaje" => "Datos incompletos"], 400);
        }

        $id_usuario = $_POST["id_usuario"];
        $id_movimiento_salida = $_POST["id_movimiento_salida"];

        // 2. Subir imagen
        $foto = $this->savePhoto($_FILES["foto"]);

        if (!$foto) {
            sendJSON([
                "mensaje" => "Error al subir la imagen. Verifique formato y tamaño"
            ], 400);
        }

        // 3. Preparar datos
        $data = [
            "id_movimiento_salida" => $id_movimiento_salida,
            "id_usuario" => $id_usuario,
            "foto" => $foto,
            "descripcion_obra" => $_POST["descripcion_obra"]
        ];

        // 4. Guardar evidencia
        if ($this->model->crear($data)) {

            // 5. NOTIFICACIÓN
            $this->notificarEvidencia(
                $id_usuario,              // destinatario
                $id_movimiento_salida     // referencia
            );

            // 6. Respuesta
            sendJSON(["mensaje" => "Evidencia creada correctamente"], 201);
        } else {
            sendJSON(["mensaje" => "Error al crear la evidencia"], 400);
        }

    } catch (Exception $e) {
        sendJSON([
            "mensaje" => "Error del servidor",
            "error" => $e->getMessage()
        ], 500);
    }
}


    private function notificarEvidencia($idUsuario, $idMovimiento) {
        // Obtener la descripción de la obra y el ID de la evidencia recién creada
        $sqlInfo = "
            SELECT
                e.id_evidencia as id_evidencia,
                e.descripcion_obra as descripcion_obra
            FROM evidencias e
            WHERE e.id_movimiento_salida = ?
            ORDER BY e.id_evidencia DESC
            LIMIT 1
        ";

        $stmtInfo = $this->conn->prepare($sqlInfo);
        $stmtInfo->execute([$idMovimiento]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            // Si no encontramos la información, se usan valores por defecto
            $titulo = 'Nueva evidencia cargada';
            $mensaje = 'Se ha subido una nueva evidencia de una obra.';
        } else {
            // Construir mensaje con la información obtenida
            $titulo = 'Nueva evidencia cargada';
            $mensaje = 'Se ha subido la evidencia #' . $info['id_evidencia'] . ' de salida de material.';
        }

        $sql = "
            INSERT INTO notificaciones (
                id_usuario,
                tipo,
                titulo,
                mensaje,
                referencia_tipo,
                referencia_id,
                leida,
                fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $idUsuario,
            'EVIDENCIA_SUBIDA',
            $titulo,
            $mensaje,
            'movimiento',
            $idMovimiento
        ]);
    }






    /* Save uploaded photo */
    
    private function savePhoto($file) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            return null;
        }

        if ($file['size'] > $maxSize) {
            return null;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'evidencia_' . time() . '_' . uniqid() . '.' . $extension;

        $uploadDir = __DIR__ . '/../uploads/evidencias/';
        
        // Crear directorio si no existe
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $path = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return null;
        }

        return $filename; // Solo el nombre del archivo
    }
}

/* =====================================
   Helper function - Send JSON
   ===================================== */
function sendJSON($data, $code = 200)
{
    header("Content-Type: application/json; charset=utf-8");
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* =====================================
   Action handler
   ===================================== */
$controller = new EvidenciaController($conn);

/* Routes */
$method = $_SERVER["REQUEST_METHOD"];
$accion = $_GET["accion"] ?? null;
$id_usuario = $_GET["id_usuario"] ?? null;

if ($method === "GET" && $accion === "salidas_pendientes" && $id_usuario) {
    sendJSON($controller->model->obtenerSalidasPendientesPorUsuario($id_usuario));
} elseif ($method === "GET" && $accion === "salidas_pendientes") {
    sendJSON($controller->model->obtenerSalidasSinEvidencia());
} elseif ($method === "GET" && isset($_GET["id"])) {
    $controller->show($_GET["id"]);
} elseif ($method === "GET") {
    $controller->index();
} elseif ($method === "POST") {
    $controller->store();
}