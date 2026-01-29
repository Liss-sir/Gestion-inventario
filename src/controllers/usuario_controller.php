<?php
/**
 * ============================================================
 * USUARIO CONTROLLER (COMBINADO)
 * ============================================================
 * ✅ Combina:
 * 1) Tu controlador completo (usuarios + tokens_correo + notificaciones)
 * 2) La solución de sesión única con tabla sesiones_usuarios (token_sesion)
 *
 * ✅ Incluye:
 * - Output buffering para que NO se rompa JSON por warnings/espacios
 * - Helpers JSON con no-cache
 * - Login con revocación de sesión anterior + token_sesion en BD
 * - Endpoint check_session
 * - Notificaciones (contar / obtener / marcar leída / eliminar / marcar todas)
 * - Flujo reset_password + request_reset_password + activar cuenta
 * - Actualizar perfil (con validación de token_sesion)
 * - Cambiar password desde perfil (con validación de token_sesion)
 * - Solicitudes de cambio de datos sensibles (aprendiz -> coordinador)
 *
 * ✅ FIX APLICADO (SIN TOCAR TU BASE):
 * - obtener_notificaciones ahora lista notificaciones + datos del usuario destinatario
 * - y si existe referencia_id (o equivalente), también trae datos del solicitante (para CAMBIO_DATOS)
 */

ob_start();

/* ================= DEBUG ================= */
ini_set('display_errors', 0); // Cambia a 0 en producción
error_reporting(0); // Cambia a 0 en producción

// Para debug temporal, cambia a:
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

/* ================= SESSION ================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================= PHPMailer ================= */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException; // ✅ FIX: Alias para no romper catch(Exception)

require_once __DIR__ . '/../../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../libs/PHPMailer/src/SMTP.php';

/* ================= DB + MODEL ================= */
require_once __DIR__ . '/../../Config/database.php';
require_once __DIR__ . '/../models/usuario.php';

/* ================= BASE_URL AUTO ================= */
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https://'
        : 'http://';

    $host = $_SERVER['HTTP_HOST'];

    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $project   = preg_replace('#/src/.*$#', '/', $scriptDir);

    define('BASE_URL', $protocol . $host . $project);
}

/* ================= VALIDAR CONEXIÓN ================= */
if (!isset($conn) || !($conn instanceof PDO)) {
    header('Content-Type: application/json; charset=utf-8');
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    if (ob_get_length()) ob_clean();
    echo json_encode(['error' => 'No se pudo establecer conexión con la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ================= HEADERS JSON ================= */
function enviarJSON($data, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    if (ob_get_length()) ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ================= HELPERS ================= */
function validarSoloTexto($s): bool
{
    return preg_match('/^[A-Za-zÁÉÍÓÚÜáéíóúüñ\s]+$/u', (string)$s) === 1;
}

function colapsarEspacios($s): string
{
    return trim(preg_replace('/\s{2,}/u', ' ', (string)$s));
}

/**
 * Normaliza y evalúa si "estado" representa un usuario activo.
 * Soporta: 'activo', 1, true, etc.
 */
function estadoEsActivo($rawEstado): bool
{
    $val = strtolower(trim((string)$rawEstado));
    return ($val === 'activo' || $val === '1' || $val === 'true');
}

/**
 * Revoca sesiones activas en BD del usuario.
 * Best-effort: si falla no rompe el flujo.
 */
function revocarSesionesUsuario(PDO $conn, int $idUsuario): void
{
    try {
        $stmtOff = $conn->prepare("
            UPDATE sesiones_usuarios
            SET activa = 0
            WHERE id_usuario = :id
              AND activa = 1
        ");
        $stmtOff->execute([':id' => $idUsuario]);
    } catch (\Exception $e) {
        // Ignorar para no romper login/logout
    }
}

/**
 * Crea token_sesion nuevo en sesiones_usuarios.
 * Devuelve token o null si falla (no bloquea login).
 */
function crearTokenSesionBD(PDO $conn, int $idUsuario): ?string
{
    try {
        $token = bin2hex(random_bytes(32));
        $stmt  = $conn->prepare("
            INSERT INTO sesiones_usuarios (id_usuario, token_sesion, activa)
            VALUES (:id, :t, 1)
        ");
        $stmt->execute([
            ':id' => $idUsuario,
            ':t'  => $token
        ]);
        return $token;
    } catch (\Exception $e) {
        return null;
    }
}



/**
 * Valida que la sesión PHP actual siga activa en la BD (token_sesion).
 */
function validarSesionActivaEnBD(PDO $conn): bool
{
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
    } catch (\Exception $e) {
        return false;
    }
}

/* =======================================================
   FIX SIN TOCAR DB:
   tokens_correo.tipo NO acepta 'force_password'
   Reutilizamos 'reset_password' y distinguimos con FORCE_
======================================================= */
if (!defined('FORCE_TOKEN_PREFIX')) {
    define('FORCE_TOKEN_PREFIX', 'FORCE_');
}

/* ============================
   ENVIAR CORREO RESET (helper)
============================ */
function enviarCorreoResetPassword($toEmail, $toName, $resetLink): bool
{
    $mail = new PHPMailer(true);

    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';

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

/* ============================
   MAPEO CAMPOS (cambio datos)
============================ */
function mapearCampo($campoFormulario): ?string
{
    $mapeo = [
        'nombre'            => 'nombre_completo',
        'correo'            => 'correo',
        'telefono'          => 'telefono',
        'direccion'         => 'direccion',
        'tipo_documento'    => 'tipo_documento',
        'numero_documento'  => 'numero_documento'
    ];
    return $mapeo[$campoFormulario] ?? null;
}

/* =====================================================
   HELPERS EXTRA (NOTIFICACIONES: PK Y COLUMNAS)
   ===================================================== */

function obtenerPKNotificaciones(PDO $conn): string {
    try {
        $cols = [];
        $stmt = $conn->query("SHOW COLUMNS FROM notificaciones");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $cols[] = $r['Field'];
        }

        if (in_array('id_notificacion', $cols, true)) return 'id_notificacion';
        if (in_array('id', $cols, true)) return 'id';

        return 'id_notificacion';
    } catch (\Exception $e) {
        return 'id_notificacion';
    }
}

function obtenerColRefNotificaciones(PDO $conn): string {
    try {
        $cols = [];
        $stmt = $conn->query("SHOW COLUMNS FROM notificaciones");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) $cols[] = $r['Field'];

        if (in_array('referencia_id', $cols, true)) return 'referencia_id';
        if (in_array('reference_id', $cols, true)) return 'reference_id';
        if (in_array('ref_id', $cols, true)) return 'ref_id';

        return 'referencia_id';
    } catch (\Exception $e) {
        return 'referencia_id';
    }
}

/* ✅ FIX EXTRA: detectar columna fecha en notificaciones */
function obtenerColFechaNotificaciones(PDO $conn): string {
    try {
        $cols = [];
        $stmt = $conn->query("SHOW COLUMNS FROM notificaciones");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) $cols[] = $r['Field'];

        if (in_array('fecha_creacion', $cols, true)) return 'fecha_creacion';
        if (in_array('created_at', $cols, true)) return 'created_at';

        return ''; // sin fecha
    } catch (\Exception $e) {
        return '';
    }
}

/* ✅ FIX EXTRA: obtener columnas disponibles de notificaciones */
function obtenerColsNotificaciones(PDO $conn): array {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM notificaciones");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $r['Field'], $rows);
    } catch (\Exception $e) {
        return [];
    }
}

/* =====================================================
   ✅ NUEVO (SIN TOCAR DB): HELPERS EXTRA USUARIOS
   - Para poder traer notificaciones + usuarios
===================================================== */
function obtenerColsUsuarios(PDO $conn): array {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM usuarios");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $r['Field'], $rows);
    } catch (\Exception $e) {
        return [];
    }
}

function obtenerColFotoUsuarios(PDO $conn): string {
    $cols = obtenerColsUsuarios($conn);

    if (in_array('foto_perfil', $cols, true)) return 'foto_perfil';
    if (in_array('foto', $cols, true)) return 'foto';
    if (in_array('imagen', $cols, true)) return 'imagen';
    if (in_array('avatar', $cols, true)) return 'avatar';

    return ''; // no existe columna de foto
}

function insertarNotificacionSimple(PDO $conn, array $data): bool
{
    try {
        $stmtCols = $conn->query("SHOW COLUMNS FROM notificaciones");
        $cols = array_map(fn($r) => $r['Field'], $stmtCols->fetchAll(PDO::FETCH_ASSOC));

        // Columnas base
        $colMensaje = in_array('mensaje', $cols, true) ? 'mensaje' : (in_array('descripcion', $cols, true) ? 'descripcion' : null);
        $colTitulo  = in_array('titulo', $cols, true) ? 'titulo' : (in_array('asunto', $cols, true) ? 'asunto' : null);
        $colFecha   = in_array('fecha_creacion', $cols, true) ? 'fecha_creacion' : (in_array('created_at', $cols, true) ? 'created_at' : null);

        // Compat columnas referencia
        $tieneRefTipo = in_array('referencia_tipo', $cols, true);
        $tieneRefId   = in_array('referencia_id', $cols, true);

        if (!$colMensaje || !$colTitulo) {
            return false;
        }

        $fields = ['id_usuario', 'tipo', $colTitulo, $colMensaje, 'leida'];
        $values = [':id_usuario', ':tipo', ':titulo', ':mensaje', ':leida'];

        // Referencia opcional
        if ($tieneRefTipo && isset($data['referencia_tipo'])) {
            $fields[] = 'referencia_tipo';
            $values[] = ':ref_tipo';
        }
        if ($tieneRefId && isset($data['referencia_id'])) {
            $fields[] = 'referencia_id';
            $values[] = ':ref_id';
        }

        if ($colFecha) {
            $fields[] = $colFecha;
            $values[] = 'NOW()';
        }

        $sql = "INSERT INTO notificaciones (" . implode(',', $fields) . ")
                VALUES (" . implode(',', $values) . ")";

        $stmt = $conn->prepare($sql);

        $params = [
            ':id_usuario' => (int)$data['id_usuario'],
            ':tipo'       => (string)$data['tipo'],
            ':titulo'     => (string)$data['titulo'],
            ':mensaje'    => (string)$data['mensaje'],
            ':leida'      => (int)($data['leida'] ?? 0),
        ];

        if ($tieneRefTipo && isset($data['referencia_tipo'])) {
            $params[':ref_tipo'] = (string)$data['referencia_tipo'];
        }
        if ($tieneRefId && isset($data['referencia_id'])) {
            $params[':ref_id'] = (int)$data['referencia_id'];
        }

        return $stmt->execute($params);

    } catch (\Exception $e) {
        error_log("ERROR insertarNotificacionSimple: " . $e->getMessage());
        return false;
    }
}


/* ✅ FIX: insertar notificación de CAMBIO_DATOS de forma tolerante */
/* ✅ FIX: insertar notificación de CAMBIO_DATOS de forma tolerante */
function insertarNotificacionCambioDatos(PDO $conn, int $idDestinatario, string $jsonMensaje, int $idSolicitante): bool
{
    try {
        $cols = obtenerColsNotificaciones($conn);

        // ✅ Detectar columna de fecha si existe
        $colFecha = null;
        if (in_array('fecha_creacion', $cols, true)) $colFecha = 'fecha_creacion';
        elseif (in_array('created_at', $cols, true)) $colFecha = 'created_at';

        // ✅ Detectar columnas reales para título y mensaje
        $colTitulo  = in_array('titulo', $cols, true) ? 'titulo' : (in_array('asunto', $cols, true) ? 'asunto' : null);
        $colMensaje = in_array('mensaje', $cols, true) ? 'mensaje' : (in_array('descripcion', $cols, true) ? 'descripcion' : null);

        if (!$colTitulo || !$colMensaje) {
            // Si no existen columnas base, no podemos insertar sin romper
            return false;
        }

        // ✅ Detectar columna referencia_id si existe
        $colRef = obtenerColRefNotificaciones($conn);
        $tieneRef = (!empty($colRef) && in_array($colRef, $cols, true));

        // ✅ referencia_tipo solo si existe
        $tieneRefTipo = in_array('referencia_tipo', $cols, true);

        // ✅ Construcción dinámica (segura)
        $fields = ['id_usuario', 'tipo', $colTitulo, $colMensaje, 'leida'];
        $values = [':id_usuario', ':tipo', ':titulo', ':mensaje', ':leida'];

        if ($tieneRefTipo) {
            $fields[] = 'referencia_tipo';
            $values[] = ':ref_tipo';
        }

        if ($tieneRef) {
            $fields[] = $colRef;
            $values[] = ':ref_id';
        }

        if ($colFecha) {
            $fields[] = $colFecha;
            $values[] = 'NOW()';
        }

        $sql = "INSERT INTO notificaciones (" . implode(',', $fields) . ")
                VALUES (" . implode(',', $values) . ")";

        $stmt = $conn->prepare($sql);

        $params = [
            ':id_usuario' => $idDestinatario,
            ':tipo'       => 'CAMBIO_DATOS',
            ':titulo'     => 'Solicitud de Cambio de Datos',
            ':mensaje'    => $jsonMensaje,
            ':leida'      => 0,
        ];

        if ($tieneRefTipo) {
            $params[':ref_tipo'] = 'usuario';
        }

        if ($tieneRef) {
            $params[':ref_id'] = $idSolicitante;
        }

        return $stmt->execute($params);

} catch (\Exception $e) {
    error_log("ERROR insertarNotificacionCambioDatos: " . $e->getMessage());
    return false;
}

}

/**
 * ✅ Detecta la columna real que guarda el ID del solicitante en notificaciones
 * Sin tocar la DB, solo lectura segura
 */
function obtenerColSolicitanteNotif(PDO $conn): string {
    $cols = obtenerColsNotificaciones($conn);

    $candidatas = ['referencia_id', 'reference_id', 'ref_id'];

    foreach ($candidatas as $c) {
        if (in_array($c, $cols, true)) {
            return $c;
        }
    }

    return ''; // no hay columna referencia en la tabla
}



/* ============================ */
$usuario = new Usuario($conn);

/* ✅ FIX: aceptar accion por GET o POST (FormData) */
$accion = $_GET['accion'] ?? $_POST['accion'] ?? null;
if (!$accion) {
    enviarJSON(['error' => 'Debe especificar la acción'], 400);
}

/* =====================================================
   ✅ CAPA COMPATIBILIDAD FRONTEND (SIN TOCAR TU BASE)
   Convierte acciones antiguas del JS a las acciones reales
===================================================== */
$accionCompat = [
    // ✅ COMPAT: NOTIFICACIONES (usar nombres que NO choquen con usuarios)
    'contador'              => 'contar_notificaciones',
    'listar_notificaciones' => 'obtener_notificaciones',
    'listarNotificaciones'  => 'obtener_notificaciones',
    'marcar-leida'          => 'marcar_notificacion_leida',
    'marcar-todas'          => 'marcar_todas_leidas',
    'eliminar_notificacion' => 'eliminar_notificacion',

    // ✅ COMPAT: Perfil (tu frontend está enviando esto)
    'editar_perfil_usuario'           => 'actualizar_perfil',
    'actualizar_perfil_usuario'       => 'actualizar_perfil',
    'editar_perfil'                   => 'actualizar_perfil',

    // ✅ COMPAT: listar usuarios (por si tu JS usa otra variante)
    'listarUsuarios' => 'listar_usuarios',
];



$accionTrim = trim((string)$accion);
if (isset($accionCompat[$accionTrim])) {
    $accion = $accionCompat[$accionTrim];
}

/* =====================================================
   ✅ Detectores de columnas (notificaciones)
===================================================== */
$NOTI_PK   = obtenerPKNotificaciones($conn);
$NOTI_REF  = obtenerColRefNotificaciones($conn);
$NOTI_DATE = obtenerColFechaNotificaciones($conn);

/* =====================================================
   SWITCH
===================================================== */
switch ($accion) {

    /* =====================================================
       ✅ CONTADOR NOTIFICACIONES (coordinador vs usuario)
       GET: ?accion=contar_notificaciones
    ===================================================== */
    case 'contar_notificaciones':

        if (!isset($_SESSION['usuario_id'])) {
            enviarJSON(['success' => false, 'error' => 'No autorizado'], 401);
        }

        $idUsuario      = (int)$_SESSION['usuario_id'];
        $esCoordinador  = ($_SESSION['usuario_cargo'] ?? '') === 'Coordinador';

        try {
            if ($esCoordinador) {
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) AS total,
                        SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) AS no_leidas
                    FROM notificaciones
                ");
                $stmt->execute();
            } else {
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

            enviarJSON([
                'success'   => true,
                'no_leidas' => (int)($row['no_leidas'] ?? 0),
                'total'     => (int)($row['total'] ?? 0),
                'es_coordinador' => $esCoordinador
            ]);
        } catch (\Exception $e) {
            enviarJSON(['success' => false, 'error' => $e->getMessage()], 500);
        }
    break;

    /* =====================================================
       ✅ OBTENER NOTIFICACIONES (CORREGIDO: TAMBIÉN TRAE USUARIOS)
       GET: ?accion=obtener_notificaciones&solo_no_leidas=1
    ===================================================== */
    case 'obtener_notificaciones':

        if (!isset($_SESSION['usuario_id'])) {
            enviarJSON([
                'success' => false,
                'error' => 'No hay sesión activa',
                'notificaciones' => [],
                'sin_leer' => 0,
                'total' => 0
            ], 401);
        }

        $id_usuario     = (int)$_SESSION['usuario_id'];
        $cargo_usuario  = $_SESSION['usuario_cargo'] ?? '';
        $solo_no_leidas = isset($_GET['solo_no_leidas']) && $_GET['solo_no_leidas'] == '1';

        try {
            $where  = "WHERE n.id_usuario = ?";
            $params = [$id_usuario];

            // Si NO es coordinador, NO mostrar notificaciones de cambio de datos
            if ($cargo_usuario !== 'Coordinador') {
                $where .= " AND n.tipo != 'CAMBIO_DATOS'";
            }

            if ($solo_no_leidas) {
                $where .= " AND n.leida = 0";
            }

            $sql_sin_leer = "SELECT COUNT(*) as total FROM notificaciones n $where";
            $stmt_sin_leer = $conn->prepare($sql_sin_leer);
            $stmt_sin_leer->execute($params);
            $sin_leer = (int)$stmt_sin_leer->fetchColumn();

            $sql_total = "SELECT COUNT(*) as total FROM notificaciones WHERE id_usuario = ?";
            $stmt_total = $conn->prepare($sql_total);
            $stmt_total->execute([$id_usuario]);
            $total = (int)$stmt_total->fetchColumn();

            // ✅ FIX: orden por fecha si existe, si no por PK desc
            $orderBy = "";
            if (!empty($NOTI_DATE)) {
                $orderBy = "ORDER BY n.{$NOTI_DATE} DESC";
            } else {
                $orderBy = "ORDER BY n.{$NOTI_PK} DESC";
            }

            // Detectar si existe referencia_id (o su nombre real)
            $colsNoti = obtenerColsNotificaciones($conn);
            $tieneRef = (!empty($NOTI_REF) && in_array($NOTI_REF, $colsNoti, true));

            // Detectar columna foto en usuarios si existe
            $fotoCol = obtenerColFotoUsuarios($conn);

            // SELECT con usuario destinatario (u)
            $select = "
                n.*,
                u.id_usuario AS usuario_id,
                u.nombre_completo AS usuario_nombre,
                u.correo AS usuario_correo,
                u.cargo AS usuario_cargo
            ";

            if ($fotoCol !== '') {
                $select .= ", u.{$fotoCol} AS usuario_foto";
            }

            // Si tiene referencia_id, traer también el solicitante (us)
            if ($tieneRef) {
                $select .= ",
                    us.id_usuario AS solicitante_id,
                    us.nombre_completo AS solicitante_nombre,
                    us.correo AS solicitante_correo,
                    us.cargo AS solicitante_cargo
                ";

                if ($fotoCol !== '') {
                    $select .= ", us.{$fotoCol} AS solicitante_foto";
                }
            }

            $joinSolicitante = "";
            if ($tieneRef) {
                $joinSolicitante = "LEFT JOIN usuarios us ON us.id_usuario = n.{$NOTI_REF}";
            }

            $sql_notif = "
                SELECT $select
                FROM notificaciones n
                LEFT JOIN usuarios u ON u.id_usuario = n.id_usuario
                $joinSolicitante
                $where
                $orderBy
                LIMIT 10
            ";

            $stmt_notif = $conn->prepare($sql_notif);
            $stmt_notif->execute($params);
            $notificaciones = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);

            enviarJSON([
                'success' => true,
                'sin_leer' => $sin_leer,
                'total' => $total,
                'notificaciones' => $notificaciones,
                'usuario_id' => $id_usuario,
                'cargo' => $cargo_usuario,
                'es_coordinador' => ($cargo_usuario === 'Coordinador'),
                'pk' => $NOTI_PK,
                'col_fecha' => $NOTI_DATE,
                'col_ref' => $NOTI_REF,
                'tiene_ref' => $tieneRef ? 1 : 0
            ]);

        } catch (\Exception $e) {
            enviarJSON([
                'success' => false,
                'error' => $e->getMessage(),
                'notificaciones' => [],
                'sin_leer' => 0,
                'total' => 0
            ], 500);
        }
    break;

    /* =====================================================
       ✅ ELIMINAR NOTIFICACIÓN
       POST: notificacion_id
       ?accion=eliminar_notificacion
    ===================================================== */
    case 'eliminar_notificacion':
        try {
            $notificacion_id = (int)($_POST['notificacion_id'] ?? $_POST['id_notificacion'] ?? 0);
            $usuario_id      = (int)($_SESSION['usuario_id'] ?? 0);

            if ($notificacion_id <= 0 || $usuario_id <= 0) {
                throw new \Exception('Datos inválidos');
            }

            $stmt = $conn->prepare("
                SELECT {$NOTI_PK}
                FROM notificaciones
                WHERE {$NOTI_PK} = ?
                  AND id_usuario = ?
                LIMIT 1
            ");
            $stmt->execute([$notificacion_id, $usuario_id]);

            if (!$stmt->fetch()) {
                throw new \Exception('No tienes permiso para eliminar esta notificación');
            }

            $stmt = $conn->prepare("DELETE FROM notificaciones WHERE {$NOTI_PK} = ?");
            $stmt->execute([$notificacion_id]);

            enviarJSON(['success' => true, 'message' => 'Notificación eliminada correctamente']);
        } catch (\Exception $e) {
            enviarJSON(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    break;

    /* =====================================================
       ✅ MARCAR NOTIFICACIÓN COMO LEÍDA
       POST: notificacion_id
       ?accion=marcar_notificacion_leida
    ===================================================== */
    case 'marcar_notificacion_leida':
        try {
            $notificacion_id = (int)($_POST['notificacion_id'] ?? $_POST['id_notificacion'] ?? 0);
            $usuario_id      = (int)($_SESSION['usuario_id'] ?? 0);

            if ($notificacion_id <= 0 || $usuario_id <= 0) {
                throw new \Exception('ID inválido o no autorizado');
            }

            $stmt = $conn->prepare("
                UPDATE notificaciones
                SET leida = 1
                WHERE {$NOTI_PK} = ?
                  AND id_usuario = ?
            ");
            $stmt->execute([$notificacion_id, $usuario_id]);

            enviarJSON(['success' => true, 'message' => 'Notificación marcada como leída']);
        } catch (\Exception $e) {
            enviarJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    break;

    /* =====================================================
       ✅ MARCAR TODAS COMO LEÍDAS
       ?accion=marcar_todas_leidas
    ===================================================== */
    case 'marcar_todas_leidas':
        try {
            $usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
            if ($usuario_id <= 0) {
                enviarJSON(['success' => false, 'message' => 'No autorizado'], 401);
            }

            $stmt = $conn->prepare("UPDATE notificaciones SET leida = 1 WHERE id_usuario = ?");
            $stmt->execute([$usuario_id]);

            enviarJSON([
                'success' => true,
                'message' => 'Todas las notificaciones marcadas como leídas',
                'actualizadas' => (int)$stmt->rowCount()
            ]);
        } catch (\Exception $e) {
            enviarJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    break;

    /* =====================================================
       ✅ SOLICITAR CAMBIO DATOS SENSIBLES (aprendiz -> coordinador)
       POST: datos_cambiados (JSON string)
       ?accion=solicitar_cambio_datos_sensibles
    ===================================================== */
    case 'solicitar_cambio_datos_sensibles':

    if (!isset($_SESSION['usuario_id'])) {
        enviarJSON(['success' => false, 'error' => 'No autorizado'], 401);
    }

    $id_usuario_solicitante = (int)$_SESSION['usuario_id'];

    // ✅ 1) Intentar capturar desde POST normal (FormData)
    $datos_json = (string)($_POST['datos_cambiados'] ?? '');

    // ✅ 2) Si viene vacío, intentar capturar desde JSON (fetch application/json)
    if ($datos_json === '') {
        $rawBody = file_get_contents("php://input");
        $jsonBody = json_decode($rawBody, true);

        if (is_array($jsonBody) && isset($jsonBody['datos_cambiados'])) {
            // Puede venir ya como string JSON o como array
            if (is_string($jsonBody['datos_cambiados'])) {
                $datos_json = $jsonBody['datos_cambiados'];
            } else {
                $datos_json = json_encode($jsonBody['datos_cambiados'], JSON_UNESCAPED_UNICODE);
            }
        }
    }

    // ✅ 3) Validación final
    if (trim($datos_json) === '') {
        enviarJSON([
            'success' => false,
            'error'   => 'No se enviaron datos (datos_cambiados vacío)',
        ], 400);
    }

    try {
        // ✅ Buscar coordinador real
        $stmtCoord = $conn->prepare("
            SELECT id_usuario 
            FROM usuarios 
            WHERE LOWER(cargo) = 'coordinador' 
            LIMIT 1
        ");
        $stmtCoord->execute();
        $coord = $stmtCoord->fetch(PDO::FETCH_ASSOC);

        if (!$coord) {
            enviarJSON(['success' => false, 'error' => 'No existe un coordinador registrado'], 404);
        }

        $id_destinatario = (int)$coord['id_usuario'];

        // ✅ Insertar notificación (CAMBIO_DATOS)
        $ok = insertarNotificacionCambioDatos($conn, $id_destinatario, $datos_json, $id_usuario_solicitante);

        if (!$ok) {
            enviarJSON([
                'success' => false,
                'error'   => 'No se pudo insertar la notificación en DB'
            ], 500);
        }

        enviarJSON([
            'success' => true,
            'message' => 'Solicitud enviada al coordinador',
            'debug'   => [
                'destinatario' => $id_destinatario,
                'solicitante'  => $id_usuario_solicitante
            ]
        ]);

    } catch (\Exception $e) {
        error_log("ERROR solicitud cambio datos: " . $e->getMessage());

        enviarJSON([
            'success' => false,
            'error'   => 'Error del servidor',
            'detalle' => $e->getMessage()
        ], 500);
    }

break;


    /* =====================================================
       ✅ APROBAR CAMBIO DATOS (coordinador)
       POST: notificacion_id
       ?accion=aprobar_cambio_datos
    ===================================================== */
    case 'aprobar_cambio_datos':

    if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_cargo'] ?? '') !== 'Coordinador') {
        enviarJSON(['success' => false, 'error' => 'No autorizado'], 401);
    }

    $notif_id = (int)($_POST['notificacion_id'] ?? $_POST['id_notificacion'] ?? 0);
    if ($notif_id <= 0) {
        enviarJSON(['success' => false, 'error' => 'ID de notificación requerido'], 400);
    }

    try {
        // Traer notificación completa (CAMBIO_DATOS)
        $stmt = $conn->prepare("
            SELECT {$NOTI_PK} AS pk,
                   id_usuario,
                   tipo,
                   mensaje,
                   referencia_tipo,
                   referencia_id
            FROM notificaciones
            WHERE {$NOTI_PK} = ?
            LIMIT 1
        ");
        $stmt->execute([$notif_id]);
        $notif = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notif) {
            enviarJSON(['success' => false, 'error' => 'Notificación no encontrada'], 404);
        }

        // Validar que sea CAMBIO_DATOS
        $tipoNotif = strtoupper(trim((string)($notif['tipo'] ?? '')));
        if ($tipoNotif !== 'CAMBIO_DATOS') {
            enviarJSON(['success' => false, 'error' => 'Esta notificación no es de CAMBIO_DATOS'], 400);
        }

        $id_coordinador = (int)($_SESSION['usuario_id'] ?? 0);

        // ✅ ESTE ES EL ID REAL DEL APRENDIZ (SOLICITANTE)
        $id_aprendiz = (int)($notif['referencia_id'] ?? 0);
        $refTipo     = strtolower(trim((string)($notif['referencia_tipo'] ?? '')));

        // Si referencia_tipo existe y NO es usuario, igual intentamos usar referencia_id
        if ($id_aprendiz <= 0) {
            enviarJSON(['success' => false, 'error' => 'No se encontró el id del aprendiz (referencia_id vacío)'], 500);
        }

        // ✅ PROTECCIÓN: si por algún motivo referencia_id terminó siendo el coordinador, NO insertes mal
        if ($id_aprendiz === $id_coordinador) {
            enviarJSON([
                'success' => false,
                'error' => 'referencia_id apunta al coordinador. No se puede notificar al aprendiz.',
                'debug' => [
                    'id_aprendiz' => $id_aprendiz,
                    'id_coordinador' => $id_coordinador
                ]
            ], 500);
        }

        // Marcar la solicitud como leída (para el coordinador)
        $conn->prepare("UPDATE notificaciones SET leida = 1 WHERE {$NOTI_PK} = ?")->execute([$notif_id]);

        // ✅ Insertar notificación de respuesta AL APRENDIZ
        $okInsert = insertarNotificacionSimple($conn, [
            'id_usuario'       => $id_aprendiz,
            'tipo'             => 'SOLICITUD_APROBADA', // ✅ ENUM válido en tu BD
            'titulo'           => 'Cambio de Datos Aprobado',
            'mensaje'          => 'Tu solicitud de cambio de datos fue aprobada. El coordinador realizará los cambios manualmente.',
            'leida'            => 0,

            // ✅ Para que el aprendiz vea quién respondió
            'referencia_tipo'  => 'usuario',
            'referencia_id'    => $id_coordinador
        ]);

        if (!$okInsert) {
            enviarJSON(['success' => false, 'error' => 'No se pudo insertar la notificación al aprendiz'], 500);
        }

        enviarJSON([
            'success' => true,
            'message' => 'Solicitud aprobada. Notificación enviada al aprendiz.',
            'debug'   => [
                'aprendiz_destino' => $id_aprendiz,
                'coordinador_origen' => $id_coordinador
            ]
        ]);

    } catch (\Exception $e) {
        enviarJSON(['success' => false, 'error' => $e->getMessage()], 500);
    }

break;



    /* =====================================================
       ✅ RECHAZAR CAMBIO DATOS (coordinador)
       POST: notificacion_id, motivo (opcional)
       ?accion=rechazar_cambio_datos
    ===================================================== */
    case 'rechazar_cambio_datos':

    if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_cargo'] ?? '') !== 'Coordinador') {
        enviarJSON(['success' => false, 'error' => 'No autorizado'], 401);
    }

    $notif_id = (int)($_POST['notificacion_id'] ?? $_POST['id_notificacion'] ?? 0);
    $motivo   = (string)($_POST['motivo'] ?? 'No especificado');

    if ($notif_id <= 0) {
        enviarJSON(['success' => false, 'error' => 'ID de notificación requerido'], 400);
    }

    try {

        // ✅ Detectar columna real donde está el ID del solicitante
        $COL_SOLICITANTE = obtenerColSolicitanteNotif($conn);

        if ($COL_SOLICITANTE === '') {
            enviarJSON([
                'success' => false,
                'error' => 'Tu tabla notificaciones no tiene columna de referencia (referencia_id / ref_id).'
            ], 500);
        }

        // ✅ Traer el ID del aprendiz solicitante
        $stmt = $conn->prepare("
            SELECT {$COL_SOLICITANTE} AS solicitante_id
            FROM notificaciones
            WHERE {$NOTI_PK} = ?
            LIMIT 1
        ");
        $stmt->execute([$notif_id]);
        $notif = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notif) {
            enviarJSON(['success' => false, 'error' => 'Notificación no encontrada'], 404);
        }

        $id_aprendiz = (int)($notif['solicitante_id'] ?? 0);

        // ✅ Marcar la solicitud original como leída
        $conn->prepare("UPDATE notificaciones SET leida = 1 WHERE {$NOTI_PK} = ?")->execute([$notif_id]);

        // ✅ Insertar respuesta al aprendiz (NO al coordinador)
        if ($id_aprendiz > 0) {

            $msg = "Tu solicitud de cambio de datos ha sido rechazada. Motivo: " . $motivo;

            $ok2 = insertarNotificacionSimple($conn, [
                'id_usuario' => $id_aprendiz,
                'tipo'       => 'SOLICITUD_RECHAZADA',
                'titulo'     => 'Solicitud Rechazada',
                'mensaje'    => $msg,
                'leida'      => 0
            ]);

            if (!$ok2) {
                enviarJSON([
                    'success' => false,
                    'error' => 'La solicitud fue rechazada, pero no se pudo notificar al aprendiz.'
                ], 500);
            }

        } else {
            enviarJSON([
                'success' => false,
                'error' => 'No se encontró el aprendiz solicitante (referencia vacía).'
            ], 500);
        }

        enviarJSON([
            'success' => true,
            'message' => 'Solicitud rechazada. Se ha notificado al aprendiz.',
            'debug'   => [
                'notif_id' => $notif_id,
                'id_aprendiz' => $id_aprendiz,
                'col_solicitante' => $COL_SOLICITANTE
            ]
        ]);

    } catch (\Exception $e) {
        enviarJSON(['success' => false, 'error' => $e->getMessage()], 500);
    }

break;

    /* =====================================================
       ✅ LISTAR USUARIOS
       GET: ?accion=listar
    ===================================================== */
    case 'listar':
        enviarJSON($usuario->listar());
    break;

    case 'listar_usuarios':
        enviarJSON($usuario->listar());
    break;

    /* =====================================================
       ✅ OBTENER USUARIO POR ID
       GET: ?accion=obtener&id_usuario=1
    ===================================================== */
    case 'obtener':
        $id_usuario = $_GET['id_usuario'] ?? null;

        if (!$id_usuario) {
            enviarJSON(['error' => 'Debe enviar el parámetro id_usuario'], 400);
        }

        $res = $usuario->obtenerPorId($id_usuario);
        enviarJSON($res ? $res : ['error' => 'Usuario no encontrado'], $res ? 200 : 404);
    break;

    /* =====================================================
       ✅ CREAR USUARIO + TOKEN + CORREO ACTIVACIÓN
       POST JSON: ?accion=crear
    ===================================================== */
    case 'crear':

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
            enviarJSON(['error' => 'Datos incompletos'], 400);
        }

        $nombre = colapsarEspacios($nombre);
        if ($nombre === '' || !validarSoloTexto($nombre)) {
            enviarJSON(['error' => 'El nombre solo puede contener letras y espacios'], 400);
        }

        $cargosValidos = ['Coordinador','Subcoordinador','Instructor','Pasante','Aprendiz'];
        if ($cargo && !in_array($cargo, $cargosValidos, true)) {
            enviarJSON(['error' => 'Cargo no válido'], 400);
        }

        // Regla: solo Instructor puede llevar id_programa
        if ($cargo !== 'Instructor') {
            $id_programa = null;
        } else {
            if ($id_programa === null || $id_programa === '' || (int)$id_programa <= 0) {
                enviarJSON(['error' => 'Debe seleccionar un programa para el Instructor.'], 400);
            }
        }

        try {
            if ($numero_documento && $usuario->obtenerPorDocumento($numero_documento)) {
                enviarJSON(['error' => 'El número de documento ya está registrado'], 409);
            }
            if ($usuario->obtenerPorCorreo($correo)) {
                enviarJSON(['error' => 'El correo ya está registrado'], 409);
            }

            $token = bin2hex(random_bytes(32));

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
                enviarJSON(['error' => 'Error al crear usuario'], 500);
            }

            // Guardar token
            try {
                $usuario->crearTokenVerificacion($lastId, $token);
            } catch (\Exception $e) {
                enviarJSON([
                    'success' => false,
                    'error' => 'No se pudo guardar el token en tokens_correo',
                    'detalle' => $e->getMessage(),
                    'id_usuario' => $lastId
                ], 500);
            }

            // Enviar correo
            try {
                $mail = new PHPMailer(true);

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

                enviarJSON([
                    'success' => true,
                    'mensaje' => 'Usuario creado. Revisa tu correo para activar la cuenta',
                    'id_usuario' => $lastId
                ]);

            } catch (\Exception $e) {
                enviarJSON([
                    'success' => false,
                    'error' => 'Usuario creado pero error enviando correo',
                    'detalle' => $e->getMessage()
                ], 500);
            }

        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                if (strpos($e->getMessage(), 'numero_documento') !== false) {
                    enviarJSON(['error' => 'El número de documento ya está registrado'], 409);
                } elseif (strpos($e->getMessage(), 'correo') !== false) {
                    enviarJSON(['error' => 'El correo ya está registrado'], 409);
                } else {
                    enviarJSON(['error' => 'Error de duplicado en base de datos'], 409);
                }
            } else {
                enviarJSON(['error' => 'Error en base de datos: ' . $e->getMessage()], 500);
            }
        }
    break;

    /* =====================================================
       ✅ ACTIVAR CUENTA POR TOKEN (SIN AUTO-LOGIN)
       GET: ?accion=activar&token=...
    ===================================================== */
    case 'activar':
        $token = $_GET['token'] ?? null;

        if (!$token) {
            echo "Token inválido";
            exit;
        }

        if (!$usuario->activarCuenta($token)) {
            echo "Token inválido o expirado. Contacta al administrador.";
            exit;
        }

        try {
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

            if (!$u) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_unset();
                    session_destroy();
                }
                header("Location: " . BASE_URL . "src/view/login/login.php?activacion=ok");
                exit;
            }

            $conn->prepare("
                UPDATE tokens_correo
                SET usado = 1
                WHERE id_usuario = :uid
                  AND token LIKE 'FORCE_%'
                  AND usado = 0
            ")->execute([':uid' => (int)$u['id_usuario']]);

            $forceToken = FORCE_TOKEN_PREFIX . bin2hex(random_bytes(32));
            $forceExp   = (new DateTime('now'))->modify('+1 day')->format('Y-m-d H:i:s');

            $conn->prepare("
                INSERT INTO tokens_correo (id_usuario, token, tipo, fecha_expiracion, usado)
                VALUES (:uid, :t, 'reset_password', :exp, 0)
            ")->execute([
                ':uid' => (int)$u['id_usuario'],
                ':t'   => $forceToken,
                ':exp' => $forceExp
            ]);

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_unset();
                session_destroy();
            }

            header("Location: " . BASE_URL . "src/view/login/login.php?activacion=ok&force=1");
            exit;

        } catch (\Exception $e) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_unset();
                session_destroy();
            }
            header("Location: " . BASE_URL . "src/view/login/login.php?activacion=ok");
            exit;
        }
    break;

    /* =====================================================
       ✅ ACTUALIZAR USUARIO (EDITAR)
       ?accion=actualizar (JSON)
    ===================================================== */
    case 'actualizar':

        $data = json_decode(file_get_contents("php://input"), true);
        $id_usuario = $data['id_usuario'] ?? $_POST['id_usuario'] ?? $_GET['id_usuario'] ?? null;

        if (!$id_usuario) {
            enviarJSON(['error' => 'Debe enviar id_usuario'], 400);
        }

        $usuarioActual = $usuario->obtenerPorId($id_usuario);
        if (!$usuarioActual) {
            enviarJSON(['error' => 'Usuario no encontrado'], 404);
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
            enviarJSON(['error' => 'El nombre solo puede contener letras y espacios'], 400);
        }

        $cargosValidos = ['Coordinador','Subcoordinador','Instructor','Pasante','Aprendiz'];
        if (!in_array($cargo, $cargosValidos, true)) {
            enviarJSON(['error' => 'Cargo no válido'], 400);
        }

        if ($cargo !== 'Instructor') {
            $id_programa = null;
        } else {
            if ($id_programa === null || $id_programa === '' || (int)$id_programa <= 0) {
                enviarJSON(['error' => 'Debe seleccionar un programa para el Instructor.'], 400);
            }
        }

        if ($num_doc !== $usuarioActual['numero_documento']) {
            $existeDoc = $usuario->obtenerPorDocumento($num_doc);
            if ($existeDoc && (int)$existeDoc['id_usuario'] !== (int)$id_usuario) {
                enviarJSON(['error' => 'El número de documento ya está registrado'], 409);
            }
        }

        if ($correo !== $usuarioActual['correo'] && $usuario->obtenerPorCorreo($correo)) {
            enviarJSON(['error' => 'El correo ya está registrado'], 409);
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

        if ($ok && isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] === (int)$id_usuario) {
            $_SESSION['usuario_nombre'] = $nombre;
            $_SESSION['usuario_correo'] = $correo;
            $_SESSION['usuario_cargo']  = $cargo;

            $_SESSION['usuario_tipo_documento']   = $tipo_doc;
            $_SESSION['usuario_numero_documento'] = $num_doc;
            $_SESSION['usuario_telefono']         = $telefono;
            $_SESSION['usuario_direccion']        = $direccion;
        }

        enviarJSON(
            $ok
                ? ['success' => true, 'mensaje' => 'Usuario actualizado correctamente']
                : ['success' => false, 'error' => 'No se pudo actualizar el usuario'],
            $ok ? 200 : 500
        );
    break;

    /* =====================================================
       ✅ CAMBIAR ESTADO
       ?accion=cambiar_estado
       (si deshabilita, revoca sesiones en BD)
    ===================================================== */
    case 'cambiar_estado':

        $data = json_decode(file_get_contents("php://input"), true);

        $id_usuario = $data['id_usuario'] ?? $_POST['id_usuario'] ?? $_GET['id_usuario'] ?? null;
        $estado     = $data['estado']     ?? $_POST['estado']     ?? $_GET['estado']     ?? null;

        if ($id_usuario === null || $estado === null) {
            enviarJSON(['error' => 'Debe enviar id_usuario y estado (1 o 0)'], 400);
        }

        if ((int)$estado !== 1 && (int)$estado !== 0) {
            enviarJSON(['error' => 'El estado debe ser 1 o 0'], 400);
        }

        if (!$usuario->obtenerPorId($id_usuario)) {
            enviarJSON(['error' => 'Usuario no encontrado'], 404);
        }

        $ok = $usuario->cambiarEstado($id_usuario, $estado);

        if ($ok && (int)$estado === 0) {
            revocarSesionesUsuario($conn, (int)$id_usuario);
        }

        enviarJSON(
            $ok
                ? ['success' => true, 'mensaje' => 'Estado actualizado']
                : ['success' => false, 'error' => 'Error al actualizar estado'],
            $ok ? 200 : 500
        );
    break;

    /* =====================================================
       ✅ CHECK SESSION (valida token_sesion en BD)
       GET: ?accion=check_session
    ===================================================== */
    case 'check_session':

        if (!isset($_SESSION['usuario_id'])) {
            enviarJSON([
                'active' => 0,
                'reason' => 'no_session',
                'message' => 'No hay sesión activa.'
            ]);
        }

        if (!validarSesionActivaEnBD($conn)) {
            enviarJSON([
                'active' => 0,
                'reason' => 'revoked',
                'message' => 'Tu sesión fue cerrada o revocada.'
            ]);
        }

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

                revocarSesionesUsuario($conn, (int)$_SESSION['usuario_id']);

                session_unset();
                session_destroy();

                enviarJSON([
                    'active' => 0,
                    'reason' => 'disabled',
                    'message' => 'Tu cuenta ha sido deshabilitada por el administrador.'
                ]);
            }

            enviarJSON([
                'active' => 1,
                'reason' => 'ok',
                'message' => 'Sesión válida.'
            ]);

        } catch (\Exception $e) {
            enviarJSON([
                'active' => 0,
                'reason' => 'server',
                'message' => 'Error validando estado de la cuenta.'
            ], 500);
        }
    break;

    /* =====================================================
       ✅ LOGIN (JSON) + SESSION TOKEN BD + FORCE_ detection
       POST JSON: ?accion=login
    ===================================================== */
    case 'login':

        $data = json_decode(file_get_contents("php://input"), true);

        $correo   = $data['correo'] ?? null;
        $password = $data['password'] ?? null;

        if (!$correo || !$password) {
            enviarJSON(['error' => 'Credenciales incompletas'], 400);
        }

        $user = $usuario->login($correo, $password);

        if (!$user) {
            enviarJSON([
                'success' => false,
                'error' => 'Credenciales incorrectas o cuenta inactiva'
            ], 401);
        }

        if (isset($user['estado']) && !estadoEsActivo($user['estado'])) {
            enviarJSON([
                'success' => false,
                'error' => 'Cuenta inactiva. Contacta al administrador.'
            ], 401);
        }

        revocarSesionesUsuario($conn, (int)$user['id_usuario']);
        $newToken = crearTokenSesionBD($conn, (int)$user['id_usuario']);

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

        $_SESSION['token_sesion'] = $newToken ?: '';

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

        enviarJSON([
            'success' => true,
            'mensaje' => 'Login exitoso',
            'force_password_change' => (int)$_SESSION['force_password_change'],
            'usuario' => [
                'id'     => $user['id_usuario'],
                'nombre' => $user['nombre_completo'],
                'cargo'  => $user['cargo']
            ]
        ]);
    break;

    /* =====================================================
       ✅ LOGOUT (REDIRECCIÓN) + baja token en BD
       GET: ?accion=logout
    ===================================================== */
    case 'logout':

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
        } catch (\Exception $e) {
            // ignore
        }

        session_unset();
        session_destroy();

        header("Location: " . BASE_URL . "src/view/login/login.php");
        exit;
    break;

    /* =====================================================
       ✅ RESET PASSWORD (VALIDA TOKEN + CAMBIA PASSWORD)
       POST FORM: ?accion=reset_password
    ===================================================== */
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
                $conn->prepare("UPDATE tokens_correo SET usado = 1 WHERE id_token = :id")
                     ->execute([':id' => (int)$row['id_token']]);

                header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=expired");
                exit;
            }

            $idUsuario = (int)$row['id_usuario'];
            $idToken   = (int)$row['id_token'];

            $newHash = password_hash($p1, PASSWORD_DEFAULT);

            $conn->prepare("
                UPDATE usuarios
                SET password = :ph
                WHERE id_usuario = :uid
                LIMIT 1
            ")->execute([
                ':ph'  => $newHash,
                ':uid' => $idUsuario
            ]);

            $conn->prepare("
                UPDATE tokens_correo
                SET usado = 1
                WHERE id_token = :id
                LIMIT 1
            ")->execute([':id' => $idToken]);

            revocarSesionesUsuario($conn, $idUsuario);

            header("Location: " . BASE_URL . "src/view/login/login.php?reset=ok");
            exit;

        } catch (\Exception $e) {
            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=server");
            exit;
        }
    break;

    /* =====================================================
       ✅ SOLICITAR RESET PASSWORD (GUARDA TOKEN + ENVÍA CORREO)
       POST FORM: ?accion=request_reset_password
    ===================================================== */
    case 'request_reset_password':

        $correo = trim($_POST['correo'] ?? '');

        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=correo");
            exit;
        }

        try {
            $stmt = $conn->prepare("SELECT id_usuario, nombre_completo, correo FROM usuarios WHERE correo = :c LIMIT 1");
            $stmt->execute([':c' => $correo]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$u) {
                header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?ok=1");
                exit;
            }

            $idUsuario = (int)$u['id_usuario'];
            $nombre    = $u['nombre_completo'] ?? $correo;

            $conn->prepare("
                UPDATE tokens_correo
                SET usado = 1
                WHERE id_usuario = :uid AND tipo = 'reset_password' AND usado = 0
            ")->execute([':uid' => $idUsuario]);

            $token = bin2hex(random_bytes(32));
            $fechaExp = (new DateTime('now'))->modify('+30 minutes')->format('Y-m-d H:i:s');

            $ins = $conn->prepare("
                INSERT INTO tokens_correo (id_usuario, token, tipo, fecha_expiracion, usado)
                VALUES (:uid, :t, 'reset_password', :exp, 0)
            ");
            $ins->execute([
                ':uid' => $idUsuario,
                ':t'   => $token,
                ':exp' => $fechaExp
            ]);

            $resetLink = BASE_URL . "src/view/login/reset_password.php?token=" . urlencode($token);
            enviarCorreoResetPassword($correo, $nombre, $resetLink);

            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?ok=1");
            exit;

        } catch (\Exception $e) {
            header("Location: " . BASE_URL . "src/view/login/recuperar_contrasena.php?err=send");
            exit;
        }
    break;

    /* =====================================================
       ✅ ACTUALIZAR PERFIL (TELÉFONO, DIRECCIÓN Y FOTO)
       POST FORM-DATA: ?accion=actualizar_perfil
       (valida token_sesion)
    ===================================================== */
    case 'actualizar_perfil':

        if (!isset($_SESSION['usuario_id'])) {
            enviarJSON(['error' => 'No hay sesión activa. Inicia sesión nuevamente.'], 401);
        }

        if (!validarSesionActivaEnBD($conn)) {
            enviarJSON(['error' => 'Sesión expirada o revocada. Inicia sesión nuevamente.'], 401);
        }

        $id_usuario = (int)$_SESSION['usuario_id'];

        $telefono  = isset($_POST['telefono'])  ? colapsarEspacios($_POST['telefono'])  : '';
        $direccion = isset($_POST['direccion']) ? colapsarEspacios($_POST['direccion']) : '';

        if ($telefono !== '' && !preg_match('/^[0-9+\s\-()]{7,20}$/', $telefono)) {
            enviarJSON(['error' => 'Teléfono no válido.'], 400);
        }

        $fotoPathDB = null;

        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {

            if ($_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK) {
                enviarJSON(['error' => 'Error subiendo la foto.'], 400);
            }

            $tmp  = $_FILES['foto_perfil']['tmp_name'];
            $name = $_FILES['foto_perfil']['name'] ?? '';
            $size = (int)($_FILES['foto_perfil']['size'] ?? 0);

            if ($size > 2 * 1024 * 1024) {
                enviarJSON(['error' => 'La foto supera el límite de 2MB.'], 400);
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];

            if (!in_array($ext, $allowed, true)) {
                enviarJSON(['error' => 'Formato no permitido. Usa JPG, PNG o WEBP.'], 400);
            }

            $uploadDirAbs = __DIR__ . '/../uploads/perfiles/';
            if (!is_dir($uploadDirAbs)) {
                @mkdir($uploadDirAbs, 0777, true);
            }

            $fileName = 'perfil_' . $id_usuario . '_' . time() . '.' . $ext;
            $destAbs  = $uploadDirAbs . $fileName;

            if (!move_uploaded_file($tmp, $destAbs)) {
                enviarJSON(['error' => 'No se pudo guardar la foto.'], 500);
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
                enviarJSON(['error' => 'No se pudo actualizar el perfil.'], 500);
            }

            $_SESSION['usuario_telefono']  = $telefono;
            $_SESSION['usuario_direccion'] = $direccion;

            if ($fotoPathDB) {
                $_SESSION['usuario_foto'] = $fotoPathDB;
            }

            enviarJSON(['success' => true, 'mensaje' => 'Perfil actualizado correctamente']);
        } catch (\Exception $e) {
            enviarJSON(['error' => 'Error del servidor al actualizar perfil', 'detalle' => $e->getMessage()], 500);
        }
    break;

    /* =====================================================
       ✅ CAMBIAR PASSWORD DESDE PERFIL
       POST: password_actual, password_nueva, password_confirmar
       ?accion=cambiar_password
       (valida token_sesion)
    ===================================================== */
    case 'cambiar_password':

        if (!isset($_SESSION['usuario_id'])) {
            enviarJSON(['error' => 'No hay sesión activa. Inicia sesión nuevamente.'], 401);
        }

        if (!validarSesionActivaEnBD($conn)) {
            enviarJSON(['error' => 'Sesión expirada o revocada. Inicia sesión nuevamente.'], 401);
        }

        $id_usuario = (int)$_SESSION['usuario_id'];

        $actual    = (string)($_POST['password_actual'] ?? '');
        $nueva     = (string)($_POST['password_nueva'] ?? '');
        $confirmar = (string)($_POST['password_confirmar'] ?? '');

        if ($actual === '' || $nueva === '' || $confirmar === '') {
            enviarJSON(['error' => 'Complete todos los campos.'], 400);
        }

        if (strlen($nueva) < 8) {
            enviarJSON(['error' => 'La nueva contraseña debe tener mínimo 8 caracteres.'], 400);
        }

        if ($nueva !== $confirmar) {
            enviarJSON(['error' => 'La confirmación no coincide.'], 400);
        }

        try {
            $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = :id LIMIT 1");
            $stmt->execute([':id' => $id_usuario]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                enviarJSON(['error' => 'Usuario no encontrado.'], 404);
            }

            $hashActual = (string)$row['password'];

            if (!password_verify($actual, $hashActual)) {
                enviarJSON(['error' => 'La contraseña actual es incorrecta.'], 401);
            }

            if (password_verify($nueva, $hashActual)) {
                enviarJSON(['error' => 'La nueva contraseña no puede ser igual a la actual.'], 400);
            }

            $newHash = password_hash($nueva, PASSWORD_DEFAULT);

            $upd = $conn->prepare("UPDATE usuarios SET password = :ph WHERE id_usuario = :id LIMIT 1");
            $ok  = $upd->execute([':ph' => $newHash, ':id' => $id_usuario]);

            if ($ok) {
                $conn->prepare("
                    UPDATE tokens_correo
                    SET usado = 1
                    WHERE id_usuario = :uid
                      AND token LIKE 'FORCE_%'
                      AND usado = 0
                ")->execute([':uid' => $id_usuario]);

                unset($_SESSION['force_password_change']);

                enviarJSON(['success' => true, 'message' => 'Contraseña actualizada correctamente.']);
            }

            enviarJSON(['error' => 'No se pudo actualizar la contraseña.'], 500);

        } catch (\Exception $e) {
            enviarJSON(['error' => 'Error del servidor al cambiar contraseña', 'detalle' => $e->getMessage()], 500);
        }
    break;

    /* =====================================================
   ✅ CAMBIO DE CONTRASEÑA OBLIGATORIO (FORCE_%)
   POST: password_actual, password_nueva, password_confirmacion
   ?accion=cambiar_password_obligatorio
===================================================== */
case 'cambiar_password_obligatorio':

    if (!isset($_SESSION['usuario_id'])) {
        enviarJSON(['ok' => false, 'message' => 'Sesión no válida.'], 401);
    }

    $uid = (int)$_SESSION['usuario_id'];

    $passwordActual = trim($_POST['password_actual'] ?? '');
    $passwordNueva  = trim($_POST['password_nueva'] ?? '');
    $passwordConf   = trim($_POST['password_confirmacion'] ?? '');

    if ($passwordActual === '' || $passwordNueva === '' || $passwordConf === '') {
        enviarJSON(['ok' => false, 'message' => 'Debes completar todos los campos.'], 400);
    }

    if (strlen($passwordNueva) < 8) {
        enviarJSON(['ok' => false, 'message' => 'La nueva contraseña debe tener mínimo 8 caracteres.'], 400);
    }

    // ✅ Regla fuerte: 1 número, 1 mayúscula, 1 especial
    $hasUpper   = preg_match('/[A-Z]/', $passwordNueva);
    $hasNumber  = preg_match('/[0-9]/', $passwordNueva);
    $hasSpecial = preg_match('/[^A-Za-z0-9]/', $passwordNueva);

    if (!$hasUpper || !$hasNumber || !$hasSpecial) {
        enviarJSON(['ok' => false, 'message' => 'Debe tener mínimo 1 número, 1 mayúscula y 1 caracter especial.'], 400);
    }

    if ($passwordNueva !== $passwordConf) {
        enviarJSON(['ok' => false, 'message' => 'La confirmación no coincide con la nueva contraseña.'], 400);
    }

    if ($passwordActual === $passwordNueva) {
        enviarJSON(['ok' => false, 'message' => 'La nueva contraseña no puede ser igual a la actual.'], 400);
    }

    // 1) traer hash actual
    $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ? LIMIT 1");
    $stmt->execute([$uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        enviarJSON(['ok' => false, 'message' => 'Usuario no encontrado.'], 404);
    }

    $hashActual = (string)$row['password'];

    // 2) validar contraseña actual (hash o texto plano legacy)
    $okActual = false;
    if (password_verify($passwordActual, $hashActual)) {
        $okActual = true;
    } elseif ($passwordActual === $hashActual) {
        $okActual = true;
    }

    if (!$okActual) {
        enviarJSON(['ok' => false, 'message' => 'La contraseña actual no es correcta.'], 401);
    }

    // 3) actualizar password (hash)
    $nuevoHash = password_hash($passwordNueva, PASSWORD_DEFAULT);
    $stmtUp = $conn->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ? LIMIT 1");
    $stmtUp->execute([$nuevoHash, $uid]);

    // 4) marcar FORCE_% como usado
    try {
        $stmtToken = $conn->prepare("
            UPDATE tokens_correo
            SET usado = 1
            WHERE id_usuario = :uid
              AND tipo = 'reset_password'
              AND token LIKE 'FORCE_%'
              AND usado = 0
        ");
        $stmtToken->execute([':uid' => $uid]);
    } catch (Throwable $e) {
        // no romper
    }

    // 5) apagar flag
    $_SESSION['force_password_change'] = 0;

    enviarJSON(['ok' => true, 'message' => 'Contraseña actualizada correctamente.']);
break;

/* =====================================================
   ✅ LISTAR ROLES FUNCIONALES
   GET: ?accion=listar_roles_funcionales
===================================================== */
case 'listar_roles_funcionales':

    try {
        $stmt = $conn->prepare("
            SELECT id_rol, nombre_rol
            FROM roles_funcionales
            ORDER BY id_rol ASC
        ");
        $stmt->execute();

        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        enviarJSON([
            'success' => true,
            'roles' => $roles
        ]);

    } catch (\Exception $e) {
        enviarJSON([
            'success' => false,
            'error' => 'Error listando roles funcionales',
            'detalle' => $e->getMessage()
        ], 500);
    }

break;

/* =====================================================
   ✅ OBTENER ROL FUNCIONAL DE UN USUARIO
   GET: ?accion=obtener_rol_funcional_usuario&id_usuario=#
===================================================== */
case 'obtener_rol_funcional_usuario':

    $id_usuario = (int)($_GET['id_usuario'] ?? 0);

    if ($id_usuario <= 0) {
        enviarJSON(['success' => false, 'error' => 'id_usuario requerido'], 400);
    }

    try {
        $stmt = $conn->prepare("
            SELECT urf.id_rol, rf.nombre_rol
            FROM usuario_roles_funcionales urf
            INNER JOIN roles_funcionales rf ON rf.id_rol = urf.id_rol
            WHERE urf.id_usuario = ?
            ORDER BY urf.fecha_asignacion DESC
            LIMIT 1
        ");
        $stmt->execute([$id_usuario]);
        $rol = $stmt->fetch(PDO::FETCH_ASSOC);

        enviarJSON([
            'success' => true,
            'rol' => $rol ?: null
        ]);

    } catch (\Exception $e) {
        enviarJSON([
            'success' => false,
            'error' => 'Error obteniendo rol funcional',
            'detalle' => $e->getMessage()
        ], 500);
    }

break;

        /* =====================================================
           ✅ OBTENER ASIGNACIONES (BODEGAS / SUBBODEGAS) DE UN USUARIO
           GET: ?accion=obtener_asignaciones_usuario&id_usuario=#
           Retorna: { success:true, data: { bodegas: [...], subbodegas: [...] } }
        ===================================================== */
        case 'obtener_asignaciones_usuario':

            $id_usuario = (int)($_GET['id_usuario'] ?? 0);

            if ($id_usuario <= 0) {
                enviarJSON(['success' => false, 'error' => 'id_usuario requerido'], 400);
            }

            // Permisos: permitir solo Coordinador o el propio usuario ver sus asignaciones
            $esCoordinador = isset($_SESSION['usuario_cargo']) && $_SESSION['usuario_cargo'] === 'Coordinador';
            $esPropio = isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] === $id_usuario;

            if (!$esCoordinador && !$esPropio) {
                enviarJSON(['success' => false, 'error' => 'No autorizado'], 401);
            }

            try {
                // Bodegas: buscar en bodega_encargados (si la tabla existe)
                $bodegas = [];
                try {
                    $stmtB = $conn->prepare(
                        "SELECT be.id_bodega, b.nombre AS nombre_bodega, be.id_usuario_encargado, be.asignado_por, u.nombre_completo AS asignado_por_nombre, be.fecha_asignacion
                         FROM bodega_encargados be
                         LEFT JOIN bodegas b ON b.id_bodega = be.id_bodega
                         LEFT JOIN usuarios u ON u.id_usuario = be.asignado_por
                         WHERE be.id_usuario_encargado = ?"
                    );
                    $stmtB->execute([$id_usuario]);
                    $bodegas = $stmtB->fetchAll(PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    // Tabla puede no existir o fallar; no romper la respuesta
                    error_log('Error leyendo bodega_encargados: ' . $e->getMessage());
                    $bodegas = [];
                }

                // Subbodegas: buscar en subbodega_encargados (activo = 1)
                $subbodegas = [];
                try {
                    $stmtS = $conn->prepare(
                        "SELECT sb.id_subbodega, sb.nombre_subbodega, sbe.id_usuario, sbe.fecha_asignacion, sbe.activo
                         FROM subbodega_encargados sbe
                         LEFT JOIN sub_bodegas sb ON sb.id_subbodega = sbe.id_subbodega
                         WHERE sbe.id_usuario = ? AND (sbe.activo IS NULL OR sbe.activo = 1)"
                    );
                    $stmtS->execute([$id_usuario]);
                    $subbodegas = $stmtS->fetchAll(PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    error_log('Error leyendo subbodega_encargados: ' . $e->getMessage());
                    $subbodegas = [];
                }

                enviarJSON([
                    'success' => true,
                    'data' => [
                        'bodegas' => $bodegas,
                        'subbodegas' => $subbodegas,
                    ]
                ]);
            } catch (\Exception $e) {
                enviarJSON([
                    'success' => false,
                    'error' => 'Error obteniendo asignaciones',
                    'detalle' => $e->getMessage()
                ], 500);
            }

        break;

/* =====================================================
   ✅ ASIGNAR ROL FUNCIONAL (TABLA PUENTE)
   POST JSON: ?accion=asignar_rol_funcional
   Body: { id_usuario, id_rol }
===================================================== */
case 'asignar_rol_funcional':

    // ✅ Solo Coordinador puede asignar rol funcional
    if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_cargo'] ?? '') !== 'Coordinador') {
        enviarJSON(['success' => false, 'error' => 'No autorizado'], 401);
    }

    $data = json_decode(file_get_contents("php://input"), true);

    $id_usuario = (int)($data['id_usuario'] ?? 0);
    $id_rol     = (int)($data['id_rol'] ?? 0);

    if ($id_usuario <= 0 || $id_rol <= 0) {
        enviarJSON(['success' => false, 'error' => 'Datos incompletos (id_usuario / id_rol)'], 400);
    }

    $asignado_por = (int)$_SESSION['usuario_id'];

    try {
        $conn->beginTransaction();

        // ✅ Verificar usuario existe
        $stmtU = $conn->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ? LIMIT 1");
        $stmtU->execute([$id_usuario]);
        if (!$stmtU->fetch()) {
            $conn->rollBack();
            enviarJSON(['success' => false, 'error' => 'Usuario no encontrado'], 404);
        }

        // ✅ Verificar rol funcional existe
        $stmtR = $conn->prepare("SELECT id_rol FROM roles_funcionales WHERE id_rol = ? LIMIT 1");
        $stmtR->execute([$id_rol]);
        if (!$stmtR->fetch()) {
            $conn->rollBack();
            enviarJSON(['success' => false, 'error' => 'Rol funcional no existe'], 404);
        }

        // ✅ Si manejas 1 rol funcional por usuario -> reemplazar
        $conn->prepare("DELETE FROM usuario_roles_funcionales WHERE id_usuario = ?")
             ->execute([$id_usuario]);

        // ✅ Insertar nuevo rol funcional
        $stmtIns = $conn->prepare("
            INSERT INTO usuario_roles_funcionales (id_usuario, id_rol, asignado_por)
            VALUES (?, ?, ?)
        ");
        $stmtIns->execute([$id_usuario, $id_rol, $asignado_por]);


        // ✅ Si mandaron id_bodega → registrar encargado en tabla bodega_encargados
        if (!empty($data['id_bodega'])) {
            $idBodega = (int)$data['id_bodega'];

            // Eliminar asignaciones previas del mismo usuario en bodega_encargados
            $conn->prepare("DELETE FROM bodega_encargados WHERE id_usuario_encargado = ?")
                 ->execute([$id_usuario]);

            // Insertar la nueva asignación (si la tabla existe)
            try {
                $stmtBe = $conn->prepare("INSERT INTO bodega_encargados (id_bodega, id_usuario_encargado, asignado_por, fecha_asignacion) VALUES (?, ?, ?, NOW())");
                $stmtBe->execute([$idBodega, $id_usuario, $asignado_por]);
            } catch (\Exception $e) {
                // Si la tabla no existe o falla, registramos en log pero no rompemos el flujo
                error_log('Error asignando bodega_encargados: ' . $e->getMessage());
            }
        }

        // ✅ Si mandaron id_subbodega → registrar encargado en tabla subbodega_encargados
        if (!empty($data['id_subbodega'])) {
            $idSub = (int)$data['id_subbodega'];

            // Eliminar asignaciones previas del mismo usuario en subbodega_encargados
            $conn->prepare("DELETE FROM subbodega_encargados WHERE id_usuario = ?")
                 ->execute([$id_usuario]);

            try {
                $stmtSb = $conn->prepare("INSERT INTO subbodega_encargados (id_subbodega, id_usuario, fecha_asignacion, activo) VALUES (?, ?, NOW(), 1)");
                $stmtSb->execute([$idSub, $id_usuario]);
            } catch (\Exception $e) {
                error_log('Error asignando subbodega_encargados: ' . $e->getMessage());
            }
        }
        $conn->commit();

        enviarJSON([
            'success' => true,
            'mensaje' => 'Rol funcional asignado correctamente',
            'data' => [
                'id_usuario' => $id_usuario,
                'id_rol' => $id_rol,
                'asignado_por' => $asignado_por
            ]
        ]);

    } catch (\Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();

        enviarJSON([
            'success' => false,
            'error' => 'Error asignando rol funcional',
            'detalle' => $e->getMessage()
        ], 500);
    }

break;



    
    /* =====================================================
       ✅ DEFAULT
    ===================================================== */
    default:
        enviarJSON(['error' => 'Acción no válida'], 400);
    break;
}
