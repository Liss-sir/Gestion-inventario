<?php

require_once _DIR_ . "/../models/sub_bodega.php";
require_once _DIR_ . "/../../Config/database.php";

class SubBodegaController {

    private $model;

    public function __construct(PDO $conn) {
        $this->model = new SubBodegaModel($conn);
    }

    private function response($data, int $status = 200) {
        http_response_code($status);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* LIST */
    public function listar() {
        $this->response($this->model->listar());
    }

    /* GET BY ID*/
    public function obtener() {
        $id = $_GET["id"] ?? null;

        if (!$id) {
            $this->response(["error" => "ID requerido"], 400);
        }

        $data = $this->model->obtenerPorId($id);

        if (!$data) {
            $this->response(["error" => "Subbodega no encontrada"], 404);
        }

        $this->response($data);
    }

    /* CREATE */
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

   /* UPDATE */
    public function actualizar() {
        $id = $_GET["id"] ?? null;

        if (!$id) {
            $this->response(["error" => "ID requerido"], 400);
        }

        $id = (int)$id;

        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input || !is_array($input)) {
            $this->response(["error" => "Datos inválidos"], 400);
        }

        // 1) Traer el registro actual (para no exigir campos que no editas)
        $actual = $this->model->obtenerPorId($id);
        if (!$actual) {
            $this->response(["error" => "Subbodega no encontrada"], 404);
        }

        // 2) Merge: lo que llega del front sobrescribe lo existente
        $payload = array_merge($actual, $input);

        // 3) Validación mínima de los campos editables
        if (
            empty($payload["codigo_subbodega"]) ||
            empty($payload["nombre_subbodega"]) ||
            empty($payload["clasificacion_subbodegas"])
        ) {
            $this->response(["error" => "Faltan campos obligatorios"], 400);
        }

        $ok = $this->model->actualizar($id, $payload);

        if ($ok) {
            $this->response(["message" => "Subbodega actualizada correctamente", "success" => true]);
        }

        $this->response(["error" => "No se pudo actualizar"], 500);
    }

    /* CHANGE STATE  */
    public function estado() {

        $id = $_GET["id"] ?? null;

        if (!$id) {
            $this->response(["error" => "ID requerido"], 400);
        }

        // Recibir estado desde el BODY (POR POST)
        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input || !isset($input["estado"])) {
            $this->response(["error" => "Debes enviar { estado: 'Activo' | 'Inactivo' }"], 400);
        }

        $estado = $input["estado"];

        if (!in_array($estado, ["Activo", "Inactivo"])) {
            $this->response(["error" => "Estado inválido"], 400);
        }

        $ok = $this->model->cambiarEstado($id, $estado);

        if ($ok) {
            $this->response(["message" => "Estado cambiado a $estado"]);
        }

        $this->response(["error" => "No se pudo cambiar el estado"], 500);
    }
}

/* ROUTES */

$accion = $_GET["accion"] ?? null;

$controller = new SubBodegaController($conn);

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

    case "estado":
        $controller->estado();
        break;

    default:
        header("Content-Type: application/json");
        echo json_encode(["error" => "Ruta no válida"]);
        break;
}