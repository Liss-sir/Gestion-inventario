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

    // =========================
    // LISTAR PROGRAMAS
    // =========================
    case 'listar':
        echo json_encode($programa->listar());
        break;

    // =========================
    // OBTENER POR ID
    // =========================
    case 'obtener':
        $id_programa = $_GET['id_programa'] ?? null;
        if (!$id_programa) {
            echo json_encode(['error'=>'Debe enviar id_programa']);
            exit;
        }
        echo json_encode($programa->obtenerPorId($id_programa) ?: ['error'=>'Programa no encontrado']);
        break;

    // =========================
    // OBTENER POR CÓDIGO
    // =========================
    case 'obtener_por_codigo':
        $codigo = $_GET['codigo'] ?? null;
        if (!$codigo) {
            echo json_encode(['error' => 'Debe especificar código del programa']);
            exit;
        }

        try {
            $query = "SELECT id_programa, codigo_programa, nombre_programa
                      FROM programas_formacion
                      WHERE codigo_programa = ?
                      ORDER BY id_programa DESC
                      LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->execute([$codigo]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode($p ?: ['error' => 'Programa no encontrado']);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Error al buscar programa: ' . $e->getMessage()]);
        }
        break;

    // =========================
    // CREAR PROGRAMA (DEVUELVE ID)
    // =========================
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

        // Normalizar nivel
        $nivel = str_replace(['Tecnico', 'Tecnologo'], ['Técnico', 'Tecnólogo'], $nivel);

        if (!in_array($nivel, ['Técnico','Tecnólogo'], true)) {
            echo json_encode(['error'=>'Nivel invalido']);
            exit;
        }

        try {
            $query = "INSERT INTO programas_formacion 
                      (codigo_programa, nombre_programa, nivel_programa, descripcion_programa, duracion_horas, estado) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $success = $stmt->execute([$codigo, $nombre, $nivel, $desc, (int)$horas, $estado]);

            if ($success) {
                echo json_encode([
                    'mensaje' => 'Programa creado correctamente',
                    'id_programa' => $conn->lastInsertId()
                ]);
            } else {
                echo json_encode(['error'=>'No se pudo crear']);
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo json_encode(['error' => 'Ya existe un programa con este código']);
            } else {
                echo json_encode(['error' => 'Error al crear programa: ' . $e->getMessage()]);
            }
        }
        break;

    // =========================
    // ACTUALIZAR
    // =========================
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

        $nivel = str_replace(['Tecnico', 'Tecnologo'], ['Técnico', 'Tecnólogo'], $nivel);

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

    // =========================
    // ELIMINAR
    // =========================
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

    // =========================
    // CAMBIAR ESTADO
    // =========================
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

    // =========================
    // OBTENER INSTRUCTORES
    // =========================
    case 'obtener_instructores':
        try {
            $query = "SELECT id_usuario, nombre_completo, correo, cargo, estado
                      FROM usuarios
                      WHERE cargo = 'Instructor'
                      AND estado = 'activo'
                      ORDER BY nombre_completo ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Error al obtener instructores: ' . $e->getMessage()]);
        }
        break;

    // =========================
    // ASIGNAR INSTRUCTORES A PROGRAMA
    // =========================
    case 'asignar_instructores':
        $data = json_decode(file_get_contents("php://input"), true);

        $id_programa = $data['id_programa'] ?? null;
        $instructores_ids = $data['instructores_ids'] ?? [];

        if (!$id_programa || !is_array($instructores_ids)) {
            echo json_encode(['error' => 'Datos inválidos para asignar instructores']);
            exit;
        }

        try {
            $conn->prepare("DELETE FROM instructores_programas WHERE id_programa = ?")
                 ->execute([$id_programa]);

            $insert = $conn->prepare("INSERT INTO instructores_programas (id_usuario, id_programa) VALUES (?, ?)");

            foreach ($instructores_ids as $id_instructor) {
                $insert->execute([$id_instructor, $id_programa]);
            }

            echo json_encode([
                'mensaje' => 'Instructores asignados correctamente',
                'total_asignados' => count($instructores_ids)
            ]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Error al asignar instructores: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['error'=>'Acción inválida']);
}
