<?php
/**
 * ============================================================
 * API NOTIFICACIONES PARA HEADER (BD)
 * ============================================================
 * ✅ FIX REAL:
 * - Soporta acciones antiguas y nuevas:
 *   - fetch / listar / obtener_notificaciones
 *   - contador / contar
 * - Compatible con tu NotificacionSesion (notificaciones_sin_db.php)
 * - No toca DB, solo compatibilidad
 */

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/notificaciones_sin_db.php";

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado',
        'no_leidas' => 0
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ============================================================
   ✅ ACCION (GET/POST) + COMPAT
============================================================ */
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$accion = trim((string)$accion);

/**
 * ✅ Compatibilidad total:
 * Tu frontend puede usar "fetch", "listar", "obtener_notificaciones".
 * También soporta "contador".
 */
$compat = [
    'fetch' => 'listar',
    'obtener_notificaciones' => 'listar',
    'listar_notificaciones' => 'listar',
    'contador' => 'contar',
    'contar_notificaciones' => 'contar',
];

if (isset($compat[$accion])) {
    $accion = $compat[$accion];
}

/* ============================================================
   ✅ Acciones válidas reales
============================================================ */
$accionesValidas = [
    'contar',
    'listar',
    'marcar_leido',
    'marcar_todas_leidas',
    'eliminar',
    'eliminar_todas'
];

/* ============================================================
   Helpers mínimos
============================================================ */
function responderJSON(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_esCoordinador(): bool {
    $cargo = $_SESSION['usuario_cargo'] ?? '';
    return ($cargo === 'Coordinador' || $cargo === 'coordinador');
}

/**
 * ✅ Valida que la notificación sea visible para el usuario logueado
 */
function api_pk(PDO $conn): string {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM notificaciones");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cols = array_map(fn($r) => $r['Field'], $rows);

        if (in_array('id_notificacion', $cols, true)) return 'id_notificacion';
        if (in_array('id', $cols, true)) return 'id';

        return 'id_notificacion';
    } catch (\Exception $e) {
        return 'id_notificacion';
    }
}

function api_notificacionVisibleParaMi(PDO $conn, string $PK, int $notifId): bool {
    try {
        $uid = (int)($_SESSION['usuario_id'] ?? 0);
        if ($uid <= 0 || $notifId <= 0) return false;

        $stmt = $conn->prepare("SELECT id_usuario, tipo FROM notificaciones WHERE {$PK} = ? LIMIT 1");
        $stmt->execute([$notifId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return false;

        $idUsuarioNotif = (int)($row['id_usuario'] ?? 0);
        $tipoNotif      = strtoupper(trim((string)($row['tipo'] ?? '')));

        $esCoord = api_esCoordinador();

        // Usuario normal: solo las suyas y NO ve CAMBIO_DATOS
        if (!$esCoord) {
            if ($idUsuarioNotif !== $uid) return false;
            if ($tipoNotif === 'CAMBIO_DATOS') return false;
            return true;
        }

        // Coordinador: ve CAMBIO_DATOS + propias
        if ($tipoNotif === 'CAMBIO_DATOS') return true;
        return ($idUsuarioNotif === $uid);

    } catch (\Exception $e) {
        return false;
    }
}

$PK = api_pk($conn);

/* ============================================================
   ✅ ROUTER
============================================================ */
if (!in_array($accion, $accionesValidas, true)) {
    responderJSON([
        "success" => false,
        "error" => "Acción no válida",
        "acciones_validas" => [
            "contador",
            "obtener_notificaciones",
            "listar"
        ]
    ], 400);
}

try {

    switch ($accion) {

        /* ============================
           ✅ CONTAR
        ============================ */
        case 'contar':
            $res = NotificacionSesion::obtenerResumen();

            responderJSON([
                'success' => true,
                'total' => (int)($res['total'] ?? 0),
                'no_leidas' => (int)($res['no_leidas'] ?? 0),
            ]);
        break;

        /* ============================
           ✅ LISTAR / FETCH
        ============================ */
        case 'listar':

            $limit = isset($_GET["limit"]) ? (int)$_GET["limit"] : 10;
            if ($limit <= 0) $limit = 10;
            if ($limit > 50) $limit = 50;

            $resumen = NotificacionSesion::obtenerResumen();
            $lista   = NotificacionSesion::obtenerNotificaciones(null, $limit);

            responderJSON([
                "success" => true,
                "resumen" => $resumen,
                "notificaciones" => $lista
            ]);
        break;

        /* ============================
           ✅ MARCAR LEÍDA
        ============================ */
        case 'marcar_leido':
            $id = (int)($_POST['notificacion_id'] ?? 0);

            if ($id <= 0) {
                responderJSON(['success' => false, 'message' => 'ID inválido'], 400);
            }

            if (!api_notificacionVisibleParaMi($conn, $PK, $id)) {
                responderJSON(['success' => false, 'message' => 'No autorizado'], 401);
            }

            $stmt = $conn->prepare("UPDATE notificaciones SET leida=1 WHERE {$PK} = ?");
            $stmt->execute([$id]);

            responderJSON(['success' => true]);
        break;

        /* ============================
           ✅ MARCAR TODAS LEÍDAS
        ============================ */
        case 'marcar_todas_leidas':
            $uid = (int)($_SESSION['usuario_id'] ?? 0);
            $esCoord = api_esCoordinador();

            if ($uid <= 0) {
                responderJSON(['success' => false, 'message' => 'No autorizado'], 401);
            }

            if ($esCoord) {
                $stmt = $conn->prepare("
                    UPDATE notificaciones 
                    SET leida = 1
                    WHERE id_usuario = ?
                       OR tipo = 'CAMBIO_DATOS'
                ");
                $stmt->execute([$uid]);
            } else {
                $stmt = $conn->prepare("
                    UPDATE notificaciones
                    SET leida=1
                    WHERE id_usuario = ?
                      AND tipo != 'CAMBIO_DATOS'
                ");
                $stmt->execute([$uid]);
            }

            responderJSON([
                'success' => true,
                'actualizadas' => (int)$stmt->rowCount()
            ]);
        break;

        /* ============================
           ✅ ELIMINAR UNA
        ============================ */
        case 'eliminar':
            $id = (int)($_POST['notificacion_id'] ?? 0);

            if ($id <= 0) {
                responderJSON(['success' => false, 'message' => 'ID inválido'], 400);
            }

            if (!api_notificacionVisibleParaMi($conn, $PK, $id)) {
                responderJSON(['success' => false, 'message' => 'No autorizado'], 401);
            }

            $stmt = $conn->prepare("DELETE FROM notificaciones WHERE {$PK} = ?");
            $stmt->execute([$id]);

            responderJSON(['success' => true]);
        break;

        /* ============================
           ✅ ELIMINAR TODAS
        ============================ */
        case 'eliminar_todas':
            $eliminadas = NotificacionSesion::eliminarTodasBD();

            responderJSON([
                "success" => true,
                "eliminadas" => (int)$eliminadas
            ]);
        break;
    }

} catch (\Exception $e) {
    responderJSON([
        'success' => false,
        'message' => 'Error servidor',
        'detail' => $e->getMessage()
    ], 500);
}
