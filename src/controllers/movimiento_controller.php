<?php

require_once __DIR__ . "/../models/movimiento.php";
require_once __DIR__ . "/../../Config/database.php";

class MovimientoController {

    private $model;

    // Constructor: receives the PDO connection and creates the model instance.
    // Also sets the response header to JSON format.
   
    public function __construct(PDO $conn) {
        $this->model = new MovimientoModel($conn);
        header("Content-Type: application/json; charset=utf-8");
    }

    /* List all movements */
    public function listar() {
        // Get the result object from the model
        $result = $this->model->listarMovimientos();

        // Fetch all rows as associative array
        $data = $result->fetchAll(PDO::FETCH_ASSOC);

        // Return JSON response with all movements
        echo json_encode($data);
    }

    /* Get movement by ID */
    public function obtener() {
        // Get the ID from the query string
        $id = $_GET["id"] ?? null;

        // Validate that ID was provided
        if (!$id) {
            echo json_encode(["error" => "ID requerido"]);
            return;
        }

        // Query the model for the specific movement
        $data = $this->model->obtenerMovimiento($id);

        // Return the movement data or error if not found
        echo json_encode($data ?: ["error" => "Movimiento no encontrado"]);
    }

    /* Create new movement */
    public function crear() {

    $data = json_decode(file_get_contents("php://input"), true);

    // Validaciones básicas
    if (
        !$data ||
        empty($data['id_usuario']) ||
        empty($data['id_bodega']) ||
        empty($data['id_subbodega']) ||
        empty($data['materiales']) ||
        !is_array($data['materiales'])
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Datos incompletos"
        ]);
        return;
    }

    try {
        // 👉 Llamamos al nuevo método
        $codigoMovimiento = $this->model->registrarEntrada($data);

        echo json_encode([
            "success" => true,
            "codigo_movimiento" => $codigoMovimiento
        ]);

    } catch (Exception $e) {

        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
    }
}


    /* Update existing movement */
    public function actualizar() {
        // Decode the JSON input from the request body
        $input = json_decode(file_get_contents("php://input"), true);

        // Validate that the movement ID is present in the input
        if (!isset($input["id_movimiento"])) {
            echo json_encode(["error" => "id_movimiento requerido"]);
            return;
        }

        // Extract the ID from the input data
        $id = $input["id_movimiento"];

        // Update the movement in the database
        $ok = $this->model->actualizarMovimiento($id, $input);

        // Return success or error response
        echo json_encode([
            "success" => $ok,
            "message" => $ok ? "Movimiento actualizado correctamente" : "Error al actualizar movimiento"
        ]);
    }

    /* Delete movement */
    public function eliminar() {
        // Get the movement ID from the query string
        $id = $_GET["id_movimiento"] ?? null;

        // Validate that ID was provided
        if (!$id) {
            echo json_encode(["error" => "id_movimiento requerido"]);
            return;
        }

        // Delete the movement from the database
        $ok = $this->model->eliminarMovimiento($id);

        // Return success or error response
        echo json_encode([
            "success" => $ok,
            "message" => $ok ? "Movimiento eliminado" : "Error al eliminar movimiento"
        ]);
    }
}

/* Router - Routes requests to controller methods */

// Get the action from the query string
$accion = $_GET["accion"] ?? null;

// Validate that an action was specified
if (!$accion) {
    echo json_encode(["error" => "Acción requerida"]);
    exit;
}

// Create controller instance with database connection
$controller = new MovimientoController($conn);

// Route the request to the appropriate controller method
switch ($accion) {

    case "listar":
        $controller->listar();
        break;

    case "obtener":
        $controller->obtener();
        break;

    case "crear":
        $controller->crear();
        break;

    case "actualizar":
        $controller->actualizar();
        break;

    case "eliminar":
        $controller->eliminar();
        break;

    default:
        // Invalid action requested
        echo json_encode(["error" => "Acción no válida"]);
}
