<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/ficha.php";

class FichaController {

    private $model;

    public function __construct(PDO $conn) {
        $this->model = new FichaModel($conn);
    }

    /* List fichas */
    public function listar() {
        echo json_encode($this->model->listar());
    }

    /* Get ficha by ID */
    public function obtener($id) {
        if (!$id) {
            echo json_encode(['error' => 'id_ficha requerido']);
            return;
        }

        $data = $this->model->obtener($id);
        echo json_encode($data ?: ['error' => 'Ficha no encontrada']);
    }

    /* Create ficha */
    public function crear() {
        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            echo json_encode(['error' => 'Datos inválidos']);
            return;
        }

        $id = $this->model->crear($input);

        if ($id) {
            echo json_encode([
                'success' => true,
                'message' => "Ficha creada correctamente",
                'id_ficha' => $id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => "Error al crear ficha"
            ]);
        }
    }

    /* Update ficha */
    public function actualizar() {
        $input = json_decode(file_get_contents("php://input"), true);

        if (!isset($input['id_ficha'])) {
            echo json_encode(['error' => 'id_ficha requerido']);
            return;
        }

        $ok = $this->model->actualizar($input);

        echo json_encode([
            'success' => $ok,
            'message' => $ok ? "Ficha actualizada correctamente" : "Error al actualizar ficha"
        ]);
    }

    /* Change ficha state */
    public function cambiarEstado($id, $accion) {
        if (!$id) {
            echo json_encode(['error' => 'id_ficha requerido']);
            return;
        }

        // Convertir acción en estado válido
        $map = [
            "activar"    => "Activa",
            "finalizar"  => "Finalizada",
            "cancelar"   => "Cancelada"
        ];

        if (!isset($map[$accion])) {
            echo json_encode(['error' => 'Acción inválida']);
            return;
        }

        $estado = $map[$accion];
        $ok = $this->model->cambiarEstado($id, $estado);

        echo json_encode([
            'success' => $ok,
            'message' => $ok ? "Ficha actualizada a estado: $estado" : "Error al cambiar estado"
        ]);
    }

    /* Get aprendices (students) */
    public function obtenerAprendices() {
        echo json_encode($this->model->obtenerAprendices());
    }

    /* Add students to ficha */
    public function agregarEstudiantes() {
        $input = json_decode(file_get_contents("php://input"), true);

        if (!isset($input['id_ficha']) || !isset($input['estudiantes'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Datos incompletos'
            ]);
            return;
        }

        $ok = $this->model->agregarEstudiantes(
            $input['id_ficha'],
            $input['estudiantes']
        );

        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Estudiantes agregados correctamente' : 'Error al agregar estudiantes'
        ]);
    }

    /* Get students of a ficha */
    public function obtenerEstudiantesFicha($id) {
        if (!$id) {
            echo json_encode(['error' => 'id_ficha requerido']);
            return;
        }

        echo json_encode($this->model->obtenerEstudiantesDeFicha($id));
    }

}

/* Router */
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
        $controller->cambiarEstado($id, "activar");
        break;

    case "finalizar":
        $controller->cambiarEstado($id, "finalizar");
        break;

    case "cancelar":
        $controller->cambiarEstado($id, "cancelar");
        break;

    case "aprendices":
        $controller->obtenerAprendices();
        break;

    case "agregarEstudiantes":
        $controller->agregarEstudiantes();
        break;

    case "estudiantesFicha":
        $controller->obtenerEstudiantesFicha($id);
        break;

    default:
        echo json_encode(["error" => "Acción no válida"]);
}

