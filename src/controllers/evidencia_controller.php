<?php

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/evidencia.php";

class EvidenciaController {

    public $model;

    public function __construct(PDO $conn) {
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
            // Validar que venga de FormData (POST)
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

            // Procesar upload de imagen
            $foto = $this->savePhoto($_FILES["foto"]);
            
            if (!$foto) {
                sendJSON(["mensaje" => "Error al subir la imagen. Verifique el formato y tamaño (PNG/JPG, máx 5MB)"], 400);
            }

            // Preparar datos
            $data = [
                "id_movimiento_salida" => $id_movimiento_salida,
                "id_usuario" => $id_usuario,
                "foto" => $foto,
                "descripcion_obra" => $_POST["descripcion_obra"]
            ];

            if ($this->model->crear($data)) {
                sendJSON(["mensaje" => "Evidencia creada correctamente"], 201);
            } else {
                sendJSON(["mensaje" => "Error al crear la evidencia"], 400);
            }
        } catch (Exception $e) {
            sendJSON(["mensaje" => "Error del servidor: " . $e->getMessage()], 500);
        }
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