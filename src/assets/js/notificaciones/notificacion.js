// =====================================================
// 🔔 CONFIGURACIÓN API
// =====================================================
const NOTI_API = "src/controllers/usuario_controller.php";

// =====================================================
// ✅ CAPA DE COMPATIBILIDAD (SIN TOCAR TU BASE)
// 👉 Esto convierte tus acciones viejas a las reales del backend
// =====================================================
const NOTI_ACTIONS = {
  // Backend real
  COUNT: "contar_notificaciones",
  LIST: "obtener_notificaciones",
  MARK_READ: "marcar_notificacion_leida",
  MARK_ALL: "marcar_todas_leidas",
  DELETE: "eliminar_notificacion",
};

function mapearAccionFrontABack(accionFront) {
  const a = (accionFront || "").trim();

  // Tu JS usa estas acciones antiguas:
  if (a === "contador") return NOTI_ACTIONS.COUNT;
  if (a === "listar") return NOTI_ACTIONS.LIST;
  if (a === "marcar-leida") return NOTI_ACTIONS.MARK_READ;
  if (a === "marcar-todas") return NOTI_ACTIONS.MARK_ALL;

  // Si ya llega una acción real, se respeta
  return a;
}

function construirUrlAccion(accion) {
  const acc = mapearAccionFrontABack(accion);
  return `${NOTI_API}?accion=${encodeURIComponent(acc)}`;
}

// =====================================================
// ✅ Helper seguro para traer JSON (maneja HTML/Warn PHP)
// =====================================================
async function fetchJsonSeguro(url, options = {}) {
  const resp = await fetch(url, {
    credentials: "same-origin",
    ...options,
    headers: {
      Accept: "application/json",
      "Cache-Control": "no-cache",
      ...(options.headers || {}),
    },
  });

  const rawText = await resp.text();

  if (!resp.ok) {
    throw new Error(`HTTP ${resp.status} ${resp.statusText} => ${rawText.substring(0, 200)}`);
  }

  if (!rawText || rawText.trim() === "") {
    throw new Error("Respuesta vacía");
  }

  // Si hay HTML de errores PHP, no parsea como JSON
  if (
    rawText.includes("Fatal error") ||
    rawText.includes("Parse error") ||
    rawText.includes("Warning:") ||
    rawText.includes("Notice:")
  ) {
    throw new Error("Respuesta contiene errores PHP visibles");
  }

    

  try {
    return JSON.parse(rawText);
  } catch (e) {
    // Intentar extraer JSON dentro del texto
    const match = rawText.match(/\{[\s\S]*\}/);
    if (match) return JSON.parse(match[0]);
    throw new Error("No se pudo parsear JSON");
  }
}

// =====================================================
// 🔔 CONTADOR DE NOTIFICACIONES
// =====================================================
// =====================================================
// FUNCIÓN AUXILIAR: Actualizar contador de notificaciones
// =====================================================
async function actualizarContadorNotificaciones() {
  try {
    console.log("🔄 Actualizando contador de notificaciones...");

    // ✅ Mantengo tu lista, pero garantizo que el endpoint real exista
    const endpoints = [
      {
        url: "src/utils/notificaciones_sesion.php?accion=contar",
        name: "notificaciones_sesion",
      },
      {
        url: "src/controllers/notificacion_session_controller.php?accion=contador",
        name: "notificacion_session_controller",
      },
      {
        // ✅ este es el REAL
        url: construirUrlAccion("contador"), // mapea a contar_notificaciones
        name: "usuario_controller_real",
      },
      {
        // ✅ dejamos tu fallback aunque sea repetido (NO borro base)
        url: construirUrlAccion("contar_notificaciones"),
        name: "usuario_controller_directo",
      },
    ];

    for (const endpoint of endpoints) {
      console.log(`🔍 Probando endpoint: ${endpoint.name} (${endpoint.url})`);

      try {
        const data = await fetchJsonSeguro(endpoint.url, { method: "GET" });

        if (data.error) {
          console.log(`❌ ${endpoint.name}: Error en respuesta: ${data.error}`);
          continue;
        }

        if (!data.success && data.success !== undefined) {
          console.log(`❌ ${endpoint.name}: success = false`);
          continue;
        }

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
          no_leidas,
          total,
          criticas,
          stock_bajo,
          cambios_datos,
        });

        // ✅ Tu badge original
        const badge = document.querySelector(".badge-notificaciones");
        if (badge) {
          if (no_leidas > 0) {
            badge.textContent = no_leidas > 9 ? "9+" : no_leidas.toString();
            badge.classList.remove("hidden");
            badge.style.display = "inline-block";
          } else {
            badge.classList.add("hidden");
            badge.style.display = "none";
          }
        }

        return {
          success: true,
          no_leidas,
          total,
          criticas,
          stock_bajo,
          cambios_datos,
          esCoordinador: data.esCoordinador ?? data.es_coordinador ?? false,
          endpoint: endpoint.name,
        };
      } catch (fetchError) {
        console.log(`❌ ${endpoint.name}: Error: ${fetchError.message}`);
        continue;
      }
    }

    console.warn("⚠️ Todos los endpoints fallaron, usando fallback");
    return usarContadorFallback();
  } catch (error) {
    console.error("❌ Error general al actualizar contador:", error);
    return usarContadorFallback();
  }
}

// =====================================================
// MÉTODO DE FALLBACK
// =====================================================
function usarContadorFallback() {
  console.log("🔧 Usando método fallback para contador");

  const badge = document.querySelector(".badge-notificaciones");
  let no_leidas = 0;

  if (window.notificacionesContador !== undefined) {
    no_leidas = window.notificacionesContador;
    console.log(`✅ Usando contador desde window: ${no_leidas}`);
  } else if (localStorage.getItem("notificaciones_contador")) {
    no_leidas = parseInt(localStorage.getItem("notificaciones_contador")) || 0;
    console.log(`✅ Usando contador desde localStorage: ${no_leidas}`);
  } else if (badge && badge.textContent) {
    const text = badge.textContent.trim();
    if (text && text !== "0") {
      no_leidas = parseInt(text) || (text.includes("+") ? 10 : 0);
      console.log(`✅ Usando contador desde DOM: ${no_leidas}`);
    }
  }

  if (badge) {
    if (no_leidas > 0) {
      badge.textContent = no_leidas > 9 ? "9+" : no_leidas.toString();
      badge.classList.remove("hidden");
      badge.style.display = "inline-block";
    } else {
      badge.classList.add("hidden");
      badge.style.display = "none";
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
    fallback: true,
  };
}

// =====================================================
// VERSIÓN SIMPLIFICADA PARA LLAMADAS RÁPIDAS
// =====================================================
async function actualizarBadgeNotificaciones() {
  try {
    // ✅ Primero tus endpoints de sesión
    const response = await fetch("src/utils/notificaciones_sesion.php?accion=contar", {
      credentials: "same-origin",
    });

    if (response.ok) {
      const data = await response.json();
      const badge = document.querySelector(".badge-notificaciones");

      if (badge && (data.no_leidas ?? 0) > 0) {
        const n = data.no_leidas ?? 0;
        badge.textContent = n > 9 ? "9+" : n.toString();
        badge.classList.remove("hidden");
        badge.style.display = "inline-block";
        return true;
      }
    }
  } catch (error) {
    // Si falla, intento el endpoint real del usuario_controller
    try {
      const data = await fetchJsonSeguro(construirUrlAccion("contador"));
      const badge = document.querySelector(".badge-notificaciones");

      const n = data.no_leidas ?? 0;
      if (badge && n > 0) {
        badge.textContent = n > 9 ? "9+" : n.toString();
        badge.classList.remove("hidden");
        badge.style.display = "inline-block";
        return true;
      }
    } catch (e) {
      // Silencioso
    }
  }

  const badge = document.querySelector(".badge-notificaciones");
  if (badge) {
    badge.classList.add("hidden");
    badge.style.display = "none";
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
  if (intervaloContador) {
    clearInterval(intervaloContador);
    intervaloContador = null;
  }

  actualizarBadgeNotificaciones().then((success) => {
    if (success) {
      console.log("✅ Contador actualizado inicialmente");
      erroresConsecutivos = 0;
    } else {
      console.log("⚠️ Contador inicial no se pudo actualizar");
      erroresConsecutivos++;
    }
  });

  const intervaloBase = 30000;
  const intervaloActual =
    erroresConsecutivos > 0
      ? Math.min(intervaloBase * (erroresConsecutivos + 1), 300000)
      : intervaloBase;

  intervaloContador = setInterval(async () => {
    try {
      const success = await actualizarBadgeNotificaciones();

      if (success) {
        erroresConsecutivos = 0;
      } else {
        erroresConsecutivos++;

        if (erroresConsecutivos >= MAX_ERRORS) {
          console.warn(`⚠️ Demasiados errores (${erroresConsecutivos}), aumentando intervalo`);
          clearInterval(intervaloContador);
          setTimeout(iniciarActualizacionAutomatica, 120000);
        }
      }
    } catch (error) {
      console.error("Error en actualización periódica:", error);
      erroresConsecutivos++;
    }
  }, intervaloActual);

  console.log(`🔄 Actualización automática configurada cada ${intervaloActual / 1000}s`);
}

function detenerActualizacionAutomatica() {
  if (intervaloContador) {
    clearInterval(intervaloContador);
    intervaloContador = null;
    console.log("🛑 Actualización automática detenida");
  }
}

// =====================================================
// ✅ INICIALIZACIÓN NORMAL (tu sistema)
// =====================================================
document.addEventListener("DOMContentLoaded", function () {
  console.log("✅ DOM cargado - Inicializando notificaciones...");
  iniciarActualizacionAutomatica();

  setTimeout(() => {
    cargarListaNotificaciones();
  }, 1000);

  // ✅ IMPORTANTE: dejo tu bloque de debug, pero lo pongo detrás de una bandera
  if (window.DEBUG_NOTIFICACIONES === true) {
    console.log("🧪 DEBUG NOTIFICACIONES ACTIVADO");
  }
});

// =====================================================
// ✅ CARGAR LISTA DE NOTIFICACIONES (AJUSTADO AL BACKEND REAL)
// =====================================================
async function cargarListaNotificaciones() {
  try {
    // ✅ Antes: accion=listar (NO EXISTE)
    // ✅ Ahora: accion=obtener_notificaciones
    const url = construirUrlAccion("listar") + `&_t=${Date.now()}`;

    const datos = await fetchJsonSeguro(url);

    if (datos.error) {
      console.error("Error del servidor:", datos.error);
      return [];
    }

    const notificaciones = datos.notificaciones || datos.data || [];
    actualizarDropdownNotificaciones(notificaciones);

    return notificaciones;
  } catch (error) {
    console.error("Error cargando notificaciones:", error);
    return [];
  }
}

// =====================================================
// ✅ DROPDOWN UI (tu diseño queda igual)
// =====================================================
function actualizarDropdownNotificaciones(notificaciones) {
  const dropdown = document.getElementById("dropdown-notificaciones");
  if (!dropdown) {
    console.warn("⚠️ No se encontró dropdown-notificaciones");
    return;
  }

  if (!notificaciones || notificaciones.length === 0) {
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

  let html = "";

  notificaciones.slice(0, 10).forEach((notif) => {
    const notifId = notif.id || notif.id_notificacion;
    const esLeida = notif.leida == 1 || notif.leida === true;

    const esCambioDatos = notif.es_cambio_datos || notif.tipo === "CAMBIO_DATOS";

    const fecha = notif.fecha_creacion || notif.fecha || notif.created_at || "Hace un momento";

    let icono = "bi-bell";
    let color = "text-primary";
    let bgColor = "";

    if (esCambioDatos) {
      icono = "bi-person-gear";
      color = "text-warning";
      bgColor = esLeida ? "" : "bg-warning-subtle";
    } else if (notif.tipo === "STOCK_BAJO") {
      icono = "bi-box";
      color = "text-danger";
      bgColor = esLeida ? "" : "bg-danger-subtle";
    } else if (notif.tipo === "SOLICITUD_CREADA") {
      icono = "bi-file-earmark-text";
      color = "text-info";
      bgColor = esLeida ? "" : "bg-info-subtle";
    }

    html += `
      <li>
        <a class="dropdown-item ${bgColor} ${esLeida ? "" : "fw-bold"}" href="#" data-notif-id="${notifId}">
          <div class="d-flex align-items-start">
            <i class="${icono} me-2 ${color} mt-1"></i>
            <div class="flex-grow-1">
              <div class="small">${notif.titulo || "Sin título"}</div>
              <div class="text-muted small">${fecha}</div>
              ${notif.descripcion ? `<div class="text-muted small mt-1">${notif.descripcion}</div>` : ""}
            </div>
            ${!esLeida ? '<span class="badge bg-danger rounded-pill ms-2">!</span>' : ""}
          </div>
        </a>
      </li>
    `;
  });

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

  // ✅ EventListener: Marcar como leída (ARREGLADO al backend real)
  dropdown.querySelectorAll(".dropdown-item[data-notif-id]").forEach((item) => {
    item.addEventListener("click", async (e) => {
      e.preventDefault();
      const notifId = item.getAttribute("data-notif-id");

      try {
        const formData = new FormData();

        // ✅ Antes: marcar-leida (NO EXISTE)
        // ✅ Ahora: marcar_notificacion_leida
        formData.append("accion", mapearAccionFrontABack("marcar-leida"));

        // ✅ Backend real espera: notificacion_id
        // ✅ Mantengo también id_notificacion por compatibilidad
        formData.append("notificacion_id", notifId);
        formData.append("id_notificacion", notifId);

        const resp = await fetch(NOTI_API, {
          method: "POST",
          body: formData,
          credentials: "same-origin",
        });

        const data = await resp.json();

        if (data.success) {
          item.classList.remove("fw-bold", "bg-warning-subtle", "bg-danger-subtle", "bg-info-subtle");

          const badge = item.querySelector(".badge");
          if (badge) badge.remove();

          await actualizarContadorNotificaciones();
          await cargarListaNotificaciones();

          // Si es cambio de datos, redirige
          if (notifId && item.querySelector(".bi-person-gear")) {
            setTimeout(() => {
              window.location.href = "gestionar_cambios.php?id=" + notifId;
            }, 300);
          }
        }
      } catch (error) {
        console.error("Error al marcar como leída:", error);
      }
    });
  });
}

// =====================================================
// ✅ MARCAR TODAS COMO LEÍDAS (ARREGLADO AL BACKEND REAL)
// =====================================================
async function marcarTodasLeidas() {
  try {
    if (!confirm("¿Estás seguro de que quieres marcar todas las notificaciones como leídas?")) {
      return;
    }

    const formData = new FormData();

    // ✅ Antes: marcar-todas (NO EXISTE)
    // ✅ Ahora: marcar_todas_leidas
    formData.append("accion", mapearAccionFrontABack("marcar-todas"));

    const respuesta = await fetch(NOTI_API, {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    });

    const datos = await respuesta.json();

    if (datos.success) {
      await actualizarContadorNotificaciones();
      await cargarListaNotificaciones();
      alert(datos.message || "Todas las notificaciones han sido marcadas como leídas.");
    } else {
      alert("Error: " + (datos.error || datos.message || "No se pudieron marcar todas como leídas"));
    }
  } catch (error) {
    console.error("Error:", error);
    alert("Error al marcar todas como leídas");
  }
}

// =====================================================
// ✅ TU SISTEMA DE INICIALIZACIÓN BASE (SE RESPETA)
// =====================================================
function inicializarSistemaNotificaciones() {
  console.log("🚀 Inicializando sistema de notificaciones...");

  crearBadgeNotificaciones();

  setTimeout(() => {
    actualizarContadorNotificaciones();
    cargarListaNotificaciones();
  }, 1000);

  const intervaloContadorLocal = setInterval(actualizarContadorNotificaciones, 30000);
  const intervaloLista = setInterval(cargarListaNotificaciones, 60000);

  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) {
      console.log("👀 Página visible, actualizando notificaciones...");
      actualizarContadorNotificaciones();
      cargarListaNotificaciones();
    }
  });

  window._notificacionesIntervalos = {
    contador: intervaloContadorLocal,
    lista: intervaloLista,
  };
}

function detenerSistemaNotificaciones() {
  if (window._notificacionesIntervalos) {
    clearInterval(window._notificacionesIntervalos.contador);
    clearInterval(window._notificacionesIntervalos.lista);
    console.log("🛑 Sistema de notificaciones detenido");
  }
}

function abrirDropdownNotificaciones() {
  cargarListaNotificaciones();
}

// =====================================================
// ✅ AUTO INIT (tu lógica se respeta)
// =====================================================
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", inicializarSistemaNotificaciones);
} else {
  setTimeout(inicializarSistemaNotificaciones, 100);
}

// =====================================================
// EXPORTAR FUNCIONES PARA USO GLOBAL (tu base)
// =====================================================
window.actualizarContadorNotificaciones = actualizarContadorNotificaciones;
window.cargarListaNotificaciones = cargarListaNotificaciones;
window.marcarTodasLeidas = marcarTodasLeidas;
window.abrirDropdownNotificaciones = abrirDropdownNotificaciones;
window.inicializarSistemaNotificaciones = inicializarSistemaNotificaciones;
window.detenerSistemaNotificaciones = detenerSistemaNotificaciones;

// =====================================================
// FUNCIÓN DE PRUEBA PARA DESARROLLO (AJUSTADA)
// =====================================================
window.probarNotificaciones = async function () {
  console.log("🧪 Ejecutando prueba de notificaciones...");

  console.log("📋 Variables disponibles:", {
    usuarioId: window.usuarioId,
    AUTH_USER_ID: window.AUTH_USER_ID,
    esCoordinador: window.esCoordinador,
  });

  try {
    const response = await fetch(construirUrlAccion("contador") + "&_t=" + Date.now(), {
      credentials: "same-origin",
    });
    const data = await response.json();
    console.log("🎯 Respuesta del contador:", data);
  } catch (error) {
    console.error("❌ Error en prueba contador:", error);
  }

  try {
    const response = await fetch(construirUrlAccion("listar") + "&_t=" + Date.now(), {
      credentials: "same-origin",
    });
    const data = await response.json();
    console.log("📋 Respuesta de lista:", data);
  } catch (error) {
    console.error("❌ Error en prueba lista:", error);
  }
};

// =====================================================
// ✅ TUS FUNCIONES EXISTENTES (NO BORRO NADA)
// (las dejo tal cual si ya están en tu archivo)
// =====================================================

// Función para mostrar contador (tu base)
function mostrarContador(cantidad) {
  let badge = document.getElementById("contador-notificaciones");

  if (!badge) {
    console.log("🛠️ Badge no encontrado, intentando crear...");
    crearBadgeNotificaciones();
    badge = document.getElementById("contador-notificaciones");

    if (!badge) {
      console.warn("⚠️ No se pudo crear el badge de notificaciones");
      return;
    }
  }

  if (cantidad > 0) {
    badge.textContent = cantidad;
    badge.style.display = "inline";
    badge.classList.remove("d-none");

    badge.style.animation = "pulse 1s ease-in-out";
    setTimeout(() => {
      badge.style.animation = "";
    }, 1000);

    const boton = badge.closest("button, a");
    if (boton) {
      const tituloOriginal = boton.getAttribute("data-original-title") || boton.title || "Notificaciones";
      boton.setAttribute("data-original-title", tituloOriginal);
      boton.title = `${tituloOriginal} (${cantidad} sin leer)`;
    }
  } else {
    badge.style.display = "none";
    badge.classList.add("d-none");

    const boton = badge.closest("button, a");
    if (boton && boton.getAttribute("data-original-title")) {
      boton.title = boton.getAttribute("data-original-title");
    }
  }
}

// Crear badge si no existe (tu base)
function crearBadgeNotificaciones() {
  console.log("🛠️ Intentando crear badge de notificaciones...");

  let botonNotificaciones =
    document.querySelector('[data-bs-toggle="dropdown"][aria-label*="notificaciones" i]') ||
    document.querySelector('[data-bs-toggle="dropdown"] .bi-bell')?.closest("button") ||
    document.querySelector('[data-bs-toggle="dropdown"] .fa-bell')?.closest("button") ||
    document.querySelector("button.dropdown-toggle") ||
    document.querySelector(".dropdown-toggle");

  if (!botonNotificaciones) {
    const elementos = document.querySelectorAll("button, a");
    for (let el of elementos) {
      if (
        el.textContent.includes("Notificaciones") ||
        el.textContent.includes("Bell") ||
        el.innerHTML.includes("bi-bell") ||
        el.innerHTML.includes("fa-bell")
      ) {
        botonNotificaciones = el;
        break;
      }
    }
  }

  if (botonNotificaciones) {
    console.log("✅ Encontrado botón de notificaciones:", botonNotificaciones);

    let badge = botonNotificaciones.querySelector("#contador-notificaciones");
    if (badge) {
      console.log("✅ Badge ya existe");
      return badge;
    }

    badge = document.createElement("span");
    badge.id = "contador-notificaciones";
    badge.className =
      "position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none";
    badge.style.cssText = "font-size: 0.6em; padding: 0.25em 0.5em; min-width: 1.5em;";
    badge.textContent = "0";

    botonNotificaciones.appendChild(badge);
    botonNotificaciones.style.position = "relative";

    console.log("✅ Badge creado exitosamente");
    return badge;
  } else {
    console.warn("⚠️ No se encontró el botón de notificaciones");
    return null;
  }
}
