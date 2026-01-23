<?php
header("Content-Type: application/json; charset=utf-8");
error_reporting(E_ALL);
ini_set("display_errors", 1);

include_once __DIR__ . "/../../Config/database.php";
include_once __DIR__ . "/../models/rol_usuario.php";

if (!isset($conn)) {
    echo json_encode(["error"=>"No hay conexión a la base de datos"]);
    exit;
}

$controller = new UsuarioRolesFuncionales($conn);
$accion = $_GET["accion"] ?? "";

switch ($accion) {

    //Assign role to user
    case "asignar":
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(["error"=>"JSON inválido:  " . json_last_error_msg()]);
            return;
        }

        if (!isset($data["id_usuario"], $data["id_rol"], $data["asignado_por"])) {
            echo json_encode(["error"=>"Debe enviar id_usuario, id_rol y asignado_por"]);
            exit;
        }

        echo json_encode(
            $controller->asignarRol($data["id_usuario"], $data["id_rol"], $data["asignado_por"])
            ? ["mensaje"=>"Rol asignado correctamente"]
            : ["error"=>"No se pudo asignar el rol"]
        );
        break;

    //List users with assigned roles
    case "listar":
        echo json_encode($controller->listarAsignaciones());
        break;

    //Delete role assignment from user
    case "eliminar":
        $id = $_GET["id_usuario_rol"] ?? null;
        if (!$id) {
            echo json_encode(["error"=>"Debe enviar id_usuario_rol"]);
            exit;
        }

        echo json_encode(
            $controller->eliminarAsignacion($id)
            ? ["mensaje"=>"Asignación eliminada"]
            : ["error"=>"No se pudo eliminar"]
        );
        break;

    default:
        echo json_encode(["error"=>"Acción inválida"]);
}
?>
