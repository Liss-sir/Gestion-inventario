<?php
$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";

include_once BASE_PATH . '/Config/database.php';
include_once BASE_PATH . '/src/models/bodega.php';

$model = new BodegaModel($conn);
$bodegas = $model->listar();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Gestión Bodegas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- CSS globals -->
  <link rel="stylesheet" href="src/assets/css/globals.css" />

  <!-- CSS bodegas -->
  <link rel="stylesheet" href="src/assets/css/bodegas/bodegas.css" />

  <!-- ✅ JS bodegas (tu ruta original) -->
  <script src="src/assets/js/bodega/bodega.js" defer></script>
</head>

<body class="bg-background p-6 text-gray-900">
<main
  class="p-6 transition-all duration-300"
  style="margin-left: <?= $sidebarWidth ?>;"
>
  <div class="space-y-6 animate-fade-in-up">

    <!-- ================================== -->
    <!-- PAGE HEADER -->
    <!-- ================================== -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight">Gestión Bodegas</h1>
        <p class="text-muted-foreground">
          Administra las bodegas y sub-bodegas del inventario
        </p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Switch lista / grid -->
        <div class="inline-flex rounded-lg border border-border bg-card shadow-sm overflow-hidden">
          <button
            type="button"
            id="btnVistaTabla"
            class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 bg-muted text-foreground"
          >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </button>

          <button
            type="button"
            id="btnVistaTarjetas"
            class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 text-muted-foreground"
          >
            <svg class="h-4 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <rect x="4" y="4" width="7" height="7" rx="1"></rect>
              <rect x="13" y="4" width="7" height="7" rx="1"></rect>
              <rect x="4" y="13" width="7" height="7" rx="1"></rect>
              <rect x="13" y="13" width="7" height="7" rx="1"></rect>
            </svg>
          </button>
        </div>

        <!-- BOTÓN ÚNICO + MENÚ DESPLEGABLE -->
        <div class="relative">
          <button
            id="btnCrearBodegaMenu"
            class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2"
            type="button"
          >
            <i data-lucide="plus" class="w-4 h-4"></i>
            Crear bodega
            <i data-lucide="chevron-down" class="w-4 h-4 opacity-90"></i>
          </button>

          <div
            id="menuCrearBodega"
            class="hidden absolute right-0 mt-2 w-64 rounded-xl border border-border bg-card shadow-lg overflow-hidden z-40"
          >
            <button
              id="btnNuevaBodega"
              type="button"
              class="w-full flex items-center gap-3 px-4 py-3 text-sm text-foreground hover:bg-muted transition"
            >
              <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-background">
                <i data-lucide="warehouse" class="w-4 h-4 text-muted-foreground"></i>
              </span>
              <div class="flex flex-col items-start text-left">
                <span class="font-medium">Crear Bodega</span>
                <span class="text-xs text-muted-foreground">Registra una nueva bodega</span>
              </div>
            </button>

            <div class="border-t border-border"></div>

            <button
              id="btnNuevaSubBodega"
              type="button"
              class="w-full flex items-center gap-3 px-4 py-3 text-sm text-foreground hover:bg-muted transition"
            >
              <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-background">
                <i data-lucide="layers" class="w-4 h-4 text-muted-foreground"></i>
              </span>

              <div class="flex flex-col items-start text-left w-full">
                <span class="font-medium">Crear Sub-bodega</span>
                <span class="text-xs text-muted-foreground text-left w-full">
                  Asigna una sub-bodega a una bodega padre
                </span>
              </div>
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- ================================== -->
    <!-- TOP FILTERS (SEARCH + ESTADO FILTER) -->
    <!-- ================================== -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between my-6">

      <!-- SEARCH -->
      <div class="relative w-full sm:max-w-xs">
        <svg
          class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <circle cx="11" cy="11" r="8"></circle>
          <path d="m21 21-4.35-4.35"></path>
        </svg>

        <input
          id="inputBuscarBodega"
          type="text"
          placeholder="Buscar por nombre o ID..."
          class="w-full rounded-md border border-input bg-background pl-9 pr-3 py-2 text-sm"
        />
      </div>

      <!-- ESTADO FILTER -->
      <div class="flex items-center gap-2">
        <svg
          class="h-4 w-4"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="none"
        >
          <path
            d="M5 5h14a1 1 0 0 1 .8 1.6L15 12v4.5a1 1 0 0 1-.553.894l-3 1.5A1 1 0 0 1 10 18v-6L4.2 6.6A1 1 0 0 1 5 5z"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>

        <select
          id="bodegasFilter"
          class="rounded-md border border-input bg-background px-3 pr-10 py-2 text-sm"
        >
          <option value="Todos">Todos</option>
          <option value="Activo">Activos</option>
          <option value="Inactivo">Inactivos</option>
        </select>
      </div>

    </div>

    <!-- ================================== -->
    <!-- TABLE VIEW CONTAINER -->
    <!-- ================================== -->
    <div
      id="vistaTabla"
      class="overflow-hidden rounded-xl border border-border bg-card relative"
    >
      <!-- EMPTY STATE -->
      <div
        id="emptyTabla"
        class="<?= empty($bodegas) ? '' : 'hidden' ?>
              w-full flex flex-col items-center justify-center text-center
              px-6 py-10 sm:py-12
              min-h-[220px] sm:min-h-[240px]"
      >
        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-border">
          <!-- ✅ WRAPPERS (IDs no se pierden con lucide.createIcons) -->
          <span id="emptyIconNoDataListWrap" class="<?= empty($bodegas) ? '' : 'hidden' ?>">
            <i data-lucide="file-text" class="h-6 w-6 text-foreground"></i>
          </span>
          <span id="emptyIconNoResultsListWrap" class="hidden">
            <i data-lucide="search" class="h-6 w-6 text-foreground"></i>
          </span>
        </div>

        <h3 id="emptyListTitle" class="text-lg sm:text-xl font-semibold text-foreground">
          No hay bodegas registradas
        </h3>

        <p id="emptyListDesc" class="mx-auto mt-1.5 max-w-xl text-sm text-muted-foreground">
          Una vez agregues bodegas desde el botón <strong>"Crear bodega"</strong>, aparecerán listadas en esta vista.
        </p>
      </div>

      <!-- TABLA -->
      <div id="tableWrapperList" class="<?= empty($bodegas) ? 'hidden' : '' ?>">
        <table class="min-w-full divide-y divide-border text-sm">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground">ID</th>
              <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground">Nombre</th>
              <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground">Clasificación</th>
              <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground">Ubicación</th>
              <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground">Estado</th>
              <th class="px-4 py-3 text-right font-medium text-xs text-muted-foreground">Acciones</th>
            </tr>
          </thead>

          <tbody id="tbodyBodegas" class="divide-y divide-border bg-card">
            <?php foreach ($bodegas as $b): ?>
              <?php $activo = $b['estado'] === "Activo"; ?>
              <tr class="hover:bg-muted/30 bodegas-row">
                <td class="px-4 py-3 bodegas-codigo">#<?= $b['codigo_bodega'] ?></td>

                <td class="px-4 py-3 bodegas-nombre">
                  <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                      <i data-lucide="warehouse" class="w-4 h-4"></i>
                    </div>
                    <span class="font-medium text-foreground"><?= htmlspecialchars($b['nombre']) ?></span>
                  </div>
                </td>

                <td class="px-4 py-3">
                  <span class="inline-flex items-center rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-foreground">
                    <?= htmlspecialchars($b['clasificacion_bodega']) ?>
                  </span>
                </td>

                <td class="px-4 py-3 text-muted-foreground">
                  <i data-lucide="map-pin" class="w-4 h-4 inline-block text-muted-foreground mr-1"></i>
                  <?= htmlspecialchars($b['ubicacion']) ?>
                </td>

                <td class="px-4 py-3 bodegas-estado">
                  <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
                    <?= $activo ? 'badge-estado-activo' : 'badge-estado-inactivo' ?>">
                    <?= htmlspecialchars($b['estado']) ?>
                  </span>
                </td>

                <td class="px-4 py-3 text-right">
                  <button
                    type="button"
                    class="w-8 h-8 rounded-full inline-flex items-center justify-center bodegas-btn-dots hover:bg-muted"
                    data-id="<?= $b['id_bodega'] ?>"
                    data-codigo="<?= htmlspecialchars($b['codigo_bodega']) ?>"
                    data-nombre="<?= htmlspecialchars($b['nombre']) ?>"
                    data-clasificacion="<?= htmlspecialchars($b['clasificacion_bodega']) ?>"
                    data-ubicacion="<?= htmlspecialchars($b['ubicacion']) ?>"
                    data-estado="<?= htmlspecialchars($b['estado']) ?>"
                  >
                    <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>

        </table>
      </div>
    </div>

    <!-- ================================== -->
    <!-- CARDS VIEW CONTAINER -->
    <!-- ================================== -->
    <div id="vistaTarjetas" class="hidden">
      <div id="gridBodegas" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">

        <!-- EMPTY GRID -->
        <div
          id="emptyGrid"
          class="<?= empty($bodegas) ? '' : 'hidden' ?>
                col-span-full flex flex-col items-center justify-center text-center
                px-6 py-10 sm:py-12
                min-h-[220px] sm:min-h-[240px]
                overflow-hidden rounded-xl border border-border bg-card"
        >
          <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-border bg-background">
            <!-- ✅ WRAPPERS (IDs no se pierden con lucide.createIcons) -->
            <span id="emptyIconNoDataGridWrap" class="<?= empty($bodegas) ? '' : 'hidden' ?>">
              <i data-lucide="file-text" class="h-6 w-6 text-foreground"></i>
            </span>
            <span id="emptyIconNoResultsGridWrap" class="hidden">
              <i data-lucide="search" class="h-6 w-6 text-foreground"></i>
            </span>
          </div>

          <h3 id="emptyGridTitle" class="text-lg sm:text-xl font-semibold text-foreground">
            No hay bodegas registradas
          </h3>

          <p id="emptyGridDesc" class="mx-auto mt-1.5 max-w-xl text-sm text-muted-foreground">
            Una vez agregues bodegas desde el botón <strong>"Crear bodega"</strong>, aparecerán listadas en esta vista.
          </p>
        </div>

        <?php foreach ($bodegas as $b): $estadoActivo = $b['estado'] === "Activo"; ?>
          <div class="bg-card border border-border rounded-xl shadow-sm p-5 flex flex-col gap-3 relative bodegas-card">
            <div class="flex items-start justify-between gap-4">
              <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                  <i data-lucide="warehouse" class="w-4 h-4"></i>
                </div>

                <div>
                  <h2 class="text-base font-semibold text-foreground bodegas-card-nombre"><?= htmlspecialchars($b['nombre']) ?></h2>
                  <p class="text-xs text-muted-foreground bodegas-card-id">ID: <?= $b['codigo_bodega'] ?></p>
                </div>
              </div>

              <button
                type="button"
                class="w-8 h-8 rounded-full inline-flex items-center justify-center bodegas-btn-dots hover:bg-muted"
                data-id="<?= $b['id_bodega'] ?>"
                data-codigo="<?= htmlspecialchars($b['codigo_bodega']) ?>"
                data-nombre="<?= htmlspecialchars($b['nombre']) ?>"
                data-clasificacion="<?= htmlspecialchars($b['clasificacion_bodega']) ?>"
                data-ubicacion="<?= htmlspecialchars($b['ubicacion']) ?>"
                data-estado="<?= htmlspecialchars($b['estado']) ?>"
              >
                <i data-lucide="more-horizontal" class="w-4 h-4"></i>
              </button>
            </div>

            <div class="flex items-center gap-2 text-sm text-muted-foreground">
              <i data-lucide="map-pin" class="w-4 h-4 text-muted-foreground"></i>
              <span class="bodegas-card-ubicacion"><?= htmlspecialchars($b['ubicacion']) ?></span>
            </div>

            <div class="flex flex-wrap gap-2">
              <span class="inline-flex items-center rounded-full px-3 py-1 text-xs bg-blue-100 text-blue-700">
                <?= htmlspecialchars($b['clasificacion_bodega']) ?>
              </span>
            </div>

            <hr class="border-border">

            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <i data-lucide="package" class="w-4 h-4 text-muted-foreground"></i>
                <span><?= (int)($b['materiales'] ?? 0) ?> Materiales</span>
              </div>

              <div class="flex items-center gap-3">
                <span class="estado-text text-sm font-medium <?= $estadoActivo ? 'text-emerald-700' : 'text-red-700' ?>"></span>

                <label class="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    class="sr-only peer estado-switch"
                    data-id="<?= $b['id_bodega'] ?>"
                    data-codigo="<?= htmlspecialchars($b['codigo_bodega']) ?>"
                    data-estado="<?= $estadoActivo ? 'Activo' : 'Inactivo' ?>"
                    <?= $estadoActivo ? "checked" : "" ?>
                  >
                  <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:bg-emerald-600 transition">
                    <div class="w-4 h-4 bg-white rounded-full absolute top-0.5 left-0.5 peer-checked:translate-x-5 transition"></div>
                  </div>
                </label>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

      </div>
    </div>

  </div>

  <!-- ========= MENÚ CONTEXTUAL ========= -->
  <div
    id="context-menu"
    class="hidden absolute z-50 w-56 rounded-lg bg-card border border-border shadow-md"
  >
    <ul class="py-2 text-sm text-foreground">
      <li>
        <button type="button" class="w-full flex items-center gap-3 px-4 py-2 hover:bg-muted bodegas-ctx-btn" data-action="ver">
          <i data-lucide="eye" class="w-5 h-5 text-muted-foreground"></i>
          <span>Ver detalles</span>
        </button>
      </li>

      <li>
        <button type="button" class="w-full flex items-center gap-3 px-4 py-2 hover:bg-muted bodegas-ctx-btn" data-action="editar">
          <i data-lucide="square-pen" class="w-5 h-5 text-muted-foreground"></i>
          <span>Editar</span>
        </button>
      </li>

      <li class="my-2 border-t border-border"></li>

      <li>
        <button type="button" class="w-full flex items-center gap-3 px-4 py-2 hover:bg-muted bodegas-ctx-btn" data-action="deshabilitar">
          <i data-lucide="power" class="w-5 h-5 text-muted-foreground"></i>
          <span>Desactivar</span>
        </button>
      </li>
    </ul>
  </div>

  <!-- ========= MENÚ CONTEXTUAL SUB-BODEGA ========= -->
<div
  id="context-menu-subbodega"
  class="hidden fixed z-[9999] w-56 rounded-lg bg-card border border-border shadow-md"
>
  <ul class="py-2 text-sm text-foreground">
    <li>
      <button type="button" class="w-full flex items-center gap-3 px-4 py-2 hover:bg-muted ctx-sub-btn" data-action="ver">
        <i data-lucide="eye" class="w-5 h-5 text-muted-foreground"></i>
        <span>Ver detalles</span>
      </button>
    </li>

    <li>
      <button type="button" class="w-full flex items-center gap-3 px-4 py-2 hover:bg-muted ctx-sub-btn" data-action="editar">
        <i data-lucide="square-pen" class="w-5 h-5 text-muted-foreground"></i>
        <span>Editar</span>
      </button>
    </li>

    <li class="my-2 border-t border-border"></li>

    <li>
      <button type="button" class="w-full flex items-center gap-3 px-4 py-2 hover:bg-muted ctx-sub-btn" data-action="toggle">
        <i data-lucide="power" class="w-5 h-5 text-muted-foreground"></i>
        <span>Desactivar</span>
      </button>
    </li>
  </ul>
</div>


  <!-- ========= MODAL CREAR ========= -->
  <div id="modalCrear" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="absolute inset-0" id="backdropCrear"></div>

    <div class="relative mx-4 w-full max-w-2xl rounded-2xl bg-white shadow-xl p-6 sm:p-8">
      <div class="flex items-start justify-between mb-4">
        <div>
          <h2 class="text-xl font-semibold text-gray-900">Crear Nueva Bodega</h2>
          <p class="text-sm text-gray-500">Complete los datos para registrar una nueva bodega</p>
        </div>

        <button type="button" id="cerrarModal" class="inline-flex h-8 w-8 items-center justify-center rounded-full hover:bg-gray-100">
          <i data-lucide="x" class="h-4 h-4 w-4"></i>
        </button>
      </div>

      <form id="formCrearBodega" class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ID *</label>
            <input id="crearCodigo" type="text"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"
                   placeholder="C-1">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Clasificación *</label>
            <select id="crearClasificacion"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
              <option value="Insumos">Insumos</option>
              <option value="Equipos">Equipos</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación *</label>
            <input id="crearUbicacion" type="text"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"
                   placeholder="Ej: Bloque A, Piso 1">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Bodega *</label>
          <input id="crearNombre" type="text"
                 class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"
                 placeholder="Ej: Bodega Principal - Eléctrico">
        </div>

        <div class="mt-4 flex items-center justify-end gap-2">
          <button type="button" id="cancelarModal"
                  class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 border border-gray-200">
            Cancelar
          </button>

          <button type="submit"
                  class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-secondary hover:opacity-90">
            Crear Bodega
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ========= MODAL CREAR SUB-BODEGA ========= -->
  <div id="modalCrearSubBodega" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="absolute inset-0" id="backdropCrearSub"></div>

    <div class="relative mx-4 w-full max-w-2xl rounded-2xl bg-white shadow-xl p-6 sm:p-8">
      <div class="flex items-start justify-between mb-4">
        <div>
          <h2 class="text-xl font-semibold text-gray-900">Crear Nueva Sub-bodega</h2>
          <p class="text-sm text-gray-500">Complete los datos para registrar una sub-bodega</p>
        </div>

        <button type="button" id="cerrarModalSub"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full hover:bg-gray-100">
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>

      <form id="formCrearSubBodega" class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bodega padre *</label>
              <select id="id_bodega"
                      class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm
                            focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"
                      required>
                <option value="">Seleccione una bodega</option>

                <?php foreach ($bodegas as $b): ?>
                  <option value="<?= (int)$b['id_bodega'] ?>">
                    <?= htmlspecialchars($b['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Código *</label>
            <input id="subCodigo" type="text"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"
                   placeholder="SB-01" required>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Clasificación *</label>
            <select id="subClasificacion"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"
                    required>
              <option value="Insumos">Insumos</option>
              <option value="Equipos">Equipos</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la Sub-bodega *</label>
          <input id="subNombre" type="text"
                 class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"
                 placeholder="Sub-bodega Eléctrica" required>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
          <textarea id="subDescripcion" rows="3"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"
                    placeholder="Opcional"></textarea>
        </div>

        <div class="mt-4 flex items-center justify-end gap-2">
          <button type="button" id="cancelarModalSub"
                  class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 border border-gray-200">
            Cancelar
          </button>

          <button type="submit"
                  class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-secondary hover:opacity-90">
            Crear Sub-bodega
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ✅ ========= MODAL EDITAR ========= -->
  <div id="modalEditar" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="absolute inset-0" id="backdropEditar"></div>

    <div class="relative mx-4 w-full max-w-2xl rounded-2xl bg-white shadow-xl p-6 sm:p-8">
      <div class="flex items-start justify-between mb-4">
        <div>
          <h2 class="text-xl font-semibold text-gray-900">Editar Bodega</h2>
          <p class="text-sm text-gray-500">Modifica la información de la bodega</p>
        </div>

        <button type="button" id="cerrarEditar"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full hover:bg-gray-100">
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>

      <form id="formEditarBodega" class="space-y-4">
        <input type="hidden" id="editIdBodega">

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ID *</label>
            <input id="editCodigoBodega" type="text"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Clasificación *</label>
            <select id="editClasificacion"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
              <option value="Insumos">Insumos</option>
              <option value="Equipos">Equipos</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación *</label>
            <input id="editUbicacion" type="text"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                          focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Bodega *</label>
          <input id="editNombre" type="text"
                 class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                        focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
        </div>

        <div class="mt-4 flex items-center justify-end gap-2">
          <button type="button" id="cancelarEditar"
                  class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 border border-gray-200">
            Cancelar
          </button>

          <button type="button" id="guardarEditar"
                  class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-secondary hover:opacity-90">
            Guardar cambios
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ✅ ========= MODAL DETALLE (BODEGA) ========= -->
<div id="modalDetalle" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">

    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
      <h2 class="text-lg font-semibold text-gray-900">Detalles de la Bodega</h2>

      <button id="cerrarDetalle" type="button" class="h-8 w-8 rounded-full hover:bg-gray-100 flex items-center justify-center">
        <i data-lucide="x" class="w-4 h-4 text-gray-600"></i>
      </button>
    </div>

    <div class="overflow-y-auto px-6 py-5 space-y-6">

      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
          <i data-lucide="warehouse" class="w-6 h-6"></i>
        </div>

        <div>
          <h3 id="detalleNombre" class="text-base font-semibold text-gray-900">-</h3>
          <p class="text-xs text-gray-500">
            ID: <span id="detalleId">-</span>
          </p>
        </div>
      </div>

      <div class="grid gap-4 text-sm">
        <div class="grid grid-cols-2 gap-6 items-center">
          <div class="grid grid-cols-[120px_auto] gap-3 items-center">
            <span class="text-gray-600">Clasificación:</span>
            <span id="detalleClasificacion" class="detalle-chip">-</span>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-6 items-center">
          <div class="grid grid-cols-[120px_auto] gap-3 items-center">
            <span class="text-gray-600">Ubicación:</span>
            <span id="detalleUbicacion" class="font-medium text-gray-800">-</span>
          </div>

          <div class="grid grid-cols-[120px_auto] gap-3 items-center">
            <span class="text-gray-600">Estado:</span>
            <span id="detalleEstado" class="badge-estado-activo">-</span>
          </div>
        </div>
      </div>

      <div class="pt-5 border-t border-gray-200">
        <div class="flex items-center gap-2 mb-2">
          <i data-lucide="layers" class="w-4 h-4 text-gray-600"></i>
          <h4 class="font-semibold text-gray-900">Sub-bodegas</h4>
        </div>

        <div id="subBodegasContainer" class="space-y-2">
          <p class="text-sm text-gray-500">Seleccione una bodega</p>
        </div>
      </div>

      <div class="pt-5 border-t border-gray-200">
        <div class="flex items-center gap-2 mb-2">
          <i data-lucide="box" class="w-4 h-4 text-gray-600"></i>
          <h4 class="font-semibold text-gray-900">Materiales en esta Bodega</h4>
        </div>

        <p class="text-sm text-gray-500 mb-4">
          Total: <strong>3</strong> material(es)
        </p>

        <div class="border border-gray-200 rounded-2xl p-4 bg-gray-50">
          <p class="text-sm text-gray-600">
            (Placeholder del modal viejo. Luego lo conectamos a materiales reales.)
          </p>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ✅ ========= MODAL DETALLE (SUB-BODEGA) ========= -->
<div id="modalDetalleSubBodega" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">

    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
      <h2 class="text-lg font-semibold text-gray-900">Detalles de la Sub-bodega</h2>

      <button id="cerrarDetalleSub" type="button" class="h-8 w-8 rounded-full hover:bg-gray-100 flex items-center justify-center">
        <i data-lucide="x" class="w-4 h-4 text-gray-600"></i>
      </button>
    </div>

    <div class="overflow-y-auto px-6 py-5 space-y-6">

      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
          <i data-lucide="layers" class="w-6 h-6"></i>
        </div>

        <div>
          <h3 id="detalleSubNombre" class="text-base font-semibold text-gray-900">-</h3>
          <p class="text-xs text-gray-500">
            Código: <span id="detalleSubCodigo">-</span>
          </p>
        </div>
      </div>

<div class="grid gap-4 text-sm">

          <!-- Fila 1: Clasificación + Estado -->
          <div class="grid grid-cols-2 gap-6 items-center">
            <div class="grid grid-cols-[120px_auto] gap-3 items-center">
              <span class="text-gray-600">Clasificación:</span>
              <span id="detalleSubClasificacion" class="detalle-chip">-</span>
            </div>

            <div class="grid grid-cols-[120px_auto] gap-3 items-center">
              <span class="text-gray-600">Estado:</span>
              <span id="detalleSubEstado" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium w-fit badge-estado-activo text-emerald-700">-</span>
            </div>
          </div>

          <!-- Fila 2: Descripción (full) -->
          <div class="grid grid-cols-[120px_auto] gap-3 items-start">
            <span class="text-gray-600">Descripción:</span>
            <span id="detalleSubDescripcion" class="font-medium text-gray-800">-</span>
          </div>

        </div>


      <!-- ✅ AQUÍ VA LA PARTE QUE TE FALTABA -->
      <div class="pt-5 border-t border-gray-200">
        <div class="flex items-center gap-2 mb-2">
          <i data-lucide="box" class="w-4 h-4 text-gray-600"></i>
          <h4 class="font-semibold text-gray-900">Materiales en esta Sub-bodega</h4>
        </div>

        <p class="text-sm text-gray-500 mb-4">
          Total: <strong id="detalleSubTotalMateriales">0</strong> material(es)
        </p>

        <div class="border border-gray-200 rounded-2xl p-4 bg-gray-50">
          <p class="text-sm text-gray-600" id="detalleSubMaterialesPlaceholder">
            (Placeholder del modal viejo. Luego lo conectamos a materiales reales.)
          </p>
        </div>
      </div>

    </div>
  </div>
</div>

  <!-- Modal Editar sub-bodega -->

    <div id="modalEditarSubBodega" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="absolute inset-0" id="backdropEditarSub"></div>

    <div class="relative mx-4 w-full max-w-2xl rounded-2xl bg-white shadow-xl p-6 sm:p-8">
      <div class="flex items-start justify-between mb-4">
        <div>
          <h2 class="text-xl font-semibold text-gray-900">Editar Sub-bodega</h2>
          <p class="text-sm text-gray-500">Modifica la información de la sub-bodega</p>
        </div>

        <button type="button" id="cerrarEditarSub" class="inline-flex h-8 w-8 items-center justify-center rounded-full hover:bg-gray-100">
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>

      <input type="hidden" id="editSubId" />

      <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Código *</label>
            <input id="editSubCodigo" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Clasificación *</label>
            <select id="editSubClasificacion" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
              <option value="Insumos">Insumos</option>
              <option value="Equipos">Equipos</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
          <input id="editSubNombre" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
          <textarea id="editSubDescripcion" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
        </div>

        <div class="mt-4 flex items-center justify-end gap-2">
          <button type="button" id="cancelarEditarSub"
                  class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 border border-gray-200">
            Cancelar
          </button>

          <button type="button" id="guardarEditarSubBodega"
                  class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-secondary hover:opacity-90">
            Guardar cambios
          </button>
        </div>
      </div>
    </div>
  </div>

  </div>

</main>
</body>
</html>

