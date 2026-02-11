<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/ficha.php";

class FichaController {

    private $model;

    public function __construct(PDO $conn) {
        $this->model = new FichaModel($conn);
    }

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
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $this->model->crear($input);

        echo json_encode([
            'success' => (bool)$id,
            'id_ficha' => $id
        ]);
    }

    public function actualizar() {
        $input = json_decode(file_get_contents("php://input"), true);
        echo json_encode([
            'success' => $this->model->actualizar($input)
        ]);
    }

    public function cambiarEstado($id, $accion) {
        $map = [
            "activar" => "Activa",
            "finalizar" => "Finalizada",
            "cancelar" => "Cancelada"
        ];
        echo json_encode([
            'success' => $this->model->cambiarEstado($id, $map[$accion])
        ]);
    }

    /* ================= APRENDICES ================= */

    public function obtenerAprendices($id_ficha = null) {
        echo json_encode($this->model->obtenerAprendices($id_ficha));
    }

    public function agregarEstudiantes() {
        $input = json_decode(file_get_contents("php://input"), true);
        echo json_encode($this->model->agregarEstudiantes(
            $input['id_ficha'],
            $input['estudiantes']
        ));
    }

    public function obtenerEstudiantesFicha($id) {
        echo json_encode($this->model->obtenerEstudiantesDeFicha($id));
    }

    /* ================= INSTRUCTORES ================= */

    public function obtenerInstructores() {
        echo json_encode($this->model->obtenerInstructores());
    }

    public function obtenerInstructoresPorPrograma($id_programa) {
        if (!$id_programa) {
            echo json_encode(['error' => 'id_programa requerido']);
            return;
        }
        echo json_encode($this->model->obtenerInstructoresPorPrograma($id_programa));
    }

    public function obtenerInstructoresFicha($id) {
        if (!$id) {
            echo json_encode(['error' => 'id_ficha requerido']);
            return;
        }
        echo json_encode($this->model->obtenerInstructoresDeFicha($id));
    }

    public function asignarInstructores() {
        $input = json_decode(file_get_contents("php://input"), true);

        echo json_encode([
            'success' => $this->model->asignarInstructoresFicha(
                $input['id_ficha'],
                $input['instructores']
            )
        ]);
    }

    /* ================= JEFE DE FICHA ================= */

    public function asignarJefeFicha() {
        $input = json_decode(file_get_contents("php://input"), true);

        echo json_encode([
            'success' => $this->model->asignarJefeFicha(
                $input['id_ficha'],
                $input['id_usuario']
            )
        ]);
    }
}

/* ================= ROUTER ================= */

$accion = $_GET['accion'] ?? null;
$id = $_GET['id_ficha'] ?? null;
$id_programa = $_GET['id_programa'] ?? null;

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
        $controller->cambiarEstado($id, "activar");
        break;

    case "finalizar":
        $controller->cambiarEstado($id, "finalizar");
        break;

    case "cancelar":
        $controller->cambiarEstado($id, "cancelar");
        break;

    case "aprendices":
        $controller->obtenerAprendices($id);
        break;

    case "agregarEstudiantes":
        $controller->agregarEstudiantes();
        break;

    case "estudiantesFicha":
        $controller->obtenerEstudiantesFicha($id);
        break;

    case "instructores":
        $controller->obtenerInstructores();
        break;

    case "instructoresPorPrograma":
        $controller->obtenerInstructoresPorPrograma($id_programa);
        break;

    case "instructoresFicha":
        $controller->obtenerInstructoresFicha($id);
        break;

    case "asignarInstructores":
        $controller->asignarInstructores();
        break;

    case "asignarJefeFicha":
        $controller->asignarJefeFicha();
        break;

    default:
        echo json_encode(["error" => "Acción no válida"]);
}