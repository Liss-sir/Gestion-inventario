<?php

require_once "../../Config/database.php";
require_once "../../models/NotificacionModel.php";

class NotificacionController {

    private $model;

    public function __construct($conn)
    {
        $this->model = new NotificacionModel($conn);
    }

    /* 
       LIST USER NOTIFICATIONS
        */
    public function listar($id_usuario)
    {
        return [
            "status" => "success",
            "data" => $this->model->getByUsuario($id_usuario)
        ];
    }

    /* 
       COUNT UNREAD
        */
    public function contarNoLeidas($id_usuario)
    {
        return [
            "status" => "success",
            "total" => $this->model->contarNoLeidas($id_usuario)
        ];
    }

    /* 
       MARK ONE AS READ
        */
    public function marcarLeida($data, $id_usuario)
    {
        if (empty($data['id_notificacion'])) {
            return [
                "status" => "error",
                "message" => "ID de notificación requerido."
            ];
        }

        $ok = $this->model->marcarLeida(
            $data['id_notificacion'],
            $id_usuario
        );

        return $ok
            ? ["status" => "success"]
            : ["status" => "error"];
    }

    /* 
       MARK ALL AS READ
        */
    public function marcarTodas($id_usuario)
    {
        $this->model->marcarTodasLeidas($id_usuario);

        return [
            "status" => "success",
            "message" => "Todas las notificaciones fueron marcadas como leídas."
        ];
    }
}

/* 
   ACTION HANDLER
    */

session_start(); // o usa tu middleware JWT

$id_usuario = $_SESSION['id_usuario'] ?? null;

if (!$id_usuario) {
    echo json_encode([
        "status" => "error",
        "message" => "No autorizado."
    ]);
    exit;
}

$controller = new NotificacionController($conn);
$accion = $_GET['accion'] ?? null;

function sendJSON($data)
{
    header("Content-Type: application/json");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($accion) {

    case "listar":
        sendJSON($controller->listar($id_usuario));
        break;

    case "contador":
        sendJSON($controller->contarNoLeidas($id_usuario));
        break;

    case "marcar-leida":
        $data = json_decode(file_get_contents("php://input"), true);
        sendJSON($controller->marcarLeida($data, $id_usuario));
        break;

    case "marcar-todas":
        sendJSON($controller->marcarTodas($id_usuario));
        break;

    default:
        sendJSON([
            "status" => "error",
            "message" => "Acción no válida."
        ]);
}
