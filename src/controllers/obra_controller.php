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
        try {
            $instructores = $this->model->obtenerInstructoresActivos();
            if ($instructores === false) {
                echo json_encode(["error" => "Error al obtener instructores"]);
            } else {
                echo json_encode($instructores);
            }
        } catch (Exception $e) {
            error_log("Error en obtenerInstructores: " . $e->getMessage());
            echo json_encode(["error" => "Error interno del servidor"]);
        }
    }

    public function obtenerRaesPorFicha() {
    // Obtener id_ficha de GET o POST
    $idFicha = $_GET['id_ficha'] ?? ($_POST['id_ficha'] ?? null);
    
    if (!$idFicha || !is_numeric($idFicha)) {
        echo json_encode(["error" => "ID de ficha inválido o no proporcionado"]);
        return;
    }
    
    $raes = $this->model->obtenerRaesPorFicha((int)$idFicha);
    echo json_encode($raes);
}

    public function obtenerAprendicesFicha($idFicha) {
        echo json_encode($this->model->obtenerAprendicesFicha($idFicha));
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
        
        // Validar fecha de inicio no sea futura
        if (!empty($data["fecha_inicio"])) {
            $fechaInicio = new DateTime($data["fecha_inicio"]);
            $hoy = new DateTime();
            $hoy->setTime(0, 0, 0); // Solo fecha sin hora
            
            if ($fechaInicio > $hoy) {
                echo json_encode(["error" => "La fecha de inicio no puede ser futura"]);
                return;
            }
        }
        
        $idActividad = $this->model->crear($data);
        
        if ($idActividad) {
            echo json_encode([
                "success" => true,
                "id_actividad" => $idActividad,
                "message" => "Obra creada exitosamente"
            ]);
        } else {
            echo json_encode(["error" => "Error al crear la obra"]);
        }
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

    public function asignarAprendices() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data["id_actividad"]) || empty($data["aprendices"])) {
            echo json_encode(["error" => "Datos incompletos para asignar aprendices"]);
            return;
        }
        
        $result = $this->model->asignarAprendices($data["id_actividad"], $data["aprendices"]);
        
        if ($result) {
            echo json_encode([
                "success" => true,
                "message" => "Aprendices asignados exitosamente"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "error" => "Error al asignar aprendices"
            ]);
        }
    }
}

/* INSTANCIA CONTROLLER */
$controller = new ObraController($conn);

/* ROUTER */
$input = json_decode(file_get_contents("php://input"), true) ?? [];
$accion = $_POST["accion"] ?? $_GET["accion"] ?? $input["accion"] ?? null;
$id = $_POST["id_actividad"] ?? $_GET["id_actividad"] ?? $input["id_actividad"] ?? null;
$idFicha = $_POST["id_ficha"] ?? $_GET["id_ficha"] ?? $input["id_ficha"] ?? null;

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

    case "obtener_aprendices_ficha":
        // Obtener id_ficha de GET o POST
        $idFicha = $_GET['id_ficha'] ?? ($_POST['id_ficha'] ?? null);
        
        if (!$idFicha || !is_numeric($idFicha)) {
            echo json_encode(["error" => "ID de ficha inválido o no proporcionado"]);
            break;
        }
        
        $controller->obtenerAprendicesFicha((int)$idFicha);
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

    case "asignar_aprendices":
        $controller->asignarAprendices();
        break;

    default:
        echo json_encode(["error" => "Acción no válida"]);
}