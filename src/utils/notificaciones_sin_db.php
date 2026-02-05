<?php
/**
 * ============================================================
 * NOTIFICACIONES (MISMO NOMBRE "sin_db" PERO CON BD)
 * ============================================================
 * ✅ NO se toca el header
 * ✅ La campana ahora lee desde la tabla `notificaciones`
 * ✅ FIX REAL:
 *    - Respuesta compatible con frontend viejo y nuevo:
 *      id / id_notificacion / notificacion_id
 *      descripcion / mensaje
 *      leido (bool) / leida (int)
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../Config/database.php";

/* ============================
   Helpers: detectar columnas
============================ */
function noti_obtenerColumnas(PDO $conn): array {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM notificaciones");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $r['Field'], $rows);
    } catch (\Exception $e) {
        return [];
    }
}

function noti_pk(PDO $conn): string {
    $cols = noti_obtenerColumnas($conn);
    if (in_array('id_notificacion', $cols, true)) return 'id_notificacion';
    if (in_array('id', $cols, true)) return 'id';
    return 'id_notificacion';
}

function noti_col_fecha(PDO $conn): string {
    $cols = noti_obtenerColumnas($conn);
    if (in_array('fecha_creacion', $cols, true)) return 'fecha_creacion';
    if (in_array('created_at', $cols, true)) return 'created_at';
    return '';
}

function noti_col_titulo(PDO $conn): string {
    $cols = noti_obtenerColumnas($conn);
    if (in_array('titulo', $cols, true)) return 'titulo';
    if (in_array('asunto', $cols, true)) return 'asunto';
    return 'titulo';
}

function noti_col_mensaje(PDO $conn): string {
    $cols = noti_obtenerColumnas($conn);
    if (in_array('mensaje', $cols, true)) return 'mensaje';
    if (in_array('descripcion', $cols, true)) return 'descripcion';
    return 'mensaje';
}

/* ============================
   ✅ Detectar referencia_id / referencia_tipo
============================ */
function noti_col_referencia_id(PDO $conn): string {
    $cols = noti_obtenerColumnas($conn);
    if (in_array('referencia_id', $cols, true)) return 'referencia_id';
    if (in_array('reference_id', $cols, true)) return 'reference_id';
    if (in_array('ref_id', $cols, true)) return 'ref_id';
    return '';
}

function noti_col_referencia_tipo(PDO $conn): string {
    $cols = noti_obtenerColumnas($conn);
    if (in_array('referencia_tipo', $cols, true)) return 'referencia_tipo';
    if (in_array('reference_type', $cols, true)) return 'reference_type';
    return '';
}

/* ============================
   ✅ Columnas usuarios (nombre)
============================ */
function usuarios_obtenerColumnas(PDO $conn): array {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM usuarios");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $r['Field'], $rows);
    } catch (\Exception $e) {
        return [];
    }
}

function usuarios_expr_nombre(PDO $conn, string $alias = 'u'): string {
    $cols = usuarios_obtenerColumnas($conn);

    if (in_array('nombre_completo', $cols, true)) {
        return "COALESCE($alias.nombre_completo,'')";
    }

    $tieneNombre   = in_array('nombre', $cols, true);
    $tieneApellido = in_array('apellido', $cols, true);

    if ($tieneNombre && $tieneApellido) {
        return "TRIM(CONCAT(COALESCE($alias.nombre,''),' ',COALESCE($alias.apellido,'')))";
    }

    if ($tieneNombre) {
        return "COALESCE($alias.nombre,'')";
    }

    return "''";
}

/* ============================
   Map tipo => icon + color
============================ */
function noti_map_tipo(string $tipo): array {
    $tipo = strtoupper(trim($tipo));

    return match ($tipo) {
        'CAMBIO_DATOS' => ['icono' => 'file-pen-line', 'color' => 'warning'],
        'SOLICITUD_APROBADA' => ['icono' => 'check-circle', 'color' => 'success'],
        'SOLICITUD_RECHAZADA' => ['icono' => 'x-circle', 'color' => 'danger'],
        'SOLICITUD_CREADA' => ['icono' => 'bell', 'color' => 'info'],
        default => ['icono' => 'bell', 'color' => 'info'],
    };
}

/* ============================
   Cargo
============================ */
function noti_esCoordinador(): bool {
    $cargo = $_SESSION['usuario_cargo'] ?? '';
    return ($cargo === 'Coordinador' || $cargo === 'coordinador');
}

class NotificacionSesion
{
    public static function obtenerResumen(): array
    {
        global $conn;

        if (!isset($_SESSION['usuario_id'])) {
            return ['total' => 0, 'no_leidas' => 0];
        }

        $idUsuario = (int)$_SESSION['usuario_id'];
        $esCoordinador = noti_esCoordinador();

        try {
            if ($esCoordinador) {
                // Coordinador ve: (sus notificaciones) OR (CAMBIO_DATOS)
                $where = "(id_usuario = :uid OR tipo = 'CAMBIO_DATOS')";
                $params = [':uid' => $idUsuario];
            } else {
                // Usuario normal ve: solo las suyas y excluye CAMBIO_DATOS
                $where = "id_usuario = :uid AND tipo != 'CAMBIO_DATOS'";
                $params = [':uid' => $idUsuario];
            }

            $sqlTotal = "SELECT COUNT(*) FROM notificaciones WHERE $where";
            $stmtT = $conn->prepare($sqlTotal);
            $stmtT->execute($params);
            $total = (int)$stmtT->fetchColumn();

            $sqlNoLeidas = "SELECT COUNT(*) FROM notificaciones WHERE $where AND leida = 0";
            $stmtN = $conn->prepare($sqlNoLeidas);
            $stmtN->execute($params);
            $noLeidas = (int)$stmtN->fetchColumn();

            return [
                'total' => $total,
                'no_leidas' => $noLeidas
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'no_leidas' => 0];
        }
    }

    public static function obtenerNotificaciones($usuarioId = null, int $limit = 5): array
    {
        global $conn;

        if (!isset($_SESSION['usuario_id'])) {
            return [];
        }

        $idUsuario = (int)$_SESSION['usuario_id'];
        $esCoordinador = noti_esCoordinador();

        $pk     = noti_pk($conn);
        $colF   = noti_col_fecha($conn);
        $colT   = noti_col_titulo($conn);
        $colMsg = noti_col_mensaje($conn);

        $colRefId   = noti_col_referencia_id($conn);
        $colRefTipo = noti_col_referencia_tipo($conn);

        $exprNombre = usuarios_expr_nombre($conn, 'u');

        try {
            if ($esCoordinador) {
                $where = "(n.id_usuario = :uid OR n.tipo = 'CAMBIO_DATOS')";
                $params = [':uid' => $idUsuario];
            } else {
                $where = "n.id_usuario = :uid AND n.tipo != 'CAMBIO_DATOS'";
                $params = [':uid' => $idUsuario];
            }

            $orderBy = $colF !== '' ? "ORDER BY n.{$colF} DESC" : "ORDER BY n.{$pk} DESC";

            // ORIGEN (solicitante) solo si referencia_id existe
            $selectOrigen = "'Sistema' AS usuario_origen_nombre";
            $joinOrigen   = "";

            if ($colRefId !== '') {
                if ($colRefTipo === '') {
                    $selectOrigen = "
                        CASE 
                            WHEN n.{$colRefId} IS NOT NULL AND n.{$colRefId} > 0
                            THEN NULLIF($exprNombre,'')
                            ELSE 'Sistema'
                        END AS usuario_origen_nombre
                    ";
                    $joinOrigen = "";
                    $joinOrigen = "
                        LEFT JOIN usuarios u 
                          ON u.id_usuario = n.{$colRefId}
                    ";
                } else {
                    $selectOrigen = "
                        CASE 
                            WHEN LOWER(COALESCE(n.{$colRefTipo},'')) = 'usuario'
                            THEN NULLIF($exprNombre,'')
                            ELSE 'Sistema'
                        END AS usuario_origen_nombre
                    ";
                    $joinOrigen = "
                        LEFT JOIN usuarios u 
                          ON u.id_usuario = n.{$colRefId}
                         AND LOWER(COALESCE(n.{$colRefTipo},'')) = 'usuario'
                    ";
                }
            }

            $sql = "
                SELECT 
                    n.*,
                    $selectOrigen
                FROM notificaciones n
                $joinOrigen
                WHERE $where
                $orderBy
                LIMIT :lim
            ";

            $stmt = $conn->prepare($sql);

            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            }
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);

            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $resultado = [];

            foreach ($rows as $r) {
                $tipo = $r['tipo'] ?? 'INFO';
                $map  = noti_map_tipo((string)$tipo);

                $fecha = '';
                if ($colF !== '' && !empty($r[$colF])) {
                    $fecha = $r[$colF];
                } else {
                    $fecha = date('Y-m-d H:i:s');
                }

                $nombreOrigen = trim((string)($r['usuario_origen_nombre'] ?? 'Sistema'));
                if ($nombreOrigen === '' || strtolower($nombreOrigen) === 'null') {
                    $nombreOrigen = 'Sistema';
                }

                $idNotif = $r[$pk] ?? null;
                $leidaInt = (int)($r['leida'] ?? 0);
                $msgText = (string)($r[$colMsg] ?? '');

                /**
                 * ✅ FIX COMPATIBILIDAD FRONTEND:
                 * - devolvemos campos "viejos" y "nuevos"
                 *   para que el JS del header no falle.
                 */
                $resultado[] = [
                    // ✅ IDs compatibles
                    'id'              => $idNotif,
                    'id_notificacion' => $idNotif,
                    'notificacion_id' => $idNotif,

                    // ✅ Destino
                    'usuario_id'      => $r['id_usuario'] ?? null,

                    // ✅ Nombre origen (quién envió / sistema)
                    'usuario_nombre'  => $nombreOrigen,

                    // ✅ Contenido
                    'titulo'          => $r[$colT] ?? 'Notificación',

                    // ✅ mensaje/descripcion compat
                    'descripcion'     => $msgText,
                    'mensaje'         => $msgText,

                    // ✅ leido/leida compat
                    'leido'           => ($leidaInt === 1),
                    'leida'           => $leidaInt,

                    'fecha'           => $fecha,
                    'icono'           => $map['icono'],
                    'color'           => $map['color'],

                    // ✅ Tipo para que tu JS pueda filtrar
                    'tipo'            => $tipo,
                ];
            }

            return $resultado;

        } catch (\Exception $e) {
            return [];
        }
    }

    public static function eliminarTodasBD(): int
    {
        global $conn;

        if (!isset($_SESSION['usuario_id'])) return 0;

        $uid = (int)$_SESSION['usuario_id'];
        $esCoordinador = noti_esCoordinador();

        try {
            if ($esCoordinador) {
                $stmt = $conn->prepare("
                    DELETE FROM notificaciones
                    WHERE id_usuario = ?
                       OR tipo = 'CAMBIO_DATOS'
                ");
                $stmt->execute([$uid]);
            } else {
                $stmt = $conn->prepare("
                    DELETE FROM notificaciones
                    WHERE id_usuario = ?
                      AND tipo != 'CAMBIO_DATOS'
                ");
                $stmt->execute([$uid]);
            }

            return (int)$stmt->rowCount();

        } catch (\Exception $e) {
            return 0;
        }
    }

    // Compat
    public static function eliminarTodas() {
        $_SESSION['notificaciones'] = [];
    }
}
   
