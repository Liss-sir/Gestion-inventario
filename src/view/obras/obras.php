<?php
// =====================================
// OBRAS Y ACTIVIDADES – PHP VIEW
// (Adaptado al layout de "Usuarios": sidebar colapsable + main con margin-left)
// =====================================

// ✅ NECESARIO: para poder usar $_SESSION sin warnings
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>Obras y Actividades</title>

  <!-- Globals (tokens: bg-background, text-muted-foreground, border-border, bg-card, etc.) -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>css/globals.css">

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Flowbite (opcional, pero en Usuarios lo incluyes y tus toasts son "flowbite-like") -->
  <script src="https://unpkg.com/flowbite@2.5.1/dist/flowbite.min.js"></script>

  <!-- Estilos módulo -->
  <link rel="stylesheet" href="src/assets/css/obras/obras.css" />

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-background p-6">

  <!-- Si tienes header/sidebar global, lo puedes incluir aquí (igual que en Usuarios) -->
  <!-- <?php include '../partials/dashboard-header.php'; ?> -->

  <main
    class="p-6 transition-all duration-300"
    style="margin-left: <?= $sidebarWidth ?>;"
  >
    <div class="space-y-6 animate-fade-in-up">

      <!-- ================================== -->
      <!-- PAGE HEADER                         -->
      <!-- ================================== -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold tracking-tight">Obras y Actividades</h1>
          <p class="text-muted-foreground">Gestiona las obras y actividades formativas de las fichas</p>
        </div>
      </div>

      <!-- ================================== -->
      <!-- ESTADÍSTICAS                        -->
      <!-- ================================== -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Total Obras -->
        <div class="rounded-xl border border-border bg-card shadow-sm p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs text-muted-foreground mb-1">Total Obras</p>
              <p class="text-3xl font-bold text-foreground" id="totalObras">0</p>
              <p class="text-xs text-muted-foreground mt-1 opacity-75">Registradas en el sistema</p>
            </div>
            <div class="p-3 rounded-2xl bg-[#007832]/30 inline-flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#007832" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-construction-icon lucide-construction"><rect x="2" y="6" width="20" height="8" rx="1"/><path d="M17 14v7"/><path d="M7 14v7"/><path d="M17 3v3"/><path d="M7 3v3"/><path d="M10 14 2.3 6.3"/><path d="m14 6 7.7 7.7"/><path d="m8 6 8 8"/></svg>
            </div>
          </div>
        </div>

        <!-- Obras Activas -->
        <div class="rounded-xl border border-border bg-card shadow-sm p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs text-muted-foreground mb-1">Obras Activas</p>
              <p class="text-3xl font-bold text-foreground" id="obrasActivas">0</p>
              <p class="text-xs text-muted-foreground mt-1 opacity-75">En ejecución actualmente</p>
            </div>
            <div class="p-3 rounded-2xl bg-[#007832]/30 inline-flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#007832" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-monitor-check-icon lucide-monitor-check"><path d="m9 10 2 2 4-4"/><rect width="20" height="14" x="2" y="3" rx="2"/><path d="M12 17v4"/><path d="M8 21h8"/></svg>
            </div>
          </div>
        </div>

        <!-- Obras Finalizadas -->
        <div class="rounded-xl border border-border bg-card shadow-sm p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs text-muted-foreground mb-1">Obras Finalizadas</p>
              <p class="text-3xl font-bold text-foreground" id="obrasFinalizadas">0</p>
              <p class="text-xs text-muted-foreground mt-1 opacity-75">Completadas o inactivas</p>
            </div>
            <div class="p-3 rounded-2xl bg-[#007832]/30 inline-flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#007832" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bookmark-check-icon lucide-bookmark-check"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2Z"/><path d="m9 10 2 2 4-4"/></svg>
            </div>
          </div>
        </div>

      </div>

      <!-- ================================== -->
      <!-- LISTA DE OBRAS                      -->
      <!-- ================================== -->
      <div class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">

        <div class="p-6 border-b border-border">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-xl font-semibold text-foreground">Lista de Obras</h2>
              <p class="text-sm text-muted-foreground mt-1">Administra las obras y actividades formativas</p>
            </div>

            <button
              onclick="openCreateModal()"
              class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2"
            >
              <i class="fas fa-plus"></i>
              Nueva Obra
            </button>
          </div>

          <!-- Buscador (estilo tipo Usuarios) -->
          <div class="mt-4">
            <div class="relative w-full">
              <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
              <input
                type="text"
                id="searchInput"
                placeholder="Buscar por nombre, ficha o instructor..."
                class="w-full rounded-md border border-input bg-background pl-9 pr-3 py-2 text-sm"
                onkeyup="searchObras()"
              />
            </div>
          </div>
        </div>

        <!-- Contenedor de obras -->
        <div id="obrasContainer" class="p-6">
          <!-- Loading -->
          <div class="text-center py-12" id="loading">
            <i class="fas fa-spinner fa-spin text-3xl text-muted-foreground mb-3"></i>
            <p class="text-muted-foreground">Cargando obras...</p>
          </div>
        </div>

      </div>

    </div>
  </main>

  <!-- ========================================= -->
  <!-- MODAL NUEVA OBRA                          -->
  <!-- ========================================= -->
  <div id="modalCreate" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-card rounded-xl border border-border shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 pb-0">
        <div class="flex flex-col items-start justify-between p-0">
          <h3 class="text-xl font-semibold text-foreground">Nueva Obra</h3>
          <b class="text-sm text-muted-foreground js-descripcion opacity-75">Registra una nueva obra o actividad formativa</b>
        </div>
        <button onclick="closeCreateModal()" class="text-muted-foreground hover:opacity-80">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <form id="formCreate" class="p-6 space-y-4" onsubmit="handleCreateObra(event)">
        <div>
          <label class="block text-xs text-muted-foreground mb-1">Ficha *</label>
          <select id="create_ficha" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" required>
            <option value="" disabled selected class="text-gray-500">Cargando fichas...</option>
          </select>
        </div>

        <div>
          <label class="block text-xs text-muted-foreground mb-1">RAE *</label>
          <select id="create_rae" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" required>
            <option value="" disabled selected class="text-gray-500">Cargando RAEs...</option>
          </select>
        </div>

        <div>
          <label class="block text-xs text-muted-foreground mb-1">Instructor *</label>
          <select id="create_instructor" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" required>
            <option value="" disabled selected class="text-gray-500">Cargando instructores...</option>
          </select>
        </div>

        <div>
          <label class="block text-xs text-muted-foreground mb-1">Nombre de la Actividad *</label>
          <input
            type="text"
            id="create_nombre"
            placeholder="Ej: Construcción de muros de contención"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga"
            required
          />
        </div>

        <div>
          <label class="block text-xs text-muted-foreground mb-1">Descripción *</label>
          <textarea
            id="create_descripcion"
            rows="3"
            placeholder="Describe los objetivos y alcance de la obra..."
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga"
            required
          ></textarea>
        </div>

        <div>
          <label class="block text-xs text-muted-foreground mb-1">Tipo de Trabajo *</label>
          <select id="create_tipo" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" required>
            <option value="Individual">Individual</option>
            <option value="Grupal">Grupal</option>
          </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-muted-foreground mb-1">Fecha de Inicio *</label>
            <input type="date" id="create_fecha_inicio" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" required />
          </div>
          <div>
            <label class="block text-xs text-muted-foreground mb-1">Fecha de Fin *</label>
            <input type="date" id="create_fecha_fin" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" required />
          </div>
        </div>

        <div class="flex gap-3 pt-4 justify-end">
          <button type="button" onclick="closeCreateModal()" class="px-4 py-2 border border-border rounded-lg">
            Cancelar
          </button>

          <button
            type="submit"
            class="inline-flex items-center justify-center rounded-md bg-secondary px-10 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2"
            id="btnCreate"
          >
            <span id="btnCreateText">Crear Obra</span>
            <span id="btnCreateLoading" class="hidden">
              <i class="fas fa-spinner fa-spin"></i>
            </span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ========================================= -->
  <!-- MODAL EDITAR OBRA                         -->
  <!-- ========================================= -->
  <div id="modalEdit" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-card rounded-xl border border-border shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 pb-0">
        <div class="flex flex-col items-start justify-between p-0">
          <h3 class="text-xl font-semibold text-foreground">Editar Obra</h3>
          <p class="text-sm text-muted-foreground js-descripcion opacity-75">Modifica la información de la obra</p>
        </div>
        <button onclick="closeEditModal()" class="text-muted-foreground hover:opacity-80">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <form id="formEdit" class="p-6 pt-0 space-y-4" onsubmit="handleEditObra(event)">
        <input type="hidden" id="edit_id" />

        <div>
          <label class="block text-xs text-muted-foreground mb-1">Ficha *</label>
          <select
            id="edit_ficha"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
            required
          >
            <option value="" disabled selected class="text-gray-500">Cargando fichas...</option>
          </select>
        </div>

        <div>
          <label class="block text-xs text-muted-foreground mb-1">RAE *</label>
          <select
            id="edit_rae"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
            required
          >
            <option value="" disabled selected class="text-gray-500">Cargando RAEs...</option>
          </select>
        </div>

        <div>
          <label class="block text-xs text-muted-foreground mb-1">Instructor *</label>
          <select
            id="edit_instructor"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
            required
          >
            <option value="" disabled selected class="text-gray-500">Cargando instructores...</option>
          </select>
        </div>

        <div>
          <label class="block text-xs text-muted-foreground mb-1">Nombre de la Actividad *</label>
          <input
            type="text"
            id="edit_nombre"
            placeholder="Ej: Construcción de muros de contención"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
            required
          />
        </div>

        <div>
          <label class="block text-xs text-muted-foreground mb-1">Descripción *</label>
          <textarea
            id="edit_descripcion"
            rows="3"
            placeholder="Describe los objetivos y alcance de la obra..."
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none"
            required
          ></textarea>
        </div>

        <div>
          <label class="block text-xs text-muted-foreground mb-1">Tipo de Trabajo *</label>
          <select
            id="edit_tipo"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
            required
          >
            <option value="Individual">Individual</option>
            <option value="Grupal">Grupal</option>
          </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-muted-foreground mb-1">Fecha de Inicio *</label>
            <input
              type="date"
              id="edit_fecha_inicio"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
              required
            />
          </div>
          <div>
            <label class="block text-xs text-muted-foreground mb-1">Fecha de Fin *</label>
            <input
              type="date"
              id="edit_fecha_fin"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
              required
            />
          </div>
        </div>

        <div class="flex gap-3 pt-4 justify-end">
          <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
            Cancelar
          </button>
          <button type="submit" class="inline-flex items-center justify-center rounded-md bg-secondary px-10 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2 transition-colors">
            Guardar Cambios
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ========================================= -->
  <!-- MODAL DETALLES                            -->
  <!-- ========================================= -->
  <div id="modalDetails" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-card rounded-xl border border-border shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 pb-0">
        <h3 class="text-2xl font-bold tracking-tight">Detalles de la Obra</h3>
        <button onclick="closeDetailsModal()" class="text-muted-foreground hover:opacity-80">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <div class="p-6 pt-0 space-y-4">
        <div>
          <h4 class="text-lg font-semibold text-foreground mb-2" id="details_nombre"></h4>
          <span id="details_badge_tipo" class="inline-block px-3 py-1 bg-secondary text-white text-xs font-semibold rounded-full"></span>
        </div>

        <div>
          <p class="text-sm font-medium text-foreground mb-1">Descripción :</p>
          <p class="text-sm text-muted-foreground" id="details_descripcion"></p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
          <div>
            <p class="text-sm font-medium text-foreground mb-1">Ficha :</p>
            <p class="text-sm text-foreground" id="details_ficha"></p>
          </div>
          <div>
            <p class="text-sm font-medium text-foreground mb-1">Tipo de Trabajo :</p>
            <span id="details_tipo" class="inline-block px-2 py-1 bg-secondary text-white text-xs font-semibold rounded-full"></span>
          </div>
        </div>

        <div>
          <p class="text-sm font-medium text-foreground mb-1">Instructor :</p>
          <p class="text-sm text-foreground" id="details_instructor"></p>
        </div>

        <div>
          <p class="text-sm font-medium text-foreground mb-1">RAE :</p>
          <p class="text-sm text-foreground" id="details_rae"></p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
          <div>
            <p class="text-sm font-medium text-foreground mb-1">Fecha de Inicio :</p>
            <p class="text-sm text-foreground" id="details_fecha_inicio"></p>
          </div>
          <div>
            <p class="text-sm font-medium text-foreground mb-1">Fecha de Fin :</p>
            <p class="text-sm text-foreground" id="details_fecha_fin"></p>
          </div>
        </div>

        <div class="pt-4">
          <button onclick="closeDetailsModal()" class="w-full px-4 py-2 bg-secondary text-white rounded-lg hover:opacity-90 transition-colors">
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================= -->
  <!-- ALERT CONTAINER (FLOWBITE-LIKE TOASTS)    -->
  <!-- ========================================= -->
  <div id="flowbite-alert-container" class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 w-full max-w-md"></div>

  <script src="<?= ASSETS_URL ?>js/obras/obras.js"></script>
</body>
</html>
