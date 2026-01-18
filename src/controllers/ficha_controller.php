<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/ficha.php";

class FichaController {

    private $model;

    public function __construct(PDO $conn) {
        $this->model = new FichaModel($conn);
    }

    /* =========================
       FICHAS
    ========================== */

    public function listar() {
        echo json_encode($this->model->listar());
    }

    public function obtener($id) {
        if (!$id) {
            echo json_encode(['error' => 'id_ficha requerido']);
            return;
        }
        echo json_encode($this->model->obtener($id));
    }

    public function crear() {
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode([
            'success' => (bool)$this->model->crear($data)
        ]);
    }

    public function actualizar() {
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode([
            'success' => $this->model->actualizar($data)
        ]);
    }

    public function cambiarEstado($id, $accion) {
        $map = [
            'activar'   => 'Activa',
            'finalizar' => 'Finalizada',
            'cancelar'  => 'Cancelada'
        ];

        echo json_encode([
            'success' => $this->model->cambiarEstado($id, $map[$accion])
        ]);
    }

    /* =========================
       INSTRUCTORES
    ========================== */

    public function obtenerInstructores() {
        echo json_encode($this->model->obtenerInstructores());
    }

    public function asignarInstructores() {
        $data = json_decode(file_get_contents("php://input"), true);

        echo json_encode([
            'success' => $this->model->asignarInstructores(
                $data['id_ficha'],
                $data['instructores']
            )
        ]);
    }

    public function obtenerInstructoresFicha($id) {
        echo json_encode($this->model->obtenerInstructoresFicha($id));
    }

    public function asignarJefeFicha() {
        $data = json_decode(file_get_contents("php://input"), true);

        echo json_encode([
            'success' => $this->model->asignarJefeFicha(
                $data['id_ficha'],
                $data['id_usuario']
            )
        ]);
    }
}

/* =========================
   ROUTER
========================= */

$accion = $_GET['accion'] ?? null;
$id = $_GET['id_ficha'] ?? null;

$controller = new FichaController($conn);

switch ($accion) {

    case "listar":
        $controller->listar();
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
        $controller->cambiarEstado($id, 'activar');
        break;

    case "finalizar":
        $controller->cambiarEstado($id, 'finalizar');
        break;

    case "cancelar":
        $controller->cambiarEstado($id, 'cancelar');
        break;

    case "instructores":
        $controller->obtenerInstructores();
        break;

    case "asignarInstructores":
        $controller->asignarInstructores();
        break;

    case "instructoresFicha":
        $controller->obtenerInstructoresFicha($id);
        break;

    case "asignarJefeFicha":
        $controller->asignarJefeFicha();
        break;

    default:
        echo json_encode(["error" => "Acción no válida"]);
}
