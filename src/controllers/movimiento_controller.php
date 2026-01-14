<?php

require_once __DIR__ . "/../models/movimiento.php";
require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../../models/notificacion.php";

class MovimientoController {

    private $model;
    private $notificacionModel;

    // Constructor: receives the PDO connection and creates the model instance.
    // Also sets the response header to JSON format.
   
    public function __construct(PDO $conn) {
        $this->model = new MovimientoModel($conn);
        $this->notificacionModel = new NotificacionModel($conn);
        header("Content-Type: application/json; charset=utf-8");
    }

    /* List all movements */
    public function listar() {
        $data = $this->model->listarMovimientos();
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

        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input) {
            echo json_encode(["success" => false, "message" => "Datos inválidos"]);
            return;
        }

        $idMovimiento = $this->model->crearMovimiento($input);
        $tipo = strtolower($input['tipo_movimiento']);

        if (!$idMovimiento) {
            echo json_encode(["success" => false, "message" => "Error al crear movimiento"]);
            return;
        }

        // Notification of movement
        $this->notificacionModel->crear([
            "id_usuario" => $input['id_usuario'],
            "tipo" => "movimiento",
            "titulo" => "Nuevo movimiento registrado",
            "mensaje" => "Se registró una {$tipo} del material ID {$input['id_material']}.",
            "referencia_tipo" => "movimiento",
            "referencia_id" => $idMovimiento
        ]);

        // Notification of low stock
        if ($input['tipo_movimiento'] === 'Salida') {

            $stockActual = $this->model->obtenerStockActual(
                $input['id_material'],
                $input['id_bodega'],
                $input['id_subbodega'] ?? null
            );

            $UMBRAL_BAJO = 10;

            if ($stockActual <= $UMBRAL_BAJO) {
                $this->notificacionModel->crear([
                    "id_usuario" => $input['id_usuario'],
                    "tipo" => "stock",
                    "titulo" => "Stock bajo",
                    "mensaje" => "El material ID {$input['id_material']} tiene solo {$stockActual} unidades.",
                    "referencia_tipo" => "material",
                    "referencia_id" => $input['id_material']
                ]);
            }
        }

        echo json_encode([
            "success" => true,
            "message" => "Movimiento creado correctamente",
            "id_movimiento" => $idMovimiento
        ]);
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

        $this->notificacionModel->crear([
            "id_usuario" => $input['id_usuario'],
            "tipo" => "movimiento",
            "titulo" => "Movimiento actualizado",
            "mensaje" => "Se ha actualizado un movimiento.",
            "referencia_tipo" => "movimiento",
            "referencia_id" => $input['id_movimiento']
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
