<?php
// ==========================
// HEADER DASHBOARD – PHP
// ==========================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si no hay usuario logueado, redirige al login (ajusta la ruta según tu estructura)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: src/view/login/login.php');
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

    <!-- Notificaciones -->
    <div class="relative group">
      <button class="relative flex h-9 w-9 items-center justify-center rounded-full hover:bg-muted/70 transition">
        <i data-lucide="bell" class="h-5 w-5 text-slate-500"></i>

        <?php if (count($mockAlerts) > 0): ?>
          <span class="absolute right-1.5 top-1.5 h-2.5 w-2.5 rounded-full bg-[#ff4b4b] ring-2 ring-card"></span>
        <?php endif; ?>
      </button>

      <div class="absolute right-0 mt-2 hidden w-80 rounded-md border border-border bg-card shadow-md group-hover:block">
        <div class="flex items-center justify-between px-3 py-2">
          <span class="text-sm font-semibold">Notificaciones</span>
          <span class="rounded-full bg-muted px-2 py-0.5 text-xs">
            <?php echo count($mockAlerts); ?>
          </span>
        </div>
        <hr class="border-border" />

        <?php if (count($mockAlerts) === 0): ?>
          <p class="px-3 py-3 text-xs text-muted-foreground">No hay notificaciones nuevas.</p>
        <?php else: ?>
          <div class="<?php echo $manyAlerts ? 'max-h-60 overflow-y-auto' : ''; ?>">
            <?php foreach ($mockAlerts as $alert): ?>
              <div class="flex flex-col gap-1 px-3 py-2 hover:bg-muted/50">
                <div class="flex items-center gap-2">
                  <span class="h-2 w-2 rounded-full bg-warning"></span>
                  <span class="text-xs font-medium">Stock bajo</span>
                </div>
                <p class="text-xs text-muted-foreground">
                  <?php echo $alert["material_nombre"]; ?>:
                  <?php echo $alert["stock_actual"]; ?>/<?php echo $alert["stock_minimo"]; ?> unidades
                </p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

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

    <!-- ✅ NUEVO: MÁS ESPACIO + LÍNEA DEBAJO DE LOS BOTONES (SIN DAÑAR TU BASE) -->
    <div class="absolute left-0 right-0 top-0 px-6 pt-5 md:px-8 z-10">
      <div class="flex items-center justify-end gap-2 pr-12">
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

      <!-- ✅ Línea debajo de los botones -->
      <div class="mt-4 border-b border-slate-200"></div>
    </div>

    <!-- ✅ Más padding arriba para separar botones de la info del usuario -->
    <div class="p-6 pt-24 md:p-8 md:pt-28">
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

      <form id="formEditarPerfil" method="post" action="#" class="space-y-6">
        <div>
          <h3 class="mb-3 text-sm font-semibold text-slate-800">Datos personales</h3>
          <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">

            <div>
              <label class="text-xs font-medium text-slate-400 block mb-1">Teléfono</label>
              <input
                type="text"
                name="telefono"
                value="<?php echo htmlspecialchars($profileData["telefono"], ENT_QUOTES, 'UTF-8'); ?>"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
              />
            </div>

            <div>
              <label class="text-xs font-medium text-slate-400 block mb-1">Dirección</label>
              <input
                type="text"
                name="direccion"
                value="<?php echo htmlspecialchars($profileData["direccion"], ENT_QUOTES, 'UTF-8'); ?>"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
              />
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-2 gap-3">
          <button
            type="button"
            id="btnCancelarPerfilEditar"
            class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
          >
            Cancelar
          </button>
          <button
            type="submit"
            id="btnGuardarPerfil"
            class="inline-flex items-center justify-center rounded-lg bg-secondary px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-secondary/90 transition"
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

      <form id="formDatosSensibles" class="space-y-3" method="post" action="#">
        <div id="field_nombre" class="hidden">
          <label class="text-xs font-medium text-slate-400 block mb-1">Nombre correcto</label>
          <input
            type="text"
            name="nombre_completo"
            value="<?php echo htmlspecialchars($profileData["nombre_completo"], ENT_QUOTES, 'UTF-8'); ?>"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
          />
        </div>

        <div id="field_tipo_documento" class="hidden">
          <label class="text-xs font-medium text-slate-400 block mb-1">Tipo de documento correcto</label>
          <select
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
        </div>

        <div id="field_numero_documento" class="hidden">
          <label class="text-xs font-medium text-slate-400 block mb-1">Número de documento correcto</label>
          <input
            type="text"
            name="numero_documento"
            value="<?php echo htmlspecialchars($profileData["numero_documento"], ENT_QUOTES, 'UTF-8'); ?>"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
          />
        </div>

        <div id="field_correo" class="hidden">
          <label class="text-xs font-medium text-slate-400 block mb-1">Correo correcto</label>
          <input
            type="email"
            name="correo"
            value="<?php echo htmlspecialchars($profileData["correo"], ENT_QUOTES, 'UTF-8'); ?>"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
          />
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

      <form id="formCambiarPassword" method="post" action="#">
        <div class="space-y-4 text-sm">
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Contraseña actual</label>
            <input
              type="password"
              name="password_actual"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
              placeholder="••••••••"
            />
          </div>

          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Nueva contraseña</label>
            <input
              type="password"
              name="password_nueva"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
              placeholder="••••••••"
            />
          </div>

          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Confirmar nueva contraseña</label>
            <input
              type="password"
              name="password_confirmar"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
              placeholder="••••••••"
            />
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

<!-- Lucide -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script src="src/assets/js/perfil/perfil.js"></script>
