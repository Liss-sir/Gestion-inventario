<?php
// Página actual según el router (?page=...)
$currentPage = $_GET['page'] ?? 'dashboard';

// Datos del menú (usamos 'page' en vez de href directo)
$navigation = [
  ["name" => "Dashboard",   "page" => "dashboard",   "icon" => "LayoutDashboard"],
  ["name" => "Usuarios",    "page" => "usuarios",    "icon" => "Users"],
  ["name" => "Bodegas",     "page" => "bodegas",     "icon" => "Warehouse"],
  ["name" => "Materiales",  "page" => "materiales",  "icon" => "Package"],
  ["name" => "Movimientos", "page" => "movimientos", "icon" => "ArrowLeftRight"],
  ["name" => "Solicitudes", "page" => "solicitudes", "icon" => "ClipboardList", "badge" => 2],
  ["name" => "Programas",   "page" => "programas",   "icon" => "GraduationCap"],
  ["name" => "Fichas",      "page" => "fichas",      "icon" => "FolderKanban"],
  ["name" => "RAEs",        "page" => "raes",        "icon" => "BookOpen"],
  ["name" => "Evidencias",  "page" => "evidencias",  "icon" => "FileText"],
  ["name" => "Reportes",    "page" => "reportes",    "icon" => "BarChart3"],
];

// Estado del sidebar
$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";

// 🔹 Mapeo de tus claves a nombres reales de Lucide
function getLucideIconName(string $key): string {
  switch ($key) {
    case 'LayoutDashboard':  return 'layout-dashboard';
    case 'Users':            return 'users-2';
    case 'Warehouse':        return 'warehouse';
    case 'Package':          return 'package';
    case 'ArrowLeftRight':   return 'arrow-left-right';
    case 'ClipboardList':    return 'clipboard-list';
    case 'GraduationCap':    return 'graduation-cap';
    case 'FolderKanban':     return 'folder-kanban';
    case 'BookOpen':         return 'book-open-text';
    case 'FileText':         return 'file-text';
    case 'BarChart3':        return 'bar-chart-3';
    default:                 return 'circle-help';
  }
}

// Asegurarnos de tener BASE_URL (normalmente ya viene desde index.php)
if (!defined('BASE_URL')) {
  $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
  $host       = $_SERVER['HTTP_HOST'];
  $script_dir = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
  define('BASE_URL', $protocol . $host . $script_dir);
}
?>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

<aside
  class="fixed left-0 top-0 z-40 flex h-screen flex-col border-r border-sidebar-border bg-sidebar transition-all duration-300
  <?php echo $collapsed ? 'w-[70px]' : 'w-[260px]'; ?>"
>

  <!-- Logo -->
  <div class="flex h-16 items-center justify-between border-b border-sidebar-border px-4">
    <?php if (!$collapsed): ?>
      <a href="<?= BASE_URL ?>index.php?page=dashboard" class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-white">
          <img
            src="src/assets/img/logo-sena-negro.png"
            alt="Logo SENA"
            class="max-h-10 w-auto object-contain"
          />
        </div>
        <div class="flex flex-col">
          <span class="text-lg font-bold text-sidebar-foreground leading-tight">SIGA</span>
          <span class="text-[10px] text-muted-foreground -mt-0.5">Gestión de Almacén</span>
        </div>
      </a>
    <?php else: ?>
      <div class="flex h-12 w-12 mx-auto items-center justify-center rounded-lg bg-white">
        <img
          src="src/assets/img/logo-sena-negro.png"
          alt="Logo SENA"
          class="max-h-10 w-auto object-contain"
        />
      </div>
    <?php endif; ?>
  </div>

  <!-- Navigation -->
  <div class="flex-1 px-3 py-4 overflow-y-auto">
    <nav class="flex flex-col gap-1">

      <?php foreach ($navigation as $item): ?>
  <?php
    // URL final SIEMPRE pasa por index.php?page=...
    $itemHref = BASE_URL . 'index.php?page=' . $item['page'];

    // ✅ Dashboard siempre activo, los demás solo si coinciden con $currentPage
    if ($item['page'] === 'dashboard') {
        $isActive = true;                      // siempre verde
    } else {
        $isActive = ($currentPage === $item['page']);  // activo solo si es la página actual
    }

    $iconName = getLucideIconName($item["icon"]);
  ?>

  <a href="<?php echo $itemHref; ?>"
    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all
    <?php echo $isActive
      ? 'bg-sidebar-accent text-sidebar-primary'
      : 'text-sidebar-foreground/70 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground'; ?>"
  >
    <i
      data-lucide="<?php echo htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8'); ?>"
      class="h-5 w-5 shrink-0 <?php echo $isActive ? 'text-sidebar-primary' : ''; ?>"
    ></i>

    <?php if (!$collapsed): ?>
      <span class="flex-1"><?php echo $item["name"]; ?></span>

      <?php if (isset($item["badge"])): ?>
        <span class="h-5 min-w-5 flex items-center justify-center bg-primary text-white text-[11px] rounded-full">
          <?php echo $item["badge"]; ?>
        </span>
      <?php endif; ?>
    <?php endif; ?>
  </a>

<?php endforeach; ?>

    </nav>
  </div>

  <!-- Footer -->
  <div class="border-t border-sidebar-border p-3">
    <div class="flex items-center justify-center gap-2 <?php echo $collapsed ? 'flex-col' : ''; ?>">

      <!-- Bell -->
      <a 
        href="<?= BASE_URL ?>index.php?page=notificaciones"
        class="h-9 w-9 flex items-center justify-center rounded-md text-sidebar-foreground/70 hover:bg-sidebar-accent"
      >
        <i data-lucide="bell" class="h-5 w-5"></i>
      </a>

      <!-- Logout (icono rojo) -->
      <a 
        href="<?= BASE_URL ?>logout.php"
        class="h-9 w-9 flex items-center justify-center rounded-md text-red-500 hover:bg-red-100"
      >
        <i data-lucide="log-out" class="h-5 w-5"></i>
      </a>

      <!-- Botón colapsar -->
      <a
        href="<?= BASE_URL ?>index.php?page=<?= urlencode($currentPage) ?>&coll=<?= $collapsed ? '0' : '1' ?>"
        class="h-9 w-9 flex items-center justify-center rounded-md text-sidebar-foreground/50 hover:bg-sidebar-accent"
      >
        <?php if ($collapsed): ?>
          <i data-lucide="chevron-right" class="h-5 w-5"></i>
        <?php else: ?>
          <i data-lucide="chevron-left" class="h-5 w-5"></i>
        <?php endif; ?>
      </a>


    </div>
  </div>

</aside>

<!-- 🔹 Inicializar los iconos Lucide -->
<script>
  document.addEventListener("DOMContentLoaded", function () {

  /* ===== Inicializar Lucide ===== */
  if (window.lucide && typeof lucide.createIcons === "function") {
    lucide.createIcons();
  }

  /* ===== Sidebar / Layout Sync ===== */
  const sidebar = document.querySelector("aside");
  const page = document.querySelector(".page-with-sidebar");

  if (!sidebar || !page) return;

  function syncLayout() {
    const isCollapsed = sidebar.classList.contains("w-[70px]");

    if (isCollapsed) {
      page.style.marginLeft = "70px";
    } else {
      page.style.marginLeft = "260px";
    }

    page.style.transition = "margin-left 0.3s ease";
  }

  // Ejecutar al cargar
  syncLayout();

  // Por si el DOM cambia
  window.addEventListener("resize", syncLayout);
});

</script>
