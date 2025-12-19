<?php
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

include_once __DIR__ . '/../../Config/database.php';
include_once __DIR__ . '/../models/usuario.php';

if (!isset($conn)) {
    echo json_encode(['error' => 'No se pudo establecer conexión con la base de datos']);
    exit;
}

function validarSoloTexto($s) {
    return preg_match('/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s]+$/u', $s) === 1;
}

function colapsarEspacios($s) {
    return trim(preg_replace('/\s{2,}/u', ' ', (string)$s));
}

$usuario = new Usuario($conn);

$accion = $_GET['accion'] ?? null;
if (!$accion) {
    echo json_encode(['error' => 'Debe especificar la acción en la URL']);
    exit;
}

switch ($accion) {

    // =============================
    // CRUD NORMAL
    // =============================

    case 'listar':
        echo json_encode($usuario->listar());
    break;

    case 'obtener':
        $id_usuario = $_GET['id_usuario'] ?? null;
        if (!$id_usuario) {
            echo json_encode(['error' => 'Debe enviar id_usuario']);
            exit;
        }
        echo json_encode($usuario->obtenerPorId($id_usuario) ?: ['error' => 'Usuario no encontrado']);
    break;

    case 'crear':
        $data = json_decode(file_get_contents("php://input"), true);

        $u = [
            'nombre_completo'  => $data['nombre_completo']  ?? $_POST['nombre_completo']  ?? null,
            'tipo_documento'   => $data['tipo_documento']   ?? $_POST['tipo_documento']   ?? null,
            'numero_documento' => $data['numero_documento'] ?? $_POST['numero_documento'] ?? null,
            'telefono'        => $data['telefono'] ?? $_POST['telefono'] ?? null,
            'cargo'           => $data['cargo'] ?? $_POST['cargo'] ?? null,
            'correo'          => $data['correo'] ?? $_POST['correo'] ?? null,
            'direccion'       => $data['direccion'] ?? $_POST['direccion'] ?? null,
            'password'        => $data['password'] ?? $_POST['password'] ?? null,
        ];

        $id_programa = $data['id_programa'] ?? $_POST['id_programa'] ?? null;

        if (in_array(null, $u, true)) {
            echo json_encode(['error' => 'Debe enviar todos los campos obligatorios']);
            exit;
        }

        $u['nombre_completo'] = colapsarEspacios($u['nombre_completo']);
        if (!validarSoloTexto($u['nombre_completo'])) {
            echo json_encode(['error' => 'El nombre solo puede contener letras y espacios']);
            exit;
        }

        $cargosValidos = ['Coordinador','subcoordinador','Instructor','Pasante','Aprendiz'];
        if (!in_array($u['cargo'], $cargosValidos, true)) {
            echo json_encode(['error' => 'Cargo no válido']);
            exit;
        }

        if ($usuario->obtenerPorCorreo($u['correo'])) {
            echo json_encode(['error' => 'El correo ya está registrado']);
            exit;
        }

        $usuario->crear(
            $u['nombre_completo'],
            $u['tipo_documento'],
            $u['numero_documento'],
            $u['telefono'],
            $u['cargo'],
            $u['correo'],
            $u['direccion'],
            $u['password'],
            $id_programa
        );

        echo json_encode(['mensaje' => 'Usuario creado correctamente']);
    break;

    case 'actualizar':
        $data = json_decode(file_get_contents("php://input"), true);

        $id_usuario = $data['id_usuario'] ?? $_POST['id_usuario'] ?? $_GET['id_usuario'] ?? null;
        if (!$id_usuario) {
            echo json_encode(['error' => 'Debe enviar id_usuario']);
            exit;
        }

        $usuarioActual = $usuario->obtenerPorId($id_usuario);
        if (!$usuarioActual) {
            echo json_encode(['error' => 'Usuario no encontrado']);
            exit;
        }

        $nombre = colapsarEspacios($data['nombre_completo'] ?? $usuarioActual['nombre_completo']);
        if (!validarSoloTexto($nombre)) {
            echo json_encode(['error' => 'El nombre solo puede contener letras y espacios']);
            exit;
        }

        $cargo = $data['cargo'] ?? $usuarioActual['cargo'];
        $cargosValidos = ['Coordinador','subcoordinador','Instructor','Pasante','Aprendiz'];
        if (!in_array($cargo, $cargosValidos, true)) {
            echo json_encode(['error' => 'Cargo no válido']);
            exit;
        }

        if (
            ($data['correo'] ?? $usuarioActual['correo']) !== $usuarioActual['correo']
            && $usuario->obtenerPorCorreo($data['correo'])
        ) {
            echo json_encode(['error' => 'El correo ya está registrado por otro usuario']);
            exit;
        }

        echo json_encode(
            $usuario->actualizar(
                $id_usuario,
                $nombre,
                $data['tipo_documento']   ?? $usuarioActual['tipo_documento'],
                $data['numero_documento'] ?? $usuarioActual['numero_documento'],
                $data['telefono']         ?? $usuarioActual['telefono'],
                $cargo,
                $data['correo']           ?? $usuarioActual['correo'],
                $data['password']         ?? null,
                $data['direccion']        ?? $usuarioActual['direccion'],
                $data['id_programa']      ?? $usuarioActual['id_programa']
            )
            ? ['mensaje' => 'Usuario actualizado correctamente']
            : ['error' => 'No se pudo actualizar el usuario']
        );
    break;

    case 'cambiar_estado':
        $data = json_decode(file_get_contents("php://input"), true);
        $id_usuario = $data['id_usuario'] ?? $_GET['id_usuario'] ?? null;
        $estado = $data['estado'] ?? $_GET['estado'] ?? null;

        if ($id_usuario === null || $estado === null) {
            echo json_encode(['error' => 'Debe enviar id_usuario y estado']);
            exit;
        }

        echo json_encode(
            $usuario->cambiarEstado($id_usuario, $estado)
                ? ['mensaje' => 'Estado actualizado correctamente']
                : ['error' => 'No se pudo actualizar el estado']
        );
    break;

    // =============================
    // NOTIFICACIONES (IGUAL QUE ANTES)
    // =============================

    case 'solicitar_cambio_datos_sensibles':
        require_once __DIR__ . '/../utils/notificaciones_sin_db.php';
        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode(['error' => 'No hay sesión activa']);
            exit;
        }
        
        $id_usuario = $_SESSION['usuario_id'];
        $datos_cambiados = $_POST['datos_cambiados'] ?? null;
        
        
        $datos = json_decode($datos_cambiados, true);
        if (!$datos || !is_array($datos)) {
            echo json_encode(['error' => 'Formato de datos inválido']);
            exit;
        }
        
        // Obtener nombre del usuario
        $usuarioActual = $usuario->obtenerPorId($id_usuario);
        $nombre_usuario = $usuarioActual['nombre_completo'] ?? "Usuario ID $id_usuario";
        
        // Crear notificación usando el método existente
        NotificacionSesion::agregarCambioDatosSensibles($id_usuario, $id_usuario, $datos, $nombre_usuario);
        
        echo json_encode(['message' => 'Solicitud registrada. Un administrador será notificado.', 'success' => true]);
    break;

    case 'obtener_notificaciones':
        require_once __DIR__ . '/../utils/notificaciones_sin_db.php';
        echo json_encode([
            'notificaciones' => NotificacionSesion::obtenerNotificaciones(),
            'no_leidas' => NotificacionSesion::contarNoLeidas()
        ]);
    break;

    case 'marcar_notificacion_leida':
        require_once __DIR__ . '/../utils/notificaciones_sin_db.php';
        $id = $_POST['notificacion_id'] ?? $_GET['notificacion_id'] ?? null;
        echo json_encode(['success' => NotificacionSesion::marcarComoLeido($id)]);
    break;

    case 'marcar_todas_notificaciones_leidas':
        require_once __DIR__ . '/../utils/notificaciones_sin_db.php';
        NotificacionSesion::marcarTodasComoLeidas();
        echo json_encode(['success' => true]);
    break;

    case 'eliminar_notificacion':
        require_once __DIR__ . '/../utils/notificaciones_sin_db.php';
        $id = $_POST['notificacion_id'] ?? $_GET['notificacion_id'] ?? null;
        echo json_encode(['success' => NotificacionSesion::eliminarNotificacion($id)]);
    break;

    // =============================
    // DEFAULT (ÚNICA CORRECCIÓN REAL)
    // =============================
    default:
        echo json_encode(['error' => 'Acción no válida']);
    break;
}
