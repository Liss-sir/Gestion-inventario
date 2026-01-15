<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Do not set a global JSON response header here because this controller performs redirects
// in certain flows (account activation, logout, password recovery).
// header('Content-Type: application/json; charset=utf-8');

// ================= PHPMailer =================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../libs/PHPMailer/src/SMTP.php';

// ================= Database Connection + Model =================
require_once __DIR__ . '/../../Config/database.php';
require_once __DIR__ . '/../models/usuario.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================= Automatic BASE_URL Resolution ================= */
if (!defined('BASE_URL')) {

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https://'
        : 'http://';

    $host = $_SERVER['HTTP_HOST'];

    // Current script directory (example: /Gestion-inventario/src/controllers/usuario_controller.php)
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

    // Reduce the path to the project root by removing /src/... from the current location
    $project = preg_replace('#/src/.*$#', '/', $scriptDir);

    define('BASE_URL', $protocol . $host . $project);
}

if (!isset($conn)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'No se pudo establecer conexión con la base de datos']);
    exit;
}

// ================= Utility Functions =================
function validarSoloTexto($s) {
    return preg_match('/^[A-Za-zÁÉÍÓÚÜáéíóúüñ\s]+$/u', (string)$s) === 1;
}

function colapsarEspacios($s) {
    return trim(preg_replace('/\s{2,}/u', ' ', (string)$s));
}

/**
 * Normalizes and evaluates whether the provided "estado" value represents an active user.
 * This avoids relying on a specific data type coming from the database.
 */
function estadoEsActivo($rawEstado): bool {
    $val = strtolower(trim((string)$rawEstado));
    return ($val === 'activo' || $val === '1' || $val === 'true');
}

/**
 * Disables any active database sessions for the given user.
 * Failures are intentionally ignored to avoid interrupting critical flows.
 */
function revocarSesionesUsuario(PDO $conn, int $idUsuario): void {
    try {
        $stmtOff = $conn->prepare("
            UPDATE sesiones_usuarios
            SET activa = 0
            WHERE id_usuario = :id
              AND activa = 1
        ");
        $stmtOff->execute([':id' => $idUsuario]);
    } catch (Exception $e) {
        // Best-effort operation. Consider logging this error if needed.
    }
}

/**
 * Validates the current PHP session against the database token record.
 * This is intended for fetch-based actions such as profile updates and password changes.
 */
function validarSesionActivaEnBD(PDO $conn): bool {
    $uid   = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
    $token = isset($_SESSION['token_sesion']) ? (string)$_SESSION['token_sesion'] : '';

    if ($uid <= 0 || $token === '') return false;

    try {
        $stmt = $conn->prepare("
            SELECT 1
            FROM sesiones_usuarios
            WHERE id_usuario = :id
              AND token_sesion = :t
              AND activa = 1
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $uid,
            ':t'  => $token
        ]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

$usuario = new Usuario($conn);

$accion = $_GET['accion'] ?? null;
if (!$accion) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Debe especificar la acción']);
    exit;
}

// =====================================================
// Compatibility workaround:
// Since tokens_correo.tipo does not accept a dedicated "force_password" value,
// the existing "reset_password" type is reused and forced tokens are identified
// using the FORCE_ prefix.
// =====================================================
if (!defined('FORCE_TOKEN_PREFIX')) {
    define('FORCE_TOKEN_PREFIX', 'FORCE_');
}

// ============================
// Password reset email helper
// ============================
function enviarCorreoResetPassword($toEmail, $toName, $resetLink) {
    $mail = new PHPMailer(true);

    // Ensure proper encoding for non-ASCII characters
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';

    // SMTP configuration
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

switch ($accion) {

    // =====================================================
    // List users
    // GET: ?accion=listar
    // =====================================================
    case 'listar':
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($usuario->listar());
    break;

    // =====================================================
    // Reset password (token validation + password update)
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
            // Retrieve a valid reset token record
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
                // Mark token as used when expired to prevent reuse
                $conn->prepare("UPDATE tokens_correo SET usado = 1 WHERE id_token = :id")
                     ->execute([':id' => (int)$row['id_token']]);

                header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=expired");
                exit;
            }

            $idUsuario = (int)$row['id_usuario'];
            $idToken   = (int)$row['id_token'];

            // Hash the new password before saving
            $newHash = password_hash($p1, PASSWORD_DEFAULT);

            // Update user password
            $conn->prepare("
                UPDATE usuarios
                SET password = :ph
                WHERE id_usuario = :uid
                LIMIT 1
            ")->execute([
                ':ph'  => $newHash,
                ':uid' => $idUsuario
            ]);

            // Mark reset token as used
            $conn->prepare("
                UPDATE tokens_correo
                SET usado = 1
                WHERE id_token = :id
                LIMIT 1
            ")->execute([':id' => $idToken]);

            // Recommended: revoke active sessions after a successful password reset
            revocarSesionesUsuario($conn, $idUsuario);

            // Redirect to login with success status
            header("Location: " . BASE_URL . "src/view/login/login.php?reset=ok");
            exit;

        } catch (Exception $e) {
            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=server");
            exit;
        }

    break;

    // =====================================================
    // Request password reset (create token + send email)
    // POST FORM: ?accion=request_reset_password
    // =====================================================
    case 'request_reset_password':

        $correo = trim($_POST['correo'] ?? '');

        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=correo");
            exit;
        }

        try {
            // Lookup user by email address
            $stmt = $conn->prepare("SELECT id_usuario, nombre_completo, correo FROM usuarios WHERE correo = :c LIMIT 1");
            $stmt->execute([':c' => $correo]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);

            // Always return success to avoid exposing whether an account exists
            if (!$u) {
                header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?ok=1");
                exit;
            }

            $idUsuario = (int)$u['id_usuario'];
            $nombre    = $u['nombre_completo'] ?? $correo;

            // Invalidate any unused reset tokens previously issued
            $conn->prepare("
                UPDATE tokens_correo
                SET usado = 1
                WHERE id_usuario = :uid AND tipo = 'reset_password' AND usado = 0
            ")->execute([':uid' => $idUsuario]);

            // Create token and set expiration window
            $token = bin2hex(random_bytes(32));
            $fechaExp = (new DateTime('now'))->modify('+30 minutes')->format('Y-m-d H:i:s');

            // Persist token
            $ins = $conn->prepare("
                INSERT INTO tokens_correo (id_usuario, token, tipo, fecha_expiracion, usado)
                VALUES (:uid, :t, 'reset_password', :exp, 0)
            ");
            $ins->execute([
                ':uid' => $idUsuario,
                ':t'   => $token,
                ':exp' => $fechaExp
            ]);

            // Build password reset link
            $resetLink = BASE_URL . "src/view/login/reset_password.php?token=" . urlencode($token);

            // Send reset email
            enviarCorreoResetPassword($correo, $nombre, $resetLink);

            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?ok=1");
            exit;

        } catch (Exception $e) {
            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=send");
            exit;
        }

    break;

    // =====================================================
    // Fetch user by ID
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
    // Create user + verification token + activation email
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

        // Normalize and validate full name content
        $nombre = colapsarEspacios($nombre);
        if ($nombre === '' || !validarSoloTexto($nombre)) {
            echo json_encode(['error' => 'El nombre solo puede contener letras y espacios']);
            exit;
        }

        // Validate allowed role values
        $cargosValidos = ['Coordinador','Subcoordinador','Instructor','Pasante','Aprendiz'];
        if ($cargo && !in_array($cargo, $cargosValidos, true)) {
            echo json_encode(['error' => 'Cargo no válido']);
            exit;
        }

        // Business rule: only "Instructor" is allowed to have a training program assigned
        if ($cargo !== 'Instructor') {
            $id_programa = null;
        } else {
            if ($id_programa === null || $id_programa === '' || (int)$id_programa <= 0) {
                echo json_encode(['error' => 'Debe seleccionar un programa para el Instructor.']);
                exit;
            }
        }

        try {
            // Check duplicates before inserting
            if ($numero_documento && $usuario->obtenerPorDocumento($numero_documento)) {
                echo json_encode(['error' => 'El número de documento ya está registrado']);
                exit;
            }
            if ($usuario->obtenerPorCorreo($correo)) {
                echo json_encode(['error' => 'El correo ya está registrado']);
                exit;
            }

            $token = bin2hex(random_bytes(32));

            // Create user record
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

            // Persist verification token
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

            // Send activation email with credentials and verification link
            try {
                $mail = new PHPMailer(true);

                // Configure message encoding for special characters
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
    // Account activation by token (no automatic login)
    // =====================================================
    case 'activar':
        $token = $_GET['token'] ?? null;

        if (!$token) {
            echo "Token inválido";
            exit;
        }

        // Activate account using the verification token
        if (!$usuario->activarCuenta($token)) {
            echo "Token inválido o expirado. Contacta al administrador.";
            exit;
        }

        try {
            // Retrieve user associated with the latest token record
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

            // If no user is found, still redirect to login with activation flag
            if (!$u) {

                // Clear any existing PHP session to prevent unexpected redirects
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_unset();
                    session_destroy();
                }

                header("Location: " . BASE_URL . "src/view/login/login.php?activacion=ok");
                exit;
            }

            // Invalidate previous unused FORCE_ tokens for this user
            $conn->prepare("
                UPDATE tokens_correo
                SET usado = 1
                WHERE id_usuario = :uid
                  AND token LIKE 'FORCE_%'
                  AND usado = 0
            ")->execute([':uid' => (int)$u['id_usuario']]);

            // Create a new FORCE_ token and expiration window
            $forceToken = FORCE_TOKEN_PREFIX . bin2hex(random_bytes(32));
            $forceExp   = (new DateTime('now'))->modify('+1 day')->format('Y-m-d H:i:s');

            // Insert using the supported "reset_password" type
            $conn->prepare("
                INSERT INTO tokens_correo (id_usuario, token, tipo, fecha_expiracion, usado)
                VALUES (:uid, :t, 'reset_password', :exp, 0)
            ")->execute([
                ':uid' => (int)$u['id_usuario'],
                ':t'   => $forceToken,
                ':exp' => $forceExp
            ]);

            // Clear session state to avoid user being treated as already logged in
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_unset();
                session_destroy();
            }

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
    // Update user data
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
    break;

    // =====================================================
    // Update user status
    // If the user is disabled, active sessions are revoked in sesiones_usuarios.
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

        $uExist = $usuario->obtenerPorId($id_usuario);
        if (!$uExist) {
            echo json_encode(['error' => 'Usuario no encontrado']);
            exit;
        }

        $ok = $usuario->cambiarEstado($id_usuario, $estado);

        // When disabling a user, revoke active sessions to enforce the new status immediately
        if ($ok && (int)$estado === 0) {
            revocarSesionesUsuario($conn, (int)$id_usuario);
        }

        echo json_encode(
            $ok
                ? ['success' => true, 'mensaje' => 'Estado actualizado']
                : ['success' => false, 'error' => 'Error al actualizar estado']
        );
        exit;
    break;

    // =====================================================
    // Session validity endpoint
    // GET: ?accion=check_session
    // =====================================================
    case 'check_session':
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode([
                'active' => 0,
                'reason' => 'no_session',
                'message' => 'No hay sesión activa.'
            ]);
            exit;
        }

        // Validate active token_sesion entry in the database
        if (!validarSesionActivaEnBD($conn)) {
            echo json_encode([
                'active' => 0,
                'reason' => 'revoked',
                'message' => 'Tu sesión fue cerrada o revocada.'
            ]);
            exit;
        }

        // Validate that the user is still active in the system
        try {
            $stmt = $conn->prepare("
                SELECT estado
                FROM usuarios
                WHERE id_usuario = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => (int)$_SESSION['usuario_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || !estadoEsActivo($row['estado'] ?? null)) {

                // Ensure session is revoked in the database
                revocarSesionesUsuario($conn, (int)$_SESSION['usuario_id']);

                // Terminate PHP session
                session_unset();
                session_destroy();

                echo json_encode([
                    'active' => 0,
                    'reason' => 'disabled',
                    'message' => 'Tu cuenta ha sido deshabilitada por el administrador.'
                ]);
                exit;
            }

            echo json_encode([
                'active' => 1,
                'reason' => 'ok',
                'message' => 'Sesión válida.'
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode([
                'active' => 0,
                'reason' => 'server',
                'message' => 'Error validando estado de la cuenta.'
            ]);
            exit;
        }
    break;

    // =====================================================
    // Login endpoint (JSON), including FORCE_ token detection
    // Blocks access for disabled accounts without altering database schema.
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

            // Reject login if the account is not active
            $estadoOk = estadoEsActivo($user['estado'] ?? null);
            if (!$estadoOk) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Cuenta inactiva. Contacta al administrador.'
                ]);
                exit;
            }

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

            // Detect forced password change requirement using the FORCE_ token prefix
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
    break;

    // =====================================================
    // Logout flow (redirect-based)
    // If a session token exists, it is marked inactive in the database.
    // =====================================================
    case 'logout':

        // Mark current token session as inactive (best effort)
        try {
            $uid   = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
            $token = isset($_SESSION['token_sesion']) ? (string)$_SESSION['token_sesion'] : '';

            if ($uid > 0 && $token !== '') {
                $stmt = $conn->prepare("
                    UPDATE sesiones_usuarios
                    SET activa = 0
                    WHERE id_usuario = :id
                      AND token_sesion = :t
                      AND activa = 1
                ");
                $stmt->execute([
                    ':id' => $uid,
                    ':t'  => $token
                ]);
            }
        } catch (Exception $e) {
            // Ignore failures to prevent breaking the logout action.
        }

        session_unset();
        session_destroy();

        header("Location: " . BASE_URL . "src/view/login/login.php");
        exit;
    break;

    // =====================================================
    // Profile update (phone, address, and profile image)
    // Validates the database session token to prevent updates from revoked sessions.
    // =====================================================
    case 'actualizar_perfil':
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode(['error' => 'No hay sesión activa. Inicia sesión nuevamente.']);
            exit;
        }

        // Block updates if the session has been revoked by an administrator
        if (!validarSesionActivaEnBD($conn)) {
            echo json_encode(['error' => 'Sesión expirada o revocada. Inicia sesión nuevamente.']);
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
    break;

    // =====================================================
    // Password change from profile (includes closing FORCE_ requirement)
    // Validates database session token to prevent updates from revoked sessions.
    // =====================================================
    case 'cambiar_password':
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode(['error' => 'No hay sesión activa. Inicia sesión nuevamente.']);
            exit;
        }

        // Block changes if the session has been revoked by an administrator
        if (!validarSesionActivaEnBD($conn)) {
            echo json_encode(['error' => 'Sesión expirada o revocada. Inicia sesión nuevamente.']);
            exit;
        }

        $id_usuario = (int)$_SESSION['usuario_id'];

        $actual     = (string)($_POST['password_actual'] ?? '');
        $nueva      = (string)($_POST['password_nueva'] ?? '');
        $confirmar  = (string)($_POST['password_confirmar'] ?? '');

        if ($actual === '' || $nueva === '' || $confirmar === '') {
            echo json_encode(['error' => 'Complete todos los campos.']);
            exit;
        }

        if (strlen($nueva) < 8) {
            echo json_encode(['error' => 'La nueva contraseña debe tener mínimo 8 caracteres.']);
            exit;
        }

        if ($nueva !== $confirmar) {
            echo json_encode(['error' => 'La confirmación no coincide.']);
            exit;
        }

        try {
            $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = :id LIMIT 1");
            $stmt->execute([':id' => $id_usuario]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo json_encode(['error' => 'Usuario no encontrado.']);
                exit;
            }

            $hashActual = (string)$row['password'];

            if (!password_verify($actual, $hashActual)) {
                echo json_encode(['error' => 'La contraseña actual es incorrecta.']);
                exit;
            }

            if (password_verify($nueva, $hashActual)) {
                echo json_encode(['error' => 'La nueva contraseña no puede ser igual a la actual.']);
                exit;
            }

            $newHash = password_hash($nueva, PASSWORD_DEFAULT);

            $upd = $conn->prepare("UPDATE usuarios SET password = :ph WHERE id_usuario = :id LIMIT 1");
            $ok  = $upd->execute([':ph' => $newHash, ':id' => $id_usuario]);

            if ($ok) {
                // Close any outstanding FORCE_ tokens by marking them as used
                $conn->prepare("
                    UPDATE tokens_correo
                    SET usado = 1
                    WHERE id_usuario = :uid
                      AND token LIKE 'FORCE_%'
                      AND usado = 0
                ")->execute([':uid' => $id_usuario]);

                unset($_SESSION['force_password_change']);

                // Optionally revoke active sessions to enforce a re-login after password change
                // If you do not want this behavior, leave it commented.
                // revocarSesionesUsuario($conn, $id_usuario);

                echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente.']);
                exit;
            }

            echo json_encode(['error' => 'No se pudo actualizar la contraseña.']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['error' => 'Error del servidor al cambiar contraseña', 'detalle' => $e->getMessage()]);
            exit;
        }
    break;

    // =====================================================
    // Default handler for unsupported actions
    // =====================================================
    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Acción no válida']);
        exit;
    break;
}
