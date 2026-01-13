<?php
require_once __DIR__ . "/../models/bodega.php";
require_once __DIR__ . "/../../Config/database.php";

header("Content-Type: application/json; charset=utf-8");

class BodegaController {

    private $model;

    public function __construct(PDO $conn) {
        $this->model = new BodegaModel($conn);
    }

    private function jsonResponse($data, int $code = 200) {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function getJson() {
        return json_decode(file_get_contents("php://input"), true);
    }

    /* ============================================================
       LISTAR BODEGAS
    ============================================================ */
    public function listar() {
        $this->jsonResponse($this->model->listar());
    }

    /* ============================================================
       OBTENER BODEGA POR CODIGO
    ============================================================ */
    public function obtener() {
        $codigo = $_GET["codigo_bodega"] ?? $_GET["codigo"] ?? null;

        if (!$codigo) {
            $this->jsonResponse(["error" => "Código de bodega no proporcionado"], 400);
            return;
        }

        $bodega = $this->model->obtenerPorCodigo($codigo);

        $this->jsonResponse(
            $bodega ?: ["error" => "Bodega no encontrada"],
            $bodega ? 200 : 404
        );
    }

    /* ============================================================
       CREAR BODEGA
    ============================================================ */
    public function crear() {
        $data = $this->getJson();

        if (!$data) {
            $this->jsonResponse(["error" => "JSON inválido"], 400);
            return;
        }

        $required = ['codigo_bodega', 'nombre', 'ubicacion', 'clasificacion_bodega'];
        foreach ($required as $campo) {
            if (empty($data[$campo])) {
                $this->jsonResponse(["error" => "Falta el campo: $campo"], 400);
                return;
            }
        }

        if (!in_array($data['clasificacion_bodega'], ['Insumos', 'Equipos'], true)) {
            $this->jsonResponse(["error" => "Clasificación de bodega inválida"], 400);
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

    /* ============================================================
       ACTUALIZAR BODEGA
    ============================================================ */
    public function actualizar() {
        $data = $this->getJson();

        if (
            empty($data["id_bodega"]) ||
            empty($data["codigo_bodega"]) ||
            empty($data["nombre"]) ||
            empty($data["ubicacion"]) ||
            empty($data["clasificacion_bodega"])
        ) {
            $this->jsonResponse(["error" => "Datos incompletos"], 400);
            return;
        }

        if (!in_array($data["clasificacion_bodega"], ['Insumos', 'Equipos'], true)) {
            $this->jsonResponse(["error" => "Clasificación inválida"], 400);
            return;
        }

        $ok = $this->model->actualizar(
            $data["id_bodega"],
            $data["codigo_bodega"],
            $data["nombre"],
            $data["ubicacion"],
            $data["clasificacion_bodega"]
        );

        $this->jsonResponse(
            $ok
                ? ["mensaje" => "Bodega actualizada correctamente"]
                : ["error" => "No se pudo actualizar la bodega"],
            $ok ? 200 : 500
        );
    }

    /* ============================================================
       CAMBIAR ESTADO
    ============================================================ */
    public function cambiar_estado() {
        $data = $this->getJson();

        if (!isset($data["codigo_bodega"], $data["estado"])) {
            $this->jsonResponse(["error" => "Datos incompletos"], 400);
            return;
        }

        if (!in_array($data["estado"], ['Activo', 'Inactivo'], true)) {
            $this->jsonResponse(["error" => "Estado inválido"], 400);
            return;
        }

        $ok = $this->model->cambiarEstado(
            $data["codigo_bodega"],
            $data["estado"]
        );

        $this->jsonResponse(
            $ok
                ? ["mensaje" => "Estado actualizado correctamente"]
                : ["error" => "No se pudo actualizar el estado"],
            $ok ? 200 : 500
        );
    }
}

/* ============================================================
   ROUTER
============================================================ */
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
