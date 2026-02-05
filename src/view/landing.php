<?php
// ===========================
// Datos que antes estaban en el .map()
// ===========================
$features = [
    [
        "icon" => "warehouse", // Warehouse
        "title" => "Gestión de Bodegas",
        "description" => "Organiza y controla múltiples bodegas con clasificaciones personalizadas",
    ],
    [
        "icon" => "package", // Package
        "title" => "Control de Materiales",
        "description" => "Seguimiento completo de entradas, salidas y devoluciones de materiales",
    ],
    [
        "icon" => "users", // Users
        "title" => "Roles y Permisos",
        "description" => "Sistema de roles para Coordinadores, Instructores, Pasantes y Encargados",
    ],
    [
        "icon" => "bar-chart-3", // BarChart3
        "title" => "Reportes Detallados",
        "description" => "Genera reportes de consumo por fichas, programas y períodos",
    ],
];

$stats = [
    [ "value" => "500+",  "label" => "Materiales" ],
    [ "value" => "50+",   "label" => "Fichas activas" ],
    [ "value" => "20+",   "label" => "Instructores" ],
    [ "value" => "99.9%", "label" => "Disponibilidad" ],
];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SIGA - Sistema de Gestión de Almacén</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Tailwind / estilos de tu proyecto -->
   <link rel="stylesheet" href="src/assets/css/globals.css"> 

  <!-- Lucide (iconos, reemplazo de lucide-react) -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>

</head>
<body class="min-h-screen bg-background">

  <div class="min-h-screen bg-background">
    <!-- Hero Section -->
    <header class="border-b border-border bg-card">
  <div class="container mx-auto flex h-16 items-center justify-between px-4">

    <!-- Logo + línea + texto -->
    <div class="flex items-center gap-3">

      <!-- Imagen del logo -->
      <img src="src/assets/img/logo-sena-negro.png" 
           alt="Logo SENA"
           class="h-9 w-auto object-contain">

      <!-- Línea vertical -->
      <div class="h-8 border-l border-border mx-1"></div>

      <!-- Texto del sistema -->
      <span class="text-xl font-bold">SIGA</span>
    </div>

    <!-- Botón Iniciar Sesión -->
    <div class="flex items-center gap-4">
      <a href="src/view/login/login.php"
         class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-secondary px-4 py-2 text-sm font-medium text-secondary-foreground shadow hover:bg-secondary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
        Iniciar Sesión
      </a>
    </div>

  </div>
</header>



    <main>
      <!-- Hero -->
      <section class="relative overflow-hidden py-24">
        <div class="absolute inset-0 hero-bg"></div>
        <div class="container relative mx-auto px-4 text-center">
          <div class="mx-auto max-w-3xl space-y-6">
            <div class="inline-flex items-center gap-2 rounded-full bg-badge-secondary px-3 py-1 text-xs font-medium text-badge-secondary">
            <span class="h-2 w-2 rounded-full bg-badge-secondary-dot "></span>
            Sistema de Gestión de Almacén v1.0
            <span class="h-2 w-2 rounded-full bg-badge-secondary-dot"></span>
          </div>
            <h1 class="text-4xl font-bold tracking-tight sm:text-5xl md:text-6xl text-balance">
              Control total de tu <span class="text-secondary">inventario</span> de formación
            </h1>
            <p class="text-lg text-muted-foreground text-pretty">
              Gestiona materiales, herramientas, solicitudes y evidencias de manera eficiente. Diseñado para centros
              de formación técnica y tecnológica.
            </p>
            <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
              <!-- Comenzar ahora -->
              <a href="src/view/login/login.php"
                 class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-secondary px-6 py-3 text-sm font-medium text-primary-foreground shadow hover:bg-secondary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 gap-2">
                Comenzar ahora
                <i data-lucide="arrow-right" class="h-4 w-4"></i>
              </a>
            </div>
          </div>
        </div>
      </section>

      <!-- Features -->
      <section class="border-t border-border bg-card py-20">
  <div class="container mx-auto px-4">
    <div class="mb-12 text-center">
      <h2 class="text-3xl font-bold">Características principales</h2>
      <p class="mt-2 text-muted-foreground">
        Todo lo que necesitas para gestionar tu inventario de formación
      </p>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
      <?php foreach ($features as $index => $feature): ?>
        <div
          class="group rounded-xl border border-border bg-background p-6 transition-all hover:border-primary/50 hover:shadow-lg">

          <!-- Ícono dentro del cuadrito verde secundario al 13% -->
          <div class="mb-4 inline-flex rounded-lg bg-secondary-13 p-3 text-secondary 
                      group-hover:bg-secondary group-hover:text-secondary-foreground transition-colors">
            <i data-lucide="<?php echo htmlspecialchars($feature['icon']); ?>" class="h-6 w-6"></i>
          </div>

          <h3 class="mb-2 font-semibold">
            <?php echo htmlspecialchars($feature['title']); ?>
          </h3>

          <p class="text-sm text-muted-foreground">
            <?php echo htmlspecialchars($feature['description']); ?>
          </p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>


      <!-- Stats -->
      <section class="py-20 hero-bg">
        <div class="container mx-auto px-4">
          <div class="grid gap-8 md:grid-cols-4">
            <?php foreach ($stats as $index => $stat): ?>
              <div class="text-center">
                <p class="text-4xl font-bold text-secondary">
                  <?php echo htmlspecialchars($stat['value']); ?>
                </p>
                <p class="mt-1 text-muted-foreground">
                  <?php echo htmlspecialchars($stat['label']); ?>
                </p>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer class="border-t border-border bg-card py-8">
      <div class="container mx-auto px-4 text-center text-sm text-muted-foreground">
        <p>© 2025 SIGA - Sistema de Gestión de Almacén. Todos los derechos reservados.</p>
      </div>
    </footer>

    <?php include "src/includes/footer.php"; ?>
  </div>

  <script>
    // Inicializar iconos lucide
    if (window.lucide) {
      lucide.createIcons();
    }
  </script>
</body>
</html>
