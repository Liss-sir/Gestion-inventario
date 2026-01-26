<?php
// =====================================
// WORKS AND ACTIVITIES – PHP VIEW
// (Adapted to "Users" layout: collapsible sidebar + main with margin-left)
// =====================================

// REQUIRED: to be able to use $_SESSION without warnings
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// ✅ FIX PERMISOS (SIN ROMPER TU BASE)
require_once __DIR__ . "/../../utils/permisos_helper.php";

// ✅ PROTECCIÓN: Si NO puede listar obras, NO entra
requirePermiso("obras.listar");

// ✅ Flags de acciones (Aprendiz NO tendrá estos permisos)
$canCrearObra        = canPermiso("obras.crear") || canPermiso("obras.gestionar");
$canEditarObra       = canPermiso("obras.editar") || canPermiso("obras.gestionar");
$canCambiarEstadoObra = canPermiso("obras.activar_desactivar") || canPermiso("obras.gestionar");

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

  <!-- Flowbite (optional, but in Users you include it and your toasts are "flowbite-like") -->
  <script src="https://unpkg.com/flowbite@2.5.1/dist/flowbite.min.js"></script>

  <!-- Module styles -->
  <link rel="stylesheet" href="src/assets/css/obras/obras.css" />

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-background p-6">

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
      <!-- STATISTICS                         -->
      <!-- ================================== -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Total Works -->
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

        <!-- Active Works -->
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

        <!-- Completed Works -->
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
      <!-- WORKS LIST                         -->
      <!-- ================================== -->
      <div class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">

        <div class="p-6 border-b border-border">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-xl font-semibold text-foreground">Lista de Obras</h2>
              <p class="text-sm text-muted-foreground mt-1">Administra las obras y actividades formativas</p>
            </div>

            <!-- ✅ SI ES APRENDIZ: NO VE "NUEVA OBRA" -->
            <?php if ($canCrearObra): ?>
              <button
                onclick="openCreateModal()"
                class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2"
              >
                <i class="fas fa-plus"></i>
                Nueva Obra
              </button>
            <?php endif; ?>
          </div>

          <!-- Search bar (Users-style) -->
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

        <!-- Works container -->
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
  <!-- NEW WORK MODAL                             -->
  <!-- ========================================= -->
  <?php if ($canCrearObra): ?>
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

        <div id="containerAprendizIndividual" class="hidden">
          <label class="block text-xs text-muted-foreground mb-1">Aprendiz Asignado *</label>
          <select id="create_aprendiz_individual" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga">
            <option value="" disabled selected>Cargando aprendices...</option>
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
  <?php endif; ?>

  <!-- ========================================= -->
  <!-- EDIT WORK MODAL                            -->
  <!-- ========================================= -->
  <?php if ($canEditarObra): ?>
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
  <?php endif; ?>

  <!-- ========================================= -->
  <!-- DETAILS MODAL                              -->
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

  <div id="modalAsignarAprendices" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[100]">
    <div class="bg-card rounded-xl border border-border shadow-xl w-[68vh] max-w-3xl max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 pb-0">
          <div class="flex flex-col items-start justify-between p-0">
              <h3 class="text-xl font-semibold text-foreground">Asignar Aprendices</h3>
              <p class="text-sm text-muted-foreground js-descripcion opacity-75">Selecciona los aprendices que participarán en esta obra grupal</p>
          </div>
          <button onclick="closeAsignarModal()" class="text-muted-foreground hover:opacity-80">
              <i class="fas fa-times text-xl"></i>
          </button>
      </div>

      <div class="p-6 space-y-4">
          <!-- Information about the created work -->
          <div class="bg-[#002f4d34] border border-[#00304D] rounded-lg p-4 mb-4">
              <h4 class="font-semibold text-[#00304D] mb-2">Obra Creada:</h4>
              <p id="infoObraCreada" class="text-sm text-[#00304D]"></p>
          </div>

          <!-- Learner search -->
          <div>
              <label class="block text-xs text-muted-foreground mb-1">Buscar Aprendices</label>
              <div class="relative">
                  <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
                  <input
                      type="text"
                      id="searchAprendiz"
                      placeholder="Buscar por nombre o documento..."
                      class="w-full rounded-md border border-input bg-background pl-9 pr-3 py-2 text-sm"
                      onkeyup="filtrarAprendices()"
                  />
              </div>
          </div>

          <!-- Learner select -->
          <div>
              <label class="block text-xs text-muted-foreground mb-1">Seleccionar Aprendiz *</label>
              <select 
                  id="selectAprendiz"
                  class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  onchange="agregarAprendizSeleccionado()"
              >
                  <option value="" selected disabled>Cargando aprendices...</option>
              </select>
          </div>

          <!-- List of selected learners -->
          <div class="mt-6">
              <h4 class="text-sm font-medium text-foreground mb-3">Aprendices Seleccionados</h4>
              <div id="listaAprendicesSeleccionados" class="space-y-2 min-h-[100px] border border-border rounded-lg p-4">
                  <p class="text-sm text-muted-foreground text-center py-4">No hay aprendices seleccionados</p>
              </div>
          </div>

          <div class="flex gap-3 pt-4 justify-end">
              <button type="button" onclick="closeAsignarModal()" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                  Cancelar
              </button>
              <button
                  type="button"
                  onclick="finalizarCreacionGrupal()"
                  class="inline-flex items-center justify-center rounded-md bg-secondary px-10 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2 transition-colors"
                  id="btnFinalizarGrupal"
              >
                  <span id="btnFinalizarText">Finalizar</span>
                  <span id="btnFinalizarLoading" class="hidden">
                      <i class="fas fa-spinner fa-spin"></i>
                  </span>
              </button>
          </div>
      </div>
    </div>
  </div>

<!-- ========================================= -->
<!-- SELECT LEARNER MODAL (INDIVIDUAL)          -->
<!-- ========================================= -->
<div id="modalSeleccionarAprendiz" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[100]">
    <div class="bg-card rounded-xl border border-border shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-6 pb-0">
            <div class="flex flex-col items-start justify-between p-0">
                <h3 class="text-xl font-semibold text-foreground">Seleccionar Aprendiz</h3>
                <p class="text-sm text-muted-foreground opacity-75">Selecciona el aprendiz que realizará esta obra individual</p>
            </div>
            <button onclick="closeSeleccionarModal()" class="text-muted-foreground hover:opacity-80">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="p-6 space-y-4">
            <div>
                <label class="block text-xs text-muted-foreground mb-1">Aprendiz *</label>
                <select 
                    id="selectAprendizIndividual"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="" selected disabled>Cargando aprendices...</option>
                </select>
            </div>

            <div class="flex gap-3 pt-4 justify-end">
                <button type="button" onclick="closeSeleccionarModal()" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                    Cancelar
                </button>
                <button
                    type="button"
                    onclick="finalizarCreacionIndividual()"
                    class="inline-flex items-center justify-center rounded-md bg-secondary px-10 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2 transition-colors"
                >
                    Finalizar
                </button>
            </div>
        </div>
    </div>
</div>

  <!-- ========================================= -->
  <!-- ALERT CONTAINER (FLOWBITE-LIKE TOASTS)    -->
  <!-- ========================================= -->
  <div id="flowbite-alert-container" class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 w-full max-w-md"></div>

  <!-- ✅ PASAMOS PERMISOS A JS (IMPORTANTÍSIMO) -->
  <script>
    window.OBRAS_PERMS = {
      canCrear: <?= json_encode($canCrearObra) ?>,
      canEditar: <?= json_encode($canEditarObra) ?>,
      canCambiarEstado: <?= json_encode($canCambiarEstadoObra) ?>,
    };
  </script>

  <script src="<?= ASSETS_URL ?>js/obras/obras.js"></script>
</body>
</html>