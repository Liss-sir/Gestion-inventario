<?php
// src/utils/permisos_helper.php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/**
 * ✅ Normaliza claves tipo cargo/rol funcional:
 * - lowercase
 * - trim
 * - quita tildes
 * - espacios => _
 * - deja formato: encargado_inventario
 */
function permisos_norm_key($value) {
  $s = strtolower(trim((string)$value));

  // Quitar tildes
  if (function_exists("iconv")) {
    $tmp = @iconv("UTF-8", "ASCII//TRANSLIT", $s);
    if ($tmp !== false) $s = $tmp;
  }

  // espacios -> _
  $s = preg_replace('/\s+/', '_', $s);
  $s = preg_replace('/_+/', '_', $s);
  $s = preg_replace('/[^a-z0-9_]/', '', $s);

  return $s;
}

/**
 * ✅ Corrige alias comunes de cargos/roles funcionales
 * para que siempre coincidan con las llaves definidas en permisos.php
 */
function permisos_resolver_alias($value) {
  $k = permisos_norm_key($value);

  $alias = [
    // ✅ CARGOS (FIX)
    "sub_coordinador" => "subcoordinador",
    "coordinador_academico" => "coordinador",
    "coord" => "coordinador",

    // Roles funcionales comunes
    "encargado_de_inventario" => "encargado_inventario",
    "encargado_de_bodega"     => "encargado_bodega",
    "encargado_de_subbodega"  => "encargado_subbodega",
  ];

  return $alias[$k] ?? $k;
}

/**
 * ✅ Normaliza permisos tipo: "Movimientos.Gestionar"
 * -> "movimientos.gestionar"
 */
function permisos_norm_permiso($permiso) {
  $p = strtolower(trim((string)$permiso));
  $p = preg_replace('/\s+/', '', $p);
  return $p;
}

function permisos_getCargo() {
  return $_SESSION["usuario_cargo"] ?? $_SESSION["cargo"] ?? "";
}

/**
 * ✅ Obtiene rol(es) funcional(es) desde sesión
 * ✅ Soporta string o array:
 * - $_SESSION['rol_funcional'] = "encargado_bodega"
 * - $_SESSION['roles_funcionales'] = ["encargado_bodega","otro"]
 */
function permisos_getRolesFuncionales() {

  // ✅ Si tienes array
  if (!empty($_SESSION["roles_funcionales"]) && is_array($_SESSION["roles_funcionales"])) {
    return $_SESSION["roles_funcionales"];
  }

  // ✅ Si tienes un solo rol funcional (string)
  if (!empty($_SESSION["usuario_rol_funcional"])) return [$_SESSION["usuario_rol_funcional"]];
  if (!empty($_SESSION["rol_funcional"])) return [$_SESSION["rol_funcional"]];
  if (!empty($_SESSION["rol_funcional_nombre"])) return [$_SESSION["rol_funcional_nombre"]];

  return [];
}

/**
 * ✅ Carga el mapa de permisos desde src/config/permisos.php
 * (cacheado en memoria para no requerirlo mil veces)
 */
function permisos_getMapaPermisos() {
  static $cache = null;

  if ($cache !== null) return $cache;

  $path = __DIR__ . "/../config/permisos.php";

  if (!file_exists($path)) {
    $cache = [];
    return $cache;
  }

  $map = require $path;

  // Normalizar llaves del mapa
  $norm = [];
  if (is_array($map)) {
    foreach ($map as $roleKey => $perms) {
      $rk = permisos_norm_key($roleKey);
      $norm[$rk] = is_array($perms) ? $perms : [];
    }
  }

  $cache = $norm;
  return $cache;
}

/**
 * ✅ Obtiene permisos del usuario combinando:
 * - cargo (coordinador, aprendiz, etc.)
 * - rol funcional (encargado_inventario, encargado_bodega, etc.)
 *
 * 🔥 Aquí SÍ se "SUMAN" como dijiste.
 */
function permisos_getPermisosUsuario() {
  static $cache = null;

  // Cache por request
  if ($cache !== null) return $cache;

  $map = permisos_getMapaPermisos();

  $cargo = permisos_resolver_alias(permisos_getCargo());

  // ✅ roles funcionales (1 o varios)
  $rolesFuncionales = permisos_getRolesFuncionales();
  $rolesFuncionales = array_map("permisos_resolver_alias", $rolesFuncionales);

  $perms = [];

  // ✅ Cargo base
  if (!empty($cargo) && isset($map[$cargo])) {
    $perms = array_merge($perms, $map[$cargo]);
  }

  // ✅ Roles funcionales se SUMAN
  if (!empty($rolesFuncionales)) {
    foreach ($rolesFuncionales as $rolFunc) {
      if (!empty($rolFunc) && isset($map[$rolFunc])) {
        $perms = array_merge($perms, $map[$rolFunc]);
      }
    }
  }

  // Normalizar permisos
  $perms = array_map("permisos_norm_permiso", $perms);

  // Únicos
  $perms = array_values(array_unique($perms));

  $cache = $perms;
  return $cache;
}

/**
 * ✅ Verifica permiso exacto
 * ✅ Si tiene "modulo.gestionar" entonces permite todo el modulo.*
 */
function permisos_tienePermiso($permiso) {
  $permiso = permisos_norm_permiso($permiso);
  if ($permiso === "") return false;

  $permsUsuario = permisos_getPermisosUsuario();

  // ✅ Exact match
  if (in_array($permiso, $permsUsuario, true)) return true;

  // ✅ Soporte master: "modulo.gestionar" habilita todo del módulo
  $parts = explode(".", $permiso);
  $modulo = $parts[0] ?? "";
  if ($modulo !== "") {
    $master = $modulo . ".gestionar";
    if (in_array($master, $permsUsuario, true)) return true;
  }

  return false;
}

/**
 * ✅ Helper PRO: redirección segura
 * - Si no puede mandar headers, usa JS fallback
 */
function permisos_redirect($url) {
  if (!headers_sent()) {
    header("Location: " . $url);
    exit;
  }

  echo "<script>window.location.href=" . json_encode($url) . ";</script>";
  echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url) . "'></noscript>";
  exit;
}

/**
 * ✅ Helper PRO: arma la URL con coll si existe
 */
function permisos_buildPageUrl($page) {
  $page = trim((string)$page);
  if ($page === "") $page = "dashboard";

  // respeta tu sistema de ?page=xxxx
  $url = "index.php?page=" . urlencode($page);

  // ✅ si existe coll, lo preservamos
  if (isset($_GET["coll"])) {
    $url .= "&coll=" . urlencode((string)$_GET["coll"]);
  }

  return $url;
}

// ✅ Compatibilidad con vistas antiguas que usan hasPermiso()
function hasPermiso($permiso) {
  return permisos_tienePermiso($permiso);
}


/**
 * ✅ Sidebar: puede acceder al MÓDULO si tiene algún permiso que empiece por "modulo."
 * Ej: materiales.* / materiales.gestionar / materiales.crear
 *
 * ✅ FIX:
 * - Dashboard NO será visible para Aprendiz (tu pedido)
 */
function permisos_puedeAccederModulo($modulo) {
  $modulo = permisos_norm_key($modulo);
  if ($modulo === "") return false;

  // ✅ Dashboard visible para todos EXCEPTO aprendiz
  if ($modulo === "dashboard") {
    $cargoActual = permisos_resolver_alias(permisos_getCargo());
    if ($cargoActual === "aprendiz") return false;
    return true;
  }

  $permsUsuario = permisos_getPermisosUsuario();
  $prefix = $modulo . ".";

  foreach ($permsUsuario as $p) {
    if (strpos($p, $prefix) === 0) {
      return true;
    }
  }

  return false;
}

/**
 * ✅ Protege una página/módulo (router o vista)
 *
 * ✅ BLOQUEO PRO:
 * - Si Aprendiz intenta entrar a Dashboard => REDIRECCIÓN automática a Solicitudes
 *   (en vez de mostrar "No autorizado")
 */
function permisos_protegerModulo($modulo) {

  // ✅ BLOQUEO PRO: Aprendiz no entra al dashboard (redirige)
  $cargoActual = permisos_resolver_alias(permisos_getCargo());
  $moduloNorm = permisos_norm_key($modulo);

  if ($moduloNorm === "dashboard" && $cargoActual === "aprendiz") {
    // ✅ redirigir a solicitudes
    $url = permisos_buildPageUrl("solicitudes");
    permisos_redirect($url);
    return;
  }

  // ✅ Validación normal
  if (!permisos_puedeAccederModulo($modulo)) {
    echo "<h2 style='padding:20px; font-family:sans-serif;'>No autorizado</h2>";
    echo "<p style='padding:0 20px; font-family:sans-serif; color:#555;'>No tienes permisos para acceder a este módulo.</p>";
    exit;
  }
}

/**
 * ✅ Compatibilidad con tu sistema actual:
 * requirePermiso("bodegas.gestionar")
 */
function requirePermiso($permiso) {
  $permiso = permisos_norm_permiso($permiso);
  if ($permiso === "") {
    permisos_protegerModulo("dashboard");
    return;
  }

  // Si tienes el permiso -> OK
  if (permisos_tienePermiso($permiso)) return;

  // Si no, bloquea
  echo "<h2 style='padding:20px; font-family:sans-serif;'>No autorizado</h2>";
  echo "<p style='padding:0 20px; font-family:sans-serif; color:#555;'>No tienes permiso para esta acción: <b>{$permiso}</b></p>";
  exit;
}

function canPermiso($permiso) {
  return permisos_tienePermiso($permiso);
}
