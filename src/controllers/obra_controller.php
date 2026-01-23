<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/obra.php";

/* VALIDAR CONEXIÓN */
if (!isset($conn)) {
    echo json_encode(["error" => "Conexion no disponible"]);
    exit;
}

class ObraController {

    private $model;

    public function __construct(PDO $conn) {
        $this->model = new ObraModel($conn);
    }

    public function listar() {
        echo json_encode($this->model->listar());
    }

    public function obtenerFichas() {
        echo json_encode($this->model->obtenerFichasActivas());
    }

    public function obtenerRaes() {
        echo json_encode($this->model->obtenerRaesActivos());
    }

    public function obtenerInstructores() {
        echo json_encode($this->model->obtenerInstructoresActivos());
    }

    public function obtener($id) {
        echo json_encode($this->model->obtener($id));
    }

    public function crear() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validar datos requeridos
        if (empty($data["id_ficha"]) || empty($data["id_rae"]) || empty($data["id_instructor"]) || 
            empty($data["nombre_actividad"]) || empty($data["tipo_trabajo"])) {
            echo json_encode(["error" => "Faltan datos requeridos"]);
            return;
        }
        
        echo json_encode([
            "success" => $this->model->crear($data),
            "message" => "Obra creada exitosamente"
        ]);
    }

    public function actualizar() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data["id_actividad"])) {
            echo json_encode(["error" => "ID de actividad requerido"]);
            return;
        }
        
        echo json_encode([
            "success" => $this->model->actualizar($data),
            "message" => "Obra actualizada exitosamente"
        ]);
    }

    public function cambiarEstado($id, $estado) {
        echo json_encode([
            "success" => $this->model->cambiarEstado($id, $estado)
        ]);
    }
}

/* INSTANCIA CONTROLLER */
$controller = new ObraController($conn);

/* ROUTER */
$input = json_decode(file_get_contents("php://input"), true) ?? [];
$accion = $_POST["accion"] ?? $_GET["accion"] ?? $input["accion"] ?? null;
$id = $_POST["id_actividad"] ?? $_GET["id_actividad"] ?? $input["id_actividad"] ?? null;

switch ($accion) {
    case "listar":
        $controller->listar();
        break;

    case "obtener_fichas":
        $controller->obtenerFichas();
        break;

    case "obtener_raes":
        $controller->obtenerRaes();
        break;

    case "obtener_instructores":
        $controller->obtenerInstructores();
        break;

    case "obtener":
        $controller->obtener($id);
        break;

    case "crear":
        $controller->crear();
        break;

    case "actualizar":
        $controller->actualizar();
        break;

    case "activar":
        $controller->cambiarEstado($id, "Activa");
        break;

    case "finalizar":
        $controller->cambiarEstado($id, "Finalizada");
        break;

    default:
        echo json_encode(["error" => "Acción no válida"]);
}