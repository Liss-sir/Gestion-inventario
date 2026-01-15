<?php

$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";

// Asegurar que BASE_URL esté definido (por si se accede directamente)
if (!defined('BASE_URL')) {
    $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host       = $_SERVER['HTTP_HOST'];
    $script_dir = '/Gestion-inventario/';  // Ajusta según tu ruta
    define('BASE_URL', $protocol . $host . $script_dir);
}

// Obtener ID del usuario de la sesión
$idUsuario = $_SESSION['id_usuario'] ?? 1; // Por defecto 1 si no hay sesión

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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>src/assets/css/globals.css">
</head>

<body>
    <main class="p-6 transition-all duration-300"
        style="margin-left: <?= isset($_GET['coll']) && $_GET['coll'] == "1" ? '70px' : '260px' ?>;">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Movimientos de Material</h1>
                <p class="text-muted-foreground">Historial de entradas, salidas y devoluciones de materiales</p>
            </div>



            <div class="flex justify-end items-center gap-2 mt-4 mb-4">
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

            <!-- 🔍 SEARCH (LEFT - QUIETO) -->
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

            <!-- 🎛️ FILTERS (RIGHT - JUNTOS) -->
            <div class="flex items-center gap-3 justify-end w-full sm:w-auto">

                <!-- TIPO -->
                <div class="flex items-center gap-2">
                    <i data-lucide="filter" class="h-4 w-4 text-muted-foreground"></i>
                    <div class="relative">
                        <select id="filtroTipo" name="filtro_tipo"
                            class="appearance-none rounded-lg border border-border bg-background py-2 pl-3 pr-8 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                            <option value="">Todos</option>
                            <option value="entrada">Entradas</option>
                            <option value="salida">Salidas</option>
                            <option value="devolucion">Devoluciones</option>
                        </select>

                        <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-muted-foreground">
                        </span>
                    </div>
                </div>

                <!-- PROGRAMA -->
                <div class="relative w-full sm:w-56">
                    <select
                        id="filtroPrograma"
                        name="filtro_programa"
                        class="w-full appearance-none rounded-lg border border-border bg-background py-2 pl-3 pr-9 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                        <option value="">Todos los programas</option>
                        <?php foreach ($programas as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-muted-foreground">
                    </span>
                </div>

            </div>
        </div>

        <!-- TABLE -->
        <div id="tableView" class="mt-6 rounded-2xl border border-border bg-card overflow-hidden">
            <div class="overflow-x-auto">
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

                    <tbody id="sinResultados" class="hidden">
                        <tr>
                            <td colspan="13" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center">
                                        <i data-lucide="search-x" class="h-6 w-6 text-gray-400"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">No se encontraron movimientos</p>
                                        <p class="text-xs text-gray-500 mt-1">Intenta ajustar los filtros para ver más resultados</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-border bg-card">
                <p id="contadorTabla" class="text-xs text-muted-foreground">
                    Mostrando 0 - 0 de 0 registros
                </p>


                <div class="flex items-center gap-1">
                    <a class="px-3 py-2 text-xs rounded-lg border border-border hover:bg-muted <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
                        href="?page=movimientos&p=<?= $page - 1 ?><?= $collParam ?>">Anterior</a>

                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a class="px-3 py-2 text-xs rounded-lg border border-border hover:bg-muted <?= $i === $page ? 'bg-muted text-foreground' : 'text-muted-foreground' ?>"
                            href="?page=movimientos&p=<?= $i ?><?= $collParam ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <a class="px-3 py-2 text-xs rounded-lg border border-border hover:bg-muted <?= $page >= $totalPages ? 'pointer-events-none opacity-50' : '' ?>"
                        href="?page=movimientos&p=<?= $page + 1 ?><?= $collParam ?>">Siguiente</a>
                </div>
            </div>
        </div>

        <!-- GRID VIEW COMPACT -->
        <div id="gridView" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">

        </div>


        <!-- MATERIALS VIEW MODAL -->
        <div id="materialesModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="absolute inset-0" onclick="closeMaterialesModal()"></div>

            <div class="relative mx-4 w-full max-w-xl rounded-2xl bg-white shadow-xl p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Materiales del movimiento</h3>
                        <p class="text-sm text-gray-500">Listado de materiales solicitados en este movimiento</p>
                    </div>
                    <button type="button" onclick="closeMaterialesModal()"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full hover:bg-gray-100">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>

                <div id="materialesBody" class="space-y-2"></div>

                <div class="mt-5 flex justify-end">
                    <button type="button" onclick="closeMaterialesModal()"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 border border-border">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        <!-- REGISTER MOVEMENT MODAL -->
        <div id="movimientoModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="absolute inset-0" onclick="closeMovimientoModal()"></div>

            <div class="relative mx-4 w-full max-w-2xl rounded-2xl bg-white shadow-xl p-6 sm:p-8">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Registrar Movimiento</h2>
                        <p class="text-sm text-gray-500">Registre un nuevo movimiento de inventario</p>
                    </div>
                    <button type="button" onclick="closeMovimientoModal()"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full hover:bg-gray-100">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>

                <!-- Tabs ( entrada / devolucion) -->
                <div class="mb-6 flex justify-center">
                    <div id="tabsMovimiento"
                        class="flex w-full max-w-md items-center rounded-full bg-gray-100 p-1 text-sm font-medium shadow-inner">

                        <button type="button" data-tipo="entrada"
                            class="tab-mov flex-1 rounded-full py-2 text-center text-gray-600 hover:text-gray-900 transition-all">
                            Entrada
                        </button>

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
                    <div class="rounded-xl border border-border p-4 bg-gray-50">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-3">
                            Datos del material
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">

                            <!-- MATERIAL -->
                            <div class="sm:col-span-2">
                                <label class="text-sm font-medium">Material</label>
                                <select id="material" class="w-full border rounded-lg px-3 py-2">
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
                                <label class="text-sm font-medium">Cantidad</label>
                                <input id="cantidad" type="number" min="1" value="1"
                                    class="w-full border rounded-lg px-3 py-2">
                            </div>

                            <!-- ESTADO -->
                            <div class="sm:col-span-3">
                                <label class="text-sm font-medium">Estado</label>
                                <select id="estado_material" class="w-full border rounded-lg px-3 py-2">
                                    <option value="">Seleccione</option>
                                    <option value="bueno">Bueno</option>
                                </select>
                            </div>

                        </div>

                        <button type="button"
                            onclick="agregarMaterial()"
                            class="mt-3 inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm hover:bg-muted">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            Agregar material
                        </button>
                    </div>

                    <!-- =====================
                        BODEGA
                    ====================== -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                        <div>
                            <label class="text-sm font-medium">Bodega</label>
                            <select id="bodega" class="w-full border rounded-lg px-3 py-2">
                                <option value="">Seleccione</option>
                            </select>

                        </div>

                        <div>
                            <label class="text-sm font-medium">Subbodega</label>
                            <select id="subbodega" class="w-full border rounded-lg px-3 py-2">
                                <option value="">Seleccione bodega primero</option>
                            </select>

                        </div>

                    </div>
                    <!-- =====================
                        DEVOLUCIÓN (ULTRA COMPACTA)
                    ====================== -->

                    <div data-field="programa"
                        class="hidden rounded-md border border-[#39A900] bg-[#39A90015] p-2">

                        <p class="text-[11px] font-semibold text-[#2e7d00] mb-1">
                            Devolución académica
                        </p>

                        <div class="grid grid-cols-2 gap-1">

                            <!-- PROGRAMA -->
                            <select id="programa"
                                class="col-span-2 border rounded px-2 py-1 text-xs">
                                <option value="">Programa</option>
                                <?php foreach ($programas as $p): ?>
                                    <option value="<?= $p["id"] ?>"><?= $p["nombre"] ?></option>
                                <?php endforeach; ?>
                            </select>

                            <!-- FICHA -->
                            <select id="ficha"
                                class="border rounded px-2 py-1 text-xs">
                                <option value="">Ficha</option>
                                <?php foreach ($fichas as $f): ?>
                                    <option value="<?= $f["id"] ?>"><?= $f["nombre"] ?></option>
                                <?php endforeach; ?>
                            </select>

                            <!-- RAE -->
                            <select id="rae"
                                class="border rounded px-2 py-1 text-xs">
                                <option value="">RAE</option>
                                <?php foreach ($raes as $r): ?>
                                    <option value="<?= $r["id"] ?>"><?= $r["nombre"] ?></option>
                                <?php endforeach; ?>
                            </select>

                            <!-- INSTRUCTOR -->
                            <select id="instructor"
                                class="col-span-2 border rounded px-2 py-1 text-xs">
                                <option value="">Instructor</option>
                                <?php foreach ($instructores as $i): ?>
                                    <option value="<?= $i["id"] ?>"><?= $i["nombre"] ?></option>
                                <?php endforeach; ?>
                            </select>

                            <!-- SOLICITUD -->
                            <select id="solicitud"
                                class="col-span-2 border rounded px-2 py-1 text-xs">
                                <option value="">Solicitud (opcional)</option>
                                <?php foreach ($solicitudes as $s): ?>
                                    <option value="<?= $s["id"] ?>"><?= $s["nombre"] ?></option>
                                <?php endforeach; ?>
                            </select>

                        </div>
                    </div>

                    <!-- =====================
                        LISTA MATERIALES
                    ====================== -->
                    <div>
                        <p class="text-sm font-semibold mb-2">Materiales agregados</p>
                        <div id="listaMateriales" class="space-y-2 text-sm text-gray-600">
                            No hay materiales agregados
                        </div>
                    </div>

                    <!-- OBSERVACIONES -->
                    <textarea name="observaciones"
                        placeholder="Observaciones"
                        class="w-full border rounded-lg px-3 py-2"></textarea>

                    <!-- =====================
                        ACTIONS
                    ====================== -->
                    <div class="flex justify-end gap-2 pt-2 border-t">

                        <button type="button"
                            onclick="closeMovimientoModal()"
                            class="px-4 py-2 rounded-lg border text-sm hover:bg-muted">
                            Cancelar
                        </button>

                        <button
                            id="btnRegistrarMovimiento"
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition-all">
                            <i data-lucide="check-circle" class="h-4 w-4"></i>
                            Registrar entrada
                        </button>

                    </div>

                </form>
            </div>
        </div>
    </main>

    <script>
        // Función para escapar HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        let materialesAgregados = [];

        function agregarMaterial() {
            const materialSel = document.getElementById("material");
            const cantidadEl = document.getElementById("cantidad");
            const estadoSel = document.getElementById("estado_material");

            const id = materialSel.value;
            const nombre = materialSel.options[materialSel.selectedIndex]?.text;
            const unidad = materialSel.options[materialSel.selectedIndex]?.dataset.unidad || '';
            const cantidad = parseInt(cantidadEl.value);
            const estado = estadoSel.value;

            if (!id || !cantidad || cantidad < 1 || !estado) {
                alert("Debe completar todos los campos del material (Material, Cantidad y Estado)");
                return;
            }

            materialesAgregados.push({
                id_material: id,
                nombre,
                cantidad,
                unidad,
                estado
            });

            console.log("Material agregado:", materialesAgregados);

            renderMateriales();

            materialSel.value = "";
            cantidadEl.value = 1;
            estadoSel.value = "";
        }

        function eliminarMaterial(index) {
            materialesAgregados.splice(index, 1);
            renderMateriales();
        }

        function renderMateriales() {
            const cont = document.getElementById("listaMateriales");
            cont.innerHTML = "";

            if (materialesAgregados.length === 0) {
                cont.innerHTML = "No hay materiales agregados.";
                return;
            }

            materialesAgregados.forEach((m, i) => {
                cont.innerHTML += `
                    <div class="flex justify-between items-center border rounded-lg p-3">
                        <div>
                            <p class="font-semibold">${m.nombre}</p>
                            <p class="text-xs text-gray-500">
                                Cant: ${m.cantidad} ${m.unidad} · Estado: ${m.estado}
                            </p>
                        </div>
                        <button type="button"
                            onclick="eliminarMaterial(${i})"
                            class="text-red-500 hover:text-red-700 font-bold">
                            ✕
                        </button>
                    </div>
                `;
            });

            document.getElementById("materiales_json").value =
                JSON.stringify(materialesAgregados);
        }

        document.addEventListener("DOMContentLoaded", function() {
            // Cargar movimientos desde BD
            cargarMovimientosDelServidor();




            // LUCIDE
            if (window.lucide && typeof lucide.createIcons === "function") {
                lucide.createIcons();
            }

            // change view
            const btnVistaTabla = document.getElementById("btnVistaTabla");
            const btnVistaTarjetas = document.getElementById("btnVistaTarjetas");
            const tableView = document.getElementById("tableView");
            const gridView = document.getElementById("gridView");

            if (btnVistaTabla && btnVistaTarjetas && tableView && gridView) {
                const setActiveBtn = (btnActive, btnInactive) => {
                    btnActive.classList.add("bg-muted", "text-foreground");
                    btnActive.classList.remove("text-muted-foreground");

                    btnInactive.classList.remove("bg-muted", "text-foreground");
                    btnInactive.classList.add("text-muted-foreground");
                };

                const showTable = () => {
                    gridView.classList.add("hidden");
                    tableView.classList.remove("hidden");
                    setActiveBtn(btnVistaTabla, btnVistaTarjetas);
                };
                const showGrid = () => {
                    tableView.classList.add("hidden");
                    gridView.classList.remove("hidden");
                    setActiveBtn(btnVistaTarjetas, btnVistaTabla);
                    if (window.lucide && typeof lucide.createIcons === "function") lucide.createIcons();
                };

                btnVistaTabla.addEventListener("click", showTable);
                btnVistaTarjetas.addEventListener("click", showGrid);
                showTable();
            }


            /* ===============================
              TABS movement (no exit)
            =============================== */
            const labelsPorTipo = {
                entrada: 'Registrar entrada',
                devolucion: 'Registrar devolución',
            };

            function initTabsMovimiento() {
                const tabsWrap = document.getElementById("tabsMovimiento");
                if (!tabsWrap) return;

                const tabs = tabsWrap.querySelectorAll(".tab-mov");
                const hiddenTipo = document.getElementById('tipoMovimiento');
                const btnSubmit = document.getElementById('btnRegistrarMovimiento');
                const entradaBtn = tabsWrap.querySelector('[data-tipo="entrada"]');

                const setActive = (btn) => {
                    tabs.forEach(t => {
                        t.classList.remove("bg-white", "shadow", "text-gray-900");
                        t.classList.add("text-gray-600");
                    });

                    btn.classList.add("bg-white", "shadow", "text-gray-900");
                    btn.classList.remove("text-gray-600");


                    const cardDevolucion = document.querySelector('[data-field="programa"]');

                    const tipo = btn.dataset.tipo;
                    if (hiddenTipo) hiddenTipo.value = tipo;
                    if (btnSubmit) btnSubmit.textContent = labelsPorTipo[tipo] || "Registrar";

                    const isDev = (tipo === "devolucion");

                    if (cardDevolucion) {
                        cardDevolucion.classList.toggle("hidden", !isDev);
                    }

                    if (btnSubmit) {
                        btnSubmit.textContent = labelsPorTipo[tipo] || "Registrar";
                        btnSubmit.classList.remove("bg-blue-600", "bg-secondary");
                        btnSubmit.classList.add("bg-secondary"); // siempre verde
                    }


                    if (!isDev) {
                        ["programa", "ficha", "rae", "instructor", "solicitud"].forEach(id => {
                            const el = document.getElementById(id);
                            if (el) el.value = "";
                        });
                    }



                    // cleaning
                    if (tipo !== "entrada") {
                        const entregaSel = document.getElementById("entrega");
                        if (entregaSel) entregaSel.value = "";
                    }
                    if (!isDev) {
                        const fichaSel = document.getElementById("ficha");
                        const raeSel = document.getElementById("rae");
                        const insSel = document.getElementById("instructor");
                        const solSel = document.getElementById("solicitud");
                        if (fichaSel) fichaSel.value = "";
                        if (raeSel) raeSel.value = "";
                        if (insSel) insSel.value = "";
                        if (solSel) solSel.value = "";
                    }
                };

                if (entradaBtn) setActive(entradaBtn);
                tabs.forEach(btn => btn.onclick = () => setActive(btn));
            }
            window.initTabsMovimiento = initTabsMovimiento;

            /* ===============================
              Inicialización de tabs
            =============================== */
            if (window.initTabsMovimiento) {
                const form = document.getElementById("formMovimiento");
                const materialesInput = document.getElementById("materiales_json");
                let materiales = [];

                try {
                    materiales = JSON.parse(materialesInput.value || "[]");
                } catch (e) {
                    materiales = [];
                }

                const cantidadEl = document.getElementById("cantidad");
                if (cantidadEl) {
                    cantidadEl.addEventListener("keydown", function(ev) {
                        if (ev.key === "-" || ev.key === "e" || ev.key === "E" || ev.key === "+") {
                            ev.preventDefault();
                        }
                    });
                }
            }

            /* ===============================
              Funcionalidad de filtros
            =============================== */
            const filtroTipo = document.getElementById("filtroTipo");
            const filtroPrograma = document.getElementById("filtroPrograma");
            const buscarFicha = document.getElementById("buscarFicha");

            function aplicarFiltros() {
                const tbody = document.getElementById("tbodyMovimientos");
                const sinResultados = document.getElementById("sinResultados");
                if (!tbody) return;

                const filas = tbody.querySelectorAll("tr");
                const valorTipo = filtroTipo?.value.toLowerCase().trim() || "";
                const valorPrograma = filtroPrograma?.value.trim() || "";
                const valorFicha = buscarFicha?.value.toLowerCase().trim() || "";

                let filasVisibles = 0;

                filas.forEach(fila => {
                    const tipo = (fila.dataset.tipo || "").toLowerCase().trim();
                    const programa = (fila.dataset.programa || "").trim();
                    const ficha = (fila.dataset.ficha || "").toLowerCase().trim();

                    const cumpleTipo = !valorTipo || tipo === valorTipo;
                    const cumplePrograma = !valorPrograma || programa === valorPrograma;
                    const cumpleFicha = !valorFicha || ficha.includes(valorFicha);

                    const mostrar = cumpleTipo && cumplePrograma && cumpleFicha;
                    fila.style.display = mostrar ? "" : "none";
                    if (mostrar) filasVisibles++;
                });

                const totalFilas = filas.length;

                if (sinResultados) {
                    if (filasVisibles === 0) {
                        sinResultados.classList.remove("hidden");
                        sinResultados.style.display = "table-row-group";
                    } else {
                        sinResultados.classList.add("hidden");
                        sinResultados.style.display = "none";
                    }
                }

                actualizarContadorTabla(filasVisibles, totalFilas);
                            }


            if (filtroTipo) filtroTipo.addEventListener("change", aplicarFiltros);
            if (filtroPrograma) filtroPrograma.addEventListener("change", aplicarFiltros);
            if (buscarFicha) buscarFicha.addEventListener("input", aplicarFiltros);
        });


        function closeMovimientoModal() {
            const modal = document.getElementById('movimientoModal');
            if (!modal) return;

            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');

            materialesAgregados = [];
            document.getElementById("listaMateriales").innerHTML = "No hay materiales agregados.";
            document.getElementById("materiales_json").value = "";
        }


        /* ===============================
           MODAL Movement
        =============================== */
        function openMovimientoModal() {
            const modal = document.getElementById('movimientoModal');
            if (!modal) return;

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            cargarBodegas();
            cargarMateriales();

            document.body.classList.add('overflow-hidden');

            const subSel = document.getElementById("subbodega");
            if (subSel) subSel.innerHTML = `<option value="">Seleccione</option>`;

            if (window.initTabsMovimiento) window.initTabsMovimiento();
            if (window.lucide) lucide.createIcons();
        }

        function showNA(val) {
            return val && String(val).trim() !== "" ? escapeHtml(String(val)) : "N/A";
        }


        function renderGridMovimientos(movimientos) {
                const grid = document.getElementById("gridView");
                if (!grid) return;

                grid.innerHTML = "";

                movimientos.forEach(m => {
                    const tipo = (m.tipo_movimiento || "").toLowerCase();

                    let badge = {
                        texto: "Entrada",
                        color: "bg-[#39A90020] text-slate-700",
                        icon: "arrow-up-from-line"
                    };

                    if (tipo === "salida") {
                        badge = {
                            texto: "Salida",
                            color: "bg-lime-100 text-lime-700",
                            icon: "arrow-down-up"
                        };
                    } else if (tipo === "devolucion") {
                        badge = {
                            texto: "Devolución",
                            color: "bg-[#39A90020] text-slate-700",
                            icon: "rotate-ccw"
                        };
                    }

                    const fecha = m.fecha_hora ? new Date(m.fecha_hora) : null;
                    const fechaTexto = fecha? `${fecha.toLocaleDateString("es-CO")} ${fecha.toLocaleTimeString("es-CO", { hour: "2-digit", minute: "2-digit" })}`: "N/A";


                    const cantidadTotal = Array.isArray(m.materiales)
                        ? m.materiales.reduce((sum, mat) => sum + (parseInt(mat.cantidad) || 0), 0)
                        : 0;

                    grid.insertAdjacentHTML("beforeend", `
                        <div class="rounded-xl border border-border bg-card p-4 shadow-sm hover:shadow-md transition">

                            <!-- HEADER -->
                            <div class="flex items-center justify-between mb-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${badge.color}">
                                    <i data-lucide="${badge.icon}" class="h-3 w-3"></i>
                                    ${badge.texto}
                                </span>
                                <span class="text-xs text-muted-foreground">${fechaTexto}</span>
                            </div>

                            <!-- BODY -->
                            <div class="space-y-1 text-sm">

                                <p class="font-medium text-foreground truncate">
                                    Bodega: ${escapeHtml(m.bodega || "N/A")}
                                </p>

                                <p class="text-xs text-muted-foreground truncate">
                                    Subbodega: ${escapeHtml(m.subbodega || "N/A")}
                                </p>

                                ${tipo !== "entrada" ? `
                                    <div class="mt-2 space-y-0.5 text-xs text-muted-foreground">

                                        <p><span class="font-medium">Programa:</span> ${showNA(m.id_programa)}</p>
                                        <p><span class="font-medium">Ficha:</span> ${showNA(m.id_ficha)}</p>
                                        <p><span class="font-medium">RAE:</span> ${showNA(m.id_rae)}</p>
                                        <p><span class="font-medium">Solicitud:</span> ${showNA(m.id_solicitud)}</p>

                                    </div>
                                ` : `
                                    <p class="mt-2 text-xs text-muted-foreground italic">
                                        No aplica información académica
                                    </p>
                                `}
                            </div>


                            <!-- FOOTER -->
                            <div class="mt-3 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-1 text-muted-foreground">
                                    <i data-lucide="package" class="h-3 w-3"></i>
                                    ${cantidadTotal} items
                                </div>

                                <button
                                    type="button"
                                    onclick="openMaterialesModal(this)"
                                    data-materiales='${escapeHtml(JSON.stringify(m.materiales || []))}'
                                    class="inline-flex items-center gap-1 rounded-md border px-2 py-1 hover:bg-muted">
                                    <i data-lucide="eye" class="h-3 w-3"></i>
                                    Ver
                                </button>
                            </div>
                        </div>
                    `);
                });

                if (window.lucide) lucide.createIcons();
            }


        // Función para cargar movimientos desde el servidor
        async function cargarMovimientosDelServidor() {
            try {
                const res = await fetch(`${API_BASE}movimiento_controller.php?accion=listar`, {
                    headers: {
                        Accept: "application/json"
                    }
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json();

                console.log("Movimientos cargados:", json);

                if (!json.success || !Array.isArray(json.data)) {
                    console.log("No hay movimientos o error en respuesta");
                    return;
                }

                const tbody = document.getElementById("tbodyMovimientos");
                const gridView = document.getElementById("gridView");

                if (!tbody) return;

                tbody.innerHTML = ""; // Limpiar tabla
                if (gridView) gridView.innerHTML = ""; // Limpiar grid

                json.data.forEach(m => {
                    const tipo = (m.tipo_movimiento || "").toLowerCase();
                    let labelTipo = "Entrada";
                    let claseTipo = "bg-gray-100 text-gray-700";
                    let iconTipo = "arrow-down-up";

                    if (tipo === "entrada") {
                        labelTipo = "Entrada";
                        claseTipo = "bg-[#39A90020] text-slate-700";
                        iconTipo = "arrow-up-from-line";
                    } else if (tipo === "salida") {
                        labelTipo = "Salida";
                        claseTipo = "bg-lime-100 text-lime-700";
                        iconTipo = "arrow-down-up";
                    } else if (tipo === "devolucion") {
                        labelTipo = "Devolución";
                        claseTipo = "bg-[#39A90020] text-slate-700";
                        iconTipo = "rotate-ccw";
                    }

                    const fecha = m.fecha_hora ? new Date(m.fecha_hora) : null;
                    const fechaFormato = fecha ? fecha.toLocaleDateString('es-CO') : '-';
                    const horaFormato = fecha ? fecha.toLocaleTimeString('es-CO') : '';

                    // Materiales con cantidad total
                    const cantidadTotal = Array.isArray(m.materiales) ?
                        m.materiales.reduce((sum, mat) => sum + (parseInt(mat.cantidad) || 0), 0) :
                        0;
                    const materialesJson = escapeHtml(JSON.stringify(m.materiales || []));

                    // Agregar a tabla
                    tbody.insertAdjacentHTML("beforeend", `
                    <tr class="hover:bg-muted/60"
                        data-tipo="${tipo}"
                        data-programa="${m.id_programa || ''}"
                        data-ficha="${(m.id_ficha || '').toString().toLowerCase()}">


                    <td class="px-4 py-3 align-top">
                        <div class="flex items-start gap-2">
                            <i data-lucide="calendar" class="h-4 w-4 mt-0.5 text-muted-foreground"></i>
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-foreground">${fechaFormato}</span>
                                <span class="text-xs text-muted-foreground">${horaFormato}</span>
                            </div>
                        </div>
                    </td>
                    
                    <td class="px-4 py-3 align-top">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${claseTipo}">
                            <i data-lucide="${iconTipo}" class="h-3 w-3"></i>
                            ${labelTipo}
                        </span>
                    </td>
                    
                    <td class="px-4 py-3 align-top">
                        <button type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 hover:bg-muted"
                            onclick="openMaterialesModal(this)"
                            data-materiales='${materialesJson}'>
                            <i data-lucide="eye" class="h-4 w-4"></i>
                            <span class="text-xs text-muted-foreground">Ver</span>
                        </button>
                    </td>
                    
                    <td class="px-4 py-3 align-top">
                        <span class="text-sm font-medium text-foreground">${cantidadTotal}</span>
                    </td>
                    
                    <td class="px-4 py-3 align-top"><span class="text-sm">${escapeHtml(m.bodega || 'N/A')}</span></td>
                    <td class="px-4 py-3 align-top"><span class="text-sm">${escapeHtml(m.subbodega || 'N/A')}</span></td>
                    <td class="px-4 py-3 align-top"><span class="text-sm">${escapeHtml(m.id_programa ? String(m.id_programa) : 'N/A')}</span></td>
                    
                    <td class="px-4 py-3 align-top">
                        <span class="inline-flex items-center rounded-md border border-border px-2 py-1 text-xs font-medium">
                            ${escapeHtml(m.id_ficha ? String(m.id_ficha) : 'N/A')}
                        </span>
                    </td>
                    
                    <td class="px-4 py-3 align-top"><span class="text-sm">${escapeHtml(m.id_rae ? String(m.id_rae) : 'N/A')}</span></td>
                    
                    <td class="px-4 py-3 align-top">
                        <div class="flex items-start gap-2">
                            <i data-lucide="users" class="h-4 w-4 mt-0.5 text-muted-foreground"></i>
                            <span class="text-sm truncate max-w-[220px]">${escapeHtml(m.id_instructor ? String(m.id_instructor) : 'N/A')}</span>
                        </div>
                    </td>
                    
                    <td class="px-4 py-3 align-top">
                        <span class="text-sm text-muted-foreground">${escapeHtml(m.observaciones || 'N/A')}</span>
                    </td>
                    
                    <td class="px-4 py-3 align-top"><span class="text-sm">N/A</span></td>
                </tr>
            `);
                });

                // Recrear iconos de Lucide
                if (window.lucide && typeof lucide.createIcons === "function") {
                    lucide.createIcons();
                }

                // Actualizar contadores
                actualizarContadores(json.data);
                renderGridMovimientos(json.data);

                // Total real cargado
                const totalRegistros = json.data.length;

                // Mostrar todo al inicio
                actualizarContadorTabla(totalRegistros, totalRegistros);


                const contadorTabla = document.getElementById("contadorTabla");

                if (contadorTabla) {
                    const total = json.data.length;

                    if (total === 0) {
                        contadorTabla.textContent = "Mostrando 0 - 0 de 0 registros";
                    } else {
                        contadorTabla.textContent = `Mostrando 1 - ${total} de ${total} registros`;
                    }
                }



            } catch (err) {
                console.error("Error cargando movimientos:", err);
            }
        }


        function actualizarContadorTabla(visibles, total) {
            const contador = document.getElementById("contadorTabla");
            if (!contador) return;

            if (visibles === 0) {
                contador.textContent = "Mostrando 0 - 0 de 0 registros";
            } else {
                contador.textContent = `Mostrando 1 - ${visibles} de ${total} registros`;
            }
        }


        // Función para actualizar contadores de entrada, salida, devolución
        function actualizarContadores(movimientos) {
            let contEntrada = 0;
            let contSalida = 0;
            let contDevolucion = 0;

            movimientos.forEach(m => {
                const tipo = (m.tipo_movimiento || "").toLowerCase();
                if (tipo === "entrada") contEntrada++;
                else if (tipo === "salida") contSalida++;
                else if (tipo === "devolucion") contDevolucion++;
            });

            const elEntrada = document.getElementById("contadorEntrada");
            const elSalida = document.getElementById("contadorSalida");
            const elDevolucion = document.getElementById("contadorDevolucion");

            if (elEntrada) elEntrada.textContent = contEntrada;
            if (elSalida) elSalida.textContent = contSalida;
            if (elDevolucion) elDevolucion.textContent = contDevolucion;
        }

        const API_BASE = "<?= rtrim(BASE_URL, '/'); ?>/src/controllers/";
        const ID_USUARIO = <?= (int)$idUsuario; ?>;

        async function cargarBodegas() {
            const sel = document.getElementById("bodega");
            if (!sel) return;

            sel.innerHTML = `<option value="">Cargando...</option>`;
            try {
                const res = await fetch(`${API_BASE}bodega_controller.php?accion=listar`, {
                    headers: {
                        Accept: "application/json"
                    }
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json();

                sel.innerHTML = `<option value="">Seleccione</option>`;
                if (!json.success || !Array.isArray(json.data)) return;

                json.data.forEach(b => {
                    sel.insertAdjacentHTML("beforeend",
                        `<option value="${b.id_bodega}">${b.nombre}</option>`);
                });

                sel.onchange = (e) => {
                    const id = e.target.value;
                    if (!id) {
                        const sub = document.getElementById("subbodega");
                        if (sub) sub.innerHTML = `<option value="">Seleccione bodega primero</option>`;
                        return;
                    }
                    cargarSubbodegas(id);
                };
            } catch (e) {
                console.error("Error cargando bodegas:", e);
                sel.innerHTML = `<option value="">Error al cargar</option>`;
            }
        }

        async function cargarSubbodegas(idBodega) {
            const sel = document.getElementById("subbodega");
            if (!sel) return;
            if (!idBodega) {
                sel.innerHTML = `<option value="">Seleccione bodega primero</option>`;
                return;
            }

            sel.innerHTML = `<option value="">Cargando...</option>`;
            try {
                const res = await fetch(
                    `${API_BASE}sub_bodega_controller.php?accion=por_bodega&id_bodega=${encodeURIComponent(idBodega)}`, {
                        headers: {
                            Accept: "application/json"
                        }
                    }
                );
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json();

                sel.innerHTML = `<option value="">Seleccione</option>`;
                if (!json.success || !Array.isArray(json.data)) return;

                json.data.forEach(sb => {
                    sel.insertAdjacentHTML("beforeend",
                        `<option value="${sb.id_subbodega}">${sb.nombre_subbodega}</option>`);
                });
            } catch (e) {
                console.error("Error cargando subbodegas:", e);
                sel.innerHTML = `<option value="">Error al cargar</option>`;
            }
        }

        async function cargarMateriales() {
            const sel = document.getElementById("material");
            if (!sel) return;

            sel.innerHTML = `<option value="">Cargando...</option>`;
            try {
                const res = await fetch(`${API_BASE}material_formacion_controller.php?accion=listar`, {
                    headers: {
                        Accept: "application/json"
                    }
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json();

                sel.innerHTML = `<option value="">Seleccione</option>`;
                const items = Array.isArray(json) ? json : (json.data || []);
                if (!Array.isArray(items)) return;

                items.forEach(m => {
                    sel.insertAdjacentHTML(
                        "beforeend",
                        `<option value="${m.id_material}" data-unidad="${m.unidad ?? ''}">${m.nombre}</option>`
                    );
                });
            } catch (e) {
                console.error("Error cargando materiales:", e);
                sel.innerHTML = `<option value="">Error al cargar</option>`;
            }
        }
        /* ===============================
          MODAL Materials
        =============================== */
        function openMaterialesModal(btn) {
            const modal = document.getElementById("materialesModal");
            const body = document.getElementById("materialesBody");
            if (!modal || !body) return;

            let items = [];
            try {
                items = JSON.parse(btn.dataset.materiales || "[]");
            } catch (e) {
                items = [];
            }

            body.innerHTML = "";

            if (!items.length) {
                body.innerHTML = `
      <div class="rounded-lg border border-border p-4 text-sm text-muted-foreground">
        No hay materiales asociados a este movimiento.
      </div>`;
            } else {
                items.forEach((it, idx) => {
                    body.insertAdjacentHTML("beforeend", `
        <div class="rounded-xl border border-border p-4 flex items-start justify-between gap-4">
          <div class="flex items-start gap-3">
            <div class="h-9 w-9 rounded-lg bg-gray-100 flex items-center justify-center">
              <i data-lucide="package" class="h-4 w-4 text-[#39A900]"></i>
            </div>
            <div>
              <p class="text-sm font-semibold text-foreground">${escapeHtml(it.nombre || ("Material #" + (it.id_material ?? (idx+1))))}</p>
              <p class="text-xs text-muted-foreground">ID material: ${escapeHtml(String(it.id_material ?? "-"))}</p>
            </div>
          </div>
          <div class="text-right">
            <p class="text-sm font-medium">${escapeHtml(String(it.cantidad ?? "-"))} ${escapeHtml(it.unidad || "")}</p>
            <p class="text-xs text-muted-foreground">Cantidad</p>
          </div>
        </div>
      `);
                });
            }

            modal.classList.remove("hidden");
            modal.classList.add("flex");

            if (window.lucide && typeof lucide.createIcons === "function") lucide.createIcons();
        }

        function closeMaterialesModal() {
            const modal = document.getElementById("materialesModal");
            if (!modal) return;
            modal.classList.add("hidden");
            modal.classList.remove("flex");
        }

        /* ===============================
           DETAIL MODAL (Targets)
        =============================== */
        function openDetalleFromDataset(btn) {
            currentActionData = {
                id: btn.dataset.id,
                tipo: btn.dataset.tipo,
                fecha: btn.dataset.fecha,
                bodega: btn.dataset.bodega,
                subbodega: btn.dataset.subbodega,
                programa: btn.dataset.programa,
                ficha: btn.dataset.ficha,
                rae: btn.dataset.rae,
                instructor: btn.dataset.instructor,
                observaciones: btn.dataset.observaciones,
                solicitud: btn.dataset.solicitud,
                materiales: btn.dataset.materiales
            };
            openDetalleModal(currentActionData);
        }

        function openDetalleModal(data) {
            const modal = document.getElementById("detalleModal");
            if (!modal) return;

            // Header
            document.getElementById("detTitulo").textContent = `Detalle del movimiento #${data.id || "-"}`;
            document.getElementById("detSubtitulo").textContent = "Información completa del registro";

            // Badges

            const tipo = (data.tipo || "").toLowerCase();
            const badgeTipo = document.getElementById("detBadgeTipo");
            const detTipo = document.getElementById("detTipo");
            const detIconTipo = document.getElementById("detIconTipo");
            let icon = "arrow-down-up";
            let cls = "bg-gray-100 text-gray-700";
            let label = data.tipo || "-";

            if (tipo === "entrada") {
                icon = "arrow-up-from-line";
                cls = "bg-[#39A90020] text-slate-700";
                label = "Entrada";
            }
            if (tipo === "salida") {
                icon = "arrow-down-up";
                cls = "bg-lime-100 text-lime-700";
                label = "Salida";
            }
            if (tipo === "devolucion") {
                icon = "rotate-ccw";
                cls = "bg-[#39A90020] text-slate-700";
                label = "Devolución";
            }

            if (badgeTipo) {
                badgeTipo.className = `inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium ${cls}`;
                badgeTipo.innerHTML = `<i data-lucide="${icon}" class="h-3 w-3"></i>${label}`;
            }

            // Fecha
            const fecha = data.fecha_hora ? new Date(data.fecha_hora) : null;
            document.getElementById("detFecha").textContent = fecha ? fecha.toLocaleString() : "-";


            // Campos normales
            const fields = [
                "bodega",
                "subbodega",
                "programa",
                "ficha",
                "rae",
                "instructor",
                "observaciones",
                "solicitud"
            ];

            fields.forEach(f => {
                const el = document.getElementById(
                    `det${f.charAt(0).toUpperCase() + f.slice(1)}`
                );

                if (el) {
                    el.textContent = data[f] || "-";
                }
            });




            // Materiales
            const contMateriales = document.getElementById("detMateriales");
            contMateriales.innerHTML = "";

            let materiales = [];
            try {
                materiales = JSON.parse(data.materiales || "[]");
            } catch (e) {
                materiales = [];
            }

            if (materiales.length === 0) {
                contMateriales.innerHTML = `
        <div class="rounded-lg border border-border p-4 text-sm text-muted-foreground">
          No hay materiales asociados a este movimiento.
        </div>`;
            } else {
                materiales.forEach((m, idx) => {
                    contMateriales.insertAdjacentHTML("beforeend", `
          <div class="rounded-xl border border-border p-4 flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
              <div class="h-9 w-9 rounded-lg bg-gray-100 flex items-center justify-center">
                <i data-lucide="package" class="h-4 w-4 text-[#39A900]"></i>
              </div>
              <div>
                <p class="text-sm font-semibold text-foreground">${escapeHtml(m.nombre || ("Material #" + (m.id_material ?? (idx+1))))}</p>
                <p class="text-xs text-muted-foreground">ID material: ${escapeHtml(String(m.id_material ?? "-"))}</p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-sm font-medium">${escapeHtml(String(m.cantidad ?? "-"))} ${escapeHtml(m.unidad || "")}</p>
              <p class="text-xs text-muted-foreground">Cantidad</p>
            </div>
          </div>
        `);
                });
            }

            modal.classList.remove("hidden");
            modal.classList.add("flex");

            if (window.lucide && typeof lucide.createIcons === "function") lucide.createIcons();
        }

        function closeDetalleModal() {
            const modal = document.getElementById("detalleModal");
            if (!modal) return;
            modal.classList.add("hidden");
            modal.classList.remove("flex");
        }

        function registrarEntrada(ev) {
            ev.preventDefault();

            console.log("Registrando entrada, materiales agregados:", materialesAgregados);

            // Validación mínima (ya tienes más arriba)
            if (!materialesAgregados.length) {
                alert("Debe agregar al menos un material.");
                return;
            }

            // Obtener IDs (no solo nombres)
            const tipoMovimiento = document.getElementById("tipoMovimiento")?.value || "entrada";
            const idBodega = document.getElementById("bodega")?.value || "";
            const idSubbodega = document.getElementById("subbodega")?.value || "";
            const idPrograma = document.getElementById("programa")?.value || null;
            const idFicha = document.getElementById("ficha")?.value || null;
            const idRae = document.getElementById("rae")?.value || null;
            const idInstructor = document.getElementById("instructor")?.value || null;
            const idSolicitud = document.getElementById("solicitud")?.value || null;
            const observaciones = document.querySelector("textarea[name='observaciones']")?.value || "";

            // Validar que tenga bodega y subbodega
            if (!idBodega || !idSubbodega) {
                alert("Debe seleccionar bodega y subbodega.");
                return;
            }

            // Preparar datos para enviar al servidor
            const dataToSend = {
                id_usuario: ID_USUARIO, // Obtener del usuario logueado
                id_bodega: idBodega,
                id_subbodega: idSubbodega,
                id_programa: idPrograma || null,
                id_ficha: idFicha || null,
                id_rae: idRae || null,
                id_instructor: idInstructor || null,
                id_solicitud: idSolicitud || null,
                observaciones: observaciones,
                tipo_movimiento: tipoMovimiento,
                materiales: materialesAgregados
            };

            console.log("Enviando datos:", dataToSend);

            // Enviar al servidor
            fetch(`${API_BASE}movimiento_controller.php?accion=crear`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(dataToSend)
                })
                .then(res => res.json())
                .then(json => {
                    console.log("Respuesta del servidor:", json);

                    if (json.success) {
                        alert("Movimiento registrado exitosamente: " + json.codigo_movimiento);

                        // Limpiar y cerrar modal
                        materialesAgregados = [];
                        renderMateriales();
                        document.getElementById("formMovimiento")?.reset();
                        closeMovimientoModal();

                        // Recargar movimientos desde BD
                        setTimeout(() => {
                            cargarMovimientosDelServidor();
                        }, 500);
                    } else {
                        alert("Error: " + (json.message || "No se pudo registrar el movimiento"));
                    }
                })
                .catch(err => {
                    console.error("Error:", err);
                    alert("Error al registrar movimiento: " + err.message);
                });
        }
    </script>
</body>
</html>