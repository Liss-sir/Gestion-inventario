<?php

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/bodega.php";

header("Content-Type: application/json; charset=utf-8");

/* ===============================
   VALIDAR CONEXIÓN PDO
================================ */

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Conexión a base de datos no disponible"
    ]);
    exit;
}

/* ===============================
   CONTROLLER
================================ */

class BodegaController {

    private BodegaModel $model;

    public function __construct(PDO $conn) {
        $this->model = new BodegaModel($conn);
    }

    /* ===============================
       RESPUESTA JSON
    =============================== */
    private function response($data, int $code = 200): void {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ===============================
       LISTAR
    =============================== */
    public function listar(): void {
        $this->response([
            "success" => true,
            "data" => $this->model->listar()
        ]);
    }

    /* ===============================
       OBTENER POR CÓDIGO
    =============================== */
    public function obtener(): void {
        $codigo = $_GET["codigo_bodega"] ?? $_GET["codigo"] ?? null;

        if (!$codigo) {
            $this->response([
                "success" => false,
                "message" => "Código de bodega requerido"
            ], 400);
        }

        $bodega = $this->model->obtenerPorCodigo($codigo);

        if (!$bodega) {
            $this->response([
                "success" => false,
                "message" => "Bodega no encontrada"
            ], 404);
        }

        $this->response([
            "success" => true,
            "data" => $bodega
        ]);
    }

    /* ===============================
       CREAR
    =============================== */
    public function crear(): void {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            $this->response([
                "success" => false,
                "message" => "JSON inválido"
            ], 400);
        }

        $required = ["codigo_bodega", "nombre", "ubicacion", "clasificacion_bodega"];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->response([
                    "success" => false,
                    "message" => "Falta el campo: $field"
                ], 400);
            }
        }

        if (!in_array($data["clasificacion_bodega"], ["Insumos", "Equipos"], true)) {
            $this->response([
                "success" => false,
                "message" => "Clasificación inválida"
            ], 400);
        }

        $ok = $this->model->crear(
            $data["codigo_bodega"],
            $data["nombre"],
            $data["ubicacion"],
            $data["clasificacion_bodega"]
        );

        $this->response(
            $ok
                ? ["success" => true, "message" => "Bodega creada correctamente"]
                : ["success" => false, "message" => "No se pudo crear la bodega"],
            $ok ? 200 : 500
        );
    }

    /* ===============================
       ACTUALIZAR
    =============================== */
    public function actualizar(): void {
        $data = json_decode(file_get_contents("php://input"), true);

        $required = ["id_bodega", "codigo_bodega", "nombre", "ubicacion", "clasificacion_bodega"];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->response([
                    "success" => false,
                    "message" => "Datos incompletos"
                ], 400);
            }
        }

        if (!in_array($data["clasificacion_bodega"], ["Insumos", "Equipos"], true)) {
            $this->response([
                "success" => false,
                "message" => "Clasificación inválida"
            ], 400);
        }

        $ok = $this->model->actualizar(
            $data["id_bodega"],
            $data["codigo_bodega"],
            $data["nombre"],
            $data["ubicacion"],
            $data["clasificacion_bodega"]
        );

        $this->response(
            $ok
                ? ["success" => true, "message" => "Bodega actualizada"]
                : ["success" => false, "message" => "No se pudo actualizar"],
            $ok ? 200 : 500
        );
    }

    /* ===============================
       CAMBIAR ESTADO
    =============================== */
    public function cambiar_estado(): void {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data["codigo_bodega"]) || empty($data["estado"])) {
            $this->response([
                "success" => false,
                "message" => "Datos incompletos"
            ], 400);
        }

        if (!in_array($data["estado"], ["Activo", "Inactivo"], true)) {
            $this->response([
                "success" => false,
                "message" => "Estado inválido"
            ], 400);
        }

        $ok = $this->model->cambiarEstado(
            $data["codigo_bodega"],
            $data["estado"]
        );

        $this->response(
            $ok
                ? ["success" => true, "message" => "Estado actualizado"]
                : ["success" => false, "message" => "No se pudo cambiar el estado"],
            $ok ? 200 : 500
        );
    }
}

/* ===============================
   ROUTER
================================ */

$accion = $_GET["accion"] ?? null;

if (!$accion) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Acción requerida"
    ]);
    exit;
}

$controller = new BodegaController($conn);

if (!method_exists($controller, $accion)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Acción inválida"
    ]);
    exit;
}

$controller->$accion();
