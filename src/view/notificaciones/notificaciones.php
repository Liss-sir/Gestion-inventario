<?php

$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";

require_once 'src/utils/notificaciones_sin_db.php';

/* ===========================
   RESUMEN (KPI)
   =========================== */
$resumen = NotificacionSesion::obtenerResumen();

$stats = [
  'total'    => $resumen['total'],
  'unread'   => $resumen['no_leidas'],
  'critical' => $resumen['por_color']['danger'] ?? 0,
  'low'      => $resumen['por_color']['warning'] ?? 0,
  'cambios'  => $resumen['por_tipo']['solicitud_cambio_datos'] ?? 0  // Nuevo KPI
];

/* ===========================
   ALERTAS (LISTADO)
   =========================== */
$notificaciones = NotificacionSesion::obtenerNotificaciones(null, 50);

/*
 Mapear notificaciones → estructura que el diseño ya usa
*/
$alerts = array_map(function ($n) {
  
  // Determinar tipo de alerta basado en color y tipo
  if ($n['tipo'] === 'solicitud_cambio_datos') {
    $type = 'change';  // Nuevo tipo para cambios de datos
  } else {
    $type = match ($n['color']) {
      'danger'  => 'critical',
      'warning' => 'warning',
      default   => 'low'
    };
  }

  return [
    'name'           => $n['titulo'],
    'status'         => ucfirst(str_replace('_', ' ', $n['tipo'])),
    'value'          => '—',
    'code'           => 'USR-' . $n['usuario_id'],
    'usuario_nombre' => $n['usuario_nombre'] ?? ('Usuario ID ' . $n['usuario_id']),
    'descripcion'    => $n['descripcion'] ?? '',
    'type'           => $type,
    'time'           => $n['fecha'],
    'id'             => $n['id'],
    'leido'          => $n['leido'],
    'tipo_original'  => $n['tipo'],  // Mantener tipo original
    'datos_cambio'   => $n['datos_adicionales'] ?? null  // Datos específicos del cambio
  ];
}, $notificaciones);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Alertas Inventario</title>

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- CSS Global -->
  <link rel="stylesheet" href="../../assets/css/globals.css">

  <!-- Estilos suaves usando SOLO variables -->
  <style>
    .kpi-soft-warning {
      background-color: color-mix(in srgb, var(--warning) 40%, white);
    }
    .kpi-soft-critical {
      background-color: color-mix(in srgb, var(--error) 40%, white);
    }
    .kpi-soft-low {
      background-color: color-mix(in srgb, var(--foreground) 40%, white);
    }
    .kpi-soft-all {
      background-color: color-mix(in srgb, var(--success) 40%, white);
    }
    .kpi-soft-change {
      background-color: color-mix(in srgb, var(--info) 40%, white);  /* Nuevo para cambios */
    }

    .alert-soft-warning {
      background-color: color-mix(in srgb, var(--warning) 30%, white);
    }
    .alert-soft-critical {
      background-color: color-mix(in srgb, var(--error) 30%, white);
    }
    .alert-soft-low {
      background-color: color-mix(in srgb, var(--foreground) 30%, white);
    }
    .alert-soft-change {
      background-color: color-mix(in srgb, var(--info) 30%, white);  /* Nuevo para cambios */
    }

    .badge-soft-warning {
      background-color: color-mix(in srgb, var(--warning) 60%, white);
      border: 1px solid color-mix(in srgb, var(--warning) 70%, white);
      color: white !important;
    }

    .badge-soft-critical {
      background-color: color-mix(in srgb, var(--error) 60%, white);
      border: 1px solid color-mix(in srgb, var(--error) 70%, white);
      color: white !important;
    }

    .badge-soft-low {
      background-color: color-mix(in srgb, var(--foreground) 60%, white);
      border: 1px solid color-mix(in srgb, var(--foreground) 70%, white);
      color: white !important;
    }

    .badge-soft-change {
      background-color: color-mix(in srgb, var(--info) 60%, white);
      border: 1px solid color-mix(in srgb, var(--info) 70%, white);
      color: white !important;
    }

    /* Estilos para el modal */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background: white;
      border-radius: 12px;
      max-width: 500px;
      width: 90%;
      max-height: 80vh;
      overflow-y: auto;
    }

    .cambio-datos-item {
      border-bottom: 1px solid #e5e7eb;
      padding: 12px 0;
    }

    .cambio-datos-item:last-child {
      border-bottom: none;
    }

    .campo-label {
      font-weight: 600;
      color: #374151;
      min-width: 140px;
    }

    .campo-valor {
      background: #f9fafb;
      padding: 8px 12px;
      border-radius: 6px;
      margin: 4px 0;
      border: 1px solid #e5e7eb;
    }

    .btn-accion {
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 500;
      transition: all 0.2s;
      cursor: pointer;
      border: none;
    }

    .btn-aprobar {
      background-color: #10b981;
      color: white;
    }

    .btn-aprobar:hover {
      background-color: #059669;
    }

    .btn-rechazar {
      background-color: #ef4444;
      color: white;
      margin-left: 8px;
    }

    .btn-rechazar:hover {
      background-color: #dc2626;
    }

    .btn-neutro {
      background-color: #6b7280;
      color: white;
    }

    .btn-neutro:hover {
      background-color: #4b5563;
    }
  </style>
</head>

<body class="bg-background p-6">

<main class="p-6 transition-all duration-300"
      style="margin-left: <?= $sidebarWidth ?>;">

  <!-- TARJETAS KPI -->
  <div class="grid grid-cols-5 gap-4 mb-6">

    <?php
    $cards = [
      ['icon' => 'bell', 'label' => 'Total Notificaciones', 'value' => $stats['total'], 'soft' => 'kpi-soft-all', 'color' => 'var(--success)'],
      ['icon' => 'alert-triangle', 'label' => 'Sin Leer', 'value' => $stats['unread'], 'soft' => 'kpi-soft-warning', 'color' => 'var(--warning)'],
      ['icon' => 'alert-octagon', 'label' => 'Críticas', 'value' => $stats['critical'], 'soft' => 'kpi-soft-critical', 'color' => 'var(--error)'],
      ['icon' => 'box', 'label' => 'Stock Bajo', 'value' => $stats['low'], 'soft' => 'kpi-soft-low', 'color' => 'var(--foreground)'],
      ['icon' => 'user-cog', 'label' => 'Cambios Datos', 'value' => $stats['cambios'], 'soft' => 'kpi-soft-change', 'color' => 'var(--info)'],  // Nueva tarjeta
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
    <p class="text-sm text-muted-foreground mb-4">
      Notificaciones sobre el estado del inventario y solicitudes de cambios
    </p>

    <div class="space-y-4">

      <?php foreach ($alerts as $a):

        $colors = match($a['type']) {
          'warning'  => 'alert-soft-warning',
          'critical' => 'alert-soft-critical',
          'low'      => 'alert-soft-low',
          'change'   => 'alert-soft-change',
          default    => 'alert-soft-low'
        };

        $badge = match($a['type']) {
          'warning'  => 'badge-soft-warning',
          'critical' => 'badge-soft-critical',
          'low'      => 'badge-soft-low',
          'change'   => 'badge-soft-change',
          default    => 'badge-soft-low'
        };

        $icon = match($a['type']) {
          'warning'  => 'alert-triangle',
          'critical' => 'alert-octagon',
          'low'      => 'box',
          'change'   => 'user-cog',
          default    => 'bell'
        };

        $label = match($a['type']) {
          'warning'  => 'Alta',
          'critical' => 'Crítica',
          'low'      => 'Baja',
          'change'   => 'Cambio Datos',
          default    => 'General'
        };
      ?>

      <div class="flex items-start justify-between p-4 rounded-lg <?= $colors ?>">

        <div class="flex gap-3">
          <div class="mt-1">
            <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
          </div>

          <div class="flex-1">
            <p class="font-semibold">
              <?= $a['name'] ?> 
            </p>

            <p class="text-xs text-muted-foreground">
              <?= htmlspecialchars($a['usuario_nombre']) ?> – <?= $a['code'] ?>
            </p>

            <div class="mt-2 text-sm text-muted-foreground">
              <?= $a['descripcion'] ?>
            </div>

            <!-- Mostrar datos específicos de cambio si es una solicitud -->
            <?php if ($a['type'] === 'change' && !empty($a['datos_cambio'])): ?>
              <div class="mt-2 p-2 bg-white/50 rounded text-xs">
                <strong>Solicitud de cambio:</strong>
                <button class="ml-2 text-blue-600 hover:text-blue-800 underline text-xs btn-ver-detalles"
                        data-notif-id="<?= $a['id'] ?>"
                        data-datos='<?= htmlspecialchars(json_encode($a['datos_cambio']), ENT_QUOTES, 'UTF-8') ?>'>
                  Ver detalles
                </button>
              </div>
            <?php endif; ?>

            <div class="flex items-center gap-3 mt-1">
              <span class="text-xs text-muted-foreground"><?= $a['time'] ?></span>

              <!-- BADGE -->
              <span class="text-xs px-2 py-0.5 rounded <?= $badge ?>">
                <?= $label ?>
              </span>

            </div>
          </div>
        </div>

        <div class="flex gap-2">
          <?php if ($a['type'] === 'change'): ?>
            <!-- Botón para gestionar cambio -->
            <button 
              class="btn-gestionar-cambio hover:bg-blue-100 p-2 rounded"
              data-notif-id="<?= $a['id'] ?>"
              title="Gestionar solicitud de cambio"
              data-datos='<?= htmlspecialchars(json_encode($a['datos_cambio']), ENT_QUOTES, 'UTF-8') ?>'
            >
              <i data-lucide="check-circle" class="w-5 h-5 text-blue-600"></i>
            </button>
          <?php endif; ?>
          
          <button 
            class="btn-eliminar-notificacion hover:bg-red-100 p-2 rounded"
            data-notif-id="<?= $a['id'] ?>"
            title="Eliminar notificación"
          >
            <i data-lucide="trash-2" class="w-5 h-5 text-red-600"></i>
          </button>
        </div>

      </div>

      <?php endforeach; ?>

    </div>
  </div>

  <!-- Modal para detalles de cambio -->
  <div id="modalCambioDatos" class="modal-overlay">
    <div class="modal-content p-6">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold">Detalles de Solicitud de Cambio</h3>
        <button id="cerrarModal" class="text-gray-500 hover:text-gray-700">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      
      <div id="modalContenido" class="mb-4">
        <!-- Contenido dinámico -->
      </div>
      
      <div class="flex justify-end gap-2 mt-6">
        <button id="btnAprobarCambio" class="btn-accion btn-aprobar">
          <i data-lucide="check" class="w-4 h-4 mr-2"></i>Aprobar Cambio
        </button>
        <button id="btnRechazarCambio" class="btn-accion btn-rechazar">
          <i data-lucide="x" class="w-4 h-4 mr-2"></i>Rechazar
        </button>
        <button id="btnCerrarModal" class="btn-accion btn-neutro">
          Cerrar
        </button>
      </div>
    </div>
  </div>

  <script>
    lucide.createIcons();
    
    let notificacionActualId = null;
    let datosCambioActual = null;

    // Eliminar notificación
    document.querySelectorAll('.btn-eliminar-notificacion').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        
        const notifId = btn.getAttribute('data-notif-id');
        if (!notifId) return;
        
        const alertDiv = btn.closest('.flex.items-start.justify-between');
        
        if (!alertDiv) {
          console.error('No se encontró contenedor de alerta');
          return;
        }
        
        if (!confirm('¿Está seguro de eliminar esta notificación?')) {
          return;
        }
        
        try {
          const formData = new FormData();
          formData.append('notificacion_id', notifId);
          
          const resp = await fetch('src/controllers/usuario_controller.php?accion=eliminar_notificacion', {
            method: 'POST',
            body: formData
          });
          
          const data = await resp.json();
          
          if (data.success) {
            alertDiv.style.transition = 'all 0.3s ease-out';
            alertDiv.style.opacity = '0';
            alertDiv.style.height = alertDiv.offsetHeight + 'px';
            
            setTimeout(() => {
              alertDiv.style.height = '0px';
              alertDiv.style.marginBottom = '0px';
              alertDiv.style.overflow = 'hidden';
            }, 10);
            
            setTimeout(() => {
              alertDiv.remove();
            }, 300);
          } else {
            alert('Error: ' + (data.error || 'No se pudo eliminar la notificación'));
          }
        } catch (error) {
          console.error('Error eliminando notificación:', error);
          alert('Error al eliminar la notificación: ' + error.message);
        }
      });
    });

    // Mostrar detalles de cambio
    document.querySelectorAll('.btn-ver-detalles').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const datos = JSON.parse(btn.getAttribute('data-datos'));
        mostrarDetallesCambio(datos);
      });
    });

    // Gestionar solicitud de cambio
    document.querySelectorAll('.btn-gestionar-cambio').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        notificacionActualId = btn.getAttribute('data-notif-id');
        datosCambioActual = JSON.parse(btn.getAttribute('data-datos'));
        mostrarModalGestion(datosCambioActual);
      });
    });

    function mostrarDetallesCambio(datos) {
      let contenido = '';
      
      if (datos && typeof datos === 'object') {
        contenido += '<div class="space-y-3">';
        
        for (const [campo, valor] of Object.entries(datos)) {
          if (campo === 'usuario_id' || campo === 'timestamp') continue;
          
          contenido += `
            <div class="cambio-datos-item">
              <div class="font-medium text-sm text-gray-700 mb-1">${campo.replace(/_/g, ' ')}:</div>
              <div class="campo-valor">${valor}</div>
            </div>
          `;
        }
        
        if (datos.timestamp) {
          contenido += `
            <div class="cambio-datos-item">
              <div class="font-medium text-sm text-gray-700 mb-1">Fecha de solicitud:</div>
              <div class="campo-valor">${new Date(datos.timestamp).toLocaleString()}</div>
            </div>
          `;
        }
        
        contenido += '</div>';
      } else {
        contenido = '<p class="text-gray-500">No hay datos de cambio disponibles.</p>';
      }
      
      // Actualizar modal
      document.getElementById('modalContenido').innerHTML = contenido;
      document.getElementById('modalCambioDatos').style.display = 'flex';
    }

    function mostrarModalGestion(datos) {
      let contenido = '';
      
      if (datos && typeof datos === 'object') {
        contenido += '<div class="space-y-4">';
        contenido += '<p class="text-gray-600 mb-4">Revisar los siguientes cambios solicitados:</p>';
        
        for (const [campo, valor] of Object.entries(datos)) {
          if (campo === 'usuario_id' || campo === 'timestamp') continue;
          
          contenido += `
            <div class="cambio-datos-item">
              <div class="flex items-center mb-1">
                <span class="campo-label">${campo.replace(/_/g, ' ')}:</span>
              </div>
              <div class="campo-valor">${valor}</div>
            </div>
          `;
        }
        
        contenido += '</div>';
      } else {
        contenido = '<p class="text-gray-500">No hay datos de cambio disponibles.</p>';
      }
      
      document.getElementById('modalContenido').innerHTML = contenido;
      document.getElementById('modalCambioDatos').style.display = 'flex';
    }

    // Cerrar modal
    document.getElementById('cerrarModal').addEventListener('click', () => {
      document.getElementById('modalCambioDatos').style.display = 'none';
    });

    document.getElementById('btnCerrarModal').addEventListener('click', () => {
      document.getElementById('modalCambioDatos').style.display = 'none';
    });

    // Aprobar cambio
    document.getElementById('btnAprobarCambio').addEventListener('click', async () => {
      if (!notificacionActualId || !datosCambioActual) return;
      
      if (!confirm('¿Está seguro de aprobar estos cambios?')) {
        return;
      }
      
      try {
        const formData = new FormData();
        formData.append('notificacion_id', notificacionActualId);
        formData.append('accion', 'aprobar_cambio_datos');
        formData.append('datos_cambio', JSON.stringify(datosCambioActual));
        
        const resp = await fetch('src/controllers/usuario_controller.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await resp.json();
        
        if (data.success) {
          alert('Cambio aprobado exitosamente');
          document.getElementById('modalCambioDatos').style.display = 'none';
          
          // Remover la notificación del DOM
          const alertDiv = document.querySelector(`[data-notif-id="${notificacionActualId}"]`).closest('.flex.items-start.justify-between');
          if (alertDiv) {
            alertDiv.remove();
          }
        } else {
          alert('Error: ' + (data.error || 'No se pudo aprobar el cambio'));
        }
      } catch (error) {
        console.error('Error aprobando cambio:', error);
        alert('Error al aprobar el cambio: ' + error.message);
      }
    });

    // Rechazar cambio
    document.getElementById('btnRechazarCambio').addEventListener('click', async () => {
      if (!notificacionActualId) return;
      
      const motivo = prompt('Ingrese el motivo del rechazo:');
      if (motivo === null) return;
      
      try {
        const formData = new FormData();
        formData.append('notificacion_id', notificacionActualId);
        formData.append('accion', 'rechazar_cambio_datos');
        formData.append('motivo', motivo);
        
        const resp = await fetch('src/controllers/usuario_controller.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await resp.json();
        
        if (data.success) {
          alert('Cambio rechazado exitosamente');
          document.getElementById('modalCambioDatos').style.display = 'none';
          
          // Remover la notificación del DOM
          const alertDiv = document.querySelector(`[data-notif-id="${notificacionActualId}"]`).closest('.flex.items-start.justify-between');
          if (alertDiv) {
            alertDiv.remove();
          }
        } else {
          alert('Error: ' + (data.error || 'No se pudo rechazar el cambio'));
        }
      } catch (error) {
        console.error('Error rechazando cambio:', error);
        alert('Error al rechazar el cambio: ' + error.message);
      }
    });

  </script>

</main>
</body>
</html>