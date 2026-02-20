<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. CONEXIÓN A LA BASE DE DATOS
require_once __DIR__ . '/../../../Config/database.php';

$id_usuario_logueado = $_SESSION['usuario_id'] ?? 0;
$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";

/* ===========================
   RESUMEN (KPI) DESDE BASE DE DATOS
   =========================== */
try {
    $stmtStats = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas,
            SUM(CASE WHEN tipo = 'STOCK_BAJO' THEN 1 ELSE 0 END) as stock_bajo,
            SUM(CASE WHEN tipo = 'CAMBIO_DATOS' THEN 1 ELSE 0 END) as cambios_datos
        FROM notificaciones 
        WHERE id_usuario = ?
    ");
    $stmtStats->execute([$id_usuario_logueado]);
    $resumenDB = $stmtStats->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $resumenDB = ['total' => 0, 'no_leidas' => 0, 'stock_bajo' => 0, 'cambios_datos' => 0];
}

$stats = [
  'total'    => $resumenDB['total'] ?? 0,
  'unread'   => $resumenDB['no_leidas'] ?? 0,
  'critical' => 0, 
  'low'      => $resumenDB['stock_bajo'] ?? 0,
  'cambios'  => $resumenDB['cambios_datos'] ?? 0
];

try {
    $stmtList = $conn->prepare("
        SELECT n.*, u.nombre_completo as usuario_nombre 
        FROM notificaciones n
        LEFT JOIN usuarios u ON n.referencia_id = u.id_usuario
        WHERE n.id_usuario = ? 
        ORDER BY n.fecha_creacion DESC 
        LIMIT 50
    ");
    $stmtList->execute([$id_usuario_logueado]);
    $notificacionesDB = $stmtList->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $notificacionesDB = [];
}

$alerts = array_map(function ($n) {

  $type = match ($n['tipo']) {
    'CAMBIO_DATOS' => 'change',
    'STOCK_BAJO'   => 'warning',
    'SOLICITUD_RECHAZADA' => 'critical',
    'ASIGNACION'   => 'assignment',

    'CAMBIO_DATOS_APROBADO' => 'success',
    'SOLICITUD_APROBADA'    => 'success',
    'CAMBIO_APROBADO'       => 'success',

    'CAMBIO_DATOS_RECHAZADO' => 'critical',
    'CAMBIO_RECHAZADO'       => 'critical',

    default        => 'low'
  };

  $esJson = strpos($n['mensaje'], '{') !== false;
  $descripcion_limpia = $n['mensaje'];

  if ($n['tipo'] === 'CAMBIO_DATOS' && $esJson) {
      $descripcion_limpia = "El usuario solicita actualizar información de su perfil personal.";
  }

  return [
    'id'             => $n['id_notificacion'],
    'name'           => $n['titulo'],
    'usuario_nombre' => $n['usuario_nombre'] ?? 'Sistema', 
    'code'           => 'REF-' . ($n['referencia_id'] ?? '0'), 
    'descripcion'    => $descripcion_limpia,
    'type'           => $type,
    'time'           => date('d/m/Y h:i a', strtotime($n['fecha_creacion'])),
    'leido'          => $n['leida'],
    'datos_cambio'   => json_decode($n['mensaje'], true)
  ];
}, $notificacionesDB);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Alertas Inventario</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- ✅ FLOWBITE -->
  <script src="https://unpkg.com/flowbite@2.5.1/dist/flowbite.min.js"></script>

  <link rel="stylesheet" href="<?= ASSETS_URL ?>css/globals.css">
</head>

<body class="bg-background p-6">

<!-- ✅ CONTENEDOR (lo dejamos por compatibilidad, pero ya no dependes SOLO de este) -->
<div id="toast-container-flowbite" class="fixed top-5 right-5 z-[9999] space-y-3"></div>

<main class="p-6 transition-all duration-300" style="margin-left: <?= $sidebarWidth ?>;">

  <!-- TARJETAS KPI -->
  <div class="grid grid-cols-5 gap-4 mb-6">
    <?php
    $cards = [
      ['icon' => 'bell', 'label' => 'Total Notificaciones', 'value' => $stats['total'], 'color' => 'var(--success)', 'soft' => 'bg-[color-mix(in_srgb,var(--success)_18%,white)]'],
      ['icon' => 'alert-triangle', 'label' => 'Sin Leer', 'value' => $stats['unread'], 'color' => 'var(--warning-foreground)', 'soft' => 'bg-[color-mix(in_srgb,var(--warning)_22%,white)]'],
      ['icon' => 'alert-octagon', 'label' => 'Críticas', 'value' => $stats['critical'], 'color' => 'var(--error)', 'soft' => 'bg-[color-mix(in_srgb,var(--error)_14%,white)]'],
      ['icon' => 'box', 'label' => 'Stock Bajo', 'value' => $stats['low'], 'color' => 'var(--chart-5)', 'soft' => 'bg-[color-mix(in_srgb,var(--chart-5)_18%,white)]'],
      ['icon' => 'user-cog', 'label' => 'Cambios Datos', 'value' => $stats['cambios'], 'color' => 'var(--info-foreground)', 'soft' => 'bg-[color-mix(in_srgb,var(--info)_22%,white)]'],  
    ];
    foreach ($cards as $c): 
      $kpiId = "kpi-" . $c["icon"];
    ?>
      <div class="bg-card rounded-xl p-4 shadow-sm border border-border flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center <?= $c['soft'] ?> border border-border">
          <i data-lucide="<?= $c['icon'] ?>" class="w-5 h-5" style="color: <?= $c['color'] ?>;"></i>
        </div>
        <div class="min-w-0">
          <p class="text-xs text-muted-foreground truncate"><?= $c['label'] ?></p>
          <p class="text-xl font-semibold text-foreground" id="<?= $kpiId ?>"><?= $c['value'] ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ALERTAS -->
  <div class="bg-card rounded-xl p-6 shadow-sm border border-border">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-foreground">Notificaciones</h2>
        <p class="text-xs text-muted-foreground mt-1">Listado reciente (últimas 50)</p>
      </div>
    </div>

    <div class="mt-5 space-y-3" id="lista-notificaciones-db">
      <?php foreach ($alerts as $a):

        $icon = match($a['type']) {
          'warning'  => 'alert-triangle',
          'critical' => 'alert-octagon',
          'change'   => 'user-cog',
          'assignment' => 'user-check',
          'success'  => 'check-circle',
          default    => 'bell'
        };

        $label = match($a['type']) {
          'warning'  => 'Stock Bajo',
          'critical' => 'Crítica',
          'change'   => 'Cambio Datos',
          'assignment' => 'Asignación',
          'success'  => 'Aprobada',
          default    => 'General'
        };

        $isUnread = (int)($a['leido'] ?? 1) === 0;

        $accentDot = match($a['type']) {
          'warning'  => 'bg-[var(--chart-5)]',
          'critical' => 'bg-[var(--error)]',
          'change'   => 'bg-[var(--info)]',
          'assignment' => 'bg-[var(--info)]',
          'success'  => 'bg-[var(--success)]',
          default    => 'bg-[var(--secondary)]'
        };

        $badgeClass = match($a['type']) {
          'warning'  => 'bg-[color-mix(in_srgb,var(--chart-5)_20%,white)] text-[var(--chart-5)] border border-border',
          'critical' => 'bg-[color-mix(in_srgb,var(--error)_14%,white)] text-[var(--error)] border border-border',
          'change'   => 'bg-[color-mix(in_srgb,var(--info)_22%,white)] text-foreground border border-border',
          'assignment' => 'bg-[color-mix(in_srgb,var(--info)_22%,white)] text-foreground border border-border',
          'success'  => 'bg-[color-mix(in_srgb,var(--success)_18%,white)] text-foreground border border-border',
          default    => 'bg-[color-mix(in_srgb,var(--secondary)_14%,white)] text-secondary border border-border'
        };

        $iconWrap = match($a['type']) {
          'warning'  => 'bg-[color-mix(in_srgb,var(--chart-5)_18%,white)] text-[var(--chart-5)]',
          'critical' => 'bg-[color-mix(in_srgb,var(--error)_14%,white)] text-[var(--error)]',
          'change'   => 'bg-[color-mix(in_srgb,var(--info)_22%,white)] text-foreground',
          'assignment' => 'bg-[color-mix(in_srgb,var(--info)_22%,white)] text-foreground',
          'success'  => 'bg-[color-mix(in_srgb,var(--success)_18%,white)] text-foreground',
          default    => 'bg-[color-mix(in_srgb,var(--secondary)_14%,white)] text-secondary'
        };

        $baseSoftBg = match($a['type']) {
          'warning'  => 'bg-[color-mix(in_srgb,var(--chart-5)_8%,white)]',
          'critical' => 'bg-[color-mix(in_srgb,var(--error)_8%,white)]',
          'change'   => 'bg-[color-mix(in_srgb,var(--info)_10%,white)]',
          'assignment' => 'bg-[color-mix(in_srgb,var(--info)_10%,white)]',
          'success'  => 'bg-[color-mix(in_srgb,var(--success)_10%,white)]',
          default    => 'bg-card'
        };

        $ringUnread = match($a['type']) {
          'warning'  => 'ring-2 ring-[color-mix(in_srgb,var(--chart-5)_26%,transparent)]',
          'critical' => 'ring-2 ring-[color-mix(in_srgb,var(--error)_24%,transparent)]',
          'change'   => 'ring-2 ring-[color-mix(in_srgb,var(--info)_26%,transparent)]',
          'assignment' => 'ring-2 ring-[color-mix(in_srgb,var(--info)_26%,transparent)]',
          'success'  => 'ring-2 ring-[color-mix(in_srgb,var(--success)_26%,transparent)]',
          default    => 'ring-2 ring-[color-mix(in_srgb,var(--primary)_25%,transparent)]'
        };

        // ✅ LEÍDA = GRIS CLARO
        $cardReadBg = 'bg-[color-mix(in_srgb,var(--foreground)_4%,white)] opacity-80';

        $cardState = $isUnread
          ? $baseSoftBg . ' ' . $ringUnread
          : $cardReadBg;

      ?>
      <div
        class="notif-card flex items-start justify-between gap-4 p-4 rounded-xl border border-border shadow-sm transition hover:shadow-md <?= $cardState ?>"
        data-notif-id="<?= $a['id'] ?>"
      >

        <div class="flex items-start gap-4 flex-1 min-w-0">
          <div class="flex items-center gap-3 flex-shrink-0">
            <span class="notif-dot w-2.5 h-2.5 rounded-full mt-1 <?= $accentDot ?>"></span>

            <div class="notif-iconwrap w-10 h-10 rounded-xl flex items-center justify-center border border-border <?= $iconWrap ?>">
              <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
            </div>
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-foreground truncate">
                  <?= htmlspecialchars($a['name'] ?? '') ?>
                </p>

                <div class="mt-1 flex items-center gap-2 flex-wrap text-xs text-muted-foreground">
                  <span class="truncate"><?= htmlspecialchars($a['usuario_nombre'] ?? 'Sistema') ?></span>
                  
                  <?php if ($isUnread): ?>
                    <span class="opacity-50">•</span>
                    <span class="notif-badge-new px-2 py-0.5 rounded-full bg-[color-mix(in_srgb,var(--primary)_18%,white)] border border-border text-[11px] font-semibold text-primary">
                      Nueva
                    </span>
                  <?php endif; ?>
                </div>
              </div>

              <?php if (($a['type'] ?? '') !== 'change'): ?>
                <span class="px-3 py-1 rounded-full text-[11px] font-semibold <?= $badgeClass ?>">
                  <?= $label ?>
                </span>
              <?php endif; ?>
            </div>

            <p class="mt-2 text-sm text-muted-foreground leading-relaxed line-clamp-2">
              <?= htmlspecialchars($a['descripcion'] ?? '') ?>
            </p>

            <?php if ($a['type'] === 'change' && !empty($a['datos_cambio'])): ?>
              <button
                type="button"
                class="btn-gestionar-cambio mt-3 w-full rounded-full border border-border bg-[#ffff] transition px-4 py-2 flex items-center justify-between gap-3"
                data-notif-id="<?= $a['id'] ?>"
                data-datos='<?= htmlspecialchars(json_encode($a['datos_cambio']), ENT_QUOTES, 'UTF-8') ?>'
                title="Ver solicitud de cambio"
              >
                <span class="flex items-center gap-2 min-w-0">
                  <i data-lucide="file-text" class="w-4 h-4 text-primary opacity-90"></i>
                  <span class="text-xs font-semibold text-foreground truncate">Solicitud de cambio</span>
                </span>

                <i data-lucide="chevron-right" class="w-4 h-4 text-muted-foreground opacity-70"></i>
              </button>
            <?php endif; ?>

            <div class="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
              <i data-lucide="clock" class="w-4 h-4 opacity-70"></i>
              <span><?= $a['time'] ?></span>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">

          <?php if ($a['type'] === 'change'): ?>
            <button
              type="button"
              class="btn-aprobar-directo w-9 h-9 rounded-full border border-border bg-transparent hover:bg-[color-mix(in_srgb,var(--primary)_10%,white)] transition flex items-center justify-center"
              data-notif-id="<?= $a['id'] ?>"
              title="Aprobar solicitud"
            >   
              <i data-lucide="check" class="w-4 h-4 text-primary opacity-80"></i>
            </button>
          <?php endif; ?>

          <button
            class="btn-eliminar-notificacion w-9 h-9 rounded-full border border-border bg-transparent hover:bg-[color-mix(in_srgb,var(--error)_10%,white)] transition flex items-center justify-center"
            data-notif-id="<?= $a['id'] ?>"
            title="Eliminar"
          >
            <i data-lucide="trash" class="w-4 h-4 text-[var(--error)] opacity-80"></i>
          </button>

        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($alerts)): ?>
        <div class="rounded-xl border border-border bg-card p-10 text-center">
          <div class="w-12 h-12 mx-auto rounded-xl border border-border bg-[color-mix(in_srgb,var(--primary)_12%,white)] flex items-center justify-center">
            <i data-lucide="bell-off" class="w-6 h-6 text-primary"></i>
          </div>
          <p class="mt-3 text-sm font-semibold text-foreground">No hay notificaciones</p>
          <p class="text-xs text-muted-foreground mt-1">Cuando el sistema genere alertas aparecerán aquí.</p>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- ✅ MODAL -->
  <div id="modalCambioDatos" class="fixed inset-0 z-[1000] hidden items-center justify-center bg-black/30 backdrop-blur-[2px] p-4">
    <div class="w-full max-w-[560px] bg-card rounded-2xl border border-border shadow-xl overflow-hidden">

      <div class="px-6 pt-6 pb-4 flex items-start justify-between gap-4">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-xl border border-border flex items-center justify-center">
            <i data-lucide="file-text" class="w-5 h-5 text-[var(--info-foreground)]"></i>
          </div>

          <div class="min-w-0">
            <h3 class="text-base font-semibold text-foreground">Solicitud de cambio</h3>
            <p class="text-xs text-muted-foreground mt-0.5">
              Revisa los datos actuales y los nuevos propuestos.
            </p>
          </div>
        </div>

        <button
          type="button"
          id="cerrarModal"
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

      <div class="h-px bg-[color-mix(in_srgb,var(--foreground)_10%,white)]"></div>

      <div class="px-6 py-5">
        <div id="modalContenido" class="space-y-4"></div>
      </div>

      <div class="px-6 pb-6 flex flex-col sm:flex-row justify-end gap-2">
        <button id="btnRechazarCambio"
          class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-muted">
          <span class="inline-flex items-center gap-2">
            <i data-lucide="x" class="w-4 h-4"></i> Rechazar
          </span>
        </button>

        <button id="btnAprobarCambio"
          class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:opacity-90">
          <span class="inline-flex items-center gap-2">
            <i data-lucide="check" class="w-4 h-4"></i> Aprobar
          </span>
        </button>
      </div>
    </div>
  </div>

  <!-- ✅ MODAL MOTIVO RECHAZO -->
  <div id="modalMotivoRechazo" class="fixed inset-0 z-[1100] hidden items-center justify-center bg-black/30 backdrop-blur-[2px] p-4">
    <div class="w-full max-w-[520px] bg-card rounded-2xl border border-border shadow-xl overflow-hidden">

      <div class="px-6 pt-6 pb-4 flex items-start justify-between gap-4">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-xl border border-border flex items-center justify-center
                      bg-[color-mix(in_srgb,var(--error)_10%,white)]">
            <i data-lucide="alert-octagon" class="w-5 h-5 text-[var(--error)]"></i>
          </div>

          <div class="min-w-0">
            <h3 class="text-base font-semibold text-foreground">Motivo del rechazo</h3>
            <p class="text-xs text-muted-foreground mt-0.5">
              Escribe el motivo para rechazar la solicitud.
            </p>
          </div>
        </div>

        <button id="cerrarModalMotivo"
          class="p-1 text-muted-foreground hover:text-foreground transition"
          title="Cerrar"
        >
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <div class="h-px bg-[color-mix(in_srgb,var(--foreground)_10%,white)]"></div>

      <div class="px-6 py-5 space-y-3">
        <label class="text-xs font-semibold text-foreground">Motivo</label>

        <textarea
          id="inputMotivoRechazo"
          rows="4"
          class="w-full rounded-xl border border-border bg-background px-4 py-3 text-sm text-foreground
                 focus:outline-none focus:ring-2 focus:ring-[color-mix(in_srgb,var(--error)_25%,transparent)]"
          placeholder="Ej: Datos inconsistentes, documento inválido, información incompleta..."
        ></textarea>

        <div class="flex items-center justify-between text-xs text-muted-foreground">
          <span id="motivoErrorMsg" class="hidden text-[var(--error)] font-semibold">
            Debes escribir un motivo para rechazar.
          </span>
          <span id="motivoCounter">0/250</span>
        </div>
      </div>

      <div class="px-6 pb-6 flex flex-col sm:flex-row justify-end gap-2">
        <button id="btnCancelarMotivo"
          class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-muted">
          Cancelar
        </button>

        <button id="btnEnviarRechazo"
          class="inline-flex items-center justify-center rounded-md bg-[var(--error)] px-4 py-2 text-sm font-medium text-[var(--error-foreground)] shadow hover:opacity-90">
          <span class="inline-flex items-center gap-2">
            <i data-lucide="x" class="w-4 h-4"></i> Enviar rechazo
          </span>
        </button>
      </div>

    </div>
  </div>

<script>
/* ============================================================
   ✅ SIGA GLOBAL TOASTS (MISMAS ALERTAS QUE PERFIL.JS)
   - Flowbite style
   - Anti-duplicate
   - No rompe tu base (solo UI)
============================================================ */
if (!window.__SIGA_TOASTS_READY__) {
  window.__SIGA_TOASTS_READY__ = true;

  let __lastToast = { msg: "", ts: 0 };

  function getOrCreateFlowbiteContainer() {
    // ✅ si existe tu contenedor viejo, lo usamos
    let container = document.getElementById("flowbite-alert-container");
    if (container) return container;

    // ✅ fallback: tu contenedor original (lo reutilizamos)
    const old = document.getElementById("toast-container-flowbite");
    if (old) {
      old.id = "flowbite-alert-container";
      old.className = "fixed top-6 right-6 z-[9999] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none";
      return old;
    }

    // ✅ si no existe ninguno, lo creamos
    container = document.createElement("div");
    container.id = "flowbite-alert-container";
    container.className =
      "fixed top-6 right-6 z-[9999] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none";
    document.body.appendChild(container);
    return container;
  }

  function ensureFadeAnimationClass() {
    if (document.getElementById("__siga_toast_anim__")) return;

    const st = document.createElement("style");
    st.id = "__siga_toast_anim__";
    st.textContent = `
      @keyframes sigaFadeUp {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0px); }
      }
      .animate-fade-in-up {
        animation: sigaFadeUp .25s ease-out forwards;
      }
    `;
    document.head.appendChild(st);
  }

  window.showFlowbiteAlert = function (type, a, b) {
    ensureFadeAnimationClass();

    let message = "";
    let titleText = "";

    if (typeof b === "undefined") {
      message = String(a || "");
    } else {
      titleText = String(a || "");
      message = String(b || "");
    }

    const now = Date.now();
    if (message === __lastToast.msg && now - __lastToast.ts < 900) return;
    __lastToast = { msg: message, ts: now };

    const container = getOrCreateFlowbiteContainer();
    const wrapper = document.createElement("div");

    let borderColor = "border-amber-500";
    let textColor = "text-amber-900";
    let title = titleText || "Advertencia";

    let iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
          fill="currentColor" viewBox="0 0 20 20">
        <path d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.59A1.75 1.75 0 0 1 16.768 17H3.232a1.75 1.75 0 0 1-1.492-2.311L8.257 3.1z"/>
        <path d="M11 13H9V9h2zm0 3H9v-2h2z" fill="#fff"/>
      </svg>
    `;

    if (type === "success") {
      borderColor = "border-emerald-500";
      textColor = "text-emerald-900";
      title = titleText || "Éxito";
      iconSVG = `
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
            fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm-1 15-4-4 1.414-1.414L9 12.172l4.586-4.586L15 9z"/>
        </svg>
      `;
    }

    if (type === "info") {
      borderColor = "border-blue-500";
      textColor = "text-blue-900";
      title = titleText || "Información";
      iconSVG = `
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
            fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm1 15H9v-5h2Zm0-7H9V6h2Z"/>
        </svg>
      `;
    }

    if (type === "danger" || type === "error") {
      borderColor = "border-red-500";
      textColor = "text-red-900";
      title = titleText || "Error";
      iconSVG = `
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
            fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm3.536 13.536-1.414 1.414L10 11.414 7.879 14.95l-1.414-1.414L8.586 10 6.465 7.879l1.414-1.414L10 8.586l2.121-2.121 1.414 1.414L11.414 10l2.122 3.536Z"/>
        </svg>
      `;
    }

    wrapper.className = `
      relative flex items-center w-full mx-auto pointer-events-auto
      rounded-2xl border-l-4 ${borderColor} bg-white shadow-md
      px-4 py-3 text-sm ${textColor}
      opacity-0 -translate-y-2
      transition-all duration-300 ease-out
      animate-fade-in-up
    `;

    wrapper.innerHTML = `
      <div class="flex-shrink-0 mr-3 text-current">
        ${iconSVG}
      </div>

      <div class="flex-1 min-w-0">
        <p class="font-semibold">${title}</p>
        <p class="mt-0.5 text-sm">${message}</p>
      </div>
    `;

    container.appendChild(wrapper);

    requestAnimationFrame(() => {
      wrapper.classList.remove("opacity-0", "-translate-y-2");
      wrapper.classList.add("opacity-100", "translate-y-0");
    });

    setTimeout(() => {
      wrapper.classList.add("opacity-0", "-translate-y-2");
      wrapper.classList.remove("opacity-100", "translate-y-0");
      setTimeout(() => wrapper.remove(), 250);
    }, 4000);
  };

  // ✅ helpers globales (MISMO NOMBRE QUE PERFIL)
  window.toastSuccess = (msg) => window.showFlowbiteAlert("success", msg);
  window.toastInfo    = (msg) => window.showFlowbiteAlert("info", msg);
  window.toastDanger  = (msg) => window.showFlowbiteAlert("danger", msg);
  window.toastWarning = (msg) => window.showFlowbiteAlert("warning", msg);
}

// =====================================================
// ✅ COMPATIBILIDAD TOTAL:
// Tu mostrarMensaje(tipo,mensaje) sigue existiendo,
// pero ahora usa EXACTAMENTE el toast de perfil.js
// =====================================================
function mostrarMensaje(tipo, mensaje) {
  const t = String(tipo || "").toLowerCase();

  if (t === "success") return toastSuccess(mensaje);
  if (t === "info")    return toastInfo(mensaje);
  if (t === "warning") return toastWarning(mensaje);

  // cualquier error/danger cae aquí
  return toastDanger(mensaje);
}

lucide.createIcons();
let notificacionActualId = null;

/* ======================================================
   ✅ VISUAL GRIS CLARO AL VER/APROBAR/RECHAZAR
====================================================== */
function getNotifCard(notifId) {
  return document.querySelector(`.notif-card[data-notif-id="${notifId}"]`);
}

function setCardReadVisual(notifId) {
  const card = getNotifCard(notifId);
  if (!card) return;

  card.classList.add("bg-[color-mix(in_srgb,var(--foreground)_4%,white)]", "opacity-80");

  // ✅ quita badge "Nueva"
  const badgeNew = card.querySelector(".notif-badge-new");
  if (badgeNew) badgeNew.remove();

  // ✅ atenúa puntico e icono
  const dot = card.querySelector(".notif-dot");
  dot && dot.classList.add("opacity-40");

  const iconWrap = card.querySelector(".notif-iconwrap");
  iconWrap && iconWrap.classList.add("opacity-60");
}

const openModal = (btn) => {
  const rawData = btn.getAttribute('data-datos');

  try {
    const datos = JSON.parse(rawData || '{}');
    notificacionActualId = btn.getAttribute('data-notif-id');

    // ✅ AL VER EL POPUP -> GRIS
    if (notificacionActualId) setCardReadVisual(notificacionActualId);

    let html = '<div class="space-y-4">';

    if (datos && typeof datos === 'object' && Object.keys(datos).length > 0) {
      for (const [campo, info] of Object.entries(datos)) {
        if (info && typeof info === 'object') {
          const label = info.campo_nombre || campo.replace(/_/g, ' ').toUpperCase();

          const anterior = (info.anterior !== null && info.anterior !== undefined && info.anterior.toString().trim() !== "")
            ? info.anterior
            : "No especificado";

          const nuevo = (info.nuevo !== null && info.nuevo !== undefined && info.nuevo.toString().trim() !== "")
            ? info.nuevo
            : "Sin valor nuevo";

          html += `
          <div class="p-4 rounded-2xl border border-border">
            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest mb-3">${label}</p>

            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <span class="text-[10px] px-2 py-0.5 rounded-full border border-border bg-[color-mix(in_srgb,var(--foreground)_6%,white)] text-foreground font-semibold">
                  Actual
                </span>
                <span class="text-sm text-muted-foreground">${anterior}</span>
              </div>

              <hr/>

              <div class="flex items-center gap-2">
                <span class="text-[10px] px-2 py-0.5 rounded-full border border-border bg-[color-mix(in_srgb,var(--primary)_18%,white)] text-primary font-semibold">
                  Nuevo
                </span>
                <span class="text-sm font-semibold text-foreground">${nuevo}</span>
              </div>
            </div>
          </div>`;
        }
      }
    } else {
      html += `
      <div class="p-4 rounded-2xl border border-border bg-[color-mix(in_srgb,var(--warning)_18%,white)]">
        <p class="text-sm text-foreground">No se encontraron detalles específicos del cambio.</p>
      </div>`;
    }

    html += '</div>';
    document.getElementById('modalContenido').innerHTML = html;

    const modal = document.getElementById('modalCambioDatos');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    lucide.createIcons();

  } catch (error) {
    document.getElementById('modalContenido').innerHTML = `
      <div class="p-4 rounded-2xl border border-border bg-[color-mix(in_srgb,var(--error)_12%,white)]">
        <p class="text-sm text-foreground">Error al procesar los datos</p>
      </div>`;

    const modal = document.getElementById('modalCambioDatos');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
};

async function eliminarNotificacion(notificacionId) {
  if (!confirm('¿Estás seguro de que deseas eliminar esta notificación?')) return;

  try {
    const fd = new FormData();
    fd.append('notificacion_id', notificacionId);

    const response = await fetch('src/controllers/usuario_controller.php?accion=eliminar_notificacion', {
      method: 'POST',
      body: fd
    });

    const result = await response.json();

    if (result.success) {
      const card = getNotifCard(notificacionId);
      mostrarMensaje('success', result.message);
      if (card) {
        card.classList.add("opacity-60");
        card.style.pointerEvents = "none";
        setTimeout(() => {
          card.remove();
          actualizarContadores();

          const restantes = document.querySelectorAll(".notif-card").length;
          if (restantes === 0) {
            const list = document.getElementById("lista-notificaciones-db");
            if (list) {
              list.innerHTML = `
                <div class="rounded-xl border border-border bg-card p-10 text-center">
                  <div class="w-12 h-12 mx-auto rounded-xl border border-border bg-[color-mix(in_srgb,var(--primary)_12%,white)] flex items-center justify-center">
                    <i data-lucide="bell-off" class="w-6 h-6 text-primary"></i>
                  </div>
                  <p class="mt-3 text-sm font-semibold text-foreground">No hay notificaciones</p>
                  <p class="text-xs text-muted-foreground mt-1">Cuando el sistema genere alertas aparecerán aquí.</p>
                </div>
              `;
              if (window.lucide) lucide.createIcons();
            }
          }
        }, 3000);
      } else {
        actualizarContadores();
      }
    } else {
      mostrarMensaje('error', result.message);
    }
  } catch (error) {
    mostrarMensaje('error', 'Error al eliminar la notificación');
  }
}

function actualizarContadores() {
  const notificacionesRestantes = document.querySelectorAll('.notif-card').length;
  const totalElement = document.querySelector('.text-xl.font-semibold:first-of-type');
  if (totalElement) totalElement.textContent = notificacionesRestantes;
}

/* ======================================================
   ✅ FIX SIN TOCAR TU BASE:
   - bindNotifEvents() ahora EXISTE
   - usa delegación para que funcione con LIVE REFRESH
   - NO rompe tu diseño ni tu HTML
====================================================== */
function bindNotifEvents() {
  const LIST_CONT = document.getElementById("lista-notificaciones-db");
  if (!LIST_CONT) return;

  // ✅ evita duplicar listener
  if (LIST_CONT.__eventsBound) return;
  LIST_CONT.__eventsBound = true;

  LIST_CONT.addEventListener("click", async (e) => {
    // ✅ 1) Ver detalles (Solicitud de cambio)
    const btnDetalle = e.target.closest(".btn-gestionar-cambio, .btn-ver-detalles");
    if (btnDetalle) {
      e.preventDefault();
      openModal(btnDetalle);
      return;
    }

    // ✅ 2) Eliminar notificación
    const btnEliminar = e.target.closest(".btn-eliminar-notificacion");
    if (btnEliminar) {
      e.preventDefault();
      const notificacionId = btnEliminar.getAttribute("data-notif-id");
      if (notificacionId) eliminarNotificacion(notificacionId);
      return;
    }

    // ✅ 3) Aprobar directo
    const btnAprobar = e.target.closest(".btn-aprobar-directo");
    if (btnAprobar) {
      e.preventDefault();
      const notifId = btnAprobar.getAttribute("data-notif-id");
      if (!notifId) return;

      notificacionActualId = notifId;

      // ✅ AL APROBAR -> GRIS
      setCardReadVisual(notificacionActualId);

      await procesarAccion("aprobar_cambio_datos");
      return;
    }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  // ✅ activa binds compatibles con LIVE refresh
  bindNotifEvents();

  // ✅ Cerrar modal principal
  document.getElementById('cerrarModal').onclick = () => {
    const modal = document.getElementById('modalCambioDatos');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  };

  // ✅ Aprobar desde modal
  document.getElementById('btnAprobarCambio').onclick = async () => {
    if (notificacionActualId) setCardReadVisual(notificacionActualId);
    await procesarAccion('aprobar_cambio_datos');
  };

  // ✅ Rechazar desde modal
  document.getElementById('btnRechazarCambio').onclick = () => abrirModalMotivo();

  iniciarNotificacionesLive();
});

async function procesarAccion(accion, motivoManual = "") {
  if (!notificacionActualId) return;

  const fd = new FormData();
  fd.append('notificacion_id', notificacionActualId);

  if (accion === 'rechazar_cambio_datos') {
    fd.append('motivo', motivoManual || "");
  }

  try {
    const resp = await fetch(`src/controllers/usuario_controller.php?accion=${accion}`, {
      method: 'POST',
      body: fd
    });

    const res = await resp.json();

    if (res.success) {
      // ✅ AL APROBAR O RECHAZAR -> GRIS
      setCardReadVisual(notificacionActualId);

      mostrarMensaje('success', res.message);
      setTimeout(() => location.reload(), 1000);
    } else {
      mostrarMensaje('error', res.message);
    }
  } catch (e) {
    mostrarMensaje('error', 'Error al procesar la acción');
  }
}

// =====================================================
// ✅ MODAL MOTIVO RECHAZO
// =====================================================
function abrirModalMotivo() {
  const modal = document.getElementById("modalMotivoRechazo");
  const input = document.getElementById("inputMotivoRechazo");
  const errorMsg = document.getElementById("motivoErrorMsg");
  const counter = document.getElementById("motivoCounter");

  if (!modal || !input) return;

  input.value = "";
  errorMsg?.classList.add("hidden");
  counter && (counter.textContent = "0/250");

  modal.classList.remove("hidden");
  modal.classList.add("flex");

  setTimeout(() => input.focus(), 60);

  if (window.lucide) lucide.createIcons();
}

function cerrarModalMotivo() {
  const modal = document.getElementById("modalMotivoRechazo");
  if (!modal) return;

  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

document.getElementById("cerrarModalMotivo")?.addEventListener("click", cerrarModalMotivo);
document.getElementById("btnCancelarMotivo")?.addEventListener("click", cerrarModalMotivo);

document.getElementById("modalMotivoRechazo")?.addEventListener("click", (e) => {
  if (e.target && e.target.id === "modalMotivoRechazo") cerrarModalMotivo();
});

document.getElementById("inputMotivoRechazo")?.addEventListener("input", (e) => {
  const input = e.target;
  const counter = document.getElementById("motivoCounter");
  const errorMsg = document.getElementById("motivoErrorMsg");

  if (input.value.length > 250) input.value = input.value.slice(0, 250);
  counter && (counter.textContent = `${input.value.length}/250`);

  if (input.value.trim().length > 0) errorMsg?.classList.add("hidden");
});

document.getElementById("btnEnviarRechazo")?.addEventListener("click", async () => {
  const input = document.getElementById("inputMotivoRechazo");
  const errorMsg = document.getElementById("motivoErrorMsg");

  const motivo = (input?.value || "").trim();

  if (!motivo) {
    errorMsg?.classList.remove("hidden");
    input?.focus();
    return;
  }

  // ✅ AL RECHAZAR -> GRIS
  if (notificacionActualId) setCardReadVisual(notificacionActualId);

  cerrarModalMotivo();
  await procesarAccion("rechazar_cambio_datos", motivo);
});

/* ======================================================
   ✅ LIVE REFRESH NOTIFICACIONES (SIN RECARGAR)
====================================================== */
function iniciarNotificacionesLive() {
  if (window.__notificacionesLiveStarted) return;
  window.__notificacionesLiveStarted = true;

  const KPI_TOTAL   = document.getElementById("kpi-bell");
  const KPI_UNREAD  = document.getElementById("kpi-alert-triangle");
  const KPI_CRIT    = document.getElementById("kpi-alert-octagon");
  const KPI_LOW     = document.getElementById("kpi-box");
  const KPI_CAMBIOS = document.getElementById("kpi-user-cog");

  const LIST_CONT = document.getElementById("lista-notificaciones-db");
  let lastSignature = "";

  const safeJSONParse = (str) => { try { return JSON.parse(str); } catch { return null; } };

  const escapeHTML = (str) => {
    return String(str ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  };

  const formatFecha = (raw) => {
    try {
      const d = new Date(raw);
      if (isNaN(d.getTime())) return "";
      const dd = String(d.getDate()).padStart(2, "0");
      const mm = String(d.getMonth() + 1).padStart(2, "0");
      const yy = d.getFullYear();
      let hh = d.getHours();
      const min = String(d.getMinutes()).padStart(2, "0");
      const ampm = hh >= 12 ? "pm" : "am";
      hh = hh % 12 || 12;
      return `${dd}/${mm}/${yy} ${hh}:${min} ${ampm}`;
    } catch { return ""; }
  };

  const mapTipoToUI = (tipoDB) => {
    const t = String(tipoDB || "").toUpperCase();
    if (t === "CAMBIO_DATOS") return "change";
    if (t === "STOCK_BAJO") return "warning";
    if (t === "SOLICITUD_RECHAZADA") return "critical";
    if (t === "ASIGNACION") return "assignment";
    if (t === "CAMBIO_DATOS_RECHAZADO") return "critical";
    if (t === "CAMBIO_RECHAZADO") return "critical";
    if (t === "CAMBIO_DATOS_APROBADO") return "success";
    if (t === "SOLICITUD_APROBADA") return "success";
    if (t === "CAMBIO_APROBADO") return "success";
    return "low";
  };

  const buildUI = (type, isUnread) => {
    const accentDot = {
      warning:  'bg-[var(--chart-5)]',
      critical: 'bg-[var(--error)]',
      change:   'bg-[var(--info)]',
      assignment: 'bg-[var(--info)]',
      success:  'bg-[var(--success)]',
      low:      'bg-[var(--secondary)]'
    }[type] || 'bg-[var(--secondary)]';

    const icon = {
      warning:  'alert-triangle',
      critical: 'alert-octagon',
      change:   'user-cog',
      assignment: 'user-check',
      success:  'check-circle',
      low:      'bell'
    }[type] || 'bell';

    const label = {
      warning:  'Stock Bajo',
      critical: 'Crítica',
      change:   'Cambio Datos',
      assignment: 'Asignación',
      success:  'Aprobada',
      low:      'General'
    }[type] || 'General';

    const badgeClass = {
      warning:  'bg-[color-mix(in_srgb,var(--chart-5)_20%,white)] text-[var(--chart-5)] border border-border',
      critical: 'bg-[color-mix(in_srgb,var(--error)_14%,white)] text-[var(--error)] border border-border',
      change:   'bg-[color-mix(in_srgb,var(--info)_22%,white)] text-foreground border border-border',
      assignment: 'bg-[color-mix(in_srgb,var(--info)_22%,white)] text-foreground border border-border',
      success:  'bg-[color-mix(in_srgb,var(--success)_18%,white)] text-foreground border border-border',
      low:      'bg-[color-mix(in_srgb,var(--secondary)_14%,white)] text-secondary border border-border'
    }[type] || 'bg-[color-mix(in_srgb,var(--secondary)_14%,white)] text-secondary border border-border';

    const iconWrap = {
      warning:  'bg-[color-mix(in_srgb,var(--chart-5)_18%,white)] text-[var(--chart-5)]',
      critical: 'bg-[color-mix(in_srgb,var(--error)_14%,white)] text-[var(--error)]',
      change:   'bg-[color-mix(in_srgb,var(--info)_22%,white)] text-foreground',
      assignment: 'bg-[color-mix(in_srgb,var(--info)_22%,white)] text-foreground',
      success:  'bg-[color-mix(in_srgb,var(--success)_18%,white)] text-foreground',
      low:      'bg-[color-mix(in_srgb,var(--secondary)_14%,white)] text-secondary'
    }[type] || 'bg-[color-mix(in_srgb,var(--secondary)_14%,white)] text-secondary';

    const baseSoftBg = {
      warning:  'bg-[color-mix(in_srgb,var(--chart-5)_8%,white)]',
      critical: 'bg-[color-mix(in_srgb,var(--error)_8%,white)]',
      change:   'bg-[color-mix(in_srgb,var(--info)_10%,white)]',
      assignment: 'bg-[color-mix(in_srgb,var(--info)_10%,white)]',
      success:  'bg-[color-mix(in_srgb,var(--success)_10%,white)]',
      low:      'bg-card'
    }[type] || 'bg-card';

    const ringUnread = {
      warning:  'ring-2 ring-[color-mix(in_srgb,var(--chart-5)_26%,transparent)]',
      critical: 'ring-2 ring-[color-mix(in_srgb,var(--error)_24%,transparent)]',
      change:   'ring-2 ring-[color-mix(in_srgb,var(--info)_26%,transparent)]',
      assignment: 'ring-2 ring-[color-mix(in_srgb,var(--info)_26%,transparent)]',
      success:  'ring-2 ring-[color-mix(in_srgb,var(--success)_26%,transparent)]',
      low:      'ring-2 ring-[color-mix(in_srgb,var(--primary)_25%,transparent)]'
    }[type] || 'ring-2 ring-[color-mix(in_srgb,var(--primary)_25%,transparent)]';

    const readSoft = "bg-[color-mix(in_srgb,var(--foreground)_4%,white)] opacity-80";

    const cardState = isUnread ? `${baseSoftBg} ${ringUnread}` : readSoft;
    return { accentDot, icon, label, badgeClass, iconWrap, cardState };
  };

  const renderList = (rows) => {
    if (!LIST_CONT) return;

    let html = "";

    rows.forEach((n) => {
      const idNotif = n.id_notificacion;
      const titulo  = n.titulo || "";
      const usuario = n.usuario_nombre || "Sistema";
      const tipoUI  = mapTipoToUI(n.tipo);
      const isUnread = Number(n.leida) === 0;

      let descripcion = n.mensaje || "";
      const posibleJson = safeJSONParse(n.mensaje);

      if (String(n.tipo || "").toUpperCase() === "CAMBIO_DATOS" && posibleJson) {
        descripcion = "El usuario solicita actualizar información de su perfil personal.";
      }

      const datosCambio = (tipoUI === "change" && posibleJson) ? posibleJson : null;
      const ui = buildUI(tipoUI, isUnread);
      const fecha = formatFecha(n.fecha_creacion);

      html += `
        <div class="notif-card flex items-start justify-between gap-4 p-4 rounded-xl border border-border shadow-sm transition hover:shadow-md ${ui.cardState}"
             data-notif-id="${idNotif}">

          <div class="flex items-start gap-4 flex-1 min-w-0">
            <div class="flex items-center gap-3 flex-shrink-0">
              <span class="notif-dot w-2.5 h-2.5 rounded-full mt-1 ${ui.accentDot}"></span>

              <div class="notif-iconwrap w-10 h-10 rounded-xl flex items-center justify-center border border-border ${ui.iconWrap}">
                <i data-lucide="${ui.icon}" class="w-5 h-5"></i>
              </div>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-foreground truncate">${escapeHTML(titulo)}</p>

                  <div class="mt-1 flex items-center gap-2 flex-wrap text-xs text-muted-foreground">
                    <span class="truncate">${escapeHTML(usuario)}</span>

                    ${isUnread ? `
                      <span class="opacity-50">•</span>
                      <span class="notif-badge-new px-2 py-0.5 rounded-full bg-[color-mix(in_srgb,var(--primary)_18%,white)] border border-border text-[11px] font-semibold text-primary">
                        Nueva
                      </span>
                    ` : ``}
                  </div>
                </div>

                ${tipoUI !== "change" ? `
                  <span class="px-3 py-1 rounded-full text-[11px] font-semibold ${ui.badgeClass}">
                    ${escapeHTML(ui.label)}
                  </span>
                ` : ``}
              </div>

              <p class="mt-2 text-sm text-muted-foreground leading-relaxed line-clamp-2">${escapeHTML(descripcion)}</p>

              ${tipoUI === "change" && datosCambio ? `
                <button
                  type="button"
                  class="btn-gestionar-cambio mt-3 w-full rounded-full border border-border bg-[#ffff] transition px-4 py-2 flex items-center justify-between gap-3"
                  data-notif-id="${idNotif}"
                  data-datos='${escapeHTML(JSON.stringify(datosCambio))}'
                  title="Ver solicitud de cambio"
                >
                  <span class="flex items-center gap-2 min-w-0">
                    <i data-lucide="file-text" class="w-4 h-4 text-primary opacity-90"></i>
                    <span class="text-xs font-semibold text-foreground truncate">Solicitud de cambio</span>
                  </span>
                  <i data-lucide="chevron-right" class="w-4 h-4 text-muted-foreground opacity-70"></i>
                </button>
              ` : ``}

              <div class="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                <i data-lucide="clock" class="w-4 h-4 opacity-70"></i>
                <span>${escapeHTML(fecha)}</span>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2 flex-shrink-0">
            ${tipoUI === "change" ? `
              <button
                type="button"
                class="btn-aprobar-directo w-9 h-9 rounded-full border border-border bg-transparent hover:bg-[color-mix(in_srgb,var(--primary)_10%,white)] transition flex items-center justify-center"
                data-notif-id="${idNotif}"
                title="Aprobar solicitud"
              >
                <i data-lucide="check" class="w-4 h-4 text-primary opacity-80"></i>
              </button>
            ` : ``}

            <button
              class="btn-eliminar-notificacion w-9 h-9 rounded-full border border-border bg-transparent hover:bg-[color-mix(in_srgb,var(--error)_10%,white)] transition flex items-center justify-center"
              data-notif-id="${idNotif}"
              title="Eliminar"
            >
              <i data-lucide="trash" class="w-4 h-4 text-[var(--error)] opacity-80"></i>
            </button>
          </div>
        </div>
      `;
    });

    LIST_CONT.innerHTML = html;
    lucide.createIcons();

    // ✅ AHORA SÍ EXISTE Y NO ROMPE NADA
    bindNotifEvents();
  };

  const actualizarKPIsCritical = (rows) => {
    const criticalCount = rows.filter(r => mapTipoToUI(r.tipo) === "critical").length;
    if (KPI_CRIT) KPI_CRIT.textContent = String(criticalCount);
  };

  const refrescarStats = async () => {
    try {
      const resp = await fetch("src/utils/notificaciones_db_live.php?accion=stats", { cache: "no-store" });
      const data = await resp.json();
      if (!data.success) return;

      if (KPI_TOTAL)   KPI_TOTAL.textContent   = String(data.stats.total ?? 0);
      if (KPI_UNREAD)  KPI_UNREAD.textContent  = String(data.stats.unread ?? 0);
      if (KPI_LOW)     KPI_LOW.textContent     = String(data.stats.low ?? 0);
      if (KPI_CAMBIOS) KPI_CAMBIOS.textContent = String(data.stats.cambios ?? 0);
    } catch (e) {}
  };

  const refrescarList = async () => {
    try {
      const resp = await fetch("src/utils/notificaciones_db_live.php?accion=list&limit=50", { cache: "no-store" });
      const data = await resp.json();
      if (!data.success) return;

      const signature = JSON.stringify(
        (data.notificaciones || []).map(n => `${n.id_notificacion}-${n.leida}`)
      );

      if (signature === lastSignature) return;
      lastSignature = signature;

      renderList(data.notificaciones || []);
      actualizarKPIsCritical(data.notificaciones || []);
    } catch (e) {}
  };

  refrescarStats();
  refrescarList();

  setInterval(() => {
    if (document.hidden) return;
    refrescarStats();
    refrescarList();
  }, 2000);
}
</script>

</main>
</body>
</html>
