<?php

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/sub_bodega.php";

header("Content-Type: application/json; charset=utf-8");

/* ===============================
   VALIDAR CONEXIÓN
================================ */
if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error de conexión a la base de datos"
    ]);
    exit;
}

/* ===============================
   CONTROLLER
================================ */
class SubBodegaController {

    private $model;

    public function __construct(PDO $conn) {
        $this->model = new SubBodegaModel($conn);
    }

    private function response($data, int $status = 200) {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ===============================
       LISTAR TODAS
       ?accion=listar
    ================================ */
    public function listar() {
        $this->response([
            "success" => true,
            "data" => $this->model->listar()
        ]);
    }

    /* ===============================
       LISTAR POR BODEGA
       ?accion=por_bodega&id_bodega=1
    ================================ */
    public function por_bodega() {

        $id_bodega = $_GET["id_bodega"] ?? null;

        if (!$id_bodega) {
            $this->response([
                "success" => false,
                "message" => "ID de bodega requerido"
            ], 400);
        }

        // 🔥 filtrado desde DB
        $todas = $this->model->listar();

        $filtradas = array_values(array_filter($todas, function ($s) use ($id_bodega) {
            return (int)$s["id_bodega"] === (int)$id_bodega;
        }));

        $this->response([
            "success" => true,
            "data" => $filtradas
        ]);
    }

    /* ===============================
       OBTENER POR ID
       ?accion=obtener&id=3
    ================================ */
    public function obtener() {

        $id = $_GET["id"] ?? null;

        if (!$id) {
            $this->response(["error" => "ID requerido"], 400);
        }

        $data = $this->model->obtenerPorId((int)$id);

        if (!$data) {
            $this->response(["error" => "Subbodega no encontrada"], 404);
        }

        $this->response($data);
    }

    /* ===============================
       CREAR
       POST JSON
    ================================ */
    public function crear() {

        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            $this->response(["error" => "Datos inválidos"], 400);
        }

        $ok = $this->model->crear($input);

        if ($ok) {
            $this->response(["message" => "Subbodega creada correctamente"]);
        }

        $this->response(["error" => "No se pudo crear"], 500);
    }

    /* ===============================
       ACTUALIZAR
       ?accion=actualizar&id=3
    ================================ */
    public function actualizar() {

        $id = $_GET["id"] ?? null;

        if (!$id) {
            $this->response(["error" => "ID requerido"], 400);
        }

        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            $this->response(["error" => "Datos inválidos"], 400);
        }

        $ok = $this->model->actualizar((int)$id, $input);

        if ($ok) {
            $this->response(["message" => "Subbodega actualizada correctamente"]);
        }

        $this->response(["error" => "No se pudo actualizar"], 500);
    }

    /* ===============================
       CAMBIAR ESTADO
       ?accion=estado&id=3
    ================================ */
    public function estado() {

        $id = $_GET["id"] ?? null;

        if (!$id) {
            $this->response(["error" => "ID requerido"], 400);
        }

        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input || !isset($input["estado"])) {
            $this->response([
                "error" => "Debes enviar { estado: 'Activo' | 'Inactivo' }"
            ], 400);
        }

        if (!in_array($input["estado"], ["Activo", "Inactivo"])) {
            $this->response(["error" => "Estado inválido"], 400);
        }

        $ok = $this->model->cambiarEstado((int)$id, $input["estado"]);

        if ($ok) {
            $this->response([
                "message" => "Estado cambiado a {$input["estado"]}"
            ]);
        }

        $this->response(["error" => "No se pudo cambiar el estado"], 500);
    }
}

/* ===============================
   ROUTER
================================ */
$accion = $_GET["accion"] ?? null;
$controller = new SubBodegaController($conn);

switch ($accion) {

    case "listar":
        $controller->listar();
        break;

    case "por_bodega":
        $controller->por_bodega();
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

    case "estado":
        $controller->estado();
        break;

    default:
        http_response_code(400);
        echo json_encode(["error" => "Ruta no válida"]);
        break;
}
