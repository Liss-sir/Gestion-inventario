<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Conexión a la base de datos (asegúrate que la ruta sea correcta)
require_once __DIR__ . '/../../Config/database.php'; 

require_once __DIR__ . '/auth_guard.php';


// Si no hay usuario logueado, redirige al login (ajusta la ruta según tu estructura)
if (!isset($_SESSION['usuario_id'])) {
    // ✅ FIX: usar BASE_URL si existe para evitar caer en el index de MAMP
    if (defined("BASE_URL")) {
        header('Location: ' . BASE_URL . 'src/view/login/login.php');
    } else {
        header('Location: src/view/login/login.php');
    }
    exit;
}

// Armar nombre completo desde la sesión
$nombreSesion   = $_SESSION['usuario_nombre']   ?? '';
$apellidoSesion = $_SESSION['usuario_apellido'] ?? '';
$nombreCompleto = trim($nombreSesion . ' ' . $apellidoSesion);

$fotoRaw = $_SESSION['usuario_foto'] ?? null;
$fotoUrl = null;

if (!empty($fotoRaw)) {
    // Detecta carpeta base del proyecto (antes de /src/)
    $script = $_SERVER['SCRIPT_NAME']; // ej: /mi_proyecto/src/view/dashboard.php
    $pos    = strpos($script, 'src/');  // mantengo tu búsqueda
    $base   = ($pos !== false) ? substr($script, 0, $pos) : '';

    // ✅ Quitar el "/" inicial del base para que NO empiece con "/"
    $base = ltrim($base, '/'); // ej: mi_proyecto/  (o "")

    // ✅ Construcción segura: base + "/" + fotoRaw SIN slash al inicio
    // Resultado final:
    // mi_proyecto/src/uploads/perfiles/archivo.jpg  ó  src/uploads/perfiles/archivo.jpg
    $fotoUrl = rtrim($base, '/') . '/' . ltrim($fotoRaw, '/');

    // ✅ Asegura que JAMÁS inicie con "/"
    $fotoUrl = ltrim($fotoUrl, '/');
}

$currentUser = [
    "nombre_completo" => $nombreCompleto !== '' ? $nombreCompleto : "Usuario",
    "cargo"           => $_SESSION['usuario_cargo'] ?? "encargado_inventario",
    "foto_url"        => $fotoUrl,
];

/*
  🔹 Datos extra de perfil (tomados de la sesión)
  Ajusta las claves según lo que guardes en el login
*/
$profileData = [
    "nombre_completo"   => $currentUser["nombre_completo"],
    "tipo_documento"    => isset($_SESSION['usuario_tipo_documento'])   ? $_SESSION['usuario_tipo_documento']   : "CC",
    "numero_documento"  => isset($_SESSION['usuario_numero_documento']) ? $_SESSION['usuario_numero_documento'] : "",
    "telefono"          => isset($_SESSION['usuario_telefono'])         ? $_SESSION['usuario_telefono']         : "",
    "correo"            => isset($_SESSION['usuario_correo'])           ? $_SESSION['usuario_correo']           : "",
    "fecha_creacion"    => isset($_SESSION['usuario_fecha_creacion'])   ? $_SESSION['usuario_fecha_creacion']   : "",
    "direccion"         => isset($_SESSION['usuario_direccion'])        ? $_SESSION['usuario_direccion']        : "",
    "estado"            => isset($_SESSION['usuario_estado'])           ? $_SESSION['usuario_estado']           : "activo",
    "cargo"             => isset($_SESSION['usuario_cargo'])            ? $_SESSION['usuario_cargo']            : "",
];

// Programas asociados (si es instructor)
$programasAsociados = $_SESSION['usuario_programas'] ?? [];
if (!is_array($programasAsociados) && !empty($programasAsociados)) {
    $programasAsociados = array_map('trim', explode(',', (string)$programasAsociados));
}

$mockAlerts = [
    ["material_id" => 1, "material_nombre" => "Cemento gris",       "stock_actual" => 8, "stock_minimo" => 10],
    ["material_id" => 2, "material_nombre" => "Guantes de carnaza", "stock_actual" => 4, "stock_minimo" => 6],
    ["material_id" => 1, "material_nombre" => "Cemento gris",       "stock_actual" => 8, "stock_minimo" => 10],
    ["material_id" => 2, "material_nombre" => "Guantes de carnaza", "stock_actual" => 4, "stock_minimo" => 6],
    ["material_id" => 1, "material_nombre" => "Cemento gris",       "stock_actual" => 8, "stock_minimo" => 10],
    ["material_id" => 2, "material_nombre" => "Guantes de carnaza", "stock_actual" => 4, "stock_minimo" => 6],
    ["material_id" => 1, "material_nombre" => "Cemento gris",       "stock_actual" => 8, "stock_minimo" => 10],
    ["material_id" => 2, "material_nombre" => "Guantes de carnaza", "stock_actual" => 4, "stock_minimo" => 6],
];

$roleLabels = [
    "coordinador"          => "Coordinador",
    "instructor"           => "Instructor",
    "pasante"              => "Pasante",
    "encargado_inventario" => "Encargado de Inventario",
    "encargado_bodega"     => "Encargado de Bodega",
];

/*
  🔹 Clases de badge para el ROL (solo usadas en el MODAL de perfil)
*/
$roleBadgeClasses = [
    'Coordinador'          => 'badge-role-coordinador',
    'coordinador'          => 'badge-role-coordinador',
    'Subcoordinador'       => 'badge-role-subcoordinador',
    'subcoordinador'       => 'badge-role-subcoordinador',
    'Instructor'           => 'badge-role-instructor',
    'instructor'           => 'badge-role-instructor',
    'Pasante'              => 'badge-role-pasante',
    'pasante'              => 'badge-role-pasante',
    'Aprendiz'             => 'badge-role-instructor',
    'aprendiz'             => 'badge-role-instructor',
    'encargado_inventario' => 'badge-role-instructor',
    'Encargado inventario' => 'badge-role-instructor',
    'encargado_bodega'     => 'badge-role-instructor',
    'Encargado bodega'     => 'badge-role-instructor',
];

// Iniciales: primer nombre + primer apellido
function getUserInitials(string $nombreCompleto): string {
    $partes = preg_split('/\s+/', trim($nombreCompleto));
    if (!$partes || count($partes) === 0) return '';

    $primerNombre   = $partes[0];
    $primerApellido = $partes[1] ?? $partes[0];

    $iniNombre   = mb_substr($primerNombre,   0, 1, 'UTF-8');
    $iniApellido = mb_substr($primerApellido, 0, 1, 'UTF-8');

    return mb_strtoupper($iniNombre . $iniApellido, 'UTF-8');
}

// ==========================
// ✅ FIX: detectar collapsed igual que "usuarios" (?coll=1)
// (SIN cambiar tu base: solo garantizamos que $collapsed exista)
// ==========================
$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";

// Margen según sidebar (mantenemos tu lógica, pero ahora siempre funciona)
$sidebarMarginClass = 'ml-[260px]';
if (isset($collapsed)) {
    $sidebarMarginClass = $collapsed ? 'ml-[70px]' : 'ml-[260px]';
}

// Bandera para scroll en notificaciones si hay más de 5
$manyAlerts = count($mockAlerts) > 5;

// ¿Es instructor?
$esInstructor = strtolower($profileData["cargo"]) === 'instructor';

?>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/flowbite@2.5.1/dist/flowbite.min.js"></script>
<link rel="stylesheet" href="src/assets/css/globals.css">


<header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-border bg-card px-6 transition-all duration-300 <?php echo $sidebarMarginClass; ?>">
<!-- Datos del usuario para JavaScript -->
<div id="usuario-data" 
     data-usuario-id="<?php echo $_SESSION['usuario_id']; ?>"
     data-usuario-nombre="<?php echo htmlspecialchars($currentUser["nombre_completo"], ENT_QUOTES, 'UTF-8'); ?>"
     data-usuario-cargo="<?php echo $currentUser["cargo"]; ?>"
     style="display: none;">
</div>

<script>
// Variables globales para JavaScript
window.usuarioId = <?php echo $_SESSION['usuario_id']; ?>;
window.usuarioNombre = "<?php echo htmlspecialchars($currentUser["nombre_completo"], ENT_QUOTES, 'UTF-8'); ?>";
window.esCoordinador = <?php
  $cargo = strtolower(trim($currentUser["cargo"] ?? ""));
  echo ($cargo === "coordinador" || $cargo === "subcoordinador") ? "true" : "false";
?>;

</script>
  <!-- Buscador estilo pill -->
  <div class="relative flex-1 max-w-xl">
    <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2">
      <i data-lucide="search" class="h-4 w-4 text-slate-400"></i>
    </div>

    <input
      type="search"
      placeholder="Buscar materiales, usuarios, fichas..."
      class="h-11 w-full rounded-xl border border-[#e2e8f0] bg-[#f8fbff] pl-11 pr-4 text-sm text-slate-600 placeholder:text-slate-400 shadow-[0_0_0_1px_rgba(15,23,42,0.02),0_10px_20px_rgba(15,23,42,0.05)] focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary/40"
    />
  </div>

  <div class="flex items-center gap-4 ml-4">

    <?php
    require_once 'src/utils/notificaciones_sin_db.php';

    // Obtener resumen de notificaciones
    $resumenNotificaciones = NotificacionSesion::obtenerResumen();
    // ✅ Normalizar rol/cargo del usuario actual
$cargoActual = strtolower(trim($currentUser["cargo"] ?? ''));

// ✅ Coordinador / Subcoordinador ven TODO
$esCoordinador = ($cargoActual === 'coordinador' || $cargoActual === 'subcoordinador');

// ✅ Filtrar: coordinador ve todo, los demás ven solo lo suyo
$notificacionesRecientes = NotificacionSesion::obtenerNotificaciones(
    $esCoordinador ? null : ($_SESSION['usuario_id'] ?? null),
    5
);

    ?>

<!-- Notificaciones -->
  <!-- Notificaciones -->
<div class="relative " id="contenedor-notificaciones">
  <button class="relative flex h-10 w-10 items-center justify-center rounded-full hover:bg-muted/70 transition overflow-visible">

  <!-- Campana mediana -->
  <span class="relative z-[1] flex items-center justify-center">
    <i data-lucide="bell" class="h-6 w-6 text-slate-500"></i>
  </span>

  <?php if ($resumenNotificaciones['no_leidas'] > 0): ?>
  <span class="absolute top-[2px] right-[2px] z-[2]
               h-4 w-4 rounded-full bg-[#ff4b4b] ring-2 ring-card
               flex items-center justify-center text-[9px] font-bold text-white
               badge-notificaciones pointer-events-none">
    <?php echo $resumenNotificaciones['no_leidas'] > 9 ? '9+' : $resumenNotificaciones['no_leidas']; ?>
  </span>
<?php endif; ?>


</button>






      <div class="absolute right-0 mt-2 hidden w-96 rounded-md border border-border bg-card shadow-md" id="dropdown-notificaciones">

       <div class="flex items-center justify-between px-3 py-2 border-b">
  <span class="text-sm font-semibold">Notificaciones</span>

  <div class="flex items-center gap-2">
    <span class="rounded-full bg-muted px-2 py-0.5 text-xs">
      <?php echo $resumenNotificaciones['total']; ?>
    </span>

    <?php if ($resumenNotificaciones['total'] > 0): ?>

      <!-- ✅ NUEVO: Marcar todas como leídas -->
      <?php if ($resumenNotificaciones['no_leidas'] > 0): ?>
        <button 
          onclick="marcarTodasLeidas()" 
          class="text-xs text-blue-600 hover:text-blue-800"
          title="Marcar todas como leídas"
        >
          Marcar todas
        </button>
      <?php endif; ?>

      <!-- ✅ NUEVO: Limpiar = ELIMINAR TODAS -->
      <button 
        onclick="limpiarNotificaciones()" 
        class="text-xs text-red-600 hover:text-red-800"
        title="Eliminar todas las notificaciones"
      >
        Limpiar
      </button>

    <?php endif; ?>
  </div>
</div>


       <?php if (empty($notificacionesRecientes)): ?>
  <div id="estado-vacio-notificaciones" class="px-3 py-6 text-center">
    <i data-lucide="bell-off" class="h-8 w-8 text-slate-300 mx-auto mb-2"></i>
    <p class="text-xs text-muted-foreground">No hay notificaciones nuevas.</p>
  </div>
<?php else: ?>

          <div class="max-h-96 overflow-y-auto" id="lista-notificaciones">
            <?php foreach ($notificacionesRecientes as $notif): ?>
  <div 
    class="flex flex-col gap-0.5 px-3 py-2 hover:bg-muted/50 border-b border-border last:border-b-0 transition-all duration-200 
          <?php echo !$notif['leido'] ? 'bg-blue-50 no-leida border-l-2 border-l-blue-500' : 'leida'; ?>"
    data-notif-id="<?php echo $notif['id']; ?>"
  >
    <!-- FILA 1: Icono + Título + Botón eliminar -->
    <div class="flex items-start justify-between gap-2">
      <div class="flex items-start gap-2 flex-1 min-w-0">
        <div class="h-7 w-7 rounded-full flex items-center justify-center flex-shrink-0
                  <?php echo match($notif['color']) {
                    'warning' => 'bg-amber-100 text-amber-600',
                    'danger' => 'bg-red-100 text-red-600',
                    'success' => 'bg-emerald-100 text-emerald-600',
                    default => 'bg-blue-100 text-blue-600'
                  }; ?>">
          <i data-lucide="<?php echo $notif['icono']; ?>" class="h-3.5 w-3.5"></i>
        </div>
        
        <div class="flex-1 min-w-0">
          <!-- Título y hora en la misma línea -->
          <div class="flex items-baseline justify-between gap-2">
            <p class="text-xs font-semibold text-slate-800 truncate flex-1">
              <?php echo htmlspecialchars($notif['titulo']); ?>
            </p>
            <p class="text-[10px] text-slate-500 whitespace-nowrap">
              <?php echo date('d/m H:i', strtotime($notif['fecha'])); ?>
            </p>
          </div>
        </div>
      </div>
      
      <!-- Botón eliminar -->
      <div class="flex items-center gap-1 flex-shrink-0">
        <?php if (!$notif['leido']): ?>
          <span class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse"></span>
        <?php endif; ?>
        
        <button 
          onclick="eliminarNotificacion('<?php echo $notif['id']; ?>')"
          class="h-5 w-5 flex items-center justify-center text-slate-400 hover:text-red-500"
          title="Eliminar"
        >
          <i data-lucide="x" class="h-3 w-3"></i>
        </button>
      </div>
    </div>
    
    <!-- FILA 2: Usuario y botón Marcar como leído (más compacto) -->
    <div class="flex items-center justify-between gap-2 text-[10px] pl-9">
      <span class="text-slate-500 truncate flex-1">
       Usuario: <?php echo htmlspecialchars(trim($notif['usuario_nombre'] ?? 'Sin nombre')); ?>

      </span>
      
      <?php if (!$notif['leido']): ?>
        <button 
          onclick="marcarNotificacionLeida('<?php echo $notif['id']; ?>')"
          class="text-blue-600 hover:text-blue-800 hover:underline whitespace-nowrap"
        >
          Marcar leído
        </button>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
          </div>
          
          <?php if ($resumenNotificaciones['total'] > 5): ?>
            <div class="px-3 py-2 border-t text-center">
              <a href="?page=notificaciones" class="text-xs text-blue-600 hover:text-blue-800 hover:underline">
                Ver todas las notificaciones
              </a>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <script>
    // Funciones JavaScript para manejar notificaciones
    function marcarNotificacionLeida(notifId) {
      fetch('src/utils/notificaciones_sesion.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'accion=marcar_leido&notificacion_id=' + notifId
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const notifElement = document.querySelector(`[data-notif-id="${notifId}"]`);
          if (notifElement) {
            notifElement.classList.remove('no-leida', 'bg-blue-50', 'border-l-blue-500');
            notifElement.classList.add('leida');
            
            // Actualizar contador
            actualizarContadorNotificaciones();
          }
        }
      });
    }

    function marcarTodasLeidas() {
      fetch('src/utils/notificaciones_sesion.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'accion=marcar_todas_leidas'
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Actualizar todas las notificaciones visualmente
          document.querySelectorAll('.no-leida').forEach(el => {
            el.classList.remove('no-leida', 'bg-blue-50', 'border-l-blue-500');
            el.classList.add('leida');
          });
          
          // Actualizar contador
          actualizarContadorNotificaciones();
        }
      });
    }

    function mostrarEstadoVacioNotificaciones() {
  const dropdown = document.getElementById("dropdown-notificaciones");
  const lista = document.getElementById("lista-notificaciones");

  // ✅ Si existe la lista, la quitamos
  if (lista) lista.remove();

  // ✅ Si ya existe el estado vacío, lo quitamos para no duplicar
  const existente = document.getElementById("estado-vacio-notificaciones");
  if (existente) existente.remove();

  // ✅ ESTE HTML ES IGUAL AL QUE TE RENDERIZA PHP (imagen 1)
  const emptyHTML = `
    <div id="estado-vacio-notificaciones" class="px-3 py-6 text-center">
      <i data-lucide="bell-off" class="h-8 w-8 text-slate-300 mx-auto mb-2"></i>
      <p class="text-xs text-muted-foreground">No hay notificaciones nuevas.</p>
    </div>
  `;

  dropdown.insertAdjacentHTML("beforeend", emptyHTML);

  // ✅ Volver a renderizar los íconos
  if (window.lucide && typeof window.lucide.createIcons === "function") {
    window.lucide.createIcons();
  }
}




    function eliminarNotificacion(notifId) {
  fetch('src/utils/notificaciones_sesion.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'accion=eliminar&notificacion_id=' + notifId
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const notifElement = document.querySelector(`[data-notif-id="${notifId}"]`);
      if (notifElement) {
        notifElement.style.opacity = '0';
        notifElement.style.transform = 'translateX(100%)';
        setTimeout(() => notifElement.remove(), 300);

        // ✅ Actualizar contador y UI
        actualizarContadorNotificaciones();
        verificarListaVacia();
      }
    }
  });
}

function limpiarNotificaciones() {
  fetch('src/utils/notificaciones_sesion.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'accion=eliminar_todas'
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // ✅ Borra todos los elementos visuales
      const lista = document.getElementById("lista-notificaciones");
      if (lista) {
        lista.innerHTML = "";
      }

      // ✅ Elimina el badge
      const badge = document.querySelector('.badge-notificaciones');
      if (badge) badge.remove();

      // ✅ Mostrar "vacío"
      mostrarEstadoVacioNotificaciones();
    }
  });
}



    async function actualizarContadorNotificaciones() {
      try {
        const resp = await fetch('src/utils/notificaciones_sesion.php?accion=contar');
        const data = await resp.json();
        
        const badge = document.querySelector('.badge-notificaciones');
        if (data.no_leidas > 0) {
          if (!badge) {
            // Crear badge si no existe
            const newBadge = document.createElement('span');
            newBadge.className = 'absolute right-1.5 top-1.5 h-5 w-5 rounded-full bg-[#ff4b4b] ring-2 ring-card flex items-center justify-center text-[10px] font-bold text-white badge-notificaciones';
            document.querySelector('#contenedor-notificaciones button').appendChild(newBadge);
          }
          const badgeElement = badge || document.querySelector('.badge-notificaciones');
          badgeElement.textContent = data.no_leidas > 9 ? '9+' : data.no_leidas;
        } else if (badge) {
          badge.remove();
        }
      } catch (error) {
        console.error('Error actualizando contador:', error);
      }
    }

    // Actualizar notificaciones cada 30 segundos
    setInterval(actualizarContadorNotificaciones, 30000);
    </script> <!-- FIN DE NOTIFICACIONES -->

    <!-- Menú de usuario (CLICK TOGGLE, NO HOVER) -->
    <div class="relative">
      <button
        id="btnUserMenu"
        type="button"
        aria-expanded="false"
        class="flex items-center gap-3 rounded-full px-2 py-1.5 h-auto hover:bg-muted/70 transition"
      >

        <!-- Avatar / Iniciales -->
        <div
          class="flex h-9 w-9 items-center justify-center rounded-full overflow-hidden"
          <?php if (empty($currentUser["foto_url"])): ?>
            style="background-color: color-mix(in srgb, var(--secondary) 39%, #ffffff 61%);"
          <?php else: ?>
            style="background-color: transparent;"
          <?php endif; ?>
        >
          <?php if (!empty($currentUser["foto_url"])): ?>
            <img
              src="<?php echo htmlspecialchars($currentUser["foto_url"], ENT_QUOTES, 'UTF-8'); ?>"
              alt="<?php echo htmlspecialchars($currentUser["nombre_completo"], ENT_QUOTES, 'UTF-8'); ?>"
              class="h-full w-full object-cover"
            />
          <?php else: ?>
            <span class="text-xs font-semibold text-primary">
              <?php echo getUserInitials($currentUser["nombre_completo"]); ?>
            </span>
          <?php endif; ?>
        </div>

        <div class="hidden flex-col items-start md:flex">
          <span class="text-sm font-medium text-slate-900">
            <?php
              $parts = preg_split('/\s+/', trim($currentUser["nombre_completo"]));
              $first = $parts[0] ?? '';
              $last  = $parts[1] ?? '';
              echo trim($first . ' ' . $last);
            ?>
          </span>
          <span class="text-xs text-slate-500">
            <?php echo $roleLabels[$currentUser["cargo"]] ?? $currentUser["cargo"]; ?>
          </span>
        </div>

        <i data-lucide="chevron-down" class="h-4 w-4 text-slate-500"></i>
      </button>

      <div
        id="userMenuDropdown"
        class="absolute right-0 mt-2 hidden w-56 rounded-md border border-border bg-card shadow-md"
      >
        <div class="px-3 py-2">
          <span class="block text-sm font-semibold">Mi cuenta</span>
        </div>
        <hr class="border-border" />

        <button
        id="btnVerPerfil"
        type="button"
        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition
              hover:bg-[color-mix(in_srgb,var(--primary)_10%,transparent)]
              hover:text-[var(--primary)]
              focus:outline-none focus:bg-[color-mix(in_srgb,var(--primary)_10%,transparent)]"
      >
        <i data-lucide="user" class="mr-1 h-4 w-4"></i>
        Perfil
      </button>

      <button
        id="btnEditarPerfil"
        type="button"
        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition
              hover:bg-[color-mix(in_srgb,var(--primary)_10%,transparent)]
              hover:text-[var(--primary)]
              focus:outline-none focus:bg-[color-mix(in_srgb,var(--primary)_10%,transparent)]"
      >
        <i data-lucide="settings" class="mr-1 h-4 w-4"></i>
        Editar Perfil
      </button>

        <hr class="border-border" />

        <form action="logout.php" method="post">
          <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-destructive transition hover:bg-red-50">
            <i data-lucide="log-out" class="mr-1 h-4 w-4"></i>
            Cerrar sesión
          </button>
        </form>
      </div>
    </div>

  </div>
</header>

<!-- ✅ NUEVO: CONTENEDOR GLOBAL DE TOASTS (FUERA DEL MODAL) - SIN TOCAR TU BASE -->
<div id="toastGlobalContainer" class="fixed top-5 right-5 z-[9999] space-y-3"></div>

<!-- ===================================================
   MODAL VER PERFIL (SOLO VISUALIZAR, SIN INPUTS)
=================================================== -->
<div
  id="modalPerfilVer"
  class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4"
>
  <div class="relative w-full max-w-xl rounded-3xl bg-white shadow-xl">

    <button
      id="btnCerrarModalPerfilVer"
      class="absolute right-5 top-5 inline-flex h-8 w-8 items-center justify-center bg-white rounded-full"
      type="button"
    >
      <i data-lucide="x" class="h-4 w-4 text-slate-600"></i>
    </button>

    <div class="p-6 md:p-8">
      <div class="flex items-center gap-4 mb-6">
        <div class="h-16 w-16 rounded-full overflow-hidden flex items-center justify-center bg-slate-100" style="background-color: color-mix(in srgb, var(--secondary) 39%, #ffffff 61%);">
          <?php if (!empty($currentUser["foto_url"])): ?>
            <img
              src="<?php echo htmlspecialchars($currentUser["foto_url"], ENT_QUOTES, 'UTF-8'); ?>"
              alt="<?php echo htmlspecialchars($currentUser["nombre_completo"], ENT_QUOTES, 'UTF-8'); ?>"
              class="h-full w-full object-cover"
            />
          <?php else: ?>
            <span class="text-xl font-semibold text-primary">
              <?php echo getUserInitials($currentUser["nombre_completo"]); ?>
            </span>
          <?php endif; ?>
        </div>

        <div class="flex-1">
          <h2 class="text-lg md:text-xl font-semibold text-slate-900">
            <?php echo htmlspecialchars($profileData["nombre_completo"], ENT_QUOTES, 'UTF-8'); ?>
          </h2>

          <div class="mt-1 flex items-center gap-2">
            <?php
              $cargoRawModalVer      = $profileData["cargo"];
              $cargoLabelModalVer    = $roleLabels[$cargoRawModalVer] ?? ucfirst(str_replace('_', ' ', $cargoRawModalVer));
              $cargoBadgeClsModalVer = $roleBadgeClasses[$cargoRawModalVer] ?? 'badge-role-instructor';
            ?>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium <?php echo $cargoBadgeClsModalVer; ?>">
              <?php echo htmlspecialchars($cargoLabelModalVer, ENT_QUOTES, 'UTF-8'); ?>
            </span>

            <?php
              $isActiveVer = strtolower($profileData["estado"]) === 'activo';
              $estadoClassesVer = $isActiveVer
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-red-100 text-red-700';
            ?>
            <span class="inline-flex rounded-full px-3 py-0.5 text-[11px] font-semibold <?php echo $estadoClassesVer; ?>">
              <?php echo htmlspecialchars($profileData["estado"], ENT_QUOTES, 'UTF-8'); ?>
            </span>
          </div>
        </div>
      </div>

      <div class="space-y-6 text-sm">
        <div>
          <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">
            Datos personales
          </h3>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

            <div>
              <p class="text-xs font-medium text-slate-400">Tipo de documento</p>
              <p class="text-sm text-slate-800">
                <?php echo htmlspecialchars($profileData["tipo_documento"], ENT_QUOTES, 'UTF-8'); ?>
              </p>
            </div>

            <div>
              <p class="text-xs font-medium text-slate-400">Número de documento</p>
              <p class="text-sm text-slate-800">
                <?php echo htmlspecialchars($profileData["numero_documento"], ENT_QUOTES, 'UTF-8'); ?>
              </p>
            </div>

            <div>
              <p class="text-xs font-medium text-slate-400">Teléfono</p>
              <p class="text-sm text-slate-800">
                <?php echo htmlspecialchars($profileData["telefono"], ENT_QUOTES, 'UTF-8'); ?>
              </p>
            </div>

            <div>
              <p class="text-xs font-medium text-slate-400">Dirección</p>
              <p class="text-sm text-slate-800 break-words">
                <?php echo htmlspecialchars($profileData["direccion"], ENT_QUOTES, 'UTF-8'); ?>
              </p>
            </div>
          </div>
        </div>

        <div>
          <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">
            Datos de la cuenta
          </h3>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <p class="text-xs font-medium text-slate-400">Correo</p>
              <p class="text-sm text-slate-800 break-all">
                <?php echo htmlspecialchars($profileData["correo"], ENT_QUOTES, 'UTF-8'); ?>
              </p>
            </div>

            <div>
              <p class="text-xs font-medium text-slate-400">Fecha de creación</p>
              <p class="text-sm text-slate-800">
                <?php echo htmlspecialchars($profileData["fecha_creacion"], ENT_QUOTES, 'UTF-8'); ?>
              </p>
            </div>
          </div>
        </div>

        <?php if ($esInstructor && !empty($programasAsociados)): ?>
          <div>
            <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">
              Programas asociados
            </h3>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($programasAsociados as $prog): ?>
                <?php if (trim($prog) === '') continue; ?>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                  <?php echo htmlspecialchars($prog, ENT_QUOTES, 'UTF-8'); ?>
                </span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ===================================================
   MODAL EDITAR PERFIL (CON INPUTS)
=================================================== -->
<div
  id="modalPerfilEditar"
  class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4"
>
  <div class="relative w-full max-w-2xl rounded-3xl bg-white shadow-xl">

    <button
      id="btnCerrarModalPerfilEditar"
      class="absolute right-5 top-5 inline-flex h-8 w-8 items-center justify-center bg-white rounded-full z-20"
      type="button"
    >
      <i data-lucide="x" class="h-4 w-4 text-slate-600"></i>
    </button>

    <!-- ✅ FIX: QUITAR ABSOLUTE para que la línea NO se atraviese -->
    <div class="px-6 pt-8 md:px-8">
  <div class="flex items-center justify-end gap-2 pr-12 mt-1">
    <button
      id="btnInfoDatosSensibles"
      type="button"
      title="Cambiar datos sensibles"
      class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
    >
      <i data-lucide="info" class="h-4 w-4"></i>
      <span class="whitespace-nowrap">Editar datos sensibles</span>
    </button>

    <button
      id="btnAbrirCambiarPassword"
      type="button"
      class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
    >
      Cambiar contraseña
    </button>
  </div>

  <!-- Línea debajo de los botones -->
  <div class="mt-5 border-b border-slate-200"></div>
</div>


    <!-- ✅ FIX: padding normal (ya no necesitamos “pt” gigante) -->
    <div class="p-6 pt-6 md:p-8 md:pt-8">
      <div class="flex items-center gap-4 mb-6">
        <div class="relative h-16 w-16 cursor-pointer" id="avatarPerfilEditar">
          <div
            class="flex h-16 w-16 items-center justify-center rounded-full overflow-hidden"
            <?php if (empty($currentUser["foto_url"])): ?>
              style="background-color: color-mix(in srgb, var(--secondary) 39%, #ffffff 61%);"
            <?php else: ?>
              style="background-color: transparent;"
            <?php endif; ?>
          >
            <?php if (!empty($currentUser["foto_url"])): ?>
              <img
                src="<?php echo htmlspecialchars($currentUser["foto_url"], ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars($currentUser["nombre_completo"], ENT_QUOTES, 'UTF-8'); ?>"
                class="h-full w-full object-cover"
              />
            <?php else: ?>
              <span class="text-xl font-semibold text-primary">
                <?php echo getUserInitials($currentUser["nombre_completo"]); ?>
              </span>
            <?php endif; ?>
          </div>

          <span
            id="btnCambiarFotoEditar"
            class="absolute -bottom-2 -right-2 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white shadow-md ring-2 ring-white cursor-pointer"
          >
            <i data-lucide="pencil" class="h-3.5 w-3.5 text-slate-700"></i>
          </span>
        </div>

        <div class="flex-1">
          <h2 class="text-lg md:text-xl font-semibold text-slate-900">
            <?php echo htmlspecialchars($profileData["nombre_completo"], ENT_QUOTES, 'UTF-8'); ?>
          </h2>

          <div class="mt-1 flex items-center gap-2">
            <?php
              $cargoRawModalEditar      = $profileData["cargo"];
              $cargoLabelModalEditar    = $roleLabels[$cargoRawModalEditar] ?? ucfirst(str_replace('_', ' ', $cargoRawModalEditar));
              $cargoBadgeClsModalEditar = $roleBadgeClasses[$cargoRawModalEditar] ?? 'badge-role-instructor';
            ?>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium <?php echo $cargoBadgeClsModalEditar; ?>">
              <?php echo htmlspecialchars($cargoLabelModalEditar, ENT_QUOTES, 'UTF-8'); ?>
            </span>

            <?php
              $isActiveEditar = strtolower($profileData["estado"]) === 'activo';
              $estadoClassesEditar = $isActiveEditar
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-red-100 text-red-700';
            ?>
            <span class="inline-flex rounded-full px-3 py-0.5 text-[11px] font-semibold <?php echo $estadoClassesEditar; ?>">
              <?php echo htmlspecialchars($profileData["estado"], ENT_QUOTES, 'UTF-8'); ?>
            </span>
          </div>
        </div>
      </div>

      <input
        type="file"
        id="inputFotoPerfilEditar"
        name="foto_perfil"
        accept="image/*"
        class="hidden"
      />

      <?php
// ... (mantenemos tu lógica de sesión y foto inicial) ...

// ✅ NUEVO: Preparar datos para JS de forma limpia
$datosParaJS = [
    "id_usuario"       => $_SESSION['usuario_id'],
    "nombre_completo"  => $profileData["nombre_completo"],
    "tipo_documento"   => $profileData["tipo_documento"],
    "numero_documento" => $profileData["numero_documento"],
    "correo"           => $profileData["correo"],
    "telefono"         => $profileData["telefono"],
    "direccion"        => $profileData["direccion"]
];
?>

<!-- Inyectar datos para JS antes de cerrar el header -->
<script>
    window.userData = <?php echo json_encode($datosParaJS); ?>;
</script>

<!-- ... (Tu HTML del header se mantiene igual hasta el modal de editar) ... -->

<!-- MODAL EDITAR PERFIL (Normal: Teléfono, Dirección y Foto) -->
<form id="formEditarPerfil" method="post" enctype="multipart/form-data" class="space-y-6">
    <!-- Se añade campo oculto para el ID -->
    <input type="hidden" name="id_usuario" value="<?php echo $_SESSION['usuario_id']; ?>">
    
    <div>
        <h3 class="mb-3 text-sm font-semibold text-slate-800">Datos personales</h3>
        <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
            <div>
                <label class="text-xs font-medium text-slate-400 block mb-1">Teléfono</label>
                <input type="text" name="telefono" id="edit_telefono"
                    value="<?php echo htmlspecialchars($profileData["telefono"]); ?>"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/40" />
            </div>
            <div>
                <label class="text-xs font-medium text-slate-400 block mb-1">Dirección</label>
                <input type="text" name="direccion" id="edit_direccion"
                    value="<?php echo htmlspecialchars($profileData["direccion"]); ?>"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/40" />
            </div>
        </div>
    </div>
   <!-- ✅ BOTONES (GUARDAR / CANCELAR) -->
<div class="mt-6 flex justify-end gap-3 pt-2">

  <button
    type="button"
    id="btnCancelarEditarPerfil"
    class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
  >
    Cancelar
  </button>

  <button
    type="submit"
    id="btnGuardarEditarPerfil"
    class="inline-flex items-center justify-center rounded-lg bg-secondary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-secondary/90 transition"
  >
    Guardar cambios
  </button>

</div>

</form>
    </div>
  </div>
</div>

<!-- ✅ NUEVO: MODAL DATOS SENSIBLES -->
<div
  id="modalDatosSensibles"
  class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4"
>
  <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl">
    <button
      id="btnCerrarDatosSensibles"
      type="button"
      class="absolute right-4 top-4 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white"
    >
      <i data-lucide="x" class="h-4 w-4 text-slate-600"></i>
    </button>

    <div class="p-6 space-y-4">
      <h2 class="text-lg font-semibold text-slate-900">Cambiar datos sensibles</h2>
      <p class="text-xs text-slate-500">
        Si requieres cambiar datos sensibles, selecciona cuáles y escribe el dato correcto.
      </p>

      <!-- ✅ NUEVO: CONTENEDOR DE ALERTAS FLOWBITE (SIN TOCAR TU DISEÑO) -->
      <div id="alertaDatosSensiblesContainer"></div>

      <div class="rounded-xl border border-slate-200 p-4">
        <p class="text-xs font-semibold text-slate-700 mb-3">Selecciona los datos a cambiar</p>

        <div class="space-y-2 text-sm">
          <label class="flex items-center gap-2">
            <input type="checkbox" class="rounded border-slate-300" data-sensible="nombre" />
            <span>Nombre</span>
          </label>

          <label class="flex items-center gap-2">
            <input type="checkbox" class="rounded border-slate-300" data-sensible="tipo_documento" />
            <span>Tipo de documento</span>
          </label>

          <label class="flex items-center gap-2">
            <input type="checkbox" class="rounded border-slate-300" data-sensible="numero_documento" />
            <span>Número de documento</span>
          </label>

          <label class="flex items-center gap-2">
            <input type="checkbox" class="rounded border-slate-300" data-sensible="correo" />
            <span>Correo</span>
          </label>
        </div>
      </div>

      <form id="formDatosSensibles"
      class="space-y-3"
      method="post"
      action="src/controllers/usuario_controller.php?accion=solicitar_cambio_datos_sensibles">

  <input type="hidden" id="datosSensiblesSeleccionados" name="datos_sensibles_seleccionados" value="">
  <input type="hidden" id="inputDatosCambiadosJSON" name="datos_cambiados" value="">

  <!-- tus fields normales abajo -->


        <div id="field_nombre" class="hidden">
          <label class="text-xs font-medium text-slate-400 block mb-1">Nombre correcto</label>
          <input
            type="text"
            id="input_nombre_sensible"
            name="nombre_completo"
            value="<?php echo htmlspecialchars($profileData["nombre_completo"], ENT_QUOTES, 'UTF-8'); ?>"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
          />
          <!-- ✅ NUEVO: mensaje de error -->
          <p class="mt-1 text-[11px] text-red-600 hidden" id="error_nombre_sensible"></p>
        </div>

        <div id="field_tipo_documento" class="hidden">
          <label class="text-xs font-medium text-slate-400 block mb-1">Tipo de documento correcto</label>
          <select
            id="select_tipo_documento_sensible"
            name="tipo_documento"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
          >
            <?php
              $tipoActual = $profileData["tipo_documento"] ?? "CC";
              $tipos = ["CC", "TI", "CE"];
              foreach ($tipos as $t) {
                $sel = ($t === $tipoActual) ? "selected" : "";
                echo "<option value=\"{$t}\" {$sel}>{$t}</option>";
              }
            ?>
          </select>
          <p class="mt-1 text-[11px] text-red-600 hidden" id="error_tipo_documento_sensible"></p>
        </div>

        <div id="field_numero_documento" class="hidden">
          <label class="text-xs font-medium text-slate-400 block mb-1">Número de documento correcto</label>
          <input
            type="text"
            id="input_numero_documento_sensible"
            name="numero_documento"
            value="<?php echo htmlspecialchars($profileData["numero_documento"], ENT_QUOTES, 'UTF-8'); ?>"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
          />
          <p class="mt-1 text-[11px] text-red-600 hidden" id="error_numero_documento_sensible"></p>
        </div>

        <div id="field_correo" class="hidden">
          <label class="text-xs font-medium text-slate-400 block mb-1">Correo correcto</label>
          <input
            type="email"
            id="input_correo_sensible"
            name="correo"
            value="<?php echo htmlspecialchars($profileData["correo"], ENT_QUOTES, 'UTF-8'); ?>"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
          />
          <p class="mt-1 text-[11px] text-red-600 hidden" id="error_correo_sensible"></p>
        </div>

        <div class="mt-4 flex justify-end gap-3">
          <button
            type="button"
            id="btnCancelarDatosSensibles"
            class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
          >
            Cancelar
          </button>
          <button
            type="submit"
            id="btnEnviarDatosSensibles"
            class="inline-flex items-center justify-center rounded-lg bg-secondary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-secondary/90 transition"
          >
            Continuar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 🔹 MODAL CAMBIAR CONTRASEÑA -->
<div
  id="modalPassword"
  class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4"
>
  <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl">
    <button
      id="btnCerrarPassword"
      type="button"
      class="absolute right-4 top-4 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white"
    >
      <i data-lucide="x" class="h-4 w-4 text-slate-600"></i>
    </button>

    <div class="p-6 space-y-4">
      <h2 class="text-lg font-semibold text-slate-900">Cambiar contraseña</h2>
      <p class="text-xs text-slate-500">
        Por seguridad, ingresa tu contraseña actual y luego la nueva contraseña.
      </p>

      <!-- ✅ Alertas Flowbite para Password -->
<div id="alertaPasswordContainer" class="mb-4"></div>


      <form id="formCambiarPassword" method="post" action="#">
        <div class="space-y-4 text-sm">

  <!-- Contraseña actual -->
  <div>
    <label class="text-xs font-medium text-slate-400 block mb-1">Contraseña actual</label>

    <div class="relative">
      <input
        type="password"
        name="password_actual"
        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 pr-11 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
        placeholder="••••••••"
      />

      <button
        type="button"
        data-toggle-password="true"
        aria-label="Mostrar/Ocultar contraseña actual"
        class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-slate-50"
      >
        <i data-lucide="eye" class="h-4 w-4 text-slate-500"></i>
        <i data-lucide="eye-off" class="h-4 w-4 text-slate-500 hidden"></i>
      </button>
    </div>
  </div>

  <!-- Nueva contraseña -->
  <div>
    <label class="text-xs font-medium text-slate-400 block mb-1">Nueva contraseña</label>

    <div class="relative">
      <input
        type="password"
        name="password_nueva"
        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 pr-11 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
        placeholder="••••••••"
      />

      <button
        type="button"
        data-toggle-password="true"
        aria-label="Mostrar/Ocultar nueva contraseña"
        class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-slate-50"
      >
        <i data-lucide="eye" class="h-4 w-4 text-slate-500"></i>
        <i data-lucide="eye-off" class="h-4 w-4 text-slate-500 hidden"></i>
      </button>
    </div>
  </div>

  <!-- Confirmar nueva contraseña -->
  <div>
    <label class="text-xs font-medium text-slate-400 block mb-1">Confirmar nueva contraseña</label>

    <div class="relative">
      <input
        type="password"
        name="password_confirmar"
        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 pr-11 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
        placeholder="••••••••"
      />

      <button
        type="button"
        data-toggle-password="true"
        aria-label="Mostrar/Ocultar confirmar contraseña"
        class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-slate-50"
      >
        <i data-lucide="eye" class="h-4 w-4 text-slate-500"></i>
        <i data-lucide="eye-off" class="h-4 w-4 text-slate-500 hidden"></i>
      </button>
    </div>
  </div>

</div>


        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            id="btnCancelarPassword"
            class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
          >
            Cancelar
          </button>
          <button
            type="submit"
            class="inline-flex items-center justify-center rounded-lg bg-secondary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-secondary/90 transition"
          >
            Guardar cambios
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php
// ✅ Detectar si viene forzado por query o por sesión
$forcePwdQuery = isset($_GET['force_pwd']) && $_GET['force_pwd'] == '1';
$forcePwdSession = !empty($_SESSION['force_password_change']) && (int)$_SESSION['force_password_change'] === 1;

$mustForcePwd = ($forcePwdQuery || $forcePwdSession);
?>

<?php if ($mustForcePwd): ?>

  <!-- ✅ Flowbite (si ya lo tienes global, puedes quitarlo) -->
  <script src="https://unpkg.com/flowbite@2.5.1/dist/flowbite.min.js"></script>

  <!-- ===========================================================
       ✅ MODAL FLOWBITE — CAMBIO DE CONTRASEÑA OBLIGATORIO
       =========================================================== -->
  <div
    id="forcePwdModal"
    tabindex="-1"
    aria-hidden="true"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-[9999] justify-center items-center w-full inset-0 h-full bg-black/40"
  >
    <div class="relative p-4 w-full max-w-lg">
      <div class="relative bg-white rounded-xl shadow-xl border border-gray-200">

        <!-- Header -->
        <div class="flex items-center justify-between p-5 border-b border-gray-200">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">
              Cambio de contraseña obligatorio
            </h3>
            <p class="text-sm text-gray-500 mt-1">
              Por seguridad, debes actualizar tu contraseña para continuar.
            </p>
          </div>
        </div>

        <!-- Body -->
        <div class="p-5 space-y-4">

          <!-- ✅ ALERTAS FLOWBITE (dinámico) -->
          <div id="forcePwdAlertContainer"></div>

          <!-- CONTRASEÑA ACTUAL -->
          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700">Contraseña actual</label>

            <div class="relative">
              <input
                id="fp_actual"
                type="password"
                placeholder="••••••••"
                class="h-11 w-full border border-gray-300 rounded-lg px-3 pr-11 focus:ring-2 focus:ring-secondary/30 focus:border-secondary"
              />

              <button
                type="button"
                id="fp_actual_eye"
                class="absolute right-0 top-0 h-11 w-11 flex items-center justify-center text-gray-500 hover:text-gray-700 transition"
                aria-label="Mostrar/ocultar contraseña actual"
              >
                <!-- Eye icon -->
                <svg id="fp_actual_eye_icon" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- NUEVA CONTRASEÑA -->
          <div class="space-y-1">
            <label class="text-sm font-medium text-gray-700">Nueva contraseña</label>

            <p class="text-xs text-gray-500">
              Debe tener mínimo 1 número, 1 letra mayúscula y 1 caracter especial.
            </p>

            <div class="relative mt-2">
              <input
                id="fp_nueva"
                type="password"
                placeholder="••••••••"
                class="h-11 w-full border border-gray-300 rounded-lg px-3 pr-11 focus:ring-2 focus:ring-secondary/30 focus:border-secondary"
              />

              <button
                type="button"
                id="fp_nueva_eye"
                class="absolute right-0 top-0 h-11 w-11 flex items-center justify-center text-gray-500 hover:text-gray-700 transition"
                aria-label="Mostrar/ocultar nueva contraseña"
              >
                <svg id="fp_nueva_eye_icon" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- CONFIRMAR CONTRASEÑA -->
          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700">Confirmar nueva contraseña</label>

            <div class="relative">
              <input
                id="fp_conf"
                type="password"
                placeholder="••••••••"
                class="h-11 w-full border border-gray-300 rounded-lg px-3 pr-11 focus:ring-2 focus:ring-secondary/30 focus:border-secondary"
              />

              <button
                type="button"
                id="fp_conf_eye"
                class="absolute right-0 top-0 h-11 w-11 flex items-center justify-center text-gray-500 hover:text-gray-700 transition"
                aria-label="Mostrar/ocultar confirmación"
              >
                <svg id="fp_conf_eye_icon" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
              </button>
            </div>
          </div>

        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 p-5 border-t border-gray-200">
          <button
            id="forcePwdSaveBtn"
            type="button"
            class="h-11 px-5 rounded-lg bg-secondary text-white font-medium hover:opacity-95 transition flex items-center justify-center"
          >
            <span id="forcePwdBtnText">Guardar contraseña</span>

            <!-- Loader -->
            <svg
              id="forcePwdLoader"
              class="hidden ml-2 h-4 w-4 animate-spin"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
              <path d="M4 12a8 8 0 018-8" />
            </svg>
          </button>
        </div>

      </div>
    </div>
  </div>

  <script>
    // ✅ Guard anti-doble ejecución
    if (!window.__forcePwdFlowbiteLoaded) {
      window.__forcePwdFlowbiteLoaded = true;

      document.addEventListener("DOMContentLoaded", () => {
        initForcePwdFlowbiteModal();
      });
    }

    function renderFlowbiteAlert(type, message) {
      // type: "error" | "success" | "info"
      const container = document.getElementById("forcePwdAlertContainer");
      if (!container) return;

      const styles = {
        error:   "text-red-800 bg-red-50 border-red-200",
        success: "text-emerald-800 bg-emerald-50 border-emerald-200",
        info:    "text-gray-800 bg-gray-50 border-gray-200",
      };

      const icon = {
        error: `
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86l-7.1 12.3A1.5 1.5 0 004.5 18h15a1.5 1.5 0 001.3-2.24l-7.1-12.3a1.5 1.5 0 00-2.6 0z"/>
          </svg>
        `,
        success: `
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
        `,
        info: `
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/>
          </svg>
        `
      };

      container.innerHTML = `
        <div class="flex items-start gap-3 p-4 rounded-lg border ${styles[type] || styles.info}" role="alert">
          <div class="mt-0.5">${icon[type] || icon.info}</div>
          <div class="text-sm font-medium leading-relaxed">${message}</div>
        </div>
      `;
    }

    function setForcePwdLoading(isLoading) {
      const btn = document.getElementById("forcePwdSaveBtn");
      const loader = document.getElementById("forcePwdLoader");
      const text = document.getElementById("forcePwdBtnText");

      if (!btn || !loader || !text) return;

      btn.disabled = isLoading;
      loader.classList.toggle("hidden", !isLoading);
      text.textContent = isLoading ? "Guardando..." : "Guardar contraseña";
    }

    function toggleEye(inputId, btnIconId) {
      const input = document.getElementById(inputId);
      const icon = document.getElementById(btnIconId);
      if (!input || !icon) return;

      const isText = input.type === "text";
      input.type = isText ? "password" : "text";

      // Cambiar icono a "eye off" cuando está visible
      icon.innerHTML = isText
        ? `
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        `
        : `
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.477 10.48a3 3 0 104.243 4.243"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.88 5.09A10.94 10.94 0 0112 5c4.477 0 8.268 2.943 9.542 7a11.04 11.04 0 01-4.12 5.27"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.23 6.23A11.04 11.04 0 002.458 12c1.274 4.057 5.065 7 9.542 7 1.335 0 2.62-.262 3.812-.74"/>
        `;
    }

    function validStrongPassword(pwd) {
      // ✅ 1 mayúscula, 1 número, 1 especial
      const hasUpper = /[A-Z]/.test(pwd);
      const hasNumber = /[0-9]/.test(pwd);
      const hasSpecial = /[^A-Za-z0-9]/.test(pwd);
      return hasUpper && hasNumber && hasSpecial;
    }

    function initForcePwdFlowbiteModal() {
      const modalEl = document.getElementById("forcePwdModal");
      if (!modalEl) return;

      const modal = new Modal(modalEl, {
        backdrop: "static",
        closable: false,
        placement: "center"
      });

      modal.show();
      renderFlowbiteAlert("info", "Completa los campos para actualizar tu contraseña.");

      // ✅ Ojitos
      document.getElementById("fp_actual_eye")?.addEventListener("click", () => toggleEye("fp_actual", "fp_actual_eye_icon"));
      document.getElementById("fp_nueva_eye")?.addEventListener("click", () => toggleEye("fp_nueva", "fp_nueva_eye_icon"));
      document.getElementById("fp_conf_eye")?.addEventListener("click", () => toggleEye("fp_conf", "fp_conf_eye_icon"));

      const btnSave = document.getElementById("forcePwdSaveBtn");
      if (!btnSave) return;

      btnSave.addEventListener("click", async () => {
        // ✅ IMPORTANTE: action por GET + POST para evitar el 400
        // const API_BASE = "src/controllers/usuario_controller.php";
        const ENDPOINT = "src/controllers/usuario_controller.php?accion=cambiar_password_obligatorio";


        const actual = (document.getElementById("fp_actual")?.value || "").trim();
        const nueva  = (document.getElementById("fp_nueva")?.value || "").trim();
        const conf   = (document.getElementById("fp_conf")?.value || "").trim();

        if (!actual || !nueva || !conf) {
          renderFlowbiteAlert("error", "Debes completar los 3 campos.");
          return;
        }

        if (nueva.length < 8) {
          renderFlowbiteAlert("error", "La nueva contraseña debe tener mínimo 8 caracteres.");
          return;
        }

        if (!validStrongPassword(nueva)) {
          renderFlowbiteAlert("error", "La nueva contraseña debe tener mínimo 1 número, 1 mayúscula y 1 caracter especial.");
          return;
        }

        if (nueva !== conf) {
          renderFlowbiteAlert("error", "La confirmación no coincide con la nueva contraseña.");
          return;
        }

        if (actual === nueva) {
          renderFlowbiteAlert("error", "La nueva contraseña no puede ser igual a la actual.");
          return;
        }

        setForcePwdLoading(true);

        try {
          const fd = new FormData();
fd.append("accion", "cambiar_password_obligatorio"); // ✅ CLAVE
fd.append("password_actual", actual);
fd.append("password_nueva", nueva);
fd.append("password_confirmacion", conf);

// (Opcional compat)
fd.append("action", "cambiar_password_obligatorio");


          const res = await fetch(ENDPOINT, {
            method: "POST",
            body: fd,
            headers: {
              "X-Requested-With": "XMLHttpRequest"
            }
          });

          // ✅ Intentar parsear seguro (por si el server manda HTML)
          const raw = await res.text();
          let data = null;

          try {
            data = JSON.parse(raw);
          } catch (e) {
            // Si no es JSON, mostrar error real
            renderFlowbiteAlert("error", "El servidor no devolvió JSON. Revisa warnings/errores en usuario_controller.php.");
            setForcePwdLoading(false);
            return;
          }

          if (!data.ok) {
            renderFlowbiteAlert("error", data.message || "No se pudo cambiar la contraseña.");
            setForcePwdLoading(false);
            return;
          }

          renderFlowbiteAlert("success", "Contraseña actualizada correctamente. Continuando...");

          setTimeout(() => {
            const url = new URL(window.location.href);
            url.searchParams.delete("force_pwd");
            window.location.href = url.toString();
          }, 900);

        } catch (err) {
          renderFlowbiteAlert("error", "Error de conexión. Intenta nuevamente.");
          setForcePwdLoading(false);
        }
      });
    }
  </script>

<?php endif; ?>




<!-- Lucide -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script src="src/assets/js/perfil/perfil.js"></script>

<style>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-6px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>
