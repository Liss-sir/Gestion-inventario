<?php
// =====================================
// FICHAS MANAGEMENT – PHP VIEW
// =====================================

$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Fichas de Formación</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Flowbite components -->
  <script src="https://unpkg.com/flowbite@2.5.1/dist/flowbite.min.js"></script>

  <!-- Custom styles -->
  <link rel="stylesheet" href="src/assets/css/fichas/fichas.css" />

  <!-- Expose authenticated user ID -->
  <script>
    const AUTH_USER_ID = <?= $_SESSION['usuario_id'] ?? 0; ?>;
  </script>
</head>

<body class="bg-background p-6">
  <main class="p-6 transition-all duration-300"
        style="margin-left: <?= $sidebarWidth ?>;">

    <div class="space-y-6 animate-fade-in-up">

      <!-- HEADER -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold tracking-tight">Fichas de Formación</h1>
          <p class="text-muted-foreground">
            Administra las fichas y su asignación a programas e instructores
          </p>
        </div>

        <div class="flex items-center gap-3">

          <!-- View Switch -->
          <div class="inline-flex rounded-lg border border-border bg-card shadow-sm overflow-hidden">

            <!-- Table view -->
            <button id="btnVistaTabla"
              class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 bg-muted text-foreground">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
              </svg>
            </button>

            <!-- Cards view -->
            <button id="btnVistaTarjetas"
              class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 text-muted-foreground">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <rect x="4" y="4" width="7" height="7" rx="1"></rect>
                <rect x="13" y="4" width="7" height="7" rx="1"></rect>
                <rect x="4" y="13" width="7" height="7" rx="1"></rect>
                <rect x="13" y="13" width="7" height="7" rx="1"></rect>
              </svg>
            </button>

          </div>

          <!-- New ficha -->
          <button id="btnNuevaFicha"
            class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-white shadow-sm gap-2">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4v16m8-8H4" />
            </svg>
            Nueva Ficha
          </button>

        </div>
      </div>

      <!-- SEARCH + ESTADO FILTER -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <!-- Search -->
        <div class="w-full sm:max-w-xs relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-4 w-4 text-muted-foreground" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="m21 21-4.35-4.35"></path>
            </svg>
          </div>
          <input id="inputBuscar" type="text"
            placeholder="Buscar por número..."
            class="w-full rounded-md border-0 bg-gray-100 pl-10 pr-3 py-2 text-sm
                   placeholder:text-muted-foreground focus:ring-2 focus:ring-primary" />
        </div>

        <!-- Estado filter -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <!-- ... (search) ... -->
    
        <!-- State filter updated for 3 states -->
        <div class="flex items-center gap-2">
            <svg class="h-4 w-4 text-muted-foreground" fill="none" viewBox="0 0 24 24">
                <path d="M5 5h14a1 1 0 0 1 .8 1.6L15 12v4.5a1 1 0 0 1-.553.894l-3 1.5A1 1 0 0 1 10 18v-6L4.2 6.6A1 1 0 0 1 5 5z"
                      stroke="currentColor" stroke-width="1.8"
                      stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <select id="selectFiltroEstado"
                class="rounded-md border border-input bg-background px-3 pr-10 py-2 text-sm">
                <option value="">Todos</option>
                <option value="Activa">Activa</option>
                <option value="Finalizada">Finalizada</option>
                <option value="Cancelada">Cancelada</option>
            </select>
        </div>
      </div>

      </div>

      <!-- TABLE VIEW -->
      <div id="vistaTabla"
        class="rounded-xl border border-border bg-card overflow-hidden">
        <table class="min-w-full divide-y divide-border text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs text-muted-foreground">Número</th>
              <th class="px-4 py-3 text-left text-xs text-muted-foreground">Programa</th>
              <th class="px-4 py-3 text-left text-xs text-muted-foreground">Nivel</th>
              <th class="px-4 py-3 text-left text-xs text-muted-foreground">Jornada</th>
              <th class="px-4 py-3 text-left text-xs text-muted-foreground">Estado</th>
              <th class="px-4 py-3 text-right text-xs text-muted-foreground">Acciones</th>
            </tr>
          </thead>
          <tbody id="tbodyFichas" class="divide-y divide-border bg-card">
          </tbody>
        </table>
      </div>

      <!-- CARDS VIEW -->
      <div id="vistaTarjetas" class="hidden">
        <div id="cardsContainer" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"></div>
      </div>

    </div>
  </main>

  <!-- MODAL CREATE / EDIT -->
  <div id="modalFicha" class="modal-overlay">
    <div class="relative w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-lg">

      <div class="flex items-start justify-between gap-4 mb-4">
        <div>
          <h2 id="modalFichaTitulo" class="text-lg font-semibold">Crear Nueva Ficha</h2>
          <p id="modalFichaDescripcion" class="text-sm text-muted-foreground">
            Complete los datos para registrar una nueva ficha
          </p>
        </div>
        <button id="btnCerrarModalFicha" class="rounded-full p-1 hover:bg-muted">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- COMPLETED FORM -->
      <form id="formFicha" class="space-y-4" novalidate>

        <!-- id_ficha -->
        <input type="hidden" id="hiddenFichaId">
        <input type="hidden" id="id_ficha">

        <!-- Number -->
        <div class="space-y-2">
          <label for="numero_ficha" class="text-sm font-medium">Número de ficha*</label>
          <input id="numero_ficha" type="text"
                 class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga"
                 placeholder="2567890">
        </div>

        <!-- Program -->
        <div class="space-y-2">
          <label for="id_programa" class="text-sm font-medium">Programa*</label>
          <select id="id_programa"
                  class="w-full rounded-md border border-input bg-background px-3 pr-8 py-2 text-sm input-siga">
            <option value="">Seleccione</option>
          </select>
        </div>

        <!-- Day -->
        <div class="space-y-2">
          <label for="jornada" class="text-sm font-medium">Jornada*</label>
          <select id="jornada"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga">
            <option value="">Seleccione</option>
            <option value="Mañana">Mañana</option>
            <option value="Tarde">Tarde</option>
            <option value="Noche">Noche</option>
          </select>
        </div>

        <!-- Mode -->
        <div class="space-y-2">
          <label for="modalidad" class="text-sm font-medium">Modalidad*</label>
          <select id="modalidad"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga">
            <option value="">Seleccione</option>
            <option value="Presencial">Presencial</option>
            <option value="Virtual">Virtual</option>
            <option value="Mixta">Mixta</option>
          </select>
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-2">
            <label for="fecha_inicio" class="text-sm font-medium">Fecha inicio*</label>
            <input id="fecha_inicio" type="date"
                   class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga">
          </div>

          <div class="space-y-2">
            <label for="fecha_fin" class="text-sm font-medium">Fecha fin*</label>
            <input id="fecha_fin" type="date"
                   class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga">
          </div>
        </div>

        <!-- FOOTER -->
        <div class="flex justify-end gap-2 pt-4">
          <button id="btnCancelarModalFicha" type="button"
            class="inline-flex items-center justify-center rounded-md border border-input px-4 py-2 text-sm hover:bg-muted">
            Cancelar
          </button>

          <button type="submit"
            class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm text-white shadow">
            Guardar Cambios
          </button>
        </div>

      </form>

    </div>
  </div>

  <!-- MODAL SEE DETAILS -->
  <div id="modalVerFicha" class="modal-overlay">
    <div class="relative w-full max-w-md rounded-xl border border-border bg-card from-white p-6 shadow-lg">

      <div class="flex items-start justify-between mb-4">
        <h2 class="text-lg font-semibold">Detalles de la Ficha</h2>
        <button id="btnCerrarModalVerFicha" class="rounded-full p-1 hover:bg-muted">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div id="detalleFichaContent" class="space-y-4"></div>

    </div>
  </div>

  <!-- ALERT CONTAINER -->
  <div id="flowbite-alert-container"
       class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 w-full max-w-md"></div>

  <!-- JS MODULE -->
  <script src="src/assets/js/fichas/fichas.js"></script>

</body>
</html>
