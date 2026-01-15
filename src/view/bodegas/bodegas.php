<?php
// =====================================
// BODEGAS – PHP VIEW
// =====================================

$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Gestión Bodegas</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Iconos Lucide -->
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- CSS Global (RUTA ABSOLUTA CORRECTA) -->
  <link rel="stylesheet" href="/Gestion-inventario/src/assets/css/globals.css">

  <!-- CSS del módulo Bodegas -->
  <link rel="stylesheet" href="/Gestion-inventario/src/assets/css/bodega/bodega.css">
</head>

<body class="bg-background text-foreground sidebar-expanded">

  <main class="page-with-sidebar px-6 lg:px-10 py-6 fade-in">

    <!-- =========================================================
         ENCABEZADO PRINCIPAL
    ========================================================== -->
    <div class="flex flex-col gap-1 mb-6">
      <h1 class="text-2xl font-semibold tracking-tight">Gestión Bodegas</h1>
      <p class="text-sm text-muted-foreground">Administra las bodegas y sub-bodegas del inventario</p>
    </div>

    <!-- =========================================================
         BARRA SUPERIOR (buscador, filtros, vistas, nuevo)
    ========================================================== -->
    <div class="flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between mb-5">

      <!-- BUSCADOR -->
      <div class="w-full lg:max-w-md">
        <div class="relative">
          <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>

          <input
            id="inputBuscarBodega"
            type="text"
            class="input-siga w-full"
            placeholder="Buscar por nombre o código..."
          />
        </div>
      </div>

      <!-- CONTROLES DERECHA -->
      <div class="flex flex-wrap gap-3 items-center justify-between lg:justify-end w-full">

        <!-- Cambiar vista -->
        <div class="inline-flex rounded-xl border border-border bg-card overflow-hidden">

          <!-- Vista tabla -->
          <button
            id="btnVistaTablaBodega"
            class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 bg-muted"
          >
            <i data-lucide="list" class="w-4 h-4"></i>
            Lista
          </button>

          <!-- Vista tarjetas -->
          <button
            id="btnVistaTarjetasBodega"
            class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 text-muted-foreground"
          >
            <i data-lucide="grid" class="w-4 h-4"></i>
            Tarjetas
          </button>

        </div>

        <!-- Botón nueva bodega -->
        <button
          id="btnNuevaBodega"
          class="inline-flex items-center gap-2 rounded-xl bg-primary text-white px-4 py-2 text-sm font-medium shadow-sm"
        >
          <i data-lucide="plus" class="w-4 h-4"></i>
          Nueva Bodega
        </button>

        <!-- Filtros -->
        <div class="flex gap-2">

          <!-- Filtrar por tipo -->
          <select id="selectFiltroTipo" class="input-siga text-xs sm:text-sm">
            <option value="todos">Todas</option>
            <option value="bodega">Solo bodegas</option>
            <option value="subbodega">Solo sub-bodegas</option>
          </select>

          <!-- Filtrar por estado -->
          <select id="selectFiltroEstado" class="input-siga text-xs sm:text-sm">
            <option value="todos">Todos</option>
            <option value="Activo">Activos</option>
            <option value="Inactivo">Inactivos</option>
          </select>

        </div>

      </div>
    </div>

    <!-- =========================================================
         ESTADO VACÍO (cuando no hay registros)
    ========================================================== -->
    <div id="emptyStateBodegas"
         class="hidden mt-10 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10">

      <i data-lucide="archive" class="w-12 h-12 text-muted-foreground"></i>

      <h3 class="text-lg font-semibold mt-4">No hay bodegas registradas</h3>
      <p class="text-sm text-muted-foreground mt-1 max-w-md">
        Agrega una bodega o sub-bodega con el botón “Nueva Bodega”.
      </p>
    </div>

    <!-- =========================================================
         TABLA
    ========================================================== -->
    <div id="vistaTablaBodegas" class="bg-card border border-border rounded-2xl overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-muted/60 text-xs font-medium text-muted-foreground">
          <tr>
            <th class="px-4 py-3 text-left">ID</th>
            <th class="px-4 py-3 text-left">Nombre</th>
            <th class="px-4 py-3 text-left">Código</th>
            <th class="px-4 py-3 text-left">Ubicación</th>
            <th class="px-4 py-3 text-left">Tipo</th>
            <th class="px-4 py-3 text-left">Estado</th>
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody id="tbodyBodegas" class="divide-y divide-border bg-card"></tbody>
      </table>
    </div>

    <!-- =========================================================
         TARJETAS (GRID)
    ========================================================== -->
    <div id="vistaTarjetasBodegas" class="hidden">
      <div id="cardsBodegasContainer"
           class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-2">
      </div>
    </div>

    <!-- =========================================================
         MODAL CREAR / EDITAR
    ========================================================== -->
    <div id="modalBodega" class="modal-overlay">

      <div class="modal-container">

        <div class="flex items-start justify-between mb-4">
          <div>
            <h2 id="modalBodegaTitulo" class="text-lg font-semibold">Crear Nueva Bodega</h2>
            <p class="text-sm text-muted-foreground">
              Complete los datos para registrar una nueva bodega o sub-bodega
            </p>
          </div>

          <button id="btnCerrarModalBodega" class="modal-close-btn">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>

        <!-- formulario -->
        <form id="formBodega" class="space-y-4">

          <input type="hidden" id="hiddenRegistroId" />

          <label>Tipo *</label>
          <select id="tipo_registro" class="input-siga w-full">
            <option value="bodega">Bodega</option>
            <option value="subbodega">Sub-bodega</option>
          </select>

          <div id="wrapper_bodega_padre" class="hidden">
            <label>Bodega padre *</label>
            <select id="id_bodega_padre" class="input-siga w-full"></select>
          </div>

          <label>Código *</label>
          <input id="codigo_registro" class="input-siga w-full">

          <label>Nombre *</label>
          <input id="nombre_registro" class="input-siga w-full">

          <div id="wrapper_ubicacion">
            <label>Ubicación *</label>
            <input id="ubicacion_registro" class="input-siga w-full">
          </div>

          <div id="wrapper_clasificacion" class="hidden">
            <label>Clasificación *</label>
            <input id="clasificacion_registro" class="input-siga w-full">
          </div>

          <div id="wrapper_descripcion" class="hidden">
            <label>Descripción</label>
            <textarea id="descripcion_registro" class="input-siga w-full h-20"></textarea>
          </div>

          <div id="wrapper_estado_registro" class="hidden">
            <label>Estado</label>
            <select id="estado_registro" class="input-siga w-full">
              <option value="Activo">Activo</option>
              <option value="Inactivo">Inactivo</option>
            </select>
          </div>

          <div class="flex justify-end gap-3">
            <button id="btnCancelarModalBodega" type="button" class="btn-secondary">Cancelar</button>
            <button id="btnSubmitBodega" class="btn-primary">Guardar</button>
          </div>
        </form>

      </div>
    </div>

  </main>

  <!-- JAVASCRIPT DEL MÓDULO -->
  <script src="/Gestion-inventario/src/assets/js/bodega/bodega.js"></script>

  <!-- Inicializar Lucide -->
  <script>
    lucide.createIcons();
  </script>

</body>
</html>
