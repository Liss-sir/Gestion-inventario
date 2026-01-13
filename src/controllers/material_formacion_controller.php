<?php
require_once "../../config/database.php";
require_once "../models/material_formacion.php";

class MaterialFormacionController {

    private $model;

    // Save uploaded material photo
    private function savePhoto($file)
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowedTypes)) {
            return null;
        }

        if ($file['size'] > $maxSize) {
            return null;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'material_' . time() . '.' . $extension;

        $path = __DIR__ . '/../uploads/materiales/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return null;
        }

        return $filename; // This is what is stored in DB
    }


    public function __construct($conn)
    {
        $this->model = new MaterialFormacionModel($conn);
    }

    // Get all materials
    public function listar()
    {
        return $this->model->getAll();
    }

    // Get material by ID
    public function obtener($id)
    {
        if (!$id) {
            return null;
        }

        return $this->model->getById($id);
    }

    // Create new material
    public function crear($data)
    {
    // Handle optional photo upload
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $data['foto'] = $this->savePhoto($_FILES['foto']);
        } else {
            $data['foto'] = null;
        }

        $ok = $this->model->create($data);

        if (!$ok) {
            return [
                "status" => "error",
                "message" => "No se pudo crear el material."
            ];
        }

        return [
            "status" => "success",
            "message" => "Material creado correctamente."
        ];
    }


    // Update material
    public function actualizar($id, $data)
    {
        if (!$id || !$data) {
            return [
                "status" => "error",
                "message" => "Datos inválidos para actualizar el material."
            ];
        }

        // AGREGADO: Mantener foto actual si no se envía una nueva
        $actual = $this->model->getById($id);
        $fotoActual = $actual['foto'] ?? null;

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $nuevaFoto = $this->savePhoto($_FILES['foto']);
            if (!$nuevaFoto) {
                return [
                    "status" => "error",
                    "message" => "La imagen no es válida (tipo o tamaño)."
                ];
            }
            $data['foto'] = $nuevaFoto;
        } else {
            $data['foto'] = $fotoActual;
        }

        $ok = $this->model->update($id, $data);

        if (!$ok) {
            return [
                "status" => "error",
                "message" => "El material no fue actualizado. Verifique la clasificación y el código de inventario."
            ];
        }

        return [
            "status" => "success",
            "message" => "El material fue actualizado correctamente."
        ];
    }

    // // Delete material
    // public function eliminar($id)
    // {
    //     if (!$id) {
    //         return [
    //             "status" => "error",
    //             "message" => "ID de material no válido."
    //         ];
    //     }

    //     // Model returns false if material is used
    //     $ok = $this->model->delete($id);

    //     if (!$ok) {
    //         return [
    //             "status" => "error",
    //             "message" => "El material no se puede eliminar porque está siendo usado en otras tablas."
    //         ];
    //     }

    //     return [
    //         "status" => "success",
    //         "message" => "El material fue eliminado correctamente."
    //     ];
    // }

    // Search material
    public function buscar($term)
    {
        return $this->model->search($term);
    }

    // Get total stock
    public function stock($id)
    {
        if (!$id) {
            return [
                "stock_bodega" => 0,
                "stock_subbodega" => 0
            ];
        }

        return $this->model->getStockTotal($id);
    }
}

/* =====================================
   Action handler (switch)
   ===================================== */

$controller = new MaterialFormacionController($conn);

// Read action
$accion = $_GET['accion'] ?? null;

// Send JSON response
function sendJSON($data, $code = 200)
{
    header("Content-Type: application/json");
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($accion) {

    case "listar":
        sendJSON($controller->listar());
        break;

    case "obtener":
        sendJSON($controller->obtener($_GET['id'] ?? null));
        break;

    case "buscar":
        sendJSON($controller->buscar($_GET['term'] ?? ""));
        break;

    case "stock":
        sendJSON($controller->stock($_GET['id'] ?? null));
        break;

    case "crear":
        // Solo FormData: los campos vienen en $_POST y la imagen en $_FILES
        $data = $_POST;
        sendJSON($controller->crear($data));
        break;

    case "actualizar":
        $id = $_GET['id'] ?? null;
        // Solo FormData también para actualizar (campos en $_POST, imagen en $_FILES)
        $data = $_POST;
        sendJSON($controller->actualizar($id, $data));
        break;

    // case "eliminar":
    //     sendJSON($controller->eliminar($_GET['id'] ?? null));
    //     break;

    default:
        sendJSON([
            "status" => "error",
            "message" => "La acción solicitada no es válida."
        ], 400);
        break;
}

?>