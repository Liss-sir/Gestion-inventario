<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/evidencia.php";

class EvidenciaController {

    private $model;

    public function __construct(PDO $conn) {
        $this->model = new EvidenciaModel($conn);
    }

    /* GET - List evidences */
    public function index() {
        echo json_encode($this->model->listar());
    }

    /* GET - Get evidence by ID */
    public function show($id) {
        $resultado = $this->model->obtenerPorId($id);

        if ($resultado) {
            echo json_encode($resultado);
        } else {
            http_response_code(404);
            echo json_encode(["mensaje" => "Evidencia no encontrada"]);
        }
    }

    /* POST - Create evidence */
    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (
            isset($data["id_movimiento_salida"]) &&
            isset($data["id_usuario"]) &&
            isset($data["foto"]) &&
            isset($data["descripcion_obra"])
        ) {
            if ($this->model->crear($data)) {
                http_response_code(201);
                echo json_encode(["mensaje" => "Evidencia creada correctamente"]);
            } else {
                http_response_code(400);
                echo json_encode(["mensaje" => "Error al crear la evidencia"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["mensaje" => "Datos incompletos"]);
        }
    }
}

/* Instance */
$database = new Database();
$db = $database->getConnection();
$controller = new EvidenciaController($db);

/* Routes */
$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET" && isset($_GET["id"])) {
    $controller->show($_GET["id"]);
} elseif ($method === "GET") {
    $controller->index();
} elseif ($method === "POST") {
    $controller->store();
}