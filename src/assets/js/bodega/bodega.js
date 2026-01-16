/* =========================================================
   CONFIGURACIÓN GENERAL
========================================================= */

  const API_MATERIALES = new URL(
  "src/controllers/material_formacion_controller.php",
  document.baseURI
).toString();

  const API_URL = new URL("src/controllers/bodega_controller.php", document.baseURI).toString();
  const API_SUBBODEGAS = new URL("src/controllers/sub_bodega_controller.php", document.baseURI).toString();



document.addEventListener("click", (e) => {
  const btn = e.target.closest(".btn-toggle-subbodegas");
  if (!btn) return;

  e.preventDefault();

  const idBodega = btn.dataset.id;
  const tr = btn.closest("tr");
  if (!tr) return;

  // toggle
  const next = tr.nextElementSibling;
  if (next && next.classList.contains("subbodegas-row")) {
    next.remove();
    btn.textContent = "Ver sub-bodegas";
    return;
  }

  btn.textContent = "Ocultar sub-bodegas";

  const subs = allSubBodegas.filter(
    sb => String(sb.id_bodega) === String(idBodega)
  );

  const html = subs.length === 0
    ? `<p class="text-sm text-gray-500">No tiene sub-bodegas</p>`
    : subs.map(sb => `
  <div class="flex items-center justify-between p-2 rounded-lg border bg-gray-50">
    <div>
      <p class="text-sm font-medium">${sb.nombre_subbodega}</p>
      <p class="text-xs text-gray-500">
        ${sb.codigo_subbodega} · ${sb.clasificacion_subbodegas}
      </p>
    </div>

    <div class="flex items-center gap-2">
      <span class="text-xs px-2 py-1 rounded-full
        ${sb.estado === "Activo"
          ? "bg-emerald-100 text-emerald-700"
          : "bg-red-100 text-red-700"}">
        ${sb.estado}
      </span>

      <button
        type="button"
        class="w-8 h-8 rounded-full inline-flex items-center justify-center subbodegas-btn-dots hover:bg-gray-200"
        data-id="${sb.id_subbodega}"
        data-nombre="${sb.nombre_subbodega}"
        data-codigo="${sb.codigo_subbodega}"
        data-estado="${sb.estado}"
      >
        <i data-lucide="more-horizontal" class="w-4 h-4"></i>
      </button>
    </div>
  </div>
`).join("")


  tr.after(subRow);
});

let allSubBodegas = [];
let subBodegasCountByBodega = {};

  // ============================
  // HELPERS
  // ============================

  const $ = (id) => document.getElementById(id);

let vistaActual = "tabla";

/* ELEMENTOS DEL DOM */
const tbody = document.getElementById("tbodyBodegas");
const contCards = document.getElementById("cardsBodegasContainer");

const emptyState = document.getElementById("emptyStateBodegas");

const inputBuscar = document.getElementById("inputBuscarBodega");
const filtroTipo = document.getElementById("selectFiltroTipo");
const filtroEstado = document.getElementById("selectFiltroEstado");

const modal = document.getElementById("modalBodega");
const btnNueva = document.getElementById("btnNuevaBodega");
const btnCerrar = document.getElementById("btnCerrarModalBodega");
const btnCancelar = document.getElementById("btnCancelarModalBodega");
const form = document.getElementById("formBodega");

const modalTitulo = document.getElementById("modalBodegaTitulo");
const hiddenId = document.getElementById("hiddenRegistroId");

const tipoRegistro = document.getElementById("tipo_registro");
const wrapperPadre = document.getElementById("wrapper_bodega_padre");
const idPadre = document.getElementById("id_bodega_padre");

const inputCodigo = document.getElementById("codigo_registro");
const inputNombre = document.getElementById("nombre_registro");
const wrapperUbicacion = document.getElementById("wrapper_ubicacion");
const inputUbicacion = document.getElementById("ubicacion_registro");

const wrapperClasificacion = document.getElementById("wrapper_clasificacion");
const inputClasificacion = document.getElementById("clasificacion_registro");

const wrapperDescripcion = document.getElementById("wrapper_descripcion");
const inputDescripcion = document.getElementById("descripcion_registro");

const wrapperEstado = document.getElementById("wrapper_estado_registro");
const inputEstado = document.getElementById("estado_registro");

/* =========================================================
   OBTENER DATOS API
========================================================= */
async function cargarDatos() {
  try {
    // ------- BODEGAS -------
    const resB = await fetch(`${API_BODEGA}?accion=listar`);
    if (!resB.ok) {
      const txt = await resB.text();
      console.error("Error HTTP bodegas:", resB.status, txt);
      throw new Error(`Error HTTP bodegas ${resB.status}`);
    }
    listaBodegas = await resB.json();

    // ------- SUBBODEGAS -------
    const resS = await fetch(`${API_SUB}?accion=listar`);
    if (!resS.ok) {
      const txt = await resS.text();
      console.error("Error HTTP subbodegas:", resS.status, txt);
      throw new Error(`Error HTTP subbodegas ${resS.status}`);
    }
    listaSubbodegas = await resS.json();

    // Unificar
    listaTotal = [
      ...listaBodegas.map(b => ({ ...b, tipo: "bodega" })),
      ...listaSubbodegas.map(s => ({ ...s, tipo: "subbodega" }))
    ];

    render();
    cargarBodegasPadre();

  } catch (e) {
    console.error("ERROR al cargar datos:", e);
  }
}

/* =========================================================
   RENDERIZAR LISTA
========================================================= */
function render() {
  const termino = inputBuscar.value.toLowerCase().trim();

  let filtrado = listaTotal.filter(item => {
    const coincideTexto =
      (item.nombre && item.nombre.toLowerCase().includes(termino)) ||
      (item.nombre_subbodega && item.nombre_subbodega.toLowerCase().includes(termino)) ||
      (item.codigo_bodega && item.codigo_bodega.toLowerCase().includes(termino)) ||
      (item.codigo_subbodega && item.codigo_subbodega.toLowerCase().includes(termino));

    let coincideTipo =
      filtroTipo.value === "todos" || filtroTipo.value === item.tipo;

    let coincideEstado =
      filtroEstado.value === "todos" || filtroEstado.value === item.estado;

    return coincideTexto && coincideTipo && coincideEstado;
  });

  if (filtrado.length === 0) {
    emptyState.classList.remove("hidden");
  } else {
    emptyState.classList.add("hidden");
  }

  renderTabla(filtrado);
  renderTarjetas(filtrado);

  // Re-generar iconos Lucide después de inyectar HTML
  lucide.createIcons();
}

/* =========================================================
   TABLA
========================================================= */
function renderTabla(data) {
  tbody.innerHTML = "";

  data.forEach(item => {
    const fila = document.createElement("tr");

    fila.innerHTML = `
      <td class="px-4 py-3">${item.id_bodega || item.id_subbodega}</td>
      <td class="px-4 py-3">${item.nombre || item.nombre_subbodega}</td>
      <td class="px-4 py-3">${item.codigo_bodega || item.codigo_subbodega}</td>
      <td class="px-4 py-3">${item.ubicacion || "-"}</td>
      <td class="px-4 py-3">${item.tipo === "bodega" ? "Bodega" : "Sub-bodega"}</td>
      <td class="px-4 py-3">
        <span class="px-2 py-1 rounded text-xs ${
          item.estado === "Activo"
            ? "bg-green-200 text-green-700"
            : "bg-red-200 text-red-700"
        }">
          ${item.estado}
        </span>
      </td>
      <td class="px-4 py-3">
        <div class="flex gap-2 justify-end">

          <button class="btn-secondary" onclick="verDetalles('${item.tipo}', ${item.id_bodega || item.id_subbodega})">
            <i data-lucide="eye" class="w-4 h-4"></i>
          </button>

          <button class="btn-primary" onclick="editar('${item.tipo}', ${item.id_bodega || item.id_subbodega})">
            <i data-lucide="pencil" class="w-4 h-4"></i>
          </button>

          <button class="btn-danger" onclick="cambiarEstado('${item.tipo}', ${item.id_bodega || item.id_subbodega}, '${item.estado}')">
            <i data-lucide="toggle-left" class="w-4 h-4"></i>
          </button>

        </div>
      </td>
    `;

    tbody.appendChild(fila);
  });
}

/* =========================================================
   TARJETAS
========================================================= */
function renderTarjetas(data) {
  contCards.innerHTML = "";

  data.forEach(item => {
    const div = document.createElement("div");
    div.className = "card-bodega";

    div.innerHTML = `
      <div class="flex justify-between items-start">
        <h3 class="font-semibold">${item.nombre || item.nombre_subbodega}</h3>
        <span class="text-xs ${item.estado === "Activo" ? "text-green-600" : "text-red-600"}">
          ${item.estado}
        </span>
      </div>

      <p class="text-sm text-muted">${item.tipo === "bodega" ? "Bodega" : "Sub-bodega"}</p>

      <p class="mt-1 text-sm">Código: <strong>${item.codigo_bodega || item.codigo_subbodega}</strong></p>

      <div class="mt-3 flex gap-2">
        <button class="btn-secondary" onclick="verDetalles('${item.tipo}', ${item.id_bodega || item.id_subbodega})">
          <i data-lucide="eye" class="w-4 h-4"></i>
        </button>

        <button class="btn-primary" onclick="editar('${item.tipo}', ${item.id_bodega || item.id_subbodega})">
          <i data-lucide="pencil" class="w-4 h-4"></i>
        </button>
      </div>
    `;

    contCards.appendChild(div);
  });
}

/* =========================================================
   FORMULARIO / MODAL
========================================================= */

function abrirModal() {
  modal.classList.add("active");
}

function cerrarModal() {
  modal.classList.remove("active");
  form.reset();
  hiddenId.value = "";
  wrapperEstado.classList.add("hidden");
}

btnNueva.onclick = () => {
  modalTitulo.textContent = "Crear Nueva Bodega";
  abrirModal();
};

btnCerrar.onclick = cerrarModal;
btnCancelar.onclick = cerrarModal;

/* Control dependiente del tipo */
tipoRegistro.onchange = () => {
  const tipo = tipoRegistro.value;

  if (tipo === "bodega") {
    wrapperPadre.classList.add("hidden");
    wrapperUbicacion.classList.remove("hidden");
    wrapperClasificacion.classList.add("hidden");
    wrapperDescripcion.classList.add("hidden");
  } else {
    wrapperPadre.classList.remove("hidden");
    wrapperUbicacion.classList.add("hidden");
    wrapperClasificacion.classList.remove("hidden");
    wrapperDescripcion.classList.remove("hidden");
  }
};

/* =========================================================
   CARGAR LISTA DE BODEGAS COMO PADRES PARA SUBBODEGA
========================================================= */
function cargarBodegasPadre() {
  idPadre.innerHTML = `<option value="">Seleccione una bodega</option>`;

  listaBodegas.forEach(b => {
    idPadre.innerHTML += `<option value="${b.id_bodega}">${b.nombre}</option>`;
  });
}

/* =========================================================
   VER DETALLES (solo lectura)
========================================================= */
function verDetalles(tipo, id) {
  let data;

  if (tipo === "bodega") {
    data = listaBodegas.find(b => b.id_bodega == id);
  } else {
    data = listaSubbodegas.find(s => s.id_subbodega == id);
  }

  const toastError = (msg) => showFlowbiteAlert("warning", msg);
  const toastSuccess = (msg) => showFlowbiteAlert("success", msg);
  const toastInfo = (msg) => showFlowbiteAlert("info", msg);

  // ============================
  // PAGE GUARD
  // ============================
  const isBodegasPage =
    !!$("btnCrearBodegaMenu") ||
    !!$("btnVistaTabla") ||
    !!$("context-menu") ||
    !!$("modalCrear") ||
    !!$("vistaTabla");

  if (!isBodegasPage) return;

  safeIcons();

  // ============================
  // LISTA / GRID
  // ============================
  const btnVistaTabla = $("btnVistaTabla");
  const btnVistaTarjetas = $("btnVistaTarjetas");
  const vistaTabla = $("vistaTabla");
  const vistaTarjetas = $("vistaTarjetas");

  const setActiveBtn = (active, inactive) => {
    if (!active || !inactive) return;
    active.classList.add("bg-muted", "text-foreground");
    active.classList.remove("text-muted-foreground");
    inactive.classList.remove("bg-muted", "text-foreground");
    inactive.classList.add("text-muted-foreground");
  };

  const showList = () => {
    vistaTabla?.classList.remove("hidden");
    vistaTarjetas?.classList.add("hidden");
    setActiveBtn(btnVistaTabla, btnVistaTarjetas);
  };

  const showGrid = () => {
    vistaTarjetas?.classList.remove("hidden");
    vistaTabla?.classList.add("hidden");
    setActiveBtn(btnVistaTarjetas, btnVistaTabla);
    safeIcons();
  };

  btnVistaTabla?.addEventListener("click", showList);
  btnVistaTarjetas?.addEventListener("click", showGrid);
  showList();

  // ============================
  // DROPDOWN CREAR BODEGA
  // ============================
  const btnCrearBodegaMenu = $("btnCrearBodegaMenu");
  const menuCrearBodega = $("menuCrearBodega");

  const closeCreateMenu = () => menuCrearBodega?.classList.add("hidden");

  const toggleCreateMenu = () => {
    if (!menuCrearBodega) return;
    menuCrearBodega.classList.toggle("hidden");
    safeIcons();
  };

  btnCrearBodegaMenu?.addEventListener("click", (e) => {
    e.preventDefault();
    e.stopPropagation();
    toggleCreateMenu();
  });

  document.addEventListener("click", (e) => {
    if (!menuCrearBodega) return;
    if (btnCrearBodegaMenu && btnCrearBodegaMenu.contains(e.target)) return;
    if (menuCrearBodega.contains(e.target)) return;
    closeCreateMenu();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeCreateMenu();
  });

  // ============================
  // MODAL CREAR BODEGA
  // ============================
  const btnNuevaBodega = $("btnNuevaBodega");
  const modalCrear = $("modalCrear");
  const formCrearBodega = $("formCrearBodega");
  const cerrarModal = $("cerrarModal");
  const cancelarModal = $("cancelarModal");
  const backdropCrear = $("backdropCrear");

  btnNuevaBodega?.addEventListener("click", () => {
    closeCreateMenu();
    openModal(modalCrear);
  });

  cerrarModal?.addEventListener("click", () => closeModal(modalCrear));
  cancelarModal?.addEventListener("click", () => closeModal(modalCrear));
  backdropCrear?.addEventListener("click", () => closeModal(modalCrear));
  modalCrear?.addEventListener("click", (e) => {
    if (e.target === modalCrear) closeModal(modalCrear);
  });

  formCrearBodega?.addEventListener("submit", async (e) => {
    e.preventDefault();

    const codigo = ($("crearCodigo")?.value || "").trim();
    const nombre = ($("crearNombre")?.value || "").trim();
    const ubicacion = ($("crearUbicacion")?.value || "").trim();
    const clasificacion = $("crearClasificacion") ? $("crearClasificacion").value : "";

    if (!codigo || !nombre || !ubicacion || !clasificacion) {
      toastError("Completa todos los campos obligatorios.");
      return;
    }

    try {
      const res = await fetch(`${API_URL}?accion=crear`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          codigo_bodega: codigo,
          nombre,
          ubicacion,
          clasificacion_bodega: clasificacion,
        }),
      });

      const parsed = await safeJson(res);

if (!res.ok) {
  console.error("[BACKEND RAW]", parsed.raw);
  throw new Error(`HTTP ${res.status} - ${parsed.raw?.slice(0, 300) || "Sin respuesta"}`);
}

if (parsed?.data?.error) {
  throw new Error(parsed.data.error);
}


      closeModal(modalCrear);
      toastSuccess(parsed?.data?.mensaje || "Bodega creada correctamente.");
      // aquí sí recargamos porque tú estás renderizando desde PHP (server-side)
      setTimeout(() => location.reload(), 650);
    } catch (err) {
      console.error(err);
      toastError(err?.message || "No se pudo crear la bodega.");
    }
  });

// ============================
// MODAL CREAR SUB-BODEGA (FIX)
// ============================
const btnNuevaSubBodega = $("btnNuevaSubBodega");
const modalCrearSubBodega = $("modalCrearSubBodega");
const formCrearSubBodega = $("formCrearSubBodega");
const cerrarModalSub = $("cerrarModalSub");
const cancelarModalSub = $("cancelarModalSub");
const backdropCrearSub = $("backdropCrearSub");

btnNuevaSubBodega?.addEventListener("click", () => {
  closeCreateMenu();
  openModal(modalCrearSubBodega);
});

cerrarModalSub?.addEventListener("click", () => closeModal(modalCrearSubBodega));
cancelarModalSub?.addEventListener("click", () => closeModal(modalCrearSubBodega));
backdropCrearSub?.addEventListener("click", () => closeModal(modalCrearSubBodega));
modalCrearSubBodega?.addEventListener("click", (e) => {
  if (e.target === modalCrearSubBodega) closeModal(modalCrearSubBodega);
});

formCrearSubBodega?.addEventListener("submit", async (e) => {
  e.preventDefault();

  const idBodegaPadre = ($("id_bodega")?.value || "").trim();
  const codigo = ($("subCodigo")?.value || "").trim();
  const nombre = ($("subNombre")?.value || "").trim();
  const clasificacion = ($("subClasificacion")?.value || "").trim();
  const descripcion = ($("subDescripcion")?.value || "").trim();

  if (!idBodegaPadre || !codigo || !nombre || !clasificacion) {
    toastError("Completa todos los campos obligatorios.");
    return;
  }

  try {
    const res = await fetch(
      `${API_SUBBODEGAS}?accion=crear`,
      {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id_bodega: idBodegaPadre,
          codigo_subbodega: codigo,
          nombre_subbodega: nombre,
          clasificacion_subbodegas: clasificacion,
          descripcion,
          estado: "Activo"
        }),
      }
    );

    const parsed = await safeJson(res);

    if (!parsed.ok || parsed.data?.error) {
      throw new Error(parsed.data?.error || "Error al crear sub-bodega");
    }


    closeModal(modalCrearSubBodega);
    toastSuccess(parsed.data?.message || "Sub-bodega creada correctamente.");
    setTimeout(() => location.reload(), 650);

  } catch (err) {
    console.error(err);
    toastError(err.message || "No se pudo crear la sub-bodega.");
  }
});

// ============================
// LISTAR SUB-BODEGAS (FRONTEND ONLY)
// ============================
const loadSubBodegas = async (idBodega) => {
  const container = document.getElementById("subBodegasContainer");
  if (!container) return;

  container.innerHTML = `
    <p class="text-sm text-gray-500">Cargando sub-bodegas...</p>
  `;

  try {
    const res = await fetch(`${API_SUBBODEGAS}?accion=listar`);
    const parsed = await safeJson(res);

    if (!parsed.ok || !Array.isArray(parsed.data)) {
      throw new Error("Respuesta inválida");
    }

    // FILTRO POR BODEGA PADRE
    const subBodegas = parsed.data.filter(
      sb => String(sb.id_bodega) === String(idBodega)
    );

    if (subBodegas.length === 0) {
      container.innerHTML = `
        <p class="text-sm text-gray-500">
          Esta bodega no tiene sub-bodegas registradas.
        </p>`;
      return;
    }

container.innerHTML = subBodegas.map(sb => `
  <div class="flex items-center justify-between p-3 rounded-lg border border-border bg-gray-50">
    <div class="min-w-0">
      <p class="font-medium text-gray-900 truncate">
        ${sb.nombre_subbodega}
      </p>
      <p class="text-xs text-gray-500">
        ${sb.codigo_subbodega} · ${sb.clasificacion_subbodegas}
      </p>
    </div>

    <div class="flex items-center gap-2">
      <span class="text-xs px-2 py-1 rounded-full
        ${sb.estado === "Activo"
          ? "bg-emerald-100 text-emerald-700"
          : "bg-red-100 text-red-700"}">
        ${sb.estado}
      </span>

      <!-- Botón menú (igual idea que bodegas) -->
      <button
        type="button"
        class="w-8 h-8 rounded-full inline-flex items-center justify-center subbodega-actions-btn hover:bg-gray-200"
        data-id="${sb.id_subbodega}"
        data-idbodega="${sb.id_bodega}"
        data-codigo="${sb.codigo_subbodega}"
        data-nombre="${sb.nombre_subbodega}"
        data-clasificacion="${sb.clasificacion_subbodegas}"
        data-descripcion="${sb.descripcion ?? ""}"
        data-estado="${sb.estado}"
      >
        <i data-lucide="more-horizontal" class="w-4 h-4"></i>
      </button>
    </div>
  </div>
`).join("");

    safeIcons();

  } catch (err) {
    console.error(err);
    container.innerHTML = `
      <p class="text-sm text-red-600">
        Error al cargar sub-bodegas
      </p>`;
  }
};

// ============================
// CONTADOR DE SUB-BODEGAS (CACHE)
// ============================

const loadSubBodegasCount = () => {
  subBodegasCountByBodega = {};

  allSubBodegas.forEach(sb => {
    const id = String(sb.id_bodega);
    if (!subBodegasCountByBodega[id]) {
      subBodegasCountByBodega[id] = 0;
    }
    subBodegasCountByBodega[id]++;
  });

  paintSubBodegasCount();
};

const paintSubBodegasCount = () => {
  // ===== TABLA =====
  document.querySelectorAll(".bodegas-btn-dots").forEach(btn => {
    const id = btn.dataset.id;
    if (!id) return;

    const count = subBodegasCountByBodega[id] || 0;

    const tr = btn.closest("tr");
    if (!tr) return;

    let badge = tr.querySelector(".subbodegas-count");
    if (!badge) {
      badge = document.createElement("span");
      badge.className =
        "subbodegas-count ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600";
      tr.querySelector(".bodegas-nombre span")?.after(badge);
    }

    badge.textContent = `${count} sub-bodega${count === 1 ? "" : "s"}`;
  });

  // ===== CARDS =====
  document.querySelectorAll(".bodegas-card").forEach(card => {
    const btn = card.querySelector(".bodegas-btn-dots");
    if (!btn) return;

    const id = btn.dataset.id;
    const count = subBodegasCountByBodega[id] || 0;

    let text = card.querySelector(".subbodegas-count-card");
    if (!text) {
      text = document.createElement("span");
      text.className = "subbodegas-count-card text-sm text-muted-foreground";
      card.querySelector(".estado-text")?.parentElement?.prepend(text);
    }

    text.textContent = `${count} sub-bodega${count === 1 ? "" : "s"}`;
  });
};

const loadAllSubBodegas = async () => {
  try {
    const res = await fetch(`${API_SUBBODEGAS}?accion=listar`);
    const parsed = await safeJson(res);

    if (!parsed.ok || !Array.isArray(parsed.data)) {
      throw new Error("Respuesta inválida");
    }

    allSubBodegas = parsed.data;

  } catch (err) {
    console.error("Error cargando sub-bodegas", err);
    allSubBodegas = [];
  }
};

const initSubBodegas = async () => {
  await loadAllSubBodegas();
  loadSubBodegasCount();
};

initSubBodegas();

// ============================
// MENÚ CONTEXTUAL SUB-BODEGAS (TOGGLE BLINDADO)
// ============================
let selectedSubBodega = null;

const subMenu = document.getElementById("context-menu-subbodega");

const isSubMenuOpen = () => subMenu && !subMenu.classList.contains("hidden");

const closeSubMenu = () => {
  if (!subMenu) return;
  subMenu.classList.add("hidden");
  delete subMenu.dataset.openFor; // 👈 clave para el toggle
};

const openSubMenu = (btn) => {
  if (!subMenu || !btn) return;

  // Posición (fixed/absolute depende tu HTML, pero esto funciona con tu cálculo)
  const r = btn.getBoundingClientRect();
  subMenu.style.left = `${r.right + window.scrollX - 220}px`;
  subMenu.style.top = `${r.bottom + window.scrollY + 8}px`;

  subMenu.classList.remove("hidden");
  subMenu.dataset.openFor = btn.dataset.id || ""; // 👈 guardamos para saber si es el mismo botón
  safeIcons();
};

// ABRIR/CERRAR desde el botón ...
document.addEventListener("click", (e) => {
  const btn = e.target.closest(".subbodega-actions-btn");
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  // Si está abierto para el MISMO botón => toggle cerrar
  const openFor = subMenu?.dataset?.openFor || "";
  const thisId = btn.dataset.id || "";

  if (isSubMenuOpen() && openFor === thisId) {
    closeSubMenu();
    return;
  }

  // Siempre cerramos antes (evita estados raros)
  closeSubMenu();

  selectedSubBodega = {
    id: thisId,
    id_bodega: btn.dataset.idbodega,
    codigo: btn.dataset.codigo,
    nombre: btn.dataset.nombre,
    clasificacion: btn.dataset.clasificacion,
    descripcion: btn.dataset.descripcion,
    estado: btn.dataset.estado,
  };

  const label = subMenu?.querySelector("[data-action='toggle'] span");
  if (label) {
    label.textContent = selectedSubBodega.estado === "Activo" ? "Desactivar" : "Activar";
  }

  openSubMenu(btn);
});

// Click en opciones del menú
subMenu?.addEventListener("click", async (e) => {
  const btn = e.target.closest(".ctx-sub-btn");
  if (!btn || !selectedSubBodega) return;

  e.preventDefault();
  e.stopPropagation();

  const action = btn.dataset.action;

  closeSubMenu();

  if (action === "ver") {
    
    document.getElementById("detalleSubNombre").textContent = selectedSubBodega.nombre;
    document.getElementById("detalleSubCodigo").textContent = selectedSubBodega.codigo;
    document.getElementById("detalleSubClasificacion").textContent = selectedSubBodega.clasificacion;
    document.getElementById("detalleSubDescripcion").textContent = selectedSubBodega.descripcion || "-";
    document.getElementById("detalleSubEstado").textContent = selectedSubBodega.estado;

    loadMaterialesSubBodega(selectedSubBodega.id); // 👈 CLAVE

    openModal(document.getElementById("modalDetalleSubBodega"));
    return;
  }

  if (action === "editar") {
    document.getElementById("editSubId").value = selectedSubBodega.id;
    document.getElementById("editSubCodigo").value = selectedSubBodega.codigo;
    document.getElementById("editSubNombre").value = selectedSubBodega.nombre;
    document.getElementById("editSubClasificacion").value = selectedSubBodega.clasificacion;
    document.getElementById("editSubDescripcion").value = selectedSubBodega.descripcion || "";

    openModal(document.getElementById("modalEditarSubBodega"));
    return;
  }

  if (action === "toggle") {
    const next = selectedSubBodega.estado === "Activo" ? "Inactivo" : "Activo";

    const res = await fetch(`${API_SUBBODEGAS}?accion=estado&id=${selectedSubBodega.id}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ estado: next }),
    });

    const parsed = await safeJson(res);

    if (!parsed.ok || parsed.data?.error) {
      toastError(parsed.data?.error || "No se pudo cambiar el estado");
      return;
    }

    toastSuccess(`Sub-bodega ${next === "Activo" ? "activada" : "desactivada"}`);
    setTimeout(() => location.reload(), 600);
  }
});

// Cerrar al hacer click afuera
document.addEventListener("click", (e) => {
  if (!subMenu) return;
  if (e.target.closest(".subbodega-actions-btn")) return;
  if (!subMenu.contains(e.target)) closeSubMenu();
});

// Cerrar con ESC
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeSubMenu();
});


  // ============================
  // MENÚ CONTEXTUAL
  // ============================
  const contextMenu = $("context-menu");
  let selectedData = null;

  const closeContextMenu = () => contextMenu?.classList.add("hidden");

  const openContextMenu = (btnDots) => {
    if (!contextMenu || !btnDots) return;

    selectedData = {
      id: btnDots.dataset.id || "",
      codigo: btnDots.dataset.codigo || "",
      nombre: btnDots.dataset.nombre || "",
      clasificacion: btnDots.dataset.clasificacion || "",
      ubicacion: btnDots.dataset.ubicacion || "",
      estado: btnDots.dataset.estado || "",
    };

    const labelToggle = contextMenu.querySelector("[data-action='deshabilitar'] span");
    if (labelToggle) {
      labelToggle.textContent = selectedData.estado === "Activo" ? "Desactivar" : "Activar";
    }

    const r = btnDots.getBoundingClientRect();
    const menuWidth = 224;

    contextMenu.style.left = `${r.right + window.scrollX - menuWidth}px`;
    contextMenu.style.top = `${r.bottom + window.scrollY + 8}px`;

    contextMenu.classList.remove("hidden");
    safeIcons();
  };

  document.addEventListener("click", (e) => {
    const btnDots = e.target.closest(".bodegas-btn-dots");
    if (btnDots) {
      e.preventDefault();
      e.stopPropagation();
      openContextMenu(btnDots);
      return;
    }
    if (contextMenu && !contextMenu.contains(e.target)) closeContextMenu();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeContextMenu();
  });

  // ============================
  // MODAL DETALLE
  // ============================
  const modalDetalle = $("modalDetalle");
  const cerrarDetalle = $("cerrarDetalle");

  cerrarDetalle?.addEventListener("click", () => closeModal(modalDetalle));
  modalDetalle?.addEventListener("click", (e) => {
    if (e.target === modalDetalle) closeModal(modalDetalle);
  });

  const fillDetalle = (data) => {
    const setText = (id, value) => {
      const el = $(id);
      if (!el) return;
      el.textContent = value ?? "";
    };

    setText("detalleNombre", data.nombre);
    setText("detalleId", data.codigo);
    setText("detalleClasificacion", data.clasificacion);
    setText("detalleUbicacion", data.ubicacion);

    const estadoEl = $("detalleEstado");
    if (estadoEl) {
      estadoEl.textContent = data.estado || "";
      estadoEl.classList.remove("badge-estado-activo", "badge-estado-inactivo");
      estadoEl.classList.add(data.estado === "Activo" ? "badge-estado-activo" : "badge-estado-inactivo");
    }
  };

  // ============================
  // MODAL EDITAR
  // ============================
  const modalEditar = $("modalEditar");
  const backdropEditar = $("backdropEditar");
  const cerrarEditar = $("cerrarEditar");
  const cancelarEditar = $("cancelarEditar");
  const guardarEditar = $("guardarEditar");

  cerrarEditar?.addEventListener("click", () => closeModal(modalEditar));
  cancelarEditar?.addEventListener("click", () => closeModal(modalEditar));
  backdropEditar?.addEventListener("click", () => closeModal(modalEditar));
  modalEditar?.addEventListener("click", (e) => {
    if (e.target === modalEditar) closeModal(modalEditar);
  });

  const fillEditar = (data) => {
    if ($("editIdBodega")) $("editIdBodega").value = data.id || "";
    if ($("editCodigoBodega")) $("editCodigoBodega").value = data.codigo || "";
    if ($("editNombre")) $("editNombre").value = data.nombre || "";
    if ($("editUbicacion")) $("editUbicacion").value = data.ubicacion || "";
    if ($("editClasificacion")) $("editClasificacion").value = data.clasificacion || "";
  };

  // ============================
// MODAL DETALLE SUB-BODEGA
// ============================
const modalDetalleSub = $("modalDetalleSubBodega");
const cerrarDetalleSub = $("cerrarDetalleSub");

cerrarDetalleSub?.addEventListener("click", () => closeModal(modalDetalleSub));
modalDetalleSub?.addEventListener("click", (e) => {
  if (e.target === modalDetalleSub) closeModal(modalDetalleSub);
});

const fillDetalleSub = (data) => {
  const setText = (id, value) => {
    const el = $(id);
    if (!el) return;
    el.textContent = value ?? "";
  };

  // OJO: estos IDs deben existir en tu HTML del modal sub-bodega
  setText("detalleSubNombre", data.nombre_subbodega ?? data.nombre);
  setText("detalleSubCodigo", data.codigo_subbodega ?? data.codigo);
  setText("detalleSubClasificacion", data.clasificacion_subbodegas ?? data.clasificacion);
  setText("detalleSubDescripcion", data.descripcion ?? "");

  const estadoEl = $("detalleSubEstado");
  if (estadoEl) {
    const estado = data.estado || "";
    estadoEl.textContent = estado;

    // Mismo patrón que bodega
    estadoEl.classList.remove("badge-estado-activo", "badge-estado-inactivo");
    estadoEl.classList.add(estado === "Activo" ? "badge-estado-activo" : "badge-estado-inactivo");

    // 🔒 Importante para que NO se estire como barra (Tailwind)
    estadoEl.classList.add("inline-flex", "w-fit");
  }

    const totalEl = $("detalleSubTotalMateriales");
  if (totalEl) {
    totalEl.textContent = String(data.total_materiales ?? 0);
  }
};


  // ============================
  // BACKEND HELPERS
  // ============================
  const tryPostActions = async (actions, payload) => {
    let last = null;

    for (const accion of actions) {
      try {
        const res = await fetch(`${API_URL}?accion=${encodeURIComponent(accion)}`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });

        const parsed = await safeJson(res);
        last = { accion, parsed };

        // si backend devuelve error aunque sea 200 => NO es ok
        if (parsed?.data?.error) continue;

        const okByBody =
          parsed?.data?.success === true ||
          parsed?.data?.ok === true ||
          parsed?.data?.status === "ok";

        if (parsed.ok && (okByBody || parsed.data !== null)) {
          return { ok: true, accion, parsed };
        }
      } catch (e) {
        last = { accion, error: e };
      }
    }

    return { ok: false, last };
  };

  const toggleEstadoBodega = async ({ id, codigo, estadoActual }) => {
    const next = estadoActual === "Activo" ? "Inactivo" : "Activo";

    const payload = {
      id_bodega: id,
      codigo_bodega: codigo,
      estado: next,
      nuevo_estado: next,
    };

    const actions = ["cambiar_estado", "toggleEstado", "actualizarEstado", "deshabilitar", "activar"];

    const result = await tryPostActions(actions, payload);

    if (!result.ok) {
      console.error("[toggleEstadoBodega] fallo", result);
      const msg =
        result?.last?.parsed?.data?.error ||
        "No se pudo cambiar el estado (revisa el nombre de la acción en tu controller).";
      toastError(msg);
      return { ok: false, next };
    }

    const msgOk =
      result?.parsed?.data?.mensaje ||
      (next === "Inactivo" ? "Bodega desactivada correctamente." : "Bodega activada correctamente.");

    toastSuccess(msgOk);
    return { ok: true, next };
  };

  // ============================
  // ✅ UI UPDATE (SIN RELOAD)
  // ============================
  const updateEstadoBadgesAndDatasets = (codigo, nextEstado) => {
    // 1) Actualiza todos los dots (tabla + cards) que tengan ese codigo
    document.querySelectorAll(`.bodegas-btn-dots[data-codigo="${CSS.escape(codigo)}"]`).forEach((btn) => {
      btn.dataset.estado = nextEstado;
    });

    // 2) Tabla: badge estado en la fila
    document.querySelectorAll("#tbodyBodegas tr").forEach((tr) => {
      const dots = tr.querySelector(".bodegas-btn-dots");
      if (!dots) return;
      const cod = String(dots.dataset.codigo || "");
      if (cod !== String(codigo)) return;

      const tdEstado = tr.querySelector(".bodegas-estado span");
      if (tdEstado) {
        tdEstado.textContent = nextEstado;
        tdEstado.classList.remove("badge-estado-activo", "badge-estado-inactivo");
        tdEstado.classList.add(nextEstado === "Activo" ? "badge-estado-activo" : "badge-estado-inactivo");
      }
    });

    // 3) Cards: switch + dataset estado
    document.querySelectorAll(".estado-switch").forEach((sw) => {
      const cod = String(sw.dataset.codigo || "");
      if (cod !== String(codigo)) return;

      sw.dataset.estado = nextEstado;
      sw.checked = nextEstado === "Activo";
    });

    // 4) Si modal detalle está abierto con esa bodega, actualiza badge ahí también
    if ($("detalleId") && $("detalleId").textContent && String($("detalleId").textContent) === String(codigo)) {
      const estadoEl = $("detalleEstado");
      if (estadoEl) {
        estadoEl.textContent = nextEstado;
        estadoEl.classList.remove("badge-estado-activo", "badge-estado-inactivo");
        estadoEl.classList.add(nextEstado === "Activo" ? "badge-estado-activo" : "badge-estado-inactivo");
      }
    }

    // 5) Si selectedData es esa bodega, también se actualiza para que el menú diga Activar/Desactivar bien
    if (selectedData && String(selectedData.codigo) === String(codigo)) {
      selectedData.estado = nextEstado;
    }
  };

  // ============================
  // CLICK acciones menú contextual
  // ============================
  contextMenu?.addEventListener("click", async (e) => {
    const btn = e.target.closest(".bodegas-ctx-btn");
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    const action = btn.dataset.action || "";
    closeContextMenu();

    if (!selectedData) return;

    if (action === "ver") {
    fillDetalle(selectedData);

    loadSubBodegas(selectedData.id);
    loadMaterialesBodega(selectedData.id); 

    openModal(modalDetalle);
    return;
  }


    if (action === "editar") {
      fillEditar(selectedData);
      openModal(modalEditar);
      return;
    }

    if (action === "deshabilitar") {
      const { ok, next } = await toggleEstadoBodega({
        id: selectedData.id,
        codigo: selectedData.codigo,
        estadoActual: selectedData.estado,
      });

      if (ok) {
        updateEstadoBadgesAndDatasets(selectedData.codigo, next);
        // Reaplicar filtros por si el usuario está filtrando por Activo/Inactivo
        applyFilters();
      }
      return;
    }
  });

  // ============================
  // GUARDAR EDITAR
  // ============================
  guardarEditar?.addEventListener("click", async () => {
    const id = ($("editIdBodega")?.value || "").trim();
    const codigo = ($("editCodigoBodega")?.value || "").trim();
    const nombre = ($("editNombre")?.value || "").trim();
    const ubicacion = ($("editUbicacion")?.value || "").trim();
    const clasificacion = $("editClasificacion") ? $("editClasificacion").value : "";

    if (!id || !codigo || !nombre || !ubicacion || !clasificacion) {
      toastError("Completa todos los campos obligatorios.");
      return;
    }

    try {
      const payload = {
        id_bodega: id,
        codigo_bodega: codigo,
        nombre,
        ubicacion,
        clasificacion_bodega: clasificacion,
      };

      /* EDITAR */
      if (id) {
        body.estado = inputEstado.value;

        await fetch(`${API_BODEGA}?accion=actualizar&id=${id}`, {
          method: "PUT",
          body: JSON.stringify(body)
        });

      } else {
        /* CREAR */
        await fetch(`${API_BODEGA}?accion=crear`, {
          method: "POST",
          body: JSON.stringify(body)
        });
      }
    }

    /* -------------------------
       SUBBODEGA
    ------------------------- */
    if (tipo === "subbodega") {
      const body = {
        id_bodega: idPadre.value,
        codigo_subbodega: inputCodigo.value,
        nombre_subbodega: inputNombre.value,
        clasificacion_subbodegas: inputClasificacion.value,
        descripcion: inputDescripcion.value
      };

      /* EDITAR */
      if (id) {
        body.estado = inputEstado.value;

        await fetch(`${API_SUB}?accion=actualizar&id=${id}`, {
          method: "PUT",
          body: JSON.stringify(body)
        });
      } else {
        /* CREAR */
        await fetch(`${API_SUB}?accion=crear`, {
          method: "POST",
          body: JSON.stringify(body)
        });
      }
    }

    cerrarModal();
    cargarDatos();

  } catch (err) {
    console.error("Error al guardar:", err);
    alert("Ocurrió un error al guardar la bodega.");
  }
};

/* =========================================================
   CAMBIAR ESTADO
========================================================= */
async function cambiarEstado(tipo, id, estadoActual) {
  const nuevo = estadoActual === "Activo" ? "Inactivo" : "Activo";

  try {
    if (tipo === "bodega") {
      await fetch(`${API_BODEGA}?accion=cambiar_estado`, {
        method: "PUT",
        body: JSON.stringify({ id_bodega: id, estado: nuevo })
      });
    }

    if (tipo === "subbodega") {
      await fetch(`${API_SUB}?accion=estado&id=${id}`, {
        method: "POST",
        body: JSON.stringify({ estado: nuevo })
      });
    }

    cargarDatos();
  } catch (err) {
    console.error("Error al cambiar estado:", err);
    alert("No se pudo cambiar el estado.");
  }
}

/* =========================================================
   EVENTOS
========================================================= */
inputBuscar.oninput = render;
filtroTipo.onchange = render;
filtroEstado.onchange = render;

/* Cambio vistas */
document.getElementById("btnVistaTablaBodega").onclick = () => {
  document.getElementById("vistaTablaBodegas").classList.remove("hidden");
  document.getElementById("vistaTarjetasBodegas").classList.add("hidden");
};

document.getElementById("btnVistaTarjetasBodega").onclick = () => {
  document.getElementById("vistaTablaBodegas").classList.add("hidden");
  document.getElementById("vistaTarjetasBodegas").classList.remove("hidden");
};

    safeIcons();
  };

  inputBuscar?.addEventListener("input", applyFilters);
  selectEstado?.addEventListener("change", applyFilters);

  applyFilters();

// ============================
// CIERRE ROBUSTO: MODAL EDITAR SUB-BODEGA
// ============================
const modalEditarSub = document.getElementById("modalEditarSubBodega");

document.addEventListener("click", (e) => {
  if (!modalEditarSub) return;

  const clickEnX = e.target.closest("#cerrarEditarSub");
  const clickEnCancelar = e.target.closest("#cancelarEditarSub");
  const clickEnBackdrop = e.target.id === "backdropEditarSub";
  const clickEnOverlay = e.target === modalEditarSub; // click en el overlay

  if (clickEnX || clickEnCancelar || clickEnBackdrop || clickEnOverlay) {
    e.preventDefault();
    closeModal(modalEditarSub);
  }
});

// ============================
// GUARDAR EDITAR SUB-BODEGA (FIX)
// ============================
const btnGuardarEditarSub = document.getElementById("guardarEditarSubBodega");

btnGuardarEditarSub?.addEventListener("click", async () => {
  const id = (document.getElementById("editSubId")?.value || "").trim();
  const codigo = (document.getElementById("editSubCodigo")?.value || "").trim();
  const nombre = (document.getElementById("editSubNombre")?.value || "").trim();
  const clasificacion = (document.getElementById("editSubClasificacion")?.value || "").trim();
  const descripcion = (document.getElementById("editSubDescripcion")?.value || "").trim();

  if (!id || !codigo || !nombre || !clasificacion) {
    toastError("Completa todos los campos obligatorios.");
    return;
  }

  try {
    const res = await fetch(
      `${API_SUBBODEGAS}?accion=actualizar&id=${encodeURIComponent(id)}`,
      {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          codigo_subbodega: codigo,
          nombre_subbodega: nombre,
          clasificacion_subbodegas: clasificacion,
          descripcion,
        }),
      }
    );

    const parsed = await safeJson(res);

    if (!parsed.ok || parsed.data?.error) {
      console.error("[ACTUALIZAR SUB RAW]", parsed.raw);
      throw new Error(parsed.data?.error || `HTTP ${res.status}`);
    }

    closeModal(document.getElementById("modalEditarSubBodega"));
    toastSuccess(parsed.data?.message || "Sub-bodega actualizada correctamente.");
    setTimeout(() => location.reload(), 650);
      } catch (err) {
      console.error(err);
      toastError(err.message || "No se pudo actualizar la sub-bodega.");
    }
  });

  const renderMateriales = (materiales, {
    containerId,
    emptyId,
    totalId
  }) => {
    const cont = document.getElementById(containerId);
    const empty = document.getElementById(emptyId);
    const total = document.getElementById(totalId);

    if (!cont) return;

    cont.innerHTML = "";

    if (!Array.isArray(materiales) || materiales.length === 0) {
      empty?.classList.remove("hidden");
      if (total) total.textContent = "0";
      return;
    }

    empty?.classList.add("hidden");
    if (total) total.textContent = materiales.length;

    cont.innerHTML = materiales.map(m => `
      <div class="flex items-center justify-between p-3 rounded-lg border bg-white">
        <div class="min-w-0">
          <p class="font-medium text-gray-900 truncate">${m.nombre_material || m.nombre || "Material sin nombre"}</p>
          <p class="text-xs text-gray-500">
            ${m.unidad_medida || "N/A"}${m.clasificacion ? " · " + m.clasificacion : ""}
            ${m.codigo_inventario ? " · " + m.codigo_inventario : ""}
          </p>
        </div>

        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap ml-2">
          ${m.cantidad_total || m.stock_actual || "0"}
        </span>
      </div>
    `).join("");
  };

});

// ============================
// FUNCIONES GLOBALES (fuera de DOMContentLoaded)
// ============================

const loadMaterialesBodega = async (idBodega) => {
  try {
    console.log("� [loadMaterialesBodega] Buscando materiales para bodega ID:", idBodega);
    
    const API_URL = new URL("src/controllers/bodega_controller.php", document.baseURI).toString();
    const url = `${API_URL}?accion=inventario_bodega&id_bodega=${encodeURIComponent(idBodega)}`;
    console.log("🔗 [loadMaterialesBodega] URL:", url);
    
    const res = await fetch(url);
    const json = await res.json();
    
    console.log("📥 [loadMaterialesBodega] Respuesta:", json);

    const cont = document.getElementById("detalleBodegaMateriales");
    const empty = document.getElementById("detalleBodegaMaterialesVacio");
    const total = document.getElementById("totalMateriales");

    console.log("🎯 [loadMaterialesBodega] Contenedores encontrados:", {
      cont: !!cont,
      empty: !!empty,
      total: !!total
    });

    if (!cont) {
      console.error("❌ [loadMaterialesBodega] Contenedor no encontrado");
      return;
    }

    if (!json.success || !Array.isArray(json.data)) {
      console.error("❌ [loadMaterialesBodega] Respuesta inválida:", json);
      cont.innerHTML = "";
      empty?.classList.remove("hidden");
      if (total) total.textContent = "0";
      return;
    }

    console.log("✅ [loadMaterialesBodega] Materiales encontrados:", json.data.length);

    if (json.data.length === 0) {
      cont.innerHTML = "";
      empty?.classList.remove("hidden");
      if (total) total.textContent = "0";
      return;
    }

    empty?.classList.add("hidden");
    if (total) total.textContent = json.data.length;

    cont.innerHTML = json.data.map(m => `
      <div class="flex items-center justify-between p-3 rounded-lg border bg-white">
        <div class="min-w-0">
          <p class="font-medium text-gray-900 truncate">${m.nombre_material || "Material sin nombre"}</p>
          <p class="text-xs text-gray-500">
            ${m.unidad_medida || "N/A"}
          </p>
        </div>
        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap ml-2">
          ${m.cantidad_total || "0"}
        </span>
      </div>
    `).join("");
    
    console.log("✅ [loadMaterialesBodega] Renderizado completo");

  } catch (err) {
    console.error("❌ [loadMaterialesBodega] Error:", err);
    const cont = document.getElementById("detalleBodegaMateriales");
    const empty = document.getElementById("detalleBodegaMaterialesVacio");
    if (cont) cont.innerHTML = "";
    if (empty) empty?.classList.remove("hidden");
  }
};

const loadMaterialesSubBodega = async (idSubBodega) => {
  try {
    console.log("🔍 [loadMaterialesSubBodega] Buscando materiales para subbodega ID:", idSubBodega);
    
    const API_URL = new URL("src/controllers/bodega_controller.php", document.baseURI).toString();
    const url = `${API_URL}?accion=inventario_subbodega&id_subbodega=${encodeURIComponent(idSubBodega)}`;
    console.log("🔗 [loadMaterialesSubBodega] URL:", url);
    
    const res = await fetch(url);
    const json = await res.json();
    
    console.log("📥 [loadMaterialesSubBodega] Respuesta:", json);

    const cont = document.getElementById("detalleSubBodegaMateriales");
    const empty = document.getElementById("detalleSubBodegaMaterialesVacio");
    const total = document.getElementById("totalSubMateriales");

    console.log("🎯 [loadMaterialesSubBodega] Contenedores encontrados:", {
      cont: !!cont,
      empty: !!empty,
      total: !!total
    });

    if (!cont) {
      console.error("❌ [loadMaterialesSubBodega] Contenedor no encontrado");
      return;
    }

    if (!json.success || !Array.isArray(json.data)) {
      console.error("❌ [loadMaterialesSubBodega] Respuesta inválida:", json);
      cont.innerHTML = "";
      empty?.classList.remove("hidden");
      if (total) total.textContent = "0";
      return;
    }

    console.log("✅ [loadMaterialesSubBodega] Materiales encontrados:", json.data.length);

    if (json.data.length === 0) {
      cont.innerHTML = "";
      empty?.classList.remove("hidden");
      if (total) total.textContent = "0";
      return;
    }

    empty?.classList.add("hidden");
    if (total) total.textContent = json.data.length;

    cont.innerHTML = json.data.map(m => `
      <div class="flex items-center justify-between p-3 rounded-lg border bg-white">
        <div class="min-w-0">
          <p class="font-medium text-gray-900 truncate">${m.nombre_material || "Material sin nombre"}</p>
          <p class="text-xs text-gray-500">
            ${m.unidad_medida || "N/A"}
          </p>
        </div>
        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap ml-2">
          ${m.cantidad_total || "0"}
        </span>
      </div>
    `).join("");
    
    console.log("✅ [loadMaterialesSubBodega] Renderizado completo");

  } catch (err) {
    console.error("❌ [loadMaterialesSubBodega] Error:", err);
    const cont = document.getElementById("detalleSubBodegaMateriales");
    const empty = document.getElementById("detalleSubBodegaMaterialesVacio");
    if (cont) cont.innerHTML = "";
    if (empty) empty?.classList.remove("hidden");
  }
};

// Función para ver materiales de una bodega
async function verMaterialesBodega(idBodega) {
  const modal = document.getElementById("modalMaterialesBodega");
  const lista = document.getElementById("listaMaterialesBodega");
  const nombreBodega = document.getElementById("nombreBodegaMateriales");
  
  if (!modal || !lista) return;
  
  // Mostrar modal
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  
  // Mostrar loading
  lista.innerHTML = `
    <div class="text-center py-8 text-gray-500">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-gray-900 mx-auto mb-2"></div>
      <p>Cargando materiales...</p>
    </div>
  `;
  
  try {
    const API_URL = new URL("src/controllers/bodega_controller.php", document.baseURI).toString();
    const res = await fetch(`${API_URL}?accion=inventario_bodega&id_bodega=${idBodega}`);
    const json = await res.json();
    
    if (json.success && Array.isArray(json.data)) {
      const materiales = json.data;
      
      if (materiales.length === 0) {
        lista.innerHTML = `
          <div class="text-center py-8">
            <i data-lucide="package-open" class="h-16 w-16 mx-auto mb-3 text-gray-300"></i>
            <p class="text-gray-500 font-medium">No hay materiales en esta bodega</p>
            <p class="text-xs text-gray-400 mt-1">Los materiales aparecerán aquí cuando se registren entradas</p>
          </div>
        `;
      } else {
        lista.innerHTML = materiales.map(m => `
          <div class="flex justify-between items-center border rounded-lg p-4 bg-green-50 border-green-200 hover:shadow-sm transition">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                <i data-lucide="package" class="w-5 h-5 text-green-600"></i>
              </div>
              <div>
                <p class="font-semibold text-green-900">${escapeHtml(m.nombre_material)}</p>
                <p class="text-xs text-green-700">Unidad: ${escapeHtml(m.unidad_medida)}</p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-2xl font-bold text-green-600">${m.cantidad_total}</p>
              <p class="text-xs text-green-700">${escapeHtml(m.unidad_medida)}</p>
            </div>
          </div>
        `).join('');
      }
      
      // Reiniciar iconos de Lucide
      if (window.lucide) window.lucide.createIcons();
    } else {
      throw new Error(json.message || "Error al cargar materiales");
    }
  } catch (err) {
    console.error("Error cargando materiales:", err);
    lista.innerHTML = `
      <div class="text-center py-8 text-red-500">
        <i data-lucide="alert-circle" class="h-12 w-12 mx-auto mb-2"></i>
        <p class="font-medium">Error al cargar materiales</p>
        <p class="text-xs mt-1">${err.message}</p>
      </div>
    `;
    if (window.lucide) window.lucide.createIcons();
  }
}

function cerrarModalMateriales() {
  const modal = document.getElementById("modalMaterialesBodega");
  if (modal) {
    modal.classList.add("hidden");
    modal.classList.remove("flex");
  }
}

function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Exponer funciones globalmente
window.verMaterialesBodega = verMaterialesBodega;
window.cerrarModalMateriales = cerrarModalMateriales;
