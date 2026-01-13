// ============================================================
//  MÓDULO SOLICITUDES – JS FUNCIONAL (BACKEND/BD INTACTOS)
//  + NUEVO: marcar "Aprobada" como "Entregada" (accion=entregar)
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
};

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

  selectMaterial: document.getElementById("material-select"),
  inputCantidad: document.getElementById("material-cantidad"),
  btnAgregarMaterial: document.getElementById("btn-agregar-material"),
  listaMateriales: document.getElementById("lista-materiales"),

  resumenPendientes: document.getElementById("resumen-pendientes"),
  resumenAprobadas: document.getElementById("resumen-aprobadas"),
  resumenEntregadas: document.getElementById("resumen-entregadas"),
  resumenRechazadas: document.getElementById("resumen-rechazadas"),
};

const utilidades = {
  normalizarEstado(estadoBD) {
    if (!estadoBD) return "pendiente";
    const s = String(estadoBD).trim().toLowerCase();
    if (s === "aprobado") return "aprobada";
    if (s === "rechazado") return "rechazada";
    if (s === "entregado") return "entregada";
    return s;
  },
  formatearFecha(fechaString) {
    if (!fechaString) return "";
    try {
      const normalized = String(fechaString).includes(" ")
        ? String(fechaString).replace(" ", "T")
        : fechaString;
      const d = new Date(normalized);
      if (isNaN(d.getTime())) return String(fechaString);
      return d.toLocaleDateString("es-ES", { day: "2-digit", month: "2-digit", year: "numeric" });
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
    };
  },
  mostrarError(msg) {
    console.error("❌", msg);
    alert(`Error: ${msg}`);
  },
  mostrarExito(msg) {
    console.log("✅", msg);
    alert(msg);
  },
};

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
        estado, // 'aprobada' | 'rechazada'
        id_usuario_aprobador: 1,
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

  // ✅ NUEVO: marcar entregada (accion=entregar)
  async entregarSolicitud(idSolicitud) {
    const res = await fetch(`${API}?accion=entregar`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id_solicitud: parseInt(idSolicitud, 10),
        id_usuario: 1, // quien marca entrega (según tu controller)
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

  async cargarSelectores() {
    try {
      const resProg = await fetch(`${API}?accion=programas`);
      if (resProg.ok) {
        const programas = await resProg.json();
        selectores.selectPrograma.innerHTML = '<option value="">Seleccionar programa</option>';
        if (Array.isArray(programas)) {
          programas.forEach((p) => {
            const opt = document.createElement("option");
            opt.value = p.id_programa;
            opt.textContent = `${p.codigo_programa} - ${p.nombre_programa}`;
            selectores.selectPrograma.appendChild(opt);
          });
        }
      }

      selectores.selectPrograma.addEventListener("change", async function () {
        const programaId = this.value;

        selectores.selectRae.innerHTML = '<option value="">Seleccionar RAE</option>';
        selectores.selectFichas.innerHTML = '<option value="">Seleccionar ficha</option>';

        if (!programaId) return;

        const [resRaes, resFichas] = await Promise.all([
          fetch(`${API}?accion=raes&programa=${programaId}`),
          fetch(`${API}?accion=fichas&programa=${programaId}`),
        ]);

        if (resRaes.ok) {
          const raes = await resRaes.json();
          selectores.selectRae.innerHTML = '<option value="">Seleccionar RAE</option>';
          if (Array.isArray(raes) && raes.length) {
            raes.forEach((r) => {
              const opt = document.createElement("option");
              opt.value = r.id_rae;
              opt.textContent = `${r.codigo_rae} - ${r.descripcion_rae}`;
              selectores.selectRae.appendChild(opt);
            });
          } else {
            selectores.selectRae.innerHTML = '<option value="">No hay RAEs disponibles</option>';
          }
        }

        if (resFichas.ok) {
          const fichas = await resFichas.json();
          selectores.selectFichas.innerHTML = '<option value="">Seleccionar ficha</option>';
          if (Array.isArray(fichas) && fichas.length) {
            fichas.forEach((f) => {
              const opt = document.createElement("option");
              opt.value = f.id_ficha;
              opt.textContent = `${f.numero_ficha} - ${f.jornada}`;
              selectores.selectFichas.appendChild(opt);
            });
          } else {
            selectores.selectFichas.innerHTML = '<option value="">No hay fichas disponibles</option>';
          }
        }
      });

      const resMat = await fetch(`${API}?accion=materiales`);
      if (resMat.ok) {
        const mats = await resMat.json();
        selectores.selectMaterial.innerHTML = '<option value="">Seleccionar material</option>';
        if (Array.isArray(mats) && mats.length) {
          mats.forEach((m) => {
            const opt = document.createElement("option");
            opt.value = m.id_material;
            opt.textContent = `${m.nombre} (${m.codigo_inventario || "Sin código"})`;
            opt.dataset.stock = m.stock_actual || 0;
            opt.dataset.unidad = m.unidad_medida || "UND";
            opt.dataset.nombre = m.nombre || "";
            selectores.selectMaterial.appendChild(opt);
          });
        } else {
          selectores.selectMaterial.innerHTML = '<option value="">No hay materiales disponibles</option>';
        }
      } else {
        selectores.selectMaterial.innerHTML = '<option value="">Error cargando materiales</option>';
      }
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
    selectores.resumenPendientes.textContent = c.pendiente;
    selectores.resumenAprobadas.textContent = c.aprobada;
    selectores.resumenEntregadas.textContent = c.entregada;
    selectores.resumenRechazadas.textContent = c.rechazada;
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
        <div class="col-span-full py-12 text-center">
          <i data-lucide="file-text" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
          <h3 class="text-lg font-medium text-gray-700 mb-2">No hay solicitudes registradas</h3>
          <p class="text-gray-500">Cree una nueva solicitud para comenzar</p>
        </div>`;
      lucide.createIcons();
      return;
    }

    const filtradas =
      estadoApp.filtroActivo === "todas"
        ? estadoApp.solicitudes
        : estadoApp.solicitudes.filter((s) => (s.estado || "pendiente") === estadoApp.filtroActivo);

    if (!filtradas.length) {
      cont.innerHTML = `
        <div class="col-span-full py-12 text-center">
          <i data-lucide="filter" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
          <h3 class="text-lg font-medium text-gray-700 mb-2">No hay solicitudes ${CONFIG.LABELS[estadoApp.filtroActivo]}s</h3>
          <p class="text-gray-500">Intente con otro filtro</p>
        </div>`;
      lucide.createIcons();
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

      const mostrarAccionesPendiente = st === "pendiente";
      const mostrarAccionEntregar = st === "aprobada";

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

        <div class="sol-card-body">
          <div class="sol-card-row">
            <i data-lucide="hash" class="sol-icon-muted"></i>
            <span>Ficha: ${s.ficha}</span>
          </div>

          ${s.programa ? `
          <div class="sol-card-row">
            <i data-lucide="book-open" class="sol-icon-muted"></i>
            <span>Programa: ${s.programa}</span>
          </div>` : ""}

          ${s.rae ? `
          <div class="sol-card-row">
            <i data-lucide="target" class="sol-icon-muted"></i>
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
        </div>

        ${mostrarAccionesPendiente ? `
        <div class="sol-card-footer mt-4 pt-4 border-t border-gray-200">
          <div class="flex gap-2">
            <button class="sol-btn-aceptar flex-1 py-2 px-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center gap-2"
                    data-id="${s.id}">
              <i data-lucide="check-circle" class="w-4 h-4"></i>
              Aceptar
            </button>
            <button class="sol-btn-rechazar flex-1 py-2 px-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2"
                    data-id="${s.id}">
              <i data-lucide="x-circle" class="w-4 h-4"></i>
              Rechazar
            </button>
          </div>
        </div>` : ""}

        ${mostrarAccionEntregar ? `
        <div class="sol-card-footer mt-4 pt-4 border-t border-gray-200">
          <button class="sol-btn-entregar w-full py-2 px-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2"
                  data-id="${s.id}">
            <i data-lucide="package-check" class="w-4 h-4"></i>
            Marcar como entregada
          </button>
        </div>` : ""}
      `;

      cont.appendChild(card);
    });

    lucide.createIcons();
    setTimeout(agregarEventosBotonesAccion, 50);
    this.renderizarPaginacion(filtradas.length);
  },

  renderizarPaginacion(totalItems) {
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
    lucide.createIcons();
  },

  renderizarMateriales() {
    if (!estadoApp.materialesSeleccionados.length) {
      selectores.listaMateriales.innerHTML = `
        <div class="text-center text-muted-foreground py-8">
          <i data-lucide="package" class="w-8 h-8 mx-auto mb-2"></i>
          <p>No hay materiales agregados</p>
        </div>`;
      lucide.createIcons();
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
    lucide.createIcons();
  },
};

const materiales = {
  agregarMaterial() {
    const id = selectores.selectMaterial.value;
    const cantidad = parseInt(selectores.inputCantidad.value, 10);

    if (!id) return utilidades.mostrarError("Seleccione un material");
    if (!cantidad || cantidad < 1) return utilidades.mostrarError("Cantidad inválida");

    const opt = selectores.selectMaterial.selectedOptions[0];
    const stock = parseInt(opt.dataset.stock, 10) || 0;
    const unidad = opt.dataset.unidad || "UND";
    const nombre = opt.dataset.nombre || opt.textContent;

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
    if (confirm("¿Eliminar este material?")) {
      estadoApp.materialesSeleccionados.splice(index, 1);
      render.renderizarMateriales();
    }
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
    window.scrollTo({ top: selectores.contenedorCards.offsetTop - 100, behavior: "smooth" });
  },
};

// ============================================================
//  BOTONES: Aceptar / Rechazar / Entregar
// ============================================================
function agregarEventosBotonesAccion() {
  // Aceptar
  document.querySelectorAll(".sol-btn-aceptar").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const idSolicitud = btn.dataset.id;

      if (confirm('¿Aceptar esta solicitud?\n\nSe cambiará a "Aprobada"')) {
        await cambiarEstadoSolicitud(idSolicitud, "aprobada");
      }
    });
  });

  // Rechazar
  document.querySelectorAll(".sol-btn-rechazar").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const idSolicitud = btn.dataset.id;

      const motivo = prompt("Motivo del rechazo (requerido):");
      if (motivo === null) return;
      if (!motivo.trim()) return alert("Debe ingresar un motivo.");

      if (confirm('¿Rechazar esta solicitud?\n\nSe cambiará a "Rechazada"')) {
        await cambiarEstadoSolicitud(idSolicitud, "rechazada", motivo.trim());
      }
    });
  });

  // ✅ NUEVO: Entregar (solo aprobadas muestran el botón)
  document.querySelectorAll(".sol-btn-entregar").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const idSolicitud = btn.dataset.id;

      if (confirm('¿Marcar esta solicitud como "Entregada"?')) {
        await marcarEntregada(idSolicitud);
      }
    });
  });
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
    lucide.createIcons();

    const resp = await api.responderSolicitud(idSolicitud, nuevoEstado, motivo);

    if (resp?.success) {
      utilidades.mostrarExito(resp.message || "Solicitud actualizada");
      await app.cargarSolicitudes();
      return;
    }

    throw new Error(resp?.error || resp?.message || "No se pudo actualizar la solicitud.");
  } catch (e) {
    utilidades.mostrarError(e.message);
    await app.cargarSolicitudes();
  }
}

// ✅ NUEVO: marcar como entregada usando accion=entregar
async function marcarEntregada(idSolicitud) {
  const card = document.querySelector(`.sol-card[data-id="${idSolicitud}"]`);
  const btnE = card ? card.querySelector(".sol-btn-entregar") : null;

  try {
    if (btnE) {
      btnE.disabled = true;
      btnE.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Procesando...';
      lucide.createIcons();
    }

    const resp = await api.entregarSolicitud(idSolicitud);

    if (resp?.success) {
      utilidades.mostrarExito(resp.message || "Solicitud marcada como entregada");
      await app.cargarSolicitudes();
      return;
    }

    throw new Error(resp?.error || resp?.message || "No se pudo marcar como entregada.");
  } catch (e) {
    utilidades.mostrarError(e.message);
    if (btnE) {
      btnE.disabled = false;
      btnE.innerHTML = '<i data-lucide="package-check" class="w-4 h-4"></i> Marcar como entregada';
      lucide.createIcons();
    }
  }
}

// ============================================================
//  MODAL
// ============================================================
const modal = {
  abrir() {
    selectores.modal.classList.add("sol-modal-show");
    selectores.paso1.classList.remove("hidden");
    selectores.paso2.classList.add("hidden");
    if (selectores.btnGuardar) selectores.btnGuardar.style.display = "none";
    this.limpiarFormulario();
    setTimeout(() => selectores.selectPrograma?.focus(), 50);
  },

  cerrar() {
    selectores.modal.classList.remove("sol-modal-show");
    this.limpiarFormulario();
  },

  limpiarFormulario() {
    estadoApp.datosFormulario = { programa: "", rae: "", ficha: "", observaciones: "" };
    estadoApp.materialesSeleccionados = [];
    selectores.formNueva.reset();
    materiales.limpiarMateriales();
  },

  validarPaso1() {
    if (!selectores.selectPrograma.value) {
      utilidades.mostrarError("Seleccione un programa");
      selectores.selectPrograma.focus();
      return false;
    }
    if (!selectores.selectRae.value) {
      utilidades.mostrarError("Seleccione un RAE");
      selectores.selectRae.focus();
      return false;
    }
    if (!selectores.selectFichas.value) {
      utilidades.mostrarError("Seleccione una ficha");
      selectores.selectFichas.focus();
      return false;
    }

    estadoApp.datosFormulario = {
      programa: selectores.selectPrograma.value,
      rae: selectores.selectRae.value,
      ficha: selectores.selectFichas.value,
      observaciones: (selectores.textareaObservaciones.value || "").trim(),
    };

    return true;
  },

  irPaso2() {
    if (!this.validarPaso1()) return;
    selectores.paso1.classList.add("hidden");
    selectores.paso2.classList.remove("hidden");
    if (selectores.btnGuardar) selectores.btnGuardar.style.display = "inline-flex";
    selectores.selectMaterial?.focus();
  },

  volverPaso1() {
    selectores.paso2.classList.add("hidden");
    selectores.paso1.classList.remove("hidden");
    if (selectores.btnGuardar) selectores.btnGuardar.style.display = "none";
    selectores.selectPrograma?.focus();
  },

  async enviarSolicitud() {
    if (!estadoApp.materialesSeleccionados.length) {
      utilidades.mostrarError("Debe agregar al menos un material");
      selectores.selectMaterial.focus();
      return;
    }
    if (!confirm("¿Crear esta solicitud?")) return;

    try {
      selectores.btnGuardar.disabled = true;
      selectores.btnGuardar.innerHTML =
        '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Procesando...';
      lucide.createIcons();

      const payload = {
        id_usuario: 1,
        id_programa: parseInt(estadoApp.datosFormulario.programa, 10),
        id_rae: parseInt(estadoApp.datosFormulario.rae, 10),
        id_ficha: parseInt(estadoApp.datosFormulario.ficha, 10),
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
      selectores.btnGuardar.disabled = false;
      selectores.btnGuardar.innerHTML = "Crear Solicitud";
      lucide.createIcons();
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
      if (e.key === "Escape" && selectores.modal.classList.contains("sol-modal-show")) {
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
    selectores.contenedorCards.innerHTML = `
      <div class="col-span-full py-12 text-center">
        <i data-lucide="loader" class="w-12 h-12 text-blue-300 animate-spin mx-auto mb-4"></i>
        <h3 class="text-lg font-medium text-gray-700 mb-2">Cargando solicitudes</h3>
        <p class="text-gray-500">Obteniendo datos de la base de datos...</p>
      </div>`;
    lucide.createIcons();

    await this.cargarSolicitudes();
    await api.cargarSelectores();
    eventos.inicializar();
  },

  async cargarSolicitudes() {
    estadoApp.solicitudes = await api.listarSolicitudes();
    render.actualizarResumen();
    render.actualizarFiltros();
    render.renderizarSolicitudes();
  },
};

document.addEventListener("DOMContentLoaded", () => {
  if (typeof lucide !== "undefined") lucide.createIcons();
  console.log("[SOLICITUDES] API =", API);
  app.inicializar();
});

window.paginacion = paginacion;
window.materiales = materiales;
window.app = app;
window.agregarEventosBotonesAccion = agregarEventosBotonesAccion;
window.cambiarEstadoSolicitud = cambiarEstadoSolicitud;
window.marcarEntregada = marcarEntregada;
    