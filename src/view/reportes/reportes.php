<?php
// =====================================================
// ✅ INTEGRACIÓN BACKEND REAL (SIN ROMPER TU BASE)
//   - Mantengo tus MOCKS como fallback
//   - Si BD funciona, se reemplazan los arrays por datos reales
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===========================
// ✅ FALLBACK MOCKS (TU BASE)
// ===========================

// Datos para gráficas
$consumoPorMes = [
    ['mes' => 'Ene', 'consumo' => 120, 'devoluciones' => 15],
    ['mes' => 'Feb', 'consumo' => 98, 'devoluciones' => 12],
    ['mes' => 'Mar', 'consumo' => 145, 'devoluciones' => 20],
    ['mes' => 'Abr', 'consumo' => 87, 'devoluciones' => 8],
    ['mes' => 'May', 'consumo' => 156, 'devoluciones' => 18],
    ['mes' => 'Jun', 'consumo' => 134, 'devoluciones' => 14],
];

// 👇 Colores solo para la leyenda (coinciden con los de la dona)
$consumoPorPrograma = [
    ['name' => 'Construcción', 'value' => 45, 'color' => '#6CC24A'], // lightGreen
    ['name' => 'Eléctrico',    'value' => 25, 'color' => '#007832'], // secondary
    ['name' => 'Acabados',     'value' => 20, 'color' => '#002B49'], // navy
    ['name' => 'Otros',        'value' => 10, 'color' => '#71277A'], // purple
];

$materialesMasUsados = [
    ['nombre' => 'Cemento Gris', 'cantidad' => 150],
    ['nombre' => 'Arena de Río', 'cantidad' => 120],
    ['nombre' => 'Cable #12', 'cantidad' => 85],
    ['nombre' => 'Pintura Vinilo', 'cantidad' => 65],
    ['nombre' => 'Tubo PVC 4"', 'cantidad' => 45],
];

$consumoPorFicha = [
    ['ficha' => '2567890', 'programa' => 'Construcción', 'consumo' => 85, 'costo' => 2450000],
    ['ficha' => '2567891', 'programa' => 'Tecnólogo Construcción', 'consumo' => 72, 'costo' => 1980000],
    ['ficha' => '2567892', 'programa' => 'Eléctrico', 'consumo' => 48, 'costo' => 890000],
    ['ficha' => '2567893', 'programa' => 'Construcción', 'consumo' => 35, 'costo' => 720000],
];

$mockProgramas = [
    ['id' => '1', 'nombre' => 'Tecnología en Construcción'],
    ['id' => '2', 'nombre' => 'Técnico en Instalaciones Eléctricas'],
    ['id' => '3', 'nombre' => 'Tecnología en Acabados de Construcción'],
    ['id' => '4', 'nombre' => 'Técnico en Obras Civiles'],
];

$mockFichas = [
    ['id' => '1', 'numero' => '2567890'],
    ['id' => '2', 'numero' => '2567891'],
    ['id' => '3', 'numero' => '2567892'],
    ['id' => '4', 'numero' => '2567893'],
];

$reportTypes = [
    [
        'id' => 'consumo-ficha',
        'title' => 'Consumo por Ficha',
        'description' => 'Detalle de materiales consumidos por cada ficha de formación',
        'icon' => 'file-text'
    ],
    [
        'id' => 'consumo-programa',
        'title' => 'Consumo por Programa',
        'description' => 'Análisis de consumo de materiales por programa de formación',
        'icon' => 'bar-chart-3'
    ],
    [
        'id' => 'consumo-rae',
        'title' => 'Consumo por RAE',
        'description' => 'Materiales utilizados por resultado de aprendizaje',
        'icon' => 'pie-chart'
    ],
    [
        'id' => 'movimientos',
        'title' => 'Historial de Movimientos',
        'description' => 'Registro completo de entradas, salidas y devoluciones',
        'icon' => 'trending-up'
    ],
    [
        'id' => 'material-faltante',
        'title' => 'Material Faltante',
        'description' => 'Listado de materiales con stock bajo o agotado',
        'icon' => 'package'
    ],
];

// Calcular totales (MOCK)
$totalConsumo = array_sum(array_column($consumoPorFicha, 'consumo'));
$totalCosto = array_sum(array_column($consumoPorFicha, 'costo'));

// Tab activa
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'estadisticas';

// Función para formatear números en formato colombiano
function formatCOP($number)
{
    return '$' . number_format($number, 0, ',', '.');
}

// =====================================================
// ✅ ADAPTACIÓN SIDEBAR (SIN TOCAR TU BASE)
// =====================================================
$collapsed = isset($_GET['coll']) && $_GET['coll'] == '1';
$contentOffsetClass = $collapsed ? 'lg:pl-[90px]' : 'lg:pl-[280px]';


// =====================================================
// ✅ BACKEND STATUS (para saber si usa BD o MOCK)
// =====================================================
$__REPORTES_SOURCE = "MOCK";
$__REPORTES_ERROR  = null;

try {
    // ✅ Ajusta rutas si tu estructura cambia
    $dbPath    = __DIR__ . "/../../../Config/database.php";
    $modelPath = __DIR__ . "/../../models/ReportesModel.php";

    // ✅ Si no existe, fuerza error para verlo con debug
    if (!file_exists($dbPath)) {
        throw new Exception("No existe database.php en: " . $dbPath);
    }
    if (!file_exists($modelPath)) {
        throw new Exception("No existe ReportesModel.php en: " . $modelPath);
    }

    require_once $dbPath;
    require_once $modelPath;

    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new Exception("La variable \$conn no es PDO. Revisa tu Config/database.php");
    }

    // ✅ Fuerza errores SQL visibles (para detectar tablas/columnas)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $modelReportes = new ReportesModel($conn);

    // =====================================================
    // ✅ Capturar filtros desde GET (FIX: INCLUYE BODEGA / SUBBODEGA)
    // =====================================================
    $filters = [
        "fecha_inicio" => $_GET["fecha_inicio"] ?? null,
        "fecha_fin"    => $_GET["fecha_fin"] ?? null,
        "programa"     => $_GET["programa"] ?? "all",
        "ficha"        => $_GET["ficha"] ?? "all",

        // ✅ NUEVO (FIX DEFINITIVO)
        "bodega"       => $_GET["bodega"] ?? "all",
        "subbodega"    => $_GET["subbodega"] ?? "all",
    ];

    // ✅ Combos reales
    $mockProgramas = $modelReportes->getProgramas();
    $mockFichas    = $modelReportes->getFichas();

    // ✅ Bodegas reales (si existe el método, si no, no rompe)
    $mockBodegas = [];
    if (method_exists($modelReportes, "getBodegas")) {
        $mockBodegas = $modelReportes->getBodegas();
    }

    // ✅ Dashboard real
    $dashboard = $modelReportes->getDashboardData($filters);

    if (!is_array($dashboard)) {
        throw new Exception("getDashboardData() no devolvió un array");
    }

    // ✅ IMPORTANTE: sobreescribimos SIEMPRE (aunque venga vacío)
    $consumoPorMes       = $dashboard["consumoPorMes"]       ?? [];
    $consumoPorPrograma  = $dashboard["consumoPorPrograma"]  ?? [];
    $materialesMasUsados = $dashboard["materialesMasUsados"] ?? [];
    $consumoPorFicha     = $dashboard["consumoPorFicha"]     ?? [];

    $totalConsumo = (int)($dashboard["totalConsumo"] ?? 0);
    $totalCosto   = (int)($dashboard["totalCosto"] ?? 0);

    // ✅ Colores para leyenda si backend no manda color
    $legendColors = ['#6CC24A', '#007832', '#002B49', '#71277A'];
    foreach ($consumoPorPrograma as $i => &$item) {
        if (!isset($item["color"]) || !$item["color"]) {
            $item["color"] = $legendColors[$i] ?? '#007832';
        }
    }
    unset($item);

    $__REPORTES_SOURCE = "BD ✅";

} catch (Throwable $e) {
    $__REPORTES_SOURCE = "MOCK ❌";
    $__REPORTES_ERROR  = $e->getMessage();

    // ✅ Si activas debug=1 en la URL, te muestra el error real
    if (isset($_GET["debug"]) && $_GET["debug"] == "1") {
        echo "<pre style='background:#111;color:#0f0;padding:14px;border-radius:10px;max-width:100%;overflow:auto'>";
        echo "REPORTES DEBUG MODE\n";
        echo "SOURCE: {$__REPORTES_SOURCE}\n";
        echo "ERROR: {$__REPORTES_ERROR}\n";
        echo "</pre>";
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Estadísticas - SIGA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="src/assets/css/globals.css">
    <link rel="stylesheet" href="src/assets/css/reportes/reportes.css">
</head>
<body class="min-h-screen">

<!-- ✅ TOASTS FLOWBITE (GLOBAL) -->
<div id="flowbiteToastRoot"
     class="fixed top-6 right-6 z-[9999] flex flex-col gap-3 w-full max-w-sm pointer-events-none">
</div>


    <!-- ✅ SOLO ADAPTACIÓN: el contenido ahora respeta sidebar colapsado/expandido -->
    <div class="page-with-sidebar w-full px-6 pt-8 pb-8 <?php echo $contentOffsetClass; ?>">

        <div class="space-y-6 animate-fade-in-up">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">Reportes y Estadísticas</h1>
                    <p class="text-muted-foreground">
                        Genera reportes detallados y visualiza estadísticas del inventario
                    </p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="w-full">
                <div class="inline-flex bg-muted p-1 rounded-lg gap-1 max-w-md w-full">
                    <a href="?page=reportes&tab=estadisticas<?php echo $collapsed ? '&coll=1' : ''; ?>"
                       class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-md transition-all
                              <?= $activeTab === 'estadisticas' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' ?>">
                        <svg class="w-4 h-4"
                             xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">
                            <line x1="18" x2="18" y1="20" y2="10"/>
                            <line x1="12" x2="12" y1="20" y2="4"/>
                            <line x1="6" x2="6" y1="20" y2="14"/>
                        </svg>
                        Estadísticas
                    </a>

                    <a href="?page=reportes&tab=reportes<?php echo $collapsed ? '&coll=1' : ''; ?>"
                       class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-md transition-all
                              <?= $activeTab === 'reportes' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' ?>">
                        <svg class="w-4 h-4"
                             xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                            <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                        </svg>
                        Reportes PDF
                    </a>
                </div>

                <!-- Tab Content: Estadísticas -->
                <?php if ($activeTab === 'estadisticas'): ?>
                    <div class="mt-6 space-y-6">
                        <!-- Filtros -->
                        <div class="bg-card border border-border rounded-lg shadow-sm">
                            <div class="p-6 pb-3">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-muted-foreground"
                                         xmlns="http://www.w3.org/2000/svg"
                                         viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="2"
                                         stroke-linecap="round"
                                         stroke-linejoin="round">
                                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                                    </svg>
                                    <h3 class="text-base font-semibold text-foreground">Filtros</h3>
                                </div>
                            </div>
                            <div class="p-6 pt-3">
                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground" for="fecha-inicio">
                                            Fecha inicio
                                        </label>
                                        <input type="date"
                                               id="fecha-inicio"
                                               name="fecha_inicio"
                                               class="input-siga w-full">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground" for="fecha-fin">
                                            Fecha fin
                                        </label>
                                        <input type="date"
                                               id="fecha-fin"
                                               name="fecha_fin"
                                               class="input-siga w-full">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground" for="programa">
                                            Programa
                                        </label>
                                        <select id="programa"
                                                name="programa"
                                                class="input-siga w-full">
                                            <option value="all">Todos</option>
                                            <?php foreach ($mockProgramas as $p): ?>
                                                <option value="<?= htmlspecialchars($p['id']) ?>">
                                                    <?= htmlspecialchars($p['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground" for="ficha">
                                            Ficha
                                        </label>
                                        <select id="ficha"
                                                name="ficha"
                                                class="input-siga w-full">
                                            <option value="all">Todas</option>
                                            <?php foreach ($mockFichas as $f): ?>
                                                <option value="<?= htmlspecialchars($f['id']) ?>">
                                                    <?= htmlspecialchars($f['numero']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Gráficas principales -->
                        <div class="grid gap-6 lg:grid-cols-2">
                            <!-- Consumo mensual -->
                            <div class="bg-card border border-border rounded-lg shadow-sm">
                                <div class="p-6 pb-3">
                                    <h3 class="text-base font-semibold text-foreground">Consumo vs Devoluciones</h3>
                                    <p class="text-sm text-muted-foreground mt-1">
                                        Comparativa mensual de movimientos
                                    </p>
                                </div>
                                <div class="p-6 pt-3">
                                    <div class="h-[300px]">
                                        <canvas id="chartConsumoMensual"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Distribución por programa -->
                            <div class="bg-card border border-border rounded-lg shadow-sm">
                                <div class="p-6 pb-3">
                                    <h3 class="text-base font-semibold text-foreground">Distribución por Programa</h3>
                                    <p class="text-sm text-muted-foreground mt-1">
                                        Porcentaje de consumo por programa
                                    </p>
                                </div>
                                <div class="p-6 pt-3">
                                    <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                                        <div class="h-[250px] w-[250px]">
                                            <canvas id="chartPrograma"></canvas>
                                        </div>
                                        <div class="space-y-3">
                                            <?php foreach ($consumoPorPrograma as $item): ?>
                                                <div class="flex items-center gap-3">
                                                    <span class="h-3 w-3 rounded-full"
                                                          style="background-color: <?= $item['color'] ?>"></span>
                                                    <span class="text-sm font-medium"
                                                          style="color: <?= $item['color'] ?>">
                                                        <?= htmlspecialchars($item['name']) ?>
                                                    </span>
                                                    <span class="ml-auto inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-muted text-foreground">
                                                        <?= $item['value'] ?>%
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Materiales más usados -->
                        <div class="bg-card border border-border rounded-lg shadow-sm">
                            <div class="p-6 pb-3">
                                <h3 class="text-base font-semibold text-foreground">Materiales Más Usados</h3>
                                <p class="text-sm text-muted-foreground mt-1">
                                    Top 5 materiales con mayor consumo
                                </p>
                            </div>
                            <div class="p-6 pt-3">
                                <div class="h-[250px]">
                                    <canvas id="chartMateriales"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla de consumo por ficha -->
                        <div class="bg-card border border-border rounded-lg shadow-sm">
                            <div class="p-6 pb-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-base font-semibold text-foreground">Consumo por Ficha</h3>
                                        <p class="text-sm text-muted-foreground mt-1">
                                            Detalle de consumo y costos por ficha
                                        </p>
                                    </div>
                                    <button id="btnExportConsumoFicha" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border border-border rounded-md bg-transparent text-foreground hover:bg-muted transition-colors">
                                        <svg class="w-4 h-4"
                                             xmlns="http://www.w3.org/2000/svg"
                                             viewBox="0 0 24 24"
                                             fill="none"
                                             stroke="currentColor"
                                             stroke-width="2"
                                             stroke-linecap="round"
                                             stroke-linejoin="round">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                            <polyline points="7 10 12 15 17 10"/>
                                            <line x1="12" x2="12" y1="15" y2="3"/>
                                        </svg>
                                        Exportar
                                    </button>
                                </div>
                            </div>
                            <div class="p-6 pt-3">
                                <div class="rounded-lg border border-border overflow-hidden">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="bg-muted/50">
                                                <th class="text-left p-3 text-sm font-semibold text-foreground">Ficha</th>
                                                <th class="text-left p-3 text-sm font-semibold text-foreground">Programa</th>
                                                <th class="text-right p-3 text-sm font-semibold text-foreground">Consumo</th>
                                                <th class="text-right p-3 text-sm font-semibold text-foreground">Costo Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($consumoPorFicha as $item): ?>
                                                <tr class="border-t border-border hover:bg-muted/30 transition-colors">
                                                    <td class="p-3">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-border">
                                                            <?= htmlspecialchars($item['ficha']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="p-3 text-sm text-foreground">
                                                        <?= htmlspecialchars($item['programa']) ?>
                                                    </td>
                                                    <td class="p-3 text-sm text-right font-medium text-foreground">
                                                        <?= $item['consumo'] ?> uds
                                                    </td>
                                                    <td class="p-3 text-sm text-right font-medium text-foreground">
                                                        <?= formatCOP($item['costo']) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="border-t border-border bg-muted/30">
                                                <td colspan="2" class="p-3 text-sm font-semibold text-foreground">
                                                    Total
                                                </td>
                                                <td class="p-3 text-sm text-right font-semibold text-foreground">
                                                    <?= $totalConsumo ?> uds
                                                </td>
                                                <td class="p-3 text-sm text-right font-semibold text-foreground">
                                                    <?= formatCOP($totalCosto) ?>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tab Content: Reportes PDF -->
                <?php if ($activeTab === 'reportes'): ?>
                    <div class="mt-6 space-y-6">
                        <!-- Report Cards Grid -->
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <?php foreach ($reportTypes as $report): ?>
                                <div class="bg-card border border-border rounded-lg shadow-sm hover:shadow-lg transition-shadow">
                                    <div class="p-6 pb-3">
                                        <div class="flex items-start gap-4">
                                            <div class="rounded-2xl p-3"
                                                 style="background-color: rgba(0, 120, 50, 0.08);">
                                                <?php if ($report['icon'] === 'file-text'): ?>
                                                    <svg class="w-5 h-5 text-secondary"
                                                         style="color:#007832;"
                                                         xmlns="http://www.w3.org/2000/svg"
                                                         viewBox="0 0 24 24"
                                                         fill="none"
                                                         stroke="currentColor"
                                                         stroke-width="2"
                                                         stroke-linecap="round"
                                                         stroke-linejoin="round">
                                                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                                                        <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                                                        <path d="M10 9H8"/>
                                                        <path d="M16 13H8"/>
                                                        <path d="M16 17H8"/>
                                                    </svg>
                                                <?php elseif ($report['icon'] === 'bar-chart-3'): ?>
                                                    <svg class="w-5 h-5 text-secondary"
                                                         style="color:#007832;"
                                                         xmlns="http://www.w3.org/2000/svg"
                                                         viewBox="0 0 24 24"
                                                         fill="none"
                                                         stroke="currentColor"
                                                         stroke-width="2"
                                                         stroke-linecap="round"
                                                         stroke-linejoin="round">
                                                        <line x1="18" x2="18" y1="20" y2="10"/>
                                                        <line x1="12" x2="12" y1="20" y2="4"/>
                                                        <line x1="6" x2="6" y1="20" y2="14"/>
                                                    </svg>
                                                <?php elseif ($report['icon'] === 'pie-chart'): ?>
                                                    <svg class="w-5 h-5 text-secondary"
                                                         style="color:#007832;"
                                                         xmlns="http://www.w3.org/2000/svg"
                                                         viewBox="0 0 24 24"
                                                         fill="none"
                                                         stroke="currentColor"
                                                         stroke-width="2"
                                                         stroke-linecap="round"
                                                         stroke-linejoin="round">
                                                        <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                                                        <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                                                    </svg>
                                                <?php elseif ($report['icon'] === 'trending-up'): ?>
                                                    <svg class="w-5 h-5 text-secondary"
                                                         style="color:#007832;"
                                                         xmlns="http://www.w3.org/2000/svg"
                                                         viewBox="0 0 24 24"
                                                         fill="none"
                                                         stroke="currentColor"
                                                         stroke-width="2"
                                                         stroke-linecap="round"
                                                         stroke-linejoin="round">
                                                        <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                                                        <polyline points="16 7 22 7 22 13"/>
                                                    </svg>
                                                <?php elseif ($report['icon'] === 'package'): ?>
                                                    <svg class="w-5 h-5 text-secondary"
                                                         style="color:#007832;"
                                                         xmlns="http://www.w3.org/2000/svg"
                                                         viewBox="0 0 24 24"
                                                         fill="none"
                                                         stroke="currentColor"
                                                         stroke-width="2"
                                                         stroke-linecap="round"
                                                         stroke-linejoin="round">
                                                        <path d="m7.5 4.27 9 5.15"/>
                                                        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                                                        <path d="m3.3 7 8.7 5 8.7-5"/>
                                                        <path d="M12 22V12"/>
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1">
                                                <h3 class="text-base font-semibold text-foreground">
                                                    <?= htmlspecialchars($report['title']) ?>
                                                </h3>
                                                <p class="text-sm text-muted-foreground mt-1">
                                                    <?= htmlspecialchars($report['description']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-6 pt-3">
                                        <div class="flex gap-2">
                                            <button onclick="handleGenerateReport('<?= $report['id'] ?>')"
                                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium bg-secondary text-secondary-foreground rounded-md hover:opacity-90 transition-opacity">
                                                <svg class="w-4 h-4"
                                                     xmlns="http://www.w3.org/2000/svg"
                                                     viewBox="0 0 24 24"
                                                     fill="none"
                                                     stroke="currentColor"
                                                     stroke-width="2"
                                                     stroke-linecap="round"
                                                     stroke-linejoin="round">
                                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                    <polyline points="7 10 12 15 17 10"/>
                                                    <line x1="12" x2="12" y1="15" y2="3"/>
                                                </svg>
                                                Generar PDF
                                            </button>
                                            <button data-print-btn="1" class="inline-flex items-center justify-center p-2 border border-border rounded-md text-foreground hover:bg-muted transition-colors">
                                                <svg class="w-4 h-4"
                                                     xmlns="http://www.w3.org/2000/svg"
                                                     viewBox="0 0 24 24"
                                                     fill="none"
                                                     stroke="currentColor"
                                                     stroke-width="2"
                                                     stroke-linecap="round"
                                                     stroke-linejoin="round">
                                                    <polyline points="6 9 6 2 18 2 18 9"/>
                                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                                    <rect width="12" height="8" x="6" y="14"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Configuración de reporte personalizado -->
                        <div class="bg-card border border-border rounded-lg shadow-sm">
                            <div class="p-6 pb-3">
                                <h3 class="text-base font-semibold text-foreground">Configurar Reporte Personalizado</h3>
                                <p class="text-sm text-muted-foreground mt-1">
                                    Selecciona los parámetros para generar un reporte a medida
                                </p>
                            </div>
                            <div class="p-6 pt-3 space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground">
                                            Tipo de reporte
                                        </label>
                                        <select class="input-siga w-full">
                                            <option value="consumo">Consumo de materiales</option>
                                            <option value="movimientos">Movimientos</option>
                                            <option value="stock">Estado de stock</option>
                                            <option value="auditoria">Auditoría</option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground">
                                            Fecha inicio
                                        </label>
                                        <input type="date" class="input-siga w-full">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground">
                                            Fecha fin
                                        </label>
                                        <input type="date" class="input-siga w-full">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground">
                                            Formato
                                        </label>
                                        <select class="input-siga w-full">
                                            <option value="pdf">PDF</option>
                                            <option value="excel">Excel</option>
                                            <option value="csv">CSV</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-4">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground">
                                            Programa
                                        </label>
                                        <select id="custom-programa" class="input-siga w-full">
                                            <option value="all">Todos los programas</option>
                                            <?php foreach ($mockProgramas as $p): ?>
                                                <option value="<?= htmlspecialchars($p['id']) ?>">
                                                    <?= htmlspecialchars($p['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground">
                                            Bodega
                                        </label>
                                        <select id="custom-bodega" class="input-siga w-full">
                                            <option value="all">Todas las bodegas</option>

                                            <?php if (!empty($mockBodegas) && is_array($mockBodegas)): ?>
                                                <?php foreach ($mockBodegas as $b): ?>
                                                    <option value="<?= htmlspecialchars($b['id']) ?>">
                                                        <?= htmlspecialchars($b['nombre']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <!-- ✅ NUEVO: Subbodega -->
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground">
                                            Subbodega
                                        </label>
                                        <select id="custom-subbodega" class="input-siga w-full" disabled>
                                            <option value="all">Todas las subbodegas</option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground">
                                            Incluir gráficas
                                        </label>
                                        <select id="custom-graficas" class="input-siga w-full">
                                            <option value="yes">Sí</option>
                                            <option value="no">No</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-4">
                                    <button class="inline-flex items-center gap-2 px-6 py-2 text-sm font-medium bg-secondary text-secondary-foreground rounded-md hover:opacity-90 transition-opacity">
                                        <svg class="w-4 h-4"
                                             xmlns="http://www.w3.org/2000/svg"
                                             viewBox="0 0 24 24"
                                             fill="none"
                                             stroke="currentColor"
                                             stroke-width="2"
                                             stroke-linecap="round"
                                             stroke-linejoin="round">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                            <polyline points="7 10 12 15 17 10"/>
                                            <line x1="12" x2="12" y1="15" y2="3"/>
                                        </svg>
                                        Generar Reporte
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>

        console.log("✅ Reportes source:", <?= json_encode($__REPORTES_SOURCE) ?>);
        console.log("⚠️ Reportes error:", <?= json_encode($__REPORTES_ERROR) ?>);

        // Datos PHP pasados a JavaScript
        const consumoPorMes       = <?= json_encode($consumoPorMes) ?>;
        const consumoPorPrograma  = <?= json_encode($consumoPorPrograma) ?>;
        const materialesMasUsados = <?= json_encode($materialesMasUsados) ?>;

        // Colores del tema SENA
        const chartColors = {
            primary:    '#39A900',
            secondary:  '#007832',
            accent:     '#50E5F9',
            warning:    '#FDC300',
            purple:     '#71277A',
            navy:       '#002B49',
            lightGreen: '#6CC24A',
            foreground: '#00304D',
            border:     'rgba(0, 48, 77, 0.12)'
        };

        <?php if ($activeTab === 'estadisticas'): ?>
        // Gráfico de Consumo vs Devoluciones
        const ctxConsumo = document.getElementById('chartConsumoMensual').getContext('2d');
        new Chart(ctxConsumo, {
            type: 'bar',
            data: {
                labels: consumoPorMes.map(d => d.mes),
                datasets: [
                    {
                        label: 'Consumo',
                        data: consumoPorMes.map(d => d.consumo),
                        backgroundColor: chartColors.secondary,
                        borderRadius: 4
                    },
                    {
                        label: 'Devoluciones',
                        data: consumoPorMes.map(d => d.devoluciones),
                        backgroundColor: chartColors.navy,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: chartColors.foreground,
                            font: { family: 'Inter' }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: chartColors.foreground },
                        grid: { color: chartColors.border }
                    },
                    y: {
                        ticks: { color: chartColors.foreground },
                        grid: { color: chartColors.border }
                    }
                }
            }
        });

        // Gráfico de Distribución por Programa (Dona)
        const ctxPrograma = document.getElementById('chartPrograma').getContext('2d');
        new Chart(ctxPrograma, {
            type: 'doughnut',
            data: {
                labels: consumoPorPrograma.map(d => d.name),
                datasets: [{
                    data: consumoPorPrograma.map(d => d.value),
                    backgroundColor: [
                        chartColors.lightGreen,
                        chartColors.secondary,
                        chartColors.navy,
                        chartColors.purple
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: { legend: { display: false } }
            }
        });

        // Gráfico de Materiales más usados (Barras horizontales)
        const ctxMateriales = document.getElementById('chartMateriales').getContext('2d');
        new Chart(ctxMateriales, {
            type: 'bar',
            data: {
                labels: materialesMasUsados.map(d => d.nombre),
                datasets: [{
                    label: 'Cantidad',
                    data: materialesMasUsados.map(d => d.cantidad),
                    backgroundColor: chartColors.secondary,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: { color: chartColors.foreground },
                        grid: { color: chartColors.border }
                    },
                    y: {
                        ticks: { color: chartColors.foreground },
                        grid: { display: false }
                    }
                }
            }
        });
        <?php endif; ?>

        // =========================
        // FLOWBITE-STYLE ALERTS (IGUAL A USUARIOS.JS)
        // =========================
        function getOrCreateFlowbiteContainer() {
          let container = document.getElementById("flowbite-alert-container");

          if (!container) {
            container = document.createElement("div");
            container.id = "flowbite-alert-container";

            container.className =
              "fixed top-6 left-1/2 -translate-x-1/2 z-[9999] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none";

            container.style.left = "auto";
            container.style.right = "1.5rem";
            container.style.transform = "none";

            document.body.appendChild(container);
          }

          return container;
        }

        function showFlowbiteAlert(type, message) {
          const container = getOrCreateFlowbiteContainer();
          const wrapper = document.createElement("div");

          let borderColor = "border-amber-500";
          let textColor = "text-amber-900";
          let titleText = "Advertencia";

          let iconSVG = `
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
                 fill="currentColor" viewBox="0 0 20 20">
              <path d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.59A1.75 1.75 0 0 1 16.768 17H3.232a1.75 1.75 0 0 1-1.492-2.311L8.257 3.1z"/>
              <path d="M11 13H9V9h2zm0 3H9v-2h2z" fill="#fff"/>
            </svg>
          `;

          if (type === "success") {
            borderColor = "border-emerald-500";
            textColor = "text-emerald-900";
            titleText = "Éxito";
            iconSVG = `
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
                   fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm-1 15-4-4 1.414-1.414L9 12.172l4.586-4.586L15 9z"/>
              </svg>
            `;
          }

          if (type === "info") {
            borderColor = "border-blue-500";
            textColor = "text-blue-900";
            titleText = "Información";
            iconSVG = `
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
                   fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm1 15H9v-5h2Zm0-7H9V6h2Z"/>
              </svg>
            `;
          }

          wrapper.className = `
            relative flex items-center w-full mx-auto pointer-events-auto
            rounded-2xl border-l-4 ${borderColor} bg-white shadow-md
            px-4 py-3 text-sm ${textColor}
            opacity-0 -translate-y-2
            transition-all duration-300 ease-out
            animate-fade-in-up
          `;

          wrapper.innerHTML = `
            <div class="flex-shrink-0 mr-3 text-current">
              ${iconSVG}
            </div>

            <div class="flex-1 min-w-0">
              <p class="font-semibold">${titleText}</p>
              <p class="mt-0.5 text-sm">${message}</p>
            </div>
          `;

          container.appendChild(wrapper);

          requestAnimationFrame(() => {
            wrapper.classList.remove("opacity-0", "-translate-y-2");
            wrapper.classList.add("opacity-100", "translate-y-0");
          });

          setTimeout(() => {
            wrapper.classList.add("opacity-0", "-translate-y-2");
            wrapper.classList.remove("opacity-100", "translate-y-0");
            setTimeout(() => wrapper.remove(), 250);
          }, 4000);
        }

        function toastError(message) {
          showFlowbiteAlert("warning", message);
        }

        function toastSuccess(message) {
          showFlowbiteAlert("success", message);
        }

        function toastInfo(message) {
          showFlowbiteAlert("info", message);
        }

        function showFlowbiteToast(type = "info", title = "Información", message = "") {
          const msgFinal = message ? message : title;

          if (type === "success") return toastSuccess(msgFinal);
          if (type === "warning") return toastError(msgFinal);
          if (type === "error") return toastError(msgFinal);
          return toastInfo(msgFinal);
        }

        // =====================================================
        // ✅ SPINNER REUTILIZABLE DENTRO DE BOTONES (SIN ALERTAS)
        // =====================================================
        function __setBtnLoading(btn, loading, loadingText = "Generando...") {
        if (!btn) return;

        if (!btn.dataset.originalHtml) {
            btn.dataset.originalHtml = btn.innerHTML;
        }

        if (loading) {
            btn.disabled = true;
            btn.classList.add("opacity-80", "cursor-not-allowed");
            btn.innerHTML = `
            <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 0 1 8-8v3.2a4.8 4.8 0 0 0-4.8 4.8H4z"></path>
            </svg>
            ${loadingText}
            `;
        } else {
            btn.disabled = false;
            btn.classList.remove("opacity-80", "cursor-not-allowed");
            btn.innerHTML = btn.dataset.originalHtml;
        }
        }


        // =====================================================
        // ✅ INTEGRACIÓN DE FILTROS (SIN DAÑAR TU BASE)
        //   - guarda filtros en la URL (GET)
        //   - el backend los lee y devuelve data real
        //   ✅ FIX: preserva bodega/subbodega si ya existen en URL
        // =====================================================
        (function () {
            const params = new URLSearchParams(window.location.search);

            const fechaInicio = document.getElementById("fecha-inicio");
            const fechaFin    = document.getElementById("fecha-fin");
            const programa    = document.getElementById("programa");
            const ficha       = document.getElementById("ficha");

            if (fechaInicio) fechaInicio.value = params.get("fecha_inicio") || "";
            if (fechaFin)    fechaFin.value    = params.get("fecha_fin") || "";
            if (programa)    programa.value    = params.get("programa") || "all";
            if (ficha)       ficha.value       = params.get("ficha") || "all";

            function applyFiltersToURL() {
                const newParams = new URLSearchParams(window.location.search);

                // conservar page/tab/coll
                newParams.set("page", "reportes");
                newParams.set("tab", "estadisticas");

                if (fechaInicio && fechaInicio.value) newParams.set("fecha_inicio", fechaInicio.value);
                else newParams.delete("fecha_inicio");

                if (fechaFin && fechaFin.value) newParams.set("fecha_fin", fechaFin.value);
                else newParams.delete("fecha_fin");

                if (programa && programa.value) newParams.set("programa", programa.value);
                else newParams.set("programa", "all");

                if (ficha && ficha.value) newParams.set("ficha", ficha.value);
                else newParams.set("ficha", "all");

                // ✅ FIX: preservar filtros custom si ya están en URL
                if (params.get("bodega")) newParams.set("bodega", params.get("bodega"));
                if (params.get("subbodega")) newParams.set("subbodega", params.get("subbodega"));

                // mantener coll si existe
                if (params.get("coll") === "1") newParams.set("coll", "1");

                window.location.search = newParams.toString();
            }

            if (fechaInicio) fechaInicio.addEventListener("change", applyFiltersToURL);
            if (fechaFin)    fechaFin.addEventListener("change", applyFiltersToURL);
            if (programa)    programa.addEventListener("change", applyFiltersToURL);
            if (ficha)       ficha.addEventListener("change", applyFiltersToURL);
        })();

        // =====================================================
        // ✅ Helper global: descarga PDF/CSV/Excel sin recargar (iframe invisible)
        // =====================================================
        function __downloadSamePage(urlFile) {
            let iframe = document.getElementById("pdfDownloadFrame");
            if (!iframe) {
                iframe = document.createElement("iframe");
                iframe.id = "pdfDownloadFrame";
                iframe.style.display = "none";
                document.body.appendChild(iframe);
            }

            const sep = urlFile.includes("?") ? "&" : "?";
            iframe.src = urlFile + sep + "t=" + Date.now();
        }

        // =====================================================================
        // ✅✅✅ FIX CENTRAL: AHORA LOS FILTROS INCLUYEN BODEGA/SUBBODEGA
        // =====================================================================
        function __getReportFiltersFromURL() {
          const params = new URLSearchParams(window.location.search);

          return {
            fecha_inicio: params.get("fecha_inicio") || "",
            fecha_fin:    params.get("fecha_fin") || "",
            programa:     params.get("programa") || "all",
            ficha:        params.get("ficha") || "all",

            // ✅ NUEVO (FIX)
            bodega:       params.get("bodega") || "all",
            subbodega:    params.get("subbodega") || "all",
          };
        }

        async function __checkReportHasData(type) {
          const f = __getReportFiltersFromURL();

          const url =
            `src/controllers/reportes_controller.php?action=check_data&type=${encodeURIComponent(type)}`
            + `&fecha_inicio=${encodeURIComponent(f.fecha_inicio)}`
            + `&fecha_fin=${encodeURIComponent(f.fecha_fin)}`
            + `&programa=${encodeURIComponent(f.programa)}`
            + `&ficha=${encodeURIComponent(f.ficha)}`
            + `&bodega=${encodeURIComponent(f.bodega)}`
            + `&subbodega=${encodeURIComponent(f.subbodega)}`;

          const res = await fetch(url, { method: "GET" });
          const data = await res.json().catch(() => null);
          return data;
        }

        // =====================================================
        // ✅ EXPORTAR PDF: Consumo por ficha
        // ✅ FIX: AHORA VALIDA Y MANDA BODEGA/SUBBODEGA
        // =====================================================
        // =====================================================
// ✅ EXPORTAR PDF: Consumo por ficha (CON SPINNER EN BOTÓN)
// =====================================================
(function () {
  const btn = document.getElementById("btnExportConsumoFicha");
  if (!btn) return;

  btn.addEventListener("click", async () => {
    try {
      __setBtnLoading(btn, true, "Exportando...");

      const check = await __checkReportHasData("consumo-ficha");

      if (!check || check.ok === false) {
        showFlowbiteToast("error", "Validación no disponible", check?.message || "No fue posible validar el reporte.");
        __setBtnLoading(btn, false);
        return;
      }

      if (check.hasData === false) {
        showFlowbiteToast("info", "Sin resultados", check.message || "No hay datos con los filtros seleccionados.");
        __setBtnLoading(btn, false);
        return;
      }

      // ✅ SI HAY DATA → DESCARGAR
      const f = __getReportFiltersFromURL();

      const url = `src/controllers/reportes_controller.php?action=export_consumo_ficha`
        + `&fecha_inicio=${encodeURIComponent(f.fecha_inicio)}`
        + `&fecha_fin=${encodeURIComponent(f.fecha_fin)}`
        + `&programa=${encodeURIComponent(f.programa)}`
        + `&ficha=${encodeURIComponent(f.ficha)}`
        + `&bodega=${encodeURIComponent(f.bodega)}`
        + `&subbodega=${encodeURIComponent(f.subbodega)}`;

      __downloadSamePage(url);

      // ✅ Quitamos toast de éxito (porque tú NO quieres alertas al descargar)
      setTimeout(() => __setBtnLoading(btn, false), 1200);

    } catch (err) {
      console.error(err);
      __setBtnLoading(btn, false);
      showFlowbiteToast("error", "Error", "Ocurrió un problema al exportar el PDF.");
    }
  });
})();


        // =====================================================
        // ✅ Generar PDF por tarjeta (con validación subbodega)
        // =====================================================
        // =====================================================
// ✅ Generar PDF por tarjeta (SPINNER EN EL BOTÓN - SIN TOAST ÉXITO)
// =====================================================
async function handleGenerateReport(type) {
  const btn = (window.event && window.event.currentTarget) ? window.event.currentTarget : null;

  try {
    __setBtnLoading(btn, true, "Generando...");

    const check = await __checkReportHasData(type);

    if (!check || check.ok === false) {
      showFlowbiteToast("error", "Validación no disponible", check?.message || "No fue posible validar la información del reporte.");
      __setBtnLoading(btn, false);
      return;
    }

    if (check.hasData === false) {
      showFlowbiteToast("info", "Sin resultados", check.message || "No se encontraron resultados con estos filtros.");
      __setBtnLoading(btn, false);
      return;
    }

    const f = __getReportFiltersFromURL();

    const url =
      `src/controllers/reportes_controller.php?action=generate_pdf&type=${encodeURIComponent(type)}`
      + `&fecha_inicio=${encodeURIComponent(f.fecha_inicio)}`
      + `&fecha_fin=${encodeURIComponent(f.fecha_fin)}`
      + `&programa=${encodeURIComponent(f.programa)}`
      + `&ficha=${encodeURIComponent(f.ficha)}`
      + `&bodega=${encodeURIComponent(f.bodega)}`
      + `&subbodega=${encodeURIComponent(f.subbodega)}`;

    __downloadSamePage(url);

    // ✅ SIN TOAST DE ÉXITO
    setTimeout(() => __setBtnLoading(btn, false), 1200);

  } catch (err) {
    console.error(err);
    __setBtnLoading(btn, false);
    showFlowbiteToast("error", "Error al generar", "Ocurrió un problema al generar el PDF.");
  }
}


        // =====================================================
        // ✅ Imprimir por tarjeta (con validación subbodega)
        // =====================================================
        async function handlePrintReport(type) {
          try {
            const check = await __checkReportHasData(type);

            if (!check || check.ok === false) {
              showFlowbiteToast("error", "Validación no disponible", check?.message || "No fue posible validar el reporte.");
              return;
            }

            if (check.hasData === false) {
              showFlowbiteToast("info", "Sin resultados", check.message || "No hay datos para imprimir con esos filtros.");
              return;
            }

            const f = __getReportFiltersFromURL();

            const url =
              `src/controllers/reportes_controller.php?action=print_view&type=${encodeURIComponent(type)}`
              + `&fecha_inicio=${encodeURIComponent(f.fecha_inicio)}`
              + `&fecha_fin=${encodeURIComponent(f.fecha_fin)}`
              + `&programa=${encodeURIComponent(f.programa)}`
              + `&ficha=${encodeURIComponent(f.ficha)}`
              + `&bodega=${encodeURIComponent(f.bodega)}`
              + `&subbodega=${encodeURIComponent(f.subbodega)}`
              + `&auto=1`;

            const w = 980;
            const h = 720;
            const left = Math.max(0, (window.screen.width - w) / 2);
            const top  = Math.max(0, (window.screen.height - h) / 2);

            const popup = window.open(url, "SIGA_PRINT", `width=${w},height=${h},top=${top},left=${left},resizable=yes,scrollbars=yes`);
            if (!popup) window.open(url, "_blank");

          } catch (err) {
            console.error(err);
            showFlowbiteToast("error", "Error al imprimir", "No fue posible abrir la vista de impresión.");
          }
        }

        // ✅ Enlazar impresión (sin tocar HTML)
        (function () {
          document.addEventListener("click", function (e) {
            const btn = e.target.closest("button[data-print-btn='1']");
            if (!btn) return;

            e.preventDefault();

            const card = btn.closest(".bg-card");
            if (!card) return;

            const btnGenerate = card.querySelector("button[onclick*=\"handleGenerateReport(\"]");
            if (!btnGenerate) return;

            const onclick = btnGenerate.getAttribute("onclick") || "";
            const match = onclick.match(/handleGenerateReport\(['"]([^'"]+)['"]\)/);

            if (!match || !match[1]) return;

            const reportId = match[1];
            handlePrintReport(reportId);
          });
        })();

        // =====================================================
        // ✅ SUBBODEGAS DINÁMICAS (CUSTOM REPORT)
        // =====================================================
        (function () {
          const bodegaSelect = document.getElementById("custom-bodega");
          const subbodegaSelect = document.getElementById("custom-subbodega");

          if (!bodegaSelect || !subbodegaSelect) return;

          function resetSubbodegas() {
            subbodegaSelect.innerHTML = "";
            subbodegaSelect.appendChild(new Option("Todas las subbodegas", "all"));
            subbodegaSelect.value = "all";
            subbodegaSelect.disabled = true;
          }

          async function cargarSubbodegas(idBodega) {
            if (!idBodega || idBodega === "all") {
              resetSubbodegas();
              return;
            }

            subbodegaSelect.disabled = true;
            subbodegaSelect.innerHTML = "";
            subbodegaSelect.appendChild(new Option("Cargando...", "all"));

            try {
              const url =
                `src/controllers/reportes_controller.php?action=get_subbodegas&id_bodega=${encodeURIComponent(idBodega)}`;

              const res = await fetch(url, { method: "GET", headers: { "Accept": "application/json" } });
              const data = await res.json();

              subbodegaSelect.innerHTML = "";
              subbodegaSelect.appendChild(new Option("Todas las subbodegas", "all"));

              if (!data || data.ok !== true || !Array.isArray(data.items)) {
                resetSubbodegas();
                return;
              }

              if (data.items.length === 0) {
                subbodegaSelect.appendChild(new Option("No hay subbodegas asociadas", "all"));
                subbodegaSelect.value = "all";
                subbodegaSelect.disabled = true;
                return;
              }

              data.items.forEach((item) => {
                const opt = new Option(item.nombre, item.id);
                subbodegaSelect.appendChild(opt);
              });

              subbodegaSelect.disabled = false;
              subbodegaSelect.value = "all";
            } catch (err) {
              console.error("❌ Error cargando subbodegas:", err);
              resetSubbodegas();
            }
          }

          bodegaSelect.addEventListener("change", (e) => {
            cargarSubbodegas(e.target.value);
          });

          resetSubbodegas();
          if (bodegaSelect.value && bodegaSelect.value !== "all") {
            cargarSubbodegas(bodegaSelect.value);
          }
        })();

        // =====================================================
        // ✅ FIX FINAL: GUARDAR BODEGA/SUBBODEGA EN LA URL
        //   - para que cualquier descarga use subbodega
        // =====================================================
        (function () {
          const programaEl  = document.getElementById("custom-programa");
          const bodegaEl    = document.getElementById("custom-bodega");
          const subbodegaEl = document.getElementById("custom-subbodega");

          if (!programaEl || !bodegaEl || !subbodegaEl) return;

          function syncCustomFiltersToURL() {
            const params = new URLSearchParams(window.location.search);

            params.set("page", "reportes");
            params.set("tab", params.get("tab") || "reportes");

            // ✅ Guardar los custom como filtros globales
            params.set("bodega", bodegaEl.value || "all");
            params.set("subbodega", subbodegaEl.value || "all");

            // ✅ NO recarga la página (solo actualiza la URL)
            const newUrl = window.location.pathname + "?" + params.toString();
            window.history.replaceState({}, "", newUrl);
          }

          bodegaEl.addEventListener("change", () => {
            // cuando cambia bodega, subbodega se reseteará a all y luego se elegirá
            setTimeout(syncCustomFiltersToURL, 50);
          });

          subbodegaEl.addEventListener("change", syncCustomFiltersToURL);
          programaEl.addEventListener("change", syncCustomFiltersToURL);

          // inicial
          syncCustomFiltersToURL();
        })();

        // =====================================================
// ✅ FIX: BOTÓN "Generar Reporte" (PERSONALIZADO)
// =====================================================
(function () {
  // Buscar la tarjeta del "Configurar Reporte Personalizado"
  const header = Array.from(document.querySelectorAll("h3"))
    .find(h => (h.textContent || "").trim() === "Configurar Reporte Personalizado");

  if (!header) return;

  const card = header.closest(".bg-card");
  if (!card) return;

  // Inputs del primer bloque (Tipo reporte, Fecha inicio, Fecha fin, Formato)
  const tipoReporteEl = card.querySelector("select.input-siga"); // el primero
  const inputsDate = card.querySelectorAll("input[type='date'].input-siga");
  const fechaInicioEl = inputsDate[0] || null;
  const fechaFinEl = inputsDate[1] || null;

  const selectsAll = card.querySelectorAll("select.input-siga");
  const formatoEl = selectsAll[1] || null; // el segundo select del bloque (formato)

  // Selects con ID (segundo bloque)
  const programaEl  = document.getElementById("custom-programa");
  const bodegaEl    = document.getElementById("custom-bodega");
  const subbodegaEl = document.getElementById("custom-subbodega");
  const graficasEl  = document.getElementById("custom-graficas");

  // Botón "Generar Reporte"
  const btn = Array.from(card.querySelectorAll("button"))
    .find(b => (b.textContent || "").trim().includes("Generar Reporte"));

  if (!btn) return;

  // Spinner dentro del botón
  function setBtnLoading(loading) {
    if (!btn.dataset.originalHtml) btn.dataset.originalHtml = btn.innerHTML;

    if (loading) {
      btn.disabled = true;
      btn.classList.add("opacity-80", "cursor-not-allowed");
      btn.innerHTML = `
        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor"
            d="M4 12a8 8 0 0 1 8-8v3.2a4.8 4.8 0 0 0-4.8 4.8H4z"></path>
        </svg>
        Generando...
      `;
    } else {
      btn.disabled = false;
      btn.classList.remove("opacity-80", "cursor-not-allowed");
      btn.innerHTML = btn.dataset.originalHtml;
    }
  }

  // ✅ Convertir tipo UI → tipo real del controller (para check_data)
  function mapTipoToCheckData(tipoUI) {
    const map = {
      consumo: "consumo-materiales",
      movimientos: "movimientos",
      stock: "material-faltante",
      auditoria: "movimientos",
    };
    return map[tipoUI] || "consumo-materiales";
  }

  // Descargar en iframe invisible (TU MISMO MÉTODO)
  function download(urlFile) {
    let iframe = document.getElementById("pdfDownloadFrame");
    if (!iframe) {
      iframe = document.createElement("iframe");
      iframe.id = "pdfDownloadFrame";
      iframe.style.display = "none";
      document.body.appendChild(iframe);
    }

    const sep = urlFile.includes("?") ? "&" : "?";
    iframe.src = urlFile + sep + "t=" + Date.now();
  }

  btn.addEventListener("click", async () => {
    try {
      setBtnLoading(true);

      const tipoUI = (tipoReporteEl?.value || "consumo").trim();    // consumo | movimientos | stock | auditoria
      const format = (formatoEl?.value || "pdf").trim().toLowerCase(); // pdf | excel | csv

      const fecha_inicio = (fechaInicioEl?.value || "").trim();
      const fecha_fin    = (fechaFinEl?.value || "").trim();

      const programa = (programaEl?.value || "all").trim();
      const bodega   = (bodegaEl?.value || "all").trim();
      const subbodega = (subbodegaEl?.value || "all").trim();
      const incluir_graficas = (graficasEl?.value || "yes").trim().toLowerCase(); // yes | no

      // ✅ 1) VALIDAR DATA (con tipo real)
      const typeCheck = mapTipoToCheckData(tipoUI);

      const checkUrl =
        `src/controllers/reportes_controller.php?action=check_data&type=${encodeURIComponent(typeCheck)}`
        + `&fecha_inicio=${encodeURIComponent(fecha_inicio)}`
        + `&fecha_fin=${encodeURIComponent(fecha_fin)}`
        + `&programa=${encodeURIComponent(programa)}`
        + `&ficha=all`
        + `&bodega=${encodeURIComponent(bodega)}`
        + `&subbodega=${encodeURIComponent(subbodega)}`;

      const res = await fetch(checkUrl, { method: "GET" });
      const check = await res.json().catch(() => null);

      if (!check || check.ok === false) {
        showFlowbiteToast("error", "Validación no disponible", check?.message || "No fue posible validar el reporte.");
        setBtnLoading(false);
        return;
      }

      if (check.hasData === false) {
        showFlowbiteToast("info", "Sin resultados", check.message || "No hay datos con los filtros seleccionados.");
        setBtnLoading(false);
        return;
      }

      // ✅ 2) SI HAY DATA → GENERAR PERSONALIZADO
      const url =
        `src/controllers/reportes_controller.php?action=generate_custom`
        + `&tipo_reporte=${encodeURIComponent(tipoUI)}`
        + `&format=${encodeURIComponent(format)}`
        + `&fecha_inicio=${encodeURIComponent(fecha_inicio)}`
        + `&fecha_fin=${encodeURIComponent(fecha_fin)}`
        + `&programa=${encodeURIComponent(programa)}`
        + `&bodega=${encodeURIComponent(bodega)}`
        + `&subbodega=${encodeURIComponent(subbodega)}`
        + `&incluir_graficas=${encodeURIComponent(incluir_graficas)}`;

      download(url);

      setTimeout(() => setBtnLoading(false), 900);
      showFlowbiteToast("success", "Reporte generado", "El reporte personalizado se está descargando.");

    } catch (err) {
      console.error("❌ Error reporte personalizado:", err);
      setBtnLoading(false);
      showFlowbiteToast("error", "Error", "No fue posible generar el reporte personalizado.");
    }
  });
})();


    </script>
</body>
</html>
