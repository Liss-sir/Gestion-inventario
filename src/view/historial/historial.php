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

  <script src="script.js" defer></script>

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

        <div class="mt-6 space-y-6">
          <!-- ITEM 1 -->
          <div class="timeline-item group relative flex gap-4" data-modulo="Movimientos" data-accion="Entrada">
            <div class="relative w-11 shrink-0">
              <div
                class="absolute left-[22px] top-[36px] bottom-[-24px] w-px group-last:hidden"
                style="background-color: var(--border);"
              ></div>

              <div class="absolute left-[4px] top-0 z-10 flex h-9 w-9 items-center justify-center rounded-full border border-border bg-background text-muted-foreground">
                <i data-lucide="history" class="h-5 w-5"></i>
              </div>
            </div>

            <div class="w-full rounded-xl border border-border bg-card p-6 shadow-sm">
              <div class="flex flex-wrap items-center gap-2">
                <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
                  style="background-color: color-mix(in srgb, var(--chart-5) 14%, white); color: var(--chart-5);"
                >
                  Entrada
                </span>
                <span class="inline-flex items-center rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                  Movimientos
                </span>
              </div>

              <p class="mt-3 text-base font-semibold text-card-foreground">
                Registró entrada de 50 bolsas de Cemento Gris
              </p>

              <p class="mt-2 text-sm text-muted-foreground">
                Bodega: <span class="text-card-foreground/80">Bodega Construcción</span>
              </p>

              <div class="mt-4 flex flex-wrap items-center gap-5 text-xs text-muted-foreground">
                <span class="inline-flex items-center gap-2">
                  <i data-lucide="user" class="h-4 w-4"></i>
                  María Elena Rodríguez Gómez <span class="text-muted-foreground">(Encargado de Inventario)</span>
                </span>

                <span class="inline-flex items-center gap-2">
                  <i data-lucide="calendar" class="h-4 w-4"></i>
                  17 de noviembre, 2024
                </span>

                <span class="inline-flex items-center gap-2">
                  <i data-lucide="clock" class="h-4 w-4"></i>
                  10:00
                </span>
              </div>
            </div>
          </div>

          <!-- ITEM 2 -->
          <div class="timeline-item group relative flex gap-4" data-modulo="Movimientos" data-accion="Salida">
            <div class="relative w-11 shrink-0">
              <div
                class="absolute left-[22px] top-[36px] bottom-[-24px] w-px group-last:hidden"
                style="background-color: var(--border);"
              ></div>

              <div class="absolute left-[4px] top-0 z-10 flex h-9 w-9 items-center justify-center rounded-full border border-border bg-background text-muted-foreground">
                <i data-lucide="history" class="h-5 w-5"></i>
              </div>
            </div>

            <div class="w-full rounded-xl border border-border bg-card p-6 shadow-sm">
              <div class="flex flex-wrap items-center gap-2">
                <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
                  style="background-color: color-mix(in srgb, var(--chart-5) 14%, white); color: var(--chart-5);"
                >
                  Salida
                </span>
                <span class="inline-flex items-center rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                  Movimientos
                </span>
              </div>

              <p class="mt-3 text-base font-semibold text-card-foreground">
                Registró salida de 10 bolsas de Cemento Gris para Ficha 2567890
              </p>

              <p class="mt-2 text-sm text-muted-foreground">
                Instructor: <span class="text-card-foreground/80">Juan Pablo Hernández Castro</span>
              </p>

              <div class="mt-4 flex flex-wrap items-center gap-5 text-xs text-muted-foreground">
                <span class="inline-flex items-center gap-2">
                  <i data-lucide="user" class="h-4 w-4"></i>
                  Diego Fernando Torres Ríos <span class="text-muted-foreground">(Encargado de Bodega)</span>
                </span>

                <span class="inline-flex items-center gap-2">
                  <i data-lucide="calendar" class="h-4 w-4"></i>
                  19 de noviembre, 2024
                </span>

                <span class="inline-flex items-center gap-2">
                  <i data-lucide="clock" class="h-4 w-4"></i>
                  08:30
                </span>
              </div>
            </div>
          </div>

          <!-- ITEM 3 -->
          <div class="timeline-item group relative flex gap-4" data-modulo="Movimientos" data-accion="Salida">
            <div class="relative w-11 shrink-0">
              <div
                class="absolute left-[22px] top-[36px] bottom-[-24px] w-px group-last:hidden"
                style="background-color: var(--border);"
              ></div>

              <div class="absolute left-[4px] top-0 z-10 flex h-9 w-9 items-center justify-center rounded-full border border-border bg-background text-muted-foreground">
                <i data-lucide="history" class="h-5 w-5"></i>
              </div>
            </div>

            <div class="w-full rounded-xl border border-border bg-card p-6 shadow-sm">
              <div class="flex flex-wrap items-center gap-2">
                <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
                  style="background-color: color-mix(in srgb, var(--chart-5) 14%, white); color: var(--chart-5);"
                >
                  Salida
                </span>
                <span class="inline-flex items-center rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                  Movimientos
                </span>
              </div>

              <p class="mt-3 text-base font-semibold text-card-foreground">
                Registró salida de 2 m3 de Arena de Río para Ficha 2567890
              </p>

              <p class="mt-2 text-sm text-muted-foreground">
                Instructor: <span class="text-card-foreground/80">Juan Pablo Hernández Castro</span>
              </p>

              <div class="mt-4 flex flex-wrap items-center gap-5 text-xs text-muted-foreground">
                <span class="inline-flex items-center gap-2">
                  <i data-lucide="user" class="h-4 w-4"></i>
                  Diego Fernando Torres Ríos <span class="text-muted-foreground">(Encargado de Bodega)</span>
                </span>

                <span class="inline-flex items-center gap-2">
                  <i data-lucide="calendar" class="h-4 w-4"></i>
                  19 de noviembre, 2024
                </span>

                <span class="inline-flex items-center gap-2">
                  <i data-lucide="clock" class="h-4 w-4"></i>
                  08:35
                </span>
              </div>
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
