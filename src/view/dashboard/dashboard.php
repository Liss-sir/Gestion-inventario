<?php

// ✅ NECESARIO: si NO inicias sesión, $_SESSION estará vacío y el modal nunca se activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../../Config/database.php';

// database.php define $conn (PDO). Si no está, crea fallback.
if (!isset($conn) || !($conn instanceof PDO)) {
  $host = 'localhost';
  $dbname = 'gestion_inventario';
  $user = 'root';
  $pass = '123456';
  $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
}

$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";

// ===============================
//  Datos en vivo desde la BD
// ===============================

// Totales de materiales
$sqlMat = "SELECT COUNT(*) AS total, SUM(CASE WHEN estado IN ('Activo','Activa','activo','activa',1) THEN 1 ELSE 0 END) AS activos FROM material_formacion";
$materialRow = $conn->query($sqlMat)->fetch(PDO::FETCH_ASSOC);
$totalMateriales = (int)($materialRow['total'] ?? 0);
$materialesActivos = (int)($materialRow['activos'] ?? 0);

// Bodegas activas
$sqlBod = "SELECT COUNT(*) AS total, SUM(CASE WHEN estado IS NULL OR estado IN ('Activo','Activa',1) THEN 1 ELSE 0 END) AS activas FROM bodegas";
$bodRow = $conn->query($sqlBod)->fetch(PDO::FETCH_ASSOC);
$totalBodegas = (int)($bodRow['total'] ?? 0);
$bodegasActivas = (int)($bodRow['activas'] ?? 0);

// Movimientos de hoy (entrada/salida + devoluciones)
$sqlMovHoy = "SELECT SUM(cnt) AS total FROM (
  SELECT COUNT(*) AS cnt FROM movimientos_material WHERE DATE(fecha_hora) = CURDATE()
  UNION ALL
  SELECT COUNT(*) AS cnt FROM devoluciones_material WHERE DATE(fecha_hora) = CURDATE()
) t";
$movimientosHoy = (int)($conn->query($sqlMovHoy)->fetchColumn() ?: 0);

// Alertas de stock (<=20 unidades) - top 4
$sqlAlerts = "
  SELECT 
    m.id_material,
    m.nombre AS material_nombre,
    COALESCE(sb.stock_bodega,0) + COALESCE(ss.stock_subbodega,0) AS stock_actual,
    20 AS stock_minimo
  FROM material_formacion m
  LEFT JOIN (
    SELECT id_material, SUM(stock_actual) AS stock_bodega
    FROM stock_bodega
    GROUP BY id_material
  ) sb ON sb.id_material = m.id_material
  LEFT JOIN (
    SELECT id_material, SUM(stock_actual) AS stock_subbodega
    FROM stock_subbodega
    GROUP BY id_material
  ) ss ON ss.id_material = m.id_material
  WHERE (COALESCE(sb.stock_bodega,0) + COALESCE(ss.stock_subbodega,0)) <= 20
  ORDER BY stock_actual ASC
  LIMIT 4
";
$stockAlerts = $conn->query($sqlAlerts)->fetchAll(PDO::FETCH_ASSOC);

// ===== Tendencias vs. última visita (se guardan en sesión) =====
// La primera vez muestra "—"; después calcula % vs. la visita anterior.
$prevSnapshot = $_SESSION['dashboard_prev'] ?? [];

$metricsNow = [
  'total_materiales'   => $totalMateriales,
  'bodegas_activas'    => $bodegasActivas,
  'movimientos_hoy'    => $movimientosHoy,
  'alertas_stock'      => count($stockAlerts),
];

function calcTrend($current, $previous)
{
  if ($previous === null) {
    return ['text' => '—', 'class' => 'text-muted-foreground', 'icon' => 'help-circle'];
  }

  // Evita divisiones por cero; si no había dato previo y ahora sí, lo consideramos +100%
  if ($previous <= 0) {
    if ($current <= 0) {
      return ['text' => '0%', 'class' => 'text-muted-foreground', 'icon' => 'minus'];
    }
    return ['text' => '+100%', 'class' => 'text-[#39A900]', 'icon' => 'trending-up'];
  }

  $diff = $current - $previous;
  $percent = ($diff / $previous) * 100;
  $percentTxt = ($percent >= 0 ? '+' : '') . number_format($percent, 1) . '%';

  if (abs($percent) < 0.05) {
    return ['text' => 'Sin cambios', 'class' => 'text-muted-foreground', 'icon' => 'minus'];
  }

  if ($percent > 0) {
    return ['text' => $percentTxt, 'class' => 'text-[#39A900]', 'icon' => 'trending-up'];
  }
  return ['text' => $percentTxt, 'class' => 'text-[#EF4444]', 'icon' => 'trending-down'];
}

$trends = [];
foreach ($metricsNow as $key => $value) {
  $prev = $prevSnapshot[$key] ?? null;
  $trends[$key] = calcTrend($value, $prev);
}

// Guarda el snapshot actual para la próxima carga
$_SESSION['dashboard_prev'] = $metricsNow;

// Solicitudes recientes (máx 4)
$sqlSol = "
  SELECT sm.id_solicitud, sm.estado, sm.fecha_solicitud,
       COALESCE(u.nombre_completo, 'Sin instructor') AS instructor_nombre,
       COALESCE(f.numero_ficha, 'N/A') AS ficha_numero
  FROM solicitudes_material sm
  LEFT JOIN usuarios u ON u.id_usuario = sm.id_usuario_solicitante
  LEFT JOIN fichas f ON f.id_ficha = sm.id_ficha
  ORDER BY sm.fecha_solicitud DESC
  LIMIT 4
";
$recentSolicitudes = $conn->query($sqlSol)->fetchAll(PDO::FETCH_ASSOC);

// Solicitudes pendientes
$solicitudesPendientes = (int)($conn->query("SELECT COUNT(*) FROM solicitudes_material WHERE estado = 'Pendiente'")->fetchColumn() ?: 0);

// Movimientos recientes (entrada/salida/devolución) máx 4
$sqlRecentMov = "
  SELECT * FROM (
    SELECT m.fecha_hora, m.tipo_movimiento AS tipo, mf.nombre AS material_nombre, m.cantidad,
         DATE_FORMAT(m.fecha_hora, '%H:%i') AS hora
    FROM movimientos_material m
    LEFT JOIN material_formacion mf ON mf.id_material = m.id_material
        
    UNION ALL
    SELECT d.fecha_hora, 'devolucion' AS tipo, mf.nombre AS material_nombre, d.cantidad_devuelta AS cantidad,
         DATE_FORMAT(d.fecha_hora, '%H:%i') AS hora
    FROM devoluciones_material d
    LEFT JOIN material_formacion mf ON mf.id_material = d.id_material
  ) x
  ORDER BY fecha_hora DESC
  LIMIT 4
";
$recentMovimientos = $conn->query($sqlRecentMov)->fetchAll(PDO::FETCH_ASSOC);

// Consumo mensual (salidas) Enero-Diciembre
$consumoData = [];
for ($m = 1; $m <= 12; $m++) {
  $sqlConsumo = "SELECT COALESCE(SUM(cantidad),0) FROM movimientos_material WHERE tipo_movimiento = 'Salida' AND YEAR(fecha_hora) = YEAR(CURDATE()) AND MONTH(fecha_hora) = :m";
  $stmtC = $conn->prepare($sqlConsumo);
  $stmtC->execute([':m' => $m]);
  $totalMes = (int)$stmtC->fetchColumn();
  $consumoData[] = [
    'name' => date('M', mktime(0,0,0,$m,1)),
    'consumo' => $totalMes
  ];
}

// Distribución por obra (usa el nombre_actividad como título de obra)
$sqlCat = "
  SELECT COALESCE(af.nombre_actividad, 'Sin obra') AS categoria, COUNT(*) AS total
  FROM actividades_formacion af
  GROUP BY COALESCE(af.nombre_actividad, 'Sin obra')
";
$catRows = $conn->query($sqlCat)->fetchAll(PDO::FETCH_ASSOC);

// Paleta de colores reutilizable
$palette = [
  "#39A900", "#007832", "#00304D", "#71277A",
  "#50E5F9", "#FDC300", "#F6F6F6", "#FFFFFF", "#000000",
];

$categoriaData = [];
foreach ($catRows as $idx => $cat) {
  $categoriaData[] = [
    'name'  => $cat['categoria'] ?? 'Sin categoría',
    'value' => (int)$cat['total'],
    'color' => $palette[$idx % count($palette)]
  ];
}

// Gradiente para el donut
$totalCategorias = array_sum(array_column($categoriaData, 'value')) ?: 1;
$currentAngle = 0;
$gradientParts = [];
foreach ($categoriaData as $cat) {
  $angle = ($cat['value'] / $totalCategorias) * 360;
  $start = $currentAngle;
  $end   = $currentAngle + $angle;
  $gradientParts[] = $cat['color'] . " " . $start . "deg " . $end . "deg";
  $currentAngle = $end;
}
$pieGradient = implode(", ", $gradientParts);

// Máximo consumo para la escala de la gráfica
$maxConsumo = max(array_column($consumoData, 'consumo')) ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- ✅ Flowbite (para notificaciones estilo Flowbite, igual que en Usuarios) -->
    <script src="https://unpkg.com/flowbite@2.5.1/dist/flowbite.min.js"></script>

    <link rel="stylesheet" href="src/assets/css/globals.css">

    <!-- ✅ FIX SIN TOCAR TU BASE:
         Fallback de estilos del modal por si globals.css no tiene .modal-overlay / .active -->
    <style>
      .modal-overlay{
        position:fixed;
        inset:0;
        display:none;              /* oculto por defecto */
        align-items:center;
        justify-content:center;
        background:rgba(15, 23, 42, .55);
        padding:16px;
        z-index:9999;
      }
      .modal-overlay.active{ display:flex; }  /* ✅ esto es lo que tu JS usa */
    </style>
</head>
<body>
    <main class="p-6 transition-all duration-300"
      style="margin-left: <?= isset($_GET['coll']) && $_GET['coll'] == "1" ? '70px' : '260px' ?>;">

<div class="space-y-6 animate-fade-in-up">
<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Dashboard</h1>
        <p class="text-muted-foreground">Resumen general del inventario y actividad reciente</p>
    </div>
    <div class="flex gap-2">
        <a href="?page=solicitudes">
        <button class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-border bg-transparent px-4 py-2 text-sm font-medium text-foreground shadow-sm hover:bg-muted gap-2">
        <i data-lucide="clock" class="h-5 w-5 "></i>
        Pendientes
        </button>
    </a>
    <a href="?page=materiales">
        <button class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 gap-2">
        <i data-lucide="package" class="h-4 w-4"></i>
        Nuevo Material
        </button>
    </a>
    </div>
</div>

<!-- targets -->
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

    <div class=" rounded-xl border border-border bg-card p-8 flex flex-col gap-2 ">
    <div class="flex items-center justify-between">
        <p class="text-2x1 font-medium text-muted-foreground">Total Materiales</p>

        <!-- ✅ Icono: verde secundario + cuadrito al 29% -->
        <div class="rounded-md p-2 bg-[#0078324A]">
            <i data-lucide="box" class="h-5 w-5 text-[#007832]"></i>
        </div>

    </div>
    <div class="flex items-center gap-2 mt-2">
      <p class="mt-2 text-2xl font-bold"><?php echo $totalMateriales; ?></p>
      <?php $trend = $trends['total_materiales'] ?? ['text' => '—', 'class' => 'text-muted-foreground', 'icon' => 'minus']; ?>
      <span class="text-xs flex items-center <?php echo $trend['class']; ?>">
        <?php echo htmlspecialchars($trend['text']); ?>
        <i data-lucide="<?php echo $trend['icon']; ?>" class="ml-2 h-4 w-4"></i>
      </span>
    </div>

    <p class="text-xs text-success flex items-center"><?php echo $materialesActivos; ?> disponibles</p>
    <div class="flex items-center gap-1 text-xs text-success"></div>
    </div>

    <!-- StatCard: Bodegas Activas -->
    <div class="rounded-xl border border-border bg-card p-8 flex flex-col gap-2">
    <div class="flex items-center justify-between">
        <p class="text-2x1 font-medium text-muted-foreground">Bodegas Activas</p>

        <!-- ✅ Icono: verde secundario + cuadrito al 29% -->
        <div class="rounded-md p-2 bg-[#0078324A]">
            <i data-lucide="warehouse" class="h-5 w-5 text-[#007832]"></i>
        </div>

    </div>
    <div class="flex items-center gap-2 mt-2">
      <p class="mt-2 text-2xl font-bold"><?php echo $bodegasActivas; ?></p>
      <?php $trend = $trends['bodegas_activas'] ?? ['text' => '—', 'class' => 'text-muted-foreground', 'icon' => 'minus']; ?>
      <span class="text-xs flex items-center <?php echo $trend['class']; ?>">
        <?php echo htmlspecialchars($trend['text']); ?>
        <i data-lucide="<?php echo $trend['icon']; ?>" class="ml-2 h-4 w-4"></i>
      </span>
    </div>
    <p class="text-xs text-muted-foreground">de <?php echo $totalBodegas; ?> registradas</p>
    </div>

    <!-- StatCard: Movimientos Hoy -->
    <div class="rounded-xl border border-border bg-card p-8 flex flex-col gap-2">
    <div class="flex items-center justify-between">
        <p class="text-2x1 font-medium text-muted-foreground">Movimientos Hoy</p>

        <!-- ✅ Icono: verde secundario + cuadrito al 29% -->
        <div class="rounded-md p-2 bg-[#0078324A]">
            <i data-lucide="arrow-down-up" class="h-5 w-5 text-[#007832]"></i>
        </div>

    </div>
    <div class="flex items-center gap-2 mt-2">
      <p class="mt-2 text-2xl font-bold"><?php echo $movimientosHoy; ?></p>
      <?php $trend = $trends['movimientos_hoy'] ?? ['text' => '—', 'class' => 'text-muted-foreground', 'icon' => 'minus']; ?>
      <span class="text-xs flex items-center <?php echo $trend['class']; ?>">
        <?php echo htmlspecialchars($trend['text']); ?>
        <i data-lucide="<?php echo $trend['icon']; ?>" class="ml-2 h-4 w-4"></i>
      </span>
    </div>
    <p class="text-xs text-muted-foreground">Entradas y salidas</p>
    </div>

    <!-- StatCard: Alertas Stock -->
    <div class="rounded-xl border border-border bg-card p-8 flex flex-col gap-2">
    <div class="flex items-center justify-between">
        <p class="mg-7px text-2x1 font-medium text-muted-foreground">Alertas Stock</p>

        <!-- ✅ Amarillo institucional: ícono + fondo -->
      <div class="rounded-md p-2 bg-[#FDC3004A]">
        <i data-lucide="alert-triangle" class="h-5 w-5 text-[#FDC300]"></i>
      </div>

    </div>
    <div class="flex items-center gap-2 mt-2">
      <p class="mt-2 text-2xl font-bold"><?php echo count($stockAlerts); ?></p>
      <?php $trend = $trends['alertas_stock'] ?? ['text' => '—', 'class' => 'text-muted-foreground', 'icon' => 'minus']; ?>
      <span class="text-xs flex items-center <?php echo $trend['class']; ?>">
        <?php echo htmlspecialchars($trend['text']); ?>
        <i data-lucide="<?php echo $trend['icon']; ?>" class="ml-2 h-4 w-4"></i>
      </span>
    </div>
    <p class="text-xs text-muted-foreground">Materiales en riesgo</p>
    </div>

</div>

<!-- Charts Row -->
<div class="grid gap-6 lg:grid-cols-2">
    <!-- Consumo Chart -->
    <div class="rounded-xl border border-border bg-card">
        <div class="flex items-center justify-between px-6 pt-4 pb-2">
            <div>
                <h2 class="text-base font-semibold">Consumo de Materiales</h2>
                <p class="text-sm text-muted-foreground">Últimos 12 meses</p>
            </div>
            <i data-lucide="trending-up" class="h-5 w-5 text-muted-foreground"></i>
        </div>

        <div class="px-6 pb-6">
            <div class="border-t border-border pt-4 h-44">
                <canvas id="consumoChart" class="w-full h-full"></canvas>
            </div>
        </div>
    </div>

    <!-- Categorías Chart -->
    <div class="rounded-xl border border-border bg-card">
        <div class="flex items-center justify-between px-6 pt-4 pb-2">
            <div>
                <h2 class="text-base font-semibold">Distribución por Obra</h2>
                <p class="text-sm text-muted-foreground">Actividades por nombre de obra</p>
            </div>
        </div>
        <div class="px-6 pb-6">
            <div class="border-t border-border pt-4">
                <div class="flex items-center justify-center gap-6">
                    <!-- Gráfica de pastel -->
                    <div class="h-40 w-40">
                        <canvas id="categoriaChart" class="w-full h-full"></canvas>
                    </div>

                    <!-- Leyenda a la derecha -->
                    <div class="space-y-2 text-sm">
                        <?php foreach ($categoriaData as $item): ?>
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-full"
                                    style="background-color: <?php echo $item['color']; ?>;"></span>
                                <span><?php echo htmlspecialchars($item['name']); ?>:</span>
                                <span class="font-medium text-muted-foreground">
                                    <?php echo $item['value']; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Row -->
<div class="grid gap-6 lg:grid-cols-3">
    <!-- Stock Alerts -->
    <div class="rounded-xl border border-border bg-card">
    <div class="flex items-center justify-between px-6 pt-4 pb-3">
        <h2 class="text-base font-semibold">Alertas de Stock</h2>
        <a href="?page=materiales">
        <button class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-muted gap-1 h-8">
            Ver todo
            <i data-lucide="arrow-right" class="h-3 w-3"></i>
        </button>
        </a>
    </div>
    <div class="px-6 pb-4 space-y-4">
        <?php if (count($stockAlerts) === 0): ?>
        <p class="text-center text-sm text-muted-foreground py-4">No hay alertas de stock</p>
        <?php else: ?>
        <?php foreach ($stockAlerts as $alert):
            $percent = ($alert["stock_actual"] / $alert["stock_minimo"]) * 100;
            if ($percent > 100) $percent = 100;
        ?>
            <div class="space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium"><?php echo htmlspecialchars($alert["material_nombre"]); ?></span>

                <!-- ✅ CORREGIDO: misma etiqueta (tamaño/padding) que "Solicitudes Recientes" -->
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs bg-[#FDC30040] text-[#FDC300]">
                  Bajo
                </span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                <div class="h-2 rounded-full bg-[#39A900]" style="width: <?php echo $percent; ?>%;"></div>
                </div>
                <span class="text-xs text-muted-foreground whitespace-nowrap">
                <?php echo $alert["stock_actual"]; ?>/<?php echo $alert["stock_minimo"]; ?>
                </span>
            </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    </div>

    <!-- Solicitudes Recientes -->
    <div class="rounded-xl border border-border bg-card">
    <div class="flex items-center justify-between px-6 pt-4 pb-3">
        <h2 class="text-base font-semibold">Solicitudes Recientes</h2>
        <a href="?page=solicitudes">
            <button class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-muted gap-1 h-8">
                Ver todo
                <i data-lucide="arrow-right" class="h-3 w-3"></i>
            </button>
        </a>
    </div>
    <div class="px-6 pb-4 space-y-4">
        <?php foreach (array_slice($recentSolicitudes, 0, 4) as $solicitud):
        $estado = strtolower($solicitud["estado"] ?? '');
        if ($estado === "pendiente") {
            $badgeClasses = "bg-[#FDC30040] text-[#FDC300]";
            $icon = "clock";
        } elseif ($estado === "aprobada") {
            $badgeClasses = "bg-[#39A90040] text-[#39A900]";
            $icon = "check-circle-2";
        } else {
            $badgeClasses = "bg-[#EF444440] text-[#EF4444]";
            $icon = "x-circle";
        }
        ?>
        <div class="flex items-start gap-3 pb-3 border-b border-border last:border-0 last:pb-0">
            <div class="mt-0.5 rounded-full p-1.5 <?php echo $badgeClasses; ?>">
            <i data-lucide="<?php echo $icon; ?>" class="h-3 w-3"></i>
            </div>
            <div class="flex-1 min-w-0">
            <p class="text-sm font-medium truncate"><?php echo htmlspecialchars($solicitud["instructor_nombre"]); ?></p>
            <p class="text-xs text-muted-foreground">Ficha <?php echo htmlspecialchars($solicitud["ficha_numero"]); ?></p>
            </div>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs  <?php echo $badgeClasses; ?>">
            <?php echo htmlspecialchars(ucfirst($solicitud["estado"])); ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    </div>

    <!-- Actividad Reciente -->
    <div class="rounded-xl border border-border bg-card">
    <div class="flex items-center justify-between px-6 pt-4 pb-3">
        <h2 class="text-base font-semibold">Actividad Reciente</h2>
        <a href="?page=movimientos">
        <button class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-muted gap-1 h-8">
            Ver todo
            <i data-lucide="arrow-right" class="h-3 w-3"></i>
        </button>
        </a>
    </div>
    <div class="px-6 pb-4 space-y-4">
        <?php foreach (array_slice($recentMovimientos, 0, 4) as $mov):
        $tipoMov = strtolower($mov["tipo"] ?? '');
        if ($tipoMov === "entrada") {
          $movClasses = "bg-[#39A9001A] text-[#39A900]";
        } elseif ($tipoMov === "salida") {
          $movClasses = "bg-[#39A9001A] text-[#39A900]";
        } else {
          $movClasses = "bg-[#FDC3001A] text-[#FDC300]";
        }
        ?>
        <div class="flex items-start gap-3 pb-3 border-b border-border last:border-0 last:pb-0">
            <!-- ✅ Ícono estilo foto: círculo perfecto + centrado -->
            <div class="mt-0.5 h-8 w-8 rounded-full flex items-center justify-center <?php echo $movClasses; ?>">
            <i data-lucide="arrow-down-up" class="h-4 w-4"></i>
            </div>
            <div class="flex-1 min-w-0">
          <p class="text-sm font-medium capitalize"><?php echo htmlspecialchars($tipoMov); ?></p>
          <p class="text-xs text-muted-foreground truncate">
            <?php echo htmlspecialchars($mov["material_nombre"] ?? ''); ?> (<?php echo $mov["cantidad"] ?? 0; ?>)
            </p>
            </div>
          <span class="text-xs text-muted-foreground"><?php echo htmlspecialchars($mov["hora"] ?? ''); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    </div>
</div>
</div>

<!-- ========================================= -->
<!-- MODAL: CAMBIO DE CONTRASEÑA OBLIGATORIO   -->
<!-- ========================================= -->
<div id="modalForcePassword" class="modal-overlay">
  <div class="relative w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-lg">
    <div class="mb-4">
      <h2 class="text-lg font-semibold">Cambio de contraseña obligatorio</h2>
      <p class="text-sm text-muted-foreground">
        Por seguridad, debes cambiar la contraseña antes de continuar.
      </p>
    </div>

    <form id="formForcePassword" class="space-y-4" novalidate>
      <div class="space-y-2">
        <label for="fp_actual" class="text-sm font-medium">Contraseña actual *</label>

        <!-- ✅ Ojito (toggle) -->
        <div class="relative">
          <input id="fp_actual" type="password"
                 class="w-full rounded-md border border-input bg-background px-3 py-2 pr-11 text-sm input-siga"
                 placeholder="Ingresa la contraseña actual" />
          <button type="button"
                  data-toggle-password="#fp_actual"
                  class="absolute inset-y-0 right-0 inline-flex items-center justify-center px-3 text-slate-500 hover:text-slate-700"
                  aria-label="Mostrar u ocultar contraseña actual"
                  title="Mostrar/Ocultar">
            <i data-lucide="eye" class="h-4 w-4 toggle-eye-on"></i>
            <i data-lucide="eye-off" class="h-4 w-4 toggle-eye-off hidden"></i>
          </button>
        </div>
      </div>

      <div class="space-y-2">
        <label for="fp_nueva" class="text-sm font-medium">Nueva contraseña *</label>

        <!-- ✅ Ojito (toggle) -->
        <div class="relative">
          <input id="fp_nueva" type="password"
                 class="w-full rounded-md border border-input bg-background px-3 py-2 pr-11 text-sm input-siga"
                 placeholder="Ingresa la nueva contraseña" />
          <button type="button"
                  data-toggle-password="#fp_nueva"
                  class="absolute inset-y-0 right-0 inline-flex items-center justify-content-center px-3 text-slate-500 hover:text-slate-700"
                  aria-label="Mostrar u ocultar nueva contraseña"
                  title="Mostrar/Ocultar">
            <i data-lucide="eye" class="h-4 w-4 toggle-eye-on"></i>
            <i data-lucide="eye-off" class="h-4 w-4 toggle-eye-off hidden"></i>
          </button>
        </div>

        <!-- ✅ Reglas -->
        <p class="text-xs text-muted-foreground">
          Debe tener mínimo 8 caracteres e incluir: <span class="font-medium">1 mayúscula</span>, <span class="font-medium">1 número</span> y <span class="font-medium">1 caracter especial</span>.
        </p>
      </div>

      <div class="space-y-2">
        <label for="fp_confirmar" class="text-sm font-medium">Confirmar nueva contraseña *</label>

        <!-- ✅ Ojito (toggle) -->
        <div class="relative">
          <input id="fp_confirmar" type="password"
                 class="w-full rounded-md border border-input bg-background px-3 py-2 pr-11 text-sm input-siga"
                 placeholder="Confirma la nueva contraseña" />
          <button type="button"
                  data-toggle-password="#fp_confirmar"
                  class="absolute inset-y-0 right-0 inline-flex items-center justify-center px-3 text-slate-500 hover:text-slate-700"
                  aria-label="Mostrar u ocultar confirmación de contraseña"
                  title="Mostrar/Ocultar">
            <i data-lucide="eye" class="h-4 w-4 toggle-eye-on"></i>
            <i data-lucide="eye-off" class="h-4 w-4 toggle-eye-off hidden"></i>
          </button>
        </div>
      </div>

      <div class="flex justify-end pt-2">
        <button type="submit"
          class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:opacity-90">
          Actualizar contraseña
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ===================================================== -->
<!-- ✅ NUEVO MODAL CENTRADO (ADVERTENCIA) - SIN TOAST       -->
<!-- ===================================================== -->
<div id="modalSessionWarning" class="modal-overlay" style="z-index:10050;">
  <div class="relative w-full max-w-md rounded-2xl border border-border bg-white p-6 shadow-xl">
    <div class="flex items-start gap-3">
      <div class="mt-0.5 rounded-xl p-2 bg-[#FDC3004A]">
        <i data-lucide="alert-triangle" class="h-6 w-6 text-[#FDC300]"></i>
      </div>

      <div class="flex-1">
        <h3 class="text-base font-semibold text-slate-900">Advertencia</h3>
        <p id="modalSessionWarningMsg" class="mt-1 text-sm text-slate-600">
          Tu cuenta ha sido deshabilitada por el administrador.
        </p>
      </div>
    </div>

    <div class="mt-5 flex justify-end">
      <button
        type="button"
        id="btnSessionWarningOk"
        class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:opacity-90"
      >
        Entendido
      </button>
    </div>
  </div>
</div>

</main>

<!-- ✅ Contenedor de toasts (LO DEJAMOS POR TU CÓDIGO, PERO YA NO SE USA PARA DESHABILITADO) -->
<div
  id="toastContainer"
  class="fixed top-6 right-6 z-[10000] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none"
></div>

<!-- ✅ FIX IMPLEMENTADO (SIN TOCAR TU BASE):
     - Usa auth_controller.php?accion=check (tu archivo 3)
     - SI logout=true -> muestra MODAL CENTRADO de ADVERTENCIA
     - Luego redirige al LOGIN REAL -->
<script>
(function initAutoLogoutIfDisabled(){
  // ✅ Endpoint real (tu auth_controller.php)
  const CHECK_URL = "src/controllers/auth_controller.php?accion=check";
  const INTERVAL_MS = 7000; // 7 segundos
  let alreadyRedirecting = false;

  function getLoginUrl(reason){
    const base = (typeof window.BASE_URL === "string" && window.BASE_URL.length)
      ? window.BASE_URL
      : "";
    // ✅ login real
    const url = base + "src/view/login/login.php";
    return url + "?reason=" + encodeURIComponent(reason || "disabled");
  }

  function showCenteredWarning(message){
    const modal = document.getElementById("modalSessionWarning");
    const msgEl = document.getElementById("modalSessionWarningMsg");
    const btnOk = document.getElementById("btnSessionWarningOk");

    if (!modal) return;

    if (msgEl) msgEl.textContent = message || "Tu sesión fue cerrada.";
    modal.classList.add("active");
    document.body.style.overflow = "hidden";

    // Render icons
    try{
      if (window.lucide && typeof lucide.createIcons === "function") lucide.createIcons();
    }catch(e){}

    // Botón entendido: redirige
    if (btnOk && !btnOk.dataset.bound) {
      btnOk.dataset.bound = "1";
      btnOk.addEventListener("click", () => {
        window.location.href = getLoginUrl("disabled");
      });
    }
  }

  async function check(){
    if (alreadyRedirecting) return;

    try{
      const res = await fetch(CHECK_URL, { cache: "no-store" });
      const data = await res.json().catch(() => ({}));

      // Formato de tu auth_controller:
      // { ok:false, logout:true, reason:"disabled"|"session_revoked"|"no_session" }
      if (data && data.logout) {
        alreadyRedirecting = true;

        const reason = data.reason || "disabled";

        // ✅ SIEMPRE ADVERTENCIA (NO error, NO toast)
        if (reason === "disabled") {
          showCenteredWarning("Tu cuenta ha sido deshabilitada por el administrador.");
        } else if (reason === "session_revoked") {
          showCenteredWarning("Tu sesión fue cerrada (sesión revocada).");
        } else {
          showCenteredWarning("Tu sesión expiró o fue cerrada.");
        }

        // ✅ dejar que se vea el modal y luego redirigir
        setTimeout(() => {
          window.location.href = getLoginUrl(reason);
        }, 1200);

        return;
      }

    } catch(e){
      // Si falla red, no hagas logout (evita falsos positivos)
    }
  }

  // ✅ Espera DOM para que existan modal + lucide
  document.addEventListener("DOMContentLoaded", () => {
    setTimeout(() => {
      check();
      setInterval(check, INTERVAL_MS);
    }, 250);
  });
})();
</script>

<!-- ===================================================== -->
<!-- ✅ TAB LOCK (BLOQUEAR MISMA CUENTA EN OTRA PESTAÑA)    -->
<!-- ===================================================== -->
<script>
  // ✅ Pasamos el id del usuario (si hay sesión)
  window.SIGA_USER_ID = <?= json_encode($_SESSION['usuario_id'] ?? null) ?>;

  // ✅ Tab Lock: evita 2 ventanas/pestañas del mismo navegador con la misma cuenta
  (function () {
    const userId = window.SIGA_USER_ID;
    if (!userId) return;

    // ID único por pestaña
    let tabId = sessionStorage.getItem("siga_tab_id");
    if (!tabId) {
      tabId =
        (window.crypto && crypto.randomUUID && crypto.randomUUID()) ||
        ("tab_" + Math.random().toString(16).slice(2) + Date.now());
      sessionStorage.setItem("siga_tab_id", tabId);
    }

    const KEY = "siga_active_tab_" + userId;
    const TTL_MS = 15000; // 15s
    const PING_MS = 5000; // 5s

    const now = () => Date.now();

    const readLock = () => {
      try {
        const raw = localStorage.getItem(KEY);
        return raw ? JSON.parse(raw) : null;
      } catch (e) {
        return null;
      }
    };

    const writeLock = (obj) => {
      try {
        localStorage.setItem(KEY, JSON.stringify(obj));
      } catch (e) {}
    };

    const isFresh = (lock) => lock && (now() - (lock.ts || 0) < TTL_MS);

    const removeLockIfMine = () => {
      const lock = readLock();
      if (lock && lock.tabId === tabId) {
        try { localStorage.removeItem(KEY); } catch (e) {}
      }
    };

    const blockThisTab = () => {
      document.documentElement.innerHTML = `
        <head>
          <meta charset="utf-8"/>
          <meta name="viewport" content="width=device-width, initial-scale=1"/>
          <title>Sesión ya abierta</title>
          <style>
            body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu; background:#f7f7f8; margin:0; display:flex; min-height:100vh; align-items:center; justify-content:center;}
            .card{background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:22px; max-width:520px; width:92%; box-shadow:0 12px 40px rgba(0,0,0,.08);}
            h1{font-size:18px; margin:0 0 8px;}
            p{margin:0 0 14px; color:#4b5563; font-size:14px; line-height:1.4}
            .hint{font-size:13px; color:#6b7280}
          </style>
        </head>
        <body>
          <div class="card">
            <h1>Esta cuenta ya está abierta en otra ventana/pestaña</h1>
            <p>Para continuar, cierra la otra ventana/pestaña donde está SIGA abierto.</p>
            <p class="hint">Si la otra pestaña se cerró de forma inesperada, espera unos segundos y recarga.</p>
          </div>
        </body>
      `;
      try { window.stop(); } catch (e) {}
    };

    // 1) Si existe otra pestaña activa -> bloquear
    const existing = readLock();
    if (existing && isFresh(existing) && existing.tabId !== tabId) {
      blockThisTab();
      return;
    }

    // 2) Reclamar candado
    writeLock({ tabId, ts: now() });

    // 3) Heartbeat
    const interval = setInterval(() => {
      const lock = readLock();
      if (lock && isFresh(lock) && lock.tabId !== tabId) {
        clearInterval(interval);
        blockThisTab();
        return;
      }
      writeLock({ tabId, ts: now() });
    }, PING_MS);

    // 4) Liberar candado al salir
    window.addEventListener("beforeunload", () => {
      removeLockIfMine();
    });
  })();
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  window.FORCE_PASSWORD_CHANGE = <?= !empty($_SESSION['force_password_change']) ? 'true' : 'false' ?>;
  // ✅ Debug visible (puedes dejarlo o quitarlo)
  console.log("FORCE_PASSWORD_CHANGE:", window.FORCE_PASSWORD_CHANGE);
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  // ... (tus funciones de toast y modales se mantienen) ...

  // =====================================================
  // ✅ REEMPLAZO: LÓGICA DE DATOS ACTUALES
  // =====================================================
  function inicializarValoresActuales() {
    // Usamos el objeto global window.userData que inyectamos desde PHP
    const data = window.userData;
    
    const mapping = {
      'nombre': data.nombre_completo,
      'tipo_documento': data.tipo_documento,
      'numero_documento': data.numero_documento,
      'correo': data.correo
    };

    for (const [campo, valor] of Object.entries(mapping)) {
      const fieldWrap = document.getElementById(`field_${campo}`);
      if (fieldWrap) {
        const input = fieldWrap.querySelector("input, select");
        if (input) {
          input.setAttribute('data-valor-actual', valor || "");
          if (input.tagName === 'INPUT') input.placeholder = "Actual: " + (valor || "No registrado");
        }
      }
    }
  }

    // ✅ Toasts Flowbite (siguen sirviendo para otras cosas tuyas)
    initFlowbiteToasts();

        const resp = await fetch("src/controllers/usuario_controller.php?accion=editar_perfil_usuario", {
          method: "POST",
          body: formData
        });

        const data = await resp.json();
        if (data.success) {
          toastSuccess("Perfil actualizado correctamente");
          setTimeout(() => location.reload(), 1000);
        } else {
          toastError(data.error || "Error al actualizar");
        }
      } catch (err) {
        toastError("Error de comunicación con el servidor");
      } finally {
        btn.disabled = false;
        btn.innerHTML = 'Guardar cambios';
      }
    });
  }

  // =====================================================
  // ✅ EVENTO: SOLICITAR CAMBIO SENSIBLE
  // =====================================================
  if (formDatosSensibles) {
    formDatosSensibles.addEventListener("submit", async (e) => {
      e.preventDefault();
      const selected = Array.from(document.querySelectorAll('input[data-sensible]:checked'));

      if (selected.length === 0) {
        toastError("Selecciona al menos un campo para modificar.");
        return;
      }

      const datosCambiados = {};
      for (const chk of selected) {
        const key = chk.getAttribute("data-sensible");
        const input = document.getElementById(`field_${key}`).querySelector("input, select");
        const nuevoValor = input.value.trim();
        const valorAnterior = input.getAttribute("data-valor-actual");

        if (!nuevoValor) {
          toastError(`El campo ${key} no puede estar vacío.`);
          return;
        }

        // Estructura exacta que espera tu notificaciones.php
        datosCambiados[key] = {
          anterior: valorAnterior,
          nuevo: nuevoValor,
          campo_nombre: obtenerNombreCampo(key)
        };
      }

      try {
        const formData = new FormData();
        formData.append("id_usuario", window.userData.id_usuario);
        formData.append("datos_cambiados", JSON.stringify(datosCambiados));

        const resp = await fetch("src/controllers/usuario_controller.php?accion=solicitar_cambio_datos_sensibles", {
          method: "POST",
          body: formData
        });

        const res = await resp.json();
        if (res.success) {
          toastSuccess("Solicitud enviada a coordinación.");
          closeModal(modalDatosSensibles);
        } else {
          toastError(res.error || "No se pudo enviar la solicitud.");
        }
      } catch (error) {
        toastError("Error de conexión.");
      }
    });
  }
  
  // Helper simple para los nombres de los campos
  function obtenerNombreCampo(key) {
    const nombres = {
      'nombre': 'Nombre Completo',
      'tipo_documento': 'Tipo de Documento',
      'numero_documento': 'Número de Documento',
      'correo': 'Correo Electrónico'
    };
    return nombres[key] || key;
  }
});

// =========================
// ✅ ALERTAS TIPO USUARIOS (PEQUEÑAS)
// =========================
function initFlowbiteToasts() {
  // Define funciones globales si no existen (tu código ya las invoca)
  if (typeof window.toastSuccess !== "function") {
    window.toastSuccess = function(message) {
      showFlowbiteToast("success", message);
    };
  }
  if (typeof window.toastError !== "function") {
    window.toastError = function(message) {
      showFlowbiteToast("error", message);
    };
  }
  if (typeof window.toastWarning !== "function") {
    window.toastWarning = function(message) {
      showFlowbiteToast("warning", message);
    };
  }
}

function showFlowbiteToast(type, message) {
  const container = document.getElementById("toastContainer");
  if (!container) return;

  const id = "toast_" + Date.now() + "_" + Math.floor(Math.random() * 1000);

  // ✅ Estilo igual a USUARIOS: tarjeta pequeña + borde izquierdo + tipografía sm
  let borderColor = "border-amber-500";
  let textColor = "text-amber-900";
  let titleText = "Advertencia";
  let iconSVG = `
    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
         fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
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
           fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm-1 15-4-4 1.414-1.414L9 12.172l4.586-4.586L15 9z"/>
      </svg>
    `;
  }

  if (type === "error") {
    borderColor = "border-red-500";
    textColor = "text-red-900";
    titleText = "Error";
    iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
           fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm3.536 13.536a1 1 0 0 1-1.414 0L10 11.414 7.879 13.536a1 1 0 1 1-1.415-1.414L8.586 10 6.464 7.879a1 1 0 0 1 1.415-1.415L10 8.586l2.122-2.122a1 1 0 0 1 1.414 1.415L11.414 10l2.122 2.122a1 1 0 0 1 0 1.414Z"/>
      </svg>
    `;
  }

  const toast = document.createElement("div");
  toast.id = id;

  toast.className = `
    relative flex items-center w-full mx-auto pointer-events-auto
    rounded-2xl border-l-4 ${borderColor} bg-white shadow-md
    px-4 py-3 text-sm ${textColor}
    opacity-0 -translate-y-2
    transition-all duration-300 ease-out
    animate-fade-in-up
  `;

  toast.setAttribute("role", "alert");
  toast.innerHTML = `
    <div class="flex-shrink-0 mr-3 text-current">
      ${iconSVG}
    </div>

    <div class="flex-1 min-w-0">
      <p class="font-semibold">${escapeHtml(titleText)}</p>
      <p class="mt-0.5 text-sm">${escapeHtml(String(message || ""))}</p>
    </div>
  `;

  container.appendChild(toast);

  requestAnimationFrame(() => {
    toast.classList.remove("opacity-0", "-translate-y-2");
    toast.classList.add("opacity-100", "translate-y-0");
  });

  toast.addEventListener("click", () => {
    toast.classList.add("opacity-0", "-translate-y-2");
    toast.classList.remove("opacity-100", "translate-y-0");
    setTimeout(() => toast.remove(), 250);
  });

  setTimeout(() => {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add("opacity-0", "-translate-y-2");
    el.classList.remove("opacity-100", "translate-y-0");
    setTimeout(() => el.remove(), 250);
  }, 4000);
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// =========================
// ✅ OJITOS (TOGGLE PASSWORD)
// =========================
function initPasswordToggles() {
  const buttons = document.querySelectorAll("[data-toggle-password]");
  if (!buttons || buttons.length === 0) return;

  buttons.forEach((btn) => {
    const selector = btn.getAttribute("data-toggle-password");
    const input = selector ? document.querySelector(selector) : null;
    if (!input) return;

    const eyeOn  = btn.querySelector(".toggle-eye-on");
    const eyeOff = btn.querySelector(".toggle-eye-off");

    btn.addEventListener("click", () => {
      const isPassword = input.type === "password";
      input.type = isPassword ? "text" : "password";

      if (eyeOn)  eyeOn.classList.toggle("hidden", isPassword);
      if (eyeOff) eyeOff.classList.toggle("hidden", !isPassword);
    });
  });
}

// ✅ MISMA LÓGICA QUE TENÍAS, pero como función (más estable)
function forcePasswordFlow() {
  const modal = document.getElementById("modalForcePassword");
  const form  = document.getElementById("formForcePassword");

  if (!modal || !form) return;

  // Solo si está forzado por sesión
  if (!window.FORCE_PASSWORD_CHANGE) return;

  // Mostrar modal
  modal.classList.add("active");
  document.body.style.overflow = "hidden"; // ✅ evita que sigan navegando detrás

  // Bloquear cierre por overlay click
  modal.addEventListener("click", (e) => {
    if (e.target === modal) e.stopPropagation();
  }, true);

  // Bloquear ESC
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" || e.key === "Esc" || e.keyCode === 27) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }
  }, true);

  // Submit
  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const actual = document.getElementById("fp_actual").value.trim();
    const nueva  = document.getElementById("fp_nueva").value.trim();
    const conf   = document.getElementById("fp_confirmar").value.trim();

    if (!actual || !nueva || !conf) {
      if (typeof toastWarning === "function") toastWarning("Complete todos los campos.");
      else if (typeof toastError === "function") toastError("Complete todos los campos.");
      return;
    }

    if (nueva.length < 8) {
      if (typeof toastWarning === "function") toastWarning("La nueva contraseña debe tener mínimo 8 caracteres.");
      else if (typeof toastError === "function") toastError("La nueva contraseña debe tener mínimo 8 caracteres.");
      return;
    }

    const hasUpper   = /[A-Z]/.test(nueva);
    const hasNumber  = /[0-9]/.test(nueva);
    const hasSpecial = /[^A-Za-z0-9]/.test(nueva);

    if (!hasUpper || !hasNumber || !hasSpecial) {
      if (typeof toastWarning === "function") {
        toastWarning("La contraseña debe incluir al menos 1 mayúscula, 1 número y 1 carácter especial.");
      } else if (typeof toastError === "function") {
        toastError("La contraseña debe incluir al menos 1 mayúscula, 1 número y 1 carácter especial.");
      }
      return;
    }

    if (nueva !== conf) {
      if (typeof toastWarning === "function") toastWarning("La confirmación no coincide.");
      else if (typeof toastError === "function") toastError("La confirmación no coincide.");
      return;
    }

    try {
      const fd = new FormData();
      fd.append("password_actual", actual);
      fd.append("password_nueva", nueva);
      fd.append("password_confirmar", conf);

      const res = await fetch("src/controllers/usuario_controller.php?accion=cambiar_password", {
        method: "POST",
        body: fd
      });

      const data = await res.json();

      if (data.error) {
        if (typeof toastError === "function") toastError(data.error);
        return;
      }

      if (typeof toastSuccess === "function") toastSuccess("Contraseña actualizada correctamente.");

      modal.classList.remove("active");
      document.body.style.overflow = "";

      const url = new URL(window.location.href);
      url.searchParams.delete("force_pass");
      window.location.replace(url.toString());

    } catch (err) {
      console.error(err);
      if (typeof toastError === "function") toastError("Error de red al cambiar la contraseña.");
    }
  });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  window.FORCE_PASSWORD_CHANGE = <?= !empty($_SESSION['force_password_change']) ? 'true' : 'false' ?>;
  console.log("FORCE_PASSWORD_CHANGE:", window.FORCE_PASSWORD_CHANGE);
</script>

<script>
  // ========= CONSUMO MENSUAL (BARRAS) =========
  const labelsConsumo = <?php echo json_encode(array_column($consumoData, 'name')); ?>;
  const valoresConsumo = <?php echo json_encode(array_map('intval', array_column($consumoData, 'consumo'))); ?>;

  const totalMateriales = valoresConsumo.reduce((acc, val) => acc + val, 0);
  const maxY = totalMateriales > 0 ? totalMateriales : 10;

  const consumoCtx = document.getElementById('consumoChart').getContext('2d');

  const consumoChart = new Chart(consumoCtx, {
      type: 'bar',
      data: {
      labels: labelsConsumo,
      datasets: [{
          label: 'Consumo de materiales',
          data: valoresConsumo,
          backgroundColor: 'rgba(148, 163, 184, 0.75)',
          borderRadius: 8,
          borderSkipped: false
      }]
      },
      options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
          legend: { display: false },
          tooltip: {
          enabled: true,
          callbacks: {
              label: function(context) {
              const valor = context.parsed.y || 0;
              return valor + ' materiales';
              }
          }
          }
      },
      scales: {
          x: { grid: { display: false }, ticks: { font: { size: 10 } } },
          y: {
          beginAtZero: true,
          suggestedMax: maxY,
          ticks: {
              stepSize: Math.max(1, Math.round(maxY / 5)),
              font: { size: 10 }
          },
          grid: { color: 'rgba(229, 231, 235, 0.8)' }
          }
      }
      }
  });

  // ========= DISTRIBUCIÓN POR CATEGORÍA (DOUGHNUT) =========
  const categoriaLabels = <?php echo json_encode(array_column($categoriaData, 'name')); ?>;
  const categoriaValoresRaw = <?php echo json_encode(array_map('intval', array_column($categoriaData, 'value'))); ?>;
  const categoriaColoresRaw = <?php echo json_encode(array_column($categoriaData, 'color')); ?>;

  const totalCategoriasValor = categoriaValoresRaw.reduce((acc, val) => acc + val, 0);

  let categoriaLabelsFinal = categoriaLabels;
  let categoriaValoresFinal = categoriaValoresRaw;
  let categoriaColoresFinal = categoriaColoresRaw;

  if (totalCategoriasValor === 0) {
      categoriaLabelsFinal = ['Sin datos'];
      categoriaValoresFinal = [1];
      categoriaColoresFinal = ['rgba(148, 163, 184, 0.4)'];
  }

  const categoriaCtx = document.getElementById('categoriaChart').getContext('2d');

  const categoriaChart = new Chart(categoriaCtx, {
      type: 'doughnut',
      data: {
      labels: categoriaLabelsFinal,
      datasets: [{
          data: categoriaValoresFinal,
          backgroundColor: categoriaColoresFinal,
          borderWidth: 0
      }]
      },
      options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '65%',
      plugins: {
          legend: { display: false },
          tooltip: {
          callbacks: {
              label: function(context) {
              if (totalCategoriasValor === 0) return 'Sin datos';
              const value = context.parsed;
              const percent = ((value / totalCategoriasValor) * 100).toFixed(1);
              return `${context.label}: ${value}% (${percent}%)`;
              }
          }
          }
      }
      }
  });
</script>

<script>
  // ✅ Re-inicializa lucide si algo nuevo aparece
  document.addEventListener("DOMContentLoaded", function () {
    if (window.lucide && typeof lucide.createIcons === "function") {
      lucide.createIcons();
    }
  });
</script>

</body>
</html>
