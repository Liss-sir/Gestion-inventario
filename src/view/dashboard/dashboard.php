<?php

// ✅ NECESARIO: si NO inicias sesión, $_SESSION estará vacío y el modal nunca se activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";
// ===============================
//  Mock data equivalente a mock-data.ts
//  (puedes luego conectarlo a tu DB)
// ===============================

$mockMateriales = [
    ["id" => 1, "nombre" => "Cemento gris", "estado" => "disponible"],
    ["id" => 2, "nombre" => "Cable eléctrico", "estado" => "disponible"],
    ["id" => 3, "nombre" => "Pintura blanca", "estado" => "agotado"],
];

$mockBodegas = [
    ["id" => 1, "nombre" => "Bodega Principal", "estado" => true],
    ["id" => 2, "nombre" => "Bodega Secundaria", "estado" => true],
    ["id" => 3, "nombre" => "Bodega Antigua", "estado" => false],
];

$mockMovimientos = [
    ["id" => 1, "tipo" => "entrada", "material_nombre" => "Cemento gris", "cantidad" => 20, "fecha" => "2024-11-27", "hora" => "08:15"],
    ["id" => 2, "tipo" => "salida",  "material_nombre" => "Cable eléctrico", "cantidad" => 10, "fecha" => "2024-11-27", "hora" => "10:30"],
    ["id" => 3, "tipo" => "entrada", "material_nombre" => "Pintura blanca", "cantidad" => 5, "fecha" => "2024-11-26", "hora" => "16:10"],
    ["id" => 4, "tipo" => "salida",  "material_nombre" => "Cemento gris", "cantidad" => 8, "fecha" => "2024-11-27", "hora" => "17:45"],
];

$mockSolicitudes = [
    ["id" => 1, "instructor_nombre" => "Juan Pérez",  "ficha_numero" => "2567890", "estado" => "pendiente"],
    ["id" => 2, "instructor_nombre" => "Ana Gómez",   "ficha_numero" => "2456789", "estado" => "aprobada"],
    ["id" => 3, "instructor_nombre" => "Carlos Ruiz", "ficha_numero" => "2345678", "estado" => "rechazada"],
    ["id" => 4, "instructor_nombre" => "Laura Díaz",  "ficha_numero" => "2678901", "estado" => "pendiente"],
];

$mockAlerts = [
    ["material_id" => 1, "material_nombre" => "Cemento gris",   "stock_actual" => 5,  "stock_minimo" => 20],
    ["material_id" => 2, "material_nombre" => "Cable eléctrico","stock_actual" => 3,  "stock_minimo" => 15],
];

$consumoData = [
    [ "name" => "Ene", "consumo" => 120 ],
    [ "name" => "Feb", "consumo" => 98 ],
    [ "name" => "Mar", "consumo" => 145 ],
    [ "name" => "Abr", "consumo" => 87 ],
    [ "name" => "May", "consumo" => 156 ],
    [ "name" => "Jun", "consumo" => 134 ],
];

$categoriaData = [
    [ "name" => "Construcción", "value" => 45, "color" => "var(--chart-1)" ],
    [ "name" => "Eléctrico",    "value" => 25, "color" => "var(--chart-2)" ],
    [ "name" => "Herramientas", "value" => 20, "color" => "var(--chart-3)" ],
    [ "name" => "Pinturas",     "value" => 10, "color" => "var(--chart-4)" ],
];

// CATEGORÍAS (sin color aún, solo nombre + valor)
$categoriaDataRaw = [
    [ "name" => "Construcción", "value" => 45 ],
    [ "name" => "Eléctrico",    "value" => 25 ],
    [ "name" => "Herramientas", "value" => 20 ],
    [ "name" => "Pinturas",     "value" => 10 ],
];

// Paleta de colores reutilizable
$palette = [
    "#39A900", // Verde principal
    "#007832", // Verde secundario (oscuro)
    "#00304D", // Azul oscuro
    "#71277A", // Morado
    "#50E5F9", // Cian
    "#FDC300", // Amarillo
    "#F6F6F6", // Gris claro
    "#FFFFFF", // Blanco
    "#000000", // Negro
];

// Construimos $categoriaData con color dinámico
$categoriaData = [];
foreach ($categoriaDataRaw as $index => $cat) {
    $color = $palette[$index % count($palette)];
    $categoriaData[] = [
        "name"  => $cat["name"],
        "value" => $cat["value"],
        "color" => $color,
    ];
}

// ===============================
//  Cálculos equivalentes a los de React
//  ✅ CORREGIDO: sin fn() => (compatibilidad PHP < 7.4)
// ===============================
$solicitudesPendientes = count(array_filter($mockSolicitudes, function($s){
    return $s["estado"] === "pendiente";
}));

$materialesActivos = count(array_filter($mockMateriales, function($m){
    return $m["estado"] === "disponible";
}));

$bodegasActivas = count(array_filter($mockBodegas, function($b){
    return !empty($b["estado"]);
}));

$movimientosHoy = count(array_filter($mockMovimientos, function($m){
    return $m["fecha"] === "2024-11-27";
}));

$maxConsumo = max(array_column($consumoData, "consumo"));

$totalCategorias = array_sum(array_column($categoriaData, "value"));
$currentAngle = 0;
$gradientParts = [];
foreach ($categoriaData as $cat) {
    $angle = ($cat["value"] / $totalCategorias) * 360;
    $start = $currentAngle;
    $end   = $currentAngle + $angle;
    $gradientParts[] = $cat["color"] . " " . $start . "deg " . $end . "deg";
    $currentAngle = $end;
}
$pieGradient = implode(", ", $gradientParts);
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
        <p class="mt-2 text-2xl font-bold"><?php echo count($mockMateriales); ?></p>
        <span class="text-xs text-success flex items-center">+12%<i data-lucide="trending-up" class="ml-2 h-4 w-4 text-[#39A900]"></i></span>
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
        <span class="text-xs text-success flex items-center">Sin cambios</span>
    </div>
    <p class="text-xs text-muted-foreground">de <?php echo count($mockBodegas); ?> registradas</p>
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
        <span class="text-xs text-success flex items-center">+8%<i data-lucide="trending-up" class="ml-2 h-4 w-4 text-[#39A900]"></i></span>
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
        <p class="mt-2 text-2xl font-bold"><?php echo count($mockAlerts); ?></p>
        <span class="text-xs text-success flex items-center">+8%<i data-lucide="trending-down" class="ml-2 h-4 w-4 text-[#EF4444]"></i></span>
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
                <h2 class="text-base font-semibold">Distribución por Categoría</h2>
                <p class="text-sm text-muted-foreground">Materiales activos</p>
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
        <?php if (count($mockAlerts) === 0): ?>
        <p class="text-center text-sm text-muted-foreground py-4">No hay alertas de stock</p>
        <?php else: ?>
        <?php foreach ($mockAlerts as $alert):
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
        <?php foreach (array_slice($mockSolicitudes, 0, 3) as $solicitud):
        $estado = $solicitud["estado"];
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
            <?php echo htmlspecialchars($estado); ?>
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
        <?php foreach (array_slice($mockMovimientos, 0, 4) as $mov):
        // ✅ Estilo como la foto: bolita perfecta + verde suave
        if ($mov["tipo"] === "entrada") {
            $movClasses = "bg-[#39A9001A] text-[#39A900]";
        } elseif ($mov["tipo"] === "salida") {
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
            <p class="text-sm font-medium capitalize"><?php echo htmlspecialchars($mov["tipo"]); ?></p>
            <p class="text-xs text-muted-foreground truncate">
                <?php echo htmlspecialchars($mov["material_nombre"]); ?> (<?php echo $mov["cantidad"]; ?>)
            </p>
            </div>
            <span class="text-xs text-muted-foreground"><?php echo htmlspecialchars($mov["hora"]); ?></span>
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
                  class="absolute inset-y-0 right-0 inline-flex items-center justify-center px-3 text-slate-500 hover:text-slate-700"
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

</main>

<!-- ✅ Contenedor de toasts (AHORA EN ESQUINA SUPERIOR DERECHA) -->
<div
  id="toastContainer"
  class="fixed top-6 right-6 z-[10000] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none"
></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  window.FORCE_PASSWORD_CHANGE = <?= !empty($_SESSION['force_password_change']) ? 'true' : 'false' ?>;
  // ✅ Debug visible (puedes dejarlo o quitarlo)
  console.log("FORCE_PASSWORD_CHANGE:", window.FORCE_PASSWORD_CHANGE);
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (window.lucide && typeof lucide.createIcons === "function") {
        lucide.createIcons();
    }

    // ✅ Toasts Flowbite (reemplaza alerts y unifica notificaciones) - AHORA TAMAÑO PEQUEÑO COMO USUARIOS
    initFlowbiteToasts();

    // ✅ Ojitos (toggle show/hide password)
    initPasswordToggles();

    // ✅ Ejecutar el flujo del modal ya con DOM cargado
    forcePasswordFlow();
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

  // ✅ En dashboard usamos "error" (en usuarios usan warning). Aquí lo dejamos rojo.
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

  // Smooth fade-in
  requestAnimationFrame(() => {
    toast.classList.remove("opacity-0", "-translate-y-2");
    toast.classList.add("opacity-100", "translate-y-0");
  });

  // Click para cerrar (opcional)
  toast.addEventListener("click", () => {
    toast.classList.add("opacity-0", "-translate-y-2");
    toast.classList.remove("opacity-100", "translate-y-0");
    setTimeout(() => toast.remove(), 250);
  });

  // Auto-dismiss como en usuarios
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

    // ✅ Reglas: mínimo 8
    if (nueva.length < 8) {
      if (typeof toastWarning === "function") toastWarning("La nueva contraseña debe tener mínimo 8 caracteres.");
      else if (typeof toastError === "function") toastError("La nueva contraseña debe tener mínimo 8 caracteres.");
      return;
    }

    // ✅ Reglas: mayúscula + número + caracter especial
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

      // Cerrar modal
      modal.classList.remove("active");
      document.body.style.overflow = ""; // ✅ restaura scroll

      // Limpia el query param force_pass (si existe)
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
</body>
</html>
