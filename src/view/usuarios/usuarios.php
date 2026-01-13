<?php
// =====================================
// USER MANAGEMENT – PHP VIEW
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
  <meta charset="UTF-8">
  <title>Gestión de Usuarios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Flowbite components (optional usage in this view) -->
  <script src="https://unpkg.com/flowbite@2.5.1/dist/flowbite.min.js"></script>

  <!-- Custom styles for the Users management module -->
  <link rel="stylesheet" href="src/assets/css/usuarios/usuarios.css" />

  <!-- Expose authenticated user ID to JavaScript logic -->
  <script>
    const AUTH_USER_ID = <?= (int)($_SESSION['usuario_id'] ?? 0); ?>;
  </script>
</head>

<body class="bg-background p-6">
  <!-- Global dashboard header/sidebar can be included here if available -->
  <!-- <?php include '../partials/dashboard-header.php'; ?> -->

  <main class="p-6 transition-all duration-300"
      style="margin-left: <?= $sidebarWidth ?>;">
    <div class="space-y-6 animate-fade-in-up">
      <!-- ================================== -->
      <!-- PAGE HEADER                         -->
      <!-- ================================== -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold tracking-tight">Gestión de Usuarios</h1>
          <p class="text-muted-foreground">
            Administra los usuarios y sus roles en el sistema
          </p>
        </div>

        <!-- Right-side controls: view switch and "New User" button -->
        <div class="flex items-center gap-3">
          <!-- View switch: table / cards -->
          <div class="inline-flex rounded-lg border border-border bg-card shadow-sm overflow-hidden">
            <!-- Table view button -->
            <button
              type="button"
              id="btnVistaTabla"
              class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 bg-muted text-foreground "
            >
              <!-- List icon -->
              <svg
                class="h-4 w-4"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M4 6h16M4 12h16M4 18h16"
                />
              </svg>
            </button>

            <!-- Cards view button -->
            <button
              type="button"
              id="btnVistaTarjetas"
              class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 text-muted-foreground"
            >
              <!-- Grid icon -->
              <svg
                class="h-4 w-4"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8"
              >
                <rect x="4" y="4" width="7" height="7" rx="1"></rect>
                <rect x="13" y="4" width="7" height="7" rx="1"></rect>
                <rect x="4" y="13" width="7" height="7" rx="1"></rect>
                <rect x="13" y="13" width="7" height="7" rx="1"></rect>
              </svg>
            </button>
          </div>

          <!-- "New User" primary action -->
          <button
            id="btnNuevoUsuario"
            class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2"
            type="button"
          >
            <!-- Plus icon -->
            <svg
              class="h-4 w-4"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4v16m8-8H4"
              />
            </svg>
            Nuevo Usuario
          </button>
        </div>
      </div>

      <!-- ================================== -->
      <!-- TOP FILTERS (SEARCH + ROLE FILTER) -->
      <!-- ================================== -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between my-6">

        <!-- SEARCH -->
        <div class="relative w-full sm:max-w-xs">
          <!-- Lupa dentro -->
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
            id="inputBuscar"
            type="text"
            placeholder="Buscar por nombre..."
            class="w-full rounded-md border border-input bg-background pl-9 pr-3 py-2 text-sm"
          />
        </div>

        <!-- ROLE FILTER -->
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
            id="selectFiltroRol"
            class="rounded-md border border-input bg-background px-3 pr-10 py-2 text-sm"
          >
            <option value="">Todos</option>
            <option value="Coordinador">Coordinador</option>
            <option value="Subcoordinador">Subcoordinador</option>
            <option value="Instructor">Instructor</option>
            <option value="Pasante">Pasante</option>
            <option value="Aprendiz">Aprendiz</option>
          </select>
        </div>

      </div>

      <!-- ================================== -->
      <!-- TABLE VIEW CONTAINER               -->
      <!-- ================================== -->
      <!-- ✅ FIX PRO: dropdown sin recorte + esquinas perfectas -->
      <div
        id="vistaTabla"
        class="relative rounded-xl border border-border bg-card p-[1px] overflow-visible"
      >
        <table class="min-w-full border-separate border-spacing-0 text-sm rounded-[11px] bg-card">
          <thead>
            <tr>
              <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground bg-gray-100 first:rounded-tl-[11px]">
                Usuario
              </th>
              <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground bg-gray-100">
                Documento
              </th>
              <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground bg-gray-100">
                Rol
              </th>
              <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground bg-gray-100">
                Teléfono
              </th>
              <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground bg-gray-100">
                Estado
              </th>
              <th class="px-4 py-3 text-right font-medium text-xs text-muted-foreground bg-gray-100 last:rounded-tr-[11px]">
                Acciones
              </th>
            </tr>
          </thead>

          <tbody
            id="tbodyUsuarios"
            class="divide-y divide-border bg-card
                   [&>tr>td]:bg-card
                   [&>tr:last-child>td:first-child]:rounded-bl-[11px]
                   [&>tr:last-child>td:last-child]:rounded-br-[11px]"
          >
            <!-- Rows are rendered dynamically via JavaScript -->
          </tbody>
        </table>
      </div>

      <!-- ================================== -->
      <!-- CARDS VIEW CONTAINER               -->
      <!-- ================================== -->
      <div id="vistaTarjetas" class="hidden">
        <div
          id="cardsContainer"
          class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
        >
          <!-- Cards are rendered dynamically via JavaScript -->
        </div>
      </div>
    </div>
  </main>

  <!-- ========================================= -->
  <!-- MODAL: CREATE / EDIT USER                 -->
  <!-- ========================================= -->
  <div id="modalUsuario" class="modal-overlay">
    <div class="relative w-full max-w-2xl rounded-xl border border-border bg-card p-6 shadow-lg">
      <!-- Modal header -->
      <div class="flex items-start justify-between gap-4 mb-4">
        <div>
          <h2 id="modalUsuarioTitulo" class="text-lg font-semibold">
            Crear Nuevo Usuario
          </h2>
          <p id="modalUsuarioDescripcion" class="text-sm text-muted-foreground">
            Complete los datos para registrar un nuevo usuario
          </p>
        </div>
        <!-- Close button -->
        <button
          type="button"
          id="btnCerrarModalUsuario"
          class="rounded-full p-1 hover:bg-muted"
        >
          <span class="sr-only">Cerrar</span>
          <svg
            class="h-5 w-5"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>
      </div>

      <!-- Modal form body -->
      <form id="formUsuario" class="space-y-4" novalidate>
        <!-- Hidden field used to distinguish between create and edit -->
        <input type="hidden" id="hiddenUserId" value="">

        <div class="grid gap-4 sm:grid-cols-2">
          <!-- Full name (full width) -->
          <div class="space-y-2 sm:col-span-2">
            <label
              for="nombre_completo"
              class="text-sm font-medium"
            >
              Nombre completo *
            </label>
            <input
              id="nombre_completo"
              type="text"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga"
              placeholder="Ej: Juan Pablo Hernández Castro"
            />
          </div>

          <!-- Document type -->
          <div class="space-y-2">
            <label
              for="tipo_documento"
              class="text-sm font-medium"
            >
              Tipo de documento *
            </label>
            <select
              id="tipo_documento"
              class="w-full rounded-md border border-input bg-background px-3 pr-10 py-2 text-sm input-siga"
            >
              <!-- Document types must match database values -->
              <option value="CC">CC</option>
              <option value="TI">TI</option>
              <option value="CE">CE</option>
            </select>
          </div>

          <!-- Document number -->
          <div class="space-y-2">
            <label
              for="numero_documento"
              class="text-sm font-medium"
            >
              Número de documento *
            </label>
            <input
              id="numero_documento"
              type="text"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga"
              placeholder="1098765432"
            />
          </div>

          <!-- Phone number -->
          <div class="space-y-2">
            <label
              for="telefono"
              class="text-sm font-medium"
            >
              Teléfono *
            </label>
            <input
              id="telefono"
              type="text"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga"
              placeholder="3101234567"
            />
          </div>

          <!-- Role (position) -->
          <div class="space-y-2">
            <label
              for="cargo"
              class="text-sm font-medium"
            >
              Cargo / Rol *
            </label>
            <select
              id="cargo"
              class="w-full rounded-md border border-input bg-background px-3 pr-10 py-2 text-sm input-siga"
            >
              <!-- Role values must be consistent with the database -->
              <option value="Coordinador">Coordinador</option>
              <option value="Subcoordinador">Subcoordinador</option>
              <option value="Instructor">Instructor</option>
              <option value="Pasante">Pasante</option>
              <option value="Aprendiz">Aprendiz</option>
            </select>
          </div>

          <!-- Training program (only visible for specific roles, e.g. Instructor) -->
          <div class="space-y-2 sm:col-span-2 hidden" id="wrapper_programa">
            <label
              for="id_programa"
              class="text-sm font-medium"
            >
              Programa de formación *
            </label>
            <select
              id="id_programa"
              class="w-full rounded-md border border-input bg-background px-3 pr-10 py-2 text-sm input-siga"
            >
              <option value="">Seleccione un programa</option>
            </select>
          </div>

          <!-- Email (full width) -->
          <div class="space-y-2 sm:col-span-2">
            <label
              for="correo"
              class="text-sm font-medium"
            >
              Correo electrónico *
            </label>
            <input
              id="correo"
              type="email"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga"
              placeholder="usuario@sena.edu.co"
            />
          </div>

          <!-- Password (full width) -->
          <div class="space-y-2 sm:col-span-2">
            <label for="password" class="text-sm font-medium">
              Contraseña *
            </label>

            <div class="relative">
              <input
                id="password"
                type="password"
                readonly
                class="w-full rounded-md border border-input bg-background px-3 py-2 pr-10 text-sm input-siga"
                placeholder="Ingrese una contraseña segura"
              />

              <!-- Ojito -->
              <button
                id="btnTogglePassword"
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted"
                title="Ver contraseña"
                aria-label="Ver contraseña"
              >
                <!-- eye -->
                <svg id="iconEye" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                </svg>

                <!-- eye-off -->
                <svg id="iconEyeOff" class="h-4 w-4 hidden" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 3l18 18"/>
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M10.5 10.677A2.5 2.5 0 0 0 13.323 13.5"/>
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M7.362 7.561C5.274 8.74 3.772 10.6 3 12c1.274 4.057 5.064 7 9.542 7 1.46 0 2.85-.313 4.107-.88"/>
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9.88 5.12A9.67 9.67 0 0 1 12 5c4.478 0 8.268 2.943 9.542 7-.448 1.427-1.23 2.72-2.25 3.77"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Address (full width) -->
          <div class="space-y-2 sm:col-span-2">
            <label
              for="direccion"
              class="text-sm font-medium"
            >
              Dirección *
            </label>
            <input
              id="direccion"
              type="text"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga"
              placeholder="Calle 45 #23-10, Bogotá"
            />
          </div>
        </div>

        <!-- Modal footer actions -->
        <div class="flex justify-end gap-2 pt-4">
          <button
            type="button"
            id="btnCancelarModalUsuario"
            class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-muted"
          >
            Cancelar
          </button>
          <button
            type="submit"
            class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:opacity-90"
          >
            Guardar
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ========================================= -->
  <!-- MODAL: VIEW USER DETAILS                  -->
  <!-- ========================================= -->
  <div id="modalVerUsuario" class="modal-overlay">
    <div class="relative w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-lg">
      <!-- Modal header -->
      <div class="flex items-start justify-between gap-4 mb-4">
        <h2 class="text-lg font-semibold">Detalles del Usuario</h2>
        <!-- Close button -->
        <button
          type="button"
          id="btnCerrarModalVerUsuario"
          class="rounded-full p-1 hover:bg-muted"
        >
          <span class="sr-only">Cerrar</span>
          <svg
            class="h-5 w-5"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>
      </div>

      <!-- User details content, populated dynamically via JavaScript -->
      <div id="detalleUsuarioContent" class="space-y-4">
        <!-- Filled when a user is selected -->
      </div>
    </div>
  </div>

  <!-- ========================================= -->
  <!-- ALERT CONTAINER (FLOWBITE-LIKE TOASTS)    -->
  <!-- ========================================= -->
  <div
    id="flowbite-alert-container"
    class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 w-full max-w-md"
  ></div>

  <!-- ========================================= -->
  <!-- MODULE SCRIPT: USERS MANAGEMENT LOGIC     -->
  <!-- ========================================= -->
  <script src="src/assets/js/usuarios/usuarios.js"></script>

</body>
</html>
