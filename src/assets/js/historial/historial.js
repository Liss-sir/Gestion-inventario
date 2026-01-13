// src/assets/js/historial/historial.js

document.addEventListener("DOMContentLoaded", () => {
  const API_URL = "src/controllers/historial_controller.php";

  const searchInput = document.getElementById("searchInput");
  const moduloFilter = document.getElementById("moduloFilter");
  const accionFilter = document.getElementById("accionFilter");
  const timelineSection = document.getElementById("timeline");
  const itemsContainer = timelineSection?.querySelector(".mt-6.space-y-6");

  if (!searchInput || !moduloFilter || !accionFilter || !timelineSection || !itemsContainer) {
    console.warn("Faltan elementos del DOM para Historial.");
    return;
  }

  function getAbsoluteApiUrl() {
    try {
      return new URL(API_URL, window.location.href).href;
    } catch (e) {
      return API_URL;
    }
  }

  const formatDate = (iso) => {
    if (!iso) return { date: "-", time: "-" };
    const d = new Date(String(iso).replace(" ", "T"));
    if (isNaN(d.getTime())) return { date: "-", time: "-" };
    const date = d.toLocaleDateString("es-CO", { year: "numeric", month: "long", day: "2-digit" });
    const time = d.toLocaleTimeString("es-CO", { hour: "2-digit", minute: "2-digit" });
    return { date, time };
  };

  const tableToLabel = (tabla) => {
    const map = {
      movimientos: "Movimientos",
      solicitudes: "Solicitudes",
      materiales: "Materiales",
      bodegas: "Bodegas",
      usuarios: "Usuarios",
      programas: "Programas",
      fichas: "Fichas",
      raes: "RAEs",
      evidencias: "Evidencias",
      reportes: "Reportes",
    };
    return map[(tabla || "").toLowerCase()] || (tabla || "Módulo");
  };

  const accionCrudToLabel = (accion) => {
    const a = (accion || "").toUpperCase();
    if (a === "INSERT") return "Creación";
    if (a === "UPDATE") return "Edición";
    if (a === "DELETE") return "Eliminación";
    return "Acción";
  };

  const accionUiFromDetalle = (detalle) => {
    if (!detalle) return "";
    const d = detalle.trim();

    const candidates = [
      "Entrada", "Salida", "Devolución", "Devolucion",
      "Aprobación", "Aprobacion", "Rechazo",
      "Desactivación", "Desactivacion", "Edición", "Edicion", "Creación", "Creacion"
    ];

    for (const c of candidates) {
      if (d.startsWith(c + ":") || d.startsWith("[" + c + "]") || d.startsWith(c + "|")) {
        if (c === "Devolucion") return "Devolución";
        if (c === "Aprobacion") return "Aprobación";
        if (c === "Desactivacion") return "Desactivación";
        if (c === "Edicion") return "Edición";
        if (c === "Creacion") return "Creación";
        return c;
      }
    }
    return "";
  };

  const cleanDetalle = (detalle) => {
    if (!detalle) return "";
    return detalle
      .replace(/^\[(.*?)\]\s*/i, "")
      .replace(/^(Entrada|Salida|Devolución|Devolucion|Aprobación|Aprobacion|Rechazo|Desactivación|Desactivacion|Edición|Edicion|Creación|Creacion)\s*[:|]\s*/i, "")
      .trim();
  };

  const updateChipTotal = (total) => {
    const pReg = Array.from(document.querySelectorAll("p"))
      .find(p => (p.textContent || "").trim().toLowerCase() === "registros");
    if (!pReg) return;

    const wrapper = pReg.parentElement;
    if (!wrapper) return;

    const pNum = wrapper.querySelector("p.text-sm.font-semibold.text-primary");
    if (!pNum) return;

    pNum.textContent = String(total);
  };

  const renderMessage = (html) => {
    itemsContainer.innerHTML = `
      <div class="rounded-xl border border-border bg-background p-6 text-sm text-muted-foreground">
        ${html}
      </div>
    `;
  };

  const renderItem = (item, isLast) => {
    const modulo = tableToLabel(item.tabla_nombre);
    const uiAccion = accionUiFromDetalle(item.detalle) || accionCrudToLabel(item.accion);
    const detalle = cleanDetalle(item.detalle) || item.descripcion || "Sin descripción.";
    const { date, time } = formatDate(item.fecha_hora);

    const usuarioNombre = (item.usuario_nombre || "").trim() || "Sistema";
    const usuarioCargo = (item.usuario_cargo || "").trim();
    const cargoTxt = usuarioCargo ? ` <span class="text-muted-foreground">(${usuarioCargo})</span>` : "";

    const connector = isLast
      ? ""
      : `<div class="absolute left-[22px] top-[36px] bottom-[-24px] w-px" style="background-color: var(--border);"></div>`;

    return `
      <div class="timeline-item relative flex gap-4">
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
              style="background-color: color-mix(in srgb, var(--chart-5) 14%, white); color: var(--chart-5);"
            >
              ${uiAccion}
            </span>

            <span class="inline-flex items-center rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
              ${modulo}
            </span>
          </div>

          <p class="mt-3 text-base font-semibold text-card-foreground">${detalle}</p>

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

  let debounceTimer = null;

  const fetchHistorial = async () => {
    const q = (searchInput.value || "").trim();
    const modulo = (moduloFilter.value || "").trim();
    const accion = (accionFilter.value || "").trim();

    const params = new URLSearchParams({
      action: "listar",
      q,
      modulo,
      accion,
      page: "1",
      limit: "20",
    });

    const baseApi = getAbsoluteApiUrl();
    const url = `${baseApi}?${params.toString()}`;

    console.log("[Historial] URL:", url);

    try {
      const res = await fetch(url, { headers: { Accept: "application/json" } });
      const text = await res.text();

      // intenta parsear JSON aunque venga con warnings
      let data = null;
      try {
        const start = text.indexOf("{");
        const end = text.lastIndexOf("}");
        const jsonText = (start !== -1 && end !== -1 && end > start) ? text.slice(start, end + 1) : "";
        data = jsonText ? JSON.parse(jsonText) : null;
      } catch (e) {
        data = null;
      }

      // ✅ AHORA: si es 500 pero trae message, lo mostramos
      if (!res.ok) {
        const backendMsg = data?.message || data?.error || "";
        renderMessage(`
          No pude consultar el historial. HTTP ${res.status}<br>
          ${backendMsg ? `<span class="text-xs text-destructive">Detalle: ${backendMsg}</span><br>` : ""}
          <span class="text-xs break-all">${url}</span>
        `);
        console.error("[Historial] Respuesta cruda:", text);
        return;
      }

      if (!data || !data.ok) {
        renderMessage(`Error: ${data?.message || "Respuesta no válida del servidor."}`);
        console.error("[Historial] Respuesta cruda:", text);
        return;
      }

      updateChipTotal(data.total || 0);

      const items = Array.isArray(data.items) ? data.items : [];
      if (items.length === 0) {
        renderMessage("No hay registros con esos filtros.");
        if (window.lucide?.createIcons) window.lucide.createIcons();
        return;
      }

      itemsContainer.innerHTML = items
        .map((it, idx) => renderItem(it, idx === items.length - 1))
        .join("");

      if (window.lucide?.createIcons) window.lucide.createIcons();
    } catch (err) {
      renderMessage(`Error de red o servidor.<br><span class="text-xs break-all">${getAbsoluteApiUrl()}</span>`);
      console.error(err);
    }
  };

  const scheduleFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchHistorial, 250);
  };

  searchInput.addEventListener("input", scheduleFetch);
  moduloFilter.addEventListener("change", scheduleFetch);
  accionFilter.addEventListener("change", scheduleFetch);

  fetchHistorial();
});
