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
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/globals.css">
  <style>
    .kpi-soft-warning { background-color: color-mix(in srgb, var(--warning) 40%, white); }
    .kpi-soft-critical { background-color: color-mix(in srgb, var(--error) 40%, white); }
    .kpi-soft-low { background-color: color-mix(in srgb, var(--foreground) 40%, white); }
    .kpi-soft-all { background-color: color-mix(in srgb, var(--success) 40%, white); }
    .kpi-soft-change { background-color: color-mix(in srgb, var(--info) 40%, white); }
    .alert-soft-warning { background-color: color-mix(in srgb, var(--warning) 30%, white); }
    .alert-soft-critical { background-color: color-mix(in srgb, var(--error) 30%, white); }
    .alert-soft-low { background-color: color-mix(in srgb, var(--foreground) 30%, white); }
    .alert-soft-change { background-color: color-mix(in srgb, var(--info) 30%, white); }
    .badge-soft-warning { background-color: color-mix(in srgb, var(--warning) 60%, white); border: 1px solid color-mix(in srgb, var(--warning) 70%, white); color: white !important; }
    .badge-soft-critical { background-color: color-mix(in srgb, var(--error) 60%, white); border: 1px solid color-mix(in srgb, var(--error) 70%, white); color: white !important; }
    .badge-soft-low { background-color: color-mix(in srgb, var(--foreground) 60%, white); border: 1px solid color-mix(in srgb, var(--foreground) 70%, white); color: white !important; }
    .badge-soft-change { background-color: color-mix(in srgb, var(--info) 60%, white); border: 1px solid color-mix(in srgb, var(--info) 70%, white); color: white !important; }
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
    .modal-content { background: white; border-radius: 12px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; }
    .cambio-datos-item { border-bottom: 1px solid #e5e7eb; padding: 12px 0; }
    .campo-label { font-weight: 600; color: #374151; min-width: 140px; }
    .btn-accion { padding: 8px 16px; border-radius: 6px; font-weight: 500; transition: all 0.2s; cursor: pointer; border: none; }
    .btn-aprobar { background-color: #10b981; color: white; }
    .btn-rechazar { background-color: #ef4444; color: white; margin-left: 8px; }
    .btn-neutro { background-color: #6b7280; color: white; }
  </style>
</head>

<body class="bg-background p-6">
<main class="p-6 transition-all duration-300" style="margin-left: <?= $sidebarWidth ?>;">

  <!-- TARJETAS KPI -->
  <div class="grid grid-cols-5 gap-4 mb-6">
    <?php
    $cards = [
      ['icon' => 'bell', 'label' => 'Total Notificaciones', 'value' => $stats['total'], 'soft' => 'kpi-soft-all', 'color' => 'var(--success)'],
      ['icon' => 'alert-triangle', 'label' => 'Sin Leer', 'value' => $stats['unread'], 'soft' => 'kpi-soft-warning', 'color' => 'var(--warning)'],
      ['icon' => 'alert-octagon', 'label' => 'Críticas', 'value' => $stats['critical'], 'soft' => 'kpi-soft-critical', 'color' => 'var(--error)'],
      ['icon' => 'box', 'label' => 'Stock Bajo', 'value' => $stats['low'], 'soft' => 'kpi-soft-low', 'color' => 'var(--foreground)'],
      ['icon' => 'user-cog', 'label' => 'Cambios Datos', 'value' => $stats['cambios'], 'soft' => 'kpi-soft-change', 'color' => 'var(--info)'],  
    ];
    foreach ($cards as $c): ?>
      <div class="bg-card rounded-xl p-4 shadow flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center <?= $c['soft'] ?>">
          <i data-lucide="<?= $c['icon'] ?>" class="w-5 h-5" style="color: <?= $c['color'] ?>;"></i>
        </div>
        <div>
          <p class="text-xs text-muted-foreground"><?= $c['label'] ?></p>
          <p class="text-xl font-semibold"><?= $c['value'] ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ALERTAS -->
  <div class="bg-card rounded-xl p-6 shadow">
    <h2 class="text-lg font-semibold mb-1">Todas las alertas</h2>
    <div class="space-y-4 mt-4">
      <?php foreach ($alerts as $a):
        $bgClass = "alert-soft-" . $a['type'];
        $badgeClass = "badge-soft-" . $a['type'];
        $icon = match($a['type']) {
          'warning'  => 'alert-triangle',
          'critical' => 'alert-octagon',
          'change'   => 'user-cog',
          default    => 'bell'
        };
        $label = match($a['type']) {
          'warning'  => 'Alta',
          'critical' => 'Crítica',
          'change'   => 'Cambio Datos',
          default    => 'General'
        };
      ?>
      <div class="flex items-start justify-between p-4 rounded-lg <?= $bgClass ?>">
        <div class="flex gap-3">
          <div class="mt-1"><i data-lucide="<?= $icon ?>" class="w-5 h-5"></i></div>
          <div class="flex-1">
            <p class="font-semibold"><?= htmlspecialchars($a['name'] ?? '') ?></p>
            <p class="text-xs text-muted-foreground">
              <?= htmlspecialchars($a['usuario_nombre'] ?? 'Sistema') ?> – <?= htmlspecialchars($a['code'] ?? 'N/A') ?>
            </p>
            <div class="mt-2 text-sm text-muted-foreground"><?= htmlspecialchars($a['descripcion'] ?? '') ?></div>
            <?php if ($a['type'] === 'change' && !empty($a['datos_cambio'])): ?>
              <div class="mt-2 p-2 bg-white/50 rounded text-xs">
                <strong>Solicitud de cambio:</strong>
                <button class="ml-2 text-blue-600 hover:text-blue-800 underline text-xs btn-gestionar-cambio"
                        data-notif-id="<?= $a['id'] ?>"
                        data-datos='<?= htmlspecialchars(json_encode($a['datos_cambio']), ENT_QUOTES, 'UTF-8') ?>'>
                  Ver detalles
                </button>
              </div>
            <?php endif; ?>
            <div class="flex items-center gap-3 mt-1">
              <span class="text-xs text-muted-foreground"><?= $a['time'] ?></span>
              <span class="text-xs px-2 py-0.5 rounded <?= $badgeClass ?>"><?= $label ?></span>
            </div>
          </div>
        </div>
        <div class="flex gap-2">
          <?php if ($a['type'] === 'change'): ?>
            <button class="btn-gestionar-cambio hover:bg-blue-100 p-2 rounded"
              data-notif-id="<?= $a['id'] ?>"
              data-datos='<?= htmlspecialchars(json_encode($a['datos_cambio']), ENT_QUOTES, 'UTF-8') ?>'>
              <i data-lucide="check-circle" class="w-5 h-5 text-blue-600"></i>
            </button>
          <?php endif; ?>
          <button class="btn-eliminar-notificacion hover:bg-red-100 p-2 rounded" data-notif-id="<?= $a['id'] ?>">
            <i data-lucide="trash-2" class="w-5 h-5 text-red-600"></i>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Modal -->
  <div id="modalCambioDatos" class="modal-overlay">
    <div class="modal-content p-6">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-slate-800">Detalles de Solicitud de Cambio</h3>
        <button id="cerrarModal" class="text-gray-500 hover:text-gray-700"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <div id="modalContenido" class="mb-4"></div>
      <div class="flex justify-end gap-2 mt-6">
        <button id="btnAprobarCambio" class="btn-accion btn-aprobar"><i data-lucide="check" class="w-4 h-4 mr-1"></i> Aprobar Cambio</button>
        <button id="btnRechazarCambio" class="btn-accion btn-rechazar"><i data-lucide="x" class="w-4 h-4 mr-1"></i> Rechazar</button>
        <button id="btnCerrarModal" class="btn-accion btn-neutro">Cerrar</button>
      </div>
    </div>
  </div>

  <script>
    lucide.createIcons();
    let notificacionActualId = null;

    const openModal = (btn) => {
        const rawData = btn.getAttribute('data-datos');
        console.log('🔍 Datos recibidos del botón:', rawData);
        
        try {
            const datos = JSON.parse(rawData || '{}');
            console.log('📊 Datos parseados:', datos);
            
            notificacionActualId = btn.getAttribute('data-notif-id');
            let html = '<div class="space-y-5">';
            
            // Verificar si datos es un objeto válido
            if (datos && typeof datos === 'object' && Object.keys(datos).length > 0) {
                // Dentro del bucle for...of de openModal
for (const [campo, info] of Object.entries(datos)) {
    if (info && typeof info === 'object') {
        const label = info.campo_nombre || campo.replace(/_/g, ' ').toUpperCase();
        
        // DEPURACIÓN: Ver qué llega realmente desde la base de datos
        console.log(`Campo: ${campo}`, {
            anterior: info.anterior,
            nuevo: info.nuevo
        });
        
        // TU LÓGICA CORREGIDA:
        const anterior = (info.anterior !== null && info.anterior !== undefined && info.anterior.toString().trim() !== "") 
            ? info.anterior 
            : "No especificado";
            
        const nuevo = (info.nuevo !== null && info.nuevo !== undefined && info.nuevo.toString().trim() !== "") 
            ? info.nuevo 
            : "Sin valor nuevo";

        // AGREGAR AL HTML (Manteniendo tus estilos)
        html += `
        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">${label}</p>
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-[9px] bg-slate-200 text-slate-500 px-1.5 py-0.5 rounded font-bold uppercase">Actual</span>
                    <span class="text-sm text-slate-400 line-through italic">${anterior}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[9px] bg-emerald-100 text-emerald-600 px-1.5 py-0.5 rounded font-bold uppercase">Nuevo</span>
                    <span class="text-sm font-bold text-slate-800">${nuevo}</span>
                </div>
            </div>
        </div>`;
    
                    }
                }
            } else {
                // Si no hay datos válidos
                html += `
                <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
                    <p class="text-sm text-yellow-700">
                        <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-2"></i>
                        No se encontraron detalles específicos del cambio.
                    </p>
                    <p class="text-xs text-yellow-600 mt-1">
                        Los datos recibidos están vacíos o no tienen la estructura esperada.
                    </p>
                </div>`;
            }
            
            html += '</div>';
            document.getElementById('modalContenido').innerHTML = html;
            document.getElementById('modalCambioDatos').style.display = 'flex';
            lucide.createIcons();
            
        } catch (error) {
            console.error('❌ Error procesando datos:', error);
            document.getElementById('modalContenido').innerHTML = `
                <div class="p-4 bg-red-50 border border-red-200 rounded-xl">
                    <p class="text-sm text-red-700">
                        <i data-lucide="alert-circle" class="w-4 h-4 inline mr-2"></i>
                        Error al procesar los datos: ${error.message}
                    </p>
                    <p class="text-xs text-red-600 mt-1">Datos crudos: ${rawData || 'VACÍO'}</p>
                </div>`;
            document.getElementById('modalCambioDatos').style.display = 'flex';
        }
    };

    // ... resto del código se mantiene igual
    document.querySelectorAll('.btn-gestionar-cambio').forEach(btn => btn.onclick = () => openModal(btn));
    document.querySelectorAll('.btn-ver-detalles').forEach(btn => btn.onclick = () => openModal(btn));

    document.getElementById('cerrarModal').onclick = () => document.getElementById('modalCambioDatos').style.display = 'none';
    document.getElementById('btnCerrarModal').onclick = () => document.getElementById('modalCambioDatos').style.display = 'none';

    async function procesarAccion(accion) {
        if (!notificacionActualId) return;
        const motivo = accion === 'rechazar_cambio_datos' ? prompt('Motivo del rechazo:') : '';
        if (accion === 'rechazar_cambio_datos' && motivo === null) return;
        
        const fd = new FormData();
        fd.append('notificacion_id', notificacionActualId);
        fd.append('motivo', motivo);
        
        const resp = await fetch(`src/controllers/usuario_controller.php?accion=${accion}`, { method: 'POST', body: fd });
        const res = await resp.json();
        if (res.success) { alert(res.message); location.reload(); }
    }

    document.getElementById('btnAprobarCambio').onclick = () => procesarAccion('aprobar_cambio_datos');
    document.getElementById('btnRechazarCambio').onclick = () => procesarAccion('rechazar_cambio_datos');
</script>
</main>
</body>
</html>