<?php
// =====================================================
// WINERIES CONTROLLER (JSON) + PERMITS PER ACTION
// =====================================================

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/bodega.php";

// Permission helper (functions)
require_once __DIR__ . "/../utils/permisos_helper.php";

if (!headers_sent()) {
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
}

/* ===============================
   VALIDATE PDO CONNECTION
================================ */
if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Conexión a base de datos no disponible"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===============================
   CONTROLLER
================================ */
class BodegaController
{
    private BodegaModel $model;

    public function __construct(PDO $conn)
    {
        $this->model = new BodegaModel($conn);
    }

    /* ===============================
       JSON RESPONSE
    =============================== */
    private function response($data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ===============================
       LIST Permission
    =============================== */
    public function listar(): void
    {
        requirePermiso("bodegas.listar");

        $this->response([
            "success" => true,
            "data" => $this->model->listar()
        ]);
    }

    /* ===============================
       GET BY CODE Permission
    =============================== */
    public function obtener(): void
    {
        requirePermiso("bodegas.listar");

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
       CREATE Permission
    =============================== */
    public function crear(): void
    {
        requirePermiso("bodegas.crear");

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
       UPDATE Permission
    =============================== */
    public function actualizar(): void
    {
        requirePermiso("bodegas.actualizar");

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
            (int)$data["id_bodega"],
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
       CHANGE STATE Permission
    =============================== */
    public function cambiar_estado(): void
    {
        requirePermiso("bodegas.cambiar_estado");

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

    /* ===============================
       INVENTORY OF WINERY
    =============================== */
    public function inventario_bodega(): void
    {
        requirePermiso("bodegas.listar");

        $idBodega = $_GET['id_bodega'] ?? null;

        if (!$idBodega) {
            $this->response([
                "success" => false,
                "message" => "ID de bodega requerido"
            ], 400);
            return;
        }

        $materiales = $this->model->obtenerInventarioPorBodega((int)$idBodega);

        $this->response([
            "success" => true,
            "data" => $materiales
        ], 200);
    }

    /* ===============================
       INVENTORY OF SUBWINERY
    =============================== */
    public function inventario_subbodega(): void
    {
        requirePermiso("bodegas.listar");

        $idSubbodega = $_GET['id_subbodega'] ?? null;

        if (!$idSubbodega) {
            $this->response([
                "success" => false,
                "message" => "ID de subbodega requerido"
            ], 400);
            return;
        }

        $materiales = $this->model->obtenerInventarioPorSubbodega((int)$idSubbodega);

        $this->response([
            "success" => true,
            "data" => $materiales
        ], 200);
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
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$controller = new BodegaController($conn);

if (!method_exists($controller, $accion)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Acción inválida"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$controller->$accion();
