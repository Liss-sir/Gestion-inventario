// src/assets/js/historial/historial.js

document.addEventListener("DOMContentLoaded", () => {
  // ========================= CONFIGURACIÓN =========================
  const API_URL = "src/controllers/historial_controller.php";
  
  // ========================= ELEMENTOS DOM =========================
  const searchInput = document.getElementById("searchInput");
  const moduloFilter = document.getElementById("moduloFilter");
  const accionFilter = document.getElementById("accionFilter");
  const timelineItems = document.getElementById("timeline-items");
  const timelineStatus = document.getElementById("timeline-status");
  
  // Contador de registros (chip)
  const chipTotalElement = document.querySelector('.text-primary + .text-muted-foreground')?.previousElementSibling;
  const chipElement = document.querySelector('.flex.items-center.gap-3.rounded-xl');

  if (!timelineItems) {
    console.error("No se encontró el contenedor timeline-items");
    return;
  }

  // ========================= FUNCIONES UTILITARIAS =========================
  
  // Obtener URL absoluta de la API
  const getAbsoluteApiUrl = () => {
    try {
      return new URL(API_URL, window.location.href).href;
    } catch (e) {
      return API_URL;
    }
  };

  // Formatear fecha y hora
  const formatDate = (iso) => {
    if (!iso) return { date: "-", time: "-" };
    
    try {
      const d = new Date(String(iso).replace(" ", "T"));
      if (isNaN(d.getTime())) return { date: "-", time: "-" };
      
      const date = d.toLocaleDateString("es-CO", { 
        year: "numeric", 
        month: "long", 
        day: "2-digit" 
      });
      
      const time = d.toLocaleTimeString("es-CO", { 
        hour: "2-digit", 
        minute: "2-digit",
        hour12: false 
      });
      
      return { date, time };
    } catch (e) {
      return { date: "-", time: "-" };
    }
  };

  // Mapear nombre de tabla a etiqueta legible
  const tableToLabel = (tabla) => {
    if (!tabla) return "Módulo";
    
    const map = {
      'movimientos_material': 'Movimientos',
      'movimientos': 'Movimientos',
      'solicitudes_material': 'Solicitudes',
      'solicitudes': 'Solicitudes',
      'material_formacion': 'Materiales',
      'materiales': 'Materiales',
      'bodegas': 'Bodegas',
      'usuarios': 'Usuarios',
      'programas_formacion': 'Programas',
      'programas': 'Programas',
      'fichas': 'Fichas',
      'raes': 'RAEs',
      'evidencias': 'Evidencias',
      'audit_log': 'Historial',
      'stock_bodega': 'Stock',
      'devoluciones_material': 'Devoluciones'
    };
    
    return map[tabla.toLowerCase()] || tabla.charAt(0).toUpperCase() + tabla.slice(1).replace(/_/g, ' ');
  };

  // Obtener acción UI desde detalle
  const getAccionFromDetalle = (detalle, accionCrud) => {
    if (!detalle && !accionCrud) return "Acción";
    
    const d = String(detalle || "").toLowerCase();
    
    // Buscar por palabras clave en el detalle
    if (d.includes("entrada")) return "Entrada";
    if (d.includes("salida")) return "Salida";
    if (d.includes("devolución") || d.includes("devolucion")) return "Devolución";
    if (d.includes("aprobación") || d.includes("aprobacion")) return "Aprobación";
    if (d.includes("rechazo")) return "Rechazo";
    if (d.includes("desactivación") || d.includes("desactivacion")) return "Desactivación";
    if (d.includes("creación") || d.includes("creacion")) return "Creación";
    if (d.includes("edición") || d.includes("edicion") || d.includes("actualizó")) return "Edición";
    
    // Fallback a acción CRUD
    const crud = (accionCrud || "").toUpperCase();
    if (crud === "INSERT") return "Creación";
    if (crud === "UPDATE") return "Edición";
    if (crud === "DELETE") return "Eliminación";
    
    return "Acción";
  };

  // Limpiar detalle para mostrar
  const cleanDetalle = (detalle) => {
    if (!detalle) return "";
    
    return detalle
      .replace(/^\[.*?\]\s*/g, "")
      .replace(/^(Entrada|Salida|Devolución|Devolucion|Aprobación|Aprobacion|Rechazo|Desactivación|Desactivacion|Edición|Edicion|Creación|Creacion)\s*[:|]\s*/gi, "")
      .replace(/[\n\r]/g, ' ') // Reemplazar saltos de línea por espacios
      .trim();
  };

  // Obtener color para el badge de acción
  const getAccionColor = (accion) => {
    const colors = {
      'Entrada': '#10b981', // verde
      'Salida': '#f59e0b', // naranja
      'Devolución': '#8b5cf6', // violeta
      'Aprobación': '#3b82f6', // azul
      'Rechazo': '#ef4444', // rojo
      'Desactivación': '#6b7280', // gris
      'Creación': '#10b981', // verde
      'Edición': '#f59e0b', // naranja
      'Eliminación': '#ef4444' // rojo
    };
    return colors[accion] || '#6b7280';
  };

  // Actualizar chip de total de registros
  const updateChipTotal = (total) => {
    if (chipTotalElement) {
      chipTotalElement.textContent = String(total);
    }
    
    if (chipElement) {
      chipElement.style.display = total > 0 ? 'flex' : 'none';
    }
  };

  // Mostrar mensaje en el timeline
  const showMessage = (message, type = 'info') => {
    if (!timelineItems) return;
    
    const bgColor = type === 'error' ? '#fee2e2' : '#f3f4f6';
    const textColor = type === 'error' ? '#ef4444' : '#6b7280';
    const icon = type === 'error' ? 'alert-circle' : 'info';
    
    timelineItems.innerHTML = `
      <div class="flex items-center justify-center py-12">
        <div class="text-center max-w-md">
          <i data-lucide="${icon}" class="h-12 w-12 mx-auto" style="color: ${textColor}"></i>
          <p class="mt-4 text-sm" style="color: ${textColor}">${message}</p>
        </div>
      </div>
    `;
    
    if (window.lucide?.createIcons) window.lucide.createIcons();
  };

  // Mostrar esqueleto de carga
  const showLoading = () => {
    if (!timelineItems) return;
    
    timelineItems.innerHTML = `
      <div class="flex items-center justify-center py-12">
        <div class="text-center">
          <i data-lucide="loader-circle" class="h-8 w-8 animate-spin text-primary mx-auto"></i>
          <p class="mt-2 text-sm text-muted-foreground">Cargando historial...</p>
        </div>
      </div>
    `;
    
    if (window.lucide?.createIcons) window.lucide.createIcons();
  };

  // Renderizar un item del timeline
  const renderItem = (item, index, total) => {
    const modulo = tableToLabel(item.tabla_nombre);
    const accion = getAccionFromDetalle(item.detalle, item.accion);
    const detalle = cleanDetalle(item.descripcion || item.detalle || "Sin descripción");
    const { date, time } = formatDate(item.fecha_hora);
    
    const usuarioNombre = (item.usuario_nombre || "").trim() || "Sistema";
    const usuarioCargo = (item.usuario_cargo || "").trim();
    const cargoTxt = usuarioCargo ? ` <span class="text-muted-foreground">(${usuarioCargo})</span>` : "";
    
    const isLast = index === total - 1;
    const connector = isLast ? "" : `
      <div 
        class="absolute left-[22px] top-[36px] bottom-[-24px] w-px" 
        style="background-color: var(--border);"
      ></div>
    `;
    
    const accionColor = getAccionColor(accion);
    
    return `
      <div class="timeline-item group relative flex gap-4">
        <div class="relative w-11 shrink-0">
          ${connector}
          <div class="absolute left-[4px] top-0 z-10 flex h-9 w-9 items-center justify-center rounded-full border border-border bg-background text-muted-foreground">
            <i data-lucide="history" class="h-5 w-5"></i>
          </div>
        </div>

        <div class="w-full rounded-xl border border-border bg-card p-6 shadow-sm">
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
              style="background-color: ${accionColor}20; color: ${accionColor};"
            >
              ${accion}
            </span>

            <span class="inline-flex items-center rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
              ${modulo}
            </span>
            
            ${item.pk_valor ? `
              <span class="inline-flex items-center rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                ID: ${item.pk_valor}
              </span>
            ` : ''}
          </div>

          <p class="mt-3 text-base font-semibold text-card-foreground">
            ${detalle}
          </p>

          <div class="mt-4 flex flex-wrap items-center gap-5 text-xs text-muted-foreground">
            <span class="inline-flex items-center gap-2">
              <i data-lucide="user" class="h-4 w-4"></i>
              ${usuarioNombre}${cargoTxt}
            </span>

            <span class="inline-flex items-center gap-2">
              <i data-lucide="calendar" class="h-4 w-4"></i>
              ${date}
            </span>

            <span class="inline-flex items-center gap-2">
              <i data-lucide="clock" class="h-4 w-4"></i>
              ${time}
            </span>
          </div>
        </div>
      </div>
    `;
  };

  // ========================= FUNCIÓN PRINCIPAL =========================
  let debounceTimer = null;

  const fetchHistorial = async () => {
    // Mostrar loading
    showLoading();
    
    // Obtener valores de los filtros
    const q = (searchInput?.value || "").trim();
    const modulo = (moduloFilter?.value || "").trim();
    const accion = (accionFilter?.value || "").trim();

    // Construir URL con parámetros
    const params = new URLSearchParams({
      action: "listar",
      page: "1",
      limit: "50"
    });
    
    if (q) params.append("q", q);
    if (modulo) params.append("modulo", modulo);
    if (accion) params.append("accion", accion);

    const baseApi = getAbsoluteApiUrl();
    const url = `${baseApi}?${params.toString()}`;

    console.log("[Historial] Fetching:", url);

    try {
      const response = await fetch(url, { 
        headers: { 
          "Accept": "application/json",
          "X-Requested-With": "XMLHttpRequest"
        } 
      });
      
      const text = await response.text();
      console.log("[Historial] Respuesta cruda:", text.substring(0, 200) + "...");

      // Intentar parsear JSON
      let data = null;
      try {
        // Buscar el primer '{' y último '}' para extraer JSON válido
        const start = text.indexOf("{");
        const end = text.lastIndexOf("}");
        
        if (start !== -1 && end !== -1 && end > start) {
          const jsonText = text.substring(start, end + 1);
          data = JSON.parse(jsonText);
        } else {
          throw new Error("No se encontró JSON válido en la respuesta");
        }
      } catch (parseError) {
        console.error("[Historial] Error parseando JSON:", parseError);
        showMessage("Error al procesar la respuesta del servidor", "error");
        return;
      }

      // Verificar respuesta exitosa
      if (!response.ok || !data?.ok) {
        showMessage(data?.message || "Error al cargar el historial", "error");
        return;
      }

      // Actualizar chip de total
      updateChipTotal(data.total || 0);

      // Renderizar items
      const items = Array.isArray(data.items) ? data.items : [];
      
      if (items.length === 0) {
        showMessage("No hay registros con esos filtros");
        return;
      }

      timelineItems.innerHTML = items
        .map((item, idx) => renderItem(item, idx, items.length))
        .join("");

      // Actualizar iconos de Lucide
      if (window.lucide?.createIcons) {
        window.lucide.createIcons();
      }

    } catch (error) {
      console.error("[Historial] Error:", error);
      showMessage("Error de conexión con el servidor", "error");
    }
  };

  // ========================= EVENT LISTENERS =========================
  
  // Búsqueda con debounce
  if (searchInput) {
    searchInput.addEventListener("input", () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(fetchHistorial, 300);
    });
  }

  // Filtros con cambio inmediato
  if (moduloFilter) {
    moduloFilter.addEventListener("change", fetchHistorial);
  }

  if (accionFilter) {
    accionFilter.addEventListener("change", fetchHistorial);
  }

  // Carga inicial
  fetchHistorial();
});