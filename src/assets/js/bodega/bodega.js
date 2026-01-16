/* =========================================================
   CONFIGURACIÓN GENERAL
========================================================= */

const API_BODEGA = "/Gestion-inventario/src/controllers/bodega_controller.php";
const API_SUB = "/Gestion-inventario/src/controllers/sub_bodega_controller.php";

let listaBodegas = [];      // bodegas
let listaSubbodegas = [];   // subbodegas
let listaTotal = [];        // ambas listas unificadas

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

  if (!data) return;

  alert(`
Nombre: ${data.nombre || data.nombre_subbodega}
Código: ${data.codigo_bodega || data.codigo_subbodega}
Tipo: ${tipo}
Estado: ${data.estado}
  `);
}

/* =========================================================
   EDITAR
========================================================= */
function editar(tipo, id) {
  modalTitulo.textContent = "Editar Registro";
  wrapperEstado.classList.remove("hidden");
  abrirModal();

  let data;

  if (tipo === "bodega") {
    tipoRegistro.value = "bodega";
    data = listaBodegas.find(b => b.id_bodega == id);

    hiddenId.value = id;
    inputCodigo.value = data.codigo_bodega;
    inputNombre.value = data.nombre;
    inputUbicacion.value = data.ubicacion;

    wrapperUbicacion.classList.remove("hidden");
    wrapperPadre.classList.add("hidden");
    wrapperClasificacion.classList.add("hidden");
    wrapperDescripcion.classList.add("hidden");
  }

  if (tipo === "subbodega") {
    tipoRegistro.value = "subbodega";
    data = listaSubbodegas.find(s => s.id_subbodega == id);

    hiddenId.value = id;
    idPadre.value = data.id_bodega;
    inputCodigo.value = data.codigo_subbodega;
    inputNombre.value = data.nombre_subbodega;
    inputClasificacion.value = data.clasificacion_subbodegas;
    inputDescripcion.value = data.descripcion;

    wrapperPadre.classList.remove("hidden");
    wrapperClasificacion.classList.remove("hidden");
    wrapperDescripcion.classList.remove("hidden");
    wrapperUbicacion.classList.add("hidden");
  }

  inputEstado.value = data.estado;
}

/* =========================================================
   SUBMIT DEL FORMULARIO (Crear o Editar)
========================================================= */
form.onsubmit = async (e) => {
  e.preventDefault();

  const tipo = tipoRegistro.value;
  const id = hiddenId.value;

  /* Ensure required fields */
  if (!inputCodigo.value || !inputNombre.value) {
    alert("Debe completar los campos obligatorios");
    return;
  }

  try {
    /* -------------------------
       CREAR / ACTUALIZAR BODEGA
    ------------------------- */
    if (tipo === "bodega") {
      const body = {
        codigo_bodega: inputCodigo.value,
        nombre: inputNombre.value,
        ubicacion: inputUbicacion.value
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

/* =========================================================
   INICIO
========================================================= */
cargarDatos();
