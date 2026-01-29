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
        sendJSON($this->model->listar());
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