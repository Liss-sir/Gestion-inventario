// src/assets/js/historial/historial.js

document.addEventListener("DOMContentLoaded", () => {

  const API_URL = "src/controllers/historial_controller.php";

  const searchInput = document.getElementById("searchInput");
  const moduloFilter = document.getElementById("moduloFilter");
  const accionFilter = document.getElementById("accionFilter");
  const timelineItems = document.getElementById("timeline-items");

  const chipTotalElement = document.querySelector('.text-primary + .text-muted-foreground')?.previousElementSibling;
  const chipElement = document.querySelector('.flex.items-center.gap-3.rounded-xl');

  if (!timelineItems) return;

  // ========================= UTILIDADES =========================

  const getAbsoluteApiUrl = () => {
    try {
      return new URL(API_URL, window.location.href).href;
    } catch {
      return API_URL;
    }
  };

  const formatDate = (iso) => {
    if (!iso) return { date: "-", time: "-" };

    const d = new Date(String(iso).replace(" ", "T"));
    if (isNaN(d.getTime())) return { date: "-", time: "-" };

    return {
      date: d.toLocaleDateString("es-CO", {
        year: "numeric",
        month: "long",
        day: "2-digit"
      }),
      time: d.toLocaleTimeString("es-CO", {
        hour: "2-digit",
        minute: "2-digit",
        hour12: false
      })
    };
  };

  const tableToLabel = (tabla) => {
    if (!tabla) return "Módulo";

    const map = {
      movimientos_material: "Movimientos",
      solicitudes_material: "Solicitudes",
      material_formacion: "Materiales",
      bodegas: "Bodegas",
      usuarios: "Usuarios",
      programas_formacion: "Programas",
      fichas: "Fichas",
      raes: "RAEs",
      evidencias: "Evidencias",
      stock_bodega: "Stock",
      devoluciones_material: "Devoluciones"
    };

    return map[tabla.toLowerCase()] ||
      tabla.charAt(0).toUpperCase() + tabla.slice(1).replace(/_/g, " ");
  };

  // 🔥 SOLO INSERT y UPDATE
  const getAccion = (accionCrud) => {
    const crud = (accionCrud || "").toUpperCase();
    if (["INSERT", "UPDATE"].includes(crud)) {
      return crud;
    }
    return null;
  };

  // 🔥 Detectar desactivación (estado = 0)
  const isDesactivacion = (item) => {
    if ((item.accion || "").toUpperCase() !== "UPDATE") return false;

    try {
      const newValues = item.new_values ? JSON.parse(item.new_values) : null;
      if (!newValues) return false;

      if (
        Object.prototype.hasOwnProperty.call(newValues, "estado") &&
        (newValues.estado === 0 || newValues.estado === "0")
      ) {
        return true;
      }
    } catch (e) {
      return false;
    }

    return false;
  };

  const getAccionColor = (accion, item) => {
    if (isDesactivacion(item)) return "#ef4444"; // rojo
    if (accion === "INSERT") return "#10b981";   // verde
    if (accion === "UPDATE") return "#3b82f6";   // azul
    return "#6b7280";
  };

  const getAccionLabel = (accion, item) => {
    if (isDesactivacion(item)) return "DESACTIVACIÓN";
    if (accion === "INSERT") return "CREACIÓN";
    if (accion === "UPDATE") return "ACTUALIZACIÓN";
    return accion;
  };

  const cleanDetalle = (detalle) => {
    if (!detalle) return "";
    return detalle
      .replace(/^\[.*?\]\s*/g, "")
      .replace(/[\n\r]/g, " ")
      .trim();
  };

  const updateChipTotal = (total) => {
    if (chipTotalElement) chipTotalElement.textContent = String(total);
    if (chipElement) chipElement.style.display = total > 0 ? "flex" : "none";
  };

  const showMessage = (message, isError = false) => {
    timelineItems.innerHTML = `
      <div class="flex items-center justify-center py-12">
        <p class="text-sm ${isError ? "text-red-500" : "text-muted-foreground"}">
          ${message}
        </p>
      </div>
    `;
  };

  const showLoading = () => {
    timelineItems.innerHTML = `
      <div class="flex items-center justify-center py-12">
        <p class="text-sm text-muted-foreground">Cargando historial...</p>
      </div>
    `;
  };

  const renderItem = (item, index, total) => {

    const accion = getAccion(item.accion);
    if (!accion) return "";

    const modulo = tableToLabel(item.tabla_nombre);
    const detalle = cleanDetalle(item.descripcion || item.detalle || "Sin descripción");
    const { date, time } = formatDate(item.fecha_hora);

    const accionColor = getAccionColor(accion, item);
    const accionLabel = getAccionLabel(accion, item);
    const isLast = index === total - 1;

    const connector = isLast ? "" : `
      <div class="absolute left-[22px] top-[36px] bottom-[-24px] w-px"
        style="background-color: var(--border);">
      </div>
    `;

    return `
      <div class="relative flex gap-4">
        <div class="relative w-11 shrink-0">
          ${connector}
          <div class="absolute left-[4px] top-0 z-10 flex h-9 w-9 items-center justify-center rounded-full border bg-background">
            <i data-lucide="history" class="h-5 w-5"></i>
          </div>
        </div>

        <div class="w-full rounded-xl border bg-card p-6 shadow-sm">
          <div class="flex flex-wrap items-center gap-2">

            <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
              style="background-color:${accionColor}20; color:${accionColor};">
              ${accionLabel}
            </span>

            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-medium text-muted-foreground">
              ${modulo}
            </span>

            ${item.pk_valor ? `
              <span class="inline-flex rounded-full border px-3 py-1 text-xs font-medium text-muted-foreground">
                ID: ${item.pk_valor}
              </span>
            ` : ""}
          </div>

          <p class="mt-3 text-base font-semibold">
            ${detalle}
          </p>

          <div class="mt-4 flex flex-wrap items-center gap-5 text-xs text-muted-foreground">
            <span>${item.usuario_nombre || "Sistema"}</span>
            <span>${date}</span>
            <span>${time}</span>
          </div>
        </div>
      </div>
    `;
  };

  // ========================= FETCH =========================

  let debounceTimer = null;

  const fetchHistorial = async () => {

    showLoading();

    const q = (searchInput?.value || "").trim();
    const modulo = (moduloFilter?.value || "").trim();
    const accion = (accionFilter?.value || "").trim();

    const params = new URLSearchParams({
      action: "listar",
      page: "1",
      limit: "50"
    });

    if (q) params.append("q", q);
    if (modulo) params.append("modulo", modulo);
    if (accion) params.append("accion", accion);

    const url = `${getAbsoluteApiUrl()}?${params.toString()}`;

    try {
      const response = await fetch(url, {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest"
        }
      });

      const data = await response.json();

      if (!response.ok || !data?.ok) {
        showMessage("Error al cargar historial", true);
        return;
      }

      let items = Array.isArray(data.items) ? data.items : [];

      // 🔥 SOLO INSERT y UPDATE
      items = items.filter(item =>
        ["INSERT", "UPDATE"].includes((item.accion || "").toUpperCase())
      );

      updateChipTotal(items.length);

      if (!items.length) {
        showMessage("No hay registros con esos filtros");
        return;
      }

      timelineItems.innerHTML = items
        .map((item, idx) => renderItem(item, idx, items.length))
        .join("");

      if (window.lucide?.createIcons) {
        window.lucide.createIcons();
      }

    } catch (error) {
      console.error(error);
      showMessage("Error de conexión con el servidor", true);
    }
  };

  if (searchInput) {
    searchInput.addEventListener("input", () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(fetchHistorial, 300);
    });
  }

  if (moduloFilter) moduloFilter.addEventListener("change", fetchHistorial);
  if (accionFilter) accionFilter.addEventListener("change", fetchHistorial);

  fetchHistorial();

});