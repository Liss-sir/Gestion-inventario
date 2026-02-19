<?php

$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";

// Asegurar que BASE_URL está definido (por si se accede directamente)
if (!defined('BASE_URL')) {
    $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host       = $_SERVER['HTTP_HOST'];
    $script_dir = '/Gestion-inventario/';  // Ajusta según tu ruta
    define('BASE_URL', $protocol . $host . $script_dir);
}

// ✅ FIX: Check if user is instructor (for entrada tab visibility)
require_once __DIR__ . '/../../utils/permisos_helper.php';
$cargoActual = permisos_resolver_alias(permisos_getCargo());
$rolesFuncionales = [];
if (!empty($_SESSION['roles_funcionales']) && is_array($_SESSION['roles_funcionales'])) {
  $rolesFuncionales = $_SESSION['roles_funcionales'];
}
if (empty($rolesFuncionales) && !empty($_SESSION['rol_funcional'])) {
  $rolesFuncionales = [$_SESSION['rol_funcional']];
}
$rolesFuncionales = array_map(function($r){ return strtolower(trim((string)$r)); }, $rolesFuncionales);
$rolesFuncPoderosos = ["encargado_inventario", "encargado_bodega", "encargado_subbodega"];
$tieneRolFuncPoderoso = false;
foreach ($rolesFuncionales as $rf) {
  if (in_array($rf, $rolesFuncPoderosos, true)) {
    $tieneRolFuncPoderoso = true;
    break;
  }
}
$esInstructor = ($cargoActual === "instructor" && !$tieneRolFuncPoderoso);

// Obtener ID del usuario de la sesi├│n
$idUsuario = $_SESSION['id_usuario'] ?? 1; // Por defecto 1 si no hay sesi├│n
$movimientos = [];
$movimientosPage = [];
$total = 0;



// Initialize arrays for form dropdowns if not already set
if (!isset($materiales)) $materiales = [];
if (!isset($programas)) $programas = [];
if (!isset($fichas)) $fichas = [];
if (!isset($raes)) $raes = [];
if (!isset($instructores)) $instructores = [];
if (!isset($solicitudes)) $solicitudes = [];



function findNameById($arr, $id)
{
    foreach ($arr as $it) if ((string)$it["id"] === (string)$id) return $it["nombre"];
    return "-";
}
function badgeTipo($tipo)
{
    $tipo = strtolower((string)$tipo);
    if ($tipo === "entrada") return ["Entrada", "bg-[#39A90020] text-slate-700", "arrow-up-from-line"];
    if ($tipo === "salida") return ["Salida", "bg-lime-100 text-lime-700", "arrow-down-up"];
    if ($tipo === "devolucion") return ["Devolución", "bg-[#39A90020] text-slate-700", "rotate-ccw"];
    return [ucfirst($tipo), "bg-gray-100 text-gray-700", "arrow-down-up"];
}

/* =========================
    PAGINATION (10 ROWS)
========================= */
$perPage = 10;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$total = count($movimientos);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;
$movimientosPage = array_slice($movimientos, $offset, $perPage);

$collParam = isset($_GET['coll']) ? '&coll=' . urlencode($_GET['coll']) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="src/assets/css/globals.css">
</head>
<body class="flex flex-col min-h-screen font-sans bg-white text-gray-900 transition-all duration-300">
    <header>
        <?php require_once BASE_PATH . '/src/includes/header.php'; ?>
        <?php require_once BASE_PATH . '/src/includes/sidebar.php'; ?>
    </header>

    <main class="p-3 sm:p-6 transition-all duration-300 flex-grow"
        style="margin-left: <?= isset($_GET['coll']) && $_GET['coll'] == "1" ? '70px' : '260px' ?>;">

<div class="space-y-6 animate-fade-in-up">
<!-- Header -->
      <!-- ================================== -->
      <!-- PAGE HEADER                         -->
      <!-- ================================== -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
                <h1 class="text-2xl font-bold tracking-tight">Movimientos de Material</h1>
                <p class="text-muted-foreground">Historial de entradas, salidas y devoluciones de materiales</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="inline-flex rounded-lg border border-border bg-card shadow-sm overflow-hidden">
                    <button type="button" id="btnVistaTabla"
                        class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 bg-muted text-foreground">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <button type="button" id="btnVistaTarjetas"
                        class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 text-muted-foreground">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <rect x="4" y="4" width="7" height="7" rx="1"></rect>
                            <rect x="13" y="4" width="7" height="7" rx="1"></rect>
                            <rect x="4" y="13" width="7" height="7" rx="1"></rect>
                            <rect x="13" y="13" width="7" height="7" rx="1"></rect>
                        </svg>
                    </button>
                </div>

                <div>
                    <button type="button" onclick="openMovimientoModal()"
                        class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        Agregar Movimiento
                    </button>
                </div>
            </div>
        </div>

        <!-- TARGETS (Entry - Exit - Return) -->
        <?php
        $contEntrada = 0;
        $contSalida = 0;
        $contDevolucion = 0;

        foreach ($movimientos as $mv) {
            $t = strtolower((string)($mv["tipo_movimiento"] ?? ""));
            if ($t === "entrada") $contEntrada++;
            if ($t === "salida") $contSalida++;
            if ($t === "devolucion") $contDevolucion++;
        }
        ?>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-6">

            <!-- ENTRY -->
            <div class="rounded-xl border border-border bg-card p-8 flex flex-col items-center">
                <div class="flex items-start gap-3">
                    <div class="p-3 rounded-2xl bg-gray-100 inline-flex items-center justify-center">
                        <i data-lucide="arrow-up-from-line" class="h-6 w-6 text-[#39A900]"></i>
                    </div>
                    <div class="flex flex-col justify-center">
                        <p id="contadorEntrada" class="text-2xl font-medium text-foreground"><?= (int)$contEntrada ?></p>
                        <span class="text-xs text-muted-foreground">Entrada</span>
                    </div>
                </div>
            </div>

            <!-- EXIT -->
            <div class="rounded-xl border border-border bg-card p-8 flex flex-col items-center">
                <div class="flex items-start gap-3">
                    <div class="p-3 rounded-2xl bg-gray-100 inline-flex items-center justify-center">
                        <i data-lucide="arrow-down-up" class="h-6 w-6 text-[#39A900]"></i>
                    </div>
                    <div class="flex flex-col">
                        <p id="contadorSalida" class="text-2xl font-medium text-foreground"><?= (int)$contSalida ?></p>
                        <span class="text-xs text-muted-foreground">Salida</span>
                    </div>
                </div>
            </div>

            <!-- RETURN -->
            <div class="rounded-xl border border-border bg-card p-8 flex flex-col items-center">
                <div class="flex items-start gap-3">
                    <div class="p-3 rounded-2xl bg-gray-100 inline-flex items-center justify-center">
                        <i data-lucide="rotate-ccw" class="h-6 w-6 text-[#39A900]"></i>
                    </div>
                    <div class="flex flex-col justify-center">
                        <p id="contadorDevolucion" class="text-2xl font-medium text-foreground"><?= (int)$contDevolucion ?></p>
                        <span class="text-xs text-muted-foreground">Devolución</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- filters -->
        <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

            <!-- ­SEARCH (LEFT - QUIETO) -->
            <div class="relative w-full sm:max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                    <i data-lucide="search" class="h-4 w-4"></i>
                </span>

                <input
                    id="buscarFicha"
                    type="text"
                    name="buscar_ficha"
                    placeholder="Buscar por ficha..."
                    class="w-full rounded-lg border border-border bg-background py-2 pl-9 pr-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
            </div>

            <!-- ­FILTERS (RIGHT - JUNTOS) -->
            <div class="flex items-center gap-3 justify-end w-full sm:w-auto">

                <!-- TIPO -->
                <div class="flex items-center gap-2">
                    <i data-lucide="filter" class="h-4 w-4 text-muted-foreground"></i>
                    <div class="relative">
                        <select id="filtroTipo" name="filtro_tipo"
                            class="appearance-none rounded-lg border border-border bg-background py-2 pl-3 pr-8 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                            <option value="">Todos</option>
                            <?php if (!$esInstructor): ?>
                            <option value="entrada">Entradas</option>
                            <?php endif; ?>
                            <option value="salida">Salidas</option>
                            <option value="devolucion">Devoluciones</option>
                        </select>

                        <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-muted-foreground">
                        </span>
                    </div>
                </div>

                <!-- PROGRAMA -->
                <div class="relative w-full sm:w-56">
                    <input
                        id="buscarPrograma"
                        type="text"
                        placeholder="Buscar por programa..."
                        class="w-full sm:max-w-xs rounded-lg border border-border bg-background py-2 px-3 text-sm"
                        />


                    <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-muted-foreground">
                    </span>
                </div>

            </div>
        </div>

        <!-- TABLE -->
        <div id="tableView" class="mt-6 rounded-2xl border border-border bg-card overflow-hidden">
            <div id="tableWrapper" class="overflow-x-auto">
                <table class="min-w-[1300px] w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr class="bg-primary/5 text-xs text-muted-foreground border-b border-border">
                            <th class="px-4 py-3 text-left font-medium">Fecha/Hora</th>
                            <th class="px-4 py-3 text-left font-medium">Tipo</th>
                            <th class="px-4 py-3 text-left font-medium">Materiales</th>
                            <th class="px-4 py-3 text-left font-medium">Cantidad</th>
                            <th class="px-4 py-3 text-left font-medium">Bodega</th>
                            <th class="px-4 py-3 text-left font-medium">Subbodega</th>
                            <th class="px-4 py-3 text-left font-medium">Programa</th>
                            <th class="px-4 py-3 text-left font-medium">Ficha</th>
                            <th class="px-4 py-3 text-left font-medium">RAE</th>
                            <th class="px-4 py-3 text-left font-medium">Instructor</th>
                            <th class="px-4 py-3 text-left font-medium">Observaciones</th>
                            <th class="px-4 py-3 text-left font-medium">Solicitud</th>
                        </tr>
                    </thead>

                    <tbody id="tbodyMovimientos" class="divide-y divide-border">

                    </tbody>

                </table>
            </div>

            <div id="tableEmptyState" class="hidden px-4 py-16 text-center">
                <div class="flex flex-col items-center gap-3">
                    <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center">
                        <i data-lucide="inbox" class="h-6 w-6 text-gray-400"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">No hay movimientos para mostrar</p>
                        <p class="text-xs text-gray-500 mt-1">Cuando se registren movimientos, apareceran aqui.</p>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-border bg-card">
                <p id="contadorTabla" class="text-xs text-muted-foreground"></p>

                <div id="paginationMovimientos" class="flex justify-end gap-2"></div>
            </div>
        </div>

        <!-- GRID VIEW COMPACT -->
        <div id="gridView" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">

        </div>


        <!-- CONFIRMATION MODAL - BODEGA/SUBBODEGA -->
        <div id="confirmBodegaModal" class="fixed inset-0 z-[60] hidden">
            <div class="fixed inset-0 z-0 bg-black/50 backdrop-blur-md" onclick="closeConfirmBodegaModal()"></div>

            <div class="absolute inset-0 z-10 flex items-center justify-center">
            <div class="relative mx-4 w-full max-w-sm rounded-2xl bg-white shadow-xl p-4 sm:p-6">
                <div class="flex items-start justify-between mb-4 gap-2">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Destino de materiales</h3>
                        <p class="text-sm text-gray-500 mt-1">¿Deseas agregar los materiales a una subbodega específica o a la bodega general?</p>
                    </div>
                    <button type="button" onclick="closeConfirmBodegaModal()"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full hover:bg-gray-100">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-end">
                    <button type="button" onclick="selectBodegaDestino('bodega')"
                        class="w-full sm:w-auto px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 border border-border">
                        No, a la bodega
                    </button>
                    <button type="button" onclick="selectBodegaDestino('subbodega')"
                        class="w-full sm:w-auto px-4 py-2 rounded-lg text-sm font-medium text-white bg-secondary hover:opacity-90">
                        Sí, a una subbodega
                    </button>
                </div>
            </div>
            </div>
        </div>

        <!-- MATERIALS VIEW MODAL -->
        <div id="materialesModal" class="fixed inset-0 z-50 hidden">
            <div class="fixed inset-0 z-0 bg-black/50 backdrop-blur-md" onclick="closeMaterialesModal()"></div>

            <div class="absolute inset-0 z-10 flex items-center justify-center">
            <div class="relative mx-4 w-full max-w-xl rounded-2xl bg-white shadow-xl p-4 sm:p-6">
                <div class="flex items-start justify-between mb-4 gap-2">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Materiales del movimiento</h3>
                        <p class="text-sm text-gray-500">Listado de materiales solicitados en este movimiento</p>
                    </div>
                    <button type="button" onclick="closeMaterialesModal()"
                        class="rounded-full p-1 hover:bg-muted">
                        <span class="sr-only">Cerrar</span>
                        <svg
                            class="h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div id="materialesBody" class="space-y-2 max-h-[60vh] overflow-y-auto"></div>
            </div>
            </div>
        </div>

        <!-- REGISTER MOVEMENT MODAL -->
        <div id="movimientoModal" class="fixed inset-0 z-50 hidden">
            <div class="fixed inset-0 z-0 bg-black/40" onclick="closeMovimientoModal()"></div>

            <div class="absolute inset-0 z-10 flex items-center justify-center overflow-y-auto">
            <div class="relative mx-2 sm:mx-4 w-full max-w-4xl rounded-2xl bg-white shadow-xl p-4 sm:p-6 lg:p-8 my-4 sm:my-0">
                <div class="flex items-start justify-between mb-4 gap-2">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Registrar Movimiento</h2>
                        <p class="text-sm text-gray-500">Registre un nuevo movimiento de inventario</p>
                    </div>
                    <button type="button" onclick="closeMovimientoModal()"
                        class="rounded-full p-1 hover:bg-muted">
                        <span class="sr-only">Cerrar</span>
                        <svg
                            class="h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Tabs ( entrada / devolucion) -->
                <div class="mb-6 flex justify-center overflow-x-auto">
                    <div id="tabsMovimiento"
                        class="flex w-full max-w-md min-w-fit items-center rounded-full bg-gray-100 p-1 text-xs sm:text-sm font-medium shadow-inner">

                        <!-- ✅ Hide Entrada tab for instructors -->
                        <?php if (!$esInstructor): ?>
                        <button type="button" data-tipo="entrada"
                            class="tab-mov flex-1 rounded-full py-2 text-center text-gray-600 hover:text-gray-900 transition-all">
                            Entrada
                        </button>
                        <?php endif; ?>

                        <button type="button" data-tipo="devolucion"
                            class="tab-mov flex-1 rounded-full py-2 text-center text-gray-600 hover:text-gray-900 transition-all">
                            Devolución
                        </button>
                    </div>
                </div>

                <!-- FORM -->
                <form id="formMovimiento" class="space-y-5" onsubmit="registrarEntrada(event)">


                    <input type="hidden" id="tipoMovimiento" name="tipo_movimiento" value="entrada">
                    <input type="hidden" name="materiales_json" id="materiales_json">

                    <!-- =====================
                        DATOS DEL MATERIAL
                    ====================== -->
                    <div data-field="material" class="rounded-xl border border-border p-4 bg-gray-50">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-3">
                            Datos del material
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3">

                            <!-- MATERIAL -->
                            <div class="sm:col-span-2">
                                <label class="text-xs sm:text-sm font-medium">Material</label>
                                <select id="material" class="w-full border rounded-lg px-2 sm:px-3 py-2 text-xs sm:text-sm">
                                    <option value="">Seleccione</option>
                                    <?php foreach ($materiales as $m): ?>
                                        <option value="<?= $m["id"] ?>" data-unidad="<?= $m["unidad"] ?>">
                                            <?= $m["nombre"] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- CANTIDAD -->
                            <div>
                                <label class="text-xs sm:text-sm font-medium">Cantidad</label>
                                <input id="cantidad" type="number" min="1" value="1"
                                    class="w-full border rounded-lg px-2 sm:px-3 py-2 text-xs sm:text-sm">
                            </div>

                            <!-- ESTADO -->
                            <div class="sm:col-span-3">
                                <label class="text-xs sm:text-sm font-medium">Estado</label>
                                <select id="estado_material" class="w-full border rounded-lg px-2 sm:px-3 py-2 text-xs sm:text-sm">
                                    <option value="">Seleccione</option>
                                    <option value="bueno">Bueno</option>
                                </select>
                            </div>

                        </div>

                        <button type="button"
                            onclick="agregarMaterial()"
                            class="mt-3 w-full sm:w-auto inline-flex items-center justify-center sm:justify-start gap-2 rounded-lg border px-3 py-2 text-xs sm:text-sm hover:bg-muted">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            Agregar material
                        </button>
                    </div>

                    <!-- =====================
                        BODEGA
                    ====================== -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3" data-field="bodega-section">

                        <div>
                            <label class="text-xs sm:text-sm font-medium">Bodega</label>
                            <select id="bodega" class="w-full border rounded-lg px-2 sm:px-3 py-2 text-xs sm:text-sm">
                                <option value="">Seleccione</option>
                            </select>

                        </div>

                        <div data-field="subbodega-row" class="hidden">
                            <label class="text-xs sm:text-sm font-medium">Subbodega</label>
                            <select id="subbodega" class="w-full border rounded-lg px-2 sm:px-3 py-2 text-xs sm:text-sm">
                                <option value="">Seleccione</option>
                            </select>

                        </div>

                    </div>
                    <!-- =====================
                        DEVOLUCIÓN
                    ====================== -->

                    <div data-field="devolucion-container"
                        class="hidden rounded-xl border border-border bg-gray-50 p-4">

                        <p class="text-xs sm:text-sm font-semibold mb-3">
                            Devolución de Material
                        </p>

                        <!-- Seleccionar solicitud con salida -->
                        <div class="mb-3 relative z-20">
                            <label class="text-xs sm:text-sm font-medium text-gray-700">Solicitud de Salida</label>
                            <select id="solicitud_salida_dev" class="w-full border rounded-lg px-2 sm:px-3 py-2 text-xs sm:text-sm relative z-20">
                                <option value="">Seleccione una solicitud...</option>
                            </select>
                        </div>

                        <!-- Materiales prestados (se carga dinámicamente) -->
                        <div id="materiales_prestados_container" class="hidden">
                            <label class="text-xs sm:text-sm font-medium text-gray-700 mb-2 block">Materiales Prestados</label>
                            <div id="materiales_prestados_lista" class="space-y-2">
                                <!-- Se llena con JavaScript -->
                            </div>
                        </div>

                    </div>

                    <!-- =====================
                        LISTA MATERIALES
                    ====================== -->
                    <div data-field="lista-materiales">
                        <p class="text-sm font-semibold mb-2">Materiales agregados</p>
                        <div id="listaMateriales" class="space-y-2 text-sm text-gray-600">
                            No hay materiales agregados
                        </div>
                    </div>

                    <!-- =====================
                        INVENTARIO ACTUAL BODEGA
                    ====================== -->
                    <div id="inventarioBodegaContainer" class="hidden rounded-xl border border-green-200 bg-green-50 p-4">
                        <p class="text-sm font-semibold mb-2 text-green-900">Inventario actual (Bodega)</p>
                        <div id="inventarioBodega" class="space-y-2 text-sm">
                            No hay materiales en esta bodega
                        </div>
                    </div>

                    <!-- =====================
                        INVENTARIO ACTUAL SUBBODEGA
                    ====================== -->
                    <div id="inventarioSubbodegaContainer" class="hidden rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <p class="text-sm font-semibold mb-2 text-blue-900">Inventario actual (Subbodega)</p>
                        <div id="inventarioSubbodega" class="space-y-2 text-sm">
                            No hay materiales en esta subbodega
                        </div>
                    </div>

                    <!-- OBSERVACIONES -->
                    <textarea name="observaciones"
                        placeholder="Observaciones"
                        class="w-full border rounded-lg px-2 sm:px-3 py-2 text-xs sm:text-sm"></textarea>

                    <!-- =====================
                        ACTIONS
                    ====================== -->
                    <div class="flex flex-col sm:flex-row justify-end gap-2 pt-4 border-t">

                        <button type="button"
                            onclick="closeMovimientoModal()"
                            class="w-full sm:w-auto inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-xs sm:text-sm font-medium hover:bg-muted">
                            Cancelar
                        </button>

                        <button
                            id="btnRegistrarMovimiento"
                            type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-secondary px-4 py-2 text-xs sm:text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition-all">
                            <i data-lucide="check-circle" class="h-4 w-4"></i>
                            Registrar entrada
                        </button>

                    </div>

                </form>
            </div>
            </div>
        </div>

        <!-- CONFIRM DEVOLUCION MODAL -->
        <div id="confirmDevolucionModal" class="fixed inset-0 z-[60] hidden">
            <div class="fixed inset-0 z-0 bg-black/50 backdrop-blur-md" onclick="closeConfirmDevolucionModal()"></div>

            <div class="absolute inset-0 z-10 flex items-center justify-center">
                <div class="relative mx-4 w-full max-w-md rounded-2xl bg-white shadow-xl p-6">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Confirmar devolución</h3>
                            <p class="text-sm text-gray-500">Revisa el resumen antes de continuar</p>
                        </div>
                        <button type="button" onclick="closeConfirmDevolucionModal()"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full hover:bg-gray-100">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>

                    <div id="confirmDevolucionText" class="text-sm text-gray-700 whitespace-pre-line"></div>

                    <div class="mt-6 flex flex-col sm:flex-row justify-end gap-2">
                        <button type="button" onclick="closeConfirmDevolucionModal()"
                            class="w-full sm:w-auto inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-muted">
                            Cancelar
                        </button>
                        <button type="button" id="confirmDevolucionOk"
                            class="w-full sm:w-auto inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90">
                            Confirmar
                        </button>
                    </div>
                </div>
            </div>
        </div>
</div>
</main>

<script>
    window.API_BASE = "<?= rtrim(BASE_URL, '/'); ?>/src/controllers/";
    window.ID_USUARIO = <?= (int)$idUsuario; ?>;
</script>
<script src="<?= BASE_URL ?>src/assets/js/movimientos/movimientos.js?v=<?= time(); ?>"></script>
</body>
</html>
<?php exit; ?>
