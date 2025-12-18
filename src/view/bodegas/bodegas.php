<?php
$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";

$bodegas = [
    [1, "Bodega Principal - Eléctrico", "Insumos", "Bloque A, Piso 1", "Bodega", "Activo", 1],
    [2, "Bodega Construcción", "Equipos", "Bloque A, Piso 2", "Bodega", "Activo", 3],
    [3, "Sub-bodega Sanitario", "Insumos", "Bloque B, Piso 1", "Sub-Bodega", "Activo", 4],
    [4, "Bodega Herramientas", "Equipos", "Bloque C, Piso 1", "Bodega", "Activo", 2],
    [5, "Ejemplo", "Insumos", "Bloque A, Piso 3", "Bodega", "Inactivo", 1],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Gestión Bodegas</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- CSS globals -->
  <link rel="stylesheet" href="src/assets/css/globals.css" />

  <!-- CSS bodegas -->
  <link rel="stylesheet" href="src/assets/css/bodegas/bodegas.css" />

  <!-- JS bodegas -->
  <script src="src/assets/js/bodegas/bodegas.js" defer></script>
</head>

<body class="bg-gray-50 text-gray-900">
<main class="p-6 transition-all duration-300"
  style="margin-left: <?= isset($_GET['coll']) && $_GET['coll'] == "1" ? '70px' : '260px' ?>;"
>

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

  <!-- TÍTULO -->
  <div>
    <h1 class="text-3xl font-bold">Gestión Bodegas</h1>
    <p class="text-sm text-gray-500">
      Administra las bodegas y sub-bodegas del inventario
    </p>
  </div>

  <!-- CONTROLES DERECHA -->
<div class="flex items-center gap-2">

  <!-- Grupo: Switch Lista / Grid -->
  <div class="inline-flex rounded-lg border border-gray-200 bg-white overflow-hidden shadow-sm">

    <!-- Lista -->
    <button
      type="button"
      id="btnVistaTabla"
      class="px-3 py-2 text-sm flex items-center gap-1 bg-muted text-foreground"
    >
      <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
           viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>

    <!-- Grid -->
    <button
      type="button"
      id="btnVistaTarjetas"
      class="px-3 py-2 text-sm flex items-center gap-1 text-muted-foreground"
    >
      <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
           viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <rect x="4" y="4" width="7" height="7" rx="1"></rect>
        <rect x="13" y="4" width="7" height="7" rx="1"></rect>
        <rect x="4" y="13" width="7" height="7" rx="1"></rect>
        <rect x="13" y="13" width="7" height="7" rx="1"></rect>
      </svg>
    </button>

  </div>

  <!-- Botón Nueva Bodega (grupo independiente) -->
  <button
    id="btnNuevaBodega"
    class="inline-flex items-center justify-center rounded-lg bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2"
  >
    <i data-lucide="plus" class="w-4 h-4"></i>
    Nueva Bodega
  </button>

</div>

</div>
<!-- BUSCADOR + FILTRO -->
<div class="flex items-center justify-between w-full gap-4 mb-4 -mt-4">

  <!-- BUSCADOR (ESTILO ORIGINAL) -->
  <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-4 py-2 shadow-sm w-full sm:w-[330px] bg-gray-100">
    <i data-lucide="search" class="w-4 h-4 text-gray-500"></i>
    <input
      type="text"
      placeholder="Buscar por nombre o ID"
      class="w-full bg-transparent outline-none text-sm"
    />
  </div>

  <!-- FILTRO -->
  <div class="flex items-center gap-2">

    <i data-lucide="filter" class="w-4 h-4 text-gray-600"></i>

    <div class="relative bg-white border border-gray-200 rounded-lg px-4 py-1 shadow-sm min-w-[150px] bg-gray-100">
      <select
        id="bodegasFilter"
        class="w-full appearance-none bg-transparent outline-none text-sm text-gray-700 pr-6"
      >
        <option value="Todos">Todos</option>
        <option value="Activo">Activos</option>
        <option value="Inactivo">Inactivos</option>
      </select>
    </div>
  </div>

</div>

  <div class="w-full">
    <!-- ========= VISTA LISTA ========= -->
    <div id="view-list">
      <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-100 text-gray-600">
            <tr>
              <th class="px-4 py-3 text-left font-semibold">ID</th>
              <th class="px-4 py-3 text-left font-semibold">Nombre</th>
              <th class="px-4 py-3 text-left font-semibold">Clasificación</th>
              <th class="px-4 py-3 text-left font-semibold">Ubicación</th>
              <th class="px-4 py-3 text-left font-semibold">Tipo</th>
              <th class="px-4 py-3 text-left font-semibold">Estado</th>
              <th class="px-4 py-3 text-left font-semibold">Acciones</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-200">
            <?php foreach ($bodegas as $b): ?>
              <?php $activo = $b[5] === "Activo"; ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">#<?= $b[0] ?></td>

                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                      <i data-lucide="warehouse" class="w-4 h-4"></i>
                    </div>
                    <span class="font-medium text-gray-900"><?= htmlspecialchars($b[1]) ?></span>
                  </div>
                </td>

                <td class="px-4 py-3">
                  <span class="inline-flex items-center rounded-full border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-700">
                    <?= htmlspecialchars($b[2]) ?>
                  </span>

                </td>

                <td class="px-4 py-3 text-gray-700">
                  <i data-lucide="map-pin" class="w-4 h-4 inline-block text-gray-500 mr-1"></i>
                  <?= htmlspecialchars($b[3]) ?>
                </td>

                <td class="px-4 py-3">
                  <span class="inline-flex items-center rounded-full border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-700">
                    <?= htmlspecialchars($b[4]) ?>
                  </span>
                </td>
                
                <td class="px-4 py-3">
                  <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
                    <?= $activo ? 'badge-estado-activo' : 'badge-estado-inactivo' ?>">
                    <?= htmlspecialchars($b[5]) ?>
                  </span>
                </td>


                <td class="px-4 py-3">
                  <button
                    class="w-8 h-8 rounded-full flex items-center justify-center bodegas-btn-dots"
                    data-id="<?= $b[0] ?>"
                    data-nombre="<?= htmlspecialchars($b[1]) ?>"
                    data-clasificacion="<?= htmlspecialchars($b[2]) ?>"
                    data-ubicacion="<?= htmlspecialchars($b[3]) ?>"
                    data-tipo="<?= htmlspecialchars($b[4]) ?>"
                    data-estado="<?= htmlspecialchars($b[5]) ?>"
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

  </div>

  <!-- ========= VISTA GRID ========= -->
  <div id="view-grid" class="hidden">
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">

      <?php foreach ($bodegas as $b):
        $estadoActivo = $b[5] === "Activo";
      ?>
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col gap-3 relative">

          <!-- Header -->
          <div class="flex items-start justify-between gap-4">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
              <i data-lucide="warehouse" class="w-5 h-5"></i>
            </div>
            <div>
              <h2 class="text-base font-semibold text-gray-900"><?= htmlspecialchars($b[1]) ?></h2>
              <p class="text-xs text-gray-500">ID: <?= $b[0] ?></p>
            </div>

            <button
              class="w-8 h-8 rounded-full flex items-center justify-center bodegas-btn-dots"
              data-id="<?= $b[0] ?>"
              data-nombre="<?= htmlspecialchars($b[1]) ?>"
              data-clasificacion="<?= htmlspecialchars($b[2]) ?>"
              data-ubicacion="<?= htmlspecialchars($b[3]) ?>"
              data-tipo="<?= htmlspecialchars($b[4]) ?>"
              data-estado="<?= htmlspecialchars($b[5]) ?>"
            >
              <i data-lucide="more-horizontal" class="w-4 h-4"></i>
            </button>
          </div>

          <!-- Location -->
          <div class="flex items-center gap-2 text-sm text-gray-700">
            <i data-lucide="map-pin" class="w-4 h-4 text-gray-500"></i>
            <span><?= htmlspecialchars($b[3]) ?></span>
          </div>

          <!-- Tags -->
          <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs bg-blue-100 text-blue-700">
              <?= htmlspecialchars($b[2]) ?>
            </span>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs bg-gray-100 text-gray-700">
              <?= htmlspecialchars($b[4]) ?>
            </span>
          </div>

          <hr class="border-gray-200">

          <!-- Footer -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-sm text-gray-700">
              <i data-lucide="package" class="w-4 h-4 text-gray-500"></i>
              <span><?= $b[6] ?> Materiales</span>
            </div>

            <div class="flex items-center gap-3">
              <span
                class="estado-text text-sm font-medium <?= $estadoActivo ? 'text-emerald-700' : 'text-red-700' ?>"
              >
                <?= $estadoActivo ? "Activa" : "Inactiva" ?>
              </span>

              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  class="sr-only peer estado-switch"
                  data-id="<?= $b[0] ?>"
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

<!-- ========= MENÚ CONTEXTUAL ========= -->
<div
  id="context-menu"
  class="hidden absolute z-50 w-56 rounded-lg bg-white border border-gray-200 shadow-md"
>
  <ul class="py-2 text-sm text-gray-800">
    
    <!-- Ver detalles -->
    <li>
      <button
        class="w-full flex items-center gap-3 px-4 py-2 hover:bg-gray-50 bodegas-ctx-btn"
        data-action="ver"
      >
        <i data-lucide="eye" class="w-5 h-5 text-gray-600"></i>
        <span>Ver detalles</span>
      </button>
    </li>

    <!-- Editar -->
    <li>
      <button
        class="w-full flex items-center gap-3 px-4 py-2 hover:bg-gray-50 bodegas-ctx-btn"
        data-action="editar"
      >
        <i data-lucide="square-pen" class="w-5 h-5 text-gray-600"></i>
        <span>Editar</span>
      </button>
    </li>

    <!-- Separador -->
    <li class="my-2 border-t border-gray-200"></li>

    <!-- Desactivar -->
    <li>
      <button
        class="w-full flex items-center gap-3 px-4 py-2 hover:bg-gray-50 bodegas-ctx-btn"
        data-action="deshabilitar"
      >
        <i data-lucide="power" class="w-5 h-5 text-gray-600"></i>
        <span>Desactivar</span>
      </button>
    </li>

  </ul>
</div>

  <!-- ========= MODAL CREAR (DISEÑO TIPO MOVIMIENTOS) ========= -->
<div id="modalCrear" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
  <!-- Fondo clicable para cerrar -->
  <div class="absolute inset-0" id="backdropCrear"></div>

  <!-- Contenido del modal -->
  <div class="relative mx-4 w-full max-w-2xl rounded-2xl bg-white shadow-xl p-6 sm:p-8">
    <!-- Header -->
    <div class="flex items-start justify-between mb-4">
      <div>
        <h2 class="text-xl font-semibold text-gray-900">Crear Nueva Bodega</h2>
        <p class="text-sm text-gray-500">Complete los datos para registrar una nueva bodega</p>
      </div>

      <button
        type="button"
        id="cerrarModal"
        class="inline-flex h-8 w-8 items-center justify-center rounded-full hover:bg-gray-100">
        <i data-lucide="x" class="h-4 w-4"></i>
      </button>
    </div>

    <!-- FORM -->
    <form class="space-y-4">
      <div class="grid gap-4 sm:grid-cols-2">

        <!-- ID -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ID *</label>
          <input
            type="text"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"
            placeholder="C-1">
        </div>

        <!-- Tipo -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
          <select
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
            <option>Bodega</option>
            <option>Sub-Bodega</option>
          </select>
        </div>

        <!-- Clasificación -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Clasificación *</label>
          <select
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
            <option>Insumos</option>
            <option>Equipos</option>
          </select>
        </div>

        <!-- Ubicación -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación *</label>
          <input
            type="text"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"
            placeholder="Ej: Bloque A, Piso 1">
        </div>
      </div>

      <!-- Nombre -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Bodega *</label>
        <input
          type="text"
          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]"
          placeholder="Ej: Bodega Principal - Eléctrico">
      </div>

      <!-- Footer botones -->
      <div class="mt-4 flex items-center justify-end gap-2">
        <button
          type="button"
          id="cancelarModal"
          class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 border border-gray-200">
          Cancelar
        </button>

        <button
          type="button"
          class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-secondary hover:opacity-90">
          Crear Bodega
        </button>
      </div>
    </form>
  </div>
</div>


  <!-- ========= MODAL EDITAR (DISEÑO TIPO MOVIMIENTOS) ========= -->
<div id="modalEditar" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
  <!-- Fondo clicable para cerrar -->
  <div class="absolute inset-0" id="backdropEditar"></div>

  <!-- Contenido del modal -->
  <div class="relative mx-4 w-full max-w-2xl rounded-2xl bg-white shadow-xl p-6 sm:p-8">
    <!-- Header -->
    <div class="flex items-start justify-between mb-4">
      <div>
        <h2 class="text-xl font-semibold text-gray-900">Editar Bodega</h2>
        <p class="text-sm text-gray-500">Modifica la información de la bodega</p>
      </div>

      <button
        type="button"
        id="cerrarEditar"
        class="inline-flex h-8 w-8 items-center justify-center rounded-full hover:bg-gray-100">
        <i data-lucide="x" class="h-4 w-4"></i>
      </button>
    </div>

    <!-- FORM -->
    <form class="space-y-4">
      <div class="grid gap-4 sm:grid-cols-2">
        <!-- ID -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ID *</label>
          <input
            id="editId"
            type="text"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
        </div>

        <!-- Tipo -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
          <select
            id="editTipo"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
            <option>Bodega</option>
            <option>Sub-Bodega</option>
          </select>
        </div>

        <!-- Clasificación -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Clasificación *</label>
          <select
            id="editClasificacion"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
            <option>Eléctrico</option>
            <option>Construcción</option>
            <option>Sanitario</option>
            <option>Herramientas</option>
            <option>Ejemplo</option>
          </select>
        </div>

        <!-- Ubicación -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación *</label>
          <input
            id="editUbicacion"
            type="text"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
        </div>
      </div>

      <!-- Nombre -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Bodega *</label>
        <input
          id="editNombre"
          type="text"
          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#39A90040] focus:border-[#39A900]">
      </div>

      <!-- Footer botones -->
      <div class="mt-4 flex items-center justify-end gap-2">
        <button
          type="button"
          id="cancelarEditar"
          class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 border border-gray-200">
          Cancelar
        </button>

        <button
          type="button"
          id="guardarEditar"
          class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-secondary hover:opacity-90">
          Guardar cambios
        </button>
      </div>
    </form>
  </div>
</div>


<!-- ========= MODAL DETALLE ========= -->
<div
  id="modalDetalle"
  class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center"
>
  <div
    class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col"
  >

    <!-- HEADER -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
      <h2 class="text-lg font-semibold text-gray-900">
        Detalles de la Bodega
      </h2>

      <button
        id="cerrarDetalle"
        class="h-8 w-8 rounded-full hover:bg-gray-100 flex items-center justify-center"
      >
        <i data-lucide="x" class="w-4 h-4 text-gray-600"></i>
      </button>
    </div>

    <!-- CONTENIDO CON SCROLL -->
    <div class="overflow-y-auto px-6 py-5 space-y-6">

      <!-- INFO PRINCIPAL -->
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
          <i data-lucide="warehouse" class="w-6 h-6"></i>
        </div>

        <div>
          <h3 id="detalleNombre" class="text-base font-semibold text-gray-900">
            Bodega Principal - Eléctrico
          </h3>
          <p class="text-xs text-gray-500">
            ID: <span id="detalleId">1</span>
          </p>
        </div>
      </div>

      <!-- DATOS BODEGA -->
      <div class="grid gap-4 text-sm">

        <!-- FILA 1: CLASIFICACIÓN + TIPO -->
        <div class="grid grid-cols-2 gap-6 items-center">
          <div class="grid grid-cols-[120px_auto] gap-3 items-center">
            <span class="text-gray-600">Clasificación:</span>
            <span id="detalleClasificacion" class="detalle-chip">Insumos</span>
          </div>

          <div class="grid grid-cols-[120px_auto] gap-3 items-center">
            <span class="text-gray-600">Tipo:</span>
            <span id="detalleTipo" class="detalle-chip">Bodega</span>
          </div>
        </div>

        <!-- FILA 2: UBICACIÓN + ESTADO -->
        <div class="grid grid-cols-2 gap-6 items-center">
          <div class="grid grid-cols-[120px_auto] gap-3 items-center">
            <span class="text-gray-600">Ubicación:</span>
            <span
              id="detalleUbicacion"
              class="font-medium text-gray-800"
            >
              Bloque A, Piso 1
            </span>
          </div>

          <div class="grid grid-cols-[120px_auto] gap-3 items-center">
            <span class="text-gray-600">Estado:</span>
            <span
              id="detalleEstado"
              class="badge-estado-activo"
            >
              Activo
            </span>
          </div>
        </div>

      </div>

      <!-- SECCIÓN MATERIALES -->
      <div class="pt-5 border-t border-gray-200">

        <div class="flex items-center gap-2 mb-2">
          <i data-lucide="box" class="w-4 h-4 text-gray-600"></i>
          <h4 class="font-semibold text-gray-900">
            Materiales en esta Bodega
          </h4>
        </div>

        <p class="text-sm text-gray-500 mb-4">
          Total: <strong>3</strong> material(es)
        </p>

        <!-- LISTA MATERIALES -->
        <div class="border border-gray-200 rounded-2xl p-4 bg-gray-50">

          <div class="flex gap-4">

            <!-- ICONO / IMAGEN DEL MATERIAL -->
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0">
              <i data-lucide="box" class="w-5 h-5"></i>
              <!-- si luego usas imagen real: <img src="ruta-imagen.png" class="w-6 h-6" /> -->
            </div>

            <!-- CONTENIDO -->
            <div class="flex-1">

              <div class="flex justify-between items-start">
              <div>
                <h5 class="font-medium text-gray-900">Taladro Percutor</h5>
                <p class="text-xs text-gray-500">HER-001 • Herramientas</p>
              </div>
              <span class="badge-material-disponible">Disponible</span>
            </div>

            <div class="mt-3 text-sm font-medium text-gray-900">
              <strong>5</strong> unidad
            </div>

            <div class="mt-2">
              <!-- BARRA -->
              <div class="mt-2">
                <div class="h-2 rounded-full bg-gray-200 overflow-hidden">
                  <div class="h-full rounded-full bg-emerald-600 w-full"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1">Min: 3</p>
              </div>
            </div>

          </div>

        </div>
      </div>
    </div>
  </div>
</div>

</main>

<script>
document.addEventListener("DOMContentLoaded", () => {
  // Lucide
  if (window.lucide && typeof lucide.createIcons === "function") {
    lucide.createIcons();
  }

  /* ============================================================
     ========== CAMBIO LISTA / GRID (TARJETAS) ==========
  ============================================================ */
  const btnVistaTabla    = document.getElementById("btnVistaTabla");
  const btnVistaTarjetas = document.getElementById("btnVistaTarjetas");
  const viewList         = document.getElementById("view-list");
  const viewGrid         = document.getElementById("view-grid");

  const setActiveBtn = (active, inactive) => {
    active.classList.add("bg-muted", "text-foreground");
    active.classList.remove("text-muted-foreground");

    inactive.classList.remove("bg-muted", "text-foreground");
    inactive.classList.add("text-muted-foreground");
  };

  const showList = () => {
    viewList?.classList.remove("hidden");
    viewGrid?.classList.add("hidden");
    if (btnVistaTabla && btnVistaTarjetas) setActiveBtn(btnVistaTabla, btnVistaTarjetas);
  };

  const showGrid = () => {
    viewGrid?.classList.remove("hidden");
    viewList?.classList.add("hidden");
    if (btnVistaTabla && btnVistaTarjetas) setActiveBtn(btnVistaTarjetas, btnVistaTabla);

    if (window.lucide && typeof lucide.createIcons === "function") {
      lucide.createIcons();
    }
  };

  if (btnVistaTabla && btnVistaTarjetas) {
    btnVistaTabla.addEventListener("click", showList);
    btnVistaTarjetas.addEventListener("click", showGrid);
    showList(); // inicial
  }

  /* ============================================================
     ========== MODAL CREAR (NUEVA BODEGA) ==========
  ============================================================ */
  const btnNuevaBodega = document.getElementById("btnNuevaBodega");
  const modalCrear     = document.getElementById("modalCrear");
  const cerrarModal    = document.getElementById("cerrarModal");
  const cancelarModal  = document.getElementById("cancelarModal");

  const openModal = (modal) => {
    if (!modal) return;
    modal.classList.remove("hidden");
    modal.classList.add("flex");
    document.body.classList.add("overflow-hidden");
    if (window.lucide && typeof lucide.createIcons === "function") lucide.createIcons();
  };

  const closeModal = (modal) => {
    if (!modal) return;
    modal.classList.add("hidden");
    modal.classList.remove("flex");
    document.body.classList.remove("overflow-hidden");
  };

  btnNuevaBodega?.addEventListener("click", () => openModal(modalCrear));
  cerrarModal?.addEventListener("click", () => closeModal(modalCrear));
  cancelarModal?.addEventListener("click", () => closeModal(modalCrear));

  // Cerrar modal crear clic fuera
  modalCrear?.addEventListener("click", (e) => {
    if (e.target === modalCrear) closeModal(modalCrear);
  });

  /* ============================================================
     ========== MENÚ CONTEXTUAL (3 PUNTOS) ==========
  ============================================================ */
  const contextMenu  = document.getElementById("context-menu");
  const modalDetalle = document.getElementById("modalDetalle");
  const modalEditar  = document.getElementById("modalEditar");

  let selectedData = null;

  function openContextMenu(btn) {
    if (!contextMenu) return;

    selectedData = {
      id: btn.dataset.id,
      nombre: btn.dataset.nombre,
      clasificacion: btn.dataset.clasificacion,
      ubicacion: btn.dataset.ubicacion,
      tipo: btn.dataset.tipo,
      estado: btn.dataset.estado
    };

    const r = btn.getBoundingClientRect();
    const menuWidth = 208; // w-52
    const x = r.right + window.scrollX - menuWidth;
    const y = r.bottom + window.scrollY + 8;

    contextMenu.style.left = `${Math.max(8, x)}px`;
    contextMenu.style.top  = `${y}px`;
    contextMenu.classList.remove("hidden");

    if (window.lucide && typeof lucide.createIcons === "function") {
      lucide.createIcons();
    }
  }

  function closeContextMenu() {
    contextMenu?.classList.add("hidden");
  }

  // Delegación para lista y grid
  document.addEventListener("click", (e) => {
    const btnDots = e.target.closest(".bodegas-btn-dots");
    if (btnDots) {
      e.preventDefault();
      e.stopPropagation();
      openContextMenu(btnDots);
      return;
    }

    if (contextMenu && !contextMenu.contains(e.target)) {
      closeContextMenu();
    }
  });

  /* ============================================================
     ========== ACCIONES DEL MENÚ ==========
  ============================================================ */
  if (contextMenu) {
    const btnVer  = contextMenu.querySelector("[data-action='ver']");
    const btnEdit = contextMenu.querySelector("[data-action='editar']");
    const btnOff  = contextMenu.querySelector("[data-action='deshabilitar']");

    btnVer?.addEventListener("click", () => {
      if (!selectedData || !modalDetalle) return;

      document.getElementById("detalleId").textContent = selectedData.id;
      document.getElementById("detalleNombre").textContent = selectedData.nombre;
      document.getElementById("detalleClasificacion").textContent = selectedData.clasificacion;
      document.getElementById("detalleTipo").textContent = selectedData.tipo;
      document.getElementById("detalleUbicacion").textContent = selectedData.ubicacion;

      const est = document.getElementById("detalleEstado");
      est.textContent = selectedData.estado;
      est.className =
        "inline-flex items-center rounded-full px-3 py-1 text-xs " +
        (selectedData.estado === "Activo"
          ? "bg-emerald-100 text-emerald-700"
          : "bg-red-100 text-red-700");

      openModal(modalDetalle);
      closeContextMenu();
    });

    btnEdit?.addEventListener("click", () => {
      if (!selectedData || !modalEditar) return;

      document.getElementById("editId").value = selectedData.id;
      document.getElementById("editNombre").value = selectedData.nombre;
      document.getElementById("editClasificacion").value = selectedData.clasificacion;
      document.getElementById("editUbicacion").value = selectedData.ubicacion;
      document.getElementById("editTipo").value = selectedData.tipo;

      openModal(modalEditar);
      closeContextMenu();
    });

    btnOff?.addEventListener("click", () => {
      if (!selectedData) return;
      alert(`Bodega #${selectedData.id} deshabilitada`);
      closeContextMenu();
    });
  }

  /* ============================================================
     ========== CERRAR MODALES DETALLE / EDITAR ==========
  ============================================================ */
  document.getElementById("cerrarDetalle")?.addEventListener("click", () => closeModal(modalDetalle));
  document.getElementById("cerrarEditar")?.addEventListener("click", () => closeModal(modalEditar));
  document.getElementById("cancelarEditar")?.addEventListener("click", () => closeModal(modalEditar));

  modalDetalle?.addEventListener("click", (e) => {
    if (e.target === modalDetalle) closeModal(modalDetalle);
  });
  modalEditar?.addEventListener("click", (e) => {
    if (e.target === modalEditar) closeModal(modalEditar);
  });

  /* ============================================================
     ========== SWITCH ACTIVA / INACTIVA (GRID) ==========
  ============================================================ */
  document.addEventListener("change", (e) => {
    const sw = e.target.closest(".estado-switch");
    if (!sw) return;

    const card = sw.closest(".bg-white.border.border-gray-200"); // la card actual
    if (!card) return;

    const estadoNuevo = sw.checked ? "Activo" : "Inactivo";
    const textoNuevo  = sw.checked ? "Activa" : "Inactiva";

    const estadoText = card.querySelector(".estado-text");
    if (estadoText) {
      estadoText.textContent = textoNuevo;
      estadoText.classList.toggle("text-emerald-700", sw.checked);
      estadoText.classList.toggle("text-red-700", !sw.checked);
    }

    // Guardar estado
    sw.dataset.estado = estadoNuevo;

    // Actualizar data-estado del botón de 3 puntos para que el modal salga bien
    const dotsBtn = card.querySelector(".bodegas-btn-dots");
    if (dotsBtn) {
      dotsBtn.dataset.estado = estadoNuevo;
    }
  });

  /* ============================================================
     ========== ESC PARA CERRAR ==========
  ============================================================ */
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeModal(modalCrear);
      closeModal(modalDetalle);
      closeModal(modalEditar);
      closeContextMenu();
    }
  });
});
</script>


</body>
</html>
