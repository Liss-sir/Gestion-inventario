<?php
// src/controllers/historial_controller.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function json_response($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ UNA SOLA RUTA (como tú la usas y te funciona)
require_once __DIR__ . '/../../Config/database.php';

// (Opcional) Por compatibilidad. Este controller no depende del modelo.
require_once __DIR__ . '/../models/historial.php';

// ✅ Tu config crea $conn (PDO)
if (!isset($conn) || !($conn instanceof PDO)) {
    json_response([
        "ok" => false,
        "message" => "Se cargó database.php pero NO existe una conexión PDO válida en \$conn."
    ], 500);
}

$db = $conn;

// ==============================
// Helpers DB introspection
// ==============================
function table_exists(PDO $db, $table) {
    try {
        $st = $db->prepare("SHOW TABLES LIKE :t");
        $st->bindValue(":t", $table, PDO::PARAM_STR);
        $st->execute();
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function get_table_columns(PDO $db, $table) {
    try {
        $st = $db->prepare("SHOW COLUMNS FROM `$table`");
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $r["Field"], $rows);
    } catch (Throwable $e) {
        return [];
    }
}

function pick_col($cols, $candidates) {
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

// ==============================
// Helpers JSON (old_values / new_values)
// ==============================
function safe_json_decode_assoc($txt) {
    if ($txt === null) return null;
    $raw = trim((string)$txt);
    if ($raw === "" || $raw === "null") return null;

    // A veces vienen comillas escapadas o texto no-JSON
    // Intentamos parse directo
    $data = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) return $data;

    // Intento extra: si hay basura alrededor, recortar desde { hasta }
    $start = strpos($raw, "{");
    $end   = strrpos($raw, "}");
    if ($start !== false && $end !== false && $end > $start) {
        $slice = substr($raw, $start, $end - $start + 1);
        $data2 = json_decode($slice, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data2)) return $data2;
    }

    return null;
}

function normalize_val_for_text($v) {
    if ($v === null) return "";
    if (is_bool($v)) return $v ? "true" : "false";
    if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
    $s = trim((string)$v);
    // limita texto enorme
    if (mb_strlen($s) > 80) $s = mb_substr($s, 0, 77) . "…";
    return $s;
}

function find_first_key($arr, $candidates) {
    if (!is_array($arr)) return null;
    foreach ($candidates as $k) {
        if (array_key_exists($k, $arr) && $arr[$k] !== null && trim((string)$arr[$k]) !== "") {
            return $k;
        }
    }
    return null;
}

// ==============================
// Helpers filtros (UI -> DB)
// ==============================
function map_modulo_ui_to_db($moduloUi) {
    $map = [
        "Movimientos" => "movimientos",
        "Solicitudes" => "solicitudes",
        "Materiales"  => "materiales",
        "Bodegas"     => "bodegas",
        "Usuarios"    => "usuarios",
        "Programas"   => "programas",
        "Fichas"      => "fichas",
        "RAEs"        => "raes",
        "Evidencias"  => "evidencias",
        "Reportes"    => "reportes",
        "Historial"   => "audit_log",
    ];
    return $map[$moduloUi] ?? "";
}

function map_accion_ui_to_db_accion($accionUi) {
    $map = [
        "Creacion" => "INSERT",
        "Edicion"  => "UPDATE",
        "Eliminar" => "DELETE",
    ];
    return $map[$accionUi] ?? "";
}

function build_detalle_filter_sql($accionUi, &$params) {
    $accionUi = trim((string)$accionUi);
    if ($accionUi === "") return "";

    $starts = [
        "Entrada"        => ["Entrada:", "[Entrada]", "Entrada|"],
        "Salida"         => ["Salida:", "[Salida]", "Salida|"],
        "Devolucion"     => ["Devolución:", "Devolucion:", "[Devolución]", "[Devolucion]", "Devolución|", "Devolucion|"],
        "Aprobacion"     => ["Aprobación:", "Aprobacion:", "[Aprobación]", "[Aprobacion]", "Aprobación|", "Aprobacion|"],
        "Rechazo"        => ["Rechazo:", "[Rechazo]", "Rechazo|"],
        "Desactivacion"  => ["Desactivación:", "Desactivacion:", "[Desactivación]", "[Desactivacion]", "Desactivación|", "Desactivacion|"],
    ];

    if (!isset($starts[$accionUi])) return "";

    $likes = [];
    $i = 0;
    foreach ($starts[$accionUi] as $prefix) {
        $key = ":det_" . $accionUi . "_" . $i;
        $likes[] = "a.detalle LIKE {$key}";
        $params[$key] = $prefix . "%";
        $i++;
    }

    return " AND (" . implode(" OR ", $likes) . ") ";
}

// ==============================
// Lookups con cache (solo SELECT)
// ==============================
function lookup_name_cached(PDO $db, $table, $id, $pkCandidates, $nameCandidates, &$cache) {
    $id = trim((string)$id);
    if ($id === "") return null;

    $ckey = $table . ":" . $id;
    if (array_key_exists($ckey, $cache)) return $cache[$ckey];

    if (!table_exists($db, $table)) {
        $cache[$ckey] = null;
        return null;
    }

    $cols = get_table_columns($db, $table);
    $pk   = pick_col($cols, $pkCandidates);
    $name = pick_col($cols, $nameCandidates);

    if (!$pk || !$name) {
        $cache[$ckey] = null;
        return null;
    }

    try {
        $st = $db->prepare("SELECT `$name` AS nombre FROM `$table` WHERE `$pk` = :id LIMIT 1");
        $st->bindValue(":id", $id, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $cache[$ckey] = $row["nombre"] ?? null;
        return $cache[$ckey];
    } catch (Throwable $e) {
        $cache[$ckey] = null;
        return null;
    }
}

// ==============================
// Descripción avanzada (sin tocar DB)
// ==============================
function build_changes_text($oldArr, $newArr, $max = 3) {
    if (!is_array($oldArr) || !is_array($newArr)) return "";

    $changes = [];
    $keys = array_unique(array_merge(array_keys($oldArr), array_keys($newArr)));

    foreach ($keys as $k) {
        $ov = array_key_exists($k, $oldArr) ? normalize_val_for_text($oldArr[$k]) : "";
        $nv = array_key_exists($k, $newArr) ? normalize_val_for_text($newArr[$k]) : "";

        // ignorar cambios vacíos iguales
        if ($ov === $nv) continue;

        // ignora campos típicos “ruido”
        if (preg_match('/^(updated_at|fecha_actualizacion|fecha_hora|created_at|fecha_creacion)$/i', $k)) continue;

        $label = str_replace("_", " ", (string)$k);
        $changes[] = "{$label}: \"{$ov}\" → \"{$nv}\"";
    }

    if (count($changes) === 0) return "";

    $shown = array_slice($changes, 0, $max);
    $rest = count($changes) - count($shown);

    $txt = implode(" • ", $shown);
    if ($rest > 0) $txt .= " • y {$rest} más";
    return $txt;
}

function build_insert_highlights($arr, $preferKeys, $max = 3) {
    if (!is_array($arr)) return "";

    $pairs = [];
    foreach ($preferKeys as $k) {
        if (array_key_exists($k, $arr)) {
            $v = normalize_val_for_text($arr[$k]);
            if ($v !== "") {
                $label = str_replace("_", " ", $k);
                $pairs[] = "{$label}: {$v}";
            }
        }
        if (count($pairs) >= $max) break;
    }

    if (count($pairs) === 0) {
        // fallback: primeras 2 keys útiles
        $i = 0;
        foreach ($arr as $k => $v) {
            if ($i >= $max) break;
            if (preg_match('/^(id_|password|clave|token)$/i', (string)$k)) continue;
            $vv = normalize_val_for_text($v);
            if ($vv === "") continue;
            $label = str_replace("_", " ", (string)$k);
            $pairs[] = "{$label}: {$vv}";
            $i++;
        }
    }

    return count($pairs) ? implode(" • ", $pairs) : "";
}

function build_movimiento_from_json(PDO $db, $pk, $accion, $oldArr, $newArr, &$cache) {
    // buscamos datos en new_values / old_values (según acción)
    $src = is_array($newArr) && count($newArr) ? $newArr : $oldArr;
    if (!is_array($src)) return null;

    $kTipo = find_first_key($src, ["tipo_movimiento","tipo","movimiento_tipo","tipo_accion"]);
    $kCant = find_first_key($src, ["cantidad","cantidad_movimiento","qty","cantidad_total"]);
    $kMat  = find_first_key($src, ["id_material","material_id","material"]);
    $kBod  = find_first_key($src, ["id_bodega","bodega_id","bodega"]);
    $kFicha= find_first_key($src, ["id_ficha","ficha_id","ficha"]);

    $tipo = $kTipo ? trim((string)$src[$kTipo]) : "";
    $cant = $kCant ? trim((string)$src[$kCant]) : "";
    $idMat = $kMat ? trim((string)$src[$kMat]) : "";
    $idBod = $kBod ? trim((string)$src[$kBod]) : "";
    $idFic = $kFicha ? trim((string)$src[$kFicha]) : "";

    $tipoUpper = mb_strtoupper($tipo);
    if ($tipoUpper && strpos($tipoUpper, "ENTR") !== false) $tipo = "Entrada";
    if ($tipoUpper && strpos($tipoUpper, "SAL")  !== false) $tipo = "Salida";

    $matNombre = $idMat ? lookup_name_cached($db, "materiales", $idMat, ["id_material","material_id","id"], ["nombre","nombre_material","material","descripcion"], $cache) : null;
    $bodNombre = $idBod ? lookup_name_cached($db, "bodegas", $idBod, ["id_bodega","bodega_id","id"], ["nombre","nombre_bodega","bodega"], $cache) : null;

    // ficha: puede que no exista "numero_ficha", igual mostramos id
    $fichaNumero = null;
    if ($idFic && table_exists($db, "fichas")) {
        $colsF = get_table_columns($db, "fichas");
        $fPk   = pick_col($colsF, ["id_ficha","ficha_id","id"]);
        $fNum  = pick_col($colsF, ["numero_ficha","num_ficha","ficha","numero"]);
        if ($fPk && $fNum) {
            try {
                $st = $db->prepare("SELECT `$fNum` AS num FROM fichas WHERE `$fPk` = :id LIMIT 1");
                $st->bindValue(":id", $idFic, PDO::PARAM_STR);
                $st->execute();
                $fichaNumero = $st->fetch(PDO::FETCH_ASSOC)["num"] ?? null;
            } catch (Throwable $e) {}
        }
    }

    $matTxt = $matNombre ?: ($idMat ? "Material #{$idMat}" : "Material");
    $bodTxt = $bodNombre ? "en {$bodNombre}" : ($idBod ? "en Bodega #{$idBod}" : "");
    $ficTxt = $idFic ? ("para Ficha " . ($fichaNumero ?: $idFic)) : "";

    if ($tipo && $cant) return trim("{$tipo} de {$cant} {$matTxt} {$bodTxt} {$ficTxt}");
    if ($tipo)          return trim("{$tipo} de {$matTxt} {$bodTxt} {$ficTxt}");
    if ($cant)          return trim("Movimiento de {$cant} {$matTxt} {$bodTxt} {$ficTxt}");
    return null;
}

function enrich_items_with_descripcion(PDO $db, &$items) {
    $cache = []; // cache de lookups

    foreach ($items as &$it) {
        $tabla  = strtolower(trim((string)($it["tabla_nombre"] ?? "")));
        $accion = strtoupper(trim((string)($it["accion"] ?? "")));
        $pk     = trim((string)($it["pk_valor"] ?? ""));
        $det    = trim((string)($it["detalle"] ?? ""));

        $oldArr = safe_json_decode_assoc($it["old_values"] ?? null);
        $newArr = safe_json_decode_assoc($it["new_values"] ?? null);

        // ✅ Si hay detalle, lo usamos como base, PERO lo enriquecemos
        if ($det !== "") {
            $extraParts = [];

            // para movimientos: intenta agregar info (material/cantidad/bodega/ficha) si no está
            if ($tabla === "movimientos") {
                $mov = build_movimiento_from_json($db, $pk, $accion, $oldArr, $newArr, $cache);
                if ($mov && stripos($det, "Entrada") === false && stripos($det, "Salida") === false) {
                    $extraParts[] = $mov;
                }
            }

            // si es UPDATE y hay JSON, agrega cambios
            if ($accion === "UPDATE") {
                $changes = build_changes_text($oldArr, $newArr, 3);
                if ($changes) $extraParts[] = $changes;
            }

            // si es INSERT y hay JSON, agrega highlights
            if ($accion === "INSERT") {
                $high = build_insert_highlights($newArr, [
                    "nombre","nombre_material","nombre_bodega","nombre_completo","correo",
                    "numero_ficha","ficha","id_ficha","cantidad","tipo_movimiento","estado"
                ], 3);
                if ($high) $extraParts[] = $high;
            }

            if (count($extraParts)) {
                $it["descripcion"] = $det . " — " . implode(" | ", $extraParts);
            } else {
                $it["descripcion"] = $det;
            }

            continue;
        }

        // ✅ Si NO hay detalle: generar descripción completa
        if ($tabla === "movimientos") {
            $mov = build_movimiento_from_json($db, $pk, $accion, $oldArr, $newArr, $cache);
            if ($mov) {
                // si además es UPDATE, agrega cambios
                if ($accion === "UPDATE") {
                    $changes = build_changes_text($oldArr, $newArr, 2);
                    $it["descripcion"] = $changes ? ($mov . " — " . $changes) : $mov;
                } else {
                    $it["descripcion"] = $mov;
                }
                continue;
            }
        }

        $mod = $tabla ? ucfirst($tabla) : "Módulo";

        if ($accion === "INSERT") {
            $high = build_insert_highlights($newArr, [
                "nombre","nombre_material","nombre_bodega","nombre_programa","nombre_completo","correo",
                "numero_ficha","ficha","cantidad","estado","tipo"
            ], 3);
            $it["descripcion"] = "Creó registro en {$mod}" . ($pk ? " (#{$pk})" : "");
            if ($high) $it["descripcion"] .= " — " . $high;
            continue;
        }

        if ($accion === "UPDATE") {
            $changes = build_changes_text($oldArr, $newArr, 3);
            $it["descripcion"] = "Actualizó {$mod}" . ($pk ? " (#{$pk})" : "");
            if ($changes) $it["descripcion"] .= " — " . $changes;
            continue;
        }

        if ($accion === "DELETE") {
            $high = build_insert_highlights($oldArr, [
                "nombre","nombre_material","nombre_bodega","nombre_programa","nombre_completo","correo",
                "numero_ficha","ficha","cantidad","estado","tipo"
            ], 2);
            $it["descripcion"] = "Eliminó registro en {$mod}" . ($pk ? " (#{$pk})" : "");
            if ($high) $it["descripcion"] .= " — " . $high;
            continue;
        }

        // Fallback
        $it["descripcion"] = "Acción en {$mod}" . ($pk ? " (#{$pk})" : "");
    }
}

// ==============================
// MAIN
// ==============================
try {
    $AUDIT_TABLE = "audit_log";

    if (!table_exists($db, $AUDIT_TABLE)) {
        json_response([
            "ok" => false,
            "message" => "La tabla '{$AUDIT_TABLE}' no existe en la base de datos."
        ], 500);
    }

    // Acepta action o accion
    $action = $_GET["action"] ?? ($_GET["accion"] ?? "listar");

    // ==============================
    // LISTAR
    // ==============================
    if ($action === "listar") {
        $q         = trim($_GET["q"] ?? "");
        $moduloUi  = trim($_GET["modulo"] ?? "");
        $accionUi  = trim($_GET["accion"] ?? "");
        $page      = max(1, (int)($_GET["page"] ?? 1));
        $limit     = min(100, max(5, (int)($_GET["limit"] ?? 20)));
        $offset    = ($page - 1) * $limit;

        $moduloDb = ($moduloUi !== "") ? map_modulo_ui_to_db($moduloUi) : "";
        $accionDb = ($accionUi !== "") ? map_accion_ui_to_db_accion($accionUi) : "";

        // ----- Usuarios JOIN seguro -----
        $joinUsuarios = "";
        $usuarioNombreExpr = "'Sistema'";
        $usuarioCargoExpr  = "''";

        if (table_exists($db, "usuarios")) {
            $uCols = get_table_columns($db, "usuarios");

            $uPk = pick_col($uCols, ["id_usuario","usuario_id","id"]);
            $uNombreCompleto = pick_col($uCols, ["nombre_completo"]);
            $uNombre = pick_col($uCols, ["nombre","nombres","primer_nombre"]);
            $uApellido = pick_col($uCols, ["apellido","apellidos","segundo_apellido"]);
            $uCargo = pick_col($uCols, ["cargo","rol","tipo_rol"]);

            if ($uPk) {
                $joinUsuarios = " LEFT JOIN usuarios u ON u.`{$uPk}` = a.usuario_id ";

                if ($uNombreCompleto) {
                    $usuarioNombreExpr = "COALESCE(u.`{$uNombreCompleto}`, '')";
                } elseif ($uNombre && $uApellido) {
                    $usuarioNombreExpr = "TRIM(CONCAT(COALESCE(u.`{$uNombre}`, ''), ' ', COALESCE(u.`{$uApellido}`, '')))";
                } elseif ($uNombre) {
                    $usuarioNombreExpr = "COALESCE(u.`{$uNombre}`, '')";
                } else {
                    $usuarioNombreExpr = "CONCAT('Usuario #', COALESCE(a.usuario_id, ''))";
                }

                $usuarioCargoExpr = $uCargo ? "COALESCE(u.`{$uCargo}`, '')" : "''";
            }
        }

        $sql = "
            SELECT
                a.id_audit,
                a.tabla_nombre,
                a.accion,
                a.pk_valor,
                a.usuario_id,
                a.old_values,
                a.new_values,
                a.detalle,
                a.fecha_hora,
                {$usuarioNombreExpr} AS usuario_nombre,
                {$usuarioCargoExpr} AS usuario_cargo
            FROM {$AUDIT_TABLE} a
            {$joinUsuarios}
            WHERE 1=1
        ";

        $params = [];

        if ($q !== "") {
            $sql .= " AND (
                a.tabla_nombre LIKE :search
                OR a.detalle LIKE :search
                OR a.pk_valor LIKE :search
                OR {$usuarioNombreExpr} LIKE :search
            ) ";
            $params[":search"] = "%" . $q . "%";
        }

        if ($moduloDb !== "") {
            $sql .= " AND a.tabla_nombre = :modulo ";
            $params[":modulo"] = $moduloDb;
        }

        if ($accionDb !== "") {
            $sql .= " AND a.accion = :accion ";
            $params[":accion"] = $accionDb;
        } else {
            if ($accionUi !== "") {
                $sql .= build_detalle_filter_sql($accionUi, $params);
            }
        }

        $sql .= " ORDER BY a.fecha_hora DESC LIMIT :limit OFFSET :offset ";

        $stmt = $db->prepare($sql);

        foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        $stmt->bindValue(":limit", (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count
        $sqlCount = "SELECT COUNT(*) AS total FROM {$AUDIT_TABLE} a {$joinUsuarios} WHERE 1=1";
        $paramsC = [];

        if ($q !== "") {
            $sqlCount .= " AND (
                a.tabla_nombre LIKE :search
                OR a.detalle LIKE :search
                OR a.pk_valor LIKE :search
                OR {$usuarioNombreExpr} LIKE :search
            ) ";
            $paramsC[":search"] = "%" . $q . "%";
        }

        if ($moduloDb !== "") {
            $sqlCount .= " AND a.tabla_nombre = :modulo ";
            $paramsC[":modulo"] = $moduloDb;
        }

        if ($accionDb !== "") {
            $sqlCount .= " AND a.accion = :accion ";
            $paramsC[":accion"] = $accionDb;
        } else {
            if ($accionUi !== "") {
                $sqlCount .= build_detalle_filter_sql($accionUi, $paramsC);
            }
        }

        $stmtC = $db->prepare($sqlCount);
        foreach ($paramsC as $k => $v) $stmtC->bindValue($k, $v, PDO::PARAM_STR);
        $stmtC->execute();
        $total = (int)($stmtC->fetch(PDO::FETCH_ASSOC)["total"] ?? 0);

        // ✅ Descripción más detallada (sin tocar DB)
        enrich_items_with_descripcion($db, $items);

        json_response([
            "ok" => true,
            "total" => $total,
            "page" => $page,
            "limit" => $limit,
            "items" => $items
        ]);
    }

    // ==============================
    // CONTAR (chip)
    // ==============================
    if ($action === "contar") {
        $q        = trim($_GET["q"] ?? "");
        $moduloUi = trim($_GET["modulo"] ?? "");
        $accionUi = trim($_GET["accion"] ?? "");

        $moduloDb = ($moduloUi !== "") ? map_modulo_ui_to_db($moduloUi) : "";
        $accionDb = ($accionUi !== "") ? map_accion_ui_to_db_accion($accionUi) : "";

        $sql = "SELECT COUNT(*) AS total FROM {$AUDIT_TABLE} a WHERE 1=1";
        $params = [];

        if ($q !== "") {
            $sql .= " AND (
                a.tabla_nombre LIKE :search
                OR a.detalle LIKE :search
                OR a.pk_valor LIKE :search
            ) ";
            $params[":search"] = "%" . $q . "%";
        }

        if ($moduloDb !== "") {
            $sql .= " AND a.tabla_nombre = :modulo ";
            $params[":modulo"] = $moduloDb;
        }

        if ($accionDb !== "") {
            $sql .= " AND a.accion = :accion ";
            $params[":accion"] = $accionDb;
        } else {
            if ($accionUi !== "") {
                $sql .= build_detalle_filter_sql($accionUi, $params);
            }
        }

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        $stmt->execute();
        $total = (int)($stmt->fetch(PDO::FETCH_ASSOC)["total"] ?? 0);

        json_response(["ok" => true, "total" => $total]);
    }

    json_response(["ok" => false, "message" => "Acción no válida."], 400);

} catch (Throwable $e) {
    json_response(["ok" => false, "message" => $e->getMessage()], 500);
}
