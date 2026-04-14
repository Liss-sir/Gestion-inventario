<?php

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/movimiento.php";

header("Content-Type: application/json; charset=utf-8");

/* ===============================
   CONEXIÓN PDO
================================ */

/* database.php crea $conn directamente */
if (!isset($conn) || !($conn instanceof PDO)) {
    echo json_encode([
        "success" => false,
        "message" => "Error de conexión a BD"
    ]);
    exit;
}


/* ===============================
   CONTROLLER
================================ */

class MovimientoController
{

    private MovimientoModel $model;

    public function __construct(PDO $conn)
    {
        $this->model = new MovimientoModel($conn);
    }

    public function listar()
    {
        // ✅ FIX: Obtener parámetros para filtrar por instructor si es necesario
        $idUsuario = (int)($_GET['id_usuario'] ?? 0);
        $esInstructor = $_GET['es_instructor'] ?? false;
        
        // Convertir string 'true'/'false' a boolean
        if (is_string($esInstructor)) {
            $esInstructor = $esInstructor === 'true' || $esInstructor === '1';
        }
        
        echo json_encode([
            "success" => true,
            "data" => $this->model->listarMovimientos($idUsuario > 0 ? $idUsuario : null, $esInstructor)
        ]);
    }

    public function obtener()
    {
        $id = $_GET["id"] ?? null;

        if (!$id) {
            echo json_encode(["success" => false, "message" => "ID requerido"]);
            return;
        }

        $mov = $this->model->obtenerMovimiento((int)$id);

        echo json_encode(
            $mov
                ? ["success" => true, "data" => $mov]
                : ["success" => false, "message" => "No encontrado"]
        );
    }

    public function crear()
    {
        $rawInput = file_get_contents("php://input");
        error_log("[CONTROLLER] JSON recibido: " . $rawInput);

        $data = json_decode($rawInput, true);
        error_log("[CONTROLLER] JSON parseado: " . json_encode($data));

        if (
            !$data ||
            empty($data['id_usuario']) ||
            empty($data['id_bodega']) ||
            empty($data['materiales'])
        ) {
            error_log("[CONTROLLER] Validación falló - Datos incompletos");
            echo json_encode(["success" => false, "message" => "Datos incompletos"]);
            return;
        }

        try {
            // ✅ REGISTRA MOVIMIENTO + ACTUALIZA STOCK AUTOMÁTICAMENTE (EN EL MODEL)
            $codigo = $this->model->registrarEntrada($data);

            echo json_encode([
                "success" => true,
                "codigo_movimiento" => $codigo
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                "success" => false,
                "message" => "Error al registrar movimiento: " . $e->getMessage()
            ]);
        }
    }

    public function eliminar()
    {
        $id = $_GET["id_movimiento"] ?? null;

        if (!$id) {
            echo json_encode(["success" => false, "message" => "ID requerido"]);
            return;
        }

        echo json_encode([
            "success" => $this->model->eliminarMovimiento((int)$id)
        ]);
    }
}

/* ===============================
   ROUTER
================================ */

$accion = $_GET["accion"] ?? null;

$controller = new MovimientoController($conn);

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

    case "eliminar":
        $controller->eliminar();
        break;

    default:
        echo json_encode([
            "success" => false,
            "message" => "Acción inválida"
        ]);
        break;
}
