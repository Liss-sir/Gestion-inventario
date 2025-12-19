<?php
require_once __DIR__ . "/../models/bodega.php";
require_once __DIR__ . "/../../Config/database.php";

// All responses will be in JSON format.
header("Content-Type: application/json; charset=utf-8");

/* REST Controller for CRUD of warehouses */
class BodegaController {

    private $model;

    public function __construct(PDO $conn) {
        $this->model = new BodegaModel($conn);
    }

    /* Send JSON response */
    private function jsonResponse($data, int $code = 200) {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /* Read JSON input from POST/PUT */
    private function getJson() {
        return json_decode(file_get_contents("php://input"), true);
    }

    /* LIST ALL BODEGAS */
    public function listar() {
        $this->jsonResponse($this->model->listar());
    }

    /* GET BODEGA BY ID */
    public function obtener() {
        $id = $_GET["id"] ?? $_GET["id_bodega"] ?? null;

        if (!$id) {
            $this->jsonResponse(["error" => "ID no proporcionado"], 400);
            return;
        }

        $bodega = $this->model->obtenerPorId(intval($id));

        $this->jsonResponse(
            $bodega ?: ["error" => "Bodega no encontrada"],
            $bodega ? 200 : 404
        );
    }

    /* CREATE BODEGA */
public function crear() {
    $data = $this->getJson();

    if (!$data) {
        $this->jsonResponse(["error" => "JSON inválido"], 400);
        return;
    }

    // Validación básica de campos obligatorios
    if (
        empty($data['codigo_bodega']) ||
        empty($data['nombre']) ||
        empty($data['ubicacion']) ||
        empty($data['clasificacion_bodega'])
    ) {
        $this->jsonResponse(
            ["error" => "Faltan campos obligatorios"],
            400
        );
        return;
    }

    // Validar ENUM
    if (!in_array($data['clasificacion_bodega'], ['Insumos', 'Equipos'])) {
        $this->jsonResponse(
            ["error" => "Clasificación de bodega inválida"],
            400
        );
        return;
    }

    $ok = $this->model->crear(
        $data['codigo_bodega'],
        $data['nombre'],
        $data['ubicacion'],
        $data['clasificacion_bodega']
    );

    $this->jsonResponse(
        $ok
            ? ["mensaje" => "Bodega creada correctamente"]
            : ["error" => "No se pudo crear la bodega"],
        $ok ? 200 : 500
    );
}


    /* UPDATE BODEGA */
public function actualizar() {

    $data = $this->getJson();

    $id = $_GET["id"]
        ?? $_GET["id_bodega"]
        ?? $data["id_bodega"]
        ?? null;

    if (!$id) {
        $this->jsonResponse(["error" => "ID de bodega faltante"], 400);
        return;
    }

    if (!$data) {
        $this->jsonResponse(["error" => "JSON inválido o vacío"], 400);
        return;
    }

    // CAMPOS OBLIGATORIOS SEGÚN LA BD
    $required = [
        "codigo_bodega",
        "nombre",
        "ubicacion",
        "estado",
        "clasificacion_bodega"
    ];

    foreach ($required as $campo) {
        if (!isset($data[$campo])) {
            $this->jsonResponse(["error" => "Falta el campo: $campo"], 400);
            return;
        }
    }

    // VALIDAR ENUMS
    $estadosValidos = ["Activo", "Inactivo"];
    $clasificacionesValidas = ["Insumos", "Equipos"];

    if (!in_array($data["estado"], $estadosValidos, true)) {
        $this->jsonResponse(["error" => "Estado inválido"], 400);
        return;
    }

    if (!in_array($data["clasificacion_bodega"], $clasificacionesValidas, true)) {
        $this->jsonResponse(["error" => "Clasificación inválida"], 400);
        return;
    }

    $ok = $this->model->actualizar(
        (int)$id,
        $data["codigo_bodega"],
        $data["nombre"],
        $data["ubicacion"],
        $data["estado"],
        $data["clasificacion_bodega"]
    );

    $this->jsonResponse(
        $ok
            ? ["mensaje" => "Bodega actualizada correctamente"]
            : ["error" => "No se pudo actualizar la bodega"],
        $ok ? 200 : 500
    );
}

    /* CHANGE STATE */
    public function cambiar_estado() {
        $data = $this->getJson();

        if (!isset($data["id_bodega"], $data["estado"])) {
            $this->jsonResponse(["error" => "Datos incompletos"], 400);
            return;
        }

        $ok = $this->model->cambiarEstado(
            $data["id_bodega"],
            $data["estado"]
        );

        $this->jsonResponse(
            $ok ? ["mensaje" => "Estado actualizado correctamente"]
                : ["error" => "No se pudo actualizar el estado"],
            $ok ? 200 : 500
        );
    }
}

/* ROUTER */
$accion = $_GET["accion"] ?? null;

if (!$accion) {
    echo json_encode(["error" => "Acción no especificada"]);
    exit;
}

$controller = new BodegaController($conn);

if (!method_exists($controller, $accion)) {
    echo json_encode(["error" => "Acción inválida"]);
    exit;
}

$controller->$accion();
