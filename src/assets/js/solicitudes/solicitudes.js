// ============================================================
//  APPLICATIONS MODULE
// ============================================================

const API = new URL("src/controllers/solicitudes_controller.php", document.baseURI).toString();

const CONFIG = {
  LABELS: {
    pendiente: "Pendiente",
    aprobada: "Aprobada",
    rechazada: "Rechazada",
    entregada: "Entregada",
  },
  ICONS: {
    pendiente: "clock",
    aprobada: "check-circle",
    entregada: "package-check",
    rechazado: "x-circle",
    rechazada: "x-circle",
  },
  PAGE_SIZE: 9,
};

let estadoApp = {
  solicitudes: [],
  filtroActivo: "todas",
  paginaActual: 1,
  materialesSeleccionados: [],
  datosFormulario: { programa: "", rae: "", ficha: "", observaciones: "" },

  // NUEVO: filtros inventario
  filtrosInventario: {
    bodega: "",
    subbodega: "",
  },
};

// ============================================================
// USER IN SESSION
// ============================================================
const USUARIO = (() => {
  const u = window.USUARIO_SESION || window.SIGA_USUARIO || window.SIGA_USER || {};
  // soportar distintos nombres de campo que suelen venir del backend
  const id =
    u.usuarioId ?? u.id_usuario ?? u.id ?? u.user_id ?? null;

  const cargo =
    u.cargo ?? u.rol ?? u.role ?? "";

  return {
    raw: u,
    id: (id !== null && id !== undefined) ? parseInt(id, 10) : null,
    cargo: String(cargo || "").trim(),
  };
})();

const CARGOS_FILTRAN_PROPIAS = new Set(["Instructor", "Pasante"]);

// ============================================================
// INSTRUCTOR: associated card in session
// ============================================================
function normalizarCargo(c) {
  return String(c || "")
    .trim()
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "");
}

const ES_INSTRUCTOR = (() => {
  const c = normalizarCargo(USUARIO.cargo);
  return c === "instructor" || c.startsWith("instructor");
})();

const FICHA_INSTRUCTOR = (() => {
  const u = USUARIO.raw || {};
  const fichas = Array.isArray(u.fichas) ? u.fichas : [];
  const primera = fichas[0] || null;
  const id = primera?.id_ficha ?? primera?.id ?? null;
  const n = parseInt(id, 10);
  return Number.isNaN(n) ? null : n;
})();

// ============================================================
// PERMISSIONS
// ============================================================
const PERMS = (() => {
  const p = window.SIGA_SOL_PERMS || {};
  return {
    crear: !!p.crear,
    aceptar: !!p.aceptar,
    rechazar: !!p.rechazar,
    entregar: !!p.entregar,
  };
})();


const selectores = {
  btnNueva: document.getElementById("sol-btn-nueva"),
  modal: document.getElementById("sol-modal"),
  btnCerrarModal: document.getElementById("sol-modal-cerrar"),
  btnCancelar: document.getElementById("sol-btn-cancelar"),

  paso1: document.getElementById("sol-paso-1"),
  paso2: document.getElementById("sol-paso-2"),
  btnPaso2: document.getElementById("sol-btn-ir-paso-2"),
  btnVolver: document.getElementById("sol-btn-volver"),
  btnGuardar: document.getElementById("sol-btn-guardar"),

  contenedorCards: document.getElementById("sol-cards"),
  paginacion: document.getElementById("sol-pagination"),
  filtros: document.querySelectorAll(".sol-filtro-btn"),

  formNueva: document.getElementById("sol-form-nueva"),
  selectPrograma: document.getElementById("programa"),
  selectRae: document.getElementById("rae"),
  selectFichas: document.getElementById("ficha"),
  textareaObservaciones: document.getElementById("observaciones"),

  selectActividad: document.getElementById("actividad"),

  selectBodega: document.getElementById("bodega-select"),
  selectSubBodega: document.getElementById("subbodega-select"),

  selectMaterial: document.getElementById("material-select"),
  inputCantidad: document.getElementById("material-cantidad"),
  btnAgregarMaterial: document.getElementById("btn-agregar-material"),
  listaMateriales: document.getElementById("lista-materiales"),

  resumenPendientes: document.getElementById("resumen-pendientes"),
  resumenAprobadas: document.getElementById("resumen-aprobadas"),
  resumenEntregadas: document.getElementById("resumen-entregadas"),
  resumenRechazadas: document.getElementById("resumen-rechazadas"),
};

// ============================================================
// CHARACTER LIMITS
// ============================================================
const LIMITES = {
  observaciones: 500,
  motivoRechazo: 300,
  cantidad: 6,
};

// Applies maxlength + cuts on input/paste (for security)
function aplicarLimiteCaracteres(el, max, { onLimitMessage } = {}) {
  if (!el || !max) return;

  // native maxlength
  el.setAttribute("maxlength", String(max));

  const cortar = () => {
    const v = String(el.value ?? "");
    if (v.length > max) {
      el.value = v.slice(0, max);
      if (typeof onLimitMessage === "function") onLimitMessage(max);
    }
  };

  // cuts on input/paste
  el.addEventListener("input", cortar);
  el.addEventListener("paste", () => setTimeout(cortar, 0));
}

// Only numbers and maximum digits (for quantity)
function aplicarLimiteNumerico(el, maxDigits = 6) {
  if (!el) return;

  el.setAttribute("inputmode", "numeric");
  el.setAttribute("maxlength", String(maxDigits));

  el.addEventListener("input", () => {
    // leave only digits
    let v = String(el.value ?? "").replace(/\D+/g, "");
    if (v.length > maxDigits) v = v.slice(0, maxDigits);
    el.value = v;
  });

  el.addEventListener("paste", () => {
    setTimeout(() => {
      let v = String(el.value ?? "").replace(/\D+/g, "");
      if (v.length > maxDigits) v = v.slice(0, maxDigits);
      el.value = v;
    }, 0);
  });
}

// Initialize limits for existing form fields
function initLimitesCaracteres() {
  // Observations (main form)
  aplicarLimiteCaracteres(selectores.textareaObservaciones, LIMITES.observaciones, {
    onLimitMessage: (max) => toastInfo(`Maximum ${max} characters in Observations.`),
  });

  // Quantity (only digits, maximum N digits)
  aplicarLimiteNumerico(selectores.inputCantidad, LIMITES.cantidad);
}

// ============================================================
//  SAFE UTILITIES
// ============================================================
function safeLucideCreateIcons() {
  try {
    if (typeof lucide !== "undefined" && lucide && typeof lucide.createIcons === "function") {
      lucide.createIcons();
    }
  } catch (e) {

  }
}

function filtrarSolicitudesPorUsuario(listado) {
  if (!Array.isArray(listado)) return [];

  // Only apply for Instructor and Intern (as you requested)
  if (!CARGOS_FILTRAN_PROPIAS.has(USUARIO.cargo)) return listado;

  // If there is no ID, for security we do not show anything (avoids information leakage)
  if (USUARIO.id === null || Number.isNaN(USUARIO.id)) return [];

  return listado.filter((s) => {
    const creador =
      s.id_usuario_solicitante ??
      s.id_usuario ??
      s.id_solicitante ??
      s.usuario_id ??
      s.usuarioId ??
      null;

    return String(creador) === String(USUARIO.id);
  });
}


// ============================================================
//  FLOWBITE-STYLE TOASTS
// ============================================================
function getOrCreateFlowbiteContainer() {
  let container = document.getElementById("flowbite-alert-container");

  if (!container) {
    container = document.createElement("div");
    container.id = "flowbite-alert-container";
    container.className =
      "fixed top-6 right-6 z-[9999] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none";
    document.body.appendChild(container);
  }

  return container;
}

function showFlowbiteAlert(type, message) {
  const container = getOrCreateFlowbiteContainer();
  const wrapper = document.createElement("div");

  const t = String(type || "").toLowerCase();
  const isSuccess = t === "success";
  const isInfo = t === "info";
  const isWarn = t === "warning" || t === "warn" || t === "error" || !t;

  let borderColor = "border-amber-500";
  let textColor = "text-amber-900";
  let titleText = "Advertencia";

  let iconSVG = `
    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
      <path d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.59A1.75 1.75 0 0 1 16.768 17H3.232a1.75 1.75 0 0 1-1.492-2.311L8.257 3.1z"/>
      <path d="M11 13H9V9h2zm0 3H9v-2h2z" fill="#fff"/>
    </svg>
  `;

  if (isSuccess) {
    borderColor = "border-emerald-500";
    textColor = "text-emerald-900";
    titleText = "Éxito";
    iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm-1 15-4-4 1.414-1.414L9 12.172l4.586-4.586L15 9z"/>
      </svg>
    `;
  } else if (isInfo) {
    borderColor = "border-blue-500";
    textColor = "text-blue-900";
    titleText = "Información";
    iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm1 15H9v-5h2Zm0-7H9V6h2Z"/>
      </svg>
    `;
  }

  wrapper.className = `
    relative flex items-center w-full pointer-events-auto
    rounded-2xl border-l-4 ${borderColor} bg-white shadow-md
    px-4 py-3 text-sm ${textColor}
    opacity-0 -translate-y-2 transition-all duration-300 ease-out
  `;

  wrapper.innerHTML = `
    <div class="flex-shrink-0 mr-3 text-current">${iconSVG}</div>
    <div class="flex-1 min-w-0">
      <p class="font-semibold">${titleText}</p>
      <p class="mt-0.5 text-sm" data-msg></p>
    </div>
  `;

  const msgEl = wrapper.querySelector("[data-msg]");
  if (msgEl) msgEl.textContent = String(message ?? "");

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
}

function toastError(message) {
  showFlowbiteAlert("warning", message);
}
function toastSuccess(message) {
  showFlowbiteAlert("success", message);
}
function toastInfo(message) {
  showFlowbiteAlert("info", message);
}

function truncateText(text, maxLength = 27) {
  if (!text) return '';
  const str = String(text);
  return str.length > maxLength ? str.substring(0, maxLength) + '...' : str;
}

// ============================================================
//  MODAL REASON FOR REJECTION
// ============================================================
function ensureMotivoModalRoot() {
  let root = document.getElementById("sol-motivo-modal-root");
  if (!root) {
    root = document.createElement("div");
    root.id = "sol-motivo-modal-root";
    root.className = "fixed inset-0 z-[9998] hidden items-center justify-center";
    document.body.appendChild(root);
  }
  return root;
}


function pedirMotivoRechazo() {
  return new Promise((resolve) => {
    const root = ensureMotivoModalRoot();

    root.innerHTML = `
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" data-motivo-backdrop></div>

      <!-- Dialog -->
      <div class="relative mx-4 w-full max-w-2xl rounded-2xl bg-card text-foreground shadow-xl border border-border p-6 sm:p-8 animate-fade-in-up">
        <div class="flex items-start justify-between mb-4">
          <div>
            <h2 class="text-xl font-semibold">Rechazar Solicitud</h2>
            <p class="text-sm text-muted-foreground">Ingrese el motivo del rechazo (obligatorio)</p>
          </div>

          <button type="button" data-motivo-cancel
            class="inline-flex h-9 w-9 items-center justify-center rounded-full hover:bg-muted">
            <i data-lucide="x" class="h-4 w-4"></i>
          </button>
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium">Motivo</label>
          <textarea
            id="sol-motivo-input"
            class="input-siga w-full min-h-[130px] resize-none"
            placeholder="Escriba el motivo..."
          ></textarea>
          <p class="text-xs text-muted-foreground">
            Este motivo quedará registrado en la solicitud.
          </p>
        </div>

        <div class="mt-6 flex justify-end gap-2">
          <button type="button" data-motivo-cancel
            class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-muted">
            Cancelar
          </button>

          <button type="button" data-motivo-ok
            class="px-4 py-2 rounded-xl bg-[#c24141] text-white">
            Rechazar
          </button>
        </div>
      </div>
    `;

    // show
    root.classList.remove("hidden");
    root.classList.add("flex");

    const input = root.querySelector("#sol-motivo-input");
    aplicarLimiteCaracteres(input, LIMITES.motivoRechazo, {
      onLimitMessage: (max) => toastInfo(`Máximo ${max} caracteres en el motivo.`),
    });

    const backdrop = root.querySelector("[data-motivo-backdrop]");

    const cleanup = () => {
      root.classList.add("hidden");
      root.classList.remove("flex");
      root.innerHTML = "";
    };

    const close = (val) => {
      document.removeEventListener("keydown", onKey);
      cleanup();
      resolve(val);
    };

    // cancel by buttons
    root.querySelectorAll("[data-motivo-cancel]").forEach((b) => {
      b.addEventListener("click", () => close(null));
    });

    // click outside
    backdrop?.addEventListener("click", () => close(null));

    // confirm
    root.querySelector("[data-motivo-ok]")?.addEventListener("click", () => {
      const motivo = String(input?.value || "").trim();
      if (!motivo) {
        toastError("Debe ingresar un motivo.");
        input?.focus();
        return;
      }
      close(motivo);
    });

    // ESC
    function onKey(e) {
      if (e.key === "Escape" || e.key === "Esc" || e.keyCode === 27) {
        close(null);
      }
    }
    document.addEventListener("keydown", onKey);

    // focus
    setTimeout(() => input?.focus(), 50);

    // lucide
    safeLucideCreateIcons();
  });
}

const utilidades = {
  normalizarEstado(estadoBD) {
    if (!estadoBD) return "pendiente";

    const s = String(estadoBD).trim().toLowerCase();
    const clean = s.normalize("NFD").replace(/[\u0300-\u036f]/g, "");

    if (clean === "aprobado" || clean === "aprobada") return "aprobada";
    if (clean === "rechazado" || clean === "rechazada") return "rechazada";
    if (clean === "entregado" || clean === "entregada") return "entregada";
    return clean;
  },

  formatearFecha(fechaString) {
    if (!fechaString) return "";
    try {
      const normalized = String(fechaString).includes(" ")
        ? String(fechaString).replace(" ", "T")
        : fechaString;

      const d = new Date(normalized);
      if (isNaN(d.getTime())) return String(fechaString);

      return d.toLocaleDateString("es-ES", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
      });
    } catch {
      return String(fechaString);
    }
  },

  extraerDatosSolicitud(s) {
    return {
      id: s.id_solicitud ?? s.id ?? "N/A",
      fecha: this.formatearFecha(s.fecha_solicitud),
      estado: this.normalizarEstado(s.estado),
      ficha: s.numero_ficha ?? s.id_ficha ?? "N/A",
      programa: s.codigo_programa ?? s.nombre_programa ?? s.id_programa ?? "",
      rae: s.codigo_rae ?? s.descripcion_rae ?? s.id_rae ?? "",
      observaciones: s.observaciones ?? "",
      fecha_respuesta: this.formatearFecha(s.fecha_respuesta),
      materiales: s.materiales ?? [],
      id_usuario_solicitante: s.id_usuario_solicitante ?? null,
      id_usuario: s.id_usuario ?? s.id_solicitante ?? s.usuario_id ?? s.usuarioId ?? null,
    };
  },
  mostrarError(msg) {
    if (typeof toastError === "function") return toastError(String(msg || "Ocurrió un error."));
    alert(String(msg || "Ocurrió un error."));
  },

  mostrarExito(msg) {
    if (typeof toastSuccess === "function") return toastSuccess(String(msg || "Operación exitosa."));
    alert(String(msg || "Operación exitosa."));
  },
};

// ============================================================
// HELPER: get inventory context from filters
// ============================================================
function getContextoInventario() {
  const { bodega, subbodega } = estadoApp.filtrosInventario || {};
  const idBodega = String(bodega || "").trim();
  const idSubbodega = String(subbodega || "").trim();

  // se permite con bodega o con subbodega (por tu lógica previa)
  const ok = !!(idBodega || idSubbodega);

  return { ok, idBodega, idSubbodega };
}

// ============================================================
// HELPER: apply material blocking according to inventory context
// ============================================================
function aplicarBloqueoMaterialesPorInventario() {
  const ctx = getContextoInventario();

  // Only if there is no context: block and show message
  if (!ctx.ok) {
    if (selectores.selectMaterial) {
      selectores.selectMaterial.innerHTML =
        '<option value="">Seleccione una bodega o sub-bodega</option>';
      selectores.selectMaterial.disabled = true;
    }
    if (selectores.btnAgregarMaterial) selectores.btnAgregarMaterial.disabled = true;
    if (selectores.inputCantidad) selectores.inputCantidad.disabled = true;
    return false;
  }

  // If there is context: ONLY enable (DO NOT clear options)
  if (selectores.selectMaterial) selectores.selectMaterial.disabled = false;
  if (selectores.btnAgregarMaterial) selectores.btnAgregarMaterial.disabled = false;
  if (selectores.inputCantidad) selectores.inputCantidad.disabled = false;

  return true;
}


// ============================================================
// HELPER: load material options safely
// ============================================================

function idsProgramasAsignadosDesdeSesion(uRaw) {
  if (!uRaw || typeof uRaw !== "object") return [];

  // supports several typical forms
  const candidatos = [
    uRaw.programas_asignados,
    uRaw.programas,
    uRaw.programasAsignados,
    uRaw.programa_ids,
    uRaw.programaIds,
    uRaw.ids_programas,
  ];

  for (const c of candidatos) {
    if (Array.isArray(c)) {
      return c
        .map((x) => (typeof x === "object" ? (x.id_programa ?? x.id ?? x) : x))
        .map((v) => parseInt(v, 10))
        .filter((v) => !Number.isNaN(v));
    }
  }

  for (const c of candidatos) {
    if (typeof c === "string" && c.trim()) {
      return c
        .split(",")
        .map((v) => parseInt(v.trim(), 10))
        .filter((v) => !Number.isNaN(v));
    }
  }

  return [];
}

function filtrarProgramasPorUsuario(programas) {
  if (!Array.isArray(programas)) return [];

  // Only filter if instructor
  if (!ES_INSTRUCTOR) return programas;

  // Security: if there is no file or assignments, show nothing
  const idsAsignados = idsProgramasAsignadosDesdeSesion(USUARIO.raw);

  const traeFichaEnPrograma = programas.some((p) =>
    p && (p.id_ficha != null || Array.isArray(p.fichas) || p.ficha_id != null)
  );

  if (traeFichaEnPrograma && FICHA_INSTRUCTOR) {
    return programas.filter((p) => {
      const idFichaDirecta = parseInt(p.id_ficha ?? p.ficha_id ?? null, 10);
      if (!Number.isNaN(idFichaDirecta)) return idFichaDirecta === FICHA_INSTRUCTOR;

      if (Array.isArray(p.fichas)) {
        return p.fichas.some((f) => {
          const idf = parseInt(f?.id_ficha ?? f?.id ?? null, 10);
          return !Number.isNaN(idf) && idf === FICHA_INSTRUCTOR;
        });
      }

      return false;
    });
  }

  if (!idsAsignados.length) return [];

  return programas.filter((p) => {
    const id = parseInt(p.id_programa ?? p.id ?? null, 10);
    return !Number.isNaN(id) && idsAsignados.includes(id);
  });
}



function setMaterialOptions(materialesArray, modo = "normal") {
  if (!selectores.selectMaterial) return;

  selectores.selectMaterial.innerHTML = '<option value="">Seleccionar material</option>';

  if (!Array.isArray(materialesArray) || !materialesArray.length) {
    selectores.selectMaterial.innerHTML = '<option value="">No hay materiales disponibles</option>';
    return;
  }

  materialesArray.forEach((m) => {
    const opt = document.createElement("option");
    opt.value = m.id_material;

    const codigo = m.codigo_inventario || "Sin código";
    opt.textContent = `${m.nombre} (${codigo})`;

    opt.dataset.stock = Number(m.stock_actual ?? 0);
    opt.dataset.unidad = m.unidad_medida || "UND";
    opt.dataset.nombre = m.nombre || "";

    selectores.selectMaterial.appendChild(opt);
  });
}

// ============================================================
//  CACHE + RENDER OF MATERIALS ON CARD
// ============================================================
estadoApp.materialesPorSolicitud = {};

function normalizarMaterialesParaCard(materiales) {
  if (!Array.isArray(materiales)) return [];
  return materiales.map((m) => ({
    nombre: m.material ?? m.nombre_material ?? m.nombre ?? "Material",
    cantidad: m.cantidad ?? m.cantidad_solicitada ?? 0,
    unidad: m.unidad_medida ?? m.unidad ?? "",
  }));
}

function htmlMaterialesCard(materiales) {
  const mats = normalizarMaterialesParaCard(materiales);

  if (!mats.length) {
    return `
      <div class="mt-3 border-t pt-3 text-sm text-muted-foreground">
        Sin materiales registrados
      </div>
    `;
  }

  return `
    <div class="mt-3 border-t pt-3">
      <div class="text-sm font-medium text-gray-600 mb-2">Materiales solicitados:</div>
      <ul class="space-y-1 text-sm">
        ${mats
          .map(
            (m) => `
          <li class="flex justify-between gap-2">
            <span class="truncate">• ${m.nombre}</span>
            <span class="font-semibold">${m.cantidad}${m.unidad ? ` ${m.unidad}` : ""}</span>
          </li>`
          )
          .join("")}
      </ul>
    </div>
  `;
}

async function cargarMaterialesEnCard(card, idSolicitud) {
  const box = card.querySelector(`.sol-card-materiales[data-mats-for="${idSolicitud}"]`);
  if (!box) return;

  if (estadoApp.materialesPorSolicitud[idSolicitud]) {
    box.innerHTML = htmlMaterialesCard(estadoApp.materialesPorSolicitud[idSolicitud]);
    safeLucideCreateIcons();
    return;
  }

  box.innerHTML = `
    <div class="mt-3 border-t pt-3 text-sm text-muted-foreground">
      <i data-lucide="loader" class="w-4 h-4 inline-block align-text-bottom animate-spin mr-1"></i>
      Cargando materiales...
    </div>
  `;
  safeLucideCreateIcons();

  try {
    const full = await api.obtenerCompleta(idSolicitud);

    const mats =
      full?.materiales ||
      full?.data?.materiales ||
      full?.solicitud?.materiales ||
      [];

    estadoApp.materialesPorSolicitud[idSolicitud] = mats;
    box.innerHTML = htmlMaterialesCard(mats);
    safeLucideCreateIcons();
  } catch (e) {
    box.innerHTML = `
      <div class="mt-3 border-t pt-3 text-sm text-muted-foreground">
        No se pudieron cargar los materiales.
      </div>
    `;
  }
}

// ============================================================
//  HELPER: validates if the materials response is actually filtered
// ============================================================
function respuestaEsStockFiltrado(mats, { bodegaId = null, subId = null } = {}) {
  if (!Array.isArray(mats)) return false;
  if (!mats.length) return true;

  const traeCamposInventario = mats.some(m =>
    m && (
      m.stock_actual != null ||
      m.stock != null ||
      m.id_bodega != null ||
      m.id_subbodega != null
    )
  );
  if (!traeCamposInventario) return false;

  if (subId && !mats.some(m => m && (m.id_subbodega != null))) {
    return false;
  }
  if (subId && mats.some(m => m && m.id_subbodega != null)) {
    const ok = mats.some(m => String(m.id_subbodega) === String(subId));
    if (!ok) return false;
  }

  if (bodegaId && mats.some(m => m && m.id_bodega != null)) {
    const ok = mats.some(m => String(m.id_bodega) === String(bodegaId));
    if (!ok) return false;
  }

  return true;
}


const api = {
  async listarSolicitudes() {
    try {
      const res = await fetch(`${API}?accion=listar`);
      if (!res.ok) throw new Error(`HTTP ${res.status} ${res.statusText}`);

      const raw = await res.text();
      let data;
      try {
        data = JSON.parse(raw);
      } catch {
        throw new Error("El servidor no devolvió JSON válido en listar()");
      }

      if (!Array.isArray(data)) {
        if (data && data.success === false) throw new Error(data.error || "Error en listar()");
        return [];
      }

      return data.map((x) => utilidades.extraerDatosSolicitud(x));
    } catch (e) {
      utilidades.mostrarError(`No se pudieron cargar las solicitudes: ${e.message}`);
      return [];
    }
  },

  async obtenerCompleta(idSolicitud) {
    const res = await fetch(`${API}?accion=obtenerCompleta&id=${encodeURIComponent(idSolicitud)}`);
    if (!res.ok) throw new Error(`HTTP ${res.status} en obtenerCompleta`);
    const data = await res.json();
    return data;
  },


  async cargarActividades(fichaId, raeId) {
    if (!selectores.selectActividad) return;

    selectores.selectActividad.innerHTML = '<option value="">Cargando actividades...</option>';

    if (!fichaId || !raeId) {
      selectores.selectActividad.innerHTML = '<option value="">Seleccione ficha y RAE</option>';
      return;
    }

    try {
      const url1 = `${API}?accion=actividades&ficha=${encodeURIComponent(fichaId)}&rae=${encodeURIComponent(raeId)}`;
      const url2 = `${API}?accion=actividad&ficha=${encodeURIComponent(fichaId)}&rae=${encodeURIComponent(raeId)}`;

      let res = await fetch(url1);
      if (!res.ok) {
        res = await fetch(url2);
      }

      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      const data = await res.json();

      let items = [];
      if (Array.isArray(data)) items = data;
      else if (Array.isArray(data.data)) items = data.data;
      else if (Array.isArray(data.actividades)) items = data.actividades;
      else if (Array.isArray(data.actividades_formacion)) items = data.actividades_formacion;
      else if (data && data.success === false) items = [];
      else if (data && typeof data === 'object') {

        const vals = Object.values(data).find((v) => Array.isArray(v));
        if (Array.isArray(vals)) items = vals;
      }

      if (!Array.isArray(items) || !items.length) {
        selectores.selectActividad.innerHTML = '<option value="">No hay actividades disponibles</option>';
        return;
      }

      selectores.selectActividad.innerHTML = '<option value="">Seleccionar actividad</option>';

      items.forEach((a) => {
        const opt = document.createElement("option");
        opt.value = a.id_actividad ?? a.id ?? "";
        const fullText = a.nombre_actividad ?? a.nombre ?? `Actividad ${opt.value}`;
        opt.textContent = truncateText(fullText, 25);
        opt.title = fullText;
        selectores.selectActividad.appendChild(opt);
      });
    } catch (e) {
      selectores.selectActividad.innerHTML = '<option value="">Error cargando actividades</option>';
      toastError("No se pudieron cargar las actividades. Revise el endpoint en el controller.");
    }
  },

  async cargarRAEs(programaId) {
    if (!selectores.selectRae) return;

    selectores.selectRae.innerHTML = '<option value="">Cargando RAEs...</option>';

    if (!programaId) {
      selectores.selectRae.innerHTML = '<option value="">Seleccione programa</option>';
      return;
    }

    try {
      const url1 = `${API}?accion=raes&programa=${encodeURIComponent(programaId)}`;
      const url2 = `${API}?accion=rae&programa=${encodeURIComponent(programaId)}`;
      const url3 = `${API}?accion=raesPorPrograma&id_programa=${encodeURIComponent(programaId)}`;

      let res = await fetch(url1);
      if (!res.ok) res = await fetch(url2);
      if (!res.ok) res = await fetch(url3);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const data = await res.json();

      let items = [];
      if (Array.isArray(data)) items = data;
      else if (Array.isArray(data.data)) items = data.data;
      else if (Array.isArray(data.raes)) items = data.raes;
      else {
        const vals = Object.values(data || {}).find(v => Array.isArray(v));
        if (Array.isArray(vals)) items = vals;
      }

      if (!items.length) {
        selectores.selectRae.innerHTML = '<option value="">No hay RAEs disponibles</option>';
        return;
      }

      selectores.selectRae.innerHTML = '<option value="">Seleccionar RAE</option>';
      items.forEach(r => {
        const opt = document.createElement("option");
        opt.value = r.id_rae ?? r.id ?? "";
        const fullText = r.codigo_rae
          ? `${r.codigo_rae} - ${r.descripcion_rae || r.descripcion || ""}`.trim()
          : (r.descripcion_rae ?? r.descripcion ?? `RAE ${opt.value}`);
        opt.textContent = truncateText(fullText, 27);
        opt.title = fullText;
        selectores.selectRae.appendChild(opt);
      });
    } catch (e) {
      selectores.selectRae.innerHTML = '<option value="">Error cargando RAEs</option>';
    }
  },

  async cargarFichas(programaId) {
    if (!selectores.selectFichas) return;

    selectores.selectFichas.innerHTML = '<option value="">Cargando fichas...</option>';

    if (!programaId) {
      selectores.selectFichas.innerHTML = '<option value="">Seleccione programa</option>';
      return;
    }

    try {
      const url1 = `${API}?accion=fichas&programa=${encodeURIComponent(programaId)}`;
      const url2 = `${API}?accion=ficha&programa=${encodeURIComponent(programaId)}`;
      const url3 = `${API}?accion=fichasPorPrograma&id_programa=${encodeURIComponent(programaId)}`;

      let res = await fetch(url1);
      if (!res.ok) res = await fetch(url2);
      if (!res.ok) res = await fetch(url3);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const data = await res.json();

      let items = [];
      if (Array.isArray(data)) items = data;
      else if (Array.isArray(data.data)) items = data.data;
      else if (Array.isArray(data.fichas)) items = data.fichas;
      else {
        const vals = Object.values(data || {}).find(v => Array.isArray(v));
        if (Array.isArray(vals)) items = vals;
      }

      if (!items.length) {
        selectores.selectFichas.innerHTML = '<option value="">No hay fichas disponibles</option>';
        return;
      }

      selectores.selectFichas.innerHTML = '<option value="">Seleccionar ficha</option>';
      items.forEach(f => {
        const opt = document.createElement("option");
        opt.value = f.id_ficha ?? f.id ?? "";
      const numero = f.numero_ficha ?? f.ficha ?? f.codigo_ficha ?? opt.value;
      const fullText = `Ficha ${numero}`;
      opt.textContent = fullText;
      opt.title = fullText;
      selectores.selectFichas.appendChild(opt);
    });
    } catch (e) {
      selectores.selectFichas.innerHTML = '<option value="">Error cargando fichas</option>';
    }
  },


  async crearSolicitud(payload) {
    const res = await fetch(`${API}?accion=crear`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    const raw = await res.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch {
      throw new Error(`El servidor no devolvió JSON válido en crear(): ${raw.substring(0, 120)}`);
    }

    if (data?.success) return data;
    throw new Error(data?.error || data?.message || "Error al crear la solicitud");
  },

  async responderSolicitud(idSolicitud, estado, observaciones = null) {
    const res = await fetch(`${API}?accion=responder`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id_solicitud: parseInt(idSolicitud, 10),
        estado,
        id_usuario_aprobador: USUARIO.id,
        observaciones,
      }),
    });

    const raw = await res.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch {
      throw new Error(`El servidor no devolvió JSON válido en responder(): ${raw.substring(0, 120)}`);
    }

    return data;
  },

  async entregarSolicitud(idSolicitud) {
    const res = await fetch(`${API}?accion=entregar`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id_solicitud: parseInt(idSolicitud, 10),
        id_usuario: USUARIO.id,
      }),
    });

    const raw = await res.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch {
      throw new Error(`El servidor no devolvió JSON válido en entregar(): ${raw.substring(0, 120)}`);
    }

    return data;
  },

  async cargarMaterialesFiltrados(bodegaId, subId = 0) {
    try {
      if (!selectores.selectMaterial) return;

      const params = new URLSearchParams();
      params.set("accion", "materiales");
      params.set("bodega", String(bodegaId || 0));

      if (parseInt(subId, 10) > 0) {
        params.set("subbodega", String(subId));
      }

      const url = `${API}?${params.toString()}`;
      const res = await fetch(url);

      if (!res.ok) {
        selectores.selectMaterial.innerHTML = '<option value="">Error cargando materiales</option>';
        return;
      }

      const raw = await res.text();
      let mats = [];
      try {
        mats = JSON.parse(raw);
      } catch {
        selectores.selectMaterial.innerHTML = '<option value="">Error: respuesta inválida</option>';
        return;
      }

      if (parseInt(subId, 10) > 0) {
        const filtradoReal = respuestaEsStockFiltrado(mats, { bodegaId, subId });

        if (!filtradoReal) {
          selectores.selectMaterial.innerHTML =
            '<option value="">No hay materiales en esta subbodega</option>';
          return;
        }

        setMaterialOptions(mats, "subbodega");
        return;
      }
      setMaterialOptions(mats, "bodega");
    } catch (e) {
      utilidades.mostrarError(`Error cargando materiales filtrados: ${e.message}`);
      if (selectores.selectMaterial) {
        selectores.selectMaterial.innerHTML = '<option value="">Error cargando materiales</option>';
      }
    }
  },

  async cargarSelectores() {
    try {
      // PROGRAMS
      if (selectores.selectPrograma) {
        const cargoNorm = normalizarCargo(USUARIO.cargo);

        const esInstructor =
          cargoNorm === "instructor" || cargoNorm.startsWith("instructor");

        const idUsuarioValido =
          USUARIO.id !== null && !Number.isNaN(USUARIO.id) && Number(USUARIO.id) > 0;

        const usarFiltroInstructor = esInstructor && idUsuarioValido;

        if (esInstructor && !idUsuarioValido) {
          toastError("No se pudo identificar tu usuario en sesión (ID inválido).");
        }

        const urls = usarFiltroInstructor
          ? [`${API}?accion=programasPorUsuario&usuario=${encodeURIComponent(USUARIO.id)}`]
          : [`${API}?accion=programas`];

        let programas = [];
        let lastError = null;

        for (const url of urls) {
          try {
            const res = await fetch(url);
            const raw = await res.text();

            let data;
            try {
              data = JSON.parse(raw);
            } catch {
              throw new Error("Respuesta no JSON: " + raw.substring(0, 120));
            }

            if (!res.ok || (data && data.error)) {
              lastError = data?.error || `HTTP ${res.status}`;
              continue;
            }

            if (Array.isArray(data)) programas = data;
            else if (Array.isArray(data.data)) programas = data.data;
            else if (Array.isArray(data.programas)) programas = data.programas;
            else programas = [];

            break;
          } catch (e) {
            lastError = e.message;
          }
        }

        if (!usarFiltroInstructor) {
          programas = filtrarProgramasPorUsuario(programas);
        }

        selectores.selectPrograma.innerHTML = '<option value="">Seleccionar programa</option>';
        selectores.selectPrograma.disabled = false;

        if (Array.isArray(programas) && programas.length) {
          programas.forEach((p) => {
            const opt = document.createElement("option");
            opt.value = p.id_programa ?? p.id ?? "";
            const cod = p.codigo_programa ?? p.codigo ?? "";
            const nom = p.nombre_programa ?? p.nombre ?? "";
            const fullText = `${cod} - ${nom}`.trim().replace(/^-+\s*/, "");
            opt.textContent = truncateText(fullText || `Programa ${opt.value}`, 27);
            opt.title = fullText || opt.textContent;
            selectores.selectPrograma.appendChild(opt);
          });
        } else {
          const msg = usarFiltroInstructor
            ? (lastError ? `No se pudieron cargar programas: ${lastError}` : "No tiene programas asignados")
            : (lastError ? `No se pudieron cargar programas: ${lastError}` : "No hay programas disponibles");

          selectores.selectPrograma.innerHTML = `<option value="">${msg}</option>`;
          selectores.selectPrograma.disabled = true;
        }

        if (selectores.selectPrograma && !selectores.selectPrograma.dataset.boundProg) {
          selectores.selectPrograma.addEventListener("change", async () => {
            const programaId = selectores.selectPrograma.value || "";
            if (selectores.selectRae) {
              selectores.selectRae.innerHTML = '<option value="">Seleccione programa</option>';
              selectores.selectRae.value = "";
            }
            if (selectores.selectFichas) {
              selectores.selectFichas.innerHTML = '<option value="">Seleccione programa</option>';
              selectores.selectFichas.value = "";
            }
            if (selectores.selectActividad) {
              selectores.selectActividad.innerHTML = '<option value="">Seleccione ficha y RAE</option>';
              selectores.selectActividad.value = "";
            }

            await api.cargarRAEs(programaId);
            await api.cargarFichas(programaId);

            if (ES_INSTRUCTOR && FICHA_INSTRUCTOR && selectores.selectFichas) {
              const opt = Array.from(selectores.selectFichas.options)
                .find(o => String(o.value) === String(FICHA_INSTRUCTOR));
              if (opt) {
                selectores.selectFichas.value = String(FICHA_INSTRUCTOR);
              }
            }
          });

          selectores.selectPrograma.dataset.boundProg = "1";
        }



        // Listener Ficha   
        if (selectores.selectFichas && !selectores.selectFichas.dataset.boundAct) {
          selectores.selectFichas.addEventListener("change", () => {
            const fichaId = selectores.selectFichas?.value || "";
            const raeId = selectores.selectRae?.value || "";
            api.cargarActividades(fichaId, raeId);
          });
          selectores.selectFichas.dataset.boundAct = "1";
        }

        // Listener RAE
        if (selectores.selectRae && !selectores.selectRae.dataset.boundAct) {
          selectores.selectRae.addEventListener("change", () => {
            const fichaId = selectores.selectFichas?.value || "";
            const raeId = selectores.selectRae?.value || "";
            api.cargarActividades(fichaId, raeId);
          });
          selectores.selectRae.dataset.boundAct = "1";
        }
      }


      // warehouses
      if (selectores.selectBodega) {
        selectores.selectBodega.innerHTML = '<option value="">Seleccione una bodega</option>';

        const resB = await fetch(`${API}?accion=bodegas`);
          if (resB.ok) {
            const bodegas = await resB.json();
            if (Array.isArray(bodegas) && bodegas.length) {
              bodegas.forEach((b) => {
                const opt = document.createElement("option");
                opt.value = b.id_bodega;
                const fullText = `${b.codigo_bodega} - ${b.nombre}`;
                opt.textContent = truncateText(fullText, 32);
                opt.title = fullText;
                selectores.selectBodega.appendChild(opt);
              });
            } else {

              selectores.selectBodega.innerHTML = '<option value="">No hay bodegas disponibles</option>';
            }
          } else {
            selectores.selectBodega.innerHTML = '<option value="">Error cargando bodegas</option>';
          }

        if (!selectores.selectBodega.dataset.boundChange) {

          selectores.selectBodega.addEventListener("change", async function () {
            const bodegaId = this.value || "";

            estadoApp.filtrosInventario.bodega = bodegaId;
            estadoApp.filtrosInventario.subbodega = "";
            aplicarBloqueoMaterialesPorInventario();


            if (selectores.selectSubBodega) {
              selectores.selectSubBodega.innerHTML = '<option value="">Seleccione una subbodega</option>';
              selectores.selectSubBodega.disabled = !bodegaId;
            }

            if (selectores.selectMaterial) {
              selectores.selectMaterial.innerHTML = '<option value="">Cargando materiales...</option>';
            }
            if (!bodegaId) {
              estadoApp.filtrosInventario.bodega = "";
              estadoApp.filtrosInventario.subbodega = "";

              aplicarBloqueoMaterialesPorInventario();
              return;
            }

            await api.cargarMaterialesFiltrados(bodegaId, 0);

            try {
              const resSub = await fetch(`${API}?accion=subbodegas&bodega=${encodeURIComponent(bodegaId)}`);
                if (selectores.selectSubBodega && resSub.ok) {
                  const subs = await resSub.json();
                selectores.selectSubBodega.innerHTML = '<option value="">Seleccione una subbodega</option>';

                if (Array.isArray(subs) && subs.length) {
                  subs.forEach((s) => {
                    const opt = document.createElement("option");
                    opt.value = s.id_subbodega;
                    const nombre = s.nombre_subbodega || s.nombre || "Subbodega";
                    const fullText = `${s.codigo_subbodega || "SB"} - ${nombre}`;
                    opt.textContent = truncateText(fullText, 27);
                    opt.title = fullText; // Mostrar texto completo en tooltip
                    selectores.selectSubBodega.appendChild(opt);
                  });
                } else {
                  selectores.selectSubBodega.innerHTML = '<option value="">No hay subbodegas</option>';
                }
              } else if (selectores.selectSubBodega) {
                selectores.selectSubBodega.innerHTML = '<option value="">Error cargando subbodegas</option>';
              }
            } catch (e) {
              utilidades.mostrarError(`Error cargando subbodegas: ${e.message}`);
              if (selectores.selectSubBodega) {
                selectores.selectSubBodega.innerHTML = '<option value="">Error cargando subbodegas</option>';
              }
            }
          });
          selectores.selectBodega.dataset.boundChange = "1";
        }
      }

      // When changing sub-warehouse, filter materials
      if (selectores.selectSubBodega && !selectores.selectSubBodega.dataset.boundChange) {
        selectores.selectSubBodega.addEventListener("change", async function () {
          const subId = this.value || "";
          const bodegaId = estadoApp.filtrosInventario.bodega || "";

          estadoApp.filtrosInventario.subbodega = subId;

          if (!bodegaId) {
            aplicarBloqueoMaterialesPorInventario();
            return;
          }

          if (!subId) {
            await api.cargarMaterialesFiltrados(bodegaId, 0);
            return;
          }

          await api.cargarMaterialesFiltrados(bodegaId, subId);
        });

        selectores.selectSubBodega.dataset.boundChange = "1";
      }

      aplicarBloqueoMaterialesPorInventario();

    } catch (e) {
      utilidades.mostrarError(`Error cargando selectores: ${e.message}`);
    }
  },
};

const render = {
  actualizarResumen() {
    const c = { pendiente: 0, aprobada: 0, entregada: 0, rechazada: 0 };
    estadoApp.solicitudes.forEach((s) => {
      const st = s.estado || "pendiente";
      if (c.hasOwnProperty(st)) c[st]++;
    });

    if (selectores.resumenPendientes) selectores.resumenPendientes.textContent = c.pendiente;
    if (selectores.resumenAprobadas) selectores.resumenAprobadas.textContent = c.aprobada;
    if (selectores.resumenEntregadas) selectores.resumenEntregadas.textContent = c.entregada;
    if (selectores.resumenRechazadas) selectores.resumenRechazadas.textContent = c.rechazada;
  },

  actualizarFiltros() {
    const c = { pendiente: 0, aprobada: 0, entregada: 0, rechazada: 0 };
    estadoApp.solicitudes.forEach((s) => {
      const st = s.estado || "pendiente";
      if (c.hasOwnProperty(st)) c[st]++;
    });

    const total = estadoApp.solicitudes.length;
    selectores.filtros.forEach((btn) => {
      const f = btn.dataset.filtro;
      if (f === "todas") btn.textContent = `Todas (${total})`;
      else btn.textContent = `${CONFIG.LABELS[f]}s (${c[f] || 0})`;
    });
  },

  renderizarSolicitudes() {
    const cont = selectores.contenedorCards;
    if (!cont) return;

    if (!estadoApp.solicitudes.length) {
      cont.innerHTML = `
        <div class="col-span-full mt-0 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full">
          <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
            <i data-lucide="file-text" class="h-7 w-7 text-muted-foreground"></i>
          </div>
          <h3 class="text-lg font-semibold mt-4">No hay solicitudes registradas</h3>
          <p class="text-sm text-muted-foreground mt-1 max-w-md">Cree una nueva solicitud para comenzar</p>
        </div>`;
      safeLucideCreateIcons();
      return;
    }

    const filtradas =
      estadoApp.filtroActivo === "todas"
        ? estadoApp.solicitudes
        : estadoApp.solicitudes.filter((s) => (s.estado || "pendiente") === estadoApp.filtroActivo);

    if (!filtradas.length) {
      cont.innerHTML = `
        <div class="col-span-full mt-0 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full">
          <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
            <svg class="h-7 w-7 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <circle cx="11" cy="11" r="6" stroke-linecap="round" stroke-linejoin="round"></circle>
              <line x1="16" y1="16" x2="20" y2="20" stroke-linecap="round" stroke-linejoin="round"></line>
            </svg>
          </div>
          <h3 class="text-lg font-semibold mt-4">No se encontraron resultados</h3>
          <p class="text-sm text-muted-foreground mt-1 max-w-md">No hay solicitudes ${CONFIG.LABELS[estadoApp.filtroActivo]}s que coincidan con el filtro actual.</p>
        </div>`;
      safeLucideCreateIcons();
      return;
    }

    const ini = (estadoApp.paginaActual - 1) * CONFIG.PAGE_SIZE;
    const fin = ini + CONFIG.PAGE_SIZE;
    const pagina = filtradas.slice(ini, fin);

    cont.innerHTML = "";

    pagina.forEach((s) => {
      const st = s.estado || "pendiente";
      const icon = CONFIG.ICONS[st] || "clock";
      const label = CONFIG.LABELS[st] || st;

      const mostrarAccionesPendiente =
        st === "pendiente" && (PERMS.aceptar || PERMS.rechazar);

      const mostrarBtnAceptar = st === "pendiente" && PERMS.aceptar;
      const mostrarBtnRechazar = st === "pendiente" && PERMS.rechazar;

      const mostrarAccionEntregar = st === "aprobada" && PERMS.entregar;


      const card = document.createElement("div");
      card.className = "sol-card";
      card.dataset.id = s.id;

      card.innerHTML = `
        <div class="sol-card-header">
          <div class="sol-card-title-wrap">
            <div class="sol-card-icon ${st}"><i data-lucide="${icon}"></i></div>
            <div>
              <div class="sol-card-title">Solicitud #${s.id}</div>
              <div class="sol-card-date">${s.fecha || "Sin fecha"}</div>
            </div>
          </div>
          <span class="sol-badge ${st}">${label}</span>
        </div>
  
        <div class="sol-card-row">
          <i data-lucide="folder-kanban" class="sol-icon-muted"></i>
          <span>Ficha: ${s.ficha}</span>
        </div>

          ${s.programa ? `
          <div class="sol-card-row">
            <i data-lucide="graduation-cap" class="sol-icon-muted"></i>
            <span>Programa: ${s.programa}</span>
          </div>` : ""}

          ${s.rae ? `
          <div class="sol-card-row">
            <i data-lucide="book-open-text" class="sol-icon-muted"></i>
            <span>RAE: ${s.rae}</span>
          </div>` : ""}

          ${s.observaciones ? `
          <div class="sol-card-row">
            <i data-lucide="message-square" class="sol-icon-muted"></i>
            <span class="truncate" title="${s.observaciones}">
              ${s.observaciones.substring(0, 60)}${s.observaciones.length > 60 ? "..." : ""}
            </span>
          </div>` : ""}

          ${s.fecha_respuesta ? `
          <div class="sol-card-row">
            <i data-lucide="calendar-check" class="sol-icon-muted"></i>
            <span>Respuesta: ${s.fecha_respuesta}</span>
          </div>` : ""}

          <div class="sol-card-materiales" data-mats-for="${s.id}">
            <div class="mt-3 border-t pt-3 text-sm text-muted-foreground">
              <i data-lucide="loader"
                 class="w-4 h-4 inline-block align-text-bottom animate-spin mr-1"></i>
              Cargando materiales...
            </div>
          </div>
        </div>

        ${mostrarAccionesPendiente ? `
          <div class="sol-card-footer mt-4 pt-4 border-t border-gray-200">
            <div class="flex gap-2">
              
              ${mostrarBtnAceptar ? `
                <button class="sol-btn-aceptar flex-1 py-2 px-3 bg-primary text-white rounded-lg flex items-center justify-center gap-2"
                        data-id="${s.id}">
                  <i data-lucide="check-circle" class="w-4 h-4"></i>
                  Aceptar
                </button>
              ` : ""}

              ${mostrarBtnRechazar ? `
                <button class="sol-btn-rechazar flex-1 py-2 px-3 bg-[#c24141] text-white rounded-lg flex items-center justify-center gap-2"
                        data-id="${s.id}">
                  <i data-lucide="x-circle" class="w-4 h-4"></i>
                  Rechazar
                </button>
              ` : ""}

            </div>
          </div>
        ` : ""}


        ${mostrarAccionEntregar ? `
        <div class="sol-card-footer mt-4 pt-4 border-t border-gray-200">
          <button class="sol-btn-entregar w-full py-2 px-3 bg-[#00304D] text-white rounded-lg flex items-center justify-center gap-2"
                  data-id="${s.id}">
            <i data-lucide="package-check" class="w-4 h-4"></i>
            Marcar como entregada
          </button>
        </div>` : ""}
      `;

      cont.appendChild(card);
      cargarMaterialesEnCard(card, s.id);
    });

    safeLucideCreateIcons();
    setTimeout(agregarEventosBotonesAccion, 50);
    this.renderizarPaginacion(filtradas.length);
  },

  renderizarPaginacion(totalItems) {
    if (!selectores.paginacion) return;

    const totalPaginas = Math.ceil(totalItems / CONFIG.PAGE_SIZE);
    if (totalPaginas <= 1) {
      selectores.paginacion.innerHTML = "";
      return;
    }

    let html = `
      <button class="sol-paginacion-btn ${estadoApp.paginaActual === 1 ? "disabled" : ""}"
              ${estadoApp.paginaActual === 1 ? "disabled" : ""}
              onclick="paginacion.cambiarPagina(${estadoApp.paginaActual - 1})">
        <i data-lucide="chevron-left" class="w-4 h-4"></i>
      </button>`;

    for (let i = 1; i <= totalPaginas; i++) {
      if (
        i === 1 ||
        i === totalPaginas ||
        (i >= estadoApp.paginaActual - 1 && i <= estadoApp.paginaActual + 1)
      ) {
        html += `
          <button class="sol-paginacion-btn ${estadoApp.paginaActual === i ? "active" : ""}"
                  onclick="paginacion.cambiarPagina(${i})">${i}</button>`;
      } else if (i === estadoApp.paginaActual - 2 || i === estadoApp.paginaActual + 2) {
        html += `<span class="px-2 text-gray-400">...</span>`;
      }
    }

    html += `
      <button class="sol-paginacion-btn ${estadoApp.paginaActual === totalPaginas ? "disabled" : ""}"
              ${estadoApp.paginaActual === totalPaginas ? "disabled" : ""}
              onclick="paginacion.cambiarPagina(${estadoApp.paginaActual + 1})">
        <i data-lucide="chevron-right" class="w-4 h-4"></i>
      </button>`;

    selectores.paginacion.innerHTML = html;
    safeLucideCreateIcons();
  },

  renderizarMateriales() {
    if (!selectores.listaMateriales) return;

    if (!estadoApp.materialesSeleccionados.length) {
      selectores.listaMateriales.innerHTML = `
        <div class="text-center text-muted-foreground py-8">
          <i data-lucide="package" class="w-8 h-8 mx-auto mb-2"></i>
          <p>No hay materiales agregados</p>
        </div>`;
      safeLucideCreateIcons();
      return;
    }

    let html = `
      <div class="space-y-2">
        <div class="flex justify-between text-sm font-medium text-gray-500 pb-2 border-b">
          <span class="flex-1">Material</span>
          <span class="w-24 text-center">Cantidad</span>
          <span class="w-16 text-center">Acciones</span>
        </div>`;

    estadoApp.materialesSeleccionados.forEach((m, idx) => {
      html += `
        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
          <div class="flex-1">
            <div class="font-medium">${m.nombre}</div>
            <div class="text-sm text-gray-500">${m.unidad} • Stock: ${m.stock}</div>
          </div>
          <div class="w-24 text-center"><span class="font-semibold">${m.cantidad}</span></div>
          <div class="w-16 text-center">
            <button type="button" onclick="materiales.eliminarMaterial(${idx})"
                    class="p-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded">
              <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
          </div>
        </div>`;
    });

    html += `</div>`;
    selectores.listaMateriales.innerHTML = html;
    safeLucideCreateIcons();
  },
};

const materiales = {
  agregarMaterial() {
    if (!selectores.selectMaterial || !selectores.inputCantidad) return;

    const ok = aplicarBloqueoMaterialesPorInventario();
    if (!ok) {
      return utilidades.mostrarError("Seleccione una bodega o sub-bodega antes de agregar materiales.");
    } 

    const id = selectores.selectMaterial.value;
    const cantidad = parseInt(selectores.inputCantidad.value, 10);

    if (!id) return utilidades.mostrarError("Seleccione un material");
    if (!cantidad || cantidad < 1) return utilidades.mostrarError("Cantidad inválida");

    const opt = selectores.selectMaterial.selectedOptions?.[0];
    if (!opt) return utilidades.mostrarError("Seleccione un material válido");

    const stock = parseInt(opt.dataset.stock, 10) || 0;
    const unidad = opt.dataset.unidad || "UND";
    const nombre = opt.dataset.nombre || opt.textContent;

    if (stock <= 0) {
      utilidades.mostrarError("Este material no tiene stock disponible.");
      return;
    }

    if (cantidad > stock) {
      utilidades.mostrarError(`Stock insuficiente. Disponible: ${stock} ${unidad}`);
      selectores.inputCantidad.value = String(stock);
      selectores.inputCantidad.focus();
      return;
    }

    const existe = estadoApp.materialesSeleccionados.find((m) => String(m.id) === String(id));
    if (existe) {
      if (confirm("Este material ya fue agregado. ¿Actualizar cantidad?")) {
        existe.cantidad = cantidad;
        render.renderizarMateriales();
      }
      return;
    }

    estadoApp.materialesSeleccionados.push({ id, nombre, cantidad, stock, unidad });
    selectores.selectMaterial.value = "";
    selectores.inputCantidad.value = "1";
    render.renderizarMateriales();
  },

  eliminarMaterial(index) {
    estadoApp.materialesSeleccionados.splice(index, 1);
    render.renderizarMateriales();
    toastInfo("Material eliminado.");
  },

  limpiarMateriales() {
    estadoApp.materialesSeleccionados = [];
    render.renderizarMateriales();
  },
};

const paginacion = {
  cambiarPagina(nuevaPagina) {
    const total =
      estadoApp.filtroActivo === "todas"
        ? estadoApp.solicitudes.length
        : estadoApp.solicitudes.filter((s) => (s.estado || "pendiente") === estadoApp.filtroActivo).length;

    const totalPag = Math.ceil(total / CONFIG.PAGE_SIZE);
    if (nuevaPagina < 1 || nuevaPagina > totalPag) return;

    estadoApp.paginaActual = nuevaPagina;
    render.renderizarSolicitudes();

    if (selectores.contenedorCards && typeof selectores.contenedorCards.offsetTop === "number") {
      window.scrollTo({ top: selectores.contenedorCards.offsetTop - 100, behavior: "smooth" });
    }
  },
};

// ============================================================
// BUTTONS: Accept / Reject / Deliver
// CORRECTION: Avoid duplicating events with data-bound
// ============================================================
function agregarEventosBotonesAccion() {
  if (PERMS.aceptar) {
    document.querySelectorAll(".sol-btn-aceptar").forEach((btn) => {
      if (btn.dataset.bound === "1") return;
      btn.dataset.bound = "1";

      btn.addEventListener("click", async (e) => {
        e.preventDefault();
        e.stopPropagation();
        const idSolicitud = btn.dataset.id;
        await cambiarEstadoSolicitud(idSolicitud, "aprobada");
      });
    });
  }

  if (PERMS.rechazar) {
    document.querySelectorAll(".sol-btn-rechazar").forEach((btn) => {
      if (btn.dataset.bound === "1") return;
      btn.dataset.bound = "1";

      btn.addEventListener("click", async (e) => {
        e.preventDefault();
        e.stopPropagation();
        const idSolicitud = btn.dataset.id;

        const motivo = await pedirMotivoRechazo();
        if (motivo === null) return;

        await cambiarEstadoSolicitud(idSolicitud, "rechazada", motivo);
      });
    });
  }

  if (PERMS.entregar) {
    document.querySelectorAll(".sol-btn-entregar").forEach((btn) => {
      if (btn.dataset.bound === "1") return;
      btn.dataset.bound = "1";

      btn.addEventListener("click", async (e) => {
        e.preventDefault();
        e.stopPropagation();
        const idSolicitud = btn.dataset.id;
        await marcarEntregada(idSolicitud);
      });
    });
  }
}

async function cambiarEstadoSolicitud(idSolicitud, nuevoEstado, motivo = null) {
  try {
    const card = document.querySelector(`.sol-card[data-id="${idSolicitud}"]`);
    if (!card) return utilidades.mostrarError("No se encontró la solicitud en la interfaz.");

    const btnA = card.querySelector(".sol-btn-aceptar");
    const btnR = card.querySelector(".sol-btn-rechazar");

    if (btnA) {
      btnA.disabled = true;
      btnA.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i>';
    }
    if (btnR) {
      btnR.disabled = true;
      btnR.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i>';
    }
    safeLucideCreateIcons();

    const resp = await api.responderSolicitud(idSolicitud, nuevoEstado, motivo);

    if (resp?.success) {
      utilidades.mostrarExito(resp.message || "Solicitud actualizada");
      await app.cargarSolicitudes();
      window.dispatchEvent(new Event("solicitudes:updated"));
      return;
    }

    throw new Error(resp?.error || resp?.message || "No se pudo actualizar la solicitud.");
  } catch (e) {
    utilidades.mostrarError(e.message);
    await app.cargarSolicitudes();
  }
}

async function marcarEntregada(idSolicitud) {
  const card = document.querySelector(`.sol-card[data-id="${idSolicitud}"]`);
  const btnE = card ? card.querySelector(".sol-btn-entregar") : null;

  try {
    if (btnE) {
      btnE.disabled = true;
      btnE.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Procesando...';
      safeLucideCreateIcons();
    }

    const resp = await api.entregarSolicitud(idSolicitud);

    if (resp?.success) {
      utilidades.mostrarExito(resp.message || "Solicitud marcada como entregada");
      await app.cargarSolicitudes();
      window.dispatchEvent(new Event("solicitudes:updated"));
      return;
    }

    throw new Error(resp?.error || resp?.message || "No se pudo marcar como entregada.");
  } catch (e) {
    utilidades.mostrarError(e.message);
    if (btnE) {
      btnE.disabled = false;
      btnE.innerHTML = '<i data-lucide="package-check" class="w-4 h-4"></i> Marcar como entregada';
      safeLucideCreateIcons();
    }
  }
}

// ============================================================
//  MODAL
// ============================================================
const modal = {
  async abrir() {
    if (!selectores.modal) return;

    selectores.modal.classList.add("sol-modal-show");
    selectores.paso1?.classList.remove("hidden");
    selectores.paso2?.classList.add("hidden");

    if (selectores.btnGuardar) selectores.btnGuardar.style.display = "none";
    this.limpiarFormulario();

    try {
      await api.cargarSelectores();
    } catch (e) {
    }

    setTimeout(() => selectores.selectPrograma?.focus(), 50);
  },

  cerrar() {
    if (!selectores.modal) return;

    selectores.modal.classList.remove("sol-modal-show");
    this.limpiarFormulario();
  },



  limpiarFormulario() {
    estadoApp.datosFormulario = { programa: "", rae: "", ficha: "", observaciones: "" };
    estadoApp.materialesSeleccionados = [];

    estadoApp.filtrosInventario = { bodega: "", subbodega: "" };

    if (selectores.formNueva) selectores.formNueva.reset();

    if (selectores.selectActividad) {
      selectores.selectActividad.innerHTML = '<option value="">Seleccione ficha y RAE</option>';
      selectores.selectActividad.value = "";
    }

    if (selectores.selectBodega) selectores.selectBodega.value = "";
    if (selectores.selectSubBodega) {
      selectores.selectSubBodega.value = "";
      selectores.selectSubBodega.disabled = true;
      selectores.selectSubBodega.innerHTML = '<option value="">Seleccione una subbodega</option>';
    }

    materiales.limpiarMateriales();
  },

  validarPaso1() {
    if (!selectores.selectPrograma?.value) {
      utilidades.mostrarError("Seleccione un programa");
      selectores.selectPrograma?.focus();
      return false;
    }
    if (!selectores.selectRae?.value) {
      utilidades.mostrarError("Seleccione un RAE");
      selectores.selectRae?.focus();
      return false;
    }
    if (!selectores.selectFichas?.value) {
      utilidades.mostrarError("Seleccione una ficha");
      selectores.selectFichas?.focus();
      return false;
    }
    if (!selectores.selectActividad?.value) {
      utilidades.mostrarError("Seleccione una actividad");
      selectores.selectActividad?.focus();
      return false;
    }
    estadoApp.datosFormulario = {
      programa: selectores.selectPrograma.value,
      rae: selectores.selectRae.value,
      ficha: selectores.selectFichas.value,
      actividad: selectores.selectActividad.value,
      observaciones: (selectores.textareaObservaciones?.value || "").trim(),
    };

    return true;
  },

  irPaso2() {
    if (!this.validarPaso1()) return;
    selectores.paso1?.classList.add("hidden");
    selectores.paso2?.classList.remove("hidden");
    if (selectores.btnGuardar) selectores.btnGuardar.style.display = "inline-flex";
    selectores.selectBodega?.focus();
  },

  volverPaso1() {
    selectores.paso2?.classList.add("hidden");
    selectores.paso1?.classList.remove("hidden");
    if (selectores.btnGuardar) selectores.btnGuardar.style.display = "none";
    selectores.selectPrograma?.focus();
  },

  async enviarSolicitud() {
    if (!estadoApp.materialesSeleccionados.length) {
      utilidades.mostrarError("Debe agregar al menos un material");
      selectores.selectMaterial?.focus();
      return;
    }

    const idActividad = parseInt(estadoApp.datosFormulario.actividad, 10);
    if (!idActividad || isNaN(idActividad)) {
      utilidades.mostrarError("Actividad inválida. Seleccione una actividad.");
      selectores.selectActividad?.focus();
      return;
    }

    if (USUARIO.id === null || Number.isNaN(USUARIO.id)) {
      utilidades.mostrarError("No se pudo identificar el usuario en sesión.");
      return;
    }

    try {
      if (selectores.btnGuardar) {
        selectores.btnGuardar.disabled = true;
        selectores.btnGuardar.innerHTML =
          '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Procesando...';
      }
      safeLucideCreateIcons();

      const payload = {
        id_usuario: USUARIO.id,
        id_programa: parseInt(estadoApp.datosFormulario.programa, 10),
        id_rae: parseInt(estadoApp.datosFormulario.rae, 10),
        id_ficha: parseInt(estadoApp.datosFormulario.ficha, 10),
        id_actividad: parseInt(estadoApp.datosFormulario.actividad, 10),
        observaciones: estadoApp.datosFormulario.observaciones || "",
        materiales: estadoApp.materialesSeleccionados.map((m) => ({
          id_material: parseInt(m.id, 10),
          cantidad_solicitada: parseInt(m.cantidad, 10),
        })),
      };

      const resp = await api.crearSolicitud(payload);
      utilidades.mostrarExito(resp.message || "Solicitud creada correctamente");

      await app.cargarSolicitudes();
      this.cerrar();
    } catch (e) {
      utilidades.mostrarError(e.message);
    } finally {
      if (selectores.btnGuardar) {
        selectores.btnGuardar.disabled = false;
        selectores.btnGuardar.innerHTML = "Crear Solicitud";
      }
      safeLucideCreateIcons();
    }
  },
};

const eventos = {
  inicializar() {
    selectores.btnNueva?.addEventListener("click", () => modal.abrir());
    selectores.btnCerrarModal?.addEventListener("click", () => modal.cerrar());
    selectores.btnCancelar?.addEventListener("click", () => modal.cerrar());
    selectores.btnPaso2?.addEventListener("click", () => modal.irPaso2());
    selectores.btnVolver?.addEventListener("click", () => modal.volverPaso1());
    selectores.btnAgregarMaterial?.addEventListener("click", () => materiales.agregarMaterial());

    selectores.inputCantidad?.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        materiales.agregarMaterial();
      }
    });

    selectores.formNueva?.addEventListener("submit", async (e) => {
      e.preventDefault();
      e.stopPropagation();
      await modal.enviarSolicitud();
      return false;
    });

    selectores.filtros?.forEach((btn) => {
      btn.addEventListener("click", () => {
        selectores.filtros.forEach((b) => b.classList.remove("sol-filtro-btn-activo"));
        btn.classList.add("sol-filtro-btn-activo");
        estadoApp.filtroActivo = btn.dataset.filtro;
        estadoApp.paginaActual = 1;
        render.renderizarSolicitudes();
      });
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && selectores.modal?.classList.contains("sol-modal-show")) {
        modal.cerrar();
      }
    });

    selectores.modal?.addEventListener("click", (e) => {
      if (e.target === selectores.modal) modal.cerrar();
    });
  },
};

const app = {
  async inicializar() {
    if (!selectores.contenedorCards) {
      return;
    }

    selectores.contenedorCards.innerHTML = `
      <div class="col-span-full py-12 text-center">
        <i data-lucide="loader" class="w-12 h-12 text-blue-300 animate-spin mx-auto mb-4"></i>
        <h3 class="text-lg font-medium text-gray-700 mb-2">Cargando solicitudes</h3>
        <p class="text-gray-500">Obteniendo datos de la base de datos...</p>
      </div>`;
    safeLucideCreateIcons();

    await this.cargarSolicitudes();
    await api.cargarSelectores();
    aplicarBloqueoMaterialesPorInventario();
    eventos.inicializar();
  },

  async cargarSolicitudes() {
  const todas = await api.listarSolicitudes();
  estadoApp.solicitudes = filtrarSolicitudesPorUsuario(todas);

  render.actualizarResumen();
  render.actualizarFiltros();
  render.renderizarSolicitudes();
},

};

document.addEventListener("DOMContentLoaded", () => {
  safeLucideCreateIcons();
  initLimitesCaracteres();
  app.inicializar();

  if (selectores.btnNueva && !PERMS.crear) {
    selectores.btnNueva.style.display = "none";
  }

});

window.paginacion = paginacion;
window.materiales = materiales;
window.app = app;
window.agregarEventosBotonesAccion = agregarEventosBotonesAccion;
window.cambiarEstadoSolicitud = cambiarEstadoSolicitud;
window.marcarEntregada = marcarEntregada;
