<?php
// Página actual según el router (?page=...)
$currentPage = $_GET['page'] ?? 'dashboard';

// ✅ importar permisos
require_once __DIR__ . "/../utils/permisos_helper.php";

// Conexión a la base de datos (fallback igual que en otras vistas)
if (!isset($conn) || !($conn instanceof PDO)) {
  try {
    require_once __DIR__ . '/../../Config/database.php';
  } catch (Throwable $e) {
    // no romper si no existe la configuración en este contexto
  }
}

// Datos del menú (usamos 'page' en vez de href directo)
$navigation = [
  ["name" => "Dashboard",   "page" => "dashboard",   "icon" => "LayoutDashboard"],
  ["name" => "Usuarios",    "page" => "usuarios",    "icon" => "Users"],
  ["name" => "Bodegas",     "page" => "bodegas",     "icon" => "Warehouse"],
  ["name" => "Materiales",  "page" => "materiales",  "icon" => "Package"],
  ["name" => "Obras",       "page" => "obras",       "icon" => "Hammer"],
  ["name" => "Movimientos", "page" => "movimientos", "icon" => "ArrowLeftRight"],
  ["name" => "Solicitudes", "page" => "solicitudes", "icon" => "ClipboardList", "badge" => 0, "badge_id" => "sidebar-badge-solicitudes"],
  ["name" => "Programas",   "page" => "programas",   "icon" => "GraduationCap"],
  ["name" => "Fichas",      "page" => "fichas",      "icon" => "FolderKanban"],
  ["name" => "RAEs",        "page" => "raes",        "icon" => "BookOpen"],
  ["name" => "Evidencias",  "page" => "evidencias",  "icon" => "FileText"],
  ["name" => "Reportes",    "page" => "reportes",    "icon" => "BarChart3"],
];

// ✅ FILTRAR SEGÚN PERMISOS
$navigation = array_values(array_filter($navigation, function($item){
  return permisos_puedeAccederModulo($item["page"]);
}));

// Estado del sidebar
$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$collQuery = $collapsed ? '&coll=1' : '';

//Solo roles con CRUD completo en solicitudes pueden ver el contador (badge)
$canVerBadgeSolicitudes = (
  function_exists('canPermiso')
  && canPermiso('solicitudes.gestionar')
  && canPermiso('solicitudes.consultar')
  && canPermiso('solicitudes.aceptar')
  && canPermiso('solicitudes.rechazar')
);

// Contador de solicitudes pendientes (solo si aplica)
$solicitudesPendientes = 0;
if ($canVerBadgeSolicitudes) {
  try {
    if (isset($conn) && $conn instanceof PDO) {
      $solicitudesPendientes = (int)($conn->query("SELECT COUNT(*) FROM solicitudes_material WHERE estado = 'Pendiente'")->fetchColumn() ?: 0);
    }
  } catch (Throwable $e) {
    // silencioso — deja en 0
    $solicitudesPendientes = 0;
  }
}

function getLucideIconName(string $key): string {
  switch ($key) {
    case 'LayoutDashboard':  return 'layout-dashboard';
    case 'Users':            return 'users-2';
    case 'Warehouse':        return 'warehouse';
    case 'Package':          return 'package';
    case 'Hammer':           return 'hammer';
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
      <a href="<?= BASE_URL ?>index.php?page=dashboard<?= $collQuery ?>" class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-white">
          <img src="src/assets/img/logo-sena-negro.png" alt="Logo SENA" class="max-h-10 w-auto object-contain" />
        </div>
        <div class="flex flex-col">
          <span class="text-lg font-bold text-sidebar-foreground leading-tight">SIGA</span>
          <span class="text-[10px] text-muted-foreground -mt-0.5">Gestión de Almacén</span>
        </div>
      </a>
    <?php else: ?>
      <div class="flex h-12 w-12 mx-auto items-center justify-center rounded-lg bg-white">
        <img src="src/assets/img/logo-sena-negro.png" alt="Logo SENA" class="max-h-10 w-auto object-contain" />
      </div>
    <?php endif; ?>
  </div>

  <!-- Navigation -->
  <div class="flex-1 px-3 py-4 overflow-y-auto">
    <nav class="flex flex-col gap-1">
      <?php foreach ($navigation as $item): ?>
        <?php
          $itemHref = BASE_URL . 'index.php?page=' . $item['page'] . $collQuery;
          $isActive = ($currentPage === $item['page']);
          $iconName = getLucideIconName($item["icon"]);
        ?>

        <a href="<?= $itemHref ?>"
          class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all
          <?= $isActive
            ? 'bg-sidebar-accent text-sidebar-primary'
            : 'text-sidebar-foreground/70 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground'; ?>"
        >
          <i data-lucide="<?= htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8') ?>"
             class="h-5 w-5 shrink-0 <?= $isActive ? 'text-sidebar-primary' : ''; ?>"></i>

          <?php if (!$collapsed): ?>
            <span class="flex-1"><?= $item["name"]; ?></span>

            <?php if (isset($item["badge"])): ?>
              <?php
                $badgeId = isset($item["badge_id"]) ? htmlspecialchars($item["badge_id"], ENT_QUOTES, "UTF-8") : '';
                $badgeCount = 0;
                $badgeStyle = 'display:none;';

                if ($item['page'] === 'solicitudes') {
                  // ✅ Badge solo para CRUD completo
                  $badgeCount = $canVerBadgeSolicitudes ? $solicitudesPendientes : 0;
                } else {
                  $badgeCount = (int)$item["badge"];
                }

                if ($badgeCount > 0) {
                  $badgeStyle = '';
                }
              ?>

              <span id="<?= $badgeId ?>"
                class="h-5 min-w-5 flex items-center justify-center bg-[#39A900] text-white text-[11px] rounded-full"
                style="<?= $badgeStyle ?>"
              ><?= htmlspecialchars((string)$badgeCount, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <!-- Footer -->
  <div class="border-t border-sidebar-border p-3">
    <div class="flex items-center justify-center gap-2 <?= $collapsed ? 'flex-col' : ''; ?>">

      <?php if (permisos_puedeAccederModulo("historial")): ?>
        <a href="<?= BASE_URL ?>index.php?page=historial<?= $collQuery ?>"
          class="h-9 w-9 flex items-center justify-center rounded-md text-sidebar-foreground/70 hover:bg-sidebar-accent"
          title="Historial">
          <i data-lucide="history" class="h-5 w-5"></i>
        </a>
      <?php endif; ?>

      <?php if (permisos_puedeAccederModulo("notificaciones")): ?>
        <a href="<?= BASE_URL ?>index.php?page=notificaciones<?= $collQuery ?>"
          class="h-9 w-9 flex items-center justify-center rounded-md text-sidebar-foreground/70 hover:bg-sidebar-accent"
          title="Notificaciones">
          <i data-lucide="bell" class="h-5 w-5"></i>
        </a>
      <?php endif; ?>

      <a href="<?= BASE_URL ?>logout.php"
        class="h-9 w-9 flex items-center justify-center rounded-md text-red-500 hover:bg-red-100"
        title="Cerrar sesión">
        <i data-lucide="log-out" class="h-5 w-5"></i>
      </a>

      <a href="<?= BASE_URL ?>index.php?page=<?= urlencode($currentPage) ?>&coll=<?= $collapsed ? '0' : '1' ?>"
        class="h-9 w-9 flex items-center justify-center rounded-md text-sidebar-foreground/50 hover:bg-sidebar-accent"
        title="<?= $collapsed ? 'Expandir' : 'Colapsar' ?>">
        <?php if ($collapsed): ?>
          <i data-lucide="chevron-right" class="h-5 w-5"></i>
        <?php else: ?>
          <i data-lucide="chevron-left" class="h-5 w-5"></i>
        <?php endif; ?>
      </a>

    </div>
  </div>
</aside>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    if (window.lucide && typeof lucide.createIcons === "function") {
      lucide.createIcons();
    }
  });

  /* ============================================================
     ✅ FIX (SIN TOCAR TU CÓDIGO BASE):
     - Si otro script global usa "Escape" para cerrar cosas y termina
       ocultando el sidebar, aquí protegemos el sidebar.
     - No altera tu lógica de colapsar/expandir por ?coll=
     - Solo evita que ESC dispare handlers globales cuando NO hay nada
       que cerrar y/o cuando el evento viene repetido (spam).
  ============================================================ */

  (function protectSidebarFromEsc() {
    // Guard global para evitar doble ejecución si este sidebar se incluye 2 veces
    if (window.__SIDEBAR_ESC_GUARD__) return;
    window.__SIDEBAR_ESC_GUARD__ = true;

    // Captura (true) para interceptar antes que listeners normales
    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;

      // Evita ejecuciones por mantener presionada o spamear Esc
      if (e.repeat) {
        e.stopPropagation();
        return;
      }

      // Si hay un modal/alerta abierta, NO bloqueamos Esc (deja que se cierre)
      // - SweetAlert2: .swal2-container
      // - Dialog nativo: dialog[open]
      // - Modales comunes: .modal.is-open / [data-modal].is-open / [aria-modal="true"]
      const hasOpenOverlay =
        !!document.querySelector(".swal2-container") ||
        !!document.querySelector("dialog[open]") ||
        !!document.querySelector("[aria-modal='true']") ||
        !!document.querySelector(".modal.is-open") ||
        !!document.querySelector("[data-modal].is-open");

      if (hasOpenOverlay) return;

      // Si hay un dropdown/menú abierto, NO bloqueamos Esc (deja que se cierre)
      const hasOpenDropdown =
        !!document.querySelector(".dropdown.open") ||
        !!document.querySelector(".menu.open") ||
        !!document.querySelector("[data-dropdown].is-open") ||
        !!document.querySelector("[aria-expanded='true'][data-dropdown-trigger]");

      if (hasOpenDropdown) return;

      // ✅ Si NO hay nada abierto que cerrar, evitamos que otros handlers
      // globales "togleen" el sidebar o le apliquen hidden/clases raras
      e.stopPropagation();
    }, true);
  })();

  /* ============================================================
     ✅ NUEVO (INTEGRADO): CIERRE DE SESIÓN AUTOMÁTICO SIN RECARGAR
     - Usa tu endpoint existente:
       src/controllers/auth_controller.php?accion=check
     - Si detecta que la sesión fue revocada o usuario desactivado:
       redirige al login con ?reason=
     - Guard global para NO duplicar intervalos
  ============================================================ */

  (function sigaSessionWatcher() {
    // Guard global para evitar doble ejecución si el sidebar se incluye 2 veces
    if (window.__SIGA_SESSION_WATCHER__) return;
    window.__SIGA_SESSION_WATCHER__ = true;

    // ✅ Check existente
    const CHECK_URL = new URL(
      "src/controllers/auth_controller.php?accion=check",
      document.baseURI
    ).toString();

    // ✅ Login (ajusta si tu ruta real cambia)
    // Tu login ya muestra mensajes según ?reason=session_revoked / disabled / no_session / etc.
    const LOGIN_URL_BASE = new URL(
      "src/view/login/login.php",
      document.baseURI
    ).toString();

    let busy = false;

    async function checkNow() {
      if (busy) return;
      busy = true;

      try {
        const res = await fetch(CHECK_URL, {
          method: "GET",
          credentials: "same-origin",
          cache: "no-store",
          headers: { "Accept": "application/json" }
        });

        const data = await res.json().catch(() => null);
        if (!data) return;

        // Esperado: { ok:false, logout:true, reason:"session_revoked" }
        if (data.logout === true) {
          const reason = encodeURIComponent(data.reason || "session_revoked");
          window.location.replace(`${LOGIN_URL_BASE}?reason=${reason}`);
        }
      } catch (e) {
        // Si falla el check (red/servidor), NO tumbar al usuario por falso positivo
      } finally {
        busy = false;
      }
    }

    // ✅ Intervalo “casi inmediato” sin castigar el server
    const INTERVAL_MS = 4000;

    // Check inmediato + polling
    checkNow();
    setInterval(checkNow, INTERVAL_MS);

    // Check cuando el usuario vuelve a la pestaña
    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) checkNow();
    });
  })();

</script>
