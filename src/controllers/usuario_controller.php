<?php
// ================= DEBUG =================
ini_set('display_errors', 0); // Cambia a 0 en producción
error_reporting(0); // Cambia a 0 en producción

// Para debug temporal, cambia a:
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// ================= SESSION =================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================= HEADERS JSON =================
function enviarJSON($data, $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// ================= INICIALIZACIÓN =================
$accion = $_GET['accion'] ?? null;

if (!$accion) {
    enviarJSON(['error' => 'Debe especificar la acción'], 400);
}

// ================= PHPMailer =================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../libs/PHPMailer/src/SMTP.php';

// ================= DB + MODEL =================
require_once __DIR__ . '/../../Config/database.php';
require_once __DIR__ . '/../models/usuario.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================= BASE_URL AUTO ================= */
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https://'
        : 'http://';

    $host = $_SERVER['HTTP_HOST'];

    // Ruta del script actual
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

    // Cortamos hasta la carpeta raíz del proyecto
    $project = preg_replace('#/src/.*$#', '/', $scriptDir);

    define('BASE_URL', $protocol . $host . $project);
}

if (!isset($conn)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'No se pudo establecer conexión con la base de datos']);
    exit;
}

// ================= HELPERS =================
function validarSoloTexto($s) {
    return preg_match('/^[A-Za-zÁÉÍÓÚÜáéíóúüñ\s]+$/u', (string)$s) === 1;
}

function colapsarEspacios($s) {
    return trim(preg_replace('/\s{2,}/u', ' ', (string)$s));
}

$usuario = new Usuario($conn);

$accion = $_GET['accion'] ?? null;
if (!$accion) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Debe especificar la acción']);
    exit;
}

// =====================================================
// ✅ FIX SIN TOCAR DB:
// Como tu columna tokens_correo.tipo NO acepta 'force_password',
// reutilizamos tipo existente 'reset_password' y distinguimos
// el token "forzado" con prefijo FORCE_
// =====================================================
if (!defined('FORCE_TOKEN_PREFIX')) {
    define('FORCE_TOKEN_PREFIX', 'FORCE_');
}

// ============================
// ✅ ENVIAR CORREO RESET (helper)
// ============================
function enviarCorreoResetPassword($toEmail, $toName, $resetLink) {
    $mail = new PHPMailer(true);

    // 🔐 SOLUCIÓN DE ACENTOS
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';

    // SMTP
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'sigainvetario2025@gmail.com';
    $mail->Password   = 'dwltqzowfouydwgf'; // app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('sigainvetario2025@gmail.com', 'Gestion Inventario');
    $mail->addAddress($toEmail, $toName ?: $toEmail);

    $mail->isHTML(true);
    $mail->Subject = 'Restablecer contraseña - SIGA';

    $mail->Body = "
        <h2>Restablecer contraseña</h2>
        <p>Recibimos una solicitud para restablecer tu contraseña.</p>
        <p>Haz clic aquí para continuar:</p>
        <p>
          <a href='$resetLink'
             style='background:#007832;color:#fff;padding:12px 18px;text-decoration:none;border-radius:8px;display:inline-block;'>
            RESTABLECER CONTRASEÑA
          </a>
        </p>
        <p style='color:#666;font-size:12px;'>Si no solicitaste esto, ignora este correo.</p>
        <hr>
    ";

    $mail->AltBody = "Restablecer contraseña: $resetLink";

    $mail->send();
    return true;
}

// ============================
// ✅ FUNCIÓN AUXILIAR PARA MAPEAR CAMPOS
// ============================
function mapearCampo($campoFormulario) {
    $mapeo = [
        'nombre' => 'nombre_completo',
        'correo' => 'correo',
        'telefono' => 'telefono',
        'direccion' => 'direccion',
        'tipo_documento' => 'tipo_documento',
        'numero_documento' => 'numero_documento'
    ];
    
    return $mapeo[$campoFormulario] ?? null;
}

switch ($accion) {
    // =====================================================
    // ✅ LISTAR USUARIOS
    // GET: ?accion=listar
    // =====================================================
    // Dentro del switch en usuario_controller.php
     case 'contar_notificaciones':
        header('Content-Type: application/json');
        try {
            // Asegúrate de tener la conexión $conn disponible
            $id_usuario = $_SESSION['usuario_id'];
            
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM notificaciones WHERE id_usuario = ? AND leida = 0");
            $stmt->execute([$id_usuario]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'no_leidas' => (int)$resultado['total'],
                'total' => (int)$resultado['total']
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    break;
    
case 'contador':
case 'contar_notificaciones':
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['usuario_id'])) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    $idUsuario = $_SESSION['usuario_id'];
    $esCoordinador = ($_SESSION['usuario_cargo'] ?? '') === 'Coordinador';

    try {
        // Si es coordinador, contamos notificaciones globales de stock y cambios
        if ($esCoordinador) {
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) AS no_leidas
                FROM notificaciones
            ");
            $stmt->execute();
        } else {
            // Usuario normal solo sus notificaciones
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) AS no_leidas
                FROM notificaciones
                WHERE id_usuario = ?
            ");
            $stmt->execute([$idUsuario]);
        }
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'   => true,
            'no_leidas' => (int)($row['no_leidas'] ?? 0),
            'total'     => (int)($row['total'] ?? 0)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
    break;

    case 'listar':
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($usuario->listar());
        break;

    // =====================================================
    // ✅ ELIMINAR NOTIFICACIÓN
    // =====================================================
   // En tu archivo usuario_controller.php, agrega este case al switch:

case 'eliminar_notificacion':
    try {
        $notificacion_id = $_POST['notificacion_id'] ?? 0;
        $usuario_id = $_SESSION['usuario_id'] ?? 0;
        
        if ($notificacion_id <= 0 || $usuario_id <= 0) {
            throw new Exception('Datos inválidos');
        }
        
        // Verificar que la notificación pertenece al usuario
        $stmt = $conn->prepare("SELECT id_notificacion FROM notificaciones WHERE id_notificacion = ? AND id_usuario = ?");
        $stmt->execute([$notificacion_id, $usuario_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception('No tienes permiso para eliminar esta notificación');
        }
        
        // Eliminar la notificación
        $stmt = $conn->prepare("DELETE FROM notificaciones WHERE id_notificacion = ?");
        $stmt->execute([$notificacion_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Notificación eliminada correctamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar: ' . $e->getMessage()
        ]);
    }
    break;
    // =====================================================
    // ✅ RESET PASSWORD (VALIDA TOKEN + CAMBIA PASSWORD)
    // POST FORM: ?accion=reset_password
    // =====================================================
    case 'reset_password':
        $token = trim($_POST['token'] ?? '');
        $p1    = (string)($_POST['password'] ?? '');
        $p2    = (string)($_POST['password2'] ?? '');

        if ($token === '') {
            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=token");
            exit;
        }

        if ($p1 === '' || strlen($p1) < 8) {
            header("Location: " . BASE_URL . "src/view/login/reset_password.php?token=" . urlencode($token) . "&err=pass");
            exit;
        }

        if ($p1 !== $p2) {
            header("Location: " . BASE_URL . "src/view/login/reset_password.php?token=" . urlencode($token) . "&err=match");
            exit;
        }

        try {
            // Buscar token válido
            $q = $conn->prepare("
                SELECT id_token, id_usuario, fecha_expiracion, usado
                FROM tokens_correo
                WHERE token = :t AND tipo = 'reset_password'
                LIMIT 1
            ");
            $q->execute([':t' => $token]);
            $row = $q->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=invalid");
                exit;
            }

            if ((int)$row['usado'] === 1) {
                header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=used");
                exit;
            }

            if (strtotime($row['fecha_expiracion']) < time()) {
                // Marcar token como usado
                $conn->prepare("UPDATE tokens_correo SET usado = 1 WHERE id_token = :id")
                     ->execute([':id' => (int)$row['id_token']]);

                header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=expired");
                exit;
            }

            $idUsuario = (int)$row['id_usuario'];
            $idToken   = (int)$row['id_token'];

            // 🔐 Hashear nueva contraseña
            $newHash = password_hash($p1, PASSWORD_DEFAULT);

            // Actualizar contraseña
            $conn->prepare("
                UPDATE usuarios
                SET password = :ph
                WHERE id_usuario = :uid
                LIMIT 1
            ")->execute([
                ':ph'  => $newHash,
                ':uid' => $idUsuario
            ]);

            // Marcar token como usado
            $conn->prepare("
                UPDATE tokens_correo
                SET usado = 1
                WHERE id_token = :id
                LIMIT 1
            ")->execute([':id' => $idToken]);

            // Redirigir al login con éxito
            header("Location: " . BASE_URL . "src/view/login/login.php?reset=ok");
            exit;

        } catch (Exception $e) {
            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=server");
            exit;
        }
        break;

    // =====================================================
    // ✅ SOLICITAR RESET PASSWORD (GUARDA TOKEN + ENVÍA CORREO)
    // POST FORM: ?accion=request_reset_password
    // =====================================================
    case 'request_reset_password':
        $correo = trim($_POST['correo'] ?? '');

        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=correo");
            exit;
        }

        try {
            // Buscar usuario por correo
            $stmt = $conn->prepare("SELECT id_usuario, nombre_completo, correo FROM usuarios WHERE correo = :c LIMIT 1");
            $stmt->execute([':c' => $correo]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);

            // Siempre redirige con OK aunque no exista (no revelar)
            if (!$u) {
                header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?ok=1");
                exit;
            }

            $idUsuario = (int)$u['id_usuario'];
            $nombre    = $u['nombre_completo'] ?? $correo;

            // Invalidar tokens anteriores sin usar
            $conn->prepare("
                UPDATE tokens_correo
                SET usado = 1
                WHERE id_usuario = :uid AND tipo = 'reset_password' AND usado = 0
            ")->execute([':uid' => $idUsuario]);

            // Token + expiración
            $token = bin2hex(random_bytes(32));
            $fechaExp = (new DateTime('now'))->modify('+30 minutes')->format('Y-m-d H:i:s');

            // Guardar token
            $ins = $conn->prepare("
                INSERT INTO tokens_correo (id_usuario, token, tipo, fecha_expiracion, usado)
                VALUES (:uid, :t, 'reset_password', :exp, 0)
            ");
            $ins->execute([
                ':uid' => $idUsuario,
                ':t'   => $token,
                ':exp' => $fechaExp
            ]);

            // Link
            $resetLink = BASE_URL . "src/view/login/reset_password.php?token=" . urlencode($token);

            // Enviar correo real
            enviarCorreoResetPassword($correo, $nombre, $resetLink);

            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?ok=1");
            exit;

        } catch (Exception $e) {
            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=send");
            exit;
        }
        break;

    // =====================================================
    // ✅ OBTENER USUARIO POR ID
    // GET: ?accion=obtener&id_usuario=1
    // =====================================================
    case 'obtener':
        header('Content-Type: application/json; charset=utf-8');

        $id_usuario = $_GET['id_usuario'] ?? null;

        if (!$id_usuario) {
            echo json_encode(['error' => 'Debe enviar el parámetro id_usuario']);
            exit;
        }

        $res = $usuario->obtenerPorId($id_usuario);
        echo json_encode($res ? $res : ['error' => 'Usuario no encontrado']);
        break;

    // =====================================================
    // ✅ CREAR USUARIO + TOKEN + CORREO ACTIVACIÓN + CREDENCIALES
    // POST JSON: ?accion=crear
    // =====================================================
    case 'crear':
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents("php://input"), true);

        $nombre           = $data['nombre_completo']   ?? null;
        $tipo_documento   = $data['tipo_documento']    ?? null;
        $numero_documento = $data['numero_documento']  ?? null;
        $telefono         = $data['telefono']          ?? null;
        $cargo            = $data['cargo']             ?? null;
        $correo           = $data['correo']            ?? null;
        $direccion        = $data['direccion']         ?? null;
        $password         = $data['password']          ?? null;
        $id_programa      = $data['id_programa']       ?? null;

        if (!$nombre || !$correo || !$password) {
            echo json_encode(['error' => 'Datos incompletos']);
            exit;
        }

        // Validación nombre
        $nombre = colapsarEspacios($nombre);
        if ($nombre === '' || !validarSoloTexto($nombre)) {
            echo json_encode(['error' => 'El nombre solo puede contener letras y espacios']);
            exit;
        }

        // Validar cargo
        $cargosValidos = ['Coordinador','Subcoordinador','Instructor','Pasante','Aprendiz'];
        if ($cargo && !in_array($cargo, $cargosValidos, true)) {
            echo json_encode(['error' => 'Cargo no válido']);
            exit;
        }

        // Regla: solo Instructor puede llevar id_programa
        if ($cargo !== 'Instructor') {
            $id_programa = null;
        } else {
            if ($id_programa === null || $id_programa === '' || (int)$id_programa <= 0) {
                echo json_encode(['error' => 'Debe seleccionar un programa para el Instructor.']);
                exit;
            }
        }

        try {
            // Duplicados
            if ($numero_documento && $usuario->obtenerPorDocumento($numero_documento)) {
                echo json_encode(['error' => 'El número de documento ya está registrado']);
                exit;
            }
            if ($usuario->obtenerPorCorreo($correo)) {
                echo json_encode(['error' => 'El correo ya está registrado']);
                exit;
            }

            $token = bin2hex(random_bytes(32));

            // ✅ Crear usuario
            $lastId = $usuario->crear(
                $nombre,
                $tipo_documento,
                $numero_documento,
                $telefono,
                $cargo,
                $correo,
                $direccion,
                $password,
                $token,
                $id_programa
            );

            if (!$lastId) {
                echo json_encode(['error' => 'Error al crear usuario']);
                exit;
            }

            // ✅ Guardar token
            try {
                $usuario->crearTokenVerificacion($lastId, $token);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'No se pudo guardar el token en tokens_correo',
                    'detalle' => $e->getMessage(),
                    'id_usuario' => $lastId
                ]);
                exit;
            }

            // ✅ Enviar correo
            try {
                $mail = new PHPMailer(true);

                // ✅ Acentos / encoding en este correo también
                $mail->CharSet  = 'UTF-8';
                $mail->Encoding = 'base64';

                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'sigainvetario2025@gmail.com';
                $mail->Password   = 'dwltqzowfouydwgf';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('sigainvetario2025@gmail.com', 'Gestion Inventario');
                $mail->addAddress($correo, $nombre);

                $link = BASE_URL . "src/controllers/usuario_controller.php?accion=activar&token=$token";

                $mail->isHTML(true);
                $mail->Subject = 'Activación de cuenta - Sistema de Inventario';

                $mail->Body = "
                    <h2>Hola $nombre</h2>
                    <p>Tu cuenta en el <strong>Sistema de Gestión de Inventario</strong> ha sido creada correctamente.</p>
                    <p><strong>📌 Tus credenciales de acceso son:</strong></p>
                    <ul>
                        <li><strong>Usuario (correo):</strong> $correo</li>
                        <li><strong>Contraseña:</strong> $password</li>
                    </ul>
                    <p style='color:#666;font-size:12px;'>
                        Por seguridad, te recomendamos cambiar tu contraseña después de iniciar sesión.
                    </p>
                    <hr>
                    <p>Para activar tu cuenta, haz clic en el siguiente botón:</p>
                    <p>
                        <a href='$link'
                           style='background-color:#4CAF50;
                                  color:#ffffff;
                                  padding:12px 24px;
                                  text-decoration:none;
                                  border-radius:5px;
                                  display:inline-block;'>
                           ACTIVAR MI CUENTA
                        </a>
                    </p>
                ";

                $mail->AltBody =
"Hola $nombre,

Tu cuenta en el Sistema de Gestión de Inventario ha sido creada correctamente.

Credenciales de acceso:
Usuario (correo): $correo
Contraseña: $password

Activa tu cuenta aquí:
$link

Recomendación: cambia tu contraseña después de iniciar sesión.
";

                $mail->send();

                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Usuario creado. Revisa tu correo para activar la cuenta',
                    'id_usuario' => $lastId
                ]);
                exit;

            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Usuario creado pero error enviando correo',
                    'detalle' => $e->getMessage()
                ]);
                exit;
            }

        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                if (strpos($e->getMessage(), 'numero_documento') !== false) {
                    echo json_encode(['error' => 'El número de documento ya está registrado']);
                } elseif (strpos($e->getMessage(), 'correo') !== false) {
                    echo json_encode(['error' => 'El correo ya está registrado']);
                } else {
                    echo json_encode(['error' => 'Error de duplicado en base de datos']);
                }
            } else {
                echo json_encode(['error' => 'Error en base de datos: ' . $e->getMessage()]);
            }
            exit;
        }
        break;

    // =====================================================
    // ✅ ACTIVAR CUENTA POR TOKEN (SIN AUTO-LOGIN)
    // =====================================================
    case 'activar':
        $token = $_GET['token'] ?? null;

        if (!$token) {
            echo "Token inválido";
            exit;
        }

        // 1) Activar cuenta
        if (!$usuario->activarCuenta($token)) {
            echo "Token inválido o expirado. Contacta al administrador.";
            exit;
        }

        try {
            // Traer usuario desde el token de verificación
            $q = $conn->prepare("
                SELECT u.id_usuario, u.nombre_completo, u.correo, u.cargo
                FROM tokens_correo t
                INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
                WHERE t.token = :t
                ORDER BY t.id_token DESC
                LIMIT 1
            ");
            $q->execute([':t' => $token]);
            $u = $q->fetch(PDO::FETCH_ASSOC);

            // Si por alguna razón no encuentra usuario, igual manda al login
            if (!$u) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_unset();
                    session_destroy();
                }

                header("Location: " . BASE_URL . "src/view/login/login.php?activacion=ok");
                exit;
            }

            // Invalida tokens FORCE_ anteriores sin usar
            $conn->prepare("
                UPDATE tokens_correo
                SET usado = 1
                WHERE id_usuario = :uid
                  AND token LIKE 'FORCE_%'
                  AND usado = 0
            ")->execute([':uid' => (int)$u['id_usuario']]);

            // Crea token FORCE_
            $forceToken = FORCE_TOKEN_PREFIX . bin2hex(random_bytes(32));
            $forceExp   = (new DateTime('now'))->modify('+1 day')->format('Y-m-d H:i:s');

            // Inserta (sin truncar tipo)
            $conn->prepare("
                INSERT INTO tokens_correo (id_usuario, token, tipo, fecha_expiracion, usado)
                VALUES (:uid, :t, 'reset_password', :exp, 0)
            ")->execute([
                ':uid' => (int)$u['id_usuario'],
                ':t'   => $forceToken,
                ':exp' => $forceExp
            ]);

            // ✅ CORRECCIÓN: cortar cualquier sesión previa
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_unset();
                session_destroy();
            }

            // Se redirige al login para que el usuario ingrese credenciales
            header("Location: " . BASE_URL . "src/view/login/login.php?activacion=ok&force=1");
            exit;

        } catch (Exception $e) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_unset();
                session_destroy();
            }

            header("Location: " . BASE_URL . "src/view/login/login.php?activacion=ok");
            exit;
        }
        break;

    // =====================================================
    // ✅ ACTUALIZAR USUARIO (EDITAR)
    // =====================================================
    case 'actualizar':
        header('Content-Type: application/json; charset=utf-8');

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

        $nombre      = $data['nombre_completo']  ?? $usuarioActual['nombre_completo'];
        $tipo_doc    = $data['tipo_documento']   ?? $usuarioActual['tipo_documento'];
        $num_doc     = $data['numero_documento'] ?? $usuarioActual['numero_documento'];
        $telefono    = $data['telefono']         ?? $usuarioActual['telefono'];
        $cargo       = $data['cargo']            ?? $usuarioActual['cargo'];
        $correo      = $data['correo']           ?? $usuarioActual['correo'];
        $direccion   = $data['direccion']        ?? $usuarioActual['direccion'];
        $password    = $data['password']         ?? null;
        $id_programa = $data['id_programa']      ?? ($usuarioActual['id_programa'] ?? null);

        $nombre = colapsarEspacios($nombre);
        if ($nombre === '' || !validarSoloTexto($nombre)) {
            echo json_encode(['error' => 'El nombre solo puede contener letras y espacios']);
            exit;
        }

        $cargosValidos = ['Coordinador','Subcoordinador','Instructor','Pasante','Aprendiz'];
        if (!in_array($cargo, $cargosValidos, true)) {
            echo json_encode(['error' => 'Cargo no válido']);
            exit;
        }

        if ($cargo !== 'Instructor') {
            $id_programa = null;
        } else {
            if ($id_programa === null || $id_programa === '' || (int)$id_programa <= 0) {
                echo json_encode(['error' => 'Debe seleccionar un programa para el Instructor.']);
                exit;
            }
        }

        if ($num_doc !== $usuarioActual['numero_documento']) {
            $existeDoc = $usuario->obtenerPorDocumento($num_doc);
            if ($existeDoc && (int)$existeDoc['id_usuario'] !== (int)$id_usuario) {
                echo json_encode(['error' => 'El número de documento ya está registrado']);
                exit;
            }
        }

        if ($correo !== $usuarioActual['correo'] && $usuario->obtenerPorCorreo($correo)) {
            echo json_encode(['error' => 'El correo ya está registrado']);
            exit;
        }

        $ok = $usuario->actualizar(
            $id_usuario,
            $nombre,
            $tipo_doc,
            $num_doc,
            $telefono,
            $cargo,
            $correo,
            $password,
            $direccion,
            $id_programa
        );

        echo json_encode(
            $ok ? ['success' => true, 'mensaje' => 'Usuario actualizado correctamente']
                : ['success' => false, 'error' => 'No se pudo actualizar el usuario']
        );
        exit;

    // =====================================================
    // ✅ CAMBIAR ESTADO
    // =====================================================
    case 'cambiar_estado':
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents("php://input"), true);

        $id_usuario = $data['id_usuario'] ?? $_POST['id_usuario'] ?? $_GET['id_usuario'] ?? null;
        $estado     = $data['estado']     ?? $_POST['estado']     ?? $_GET['estado']     ?? null;

        if ($id_usuario === null || $estado === null) {
            echo json_encode(['error' => 'Debe enviar id_usuario y estado (1 o 0)']);
            exit;
        }

        if ((int)$estado !== 1 && (int)$estado !== 0) {
            echo json_encode(['error' => 'El estado debe ser 1 o 0']);
            exit;
        }

        if (!$usuario->obtenerPorId($id_usuario)) {
            echo json_encode(['error' => 'Usuario no encontrado']);
            exit;
        }

        echo json_encode(
            $usuario->cambiarEstado($id_usuario, $estado)
                ? ['success' => true, 'mensaje' => 'Estado actualizado']
                : ['success' => false, 'error' => 'Error al actualizar estado']
        );
        exit;

    // =====================================================
    // ✅ LOGIN (JSON) + DETECTA FORCE_ EN tokens_correo
    // =====================================================
    case 'login':
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents("php://input"), true);

        $correo   = $data['correo'] ?? null;
        $password = $data['password'] ?? null;

        if (!$correo || !$password) {
            echo json_encode(['error' => 'Credenciales incompletas']);
            exit;
        }

        $user = $usuario->login($correo, $password);

        if ($user) {
            $_SESSION['usuario'] = [
                'id'     => $user['id_usuario'],
                'nombre' => $user['nombre_completo'],
                'correo' => $user['correo'],
                'cargo'  => $user['cargo']
            ];

            $_SESSION['usuario_id']     = (int)$user['id_usuario'];
            $_SESSION['usuario_nombre'] = $user['nombre_completo'] ?? '';
            $_SESSION['usuario_correo'] = $user['correo'] ?? '';
            $_SESSION['usuario_cargo']  = $user['cargo'] ?? '';

            // ✅ Detecta el "force" sin usar tipo = 'force_password'
            $stmt = $conn->prepare("
                SELECT id_token
                FROM tokens_correo
                WHERE id_usuario = :uid
                  AND tipo = 'reset_password'
                  AND token LIKE 'FORCE_%'
                  AND usado = 0
                  AND fecha_expiracion >= NOW()
                ORDER BY id_token DESC
                LIMIT 1
            ");
            $stmt->execute([':uid' => (int)$user['id_usuario']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $_SESSION['force_password_change'] = $row ? 1 : 0;

            echo json_encode([
                'success' => true,
                'mensaje' => 'Login exitoso',
                'force_password_change' => (int)$_SESSION['force_password_change'],
                'usuario' => [
                    'id'     => $user['id_usuario'],
                    'nombre' => $user['nombre_completo'],
                    'cargo'  => $user['cargo']
                ]
            ]);
            exit;

        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Credenciales incorrectas o cuenta inactiva'
            ]);
            exit;
        }

    // =====================================================
    // ✅ LOGOUT (REDIRECCIÓN)
    // =====================================================
    case 'logout':
        session_unset();
        session_destroy();
        header("Location: " . BASE_URL . "src/view/login/login.php");
        exit;

    // =====================================================
    // ✅ ACTUALIZAR PERFIL (TELÉFONO, DIRECCIÓN Y FOTO)
    // =====================================================
    case 'actualizar_perfil':
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode(['error' => 'No hay sesión activa. Inicia sesión nuevamente.']);
            exit;
        }

        $id_usuario = (int)$_SESSION['usuario_id'];

        $telefono  = isset($_POST['telefono'])  ? colapsarEspacios($_POST['telefono'])  : '';
        $direccion = isset($_POST['direccion']) ? colapsarEspacios($_POST['direccion']) : '';

        if ($telefono !== '' && !preg_match('/^[0-9+\s\-()]{7,20}$/', $telefono)) {
            echo json_encode(['error' => 'Teléfono no válido.']);
            exit;
        }

        $fotoPathDB = null;

        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['error' => 'Error subiendo la foto.']);
                exit;
            }

            $tmp  = $_FILES['foto_perfil']['tmp_name'];
            $name = $_FILES['foto_perfil']['name'] ?? '';
            $size = (int)($_FILES['foto_perfil']['size'] ?? 0);

            if ($size > 2 * 1024 * 1024) {
                echo json_encode(['error' => 'La foto supera el límite de 2MB.']);
                exit;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];

            if (!in_array($ext, $allowed, true)) {
                echo json_encode(['error' => 'Formato no permitido. Usa JPG, PNG o WEBP.']);
                exit;
            }

            $uploadDirAbs = __DIR__ . '/../uploads/perfiles/';
            if (!is_dir($uploadDirAbs)) {
                @mkdir($uploadDirAbs, 0777, true);
            }

            $fileName = 'perfil_' . $id_usuario . '_' . time() . '.' . $ext;
            $destAbs  = $uploadDirAbs . $fileName;

            if (!move_uploaded_file($tmp, $destAbs)) {
                echo json_encode(['error' => 'No se pudo guardar la foto.']);
                exit;
            }

            $fotoPathDB = 'src/uploads/perfiles/' . $fileName;
        }

        try {
            $sql = "UPDATE usuarios 
                    SET telefono = :t,
                        direccion = :d"
                    . ($fotoPathDB ? ", foto_perfil = :f" : "") .
                   " WHERE id_usuario = :id
                     LIMIT 1";

            $stmt = $conn->prepare($sql);

            $params = [
                ':t'  => $telefono,
                ':d'  => $direccion,
                ':id' => $id_usuario
            ];
            if ($fotoPathDB) $params[':f'] = $fotoPathDB;

            $ok = $stmt->execute($params);

            if (!$ok) {
                echo json_encode(['error' => 'No se pudo actualizar el perfil.']);
                exit;
            }

            $_SESSION['usuario_telefono']  = $telefono;
            $_SESSION['usuario_direccion'] = $direccion;

            if ($fotoPathDB) {
                $_SESSION['usuario_foto'] = $fotoPathDB;
            }

            echo json_encode(['success' => true, 'mensaje' => 'Perfil actualizado correctamente']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['error' => 'Error del servidor al actualizar perfil', 'detalle' => $e->getMessage()]);
            exit;
        }

    // =====================================================
    // ✅ CAMBIAR PASSWORD (DESDE PERFIL + CIERRA FORCE_PASSWORD)
    // =====================================================
    // --- DENTRO DEL SWITCH ($accion) ---

case 'solicitar_cambio_datos_sensibles':
    header('Content-Type: application/json');
    $id_usuario_solicitante = $_SESSION['usuario_id'];
    $datos_json = $_POST['datos_cambiados']; 

    try {
        $stmtCoord = $conn->prepare("SELECT id_usuario FROM usuarios WHERE cargo = 'Coordinador' LIMIT 1");
        $stmtCoord->execute();
        $coord = $stmtCoord->fetch(PDO::FETCH_ASSOC);
        $id_destinatario = $coord['id_usuario'] ?? 1;

        // GUARDAMOS SOLO EL JSON en el mensaje para que la interfaz lo procese limpio
        $stmt = $conn->prepare("INSERT INTO notificaciones 
            (id_usuario, tipo, titulo, mensaje, referencia_tipo, referencia_id, leida, fecha_creacion) 
            VALUES (?, 'CAMBIO_DATOS', 'Solicitud de Cambio de Datos', ?, 'usuario', ?, 0, NOW())");
        
        $stmt->execute([$id_destinatario, $datos_json, $id_usuario_solicitante]);

        echo json_encode(['success' => true, 'message' => 'Solicitud enviada al coordinador']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
break;

case 'aprobar_cambio_datos':
    header('Content-Type: application/json');
    $notif_id = $_POST['notificacion_id'];

    try {
        // 1. Obtener quién pidió el cambio
        $stmt = $conn->prepare("SELECT referencia_id FROM notificaciones WHERE id_notificacion = ?");
        $stmt->execute([$notif_id]);
        $notif = $stmt->fetch();
        $id_aprendiz = $notif['referencia_id'];

        // 2. Marcar notificación del coordinador como leída (finalizada)
        $conn->prepare("UPDATE notificaciones SET leida = 1 WHERE id_notificacion = ?")->execute([$notif_id]);

        // 3. NOTIFICAR AL APRENDIZ
        $stmtMsg = $conn->prepare("INSERT INTO notificaciones (id_usuario, tipo, titulo, mensaje, leida, fecha_creacion) 
            VALUES (?, 'MATERIAL_ENTREGADO', 'Cambio de Datos Aprobado', 'Tu solicitud de cambio de datos ha sido aprobada. El coordinador realizará los cambios manualmente.', 0, NOW())");
        $stmtMsg->execute([$id_aprendiz]);

        echo json_encode(['success' => true, 'message' => 'Solicitud aprobada. Se ha notificado al aprendiz.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
break;

case 'rechazar_cambio_datos':
    header('Content-Type: application/json');
    $notif_id = $_POST['notificacion_id'];
    $motivo = $_POST['motivo'] ?? 'No especificado';

    try {
        $stmt = $conn->prepare("SELECT referencia_id FROM notificaciones WHERE id_notificacion = ?");
        $stmt->execute([$notif_id]);
        $notif = $stmt->fetch();
        $id_aprendiz = $notif['referencia_id'];

        // Marcar como leída
        $conn->prepare("UPDATE notificaciones SET leida = 1 WHERE id_notificacion = ?")->execute([$notif_id]);

        // NOTIFICAR AL APRENDIZ EL RECHAZO
        $msg = "Tu solicitud de cambio de datos ha sido rechazada. Motivo: " . $motivo;
        $stmtMsg = $conn->prepare("INSERT INTO notificaciones (id_usuario, tipo, titulo, mensaje, leida, fecha_creacion) 
            VALUES (?, 'SOLICITUD_RECHAZADA', 'Solicitud Rechazada', ?, 0, NOW())");
        $stmtMsg->execute([$id_aprendiz, $msg]);

        echo json_encode(['success' => true, 'message' => 'Solicitud rechazada y aprendiz notificado.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
break;                                                                                                                          
    // =====================================================
    //  APROBAR/RECHAZAR CAMBIO DE DATOS SENSIBLES
    // =====================================================
    case 'aprobar_cambio_datos':
    case 'rechazar_cambio_datos':
        header('Content-Type: application/json; charset=utf-8');
        
        // Solo coordinadores pueden aprobar/rechazar
        if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_cargo'] ?? '') !== 'Coordinador') {
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }
        
        $id_coordinador = (int)$_SESSION['usuario_id'];
        $notificacion_id = $_POST['notificacion_id'] ?? null;
        $motivo = $_POST['motivo'] ?? null;
        
        if (!$notificacion_id) {
            echo json_encode(['error' => 'ID de notificación requerido']);
            exit;
        }
        
        try {
            // Obtener la notificación original
            require_once __DIR__ . '/../models/notificacion.php';
            $notificacionModel = new NotificacionModel($conn);
            
            // Necesitarías un método para obtener notificación por ID
            $stmt = $conn->prepare("
                SELECT n.*, u.nombre_completo, u.correo 
                FROM notificaciones n
                JOIN usuarios u ON u.id_usuario = n.id_usuario
                WHERE n.id_notificacion = ?
                LIMIT 1
            ");
            $stmt->execute([$notificacion_id]);
            $notificacion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$notificacion) {
                echo json_encode(['error' => 'Notificación no encontrada']);
                exit;
            }
            
            // Extraer datos de la solicitud
            $datosAdicionales = json_decode($notificacion['datos_adicionales'] ?? '{}', true);
            $nombre_completo = $datosAdicionales['nombre_completo'] ?? null;
            $datosCambiados = $datosAdicionales['datos_cambiados'] ?? [];
            
            if ($accion === 'aprobar_cambio_datos') {
                // Aplicar los cambios en la base de datos
                foreach ($datosCambiados as $campo => $info) {
                    $nuevoValor = $info['nuevo'] ?? '';
                    $campoDB = mapearCampo($campo);
                    
                    if ($campoDB && $nuevoValor !== '') {
                        $sql = "UPDATE usuarios SET {$campoDB} = ? WHERE id_usuario = ?";
                        $stmtUpdate = $conn->prepare($sql);
                        $stmtUpdate->execute([$nuevoValor, $nombre_completo]);
                    }
                }
                
                $estado = 'aprobado';
                $mensajeCoordinador = "✅ Has aprobado los cambios solicitados.";
                $mensajeUsuario = "✅ Tu solicitud de cambio de datos ha sido APROBADA por el coordinador.";
            } else {
                $estado = 'rechazado';
                $mensajeCoordinador = "❌ Has rechazado los cambios solicitados.";
                $mensajeUsuario = "❌ Tu solicitud de cambio de datos ha sido RECHAZADA por el coordinador.";
                if ($motivo) {
                    $mensajeUsuario .= "\n📝 Motivo: {$motivo}";
                }
            }
            
            // Actualizar estado en la notificación original
            $datosAdicionales['estado'] = $estado;
            $datosAdicionales['fecha_respuesta'] = date('Y-m-d H:i:s');
            $datosAdicionales['coordinador_responde'] = $_SESSION['usuario_nombre'] ?? '';
            if ($motivo) $datosAdicionales['motivo'] = $motivo;
            
            $stmtUpdateNotif = $conn->prepare("
                UPDATE notificaciones 
                SET datos_adicionales = ? 
                WHERE id_notificacion = ?
            ");
            $stmtUpdateNotif->execute([
                json_encode($datosAdicionales, JSON_UNESCAPED_UNICODE),
                $notificacion_id
            ]);
            
            // Notificar al usuario sobre la decisión
            $tituloUsuario = "Respuesta a tu solicitud de cambio";
            $notificacionModel->crearConfirmacionUsuario(
                $nombre_completo,
                $tituloUsuario,
                $mensajeUsuario
            );
            
            // Marcar notificación original como leída
            $notificacionModel->marcarLeida($notificacion_id, $id_coordinador);
            
            echo json_encode([
                'success' => true,
                'message' => $mensajeCoordinador,
                'estado' => $estado
            ]);
            exit;
            
        } catch (Exception $e) {
            echo json_encode([
                'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ]);
            exit;
        }
            // =====================================================
    // ✅ OBTENER NOTIFICACIONES
    // GET: ?accion=obtener_notificaciones&solo_no_leidas=1
    // =====================================================
   case 'obtener_notificaciones':
    header('Content-Type: application/json; charset=utf-8');
    
    // Iniciar sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar sesión
    if (!isset($_SESSION['usuario_id'])) {
        echo json_encode([
            'error' => 'No hay sesión activa',
            'notificaciones' => [],
            'sin_leer' => 0,
            'total' => 0
        ]);
        exit;
    }
    
    $id_usuario = (int)$_SESSION['usuario_id'];
    $cargo_usuario = $_SESSION['usuario_cargo'] ?? '';
    $solo_no_leidas = isset($_GET['solo_no_leidas']) && $_GET['solo_no_leidas'] == '1';
    
    try {
        require_once __DIR__ . '/../../Config/database.php';
        
        // 1. Determinar qué notificaciones puede ver este usuario
        $where = "WHERE id_usuario = ?";
        $params = [$id_usuario];
        
        // Si NO es coordinador, NO mostrar notificaciones de cambio de datos
        if ($cargo_usuario !== 'Coordinador') {
            $where .= " AND tipo != 'CAMBIO_DATOS'";
        }
        
        // Si solo quiere no leídas
        if ($solo_no_leidas) {
            $where .= " AND leida = 0";
        }
        
        // 2. Contar notificaciones sin leer
        $sql_sin_leer = "SELECT COUNT(*) as total FROM notificaciones $where";
        $stmt_sin_leer = $conn->prepare($sql_sin_leer);
        $stmt_sin_leer->execute($params);
        $result_sin_leer = $stmt_sin_leer->fetch(PDO::FETCH_ASSOC);
        $sin_leer = $result_sin_leer ? (int)$result_sin_leer['total'] : 0;
        
        // 3. Contar total de notificaciones
        $sql_total = "SELECT COUNT(*) as total FROM notificaciones WHERE id_usuario = ?";
        $stmt_total = $conn->prepare($sql_total);
        $stmt_total->execute([$id_usuario]);
        $result_total = $stmt_total->fetch(PDO::FETCH_ASSOC);
        $total = $result_total ? (int)$result_total['total'] : 0;
        
        // 4. Obtener notificaciones
        $sql_notif = "SELECT * FROM notificaciones $where ORDER BY fecha_creacion DESC LIMIT 10";
        $stmt_notif = $conn->prepare($sql_notif);
        $stmt_notif->execute($params);
        $notificaciones = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'sin_leer' => $sin_leer,
            'total' => $total,
            'notificaciones' => $notificaciones,
            'usuario_id' => $id_usuario,
            'cargo' => $cargo_usuario,
            'es_coordinador' => ($cargo_usuario === 'Coordinador')
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'error' => 'Error: ' . $e->getMessage(),
            'notificaciones' => [],
            'sin_leer' => 0,
            'total' => 0
        ]);
    }
    exit;
    break;
    // En el switch de acciones, añade:
case 'contar_notificaciones':
    require_once __DIR__ . '/../utils/notificaciones_sin_db.php';
    
    session_start();
    
    if (!isset($_SESSION['usuario_id'])) {
        echo json_encode(['error' => 'No autorizado']);
        break;
    }
    
    $resumen = NotificacionSesion::obtenerResumen();
    
    echo json_encode([
        'success' => true,
        'total' => $resumen['total'] ?? 0,
        'no_leidas' => $resumen['no_leidas'] ?? 0,
        'criticas' => $resumen['por_color']['danger'] ?? 0,
        'stock_bajo' => $resumen['por_color']['warning'] ?? 0,
        'cambios_datos' => $resumen['por_tipo']['solicitud_cambio_datos'] ?? 0,
        'esCoordinador' => ($_SESSION['usuario_cargo'] ?? '') === 'Coordinador',
        'rol' => $_SESSION['usuario_cargo'] ?? 'Usuario'
    ]);
    break;

    // =====================================================
    // ✅ MARCAR NOTIFICACIÓN COMO LEÍDA
    // GET: ?accion=marcar_notificacion_leida&id=123
    // =====================================================
    // Agrega estos casos al switch en tu controlador:

case 'marcar_notificacion_leida':
    try {
        $notificacion_id = $_POST['notificacion_id'] ?? 0;
        $usuario_id = $_SESSION['usuario_id'] ?? 0;
        
        if ($notificacion_id <= 0) {
            throw new Exception('ID inválido');
        }
        
        $stmt = $conn->prepare("UPDATE notificaciones SET leida = 1 WHERE id_notificacion = ? AND id_usuario = ?");
        $stmt->execute([$notificacion_id, $usuario_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Notificación marcada como leída'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    break;



// Ya deberías tener es                                                                                                              

    // =====================================================
    // ✅ MARCAR TODAS COMO LEÍDAS
    // GET: ?accion=marcar_todas_leidas
    // =====================================================
    case 'marcar_todas_leidas':
    try {
        $usuario_id = $_SESSION['usuario_id'] ?? 0;
        
        $stmt = $conn->prepare("UPDATE notificaciones SET leida = 1 WHERE id_usuario = ?");
        $stmt->execute([$usuario_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Todas las notificaciones marcadas como leídas',
            'actualizadas' => $stmt->rowCount()
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    break;

    // =====================================================
    //  DEFAULT
    // =====================================================
    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Acción no válida']);
        exit;
}