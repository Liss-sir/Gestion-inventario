<?php
// ✅ Detectar estado del sidebar desde la URL (?coll=1)
$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
?>
<!doctype html>
<html lang="es" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <title>Historial de Actividad</title>

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- ✅ Tus colores institucionales -->
  <link rel="stylesheet" href="src/assets/css/globals.css">

  <!-- Lucide icons CDN -->
  <script src="https://unpkg.com/lucide@latest"></script>


  <!-- ✅ NO TOCA TU BASE: solo empuja el contenido para que no lo tape el sidebar -->
  <style>
    :root{
      --sidebar-expanded: 260px;
      --sidebar-collapsed: 70px;
    }

    .page-content-with-sidebar{
      padding-left: <?= $collapsed ? 'var(--sidebar-collapsed)' : 'var(--sidebar-expanded)' ?>;
      transition: padding-left 300ms ease;
      width: 100%;
      min-height: 100vh;
    }

    /* En mobile normalmente el sidebar es overlay, entonces no empujamos */
    @media (max-width: 1024px){
      .page-content-with-sidebar{ padding-left: 0 !important; }
    }
  </style>
</head>

<body class="bg-background text-foreground font-sans min-h-screen">

  <!-- ✅ WRAPPER (no modifica tu base interna) -->
  <main class="page-content-with-sidebar">

    <!-- ✅ TU CÓDIGO BASE TAL CUAL -->
    <div class="w-full px-6 py-8 space-y-6">
      <!-- CABECERA -->
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between md:gap-6">
        <div>
          <h1 class="text-3xl font-bold text-card-foreground">Historial de Actividad</h1>
          <p class="text-base text-muted-foreground mt-1">
            Registro completo de todas las acciones realizadas en el sistema
          </p>
        </div>

        <!-- Chip Registros -->
        <div
          class="w-full md:w-auto flex items-center gap-3 rounded-xl border border-border px-4 py-3"
          style="background-color: color-mix(in srgb, var(--primary) 12%, white);"
        >
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-card">
            <i data-lucide="history" class="h-5 w-5 text-primary"></i>
          </div>
          <div class="leading-tight">
            <p class="text-sm font-semibold text-primary">15</p>
            <p class="text-sm text-muted-foreground">Registros</p>
          </div>
        </div>
      </div>

      <!-- FILTROS -->
      <section class="bg-card rounded-xl shadow-sm border border-border p-5 w-full">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <!-- Search -->
          <div class="relative w-full lg:flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"></i>
            <input
              id="searchInput"
              type="text"
              placeholder="Buscar por usuario, descripción o entidad..."
              class="w-full rounded-lg border border-input bg-background pl-10 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            />
          </div>

          <!-- Select módulo -->
          <div class="w-full lg:w-[260px]">
            <select
              id="moduloFilter"
              class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="">Todos los módulos</option>
              <option value="Movimientos">Movimientos</option>
              <option value="Solicitudes">Solicitudes</option>
              <option value="Materiales">Materiales</option>
              <option value="Bodegas">Bodegas</option>
              <option value="Usuarios">Usuarios</option>
              <option value="Programas">Programas</option>
              <option value="Fichas">Fichas</option>
            </select>
          </div>

          <!-- Select acción -->
          <div class="w-full lg:w-[260px]">
            <select
              id="accionFilter"
              class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="">Todas las acciones</option>
              <option value="Entrada">Entrada</option>
              <option value="Salida">Salida</option>
              <option value="Creacion">Creación</option>
              <option value="Devolucion">Devolución</option>
              <option value="Aprobacion">Aprobación</option>
              <option value="Desactivacion">Desactivación</option>
              <option value="Rechazo">Rechazo</option>
              <option value="Edicion">Edición</option>
            </select>
          </div>
        </div>
      </section>

      <!-- TIMELINE -->
<section id="timeline" class="bg-card rounded-xl shadow-sm border border-border p-5 w-full">
    <div class="flex items-center gap-2">
        <i data-lucide="file-text" class="h-5 w-5 text-card-foreground"></i>
        <h2 class="text-base font-semibold text-card-foreground">Línea de Tiempo</h2>
    </div>

    <!-- ✅ CONTENEDOR DE CARGA/ERROR -->
    <div id="timeline-status" class="mt-4 hidden"></div>

    <!-- ✅ CONTENEDOR DE ITEMS -->
    <div id="timeline-items" class="mt-6 space-y-6">
        <!-- Esqueleto de carga (skeleton) -->
        <div class="flex items-center justify-center py-12">
            <div class="text-center">
                <i data-lucide="loader-circle" class="h-8 w-8 animate-spin text-primary mx-auto"></i>
                <p class="mt-2 text-sm text-muted-foreground">Cargando historial...</p>
            </div>
        </div>
    </div>
</section>
    </div>

  </main>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      if (window.lucide && typeof lucide.createIcons === "function") {
        lucide.createIcons();
      }
    });
  </script>

  <script src="src/assets/js/historial/historial.js"></script>
</body>
</html>