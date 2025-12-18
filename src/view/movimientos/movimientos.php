<?php
$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";

/* =========================
   ✅ DATOS DE EJEMPLO (REEMPLAZA POR TU BD)
========================= */
$materiales = [
  ["id" => 1, "nombre" => "Cemento Gris", "unidad" => "Bulto"],
  ["id" => 2, "nombre" => "Cable Eléctrico", "unidad" => "Metro"],
  ["id" => 3, "nombre" => "Taladro", "unidad" => "Unidad"],
];

$bodegas = [
  ["id" => 1, "nombre" => "Bodega Construcción"],
  ["id" => 2, "nombre" => "Bodega Principal"],
];

$subbodegas = [
  ["id" => 1, "id_bodega" => 1, "nombre" => "Subbodega Cemento"],
  ["id" => 2, "id_bodega" => 1, "nombre" => "Subbodega Herramientas"],
  ["id" => 3, "id_bodega" => 2, "nombre" => "Subbodega Eléctrico"],
];

$programas = [
  ["id" => 1, "nombre" => "ADSO"],
  ["id" => 2, "nombre" => "Producción Multimedia"],
  ["id" => 3, "nombre" => "Construcción"],
];

$fichas = [
  ["id" => 2895664, "nombre" => "2895664"],
  ["id" => 2895665, "nombre" => "2895665"],
];

$raes = [
  ["id" => 1, "nombre" => "RAE 1"],
  ["id" => 2, "nombre" => "RAE 2"],
];

$instructores = [
  ["id" => 1, "nombre" => "Juan Pablo Hernandez"],
  ["id" => 2, "nombre" => "Ana Gómez"],
  ["id" => 3, "nombre" => "Carlos Ruiz"],
];

$solicitudes = [
  ["id" => 101, "nombre" => "Solicitud #101"],
  ["id" => 102, "nombre" => "Solicitud #102"],
];

/*
  ✅ Movimientos de ejemplo (simulan tu tabla)
  Campos: id_movimiento, tipo_movimiento, fecha_hora, id_usuario, id_bodega, id_subbodega, cantidad, id_programa, id_ficha, id_rae, observaciones, id_solicitud
  + materiales (lista) para el modal del ojo
*/
$movimientos = [
  [
    "id_movimiento" => 1,
    "tipo_movimiento" => "salida",
    "fecha_hora" => "2024-11-20 08:30:00",
    "id_usuario" => 1,
    "id_bodega" => 1,
    "id_subbodega" => 2,
    "cantidad" => 10,
    "id_programa" => 1,
    "id_ficha" => 2895664,
    "id_rae" => 1,
    "observaciones" => "Uso en taller",
    "id_solicitud" => 101,
    "id_instructor" => 1,
    "estado" => "activo", // ✅ NUEVO
    "materiales" => [
      ["id_material" => 1, "nombre" => "Cemento Gris", "cantidad" => 5, "unidad" => "Bulto"],
      ["id_material" => 3, "nombre" => "Taladro", "cantidad" => 1, "unidad" => "Unidad"],
    ]
  ],
  [
    "id_movimiento" => 2,
    "tipo_movimiento" => "entrada",
    "fecha_hora" => "2024-11-21 10:05:00",
    "id_usuario" => 2,
    "id_bodega" => 2,
    "id_subbodega" => 3,
    "cantidad" => 25,
    "id_programa" => null,
    "id_ficha" => null,
    "id_rae" => null,
    "observaciones" => "Ingreso por compra",
    "id_solicitud" => null,
    "id_instructor" => null,
    "entrega" => "entrada_material",
    "estado" => "activo", // ✅ NUEVO
    "materiales" => [
      ["id_material" => 2, "nombre" => "Cable Eléctrico", "cantidad" => 25, "unidad" => "Metro"],
    ]
  ],
];

function findNameById($arr, $id) {
  foreach ($arr as $it) if ((string)$it["id"] === (string)$id) return $it["nombre"];
  return "-";
}
function badgeTipo($tipo) {
  $tipo = strtolower((string)$tipo);
  if ($tipo === "entrada") return ["Entrada", "bg-[#39A90020] text-slate-700", "arrow-up-from-line"];
  if ($tipo === "salida") return ["Salida", "bg-lime-100 text-lime-700", "arrow-down-up"];
  if ($tipo === "devolucion") return ["Devolución", "bg-[#39A90020] text-slate-700", "rotate-ccw"];
  return [ucfirst($tipo), "bg-gray-100 text-gray-700", "arrow-down-up"];
}

/* =========================
   ✅ PAGINACIÓN (20 FILAS)
========================= */
$perPage = 20;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$total = count($movimientos);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;
$movimientosPage = array_slice($movimientos, $offset, $perPage);

// Mantener coll en links
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
  <link rel="stylesheet" href="../../assets/css/globals.css">
</head>
<body>
<main class="p-6 transition-all duration-300"
  style="margin-left: <?= isset($_GET['coll']) && $_GET['coll'] == "1" ? '70px' : '260px' ?>;">

  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-bold tracking-tight">Movimientos</h1>
      <p class="text-muted-foreground">Historial de entradas, salidas y devoluciones de materiales</p>
    </div>

    <div class="flex justify-end items-center gap-2 mt-4 mb-4">
      <div class="inline-flex rounded-lg border border-border bg-card shadow-sm overflow-hidden">
        <button type="button" id="btnVistaTabla"
          class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 bg-muted text-foreground">
          <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
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

  <!-- targets (DEMO) -->
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <div class="rounded-xl border border-border bg-card p-8 flex flex-col items-center">
      <div class="flex items-start gap-3">
        <div class="p-3 rounded-2xl bg-gray-100 inline-flex items-center justify-center">
          <i data-lucide="arrow-up-from-line" class="h-6 w-6 text-[#39A900]"></i>
        </div>
        <div class="flex flex-col justify-center">
          <p class="text-2xl font-medium text-muted-foreground">4</p>
          <span class="text-xs text-muted-foreground">Entrada</span>
        </div>
      </div>
    </div>

    <div class="rounded-xl border border-border bg-card p-8 flex flex-col items-center">
      <div class="flex items-start gap-3">
        <div class="p-3 rounded-2xl bg-gray-100 inline-flex items-center justify-center">
          <i data-lucide="arrow-down-to-line" class="h-6 w-6 text-[#39A900]"></i>
        </div>
        <div class="flex flex-col">
          <p class="text-2xl font-medium text-muted-foreground">2</p>
          <span class="text-xs text-muted-foreground">Salida</span>
        </div>
      </div>
    </div>

    <div class="rounded-xl border border-border bg-card p-8 flex flex-col items-center">
      <div class="flex items-start gap-3">
        <div class="p-3 rounded-2xl bg-gray-100 inline-flex items-center justify-center">
          <i data-lucide="rotate-ccw" class="h-6 w-6 text-[#39A900]"></i>
        </div>
        <div class="flex flex-col justify-center">
          <p class="text-2xl font-medium text-muted-foreground">2</p>
          <span class="text-xs text-muted-foreground">Devolver</span>
        </div>
      </div>
    </div>
  </div>

  <!-- filters -->
  <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="relative w-full sm:max-w-xs">
      <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
        <i data-lucide="search" class="h-4 w-4"></i>
      </span>

      <input type="text" name="buscar_ficha" placeholder="Buscar por ficha. . . "
        class="w-full rounded-lg border border-border bg-background py-2 pl-9 pr-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"/>
    </div>

    <div class="flex items-center gap-2">
      <i data-lucide="filter" class="h-4 w-4 text-muted-foreground"></i>
      <div class="relative">
        <select name="filtro_tipo"
          class="appearance-none rounded-lg border border-border bg-background py-2 pl-3 pr-8 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
          <option value="">Todos</option>
          <option value="entrada">Entradas</option>
          <option value="salida">Salidas</option>
          <option value="devolucion">Devoluciones</option>
        </select>

        <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-muted-foreground">
          <i data-lucide="chevron-down" class="h-4 w-4"></i>
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
            <th class="px-4 py-3 text-left font-medium">Estado</th> <!-- ✅ NUEVO -->
            <th class="px-4 py-3 text-right font-medium">Acciones</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-border">
          <?php foreach ($movimientosPage as $m): ?>
            <?php
              [$labelTipo, $claseTipo, $iconTipo] = badgeTipo($m["tipo_movimiento"]);
              $fecha = date("Y-m-d", strtotime($m["fecha_hora"]));
              $hora  = date("H:i", strtotime($m["fecha_hora"]));
              $bodegaNombre = findNameById($bodegas, $m["id_bodega"]);
              $subbodegaNombre = findNameById(array_map(function($s){ return ["id"=>$s["id"], "nombre"=>$s["nombre"]]; }, $subbodegas), $m["id_subbodega"]);
              $programaNombre = $m["id_programa"] ? findNameById($programas, $m["id_programa"]) : "-";
              $fichaNombre = $m["id_ficha"] ? (string)$m["id_ficha"] : "-";
              $raeNombre = $m["id_rae"] ? findNameById($raes, $m["id_rae"]) : "-";
              $insNombre = isset($m["id_instructor"]) && $m["id_instructor"] ? findNameById($instructores, $m["id_instructor"]) : "-";
              $solNombre = $m["id_solicitud"] ? findNameById($solicitudes, $m["id_solicitud"]) : "-";
              $materialsJson = htmlspecialchars(json_encode($m["materiales"] ?? []), ENT_QUOTES, 'UTF-8');
              $estado = strtolower((string)($m["estado"] ?? "activo"));
            ?>
            <tr class="hover:bg-muted/60">
              <td class="px-4 py-3 align-top">
                <div class="flex items-start gap-2">
                  <i data-lucide="calendar" class="h-4 w-4 mt-0.5 text-muted-foreground"></i>
                  <div class="flex flex-col">
                    <span class="text-sm font-medium text-foreground"><?= $fecha ?></span>
                    <span class="text-xs text-muted-foreground"><?= $hora ?></span>
                  </div>
                </div>
              </td>

              <td class="px-4 py-3 align-top">
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium <?= $claseTipo ?>">
                  <i data-lucide="<?= $iconTipo ?>" class="h-3 w-3"></i>
                  <?= $labelTipo ?>
                </span>
              </td>

              <!-- ✅ Materiales: ojo clickeable -->
              <td class="px-4 py-3 align-top">
                <button type="button"
                  class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 hover:bg-muted"
                  onclick="openMaterialesModal(this)"
                  data-materiales="<?= $materialsJson ?>">
                  <i data-lucide="eye" class="h-4 w-4"></i>
                  <span class="text-xs text-muted-foreground">Ver</span>
                </button>
              </td>

              <td class="px-4 py-3 align-top">
                <span class="text-sm font-medium text-foreground"><?= (int)$m["cantidad"] ?></span>
              </td>

              <td class="px-4 py-3 align-top">
                <span class="text-sm"><?= htmlspecialchars($bodegaNombre) ?></span>
              </td>

              <td class="px-4 py-3 align-top">
                <span class="text-sm"><?= htmlspecialchars($subbodegaNombre) ?></span>
              </td>

              <td class="px-4 py-3 align-top">
                <span class="text-sm"><?= htmlspecialchars($programaNombre) ?></span>
              </td>

              <td class="px-4 py-3 align-top">
                <span class="inline-flex items-center rounded-md border border-border px-2 py-1 text-xs font-medium">
                  <?= htmlspecialchars($fichaNombre) ?>
                </span>
              </td>

              <td class="px-4 py-3 align-top">
                <span class="text-sm"><?= htmlspecialchars($raeNombre) ?></span>
              </td>

              <td class="px-4 py-3 align-top">
                <div class="flex items-start gap-2">
                  <i data-lucide="users" class="h-4 w-4 mt-0.5 text-muted-foreground"></i>
                  <span class="text-sm truncate max-w-[220px]"><?= htmlspecialchars($insNombre) ?></span>
                </div>
              </td>

              <td class="px-4 py-3 align-top">
                <span class="text-sm text-muted-foreground"><?= htmlspecialchars($m["observaciones"] ?? "-") ?></span>
              </td>

              <td class="px-4 py-3 align-top">
                <span class="text-sm"><?= htmlspecialchars($solNombre) ?></span>
              </td>

              <!-- ✅ ESTADO -->
              <td class="px-4 py-3 align-top">
                <?php if ($estado === "activo"): ?>
                  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-[#39A90020] text-slate-700">
                    Activo
                  </span>
                <?php else: ?>
                  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-200 text-gray-700">
                    Inactivo
                  </span>
                <?php endif; ?>
              </td>

              <!-- ✅ ACCIONES (menú fuera de tabla) -->
              <td class="px-4 py-3 align-top text-right">
                <button type="button"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-full hover:bg-muted"
                  onclick="openActionsMenu(event, this)"
                  data-id="<?= (int)$m["id_movimiento"] ?>"
                  data-estado="<?= htmlspecialchars($m["estado"] ?? "activo") ?>"
                  data-tipo="<?= htmlspecialchars($m["tipo_movimiento"] ?? "") ?>"
                  data-fecha="<?= htmlspecialchars($m["fecha_hora"] ?? "") ?>"
                  data-bodega="<?= htmlspecialchars($bodegaNombre) ?>"
                  data-subbodega="<?= htmlspecialchars($subbodegaNombre) ?>"
                  data-programa="<?= htmlspecialchars($programaNombre) ?>"
                  data-ficha="<?= htmlspecialchars($fichaNombre) ?>"
                  data-rae="<?= htmlspecialchars($raeNombre) ?>"
                  data-instructor="<?= htmlspecialchars($insNombre) ?>"
                  data-observaciones="<?= htmlspecialchars($m["observaciones"] ?? "-") ?>"
                  data-solicitud="<?= htmlspecialchars($solNombre) ?>"
                  data-materiales="<?= $materialsJson ?>"
                >
                  <i data-lucide="more-horizontal" class="h-4 w-4"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ✅ PAGINACIÓN -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-border bg-card">
      <p class="text-xs text-muted-foreground">
        Mostrando <?= $total ? min($total, $offset + 1) : 0 ?> - <?= min($total, $offset + $perPage) ?> de <?= $total ?> registros
      </p>

      <div class="flex items-center gap-1">
        <a class="px-3 py-2 text-xs rounded-lg border border-border hover:bg-muted <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
           href="?page=movimientos&p=<?= $page-1 ?><?= $collParam ?>">
          Anterior
        </a>

        <?php
          $start = max(1, $page - 2);
          $end   = min($totalPages, $page + 2);
          for ($i=$start; $i<=$end; $i++):
        ?>
          <a class="px-3 py-2 text-xs rounded-lg border border-border hover:bg-muted <?= $i === $page ? 'bg-muted text-foreground' : 'text-muted-foreground' ?>"
             href="?page=movimientos&p=<?= $i ?><?= $collParam ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>

        <a class="px-3 py-2 text-xs rounded-lg border border-border hover:bg-muted <?= $page >= $totalPages ? 'pointer-events-none opacity-50' : '' ?>"
           href="?page=movimientos&p=<?= $page+1 ?><?= $collParam ?>">
          Siguiente
        </a>
      </div>
    </div>
  </div>

  <!-- GRID VIEW (DEMO, igual que antes) -->
  <div id="gridView" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
    <div class="bg-card border border-border rounded-lg p-6 hover:shadow-md transition-all hover:-translate-y-1 relative">
      <div class="absolute top-3 right-3">
        <button type="button" onclick="openActionsMenu(event, this)"
          class="inline-flex h-8 w-8 items-center justify-center rounded-full hover:bg-muted"
          data-id="demo"
          data-estado="activo"
          data-tipo="salida"
          data-fecha="2024-11-20 08:30:00"
          data-bodega="Bodega Construcción"
          data-subbodega="Subbodega Herramientas"
          data-programa="ADSO"
          data-ficha="2895664"
          data-rae="RAE 1"
          data-instructor="Juan Pablo Hernandez"
          data-observaciones="Uso en taller"
          data-solicitud="Solicitud #101"
          data-materiales='[{"id_material":1,"nombre":"Cemento Gris","cantidad":5,"unidad":"Bulto"}]'
        >
          <i data-lucide="more-horizontal" class="h-4 w-4"></i>
        </button>
      </div>

      <div class="flex items-start gap-3 mb-4 pr-10">
        <div class="w-12 h-12 bg-muted rounded-full flex items-center justify-center">
          <i data-lucide="package" class="h-5 w-5 text-[#39A900]"></i>
        </div>

        <div>
          <h3 class="font-semibold text-foreground">Movimiento</h3>
          <p class="text-sm text-muted-foreground">Ver detalles en tabla</p>
        </div>
      </div>

      <hr class="my-4 border-border">

      <div class="flex items-center justify-between">
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-[#39A90020] text-slate-700">Activo</span>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium bg-lime-100 text-lime-700">
          <i data-lucide="arrow-down-up" class="h-3 w-3"></i> Salida
        </span>
      </div>
    </div>
  </div>

  <!-- ✅ MODAL VER MATERIALES (OJO) -->
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

  <!-- MODAL REGISTRAR MOVIMIENTO -->
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

      <!-- Tabs (✅ entrada / salida / devolucion) -->
      <div class="mb-6 flex justify-center">
        <div id="tabsMovimiento"
          class="flex w-full max-w-md items-center rounded-full bg-gray-100 p-1 text-sm font-medium shadow-inner">

          <button type="button" data-tipo="entrada"
            class="tab-mov flex-1 rounded-full py-2 text-center text-gray-600 hover:text-gray-900 transition-all">
            Entrada
          </button>

          <button type="button" data-tipo="salida"
            class="tab-mov flex-1 rounded-full py-2 text-center text-gray-600 hover:text-gray-900 transition-all">
            Salida
          </button>

          <button type="button" data-tipo="devolucion"
            class="tab-mov flex-1 rounded-full py-2 text-center text-gray-600 hover:text-gray-900 transition-all">
            Devolución
          </button>

        </div>
      </div>

      <!-- FORM -->
      <form id="formMovimiento" class="space-y-4" novalidate>

        <div class="grid gap-4 sm:grid-cols-2">

          <!-- Material (id_material) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Material *</label>
            <select id="material" name="id_material" required
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
              <option value="">Seleccione el material</option>
              <?php foreach ($materiales as $mat): ?>
                <option value="<?= $mat["id"] ?>"><?= htmlspecialchars($mat["nombre"]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Cantidad -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad *</label>
            <input id="cantidad" name="cantidad" type="number" min="1" step="1" value="1" required
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"/>
          </div>

          <!-- Bodega -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bodega *</label>
            <select id="bodega" name="id_bodega" required
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
              <option value="">Seleccione la bodega</option>
              <?php foreach ($bodegas as $b): ?>
                <option value="<?= $b["id"] ?>"><?= htmlspecialchars($b["nombre"]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- ✅ Subbodega -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Subbodega *</label>
            <select id="subbodega" name="id_subbodega" required
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
              <option value="">Seleccione la subbodega</option>
              <?php foreach ($subbodegas as $sb): ?>
                <option value="<?= $sb["id"] ?>" data-bodega="<?= $sb["id_bodega"] ?>">
                  <?= htmlspecialchars($sb["nombre"]) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">* Se filtra automáticamente según la bodega</p>
          </div>

          <!-- Entrega (solo entrada) -->
          <div data-field="entrega">
            <label class="block text-sm font-medium text-gray-700 mb-1">Entrega *</label>
            <select id="entrega" name="entrega"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
              <option value="">Seleccione</option>
              <option value="entrada_material">Entrada del material</option>
              <option value="entrega_material">Entrega de material</option>
            </select>
          </div>

          <!-- Programa (solo salida) -->
          <div data-field="programa" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-1">Programa de formación *</label>
            <select id="programa" name="id_programa"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
              <option value="">Seleccione el programa</option>
              <?php foreach ($programas as $p): ?>
                <option value="<?= $p["id"] ?>"><?= htmlspecialchars($p["nombre"]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Ficha (salida y devolucion) -->
          <div data-field="ficha" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-1">Ficha *</label>
            <select id="ficha" name="id_ficha"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
              <option value="">Seleccione la ficha</option>
              <?php foreach ($fichas as $f): ?>
                <option value="<?= $f["id"] ?>"><?= htmlspecialchars($f["nombre"]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- RAE (salida y devolucion) -->
          <div data-field="rae" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-1">RAE *</label>
            <select id="rae" name="id_rae"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
              <option value="">Seleccione el RAE</option>
              <?php foreach ($raes as $r): ?>
                <option value="<?= $r["id"] ?>"><?= htmlspecialchars($r["nombre"]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Instructor (salida y devolucion) -->
          <div data-field="instructor" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-1">Instructor *</label>
            <select id="instructor" name="id_usuario"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
              <option value="">Seleccione el instructor</option>
              <?php foreach ($instructores as $ins): ?>
                <option value="<?= $ins["id"] ?>"><?= htmlspecialchars($ins["nombre"]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Solicitud (opcional, si la usas) -->
          <div data-field="solicitud" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-1">Solicitud (opcional)</label>
            <select id="solicitud" name="id_solicitud"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
              <option value="">Seleccione</option>
              <?php foreach ($solicitudes as $s): ?>
                <option value="<?= $s["id"] ?>"><?= htmlspecialchars($s["nombre"]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Estado del material (no está en tu tabla, pero lo dejaste en UI) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Estado del material *</label>
            <select id="estado_material" name="estado_material" required
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
              <option value="">Seleccione</option>
              <option value="bueno">Bueno</option>
              <option value="regular">Regular</option>
              <option value="malo">Malo</option>
            </select>
          </div>
        </div>

        <!-- Observaciones -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
          <textarea id="observaciones" name="observaciones" rows="3"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"
            placeholder="Observaciones del movimiento"></textarea>
        </div>

        <!-- Hidden tipo movimiento -->
        <input type="hidden" name="tipo_movimiento" id="tipoMovimiento" value="entrada">

        <div class="mt-4 flex items-center justify-end gap-2">
          <button type="button" onclick="closeMovimientoModal()"
            class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 border border-border">
            Cancelar
          </button>
          <button type="submit" id="btnRegistrarMovimiento"
            class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-secondary">
            Registrar entrada
          </button>
        </div>
      </form>
    </div>
  </div>

</main>

<!-- ✅ MENU GLOBAL (FUERA DE TABLA) -->
<div id="actionsMenu"
  class="hidden fixed z-[9999] w-44 rounded-xl border border-gray-200 bg-white shadow-lg p-2">
  <button type="button" onclick="actionVerDetalle()"
    class="flex items-center gap-2 w-full text-left px-2 py-2 rounded-lg hover:bg-gray-100">
    <i data-lucide="eye" class="h-4 w-4"></i><span>Ver detalle</span>
  </button>

  <button type="button" onclick="actionEditar()"
    class="flex items-center gap-2 w-full text-left px-2 py-2 rounded-lg hover:bg-gray-100">
    <i data-lucide="edit" class="h-4 w-4"></i><span>Editar</span>
  </button>

  <button type="button" onclick="actionDeshabilitar()"
    class="flex items-center gap-2 w-full text-left px-2 py-2 rounded-lg hover:bg-gray-100">
    <i data-lucide="power" class="h-4 w-4"></i><span>Deshabilitar</span>
  </button>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  // LUCIDE
  if (window.lucide && typeof lucide.createIcons === "function") {
    lucide.createIcons();
  }

  // CAMBIO VISTA
  const btnVistaTabla    = document.getElementById("btnVistaTabla");
  const btnVistaTarjetas = document.getElementById("btnVistaTarjetas");
  const tableView        = document.getElementById("tableView");
  const gridView         = document.getElementById("gridView");

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
     ✅ FILTRO SUBBODEGA POR BODEGA
  =============================== */
  const bodegaSel = document.getElementById("bodega");
  const subbodegaSel = document.getElementById("subbodega");
  const filterSubbodegas = () => {
    if (!bodegaSel || !subbodegaSel) return;
    const idB = bodegaSel.value;
    [...subbodegaSel.options].forEach(opt => {
      if (!opt.value) return; // placeholder
      const belongs = opt.getAttribute("data-bodega");
      opt.hidden = (idB && belongs !== idB);
    });
    const selOpt = subbodegaSel.selectedOptions[0];
    if (selOpt && selOpt.value && selOpt.hidden) subbodegaSel.value = "";
  };
  if (bodegaSel) bodegaSel.addEventListener("change", filterSubbodegas);
  filterSubbodegas();

  /* ===============================
     TABS MOVIMIENTO + SHOW/HIDE
  =============================== */
  const labelsPorTipo = {
    entrada: 'Registrar entrada',
    salida: 'Registrar salida',
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

      const tipo = btn.dataset.tipo;
      if (hiddenTipo) hiddenTipo.value = tipo;
      if (btnSubmit) btnSubmit.textContent = labelsPorTipo[tipo] || "Registrar";

      const filaEntrega    = document.querySelector('[data-field="entrega"]');
      const filaPrograma   = document.querySelector('[data-field="programa"]');
      const filaFicha      = document.querySelector('[data-field="ficha"]');
      const filaRae        = document.querySelector('[data-field="rae"]');
      const filaInstructor = document.querySelector('[data-field="instructor"]');
      const filaSolicitud  = document.querySelector('[data-field="solicitud"]');

      if (filaEntrega) filaEntrega.classList.toggle("hidden", tipo !== "entrada");
      if (filaPrograma) filaPrograma.classList.toggle("hidden", tipo !== "salida");
      if (filaFicha) filaFicha.classList.toggle("hidden", !(tipo === "salida" || tipo === "devolucion"));
      if (filaRae) filaRae.classList.toggle("hidden", !(tipo === "salida" || tipo === "devolucion"));
      if (filaInstructor) filaInstructor.classList.toggle("hidden", !(tipo === "salida" || tipo === "devolucion"));
      if (filaSolicitud) filaSolicitud.classList.toggle("hidden", !(tipo === "salida" || tipo === "devolucion"));

      if (tipo !== "entrada") document.getElementById("entrega") && (document.getElementById("entrega").value = "");
      if (tipo !== "salida") document.getElementById("programa") && (document.getElementById("programa").value = "");
      if (!(tipo === "salida" || tipo === "devolucion")) {
        ["ficha","rae","instructor","solicitud"].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.value = "";
        });
      }
    };

    if (entradaBtn) setActive(entradaBtn);
    tabs.forEach(btn => btn.onclick = () => setActive(btn));
  }
  window.initTabsMovimiento = initTabsMovimiento;

  /* ===============================
     ✅ VALIDACIONES
  =============================== */
  const form = document.getElementById("formMovimiento");
  if (form) {
    form.addEventListener("submit", function (e) {
      const tipo = document.getElementById("tipoMovimiento")?.value || "entrada";

      const material = document.getElementById("material")?.value || "";
      const bodega = document.getElementById("bodega")?.value || "";
      const subbodega = document.getElementById("subbodega")?.value || "";
      const estado = document.getElementById("estado_material")?.value || "";

      const cantidadEl = document.getElementById("cantidad");
      const cantidad = cantidadEl ? parseInt(cantidadEl.value, 10) : NaN;

      const entrega = document.getElementById("entrega")?.value || "";
      const programa = document.getElementById("programa")?.value || "";
      const ficha = document.getElementById("ficha")?.value || "";
      const rae = document.getElementById("rae")?.value || "";
      const instructor = document.getElementById("instructor")?.value || "";

      if (!Number.isInteger(cantidad) || cantidad < 1) {
        e.preventDefault();
        alert("La cantidad debe ser un número mayor o igual a 1. (No se permiten negativos)");
        cantidadEl?.focus();
        return;
      }

      if (!material) { e.preventDefault(); alert("Seleccione el material."); document.getElementById("material")?.focus(); return; }
      if (!bodega) { e.preventDefault(); alert("Seleccione la bodega."); document.getElementById("bodega")?.focus(); return; }
      if (!subbodega) { e.preventDefault(); alert("Seleccione la subbodega."); document.getElementById("subbodega")?.focus(); return; }
      if (!estado) { e.preventDefault(); alert("Seleccione el estado del material."); document.getElementById("estado_material")?.focus(); return; }

      if (tipo === "entrada" && !entrega) {
        e.preventDefault();
        alert("En Entrada debes seleccionar el tipo de Entrega.");
        document.getElementById("entrega")?.focus();
        return;
      }

      if (tipo === "salida") {
        if (!programa) { e.preventDefault(); alert("Seleccione el programa de formación."); document.getElementById("programa")?.focus(); return; }
        if (!ficha) { e.preventDefault(); alert("Seleccione la ficha."); document.getElementById("ficha")?.focus(); return; }
        if (!rae) { e.preventDefault(); alert("Seleccione el RAE."); document.getElementById("rae")?.focus(); return; }
        if (!instructor) { e.preventDefault(); alert("Seleccione el instructor."); document.getElementById("instructor")?.focus(); return; }
      }

      if (tipo === "devolucion") {
        if (!ficha) { e.preventDefault(); alert("Seleccione la ficha."); document.getElementById("ficha")?.focus(); return; }
        if (!rae) { e.preventDefault(); alert("Seleccione el RAE."); document.getElementById("rae")?.focus(); return; }
        if (!instructor) { e.preventDefault(); alert("Seleccione el instructor."); document.getElementById("instructor")?.focus(); return; }
      }
    });

    const cantidadEl = document.getElementById("cantidad");
    if (cantidadEl) {
      cantidadEl.addEventListener("keydown", function (ev) {
        if (ev.key === "-" || ev.key === "e" || ev.key === "E" || ev.key === "+") {
          ev.preventDefault();
        }
      });
    }
  }
});

/* ===============================
   MODAL MOVIMIENTO
=============================== */
function openMovimientoModal() {
  const modal = document.getElementById('movimientoModal');
  if (!modal) return;

  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.body.classList.add('overflow-hidden');

  if (window.initTabsMovimiento) window.initTabsMovimiento();
  if (window.lucide && typeof lucide.createIcons === "function") lucide.createIcons();
}
function closeMovimientoModal() {
  const modal = document.getElementById('movimientoModal');
  if (!modal) return;

  modal.classList.add('hidden');
  modal.classList.remove('flex');
  document.body.classList.remove('overflow-hidden');
}

/* ===============================
   ✅ MODAL MATERIALES (OJO)
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
   ESC
=============================== */
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    closeMovimientoModal();
    closeMaterialesModal();
    closeActionsMenu();
  }
});

/* ===============================
   Utils
=============================== */
function escapeHtml(str) {
  return (str ?? "").replace(/[&<>"']/g, function (m) {
    return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" })[m];
  });
}

/* ===============================
   ✅ MENÚ GLOBAL (ACCIONES FUERA DE TABLA)
=============================== */
let currentActionData = null;

function openActionsMenu(ev, btn) {
  ev.preventDefault();
  ev.stopPropagation();

  const menu = document.getElementById("actionsMenu");
  if (!menu) return;

  currentActionData = {
    id: btn.dataset.id,
    estado: btn.dataset.estado,
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

  const r = btn.getBoundingClientRect();
  const menuW = 176;
  const menuH = 140;

  let left = r.right - menuW;
  let top = r.bottom + 8;

  left = Math.max(8, Math.min(left, window.innerWidth - menuW - 8));
  top  = Math.max(8, Math.min(top, window.innerHeight - menuH - 8));

  menu.style.left = left + "px";
  menu.style.top  = top + "px";

  menu.classList.remove("hidden");

  if (window.lucide && typeof lucide.createIcons === "function") lucide.createIcons();
}

function closeActionsMenu() {
  const menu = document.getElementById("actionsMenu");
  if (!menu) return;
  menu.classList.add("hidden");
}

document.addEventListener("click", function () {
  closeActionsMenu();
});

function actionVerDetalle() {
  closeActionsMenu();
  if (!currentActionData) return;

  alert(
    "Detalle Movimiento #" + currentActionData.id +
    "\nTipo: " + currentActionData.tipo +
    "\nFecha: " + currentActionData.fecha +
    "\nBodega: " + currentActionData.bodega +
    "\nSubbodega: " + currentActionData.subbodega +
    "\nPrograma: " + currentActionData.programa +
    "\nFicha: " + currentActionData.ficha +
    "\nRAE: " + currentActionData.rae +
    "\nInstructor: " + currentActionData.instructor +
    "\nSolicitud: " + currentActionData.solicitud +
    "\nEstado: " + currentActionData.estado +
    "\nObs: " + currentActionData.observaciones
  );
}

function actionEditar() {
  closeActionsMenu();
  if (!currentActionData) return;
  openMovimientoModal();
}

function actionDeshabilitar() {
  closeActionsMenu();
  if (!currentActionData) return;

  const ok = confirm("¿Seguro que deseas deshabilitar el movimiento #" + currentActionData.id + "?");
  if (!ok) return;

  // ✅ aquí haces tu fetch/POST a backend para cambiar estado en BD
  alert("Movimiento #" + currentActionData.id + " deshabilitado (demo).");
}
</script>

</body>
</html>
