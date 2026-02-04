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
    // OBTENER INSTRUCTORES DE UN PROGRAMA
    // =========================
    case 'obtener_instructores_programa':
        $id_programa = $_GET['id_programa'] ?? null;
        if (!$id_programa) {
            echo json_encode(['error' => 'Debe especificar id_programa']);
            exit;
        }

        try {
            $query = "SELECT u.id_usuario, u.nombre_completo, u.correo, u.cargo
                      FROM usuarios u
                      INNER JOIN instructores_programas ip 
                        ON u.id_usuario = ip.id_usuario
                      WHERE ip.id_programa = ?
                      AND u.cargo = 'Instructor'
                      AND u.estado = 'activo'
                      ORDER BY u.nombre_completo ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute([$id_programa]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Error al obtener instructores del programa: ' . $e->getMessage()]);
        }
        break;

    // =========================
    // ASIGNAR INSTRUCTORES A PROGRAMA
    // =========================
    case 'asignar_instructores':
        $data = json_decode(file_get_contents("php://input"), true);

        $id_programa = $data['id_programa'] ?? null;
        $nuevos = $data['instructores_ids'] ?? [];

        if (!$id_programa || !is_array($nuevos)) {
            echo json_encode(['error' => 'Datos inválidos para asignar instructores']);
            exit;
        }

        try {
            // Obtener instructores actuales del programa
            $stmt = $conn->prepare("SELECT id_usuario FROM instructores_programas WHERE id_programa = ?");
            $stmt->execute([$id_programa]);
            $actuales = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Calcular diferencias
            $para_insertar = array_diff($nuevos, $actuales);   // los nuevos
            $para_borrar   = array_diff($actuales, $nuevos);   // los quitados

            // Insertar solo los nuevos
            if (!empty($para_insertar)) {
                $insert = $conn->prepare("INSERT INTO instructores_programas (id_usuario, id_programa) VALUES (?, ?)");
                foreach ($para_insertar as $id_usuario) {
                    $insert->execute([$id_usuario, $id_programa]);
                }
            }

            // Borrar solo los quitados
            if (!empty($para_borrar)) {
                $placeholders = implode(',', array_fill(0, count($para_borrar), '?'));
                $sql = "DELETE FROM instructores_programas 
                        WHERE id_programa = ? 
                        AND id_usuario IN ($placeholders)";
                $stmt = $conn->prepare($sql);
                $stmt->execute(array_merge([$id_programa], $para_borrar));
            }

            echo json_encode([
                'mensaje'   => 'Instructores actualizados correctamente',
                'agregados' => array_values($para_insertar),
                'eliminados'=> array_values($para_borrar)
            ]);

        } catch (PDOException $e) {
            echo json_encode(['error' => 'Error al asignar instructores: ' . $e->getMessage()]);
        }
        break;
    default:
        echo json_encode(['error'=>'Acción inválida']);
}