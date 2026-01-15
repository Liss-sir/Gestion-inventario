// =====================================================
// 🔔 CONFIGURACIÓN API
// =====================================================
const NOTI_API = "src/controllers/usuario_controller.php";

// =====================================================
// 🔔 CONTADOR DE NOTIFICACIONES
// =====================================================
// =====================================================
// 🔔 CONTADOR DE NOTIFICACIONES - VERSIÓN CORREGIDA
// =====================================================
// =====================================================
// 🔔 CONTADOR DE NOTIFICACIONES - VERSIÓN PARA SESIÓN
// =====================================================
// =====================================================
// FUNCIÓN AUXILIAR: Actualizar contador de notificaciones
// =====================================================
async function actualizarContadorNotificaciones() {
  try {
    console.log('🔄 Actualizando contador de notificaciones...');
    
    // Definir endpoints en orden de prioridad
    const endpoints = [
      {
        url: 'src/utils/notificaciones_sesion.php?accion=contar',
        name: 'notificaciones_sesion'
      },
      {
        url: 'src/controllers/notificacion_session_controller.php?accion=contador',
        name: 'notificacion_session_controller'
      },
      {
        url: 'src/controllers/usuario_controller.php?accion=contar_notificaciones',
        name: 'usuario_controller'
      },
      {
        url: 'src/controllers/usuario_controller.php?accion=contador',
        name: 'notificacion_controller'
      }
    ];
    
    // Intentar cada endpoint hasta que uno funcione
    for (const endpoint of endpoints) {
      console.log(`🔍 Probando endpoint: ${endpoint.name} (${endpoint.url})`);
      
      try {
        const response = await fetch(endpoint.url, {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'Cache-Control': 'no-cache'
          },
          credentials: 'same-origin'
        });
        
        // Verificar estado HTTP
        if (!response.ok) {
          console.log(`❌ ${endpoint.name}: HTTP ${response.status} - ${response.statusText}`);
          continue;
        }
        
        // Intentar obtener texto primero para depurar
        const rawText = await response.text();
        
        // Verificar si la respuesta está vacía
        if (!rawText || rawText.trim() === '') {
          console.log(`❌ ${endpoint.name}: Respuesta vacía`);
          continue;
        }
        
        // Verificar si hay errores PHP visibles
        if (rawText.includes('Fatal error') || 
            rawText.includes('Parse error') || 
            rawText.includes('Warning:') ||
            rawText.includes('Notice:')) {
          console.log(`❌ ${endpoint.name}: Contiene errores PHP`);
          continue;
        }
        
        // Intentar parsear JSON
        let data;
        try {
          data = JSON.parse(rawText);
        } catch (parseError) {
          console.log(`❌ ${endpoint.name}: Error parseando JSON: ${parseError.message}`);
          
          // Intentar extraer JSON si hay texto extra alrededor
          const jsonMatch = rawText.match(/\{[\s\S]*\}/);
          if (jsonMatch) {
            try {
              data = JSON.parse(jsonMatch[0]);
              console.log(`⚠️ ${endpoint.name}: JSON extraído de respuesta no limpia`);
            } catch (e) {
              console.log(`❌ ${endpoint.name}: No se pudo extraer JSON válido`);
              continue;
            }
          } else {
            continue;
          }
        }
        
        // Verificar estructura de respuesta
        if (data.error) {
          console.log(`❌ ${endpoint.name}: Error en respuesta: ${data.error}`);
          continue;
        }
        
        if (!data.success && data.success !== undefined) {
          console.log(`❌ ${endpoint.name}: success = false`);
          continue;
        }
        
        // Extraer datos de diferentes posibles estructuras
        const no_leidas = 
          data.no_leidas ?? 
          data.noLeidas ?? 
          data.unread ?? 
          data.sin_leer ?? 
          0;
        
        const total = 
          data.total ?? 
          data.count ?? 
          (data.notificaciones ? data.notificaciones.length : 0);
        
        const criticas = data.criticas ?? data.critical ?? 0;
        const stock_bajo = data.stock_bajo ?? data.low_stock ?? data.warning ?? 0;
        const cambios_datos = 
          data.cambios_datos ?? 
          data.cambios_pendientes ?? 
          data.pending_changes ?? 
          0;
        
        console.log(`✅ ${endpoint.name} funcionó:`, {
          no_leidas, total, criticas, stock_bajo, cambios_datos
        });
        
        // Actualizar badge
        const badge = document.querySelector('.badge-notificaciones');
        if (badge) {
          if (no_leidas > 0) {
            badge.textContent = no_leidas > 9 ? '9+' : no_leidas.toString();
            badge.classList.remove('hidden');
            badge.style.display = 'inline-block';
          } else {
            badge.classList.add('hidden');
            badge.style.display = 'none';
          }
        }
        
        // Retornar datos exitosamente
        return {
          success: true,
          no_leidas,
          total,
          criticas,
          stock_bajo,
          cambios_datos,
          esCoordinador: data.esCoordinador ?? false,
          endpoint: endpoint.name
        };
        
      } catch (fetchError) {
        console.log(`❌ ${endpoint.name}: Error de conexión: ${fetchError.message}`);
        continue;
      }
    }
    
    // Si todos los endpoints fallaron
    console.warn('⚠️ Todos los endpoints fallaron, usando fallback');
    return usarContadorFallback();
    
  } catch (error) {
    console.error('❌ Error general al actualizar contador:', error);
    return usarContadorFallback();
  }
}

// =====================================================
// MÉTODO DE FALLBACK
// =====================================================
function usarContadorFallback() {
  console.log('🔧 Usando método fallback para contador');
  
  const badge = document.querySelector('.badge-notificaciones');
  let no_leidas = 0;
  
  // 1. Intentar obtener de variable global
  if (window.notificacionesContador !== undefined) {
    no_leidas = window.notificacionesContador;
    console.log(`✅ Usando contador desde window: ${no_leidas}`);
  }
  // 2. Intentar obtener de localStorage
  else if (localStorage.getItem('notificaciones_contador')) {
    no_leidas = parseInt(localStorage.getItem('notificaciones_contador')) || 0;
    console.log(`✅ Usando contador desde localStorage: ${no_leidas}`);
  }
  // 3. Intentar obtener del badge actual en el DOM
  else if (badge && badge.textContent) {
    const text = badge.textContent.trim();
    if (text && text !== '0') {
      no_leidas = parseInt(text) || (text.includes('+') ? 10 : 0);
      console.log(`✅ Usando contador desde DOM: ${no_leidas}`);
    }
  }
  
  // Actualizar badge
  if (badge) {
    if (no_leidas > 0) {
      badge.textContent = no_leidas > 9 ? '9+' : no_leidas.toString();
      badge.classList.remove('hidden');
      badge.style.display = 'inline-block';
    } else {
      badge.classList.add('hidden');
      badge.style.display = 'none';
    }
  }
  
  return {
    success: false,
    no_leidas,
    total: no_leidas,
    criticas: 0,
    stock_bajo: 0,
    cambios_datos: 0,
    esCoordinador: false,
    fallback: true
  };
}

// =====================================================
// VERSIÓN SIMPLIFICADA PARA LLAMADAS RÁPIDAS
// =====================================================
async function actualizarBadgeNotificaciones() {
  try {
    // Usar el endpoint de sesión primero (es más confiable)
    const response = await fetch('src/utils/notificaciones_sesion.php?accion=contar', {
      credentials: 'same-origin'
    });
    
    if (response.ok) {
      const data = await response.json();
      const badge = document.querySelector('.badge-notificaciones');
      
      if (badge && data.no_leidas > 0) {
        badge.textContent = data.no_leidas > 9 ? '9+' : data.no_leidas.toString();
        badge.classList.remove('hidden');
        badge.style.display = 'inline-block';
        return true;
      }
    }
  } catch (error) {
    // Silencioso - no mostrar error en consola para el usuario
  }
  
  // Si falla, ocultar badge
  const badge = document.querySelector('.badge-notificaciones');
  if (badge) {
    badge.classList.add('hidden');
    badge.style.display = 'none';
  }
  
  return false;
}

// =====================================================
// CONFIGURAR ACTUALIZACIÓN AUTOMÁTICA (MEJORADA)
// =====================================================
let intervaloContador = null;
let erroresConsecutivos = 0;
const MAX_ERRORS = 5;

function iniciarActualizacionAutomatica() {
  // Limpiar intervalo previo
  if (intervaloContador) {
    clearInterval(intervaloContador);
    intervaloContador = null;
  }
  
  // Actualizar inmediatamente
  actualizarBadgeNotificaciones().then(success => {
    if (success) {
      console.log('✅ Contador actualizado inicialmente');
      erroresConsecutivos = 0;
    } else {
      console.log('⚠️ Contador inicial no se pudo actualizar');
      erroresConsecutivos++;
    }
  });
  
  // Configurar intervalo con backoff inteligente
  const intervaloBase = 30000; // 30 segundos
  const intervaloActual = erroresConsecutivos > 0 ? 
    Math.min(intervaloBase * (erroresConsecutivos + 1), 300000) : // Hasta 5 minutos máximo
    intervaloBase;
  
  intervaloContador = setInterval(async () => {
    try {
      const success = await actualizarBadgeNotificaciones();
      
      if (success) {
        erroresConsecutivos = 0;
      } else {
        erroresConsecutivos++;
        
        // Si hay muchos errores seguidos, reducir frecuencia o detener
        if (erroresConsecutivos >= MAX_ERRORS) {
          console.warn(`⚠️ Demasiados errores (${erroresConsecutivos}), aumentando intervalo`);
          clearInterval(intervaloContador);
          
          // Reiniciar con intervalo mayor
          setTimeout(iniciarActualizacionAutomatica, 120000); // Esperar 2 minutos
        }
      }
    } catch (error) {
      console.error('Error en actualización periódica:', error);
      erroresConsecutivos++;
    }
  }, intervaloActual);
  
  console.log(`🔄 Actualización automática configurada cada ${intervaloActual/1000}s`);
}

function detenerActualizacionAutomatica() {
  if (intervaloContador) {
    clearInterval(intervaloContador);
    intervaloContador = null;
    console.log('🛑 Actualización automática detenida');
  }
}

// =====================================================
// INICIALIZACIÓN MEJORADA
// =====================================================
// Código de depuración para probar el endpoint
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DOM cargado - Probando conexión...');
    
    // Esperar un momento para que las notificaciones carguen
    setTimeout(() => {
        const primerBoton = document.querySelector('.btn-gestionar-cambio');
        if (primerBoton) {
            const testId = primerBoton.getAttribute('data-notif-id');
            console.log('🧪 Primer ID encontrado:', testId);
            
            // Probar el endpoint
            fetch(`srobtener_detalle_notificacion.php?id=${testId}`)
                .then(response => {
                    console.log('📊 Estado HTTP:', response.status, response.statusText);
                    console.log('🔗 URL intentada:', response.url);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('📄 Respuesta cruda (primeros 500 chars):', text.substring(0, 500));
                    try {
                        const data = JSON.parse(text);
                        console.log('✅ JSON parseado:', data);
                    } catch (e) {
                        console.error('❌ Error parseando JSON:', e.message);
                    }
                })
                .catch(error => {
                    console.error('❌ Error en fetch:', error);
                    console.log('💡 Sugerencias:');
                    console.log('1. Verifica que el archivo existe en la misma carpeta');
                    console.log('2. Verifica permisos del archivo');
                    console.log('3. Prueba la URL directamente en el navegador');
                });
        } else {
            console.warn('⚠️ No se encontraron botones de notificaciones');
        }
    }, 1000);
});

// =====================================================
// FUNCIÓN DE DIAGNÓSTICO MEJORADA
// =====================================================
async function diagnosticarEndpointsNotificaciones() {
  console.log('=== DIAGNÓSTICO DE ENDPOINTS DE NOTIFICACIONES ===');
  
  const endpoints = [
    'src/utils/notificaciones_sesion.php?accion=contar',
    'src/controllers/notificacion_session_controller.php?accion=contador',
    'src/controllers/usuario_controller.php?accion=contar_notificaciones',
    'src/controllers/usuario_controller.php?accion=contador'
  ];
  
  for (const endpoint of endpoints) {
    console.log(`\n🔍 Probando: ${endpoint}`);
    
    try {
      const startTime = performance.now();
      const response = await fetch(endpoint, { credentials: 'same-origin' });
      const endTime = performance.now();
      
      console.log(`⏱️  Tiempo: ${Math.round(endTime - startTime)}ms`);
      console.log(`📊 Estado: ${response.status} ${response.statusText}`);
      console.log(`📝 Content-Type: ${response.headers.get('content-type')}`);
      
      const text = await response.text();
      console.log(`📄 Longitud: ${text.length} caracteres`);
      
      if (text.length > 0) {
        // Mostrar primeros 200 caracteres
        console.log(`📝 Primeros 200 chars: ${text.substring(0, 200)}`);
        
        // Verificar si es JSON válido
        try {
          const json = JSON.parse(text);
          console.log('✅ JSON válido:', json);
        } catch (jsonError) {
          console.log('❌ No es JSON válido:', jsonError.message);
          
          // Buscar JSON dentro del texto
          const jsonMatch = text.match(/\{[\s\S]*\}/);
          if (jsonMatch) {
            console.log('🔍 Intentando extraer JSON...');
            try {
              const extracted = JSON.parse(jsonMatch[0]);
              console.log('✅ JSON extraído:', extracted);
            } catch (e) {
              console.log('❌ No se pudo extraer JSON válido');
            }
          }
        }
      } else {
        console.log('❌ Respuesta vacía');
      }
      
    } catch (error) {
      console.log(`❌ Error de conexión: ${error.message}`);
    }
  }
  
  console.log('\n=== FIN DEL DIAGNÓSTICO ===');
}

// Para usar en consola del navegador
window.diagnosticarNotificaciones = diagnosticarEndpointsNotificaciones;

// Función para mostrar contador
function mostrarContador(cantidad) {
    let badge = document.getElementById('contador-notificaciones');
    
    if (!badge) {
        console.log('🛠️ Badge no encontrado, intentando crear...');
        crearBadgeNotificaciones();
        badge = document.getElementById('contador-notificaciones');
        
        if (!badge) {
            console.warn('⚠️ No se pudo crear el badge de notificaciones');
            return;
        }
    }
    
    if (cantidad > 0) {
        badge.textContent = cantidad;
        badge.style.display = 'inline';
        badge.classList.remove('d-none');
        
        // Agregar animación de pulso
        badge.style.animation = 'pulse 1s ease-in-out';
        setTimeout(() => {
            badge.style.animation = '';
        }, 1000);
        
        // Actualizar título del botón para accesibilidad
        const boton = badge.closest('button, a');
        if (boton) {
            const tituloOriginal = boton.getAttribute('data-original-title') || boton.title || 'Notificaciones';
            boton.setAttribute('data-original-title', tituloOriginal);
            boton.title = `${tituloOriginal} (${cantidad} sin leer)`;
        }
    } else {
        badge.style.display = 'none';
        badge.classList.add('d-none');
        
        // Restaurar título original
        const boton = badge.closest('button, a');
        if (boton && boton.getAttribute('data-original-title')) {
            boton.title = boton.getAttribute('data-original-title');
        }
    }
}

// Crear badge si no existe
function crearBadgeNotificaciones() {
    console.log('🛠️ Intentando crear badge de notificaciones...');
    
    // Buscar botón de notificaciones de diferentes maneras
    let botonNotificaciones = document.querySelector('[data-bs-toggle="dropdown"][aria-label*="notificaciones" i]') ||
                             document.querySelector('[data-bs-toggle="dropdown"] .bi-bell')?.closest('button') ||
                             document.querySelector('[data-bs-toggle="dropdown"] .fa-bell')?.closest('button') ||
                             document.querySelector('button.dropdown-toggle') ||
                             document.querySelector('.dropdown-toggle');
    
    if (!botonNotificaciones) {
        // Buscar por texto
        const elementos = document.querySelectorAll('button, a');
        for (let el of elementos) {
            if (el.textContent.includes('Notificaciones') || 
                el.textContent.includes('Bell') || 
                el.innerHTML.includes('bi-bell') ||
                el.innerHTML.includes('fa-bell')) {
                botonNotificaciones = el;
                break;
            }
        }
    }
    
    if (botonNotificaciones) {
        console.log('✅ Encontrado botón de notificaciones:', botonNotificaciones);
        
        // Verificar si ya tiene badge
        let badge = botonNotificaciones.querySelector('#contador-notificaciones');
        if (badge) {
            console.log('✅ Badge ya existe');
            return badge;
        }
        
        badge = document.createElement('span');
        badge.id = 'contador-notificaciones';
        badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none';
        badge.style.cssText = 'font-size: 0.6em; padding: 0.25em 0.5em; min-width: 1.5em;';
        badge.textContent = '0';
        
        botonNotificaciones.appendChild(badge);
        botonNotificaciones.style.position = 'relative';
        
        console.log('✅ Badge creado exitosamente');
        return badge;
    } else {
        console.warn('⚠️ No se encontró el botón de notificaciones');
        return null;
    }
}

// Función para cargar lista de notificaciones en dropdown
async function cargarListaNotificaciones() {
    try {
        const respuesta = await fetch(`${NOTI_API}?accion=listar&_t=${Date.now()}`);
        
        if (!respuesta.ok) {
            console.error('Error al cargar notificaciones:', respuesta.status);
            return;
        }
        
        const datos = await respuesta.json();
        
        if (datos.error) {
            console.error('Error del servidor:', datos.error);
            return;
        }
        
        // Usar datos.notificaciones o datos.data según la estructura
        const notificaciones = datos.notificaciones || datos.data || [];
        
        if (notificaciones.length > 0) {
            actualizarDropdownNotificaciones(notificaciones);
        } else {
            actualizarDropdownNotificaciones([]);
        }
        
        return notificaciones;
        
    } catch (error) {
        console.error('Error cargando notificaciones:', error);
        return [];
    }
}

// Actualizar dropdown de notificaciones
function actualizarDropdownNotificaciones(notificaciones) {
    const dropdown = document.getElementById('dropdown-notificaciones');
    if (!dropdown) {
        console.warn('⚠️ No se encontró dropdown-notificaciones');
        return;
    }
    
    if (notificaciones.length === 0) {
        dropdown.innerHTML = `
            <li>
                <a class="dropdown-item text-center text-muted" href="#">
                    <i class="bi bi-bell-slash me-2"></i>
                    No hay notificaciones
                </a>
            </li>
        `;
        return;
    }
    
    let html = '';
    notificaciones.slice(0, 10).forEach(notif => {
        const esLeida = notif.leida || notif.leida === 1;
        const esCambioDatos = notif.es_cambio_datos || notif.tipo === 'CAMBIO_DATOS';
        const fecha = notif.fecha || notif.created_at || 'Hace un momento';
        
        let icono = 'bi-bell';
        let color = 'text-primary';
        let bgColor = '';
        
        if (esCambioDatos) {
            icono = 'bi-person-gear';
            color = 'text-warning';
            bgColor = esLeida ? '' : 'bg-warning-subtle';
        } else if (notif.tipo === 'STOCK_BAJO') {
            icono = 'bi-box';
            color = 'text-danger';
            bgColor = esLeida ? '' : 'bg-danger-subtle';
        } else if (notif.tipo === 'SOLICITUD_CREADA') {
            icono = 'bi-file-earmark-text';
            color = 'text-info';
            bgColor = esLeida ? '' : 'bg-info-subtle';
        }
        
        html += `
            <li>
                <a class="dropdown-item ${bgColor} ${esLeida ? '' : 'fw-bold'}" href="#" data-notif-id="${notif.id || notif.id_notificacion}">
                    <div class="d-flex align-items-start">
                        <i class="${icono} me-2 ${color} mt-1"></i>
                        <div class="flex-grow-1">
                            <div class="small">${notif.titulo || 'Sin título'}</div>
                            <div class="text-muted small">${fecha}</div>
                            ${notif.descripcion ? `<div class="text-muted small mt-1">${notif.descripcion}</div>` : ''}
                        </div>
                        ${!esLeida ? '<span class="badge bg-danger rounded-pill ms-2">!</span>' : ''}
                    </div>
                </a>
            </li>
        `;
    });
    
    // Agregar enlace para ver todas
    html += `
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item text-center" href="notificaciones.php">
                <i class="bi bi-list me-2"></i>
                Ver todas las notificaciones
            </a>
        </li>
        <li>
            <a class="dropdown-item text-center text-danger" href="#" onclick="marcarTodasLeidas()">
                <i class="bi bi-check-all me-2"></i>
                Marcar todas como leídas
            </a>
        </li>
    `;
    
    dropdown.innerHTML = html;
    
    // Agregar event listeners para marcar como leídas
    dropdown.querySelectorAll('.dropdown-item[data-notif-id]').forEach(item => {
        item.addEventListener('click', async (e) => {
            e.preventDefault();
            const notifId = item.getAttribute('data-notif-id');
            
            // Marcar como leída
            try {
                const formData = new FormData();
                formData.append('accion', 'marcar-leida');
                formData.append('id_notificacion', notifId);
                
                const resp = await fetch(NOTI_API, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await resp.json();
                
                if (data.success) {
                    // Actualizar UI
                    item.classList.remove('fw-bold', 'bg-warning-subtle', 'bg-danger-subtle', 'bg-info-subtle');
                    
                    // Remover badge "!"
                    const badge = item.querySelector('.badge');
                    if (badge) badge.remove();
                    
                    // Actualizar contador
                    await actualizarContadorNotificaciones();
                    
                    // Redirigir si es necesario (por ejemplo, a la página de cambios de datos)
                    if (notifId && item.querySelector('.bi-person-gear')) {
                        // Notificación de cambio de datos - redirigir a gestión
                        setTimeout(() => {
                            window.location.href = 'gestionar_cambios.php?id=' + notifId;
                        }, 300);
                    }
                }
            } catch (error) {
                console.error('Error al marcar como leída:', error);
            }
        });
    });
}

// Función para marcar todas como leídas
async function marcarTodasLeidas() {
    try {
        if (!confirm('¿Estás seguro de que quieres marcar todas las notificaciones como leídas?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('accion', 'marcar-todas');
        
        const respuesta = await fetch(NOTI_API, {
            method: 'POST',
            body: formData
        });
        
        const datos = await respuesta.json();
        
        if (datos.success) {
            // Recargar notificaciones
            await actualizarContadorNotificaciones();
            await cargarListaNotificaciones();
            
            // Mostrar mensaje de éxito
            alert(datos.message || 'Todas las notificaciones han sido marcadas como leídas.');
        } else {
            alert('Error: ' + (datos.error || 'No se pudieron marcar todas como leídas'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al marcar todas como leídas');
    }
}

// Inicializar sistema de notificaciones
function inicializarSistemaNotificaciones() {
    console.log('🚀 Inicializando sistema de notificaciones...');
    
    // Crear badge si no existe
    crearBadgeNotificaciones();
    
    // Cargar notificaciones iniciales
    setTimeout(() => {
        actualizarContadorNotificaciones();
        cargarListaNotificaciones();
    }, 1000);
    
    // Actualizar cada 30 segundos
    const intervaloContador = setInterval(actualizarContadorNotificaciones, 30000);
    const intervaloLista = setInterval(cargarListaNotificaciones, 60000);
    
    // Actualizar cuando el usuario vuelve a la pestaña
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            console.log('👀 Página visible, actualizando notificaciones...');
            actualizarContadorNotificaciones();
            cargarListaNotificaciones();
        }
    });
    
    // Guardar referencias para poder limpiar
    window._notificacionesIntervalos = {
        contador: intervaloContador,
        lista: intervaloLista
    };
}

// Detener sistema de notificaciones
function detenerSistemaNotificaciones() {
    if (window._notificacionesIntervalos) {
        clearInterval(window._notificacionesIntervalos.contador);
        clearInterval(window._notificacionesIntervalos.lista);
        console.log('🛑 Sistema de notificaciones detenido');
    }
}

// =====================================================
// FUNCIONES PARA EL DROPDOWN
// =====================================================
function abrirDropdownNotificaciones() {
    cargarListaNotificaciones();
}

// =====================================================
// INICIALIZACIÓN AUTOMÁTICA
// =====================================================

// Inicializar cuando el DOM esté cargado
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarSistemaNotificaciones);
} else {
    // El DOM ya está cargado
    setTimeout(inicializarSistemaNotificaciones, 100);
}

// =====================================================
// EXPORTAR FUNCIONES PARA USO GLOBAL
// =====================================================
window.actualizarContadorNotificaciones = actualizarContadorNotificaciones;
window.cargarListaNotificaciones = cargarListaNotificaciones;
window.marcarTodasLeidas = marcarTodasLeidas;
window.abrirDropdownNotificaciones = abrirDropdownNotificaciones;
window.inicializarSistemaNotificaciones = inicializarSistemaNotificaciones;
window.detenerSistemaNotificaciones = detenerSistemaNotificaciones;

// =====================================================
// FUNCIÓN DE PRUEBA PARA DESARROLLO
// =====================================================
window.probarNotificaciones = async function() {
    console.log('🧪 Ejecutando prueba de notificaciones...');
    
    // 1. Verificar variables de sesión
    console.log('📋 Variables disponibles:', {
        usuarioId: window.usuarioId,
        AUTH_USER_ID: window.AUTH_USER_ID,
        esCoordinador: window.esCoordinador
    });
    
    // 2. Probar endpoint de contador
    try {
        const response = await fetch(`${NOTI_API}?accion=contador&_t=${Date.now()}`);
        const data = await response.json();
        console.log('🎯 Respuesta del contador:', data);
    } catch (error) {
        console.error('❌ Error en prueba:', error);
    }
    
    // 3. Probar endpoint de lista
    try {
        const response = await fetch(`${NOTI_API}?accion=listar&_t=${Date.now()}`);
        const data = await response.json();
        console.log('📋 Respuesta de lista:', data);
    } catch (error) {
        console.error('❌ Error en prueba:', error);
    }
};
// Función para mostrar contador
function mostrarContador(cantidad) {
    let badge = document.getElementById('contador-notificaciones');
    
    if (!badge) {
        console.log('🛠️ Badge no encontrado, intentando crear...');
        crearBadgeNotificaciones();
        badge = document.getElementById('contador-notificaciones');
        
        if (!badge) {
            console.warn('⚠️ No se pudo crear el badge de notificaciones');
            return;
        }
    }
    
    if (cantidad > 0) {
        badge.textContent = cantidad;
        badge.style.display = 'inline';
        badge.classList.remove('d-none');
        
        // Agregar animación de pulso
        badge.style.animation = 'pulse 1s ease-in-out';
        setTimeout(() => {
            badge.style.animation = '';
        }, 1000);
        
        // Actualizar título del botón para accesibilidad
        const boton = badge.closest('button, a');
        if (boton) {
            const tituloOriginal = boton.getAttribute('data-original-title') || boton.title || 'Notificaciones';
            boton.setAttribute('data-original-title', tituloOriginal);
            boton.title = `${tituloOriginal} (${cantidad} sin leer)`;
        }
    } else {
        badge.style.display = 'none';
        badge.classList.add('d-none');
        
        // Restaurar título original
        const boton = badge.closest('button, a');
        if (boton && boton.getAttribute('data-original-title')) {
            boton.title = boton.getAttribute('data-original-title');
        }
    }
}

// Crear badge si no existe
function crearBadgeNotificaciones() {
    console.log('🛠️ Intentando crear badge de notificaciones...');
    
    // Buscar botón de notificaciones de diferentes maneras
    let botonNotificaciones = document.querySelector('[data-bs-toggle="dropdown"][aria-label*="notificaciones" i]') ||
                             document.querySelector('[data-bs-toggle="dropdown"] .bi-bell')?.closest('button') ||
                             document.querySelector('[data-bs-toggle="dropdown"] .fa-bell')?.closest('button') ||
                             document.querySelector('button.dropdown-toggle') ||
                             document.querySelector('.dropdown-toggle');
    
    if (!botonNotificaciones) {
        // Buscar por texto
        const elementos = document.querySelectorAll('button, a');
        for (let el of elementos) {
            if (el.textContent.includes('Notificaciones') || 
                el.textContent.includes('Bell') || 
                el.innerHTML.includes('bi-bell') ||
                el.innerHTML.includes('fa-bell')) {
                botonNotificaciones = el;
                break;
            }
        }
    }
    
    if (botonNotificaciones) {
        console.log('✅ Encontrado botón de notificaciones:', botonNotificaciones);
        
        // Verificar si ya tiene badge
        let badge = botonNotificaciones.querySelector('#contador-notificaciones');
        if (badge) {
            console.log('✅ Badge ya existe');
            return badge;
        }
        
        badge = document.createElement('span');
        badge.id = 'contador-notificaciones';
        badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none';
        badge.style.cssText = 'font-size: 0.6em; padding: 0.25em 0.5em; min-width: 1.5em;';
        badge.textContent = '0';
        
        botonNotificaciones.appendChild(badge);
        botonNotificaciones.style.position = 'relative';
        
        console.log('✅ Badge creado exitosamente');
        return badge;
    } else {
        console.warn('⚠️ No se encontró el botón de notificaciones');
        return null;
    }
}

// Función para cargar lista de notificaciones en dropdown
async function cargarListaNotificaciones() {
    try {
        const respuesta = await fetch(`${NOTI_API}?accion=listar&_t=${Date.now()}`);
        
        if (!respuesta.ok) {
            console.error('Error al cargar notificaciones:', respuesta.status);
            return;
        }
        
        const datos = await respuesta.json();
        
        if (datos.error) {
            console.error('Error del servidor:', datos.error);
            return;
        }
        
        // Usar datos.notificaciones o datos.data según la estructura
        const notificaciones = datos.notificaciones || datos.data || [];
        
        if (notificaciones.length > 0) {
            actualizarDropdownNotificaciones(notificaciones);
        } else {
            actualizarDropdownNotificaciones([]);
        }
        
        return notificaciones;
        
    } catch (error) {
        console.error('Error cargando notificaciones:', error);
        return [];
    }
}

// Actualizar dropdown de notificaciones
function actualizarDropdownNotificaciones(notificaciones) {
    const dropdown = document.getElementById('dropdown-notificaciones');
    if (!dropdown) {
        console.warn('⚠️ No se encontró dropdown-notificaciones');
        return;
    }
    
    if (notificaciones.length === 0) {
        dropdown.innerHTML = `
            <li>
                <a class="dropdown-item text-center text-muted" href="#">
                    <i class="bi bi-bell-slash me-2"></i>
                    No hay notificaciones
                </a>
            </li>
        `;
        return;
    }
    
    let html = '';
    notificaciones.slice(0, 10).forEach(notif => {
        const esLeida = notif.leida || notif.leida === 1;
        const esCambioDatos = notif.es_cambio_datos || notif.tipo === 'CAMBIO_DATOS';
        const fecha = notif.fecha || notif.created_at || 'Hace un momento';
        
        let icono = 'bi-bell';
        let color = 'text-primary';
        let bgColor = '';
        
        if (esCambioDatos) {
            icono = 'bi-person-gear';
            color = 'text-warning';
            bgColor = esLeida ? '' : 'bg-warning-subtle';
        } else if (notif.tipo === 'STOCK_BAJO') {
            icono = 'bi-box';
            color = 'text-danger';
            bgColor = esLeida ? '' : 'bg-danger-subtle';
        } else if (notif.tipo === 'SOLICITUD_CREADA') {
            icono = 'bi-file-earmark-text';
            color = 'text-info';
            bgColor = esLeida ? '' : 'bg-info-subtle';
        }
        
        html += `
            <li>
                <a class="dropdown-item ${bgColor} ${esLeida ? '' : 'fw-bold'}" href="#" data-notif-id="${notif.id || notif.id_notificacion}">
                    <div class="d-flex align-items-start">
                        <i class="${icono} me-2 ${color} mt-1"></i>
                        <div class="flex-grow-1">
                            <div class="small">${notif.titulo || 'Sin título'}</div>
                            <div class="text-muted small">${fecha}</div>
                            ${notif.descripcion ? `<div class="text-muted small mt-1">${notif.descripcion}</div>` : ''}
                        </div>
                        ${!esLeida ? '<span class="badge bg-danger rounded-pill ms-2">!</span>' : ''}
                    </div>
                </a>
            </li>
        `;
    });
    
    // Agregar enlace para ver todas
    html += `
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item text-center" href="notificaciones.php">
                <i class="bi bi-list me-2"></i>
                Ver todas las notificaciones
            </a>
        </li>
        <li>
            <a class="dropdown-item text-center text-danger" href="#" onclick="marcarTodasLeidas()">
                <i class="bi bi-check-all me-2"></i>
                Marcar todas como leídas
            </a>
        </li>
    `;
    
    dropdown.innerHTML = html;
    
    // Agregar event listeners para marcar como leídas
    dropdown.querySelectorAll('.dropdown-item[data-notif-id]').forEach(item => {
        item.addEventListener('click', async (e) => {
            e.preventDefault();
            const notifId = item.getAttribute('data-notif-id');
            
            // Marcar como leída
            try {
                const formData = new FormData();
                formData.append('accion', 'marcar-leida');
                formData.append('id_notificacion', notifId);
                
                const resp = await fetch(NOTI_API, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await resp.json();
                
                if (data.success) {
                    // Actualizar UI
                    item.classList.remove('fw-bold', 'bg-warning-subtle', 'bg-danger-subtle', 'bg-info-subtle');
                    
                    // Remover badge "!"
                    const badge = item.querySelector('.badge');
                    if (badge) badge.remove();
                    
                    // Actualizar contador
                    await actualizarContadorNotificaciones();
                    
                    // Redirigir si es necesario (por ejemplo, a la página de cambios de datos)
                    if (notifId && item.querySelector('.bi-person-gear')) {
                        // Notificación de cambio de datos - redirigir a gestión
                        setTimeout(() => {
                            window.location.href = 'gestionar_cambios.php?id=' + notifId;
                        }, 300);
                    }
                }
            } catch (error) {
                console.error('Error al marcar como leída:', error);
            }
        });
    });
}

// Función para marcar todas como leídas
async function marcarTodasLeidas() {
    try {
        if (!confirm('¿Estás seguro de que quieres marcar todas las notificaciones como leídas?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('accion', 'marcar-todas');
        
        const respuesta = await fetch(NOTI_API, {
            method: 'POST',
            body: formData
        });
        
        const datos = await respuesta.json();
        
        if (datos.success) {
            // Recargar notificaciones
            await actualizarContadorNotificaciones();
            await cargarListaNotificaciones();
            
            // Mostrar mensaje de éxito
            alert(datos.message || 'Todas las notificaciones han sido marcadas como leídas.');
        } else {
            alert('Error: ' + (datos.error || 'No se pudieron marcar todas como leídas'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al marcar todas como leídas');
    }
}

// Inicializar sistema de notificaciones
function inicializarSistemaNotificaciones() {
    console.log('🚀 Inicializando sistema de notificaciones...');
    
    // Crear badge si no existe
    crearBadgeNotificaciones();
    
    // Cargar notificaciones iniciales
    setTimeout(() => {
        actualizarContadorNotificaciones();
        cargarListaNotificaciones();
    }, 1000);
    
    // Actualizar cada 30 segundos
    const intervaloContador = setInterval(actualizarContadorNotificaciones, 30000);
    const intervaloLista = setInterval(cargarListaNotificaciones, 60000);
    
    // Actualizar cuando el usuario vuelve a la pestaña
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            console.log('👀 Página visible, actualizando notificaciones...');
            actualizarContadorNotificaciones();
            cargarListaNotificaciones();
        }
    });
    
    // Guardar referencias para poder limpiar
    window._notificacionesIntervalos = {
        contador: intervaloContador,
        lista: intervaloLista
    };
}

// Detener sistema de notificaciones
function detenerSistemaNotificaciones() {
    if (window._notificacionesIntervalos) {
        clearInterval(window._notificacionesIntervalos.contador);
        clearInterval(window._notificacionesIntervalos.lista);
        console.log('🛑 Sistema de notificaciones detenido');
    }
}

// =====================================================
// FUNCIONES PARA EL DROPDOWN
// =====================================================
function abrirDropdownNotificaciones() {
    cargarListaNotificaciones();
}

// =====================================================
// INICIALIZACIÓN AUTOMÁTICA
// =====================================================

// Inicializar cuando el DOM esté cargado
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarSistemaNotificaciones);
} else {
    // El DOM ya está cargado
    setTimeout(inicializarSistemaNotificaciones, 100);
}

// =====================================================
// EXPORTAR FUNCIONES PARA USO GLOBAL
// =====================================================
window.actualizarContadorNotificaciones = actualizarContadorNotificaciones;
window.cargarListaNotificaciones = cargarListaNotificaciones;
window.marcarTodasLeidas = marcarTodasLeidas;
window.abrirDropdownNotificaciones = abrirDropdownNotificaciones;
window.inicializarSistemaNotificaciones = inicializarSistemaNotificaciones;
window.detenerSistemaNotificaciones = detenerSistemaNotificaciones;

// =====================================================
// FUNCIÓN DE PRUEBA PARA DESARROLLO
// =====================================================
window.probarNotificaciones = async function() {
    console.log('🧪 Ejecutando prueba de notificaciones...');
    
    // 1. Verificar variables de sesión
    console.log('📋 Variables disponibles:', {
        usuarioId: window.usuarioId,
        AUTH_USER_ID: window.AUTH_USER_ID,
        esCoordinador: window.esCoordinador
    });
    
    // 2. Probar endpoint de contador
    try {
        const response = await fetch(`${NOTI_API}?accion=contador&_t=${Date.now()}`);
        const data = await response.json();
        console.log('🎯 Respuesta del contador:', data);
    } catch (error) {
        console.error('❌ Error en prueba:', error);
    }
    
    // 3. Probar endpoint de lista
    try {
        const response = await fetch(`${NOTI_API}?accion=listar&_t=${Date.now()}`);
        const data = await response.json();
        console.log('📋 Respuesta de lista:', data);
    } catch (error) {
        console.error('❌ Error en prueba:', error);
    }
};