<?php
require_once "../Gestion-inventario/Config/database.php";
require_once "../models/solicitudes.php";

class SolicitudMaterialController {

    private $model;

    public function __construct($conn)
    {
        $this->model = new SolicitudMaterialModel($conn);
    }

    // Create request with details
    public function crear($data)
    {
        // Basic validation (business rule)
        if (empty($data['materiales']) || !is_array($data['materiales'])) {
            return [
                "status" => "error",
                "message" => "La solicitud debe contener al menos un material."
            ];
        }

        try {
            $this->model->begin();

            $idSolicitud = $this->model->createSolicitudes($data);
            $this->model->addDetalle($idSolicitud, $data['materiales']);

            $this->model->commit();

            return [
                "status" => "success",
                "message" => "Solicitud creada correctamente.",
                "id_solicitud" => $idSolicitud
            ];

        } catch (Exception $e) {
            $this->model->rollback();

            return [
                "status" => "error",
                "message" => "Error al crear la solicitud."
            ];
        }
    }


    // Approve or reject request
    public function responder($data)
    {
        $ok = $this->model->responderSolicitud(
            $data['id_solicitud'],
            $data['estado'],
            $data['id_usuario_aprobador'],
            $data['observaciones'] ?? null
        );
    
        if (!$ok) {
            return [
                "status" => "error",
                "message" => "No se pudo responder la solicitud. Verifique su estado."
            ];
        }
    
        return [
            "status" => "success",
            "message" => "La solicitud fue actualizada correctamente."
        ];
    }


    // Mark request as delivered
    public function entregar($data)
    {
        $ok = $this->model->marcarEntregada(
            $data['id_solicitud'],
            $data['id_usuario']
        );
    
        if (!$ok) {
            return [
                "status" => "error",
                "message" => "La solicitud no puede marcarse como entregada."
            ];
        }
    
        return [
            "status" => "success",
            "message" => "La solicitud fue marcada como entregada correctamente."
        ];
    }



    public function obtener($id)
    {
        return $this->model->getById($id);
    }

    public function obtenerCompleta($id)
    {
        return $this->model->getSolicitudCompleta($id);
    }

    // List all requests
    public function listar()
    {
        return $this->model->getAll();
    }




}


/* ACTION HANDLER */

$controller = new SolicitudMaterialController($conn);

$accion = $_GET['accion'] ?? null;

function sendJSON($data)
{
    header("Content-Type: application/json");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($accion) {

    case "crear":
        $data = json_decode(file_get_contents("php://input"), true);
        sendJSON($controller->crear($data));
        break;

    case "obtener":
        sendJSON($controller->obtener($_GET['id']));
        break;

    case "responder":
        $data = json_decode(file_get_contents("php://input"), true);
        sendJSON($controller->responder($data));
        break;
    
    case "entregar":
        $data = json_decode(file_get_contents("php://input"), true);
        sendJSON($controller->entregar($data));
        break;
    
    case "obtenerCompleta":
        sendJSON($controller->obtenerCompleta($_GET['id']));
        break;

    case "listar":
        sendJSON($controller->listar());
        break;

    default:
        sendJSON([
            "status" => "error",
            "message" => "Acción no válida."
        ]);
        

}
