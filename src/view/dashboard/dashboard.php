<?php

// ✅ NECESARIO: si NO inicias sesión, $_SESSION estará vacío y el modal nunca se activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../../Config/database.php';
require_once __DIR__ . "/../../utils/permisos_helper.php";


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

try {
// =====================================================
// ✅ CONTROL DE VISUALIZACIÓN POR PERMISOS (DASHBOARD)
// - Aprendiz / Pasante / Instructor NO verán:
//   Pendientes, Nuevo Material, Ver todo (stock)
// - ✅ EN Solicitudes Recientes / Actividad Reciente:
//   SOLO se oculta "Ver todo" (NO se oculta el cuadro)
// - ✅ FIX NUEVO (TU PEDIDO):
//   Si el usuario tiene rol funcional asignado:
//   encargado_inventario / encargado_bodega / encargado_subbodega
//   ENTONCES DEBE VER LO BLOQUEADO.
// =====================================================

// =====================================================
// ✅ FIX: detectar roles funcionales asignados en sesión
// =====================================================
$rolesFuncionales = [];

// Caso 1: si guardas un array de roles funcionales
if (!empty($_SESSION['roles_funcionales']) && is_array($_SESSION['roles_funcionales'])) {
  $rolesFuncionales = $_SESSION['roles_funcionales'];
}

// Caso 2: si guardas 1 solo rol funcional como string
if (empty($rolesFuncionales) && !empty($_SESSION['rol_funcional'])) {
  $rolesFuncionales = [$_SESSION['rol_funcional']];
}
if (empty($rolesFuncionales) && !empty($_SESSION['rol_funcional_nombre'])) {
  $rolesFuncionales = [$_SESSION['rol_funcional_nombre']];
}

// Normalizamos a minúsculas por seguridad
$rolesFuncionales = array_map(function($r){
  return strtolower(trim((string)$r));
}, $rolesFuncionales);

// Roles funcionales "poderosos" que deben ver lo bloqueado
$rolesFuncPoderosos = ["encargado_inventario", "encargado_bodega", "encargado_subbodega"];

// ✅ true si tiene alguno de esos roles
$tieneRolFuncPoderoso = false;
foreach ($rolesFuncionales as $rf) {
  if (in_array($rf, $rolesFuncPoderosos, true)) {
    $tieneRolFuncPoderoso = true;
    break;
  }
}

// =====================================================
// ✅ CONTEXTO PARA FILTROS DE INSTRUCTOR
// =====================================================
$cargoActual = permisos_resolver_alias(permisos_getCargo());
$esInstructor = ($cargoActual === "instructor" && !$tieneRolFuncPoderoso);
$idUsuarioSesion = (int)($_SESSION['usuario_id'] ?? $_SESSION['id_usuario'] ?? 0);

$fichasInstructor = $_SESSION['usuario_fichas'] ?? [];
$fichasInstructorIds = [];
if (is_array($fichasInstructor)) {
  foreach ($fichasInstructor as $fichaRow) {
    if (isset($fichaRow['id_ficha'])) {
      $fichasInstructorIds[] = (int)$fichaRow['id_ficha'];
    }
  }
}
$fichasInstructorIds = array_values(array_unique(array_filter($fichasInstructorIds)));

// =====================================================
// ✅ PERMISOS + OVERRIDE POR ROL FUNCIONAL PODEROSO
// =====================================================

$canVerPendientesBtn        = canPermiso("solicitudes.gestionar") || $tieneRolFuncPoderoso;
$canVerNuevoMaterialBtn     = (canPermiso("materiales.crear") || canPermiso("materiales.gestionar")) || $tieneRolFuncPoderoso;
$canVerVerTodoStockBtn      = (canPermiso("stock.controlar") || canPermiso("materiales.gestionar")) || $tieneRolFuncPoderoso;

// ✅ SOLO PARA LOS BOTONES "Ver todo"
$canVerVerTodoSolicitudesBtn =
    permisos_puedeAccederModulo("solicitudes") ||   // ✅ Si puede entrar al módulo
    canPermiso("solicitudes.consultar") ||          // ✅ Si puede consultar
    canPermiso("solicitudes.crear") ||              // ✅ Si puede crear (PASANTE / INSTRUCTOR / APRENDIZ)
    $tieneRolFuncPoderoso;                          // ✅ Override por rol funcional

$canVerVerTodoActividadBtn   = canPermiso("movimientos.gestionar") || $tieneRolFuncPoderoso;



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
if ($esInstructor) {
  $stmtMovHoy = $conn->prepare("SELECT COUNT(*) FROM devoluciones_material WHERE id_usuario = :id AND DATE(fecha_hora) = CURDATE()");
  $stmtMovHoy->execute([':id' => $idUsuarioSesion]);
  $movimientosHoy = (int)($stmtMovHoy->fetchColumn() ?: 0);
} else {
  $sqlMovHoy = "SELECT SUM(cnt) AS total FROM (
    SELECT COUNT(*) AS cnt FROM movimientos_material WHERE DATE(fecha_hora) = CURDATE()
    UNION ALL
    SELECT COUNT(*) AS cnt FROM devoluciones_material WHERE DATE(fecha_hora) = CURDATE()
  ) t";
  $movimientosHoy = (int)($conn->query($sqlMovHoy)->fetchColumn() ?: 0);
}

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
$sqlSolBase = "
  SELECT sm.id_solicitud, sm.estado, sm.fecha_solicitud,
       COALESCE(u.nombre_completo, 'Sin instructor') AS instructor_nombre,
       COALESCE(f.numero_ficha, 'N/A') AS ficha_numero
  FROM solicitudes_material sm
  LEFT JOIN usuarios u ON u.id_usuario = sm.id_usuario_solicitante
  LEFT JOIN fichas f ON f.id_ficha = sm.id_ficha
";

if ($esInstructor) {
  $sqlSol = $sqlSolBase . " WHERE sm.id_usuario_solicitante = :id ORDER BY sm.fecha_solicitud DESC LIMIT 4";
  $stmtSol = $conn->prepare($sqlSol);
  $stmtSol->execute([':id' => $idUsuarioSesion]);
  $recentSolicitudes = $stmtSol->fetchAll(PDO::FETCH_ASSOC);
} else {
  $sqlSol = $sqlSolBase . " ORDER BY sm.fecha_solicitud DESC LIMIT 4";
  $recentSolicitudes = $conn->query($sqlSol)->fetchAll(PDO::FETCH_ASSOC);
}


// Solicitudes pendientes
$solicitudesPendientes = 0;
if ($canVerPendientesBtn) {
  $solicitudesPendientes = (int)($conn->query("SELECT COUNT(*) FROM solicitudes_material WHERE estado = 'Pendiente'")->fetchColumn() ?: 0);
}


// Movimientos recientes (entrada/salida/devolución) máx 4
if ($esInstructor) {
  $sqlRecentMov = "
    SELECT d.fecha_hora, 'devolucion' AS tipo, mf.nombre AS material_nombre, d.cantidad_devuelta AS cantidad,
         DATE_FORMAT(d.fecha_hora, '%H:%i') AS hora
    FROM devoluciones_material d
    LEFT JOIN material_formacion mf ON mf.id_material = d.id_material
    WHERE d.id_usuario = :id
    ORDER BY d.fecha_hora DESC
    LIMIT 4
  ";
  $stmtMov = $conn->prepare($sqlRecentMov);
  $stmtMov->execute([':id' => $idUsuarioSesion]);
  $recentMovimientos = $stmtMov->fetchAll(PDO::FETCH_ASSOC);
} else {
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
}


// Consumo mensual (salidas) Enero-Diciembre
$consumoData = [];
$fichaFilterSql = "";
$fichaFilterParams = [];
if ($esInstructor) {
  if (!empty($fichasInstructorIds)) {
    $placeholders = [];
    foreach ($fichasInstructorIds as $idx => $fid) {
      $key = ":ficha" . $idx;
      $placeholders[] = $key;
      $fichaFilterParams[$key] = $fid;
    }
    $fichaFilterSql = " AND id_ficha IN (" . implode(",", $placeholders) . ")";
  }
}

for ($m = 1; $m <= 12; $m++) {
  if ($esInstructor && empty($fichasInstructorIds)) {
    $totalMes = 0;
  } else {
    $sqlConsumo = "SELECT COALESCE(SUM(cantidad),0) FROM movimientos_material WHERE tipo_movimiento = 'Salida' AND YEAR(fecha_hora) = YEAR(CURDATE()) AND MONTH(fecha_hora) = :m" . $fichaFilterSql;
    $stmtC = $conn->prepare($sqlConsumo);
    $params = array_merge([':m' => $m], $fichaFilterParams);
    $stmtC->execute($params);
    $totalMes = (int)$stmtC->fetchColumn();
  }
  $consumoData[] = [
    'name' => date('M', mktime(0,0,0,$m,1)),
    'consumo' => $totalMes
  ];
}

// Distribución por obra (usa el nombre_actividad como título de obra)
if ($esInstructor) {
  if (!empty($fichasInstructorIds)) {
    $placeholders = implode(",", array_fill(0, count($fichasInstructorIds), "?"));
    $sqlCat = "
      SELECT COALESCE(af.nombre_actividad, 'Sin obra') AS categoria, COUNT(*) AS total
      FROM actividades_formacion af
      WHERE af.id_ficha IN (" . $placeholders . ")
      GROUP BY COALESCE(af.nombre_actividad, 'Sin obra')
    ";
    $stmtCat = $conn->prepare($sqlCat);
    $stmtCat->execute($fichasInstructorIds);
    $catRows = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $catRows = [];
  }
} else {
  $sqlCat = "
    SELECT COALESCE(af.nombre_actividad, 'Sin obra') AS categoria, COUNT(*) AS total
    FROM actividades_formacion af
    GROUP BY COALESCE(af.nombre_actividad, 'Sin obra')
  ";
  $catRows = $conn->query($sqlCat)->fetchAll(PDO::FETCH_ASSOC);
}

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
} catch (Throwable $e) {
  error_log('Dashboard data error: ' . $e->getMessage());
  http_response_code(500);
  echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error</title></head><body>';
  echo '<h1>Error al cargar el dashboard</h1>';
  echo '<p>Detalle técnico:</p>';
  echo '<pre style="white-space:pre-wrap;font-family:monospace">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
  echo '</body></html>';
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- ✅ Flowbite (para notificaciones estilo Flowbite, igual que en Usuarios) -->
    <script src="https://unpkg.com/flowbite@2.5.1/dist/flowbite.min.js"></script>

    <link rel="stylesheet" href="src/assets/css/globals.css">
    <style>
      .modal-overlay{
        position:fixed;
        inset:0;
        display:none;             
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

  <?php if ($canVerPendientesBtn): ?>
    <a href="?page=solicitudes">
      <button class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-border bg-transparent px-4 py-2 text-sm font-medium text-foreground shadow-sm hover:bg-muted gap-2">
        <i data-lucide="clock" class="h-5 w-5 "></i>
        Pendientes
      </button>
    </a>
  <?php endif; ?>

  <?php if ($canVerNuevoMaterialBtn): ?>
    <a href="?page=materiales">
      <button class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 gap-2">
        <i data-lucide="package" class="h-4 w-4"></i>
        Nuevo Material
      </button>
    </a>
  <?php endif; ?>

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
  <?php if (empty($consumoData) || ($maxConsumo ?? 0) <= 0): ?>
    <div class="border-t border-border h-44 flex items-center justify-center text-center ">
      <div class="flex flex-col items-center justify-center">
        <div class="h-11 w-11 rounded-full bg-slate-100 flex items-center justify-center">
          <i data-lucide="trending-up" class="h-5 w-5 text-slate-500"></i>
        </div>
        <p class="mt-3 text-sm font-medium text-slate-700">Sin datos de consumo</p>
        <p class="text-xs text-slate-500">
          Cuando haya movimientos, la gráfica mostrará el consumo automáticamente.
        </p>
      </div>
    </div>
  <?php else: ?>
    <div class="border-t border-border pt-4 h-44">
      <canvas id="consumoChart" class="w-full h-full"></canvas>
    </div>
  <?php endif; ?>
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
      <?php $totalCategoriaValues = array_sum(array_column($categoriaData ?? [], 'value')) ?: 0; ?>
      <?php if ($totalCategoriaValues <= 0): ?>
      <div class="border-t border-border pt-4">
        <div class="flex flex-col items-center justify-center py-8 w-full">
          <div class="h-40 w-40 rounded-full bg-slate-100 flex items-center justify-center">
            <div class="h-24 w-24 rounded-full bg-white"></div>
          </div>
          <p class="mt-4 text-sm font-medium text-slate-700">Sin datos</p>
          <p class="text-xs text-slate-500">Cuando haya actividades, se mostrará la distribución por obra.</p>
        </div>
      </div>
      <?php else: ?>
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
      <?php endif; ?>
    </div>
    </div>
</div>

<!-- Bottom Row -->
<div class="grid gap-6 lg:grid-cols-3">
    <!-- Stock Alerts -->
    <div class="rounded-xl border border-border bg-card">
    <div class="flex items-center justify-between px-6 pt-4 pb-3">
        <h2 class="text-base font-semibold">Alertas de Stock</h2>
        <?php if ($canVerVerTodoStockBtn): ?>
          <a href="?page=materiales">
            <button class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-muted gap-1 h-8">
              Ver todo
              <i data-lucide="arrow-right" class="h-3 w-3"></i>
            </button>
          </a>
        <?php endif; ?>

    </div>
    <div class="px-6 pb-4 space-y-4">
        <?php if (count($stockAlerts) === 0): ?>
        <div class="flex flex-col items-center justify-center py-6 text-center">
          <div class="h-11 w-11 rounded-full bg-slate-100 flex items-center justify-center">
            <i data-lucide="alert-triangle" class="h-5 w-5 text-slate-500"></i>
          </div>
          <p class="mt-3 text-sm font-medium text-slate-700">No hay alertas de stock</p>
          <p class="text-xs text-slate-500">Cuando algún material esté por debajo del mínimo, aparecerá aquí automáticamente.</p>
        </div>
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

        <!-- ✅ FIX: SOLO ocultar "Ver todo" (NO el cuadro) -->
        <?php if ($canVerVerTodoSolicitudesBtn): ?>
          <a href="?page=solicitudes">
              <button class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-muted gap-1 h-8">
                  Ver todo
                  <i data-lucide="arrow-right" class="h-3 w-3"></i>
              </button>
          </a>
        <?php endif; ?>
    </div>

    <div class="px-6 pb-4 space-y-4">

      <!-- ✅ Mensaje bonito si no hay solicitudes -->
      <?php if (empty($recentSolicitudes)): ?>
        <div class="flex flex-col items-center justify-center py-6 text-center">
          <div class="h-11 w-11 rounded-full bg-slate-100 flex items-center justify-center">
            <i data-lucide="inbox" class="h-5 w-5 text-slate-500"></i>
          </div>
          <p class="mt-3 text-sm font-medium text-slate-700">Aún no hay solicitudes recientes</p>
          <p class="text-xs text-slate-500">Cuando se registren, aparecerán aquí automáticamente.</p>
        </div>
      <?php else: ?>

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

      <?php endif; ?>
    </div>
    </div>

    <!-- Actividad Reciente -->
    <div class="rounded-xl border border-border bg-card">
    <div class="flex items-center justify-between px-6 pt-4 pb-3">
        <h2 class="text-base font-semibold">Actividad Reciente</h2>

        <!-- ✅ FIX: SOLO ocultar "Ver todo" (NO el cuadro) -->
        <?php if ($canVerVerTodoActividadBtn): ?>
          <a href="?page=movimientos">
          <button class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-muted gap-1 h-8">
              Ver todo
              <i data-lucide="arrow-right" class="h-3 w-3"></i>
          </button>
          </a>
        <?php endif; ?>
    </div>

    <div class="px-6 pb-4 space-y-4">

      <!-- ✅ Mensaje bonito si no hay movimientos -->
      <?php if (empty($recentMovimientos)): ?>
        <div class="flex flex-col items-center justify-center py-6 text-center">
          <div class="h-11 w-11 rounded-full bg-slate-100 flex items-center justify-center">
            <i data-lucide="activity" class="h-5 w-5 text-slate-500"></i>
          </div>
          <p class="mt-3 text-sm font-medium text-slate-700">Sin actividad reciente</p>
          <p class="text-xs text-slate-500">Cuando haya movimientos, se verán reflejados aquí.</p>
        </div>
      <?php else: ?>

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

      <?php endif; ?>
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

    // 4) Logout automático al cerrar navegador/pestaña (compatible con todos los navegadores)
    window.addEventListener("beforeunload", () => {
      removeLockIfMine();
      // Usar fetch con keepalive (funciona en Chrome, Edge, Firefox, Safari)
      fetch("<?= BASE_URL ?>logout.php", {
        method: 'GET',
        keepalive: true
      }).catch(() => {}); // Ignorar errores
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
document.addEventListener("DOMContentLoaded", async function () {
  // ✅ FIX SIN TOCAR TU BASE:
  // Pusimos "async" porque estabas usando await en este bloque.
  
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

  // ⚠️ Tu base tiene partes que parecen pegadas incompletas (await fetch suelto)
  // Yo NO las borro ni las cambio, solo dejé async para que no reviente JS.
});
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