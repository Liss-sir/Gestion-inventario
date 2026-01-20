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

    // Capturar filtros desde GET
    $filters = [
        "fecha_inicio" => $_GET["fecha_inicio"] ?? null,
        "fecha_fin"    => $_GET["fecha_fin"] ?? null,
        "programa"     => $_GET["programa"] ?? "all",
        "ficha"        => $_GET["ficha"] ?? "all",
    ];

    // ✅ Combos reales
    $mockProgramas = $modelReportes->getProgramas();
    $mockFichas    = $modelReportes->getFichas();

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
                                            <button class="inline-flex items-center justify-center p-2 border border-border rounded-md text-foreground hover:bg-muted transition-colors">
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
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground">
                                            Programa
                                        </label>
                                        <select class="input-siga w-full">
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
                                        <select class="input-siga w-full">
                                            <option value="all">Todas las bodegas</option>
                                            <option value="1">Bodega Principal - Eléctrico</option>
                                            <option value="2">Bodega Construcción</option>
                                            <option value="3">Bodega Acabados</option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground">
                                            Incluir gráficas
                                        </label>
                                        <select class="input-siga w-full">
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

        // =====================================================
        // ✅ INTEGRACIÓN DE FILTROS (SIN DAÑAR TU BASE)
        //   - guarda filtros en la URL (GET)
        //   - el backend los lee y devuelve data real
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
        // ✅ EXPORTAR PDF: Consumo por ficha
        // ✅ MISMA PÁGINA (sin pestaña nueva, sin refresh)
        //   - Respeta filtros de la URL
        // =====================================================
        (function () {
            const btn = document.getElementById("btnExportConsumoFicha");
            if (!btn) return;

            // ✅ Descarga el PDF sin salir de la página (iframe invisible)
            function downloadSamePage(urlPdf) {
                let iframe = document.getElementById("pdfDownloadFrame");
                if (!iframe) {
                    iframe = document.createElement("iframe");
                    iframe.id = "pdfDownloadFrame";
                    iframe.style.display = "none";
                    document.body.appendChild(iframe);
                }

                // ✅ Cache-buster para que siempre dispare la descarga
                const sep = urlPdf.includes("?") ? "&" : "?";
                iframe.src = urlPdf + sep + "t=" + Date.now();
            }

            btn.addEventListener("click", () => {
                const params = new URLSearchParams(window.location.search);

                const fecha_inicio = params.get("fecha_inicio") || "";
                const fecha_fin    = params.get("fecha_fin") || "";
                const programa     = params.get("programa") || "all";
                const ficha        = params.get("ficha") || "all";

                const url = `src/controllers/reportes_controller.php?action=export_consumo_ficha`
                    + `&fecha_inicio=${encodeURIComponent(fecha_inicio)}`
                    + `&fecha_fin=${encodeURIComponent(fecha_fin)}`
                    + `&programa=${encodeURIComponent(programa)}`
                    + `&ficha=${encodeURIComponent(ficha)}`;

                // ✅ Descarga en la misma página (sin refresh, sin pestaña)
                downloadSamePage(url);
            });
        })();

        // =====================================================
        // ✅ BACKEND PARA TAB "REPORTES PDF" + REPORTE PERSONALIZADO
        //   - SIN TOCAR TU HTML BASE
        //   - Usa reportes_controller.php:
        //       action=generate_pdf&type=...
        //       action=print_view&type=...
        //       action=generate_custom
        // =====================================================

        // ✅ Helper global: descarga PDF/CSV/Excel sin recargar (iframe invisible)
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

        // ✅ Generar PDF por tarjeta (usa filtros actuales de URL si existen)
        function handleGenerateReport(type) {
            const params = new URLSearchParams(window.location.search);

            const fecha_inicio = params.get("fecha_inicio") || "";
            const fecha_fin    = params.get("fecha_fin") || "";
            const programa     = params.get("programa") || "all";
            const ficha        = params.get("ficha") || "all";

            const url =
                `src/controllers/reportes_controller.php?action=generate_pdf&type=${encodeURIComponent(type)}`
                + `&fecha_inicio=${encodeURIComponent(fecha_inicio)}`
                + `&fecha_fin=${encodeURIComponent(fecha_fin)}`
                + `&programa=${encodeURIComponent(programa)}`
                + `&ficha=${encodeURIComponent(ficha)}`;

            __downloadSamePage(url);
        }

        // ✅ Imprimir por tarjeta (abre vista imprimible)
        function handlePrintReport(type) {
            const params = new URLSearchParams(window.location.search);

            const fecha_inicio = params.get("fecha_inicio") || "";
            const fecha_fin    = params.get("fecha_fin") || "";
            const programa     = params.get("programa") || "all";
            const ficha        = params.get("ficha") || "all";

            const url =
                `src/controllers/reportes_controller.php?action=print_view&type=${encodeURIComponent(type)}`
                + `&fecha_inicio=${encodeURIComponent(fecha_inicio)}`
                + `&fecha_fin=${encodeURIComponent(fecha_fin)}`
                + `&programa=${encodeURIComponent(programa)}`
                + `&ficha=${encodeURIComponent(ficha)}`;

            window.open(url, "_blank");
        }

        // ✅ Enlazar automáticamente el botón de impresora de cada card (sin tocar HTML)
        (function () {
            // Solo aplica cuando está el tab de reportes activo (si hay cards)
            const cards = document.querySelectorAll(".grid.md\\:grid-cols-2.lg\\:grid-cols-3 > div.bg-card");
            if (!cards || !cards.length) return;

            cards.forEach((card) => {
                const btnGenerate = card.querySelector("button[onclick*=\"handleGenerateReport(\"]");
                const btnPrint = card.querySelector("button.inline-flex.items-center.justify-center.p-2");

                if (!btnGenerate || !btnPrint) return;

                const onclick = btnGenerate.getAttribute("onclick") || "";
                // Extraer id dentro de handleGenerateReport('...')
                const match = onclick.match(/handleGenerateReport\\(['"]([^'"]+)['"]\\)/);
                if (!match || !match[1]) return;

                const reportId = match[1];

                btnPrint.addEventListener("click", function (e) {
                    e.preventDefault();
                    handlePrintReport(reportId);
                });
            });
        })();

        // ✅ Reporte personalizado (leer inputs sin IDs, por orden, sin tocar HTML)
        (function () {
            // Buscar el bloque "Configurar Reporte Personalizado"
            const headers = Array.from(document.querySelectorAll("h3"));
            const titleNode = headers.find(h => (h.textContent || "").trim() === "Configurar Reporte Personalizado");
            if (!titleNode) return;

            // Contenedor principal del card
            const card = titleNode.closest(".bg-card");
            if (!card) return;

            // Obtener todos los selects e inputs dentro del card (en el orden del layout)
            const selects = Array.from(card.querySelectorAll("select.input-siga"));
            const inputs  = Array.from(card.querySelectorAll("input[type='date'].input-siga"));

            // Botón "Generar Reporte" (último botón del card)
            const btns = Array.from(card.querySelectorAll("button"));
            const btnGenerateCustom = btns.find(b => (b.textContent || "").includes("Generar Reporte"));
            if (!btnGenerateCustom) return;

            // Layout esperado:
            // selects[0] = tipo reporte
            // inputs[0]  = fecha inicio
            // inputs[1]  = fecha fin
            // selects[1] = formato
            // selects[2] = programa
            // selects[3] = bodega
            // selects[4] = incluir_graficas
            function safeVal(el, fallback = "") {
                return el && typeof el.value !== "undefined" ? el.value : fallback;
            }

            btnGenerateCustom.addEventListener("click", function (e) {
                e.preventDefault();

                const tipo_reporte = safeVal(selects[0], "consumo");
                const fecha_inicio = safeVal(inputs[0], "");
                const fecha_fin    = safeVal(inputs[1], "");
                const format       = safeVal(selects[1], "pdf");
                const programa     = safeVal(selects[2], "all");
                const bodega       = safeVal(selects[3], "all");
                const incluir_graficas = safeVal(selects[4], "yes");

                const url =
                    `src/controllers/reportes_controller.php?action=generate_custom`
                    + `&tipo_reporte=${encodeURIComponent(tipo_reporte)}`
                    + `&format=${encodeURIComponent(format)}`
                    + `&fecha_inicio=${encodeURIComponent(fecha_inicio)}`
                    + `&fecha_fin=${encodeURIComponent(fecha_fin)}`
                    + `&programa=${encodeURIComponent(programa)}`
                    + `&bodega=${encodeURIComponent(bodega)}`
                    + `&incluir_graficas=${encodeURIComponent(incluir_graficas)}`;

                // ✅ Descarga/genera en la misma página
                __downloadSamePage(url);
            });
        })();

    </script>
</body>
</html>
