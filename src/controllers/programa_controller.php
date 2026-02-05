<?php
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include_once __DIR__ . '/../../Config/database.php';
include_once __DIR__ . '/../models/programa.php';

if (!isset($conn)) {
    echo json_encode(['error' => 'No se pudo establecer conexión con la base de datos']);
    exit;
}

$programa = new Programa($conn);
$accion = $_GET['accion'] ?? '';  

if ($accion === '') {
    echo json_encode(['error'=>'Debe especificar acción']);
    exit;
}

switch ($accion) {

    //List programs
    case 'listar':
        echo json_encode($programa->listar());
        break;

    //Get program by id
    case 'obtener':
        $id_programa = $_GET['id_programa'] ?? null;
        if (!$id_programa) {
            echo json_encode(['error'=>'Debe enviar id_programa']);
            exit;
        }
        echo json_encode($programa->obtenerPorId($id_programa) ?: ['error'=>'Programa no encontrado']);
        break;

    //Create program
    case 'crear':
        $data = json_decode(file_get_contents("php://input"), true);

        $codigo = $data['codigo_programa'] ?? null;
        $nombre = $data['nombre_programa'] ?? null;
        $nivel  = $data['nivel_programa'] ?? null;
        $desc   = $data['descripcion_programa'] ?? null;
        $horas  = $data['duracion_horas'] ?? null; 
        $estado = $data['estado'] ?? null;

        if ($codigo === null || $nombre === null || $nivel === null || $desc === null || $horas === null || $estado === null) {
            echo json_encode(['error'=>'Debe enviar todos los campos obligatorios']);
            exit;
        }

        if (!in_array($nivel, ['Técnico','Tecnólogo','Tecnico','Tecnologo'], true)) {
            echo json_encode(['error'=>'Nivel invalido']);
            exit;
        }

        echo json_encode(
            $programa->crear($codigo, $nombre, $nivel, $desc, (int)$horas, $estado)
            ? ['mensaje'=>'Programa creado correctamente']
            : ['error'=>'No se pudo crear']
        );
        break;

    //Update program
    case 'actualizar':
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $_GET['id_programa'] ?? $data['id_programa'] ?? null;

        if (!$id) {
            echo json_encode(['error'=>'Debe enviar id_programa']);
            exit;
        }

        $codigo = $data['codigo_programa'] ?? null;
        $nombre = $data['nombre_programa'] ?? null;
        $nivel  = $data['nivel_programa'] ?? null;
        $desc   = $data['descripcion_programa'] ?? null;
        $horas  = $data['duracion_horas'] ?? null;
        $estado = $data['estado'] ?? null;

        if (!$codigo || !$nombre || !$nivel || !$desc || !$horas || $estado === null) {
            echo json_encode(['error'=>'Debe enviar todos los campos']);
            exit;
        }

        if (!$programa->obtenerPorId($id)) {
            echo json_encode(['error'=>'Programa no encontrado']);
            exit;
        }

        if (!in_array($nivel, ['Técnico','Tecnólogo'], true)) {
            echo json_encode(['error'=>'Nivel inválido']);
            exit;
        }

        echo json_encode(
            $programa->actualizar($id, $codigo, $nombre, $nivel, $desc, (int)$horas, $estado)
            ? ['mensaje'=>'Programa actualizado correctamente']
            : ['error'=>'No se pudo actualizar']
        );
        break;

    //Delete Program
    case 'eliminar':
        $id_programa = $_GET['id_programa'] ?? null;
        if (!$id_programa) {
            echo json_encode(['error'=>'Debe enviar id_programa']);
            exit;
        }
        echo json_encode(
            $programa->eliminar($id_programa)
            ? ['mensaje'=>'Programa eliminado correctamente']
            : ['error'=>'No se pudo eliminar']
        );
        break;

    //Change program status
    case 'cambiar_estado':
        $data = json_decode(file_get_contents("php://input"), true);
        $id_programa = $data['id_programa'] ?? null;
        $estado = $data['estado'] ?? null;

        if (!$id_programa || $estado === null) {
            echo json_encode(['error'=>'Debe enviar id_programa y estado']);
            exit;
        }

        if (!$programa->obtenerPorId($id_programa)) {
            echo json_encode(['error'=>'Programa no existe']);
            exit;
        }

        echo json_encode(
            $programa->cambiarEstado($id_programa, $estado)
            ? ['mensaje'=>'Estado cambiado correctamente']
            : ['error'=>'No se pudo cambiar']
        );
        break;

    default:
        echo json_encode(['error'=>'Acción inválida']);
}

?>
