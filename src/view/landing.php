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

  <style>
    @keyframes teamFloat {
      0%,
      100% {
        transform: translateY(0);
      }
      50% {
        transform: translateY(-8px);
      }
    }

    @keyframes teamShine {
      0% {
        transform: translateX(-140%) rotate(20deg);
      }
      100% {
        transform: translateX(220%) rotate(20deg);
      }
    }

    .team-member {
      animation: teamFloat 4.5s ease-in-out infinite;
      animation-delay: var(--delay, 0s);
      transition: transform 0.35s ease;
    }

    .team-photo {
      position: relative;
      border: 3px solid #39A90066;
      box-shadow: 0 10px 22px rgba(15, 23, 42, 0.2), 0 0 0 0 #00783266;
      transition: transform 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease;
    }

    .team-member:nth-child(even) .team-photo {
      border-color: #00783266;
      box-shadow: 0 10px 22px rgba(15, 23, 42, 0.2), 0 0 0 0 #39A90066;
    }

    .team-photo::after {
      content: "";
      position: absolute;
      top: -40%;
      left: -70%;
      width: 40%;
      height: 180%;
      opacity: 0.7;
      pointer-events: none;
      background: linear-gradient(
        120deg,
        transparent 0%,
        rgba(255, 255, 255, 0.25) 45%,
        rgba(255, 255, 255, 0.75) 50%,
        rgba(255, 255, 255, 0.25) 55%,
        transparent 100%
      );
      transform: translateX(-140%) rotate(20deg);
    }

    .team-photo img {
      transition: transform 0.35s ease, filter 0.35s ease;
    }

    .team-member:hover {
      transform: translateY(-6px);
    }

    .team-member:hover .team-photo {
      transform: scale(1.06) rotate(-2deg);
      border-color: #007832;
      box-shadow: 0 18px 34px rgba(15, 23, 42, 0.3), 0 0 0 8px #39A9004D;
    }

    .team-member:nth-child(even):hover .team-photo {
      border-color: #39A900;
      box-shadow: 0 18px 34px rgba(15, 23, 42, 0.3), 0 0 0 8px #0078324D;
    }

    .team-member:hover .team-photo::after {
      animation: teamShine 0.85s ease;
    }

    .team-member:hover .team-photo img {
      transform: scale(1.12);
      filter: saturate(1.15) contrast(1.05);
    }

    @media (prefers-reduced-motion: reduce) {
      .team-member,
      .team-member:hover .team-photo::after {
        animation: none;
      }

      .team-member,
      .team-photo,
      .team-photo img {
        transition: none;
      }
    }
  </style>

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

<!-- Nuestro equipo -->
<section class="border-t border-border bg-card py-20">
  <div class="container mx-auto px-4">
    <div class="mb-12 text-center">
      <h2 class="text-3xl font-bold">Nuestro Equipo</h2>
      <p class="mt-2 text-muted-foreground">
        Las personas detrás del Sistema de Gestión de Almacén
      </p>
    </div>

    <div class="grid grid-cols-5 grid-rows-2 gap-x-6 gap-y-7 text-center mx-auto max-w-6xl">
      <div class="team-member p-4" style="--delay: 0s;">
        <div class="team-photo h-32 w-32 mx-auto overflow-hidden rounded-full bg-gray-300">
          <img src="src/assets/img/Jhonatan.jpeg" alt="Jhonatan" class="h-full w-full rounded-full object-cover">
        </div>
          <h2 class="mt-3 whitespace-nowrap font-semibold text-sm">Jhonatan Stiven Acevedo</h2>
          <h3 class="text-xs font-light">Líder de Proyecto</h3>
      </div>

      <div class="team-member p-4" style="--delay: 0.08s;">
        <div class="team-photo h-32 w-32 mx-auto overflow-hidden rounded-full bg-gray-300">
          <img src="src/assets/img/Isaac.jpeg" alt="Isaac" class="h-full w-full rounded-full object-cover">
        </div>
          <h2 class="mt-3 whitespace-nowrap font-semibold text-sm">Isaac Echeverry</h2>
          <h3 class="text-xs font-light">Diseñador y Desarrollador Frontend</h3>
      </div>

      <div class="team-member p-4" style="--delay: 0.16s;">
        <div class="team-photo h-32 w-32 mx-auto overflow-hidden rounded-full bg-gray-300">
          <img src="src/assets/img/Catalina.jpeg" alt="Laura" class="h-full w-full rounded-full object-cover">
        </div>
          <h2 class="mt-3 whitespace-nowrap font-semibold text-sm">Laura Catalina Rubio</h2>
          <h3 class="text-xs font-light">Diseñadora y Desarrolladora Frontend</h3>
      </div>

      <div class="team-member p-4" style="--delay: 0.24s;">
        <div class="team-photo h-32 w-32 mx-auto overflow-hidden rounded-full bg-gray-300">
          <img src="src/assets/img/Juan_Esteban.jpeg" alt="Juan Esteban" class="h-full w-full rounded-full object-cover">
        </div>
          <h2 class="mt-3 whitespace-nowrap font-semibold text-sm">Juan Esteban Soto</h2>
          <h3 class="text-xs font-light">Diseñador y Desarrollador Frontend</h3>
      </div>

      <div class="team-member p-4" style="--delay: 0.32s;">
        <div class="team-photo h-32 w-32 mx-auto overflow-hidden rounded-full bg-gray-300">
          <img src="src/assets/img/Juan_Jose.jpeg" alt="Juan José" class="h-full w-full rounded-full object-cover">
        </div>
          <h2 class="mt-3 whitespace-nowrap font-semibold text-sm">Juan José Candamil</h2>
          <h3 class="text-xs font-light">Administrador Base de Datos</h3>
      </div>

      <div class="team-member p-4" style="--delay: 0.4s;">
        <div class="team-photo h-32 w-32 mx-auto overflow-hidden rounded-full bg-gray-300">
          <img src="src/assets/img/Julian.jpeg" alt="Julián" class="h-full w-full rounded-full object-cover">
        </div>
          <h2 class="mt-3 whitespace-nowrap font-semibold text-sm">Julián Osorio</h2>
          <h3 class="text-xs font-light">Diseñador y Desarrollador Frontend</h3>
      </div>

      <div class="team-member p-4" style="--delay: 0.48s;">
        <div class="team-photo h-32 w-32 mx-auto overflow-hidden rounded-full bg-gray-300">
          <img src="src/assets/img/Kevin.jpeg" alt="Kevin" class="h-full w-full rounded-full object-cover">
        </div>
          <h2 class="mt-3 whitespace-nowrap font-semibold text-sm">Kevin Andrés Duarte</h2>
          <h3 class="text-xs font-light">Diseñador y Desarrollador Frontend</h3>
      </div>

      <div class="team-member p-4" style="--delay: 0.56s;">
        <div class="team-photo h-32 w-32 mx-auto overflow-hidden rounded-full bg-gray-300">
          <img src="src/assets/img/Kevin_Leandro.jpeg" alt="Kevin Leandro" class="h-full w-full rounded-full object-cover">
        </div>
          <h2 class="mt-3 whitespace-nowrap font-semibold text-sm">Kevin Leandro Muñoz</h2>
          <h3 class="text-xs font-light">Desarrollador Backend y Tester</h3>
      </div>

      <div class="team-member p-4" style="--delay: 0.64s;">
        <div class="team-photo h-32 w-32 mx-auto overflow-hidden rounded-full bg-gray-300">
          <img src="src/assets/img/Luis.jpeg" alt="Luis" class="h-full w-full rounded-full object-cover">
        </div>
          <h2 class="mt-3 whitespace-nowrap font-semibold text-sm">Luis Carlos Hernández</h2>
          <h3 class="text-xs font-light">Diseñador y Desarrollador Frontend</h3>
      </div>

      <div class="team-member p-4" style="--delay: 0.72s;">
        <div class="team-photo h-32 w-32 mx-auto overflow-hidden rounded-full bg-gray-300">
          <img src="src/assets/img/Samuel.jpeg" alt="Samuel" class="h-full w-full rounded-full object-cover">
        </div>
          <h2 class="mt-3 whitespace-nowrap font-semibold text-sm">Samuel Monsalve</h2>
          <h3 class="text-xs font-light">Desarrollador Backend</h3>
      </div>
    </div>

  </div>

</section>

    </main>

    <!-- Footer -->
    <footer class="border-t border-border bg-card py-8">
      <div class="container mx-auto px-4 text-center text-sm text-muted-foreground">
        <p>© 2026 SIGA - Sistema de Gestión de Almacén. Todos los derechos reservados.</p>
      </div>
    </footer>

  </div>

  <script>
    // Inicializar iconos lucide
    if (window.lucide) {
      lucide.createIcons();
    }
  </script>
</body>
</html>
