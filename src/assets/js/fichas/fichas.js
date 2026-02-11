// =========================
// CONFIG: CONTROLLER ENDPOINTS
// =========================
const API_URL = "src/controllers/ficha_controller.php"
const PROGRAMAS_API_URL = "src/controllers/programa_controller.php"

// =========================
// NIVEL CONFIGURATION (label and badge styles)
// =========================
const nivelLabels = {
  Tecnólogo: "Tecnólogo",
  Técnico: "Técnico",
}

// Badge classes defined in fichas.css
const nivelBadgeStyles = {
  Tecnólogo: "badge-nivel-tecnologo",
  Técnico: "badge-nivel-tecnico",
}

// =========================
// VALID LISTS ACCORDING TO DATABASE
// =========================
const VALID_NIVELES = ["Tecnólogo", "Técnico"];

// In-memory list used to render table and cards
let fichas = [];
let originalEditData = null;
let selectedFicha = null;

let programas = [];
let programasMap = {};

// Apprentices
let aprendices = [];
let estudiantesSeleccionados = [];
let aprendicesCargando = false;
let detalleAprendices = [];
let detalleAprendicesSeleccionados = new Set();
let detalleAprendicesOriginal = new Set();
let detalleAprendicesCargando = false;

// Instructors
let instructores = [];
let instructoresSeleccionados = [];

// Group leader
let jefeGrupoSeleccionado = null;

// =========================
// PAGINATION
// =========================
const PAGE_SIZE_TABLE = 10
const PAGE_SIZE_CARDS = 9

let currentPageTable = 1
let currentPageCards = 1

// =========================
// FLOWBITE-STYLE ALERTS
// =========================

function getOrCreateFlowbiteContainer() {
  let container = document.getElementById("flowbite-alert-container")

  if (!container) {
    container = document.createElement("div")
    container.id = "flowbite-alert-container"
    container.className =
      "fixed top-6 left-1/2 -translate-x-1/2 z-[9999] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none"
    document.body.appendChild(container)
  }

  return container
}

function showFlowbiteAlert(type, message) {
  const container = getOrCreateFlowbiteContainer()
  const wrapper = document.createElement("div")

  let borderColor = "border-amber-500"
  let textColor = "text-amber-900"
  let titleText = "Advertencia"

  let iconSVG = `
    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
         fill="currentColor" viewBox="0 0 20 20">
      <path d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.59A1.75 1.75 0 0 1 16.768 17H3.232a1.75 1.75 0 0 1-1.492-2.311L8.257 3.1z"/>
      <path d="M11 13H9V9h2zm0 3H9v-2h2z" fill="#fff"/>
    </svg>
  `;

  if (type === "success") {
    borderColor = "border-emerald-500"
    textColor = "text-primary-900"
    titleText = "Éxito"
    iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
           fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm-1 15-4-4 1.414-1.414L9 12.172l4.586-4.586L15 9z"/>
      </svg>
    `
  }

  if (type === "info") {
    borderColor = "border-blue-500"
    textColor = "text-blue-900"
    titleText = "Información"
    iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
           fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm1 15H9v-5h2Zm0-7H9V6h2Z"/>
      </svg>
    `
  }

  wrapper.className = `
    relative flex items-center w-full mx-auto pointer-events-auto
    rounded-2xl border-l-4 ${borderColor} bg-white shadow-md
    px-4 py-3 text-sm ${textColor}
    opacity-0 -translate-y-2
    transition-all duration-300 ease-out
    animate-fade-in-up
  `

  wrapper.innerHTML = `
    <div class="flex-shrink-0 mr-3 text-current">
      ${iconSVG}
    </div>
    <div class="flex-1 min-w-0">
      <p class="font-semibold">${titleText}</p>
      <p class="mt-0.5 text-sm">${message}</p>
    </div>
  `

  container.appendChild(wrapper)

  requestAnimationFrame(() => {
    wrapper.classList.remove("opacity-0", "-translate-y-2")
    wrapper.classList.add("opacity-100", "translate-y-0")
  })

  setTimeout(() => {
    wrapper.classList.add("opacity-0", "-translate-y-2")
    wrapper.classList.remove("opacity-100", "translate-y-0")
    setTimeout(() => wrapper.remove(), 250)
  }, 4000)
}

function toastError(message) {
  showFlowbiteAlert("warning", message)
}

function toastSuccess(message) {
  showFlowbiteAlert("success", message)
}

function toastInfo(message) {
  showFlowbiteAlert("info", message)
}

// =========================
// DOM REFERENCES
// =========================
const tbodyFichas = document.getElementById("tbodyFichas")
const inputBuscar = document.getElementById("inputBuscar")
const selectFiltroEstado = document.getElementById("selectFiltroEstado")

const vistaTabla = document.getElementById("vistaTabla")
const vistaTarjetas = document.getElementById("vistaTarjetas")
const cardsContainer = document.getElementById("cardsContainer")
const btnVistaTabla = document.getElementById("btnVistaTabla")
const btnVistaTarjetas = document.getElementById("btnVistaTarjetas")

const modalFicha = document.getElementById("modalFicha")
const btnNuevaFicha = document.getElementById("btnNuevaFicha")
const btnCerrarModalFicha = document.getElementById("btnCerrarModalFicha")
const btnCancelarModalFicha = document.getElementById("btnCancelarModalFicha")

const formFicha = document.getElementById("formFicha")
const hiddenFichaId = document.getElementById("hiddenFichaId")
const modalFichaTitulo = document.getElementById("modalFichaTitulo")
const modalFichaDescripcion = document.getElementById("modalFichaDescripcion")

// Inputs of the Fichas form
const inputNumeroFicha = document.getElementById("numero_ficha");
const inputPrograma = document.getElementById("id_programa");
const inputJornada = document.getElementById("jornada");
const inputModalidad = document.getElementById("modalidad");
const inputFechaInicio = document.getElementById("fecha_inicio");
const inputFechaFin = document.getElementById("fecha_fin");

// Form steps
const paso1Ficha = document.getElementById("paso1Ficha");
const paso2Ficha = document.getElementById("paso2Ficha");
const paso3Ficha = document.getElementById("paso3Ficha");
const paso4Ficha = document.getElementById("paso4Ficha");
const btnIrPaso2 = document.getElementById("btnIrPaso2");
const btnVolverPaso1 = document.getElementById("btnVolverPaso1");
const btnIrPaso3 = document.getElementById("btnIrPaso3");
const btnVolverPaso2 = document.getElementById("btnVolverPaso2");
const btnIrPaso4 = document.getElementById("btnIrPaso4");
const btnVolverPaso3 = document.getElementById("btnVolverPaso3");

// Apprentices
const selectEstudiante = document.getElementById("selectEstudiante");
const listaEstudiantesSeleccionados = document.getElementById("listaEstudiantesSeleccionados");

// Instructors
const selectInstructor = document.getElementById("selectInstructor");
const listaInstructoresSeleccionados = document.getElementById("listaInstructoresSeleccionados");

const modalVerFicha = document.getElementById("modalVerFicha")
const btnCerrarModalVerFicha = document.getElementById("btnCerrarModalVerFicha")
const detalleFichaContent = document.getElementById("detalleFichaContent")

// Group leader
const listaJefeGrupo = document.getElementById("listaJefeGrupo");

console.log("API_URL:", API_URL);
console.log("PROGRAMAS_API_URL:", PROGRAMAS_API_URL);

// =========================
// SINGLE PAGINATION CONTAINER
// =========================
let paginationTabla = document.getElementById("paginationTabla")

function ensurePaginationContainer() {
  if (vistaTarjetas && !paginationTabla) {
    paginationTabla = document.createElement("div")
    paginationTabla.id = "paginationTabla"
    paginationTabla.className = "mt-4 flex justify-end gap-2"
    vistaTarjetas.parentNode.insertBefore(paginationTabla, vistaTarjetas.nextSibling)
  }
}

ensurePaginationContainer()

// =========================
// EMPTY STATE CONTAINERS
// =========================
let emptyStateContainer = document.getElementById("emptyStateFichas")
let emptySearchContainer = document.getElementById("emptySearchFichas")

if (!emptyStateContainer && vistaTabla && vistaTabla.parentNode) {
  emptyStateContainer = document.createElement("div")
  emptyStateContainer.id = "emptyStateFichas"
  emptyStateContainer.className =
    "hidden mt-10 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full"
  emptyStateContainer.innerHTML = `
    <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
      <svg class="h-7 w-7 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none"
           viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
    </div>
    <h3 class="text-lg font-semibold mt-4">No hay fichas registradas</h3>
    <p class="text-sm text-muted-foreground mt-1 max-w-md">
      Una vez agregue fichas desde el botón <strong>"Nueva Ficha"</strong>, aparecerán listadas en esta vista.
    </p>
  `
  vistaTabla.parentNode.insertBefore(emptyStateContainer, vistaTabla)
}

if (!emptySearchContainer && vistaTabla && vistaTabla.parentNode) {
  emptySearchContainer = document.createElement("div")
  emptySearchContainer.id = "emptySearchFichas"
  emptySearchContainer.className =
    "hidden mt-10 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full"
  emptySearchContainer.innerHTML = `
    <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
      <svg class="h-7 w-7 text-muted-foreground"
           xmlns="http://www.w3.org/2000/svg"
           fill="none"
           viewBox="0 0 24 24"
           stroke="currentColor"
           stroke-width="1.8">
        <circle cx="11" cy="11" r="6" stroke-linecap="round" stroke-linejoin="round"></circle>
        <line x1="16" y1="16" x2="20" y2="20" stroke-linecap="round" stroke-linejoin="round"></line>
      </svg>
    </div>
    <h3 class="text-lg font-semibold mt-4">No se encontraron resultados</h3>
    <p class="text-sm text-muted-foreground mt-1 max-w-md">
      No se encontraron fichas que coincidan con los criterios de búsqueda actuales.
    </p>
  `
  vistaTabla.parentNode.insertBefore(emptySearchContainer, vistaTabla)
}

// =========================
// HELPER FUNCTIONS
// =========================

function renderOpcionesPrograma() {
  if (!inputPrograma) return

  inputPrograma.innerHTML = ""

  if (!Array.isArray(programas) || programas.length === 0) {
    inputPrograma.innerHTML = `<option value="">No hay programas disponibles</option>`
    inputPrograma.disabled = true
    return
  }

  inputPrograma.disabled = false
  inputPrograma.innerHTML = `<option value="">Seleccione</option>`

  programas.forEach((p) => {
    const opt = document.createElement("option")
    opt.value = p.id_programa
    opt.textContent = p.nombre_programa || p.nombre || ""
    opt.dataset.nivel = p.nivel || "Tecnólogo"
    inputPrograma.appendChild(opt)
  })
}

async function cargarProgramas() {
  if (!inputPrograma) return

  try {
    const res = await fetch(`${PROGRAMAS_API_URL}?accion=listar`)
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${res.statusText}`)
    }
    
    const data = await res.json()
    
    if (Array.isArray(data)) {
      programas = data.map((p) => ({
        id_programa: p.id_programa,
        nombre_programa: p.nombre_programa || p.nombre || "",
        nivel: p.nivel || "Tecnólogo",
      }))

      programasMap = {}
      programas.forEach((p) => {
        programasMap[String(p.id_programa)] = p.nombre_programa
      })
    } else {
      programas = []
      console.error("La respuesta de programas no es un array:", data)
    }

    renderOpcionesPrograma()
  } catch (error) {
    console.error("Error al cargar programas:", error)
    toastError("Error al cargar los programas")
    programas = []
    renderOpcionesPrograma()
  }
}

// =========================
// LOAD APPRENTICES
// =========================
async function cargarAprendices(idFicha = null) {
  setAprendicesLoading(true)
  try {
    const query = idFicha ? `&id_ficha=${encodeURIComponent(idFicha)}` : ""
    const res = await fetch(`${API_URL}?accion=aprendices${query}`)
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${res.statusText}`)
    }
    
    const data = await res.json()
    
    if (Array.isArray(data)) {
      aprendices = data
    } else {
      aprendices = []
      console.error("La respuesta de aprendices no es un array:", data)
    }
    setAprendicesLoading(false)
  } catch (error) {
    console.error("Error al cargar aprendices:", error)
    aprendices = []
    setAprendicesLoading(false)
  }
}

// =========================
// LOAD INSTRUCTORS (with program filter)
// =========================
async function cargarInstructores(id_programa = null) {
  try {
    let url = `${API_URL}?accion=instructores`;

    if (id_programa) {
      url = `${API_URL}?accion=instructoresPorPrograma&id_programa=${id_programa}`;
      console.log("Cargando instructores para programa:", id_programa);
    }

    console.log("Solicitando instructores desde:", url);
    const res = await fetch(url);
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    }

    const data = await res.json();
    console.log("Instructores recibidos:", data);

    if (Array.isArray(data)) {
      instructores = data;
      console.log(`Se cargaron ${data.length} instructores para el programa ${id_programa || "todos"}`);
    } else {
      instructores = [];
      console.error("La respuesta de instructores no es un array:", data);
    }

    renderChecklistInstructores();
  }
  catch (error) {
    console.error("Error al cargar instructores:", error);
    instructores = [];
    renderChecklistInstructores();
  }
}

function renderOpcionesAprendices() {
  if (!selectEstudiante) return

  if (aprendicesCargando) {
    selectEstudiante.value = ""
    selectEstudiante.disabled = true
    selectEstudiante.placeholder = "Cargando aprendices..."
    return
  }

  selectEstudiante.disabled = false
  selectEstudiante.placeholder = "Buscar aprendices por nombre, documento o correo..."
  selectEstudiante.innerHTML = ""

  if (!Array.isArray(aprendices) || aprendices.length === 0) {
    selectEstudiante.innerHTML = `<option value="">No hay aprendices disponibles</option>`
    selectEstudiante.disabled = true
    return
  }

  selectEstudiante.disabled = false
  selectEstudiante.innerHTML = `<option value="">Seleccione un estudiante...</option>`

  // Filter only the apprentices who are not selected yet
  const aprendicesDisponibles = aprendices.filter(a => 
    !estudiantesSeleccionados.some(e => e.id_usuario === a.id_usuario)
  )

  // Add "Select All" option if there are available apprentices
  if (aprendicesDisponibles.length > 0) {
    const optAll = document.createElement("option")
    optAll.value = "all"
    optAll.textContent = "Seleccionar todos los aprendices"
    selectEstudiante.appendChild(optAll)
  }
}

function agregarEstudiante() {
  const estudianteId = selectEstudiante.value
  
  if (!estudianteId) {
    toastError("Seleccione un aprendiz")
    return
  }

  if (estudianteId === "all") {
    // Add all available apprentices
    const aprendicesDisponibles = aprendices.filter(a => 
      !estudiantesSeleccionados.some(e => e.id_usuario === a.id_usuario)
    )
    aprendicesDisponibles.forEach(a => {
      const estudiante = {
        id_usuario: a.id_usuario,
        nombre_completo: a.nombre_completo,
        numero_documento: a.numero_documento,
        correo: a.correo || ''
      }
      estudiantesSeleccionados.push(estudiante)
    })
  } else {
    const option = selectEstudiante.selectedOptions[0]
    const estudiante = {
      id_usuario: estudianteId,
      nombre_completo: option.dataset.nombre,
      numero_documento: option.dataset.documento,
      correo: option.dataset.correo
    }
    estudiantesSeleccionados.push(estudiante)
  }

  selectEstudiante.value = ''
  
  renderOpcionesAprendices() // Update available options
  renderChecklistAprendices()
}

function seleccionarTodosVisibles() {
  const searchTerm = (selectEstudiante && selectEstudiante.value ? selectEstudiante.value.toLowerCase() : '')
  const aprendicesFiltrados = aprendices.filter(a => 
    (a.nombre_completo && a.nombre_completo.toLowerCase().includes(searchTerm)) ||
    (a.numero_documento && a.numero_documento.toString().toLowerCase().includes(searchTerm)) ||
    (a.correo && a.correo.toLowerCase().includes(searchTerm))
  )

  // Verify all filtered are selected
  const todosSeleccionados = aprendicesFiltrados.every(a => 
    estudiantesSeleccionados.some(e => e.id_usuario == a.id_usuario)
  )

  if (todosSeleccionados) {
    // Remove all filters from estudiantesSeleccionados
    estudiantesSeleccionados = estudiantesSeleccionados.filter(e => 
      !aprendicesFiltrados.some(a => a.id_usuario == e.id_usuario)
    )
  } else {
    // Add the ones that aren't already selected
    aprendicesFiltrados.forEach(a => {
      if (!estudiantesSeleccionados.some(e => e.id_usuario == a.id_usuario)) {
        estudiantesSeleccionados.push({
          id_usuario: a.id_usuario,
          nombre_completo: a.nombre_completo,
          numero_documento: a.numero_documento,
          correo: a.correo || ''
        })
      }
    })
  }

  renderChecklistAprendices()
}

function seleccionarTodosInstructoresVisibles() {
  const searchTerm = (selectInstructor && selectInstructor.value ? selectInstructor.value.toLowerCase() : '')
  const instructoresFiltrados = instructores.filter(i => 
    (i.nombre_completo && i.nombre_completo.toLowerCase().includes(searchTerm)) ||
    (i.numero_documento && i.numero_documento.toString().toLowerCase().includes(searchTerm)) ||
    (i.correo && i.correo.toLowerCase().includes(searchTerm))
  )

  // Verify all filtered are selected
  const todosSeleccionados = instructoresFiltrados.every(i => 
    instructoresSeleccionados.some(e => e.id_usuario == i.id_usuario)
  )

  if (todosSeleccionados) {
    // Remove all filters from instructoresSeleccionados
    instructoresSeleccionados = instructoresSeleccionados.filter(e => 
      !instructoresFiltrados.some(i => i.id_usuario == e.id_usuario)
    )
  } else {
    // Add those that are not there
    instructoresFiltrados.forEach(i => {
      if (!instructoresSeleccionados.some(e => e.id_usuario == i.id_usuario)) {
        instructoresSeleccionados.push({
          id_usuario: i.id_usuario,
          nombre_completo: i.nombre_completo,
          numero_documento: i.numero_documento,
          correo: i.correo || ''
        })
      }
    })
  }

  renderChecklistInstructores()
}

function renderChecklistAprendices() {
  if (!listaEstudiantesSeleccionados) return

  if (aprendicesCargando) {
    listaEstudiantesSeleccionados.innerHTML = `
      <div class="text-center text-muted-foreground py-8">
        <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <p class="text-sm">Cargando aprendices...</p>
      </div>
    `
    return
  }

  if (!Array.isArray(aprendices) || aprendices.length === 0) {
    listaEstudiantesSeleccionados.innerHTML = `
      <div class="text-center text-muted-foreground py-8">
        <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <p class="text-sm">No hay aprendices disponibles</p>
      </div>
    `
    return
  }

  // Filter by search 
  const searchTerm = (selectEstudiante && selectEstudiante.value ? selectEstudiante.value.toLowerCase() : '')
  const aprendicesFiltrados = aprendices.filter(a => 
    (a.nombre_completo && a.nombre_completo.toLowerCase().includes(searchTerm)) ||
    (a.numero_documento && a.numero_documento.toString().toLowerCase().includes(searchTerm)) ||
    (a.correo && a.correo.toLowerCase().includes(searchTerm))
  )

  // If there are apprentices but none match the search, show notice
  if (aprendicesFiltrados.length === 0) {
    listaEstudiantesSeleccionados.innerHTML = `
      <div class="flex flex-col items-center justify-center text-center py-8">
        <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
          <svg class="h-7 w-7 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none"
               viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <circle cx="11" cy="11" r="6" stroke-linecap="round" stroke-linejoin="round"></circle>
            <line x1="16" y1="16" x2="20" y2="20" stroke-linecap="round" stroke-linejoin="round"></line>
          </svg>
        </div>
        <h3 class="text-lg font-semibold mt-4">No se encontraron resultados</h3>
        <p class="text-sm text-muted-foreground mt-1 max-w-md">No se encontraron aprendices que coincidan con los criterios de búsqueda actuales.</p>
      </div>
    `
    return
  }

  let html = `
    <div class="space-y-2">
      <div class="flex justify-between text-xs font-medium text-gray-500 pb-2 border-b">
        <span class="flex-1">Estudiante</span>
        <span class="w-32 text-center">Documento</span>
        <span class="w-16 text-center">Seleccionar</span>
      </div>
  `

  aprendicesFiltrados.forEach((aprendiz) => {
    const isSelected = estudiantesSeleccionados.some(e => e.id_usuario == aprendiz.id_usuario)
    html += `
      <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
        <div class="flex-1 list-item-content">
          <div class="font-medium text-sm list-item-name">${aprendiz.nombre_completo}</div>
          ${aprendiz.correo ? `<div class="text-xs text-gray-500 list-item-email">${aprendiz.correo}</div>` : ''}
        </div>
        <div class="w-32 text-center text-sm">${aprendiz.numero_documento}</div>
        <div class="w-16 text-center">
          <input type="checkbox" ${isSelected ? 'checked' : ''} onchange="toggleEstudiante(${aprendiz.id_usuario})" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
        </div>
      </div>
    `
  })

  const totalSeleccionados = estudiantesSeleccionados.length
  html += `
      <div class="pt-2 border-t">
        <div class="flex justify-between font-medium text-gray-700 text-sm">
          <span>Total seleccionados:</span>
          <span>${totalSeleccionados}</span>
        </div>
        <div class="mt-2 text-center">
          <a href="#" onclick="seleccionarTodosVisibles(); return false;" class="text-foreground hover:opacity-80 underline text-sm">
            Seleccionar todos los visibles
          </a>
        </div>
      </div>
    </div>
  `

  listaEstudiantesSeleccionados.innerHTML = html
}

function renderChecklistInstructores() {
    if (!listaInstructoresSeleccionados) return;

    // If no programs is selected
    const id_programa = inputPrograma ? inputPrograma.value : null;
    if (!id_programa && !selectedFicha?.id_programa) {
      listaInstructoresSeleccionados.innerHTML = `
        <div class="text-center text-muted-foreground py-8">
          <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <p class="text-sm">Primero debe seleccionar un programa de formación</p>
          <p class="text-xs text-gray-500 mt-1">Los instructores se mostrarán según el programa seleccionado</p>
        </div>
      `;
      return;
    }

    if (!Array.isArray(instructores) || instructores.length === 0) {
      listaInstructoresSeleccionados.innerHTML = `
        <div class="text-center text-muted-foreground py-8">
          <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <p class="text-sm">No hay instructores disponibles para este programa</p>
          <p class="text-xs text-gray-500 mt-1">Asocie instructores al programa desde la gestión de programas</p>
        </div>
      `;
      return;
    }

  // Filter by search
  const searchTerm = (selectInstructor && selectInstructor.value ? selectInstructor.value.toLowerCase() : '')
  const instructoresFiltrados = instructores.filter(i => 
    (i.nombre_completo && i.nombre_completo.toLowerCase().includes(searchTerm)) ||
    (i.numero_documento && i.numero_documento.toString().toLowerCase().includes(searchTerm)) ||
    (i.correo && i.correo.toLowerCase().includes(searchTerm))
  )

  // If there are instructors but none match the search, show notice
  if (instructoresFiltrados.length === 0) {
    listaInstructoresSeleccionados.innerHTML = `
      <div class="flex flex-col items-center justify-center text-center py-8">
        <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
          <svg class="h-7 w-7 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none"
               viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <circle cx="11" cy="11" r="6" stroke-linecap="round" stroke-linejoin="round"></circle>
            <line x1="16" y1="16" x2="20" y2="20" stroke-linecap="round" stroke-linejoin="round"></line>
          </svg>
        </div>
        <h3 class="text-lg font-semibold mt-4">No se encontraron resultados</h3>
        <p class="text-sm text-muted-foreground mt-1 max-w-md">No se encontraron instructores que coincidan con los criterios de búsqueda actuales.</p>
      </div>
    `
    return
  }

  let html = `
    <div class="space-y-2">
      <div class="flex justify-between text-xs font-medium text-gray-500 pb-2 border-b">
        <span class="flex-1">Instructor</span>
        <span class="w-32 text-center">Documento</span>
        <span class="w-16 text-center">Seleccionar</span>
      </div>
  `

  instructoresFiltrados.forEach((instructor) => {
    const isSelected = instructoresSeleccionados.some(e => e.id_usuario == instructor.id_usuario)
    html += `
      <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
        <div class="flex-1 list-item-content">
          <div class="font-medium text-sm list-item-name">${instructor.nombre_completo}</div>
          ${instructor.correo ? `<div class="text-xs text-gray-500 list-item-email">${instructor.correo}</div>` : ''}
        </div>
        <div class="w-32 text-center text-sm">${instructor.numero_documento}</div>
        <div class="w-16 text-center">
          <input type="checkbox" ${isSelected ? 'checked' : ''} onchange="toggleInstructor(${instructor.id_usuario})" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
        </div>
      </div>
    `
  })

  const totalSeleccionados = instructoresSeleccionados.length
  html += `
      <div class="pt-2 border-t">
        <div class="flex justify-between font-medium text-gray-700 text-sm">
          <span>Total seleccionados:</span>
          <span>${totalSeleccionados}</span>
        </div>
        <div class="mt-2 text-center">
          <a href="#" onclick="seleccionarTodosInstructoresVisibles(); return false;" class="text-foreground hover:opacity-80 underline text-sm">
            Seleccionar todos los visibles
          </a>
        </div>
      </div>
    </div>
  `

  listaInstructoresSeleccionados.innerHTML = html
}

// Toggle selection when checking/unchecking a single apprentice
function toggleEstudiante(id) {
  if (id === undefined || id === null) return

  const idStr = String(id)
  const exists = estudiantesSeleccionados.some(e => String(e.id_usuario) === idStr)

  if (exists) {
    estudiantesSeleccionados = estudiantesSeleccionados.filter(e => String(e.id_usuario) !== idStr)
  } else {
    const aprendiz = aprendices.find(a => String(a.id_usuario) === idStr)
    if (!aprendiz) return

    estudiantesSeleccionados.push({
      id_usuario: aprendiz.id_usuario,
      nombre_completo: aprendiz.nombre_completo,
      numero_documento: aprendiz.numero_documento,
      correo: aprendiz.correo || ''
    })
  }

  renderOpcionesAprendices()
  renderChecklistAprendices()
}

function toggleInstructor(id) {
  if (id === undefined || id === null) return;

  const idStr = String(id);
  const exists = instructoresSeleccionados.some(e => String(e.id_usuario) === idStr);

  if (exists) {
    // Verify if he is the selected group leader
    if (jefeGrupoSeleccionado && String(jefeGrupoSeleccionado.id_usuario) === idStr) {
      toastError("No puede quitar al jefe de grupo. Primero seleccione otro jefe.");
      return;
    }
    
    instructoresSeleccionados = instructoresSeleccionados.filter(e => String(e.id_usuario) !== idStr);
  } else {
    const instructor = instructores.find(i => String(i.id_usuario) === idStr);
    if (!instructor) return;

    instructoresSeleccionados.push({
      id_usuario: instructor.id_usuario,
      nombre_completo: instructor.nombre_completo,
      numero_documento: instructor.numero_documento,
      correo: instructor.correo || ''
    });
  }

  renderChecklistInstructores();
}

function eliminarEstudiante(id) {
  if (id === undefined || id === null) return
  const idStr = String(id)
  estudiantesSeleccionados = estudiantesSeleccionados.filter(e => String(e.id_usuario) !== idStr)
  renderOpcionesAprendices()
  renderChecklistAprendices()
}

function openModalFicha(editFicha = null) {
  selectedFicha = editFicha;
  modalFicha.classList.add("active")

  // Reset selections
  estudiantesSeleccionados = []
  instructoresSeleccionados = []
  jefeGrupoSeleccionado = null 

  // Show first step and hide others
  if (paso1Ficha) paso1Ficha.classList.remove("hidden")
  if (paso2Ficha) paso2Ficha.classList.add("hidden")
  if (paso3Ficha) paso3Ficha.classList.add("hidden")
  if (paso4Ficha) paso4Ficha.classList.add("hidden")

  let programaIdEditar = ""

  if (editFicha) {
    modalFichaTitulo.textContent = "Editar Ficha"
    modalFichaDescripcion.textContent = "Modifica la información de la ficha"
    hiddenFichaId.value = editFicha.id

    // Upload data to the form
    inputNumeroFicha.value = editFicha.numero_ficha || ""
    programaIdEditar = editFicha.id_programa ? String(editFicha.id_programa) : ""
    inputJornada.value = editFicha.jornada || ""
    inputModalidad.value = editFicha.modalidad || ""
    inputFechaInicio.value = editFicha.fecha_inicio || ""
    inputFechaFin.value = editFicha.fecha_fin || ""

    // Original data for comparing changes
    originalEditData = {
      numero_ficha: String(editFicha.numero_ficha ?? "").trim(),
      id_programa: editFicha.id_programa ? String(editFicha.id_programa) : "",
      jornada: editFicha.jornada || "",
      modalidad: editFicha.modalidad || "",
      fecha_inicio: editFicha.fecha_inicio || "",
      fecha_fin: editFicha.fecha_fin || "",
    }

    // Upload aprentices and instructors to this record if you are editing
    cargarEstudiantesDeFicha(editFicha.id)
    cargarInstructoresDeFicha(editFicha.id)

  } else {
    modalFichaTitulo.textContent = "Crear Nueva Ficha"
    modalFichaDescripcion.textContent = "Complete los datos para registrar una nueva ficha de formación"
    hiddenFichaId.value = ""

    // Clear the form
    inputNumeroFicha.value = ""
    inputPrograma.value = ""
    inputJornada.value = ""
    inputModalidad.value = ""
    inputFechaInicio.value = ""
    inputFechaFin.value = ""
    
    originalEditData = null
  }

  renderOpcionesPrograma();
  if (programaIdEditar) {
    inputPrograma.value = programaIdEditar
  }
  setAprendicesLoading(true)

  // Load instructors only if a program is already selected (in editing)
  if (editFicha && editFicha.id_programa) {
    cargarInstructores(editFicha.id_programa);
  } else {
    // During creation, do not load instructors until a program is selected
    instructores = [];
    renderChecklistInstructores();
  }
  
  renderOpcionesJefeGrupo();

  cargarAprendices(editFicha ? editFicha.id : null);
}

function setAprendicesLoading(isLoading) {
  aprendicesCargando = isLoading
  if (isLoading) {
    aprendices = []
  }
  renderOpcionesAprendices()
  renderChecklistAprendices()
}

async function cargarEstudiantesDeFicha(idFicha) {
  try {
    const res = await fetch(`${API_URL}?accion=estudiantesFicha&id_ficha=${idFicha}`)
    if (!res.ok) return

    const data = await res.json()
    
    if (Array.isArray(data) && data.length > 0) {
      estudiantesSeleccionados = data
      renderOpcionesAprendices()
      renderChecklistAprendices()
    }
  } catch (error) {
    console.error("Error al cargar aprendices de la ficha:", error)
  }
}

async function cargarInstructoresDeFicha(idFicha) {
  try {
    console.log(`Cargando instructores para ficha: ${idFicha}`);
    
    const res = await fetch(`${API_URL}?accion=instructoresFicha&id_ficha=${idFicha}`)
    
    if (!res.ok) {
      console.error(`Error HTTP: ${res.status}`);
      return;
    }

    const data = await res.json();
    console.log('Datos recibidos de instructores:', data);
    
    if (Array.isArray(data) && data.length > 0) {
      instructoresSeleccionados = data;
      
      // Search and set the group leader
      const jefe = data.find(instructor => instructor.es_jefe_grupo == 1 || instructor.es_jefe_grupo === true);
      if (jefe) {
        jefeGrupoSeleccionado = {
          id_usuario: jefe.id_usuario,
          nombre_completo: jefe.nombre_completo,
          numero_documento: jefe.numero_documento,
          correo: jefe.correo || ''
        };
        console.log(`Jefe de grupo establecido: ${jefeGrupoSeleccionado.nombre_completo}`);
      }
      
      console.log(`Se cargaron ${data.length} instructores`);

      // Upload the complete list of instructors for this program
      const ficha = fichas.find(f => String(f.id) === String(idFicha));
      if (ficha && ficha.id_programa) {
        await cargarInstructores(ficha.id_programa);
      } else {
        renderChecklistInstructores();
      }

      renderOpcionesJefeGrupo(); // Update group leader options
    } else {
      instructoresSeleccionados = [];
      jefeGrupoSeleccionado = null;
      console.log('No se encontraron instructores para esta ficha');

      // Upload program instructors if they exist
      const ficha = fichas.find(f => String(f.id) === String(idFicha));
      if (ficha && ficha.id_programa) {
        await cargarInstructores(ficha.id_programa);
      } else {
        renderChecklistInstructores();
      }
    }
  } catch (error) {
    console.error("Error al cargar instructores de la ficha:", error);
    instructoresSeleccionados = [];
    jefeGrupoSeleccionado = null;
  }
}

function closeModalFicha() {
  modalFicha.classList.remove("active")
  selectedFicha = null
  hiddenFichaId.value = ""
  originalEditData = null
  estudiantesSeleccionados = []
  instructoresSeleccionados = []
  jefeGrupoSeleccionado = null
  
  // Reset to first step
  if (paso1Ficha) paso1Ficha.classList.remove("hidden")
  if (paso2Ficha) paso2Ficha.classList.add("hidden")
  if (paso3Ficha) paso3Ficha.classList.add("hidden")
  if (paso4Ficha) paso4Ficha.classList.add("hidden")
}

async function openModalVerFicha(ficha) {
  selectedFicha = ficha
  modalVerFicha.classList.add("active")

  const programaNombre = ficha.id_programa
    ? programasMap[String(ficha.id_programa)] || "Sin programa asignado"
    : "Sin programa asignado"

  const nivelNombre = ficha.nivel || "N/A"

  // Fetch instructors to get the group leader
  let jefeNombre = "No asignado"
  try {
    const res = await fetch(`${API_URL}?accion=instructoresFicha&id_ficha=${ficha.id}`)
    if (res.ok) {
      const instructores = await res.json()
      const jefe = instructores.find(inst => inst.es_jefe_grupo == 1 || inst.es_jefe_grupo === true)
      if (jefe) {
        jefeNombre = jefe.nombre_completo || "Sin nombre"
      }
    }
  } catch (error) {
    console.error("Error al cargar jefe de ficha:", error)
  }

  detalleFichaContent.innerHTML = `
    <div class="flex items-center gap-4">
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-badge-secondary text-badge-secondary">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-kanban-icon lucide-folder-kanban"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/><path d="M8 10v4"/><path d="M12 10v2"/><path d="M16 10v6"/></svg>
      </div>
      <div>
        <h3 class="font-semibold text-xl">${ficha.numero_ficha}</h3>
          <div class="flex items-center gap-3 mt-1">
          <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
            nivelBadgeStyles[ficha.nivel] || "badge-nivel-default"
          }">
            ${nivelLabels[ficha.nivel] || nivelNombre || "N/A"}
          </span>

          <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${
            ficha.estado === 'Activa' ? 'badge-estado-activo' :
            ficha.estado === 'Finalizada' ? 'badge-estado-inactivo' :
            ficha.estado === 'Cancelada' ? 'badge-estado-inactivo' :
            'bg-gray-100 text-gray-800'
          }">
            ${ficha.estado || 'Activa'}
          </span>
        </div>
      </div>
    </div>
    <div class="space-y-3 text-sm mt-4">
      <div class="flex items-start gap-3">
        <span class="text-muted-foreground min-w-[80px]">Jefe de Ficha:</span>
        <span class="font-medium">${jefeNombre}</span>
      </div>
      <div class="flex items-start gap-3">
        <span class="text-muted-foreground min-w-[80px]">Programa:</span>
        <span class="font-medium">${programaNombre}</span>
      </div>
      <div class="flex items-start gap-3">
        <span class="text-muted-foreground min-w-[80px]">Jornada:</span>
        <span class="font-medium">${ficha.jornada || "No especificado"}</span>
      </div>
      <div class="flex items-start gap-3">
        <span class="text-muted-foreground min-w-[80px]">Modalidad:</span>
        <span class="font-medium">${ficha.modalidad || "No especificado"}</span>
      </div>
      <div class="flex items-start gap-3">
        <span class="text-muted-foreground min-w-[80px]">Fecha Inicio:</span>
        <span class="font-medium">${ficha.fecha_inicio || "No especificado"}</span>
      </div>
      <div class="flex items-start gap-3">
        <span class="text-muted-foreground min-w-[80px]">Fecha Fin:</span>
        <span class="font-medium">${ficha.fecha_fin || "No especificado"}</span>
      </div>
    </div>
    <div class="mt-5 border-t border-border pt-4">
      <div class="flex items-center justify-between gap-2">
        <h4 class="text-sm font-semibold">Aprendices en la ficha</h4>
        <button id="btnGuardarAprendices" type="button"
          class="inline-flex items-center justify-center rounded-md bg-secondary px-3 py-1.5 text-xs font-medium text-primary-foreground shadow hover:opacity-90 disabled:opacity-50"
          disabled>
          Sin cambios
        </button>
      </div>
      <div class="mt-2">
        <input id="detalleBuscarAprendiz" type="text"
          placeholder="Buscar aprendiz por nombre, documento o correo..."
          class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" />
      </div>
      <div id="detalleListaAprendices"
        class="mt-3 max-h-[240px] overflow-y-auto rounded-lg border border-border p-3"></div>
      <p id="detalleAprendicesInfo" class="mt-2 text-xs text-muted-foreground"></p>
    </div>
  `

  setupDetalleAprendicesHandlers()
  await cargarDetalleAprendices(ficha.id)
}

function closeModalVerFicha() {
  modalVerFicha.classList.remove("active")
  selectedFicha = null
  detalleAprendices = []
  detalleAprendicesSeleccionados = new Set()
  detalleAprendicesOriginal = new Set()
  detalleAprendicesCargando = false
}

function setupDetalleAprendicesHandlers() {
  const inputBuscar = document.getElementById("detalleBuscarAprendiz")
  const lista = document.getElementById("detalleListaAprendices")
  const btnGuardar = document.getElementById("btnGuardarAprendices")

  if (inputBuscar) {
    inputBuscar.oninput = () => renderDetalleAprendices()
  }

  if (lista) {
    lista.onchange = (event) => {
      const target = event.target
      if (!target || !target.matches("input[type='checkbox'][data-id]")) return

      const id = target.getAttribute("data-id")
      if (!id) return

      if (target.checked) {
        detalleAprendicesSeleccionados.add(id)
      } else {
        detalleAprendicesSeleccionados.delete(id)
      }

      actualizarEstadoGuardarAprendices()
    }
  }

  if (btnGuardar) {
    btnGuardar.onclick = async () => {
      if (!selectedFicha) return
      await guardarDetalleAprendices(selectedFicha.id)
    }
  }
}

function setDetalleAprendicesLoading(isLoading) {
  detalleAprendicesCargando = isLoading
  renderDetalleAprendices()
}

async function cargarDetalleAprendices(idFicha) {
  setDetalleAprendicesLoading(true)
  try {
    const res = await fetch(`${API_URL}?accion=estudiantesFicha&id_ficha=${idFicha}`)
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${res.statusText}`)
    }

    const data = await res.json()

    if (Array.isArray(data)) {
      detalleAprendices = data
    } else {
      detalleAprendices = []
    }

    detalleAprendicesSeleccionados = new Set(
      detalleAprendices.map(a => String(a.id_usuario))
    )
    detalleAprendicesOriginal = new Set(detalleAprendicesSeleccionados)
  } catch (error) {
    console.error("Error al cargar aprendices de la ficha:", error)
    detalleAprendices = []
    detalleAprendicesSeleccionados = new Set()
    detalleAprendicesOriginal = new Set()
  }

  setDetalleAprendicesLoading(false)
}

function renderDetalleAprendices() {
  const lista = document.getElementById("detalleListaAprendices")
  const info = document.getElementById("detalleAprendicesInfo")
  const inputBuscar = document.getElementById("detalleBuscarAprendiz")

  if (!lista || !info) return

  if (detalleAprendicesCargando) {
    lista.innerHTML = `
      <div class="text-center text-muted-foreground py-6">
        <p class="text-sm">Cargando aprendices...</p>
      </div>
    `
    info.textContent = ""
    actualizarEstadoGuardarAprendices()
    return
  }

  if (!Array.isArray(detalleAprendices) || detalleAprendices.length === 0) {
    lista.innerHTML = `
      <div class="text-center text-muted-foreground py-6">
        <p class="text-sm">No hay aprendices asignados a esta ficha.</p>
      </div>
    `
    info.textContent = ""
    actualizarEstadoGuardarAprendices()
    return
  }

  const term = inputBuscar && inputBuscar.value ? inputBuscar.value.toLowerCase() : ""
  const filtrados = detalleAprendices.filter(a =>
    (a.nombre_completo && a.nombre_completo.toLowerCase().includes(term)) ||
    (a.numero_documento && a.numero_documento.toString().toLowerCase().includes(term)) ||
    (a.correo && a.correo.toLowerCase().includes(term))
  )

  if (filtrados.length === 0) {
    lista.innerHTML = `
      <div class="text-center text-muted-foreground py-6">
        <p class="text-sm">No se encontraron aprendices con ese criterio.</p>
      </div>
    `
    info.textContent = ""
    actualizarEstadoGuardarAprendices()
    return
  }

  const rows = filtrados.map((a) => {
    const id = String(a.id_usuario)
    const checked = detalleAprendicesSeleccionados.has(id) ? "checked" : ""
    return `
      <label class="flex items-center justify-between gap-3 rounded-md border border-border px-3 py-2 text-sm hover:bg-muted">
        <div class="min-w-0">
          <div class="font-medium truncate">${a.nombre_completo || "Sin nombre"}</div>
          <div class="text-xs text-muted-foreground truncate">${a.numero_documento || "Sin documento"}${a.correo ? ` · ${a.correo}` : ""}</div>
        </div>
        <input type="checkbox" data-id="${id}" class="h-4 w-4 rounded border-input" ${checked} />
      </label>
    `
  }).join("")

  lista.innerHTML = `<div class="space-y-2">${rows}</div>`
  info.textContent = `Seleccionados: ${detalleAprendicesSeleccionados.size} de ${detalleAprendices.length}`
  actualizarEstadoGuardarAprendices()
}

function actualizarEstadoGuardarAprendices() {
  const btn = document.getElementById("btnGuardarAprendices")
  if (!btn) return

  const seleccionados = detalleAprendicesSeleccionados
  const originales = detalleAprendicesOriginal
  let cambios = seleccionados.size !== originales.size

  if (!cambios) {
    for (const id of seleccionados) {
      if (!originales.has(id)) {
        cambios = true
        break
      }
    }
  }

  btn.disabled = !cambios
  btn.textContent = cambios ? `Guardar cambios (${seleccionados.size})` : "Sin cambios"
}

async function guardarDetalleAprendices(idFicha) {
  const btn = document.getElementById("btnGuardarAprendices")
  if (btn) btn.disabled = true

  try {
    const payload = {
      id_ficha: idFicha,
      estudiantes: Array.from(detalleAprendicesSeleccionados)
    }

    const res = await fetch(`${API_URL}?accion=agregarEstudiantes`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    })

    const data = await res.json()
    if (data && data.success) {
      detalleAprendicesOriginal = new Set(detalleAprendicesSeleccionados)
      toastSuccess("Aprendices actualizados correctamente.")
      await cargarAprendices()
    } else {
      toastError(data.error || "No se pudieron actualizar los aprendices.")
    }
  } catch (error) {
    console.error("Error al actualizar aprendices:", error)
    toastError("No se pudieron actualizar los aprendices.")
  }

  actualizarEstadoGuardarAprendices()
}

// =========================
// BACKEND COMMUNICATION LOGIC
// =========================

async function callApi(url, payload = null) {
  try {
    const options = {
      method: payload ? "POST" : "GET",
      headers: { "Content-Type": "application/json" },
    }
    
    if (payload) {
      options.body = JSON.stringify(payload)
    }
    
    const res = await fetch(url, options)
    
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${res.statusText}`)
    }
    
    const data = await res.json()
    return data
  } catch (error) {
    console.error("Error en callApi:", error)
    return { error: error.message || "Error de conexión" }
  }
}

async function cargarFichas() {
  try {
    const data = await callApi(`${API_URL}?accion=listar`)
    
    if (data.error) {
      throw new Error(data.error) 
    }
    
    if (!Array.isArray(data)) {
      fichas = []
      console.error("La respuesta de fichas no es un array:", data)
    } else {
      fichas = data.map((f) => ({
        id: f.id_ficha,
        numero_ficha: f.numero_ficha,
        id_programa: f.id_programa ?? null,
        jornada: f.jornada || null,
        modalidad: f.modalidad || null,
        fecha_inicio: f.fecha_inicio || null,
        fecha_fin: f.fecha_fin || null,
        nivel: f.nivel || "Tecnólogo",
        estado: f.estado || "Activa",
      }))
    }

    renderTable()
  } catch (error) {
    console.error("Error al cargar fichas:", error)
    fichas = []
    renderTable()
  }
}

function crearFicha(payload) {
  return callApi(`${API_URL}?accion=crear`, payload)
}

function actualizarFicha(payload) {
  return callApi(`${API_URL}?accion=actualizar`, payload)
}

// =========================
// FUNCTIONS TO CHANGE STATE
// =========================

async function cambiarEstadoFicha(id, accion) {
    if (!id) {
        toastError("ID de ficha no válido");
        return;
    }

    // Validate allowed actions according to your controller
    const accionesPermitidas = ['activar', 'finalizar', 'cancelar'];
    if (!accionesPermitidas.includes(accion)) {
        toastError("Acción no válida");
        return;
    }
    try {
        // Call the corresponding endpoint according to the action
        const res = await fetch(`${API_URL}?accion=${accion}&id_ficha=${id}`, {
            method: "GET"  
        });

        const text = await res.text();
        
        // Parse the response
        let data;
        try {
            const start = text.indexOf("{");
            const end = text.lastIndexOf("}");
            if (start !== -1 && end !== -1 && end > start) {
                data = JSON.parse(text.slice(start, end + 1));
            } else {
                throw new Error("Respuesta no válida");
            }
        } catch (e) {
            data = { error: "Respuesta no válida del servidor" };
        }

        if (data.error) {
            toastError(data.error);
        } else if (data.success) {
            // Messages based on action
            const mensajesExito = {
                'activar': 'Ficha activada correctamente',
                'finalizar': 'Ficha finalizada correctamente',
                'cancelar': 'Ficha cancelada correctamente'
            };
            
            toastSuccess(data.message || mensajesExito[accion]);
            await cargarFichas(); // Reload the list
        } else {
            toastError(data.message || `Error al ${accion} la ficha`);
        }
    } catch (error) {
        console.error(`Error al ${accion} ficha:`, error);
        toastError(`Error al ${accion} la ficha`);
    }
}

// Specific features for compatibility
async function activarFicha(id) {
    await cambiarEstadoFicha(id, 'activar');
}

async function finalizarFicha(id) {
    await cambiarEstadoFicha(id, 'finalizar');
}

async function cancelarFicha(id) {
    await cambiarEstadoFicha(id, 'cancelar');
}

// =========================
// VIEW MODE SWITCH: TABLE / CARDS
// =========================

function setVistaTabla() {
  vistaTabla.classList.remove("hidden")
  vistaTarjetas.classList.add("hidden")

  btnVistaTabla.classList.add("bg-muted", "text-foreground")
  btnVistaTarjetas.classList.remove("bg-muted")
  btnVistaTarjetas.classList.add("text-muted-foreground")

  renderTable()
}

function setVistaTarjetas() {
  vistaTabla.classList.add("hidden")
  vistaTarjetas.classList.remove("hidden")

  btnVistaTarjetas.classList.add("bg-muted", "text-foreground")
  btnVistaTabla.classList.remove("bg-muted")
  btnVistaTabla.classList.add("text-muted-foreground")

  renderTable()
}

// =========================
// GENERIC PAGINATION RENDER
// =========================

function renderPaginationControls(container, totalItems, pageSize, currentPage, onPageChange) {
  if (!container) return

  const totalPages = Math.ceil(totalItems / pageSize)

  if (totalPages <= 1) {
    container.innerHTML = ""
    return
  }

  container.innerHTML = ""

  const btnPrev = document.createElement("button")
  btnPrev.type = "button"
  btnPrev.className = "px-3 py-1 text-sm rounded-lg border border-border bg-card hover:bg-muted disabled:opacity-40"
  btnPrev.textContent = "Anterior"
  btnPrev.disabled = currentPage === 1
  btnPrev.addEventListener("click", () => {
    if (currentPage > 1) onPageChange(currentPage - 1)
  })
  container.appendChild(btnPrev)

  for (let i = 1; i <= totalPages; i++) {
    const btn = document.createElement("button")
    btn.type = "button"
    btn.textContent = i
    btn.className =
      "px-3 py-1 text-sm rounded-lg border border-border " +
      (i === currentPage ? "bg-primary text-white" : "bg-card hover:bg-muted")
    btn.addEventListener("click", () => {
      if (i !== currentPage) onPageChange(i)
    })
    container.appendChild(btn)
  }

  const btnNext = document.createElement("button")
  btnNext.type = "button"
  btnNext.className = "px-3 py-1 text-sm rounded-lg border border-border bg-card hover:bg-muted disabled:opacity-40"
  btnNext.textContent = "Siguiente"
  btnNext.disabled = currentPage === totalPages
  btnNext.addEventListener("click", () => {
    if (currentPage < totalPages) onPageChange(currentPage + 1)
  })
  container.appendChild(btnNext)
}

// =========================
// TABLE AND CARDS RENDERING
// =========================

function renderTable() {
  const search = inputBuscar.value.trim().toLowerCase()
  const filtroEstado = selectFiltroEstado.value

  const filtered = fichas.filter((f) => {
    const matchNumero = String(f.numero_ficha).toLowerCase().includes(search)
    const matchEstado = !filtroEstado || f.estado === filtroEstado
    return matchNumero && matchEstado
  })

  const totalItems = filtered.length

  const clearRenderedContent = () => {
    tbodyFichas.innerHTML = ""
    cardsContainer.innerHTML = ""
    if (paginationTabla) paginationTabla.innerHTML = ""
  }

  if (fichas.length === 0) {
    clearRenderedContent()
    vistaTabla.classList.add("hidden")
    vistaTarjetas.classList.add("hidden")
    if (emptyStateContainer) emptyStateContainer.classList.remove("hidden")
    if (emptySearchContainer) emptySearchContainer.classList.add("hidden")
    return
  }

  if (totalItems === 0) {
    clearRenderedContent()
    vistaTabla.classList.add("hidden")
    vistaTarjetas.classList.add("hidden")
    if (emptyStateContainer) emptyStateContainer.classList.add("hidden")
    if (emptySearchContainer) emptySearchContainer.classList.remove("hidden")
    return
  }

  if (emptyStateContainer) emptyStateContainer.classList.add("hidden")
  if (emptySearchContainer) emptySearchContainer.classList.add("hidden")

  if (btnVistaTabla.classList.contains("bg-muted")) {
    vistaTabla.classList.remove("hidden")
  }
  if (btnVistaTarjetas.classList.contains("bg-muted")) {
    vistaTarjetas.classList.remove("hidden")
  }

  const totalPagesTable = Math.max(1, Math.ceil(totalItems / PAGE_SIZE_TABLE) || 1)
  const totalPagesCards = Math.max(1, Math.ceil(totalItems / PAGE_SIZE_CARDS) || 1)

  if (currentPageTable > totalPagesTable) currentPageTable = totalPagesTable
  if (currentPageCards > totalPagesCards) currentPageCards = totalPagesCards

  const startIndexTable = (currentPageTable - 1) * PAGE_SIZE_TABLE
  const endIndexTable = startIndexTable + PAGE_SIZE_TABLE
  const pageItemsTable = filtered.slice(startIndexTable, endIndexTable)

  const startIndexCards = (currentPageCards - 1) * PAGE_SIZE_CARDS
  const endIndexCards = startIndexCards + PAGE_SIZE_CARDS
  const pageItemsCards = filtered.slice(startIndexCards, endIndexCards)

  tbodyFichas.innerHTML = ""

  pageItemsTable.forEach((ficha) => {
    const tr = document.createElement("tr");
    tr.className = "hover:bg-muted/40";

    const programaNombre = ficha.id_programa
        ? programasMap[String(ficha.id_programa)] || "Sin asignar"
        : "Sin asignar";

    const nivelNombre = ficha.nivel || "N/A";

    // Determine which actions to display based on the status
    let accionesHTML = '';
    if (ficha.estado === 'Activa') {
        accionesHTML = `
            <button
                type="button"
                class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted"
                data-action="finalizar"
                data-id="${ficha.id}"
            >
                <svg class="mr-2 h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Finalizar
            </button>
            <button
                type="button"
                class="flex w-full items-center px-3 py-2 text-sm text-red-600 hover:bg-red-50"
                data-action="cancelar"
                data-id="${ficha.id}"
            >
                <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Cancelar
            </button>
        `;
    } else if (ficha.estado === 'Finalizada' || ficha.estado === 'Cancelada') {
        accionesHTML = `
            <button
                type="button"
                class="flex w-full items-center px-3 py-2 text-sm text-green-600 hover:bg-green-50"
                data-action="activar"
                data-id="${ficha.id}"
            >
                <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Activar
            </button>
        `;
    }

    tr.innerHTML = `
      <td class="px-4 py-3 align-middle">
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-badge-secondary text-badge-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-kanban-icon lucide-folder-kanban"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/><path d="M8 10v4"/><path d="M12 10v2"/><path d="M16 10v6"/></svg>
          </div>
          <span class="font-medium text-sm">${ficha.numero_ficha}</span>
        </div>
      </td>

      <td class="px-4 py-3 align-middle">
        <div class="flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-graduation-cap-icon lucide-graduation-cap"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>
          <span class="text-sm">${programaNombre}</span>
        </div>
      </td>

      <td class="px-4 py-3 align-middle">
        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${
          nivelBadgeStyles[ficha.nivel] || "badge-nivel-default"
        }">
          ${nivelLabels[ficha.nivel] || nivelNombre || "N/A"}
        </span>
      </td>

      <td class="px-4 py-3 align-middle">
        <span class="text-sm">${ficha.jornada || "No especificado"}</span>
      </td>

      <td class="px-4 py-3 align-middle">
        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${
          ficha.estado === 'Activa' ? 'badge-estado-activo' :
          ficha.estado === 'Finalizada' ? 'badge-estado-inactivo' :
          ficha.estado === 'Cancelada' ? 'badge-estado-inactivo' :
          'bg-gray-100 text-gray-800'
        }">
          ${ficha.estado || 'Activa'}
        </span>
      </td>

      <td class="px-4 py-3 align-middle text-right">
        <div class="relative inline-block text-left">
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-muted text-slate-800"
            data-menu-trigger="${ficha.id}"
          >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
              <circle cx="5" cy="12" r="1.5"></circle>
              <circle cx="12" cy="12" r="1.5"></circle>
              <circle cx="19" cy="12" r="1.5"></circle>
            </svg>
          </button>

          <div
            class="dropdown-menu hidden absolute right-0 mt-2 w-48 rounded-xl border border-border bg-popover shadow-md py-1 z-50"
            data-menu="${ficha.id}"
          >
            <button
              type="button"
              class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted"
              data-action="ver"
              data-id="${ficha.id}"
            >
              <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                   viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M1 12S4.5 5 12 5s11 7 11 7-3.5 7-11 7S1 12 1 12z"/>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
              Ver detalles
            </button>

            <button
              type="button"
              class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted"
              data-action="editar"
              data-id="${ficha.id}"
            >
              <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                   viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9"/>
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M16.5 3.5a2.121 2.121 0 0 1 3 3L9 17l-4 1 1-4 10.5-10.5z"/>
              </svg>
              Editar
            </button>
            
            ${accionesHTML}
          </div>
        </div>
      </td>
    `;

    tbodyFichas.appendChild(tr);
  });

  cardsContainer.innerHTML = ""

  pageItemsCards.forEach((ficha) => {
    const programaNombre = ficha.id_programa
        ? programasMap[String(ficha.id_programa)] || "Sin asignar"
        : "Sin asignar";

    const nivelNombre = ficha.nivel || "N/A";

    // Determine which actions to show based on the status (for cards)
    let accionesHTML = '';
    if (ficha.estado === 'Activa') {
        accionesHTML = `
            <button
                type="button"
                class="flex w-full items-center px-3 py-2 text-xs text-slate-700 hover:bg-muted"
                data-action="finalizar"
                data-id="${ficha.id}"
            >
                <svg class="mr-2 h-3.5 w-3.5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Finalizar
            </button>
            <button
                type="button"
                class="flex w-full items-center px-3 py-2 text-xs text-red-600 hover:bg-red-50"
                data-action="cancelar"
                data-id="${ficha.id}"
            >
                <svg class="mr-2 h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Cancelar
            </button>
        `;
    } else if (ficha.estado === 'Finalizada' || ficha.estado === 'Cancelada') {
        accionesHTML = `
            <button
                type="button"
                class="flex w-full items-center px-3 py-2 text-xs text-green-600 hover:bg-green-50"
                data-action="activar"
                data-id="${ficha.id}"
            >
                <svg class="mr-2 h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Activar
            </button>
        `;
    }

    const card = document.createElement("div");
    card.className = "rounded-2xl border border-border bg-card p-4 shadow-sm flex flex-col";

    card.innerHTML = `
      <div class="flex items-start justify-between gap-2 mb-3">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-badge-secondary text-badge-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-kanban-icon lucide-folder-kanban"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/><path d="M8 10v4"/><path d="M12 10v2"/><path d="M16 10v6"/></svg>
          </div>
          <div>
            <p class="font-semibold text-base">${ficha.numero_ficha}</p>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
              nivelBadgeStyles[ficha.nivel] || "badge-nivel-default"
            }">
              ${nivelLabels[ficha.nivel] || nivelNombre}
            </span>
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${
              ficha.estado === 'Activa' ? 'badge-estado-activo' :
              ficha.estado === 'Finalizada' ? 'badge-estado-inactivo' :
              ficha.estado === 'Cancelada' ? 'badge-estado-inactivo' :
              'bg-gray-100 text-gray-800'
            }">
              ${ficha.estado || 'Activa'}
            </span>
          </div>
        </div>

        <div class="relative inline-block text-left">
          <button
            type="button"
            class="inline-flex h-7 w-7 items-center justify-center rounded-md hover:bg-muted text-slate-800"
            data-menu-trigger="${ficha.id}"
          >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
              <circle cx="5" cy="12" r="1.5"></circle>
              <circle cx="12" cy="12" r="1.5"></circle>
              <circle cx="19" cy="12" r="1.5"></circle>
            </svg>
          </button>
          <div
            class="dropdown-menu hidden absolute right-0 mt-2 w-40 rounded-xl border border-border bg-popover shadow-md py-1 z-50"
            data-menu="${ficha.id}"
          >
            <button
              type="button"
              class="flex w-full items-center px-3 py-2 text-xs text-slate-700 hover:bg-muted"
              data-action="ver"
              data-id="${ficha.id}"
            >
              <svg class="mr-2 h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                   viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M1 12S4.5 5 12 5s11 7 11 7-3.5 7-11 7S1 12 1 12z"/>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
              Ver detalles
            </button>
            <button
              type="button"
              class="flex w-full items-center px-3 py-2 text-xs text-slate-700 hover:bg-muted"
              data-action="editar"
              data-id="${ficha.id}"
            >
              <svg class="mr-2 h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                   viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9"/>
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M16.5 3.5a2.121 2.121 0 0 1 3 3L9 17l-4 1 1-4 10.5-10.5z"/>
              </svg>
              Editar
            </button>
            ${accionesHTML}
          </div>
        </div>
      </div>

      <div class="space-y-2 text-sm text-muted-foreground flex-1">
        <div class="flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-graduation-cap-icon lucide-graduation-cap"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>
          <span>${programaNombre}</span>
        </div>
        <div class="flex items-center gap-2">
          <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>${ficha.jornada || "No especificado"}</span>
        </div>
        <div class="flex items-center gap-2">
          <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <span>${ficha.modalidad || "No especificado"}</span>
        </div>
        <div class="flex items-center gap-2">
          <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <span>${ficha.fecha_inicio || "No especificado"} - ${ficha.fecha_fin || "No especificado"}</span>
        </div>
      </div>
    `;

    cardsContainer.appendChild(card);
  });

  attachMenuEvents()

  const tablaVisible = !vistaTabla.classList.contains("hidden")

  if (tablaVisible) {
    renderPaginationControls(paginationTabla, totalItems, PAGE_SIZE_TABLE, currentPageTable, (page) => {
      currentPageTable = page
      renderTable()
    })
  } else {
    renderPaginationControls(paginationTabla, totalItems, PAGE_SIZE_CARDS, currentPageCards, (page) => {
      currentPageCards = page
      renderTable()
    })
  }
}

// =========================
// DROPDOWN MENU HANDLING
// =========================

let _menuEventsAttached = false

function attachMenuEvents() {
  if (_menuEventsAttached) return
  _menuEventsAttached = true

  function portalizeMenu(menu, trigger) {
    if (!menu || !trigger) return

    if (menu.dataset.portaled === "1") {
      positionMenuFixed(menu, trigger)
      return
    }

    const placeholder = document.createElement("span")
    placeholder.style.display = "none"
    placeholder.dataset.menuPlaceholder = menu.getAttribute("data-menu") || ""

    menu._placeholderEl = placeholder
    menu.parentNode.insertBefore(placeholder, menu)

    document.body.appendChild(menu)
    menu.dataset.portaled = "1"

    menu.style.position = "fixed"
    menu.style.zIndex = "99999"
    menu.style.marginTop = "0"

    positionMenuFixed(menu, trigger)
  }

  function restoreMenu(menu) {
    if (!menu || menu.dataset.portaled !== "1") return

    const placeholder = menu._placeholderEl
    if (placeholder && placeholder.parentNode) {
      placeholder.parentNode.insertBefore(menu, placeholder)
      placeholder.remove()
    }

    menu.dataset.portaled = "0"
    menu.style.position = ""
    menu.style.top = ""
    menu.style.left = ""
    menu.style.zIndex = ""
    menu.style.marginTop = ""
  }

  function positionMenuFixed(menu, trigger) {
    if (!menu || !trigger) return

    menu.classList.remove("hidden")

    const rect = trigger.getBoundingClientRect()
    const menuRect = menu.getBoundingClientRect()

    let top = rect.bottom + 8
    let left = rect.right - menuRect.width

    const maxLeft = window.innerWidth - menuRect.width - 12
    if (left > maxLeft) left = maxLeft
    if (left < 12) left = 12

    const maxTop = window.innerHeight - menuRect.height - 12
    if (top > maxTop) {
      top = rect.top - menuRect.height - 8
    }
    if (top < 12) top = 12

    menu.style.top = `${top}px`
    menu.style.left = `${left}px`
  }

  const closeAllMenus = () => {
    document.querySelectorAll("[data-menu]").forEach((el) => {
      el.classList.add("hidden")
      el.classList.remove("show")
      restoreMenu(el)
    })
  }

  document.addEventListener("click", (e) => {
    const trigger = e.target.closest("[data-menu-trigger]")
    const actionBtn = e.target.closest("[data-menu] [data-action]")
    const anyMenu = e.target.closest("[data-menu]")

    if (actionBtn) {
      e.stopPropagation()

      const action = actionBtn.getAttribute("data-action")
      const id = actionBtn.getAttribute("data-id")

      if (!id || !action) {
        closeAllMenus()
        return
      }

      if (action === "ver") {
        const ficha = fichas.find((f) => String(f.id) === String(id))
        if (ficha) openModalVerFicha(ficha)
      } else if (action === "editar") {
        const ficha = fichas.find((f) => String(f.id) === String(id))
        if (ficha) openModalFicha(ficha)
      } else if (["activar", "finalizar", "cancelar"].includes(action)) {
        cambiarEstadoFicha(id, action)
      }

      closeAllMenus()
      return
    }

    if (trigger) {
      e.stopPropagation()

      const wrapper =
        trigger.closest(".relative") ||
        trigger.closest(".inline-block") ||
        trigger.closest("td") ||
        trigger.closest("div")

      if (!wrapper) return

      const menu = wrapper.querySelector("[data-menu]")
      if (!menu) return

      const isHidden = menu.classList.contains("hidden")

      closeAllMenus()

      if (isHidden) {
        portalizeMenu(menu, trigger)
        requestAnimationFrame(() => {
          menu.classList.add("show")
        })
      } else {
        closeAllMenus()
      }

      return
    }

    if (!anyMenu) {
      closeAllMenus()
    }
  })

  window.addEventListener("scroll", closeAllMenus, true)
  window.addEventListener("resize", closeAllMenus)
}

// =========================
// GLOBAL EVENT LISTENERS
// =========================

// Navigation between steps
if (btnIrPaso2) {
  btnIrPaso2.addEventListener("click", function() {
    if (validarPaso1()) {
      // Verify if the ficha number already exists
      const numeroFicha = inputNumeroFicha.value.trim();
      const fichaExistente = fichas.find(f => String(f.numero_ficha) === String(numeroFicha));
      const isEdit = hiddenFichaId.value !== "" && hiddenFichaId.value !== null && hiddenFichaId.value !== undefined;

      if (fichaExistente && (!isEdit || String(fichaExistente.id) !== String(hiddenFichaId.value))) {
        const mensaje = isEdit
          ? "El número de ficha ingresado ya existe."
          : "El número de ficha que se intenta crear ya existe.";
        showFlowbiteAlert("warning", mensaje);
        return;
      }
      paso1Ficha.classList.add("hidden")
      paso2Ficha.classList.remove("hidden")
    }
  });
}

if (btnVolverPaso1) {
  btnVolverPaso1.addEventListener("click", function() {
    paso2Ficha.classList.add("hidden")
    paso1Ficha.classList.remove("hidden")
  })
}

if (btnIrPaso3) {
  btnIrPaso3.addEventListener("click", function() {
    paso2Ficha.classList.add("hidden")
    paso3Ficha.classList.remove("hidden")
  })
}

if (btnVolverPaso2) {
  btnVolverPaso2.addEventListener("click", function() {
    paso3Ficha.classList.add("hidden")
    paso2Ficha.classList.remove("hidden")
  })
}

if (btnIrPaso4) {
  btnIrPaso4.addEventListener("click", function() {
    if (instructoresSeleccionados.length === 0) {
      toastError("Debe seleccionar al menos un instructor antes de continuar.");
      return;
    }
    
    paso3Ficha.classList.add("hidden");
    paso4Ficha.classList.remove("hidden");
    renderOpcionesJefeGrupo();
  })
}

if (btnVolverPaso3) {
  btnVolverPaso3.addEventListener("click", function() {
    paso4Ficha.classList.add("hidden");
    paso3Ficha.classList.remove("hidden");
  })
}

// ==========================================
// NUEVO LISTENER PARA CAMBIOS EN PROGRAMA
// ==========================================
// Cuando cambia el programa, actualizar instructores
if (inputPrograma) {
  inputPrograma.addEventListener("change", async function() {
    const id_programa = this.value;
    
    if (id_programa) {
      // Recargar instructores con el nuevo filtro
      await cargarInstructores(id_programa);
      
      // Limpiar selecciones anteriores si estamos en edición y el programa cambió
      if (selectedFicha) {
        const programaAnterior = selectedFicha.id_programa;
        if (String(programaAnterior) !== String(id_programa)) {
          instructoresSeleccionados = [];
          jefeGrupoSeleccionado = null;
          toastInfo("El programa ha cambiado. Por favor, seleccione los instructores nuevamente.");
        }
      }
      
      renderChecklistInstructores();
      renderOpcionesJefeGrupo();
    } else {
      // Si se deselecciona el programa, limpiar instructores
      instructores = [];
      instructoresSeleccionados = [];
      jefeGrupoSeleccionado = null;
      renderChecklistInstructores();
      renderOpcionesJefeGrupo();
    }
  });
}
// ==========================================

// Add student with Enter
if (selectEstudiante) {
  selectEstudiante.addEventListener("keyup", () => {
    console.log("Filtro activado:", selectEstudiante.value);
    renderChecklistAprendices()
  })
}

// Add instructor with Enter
if (selectInstructor) {
  selectInstructor.addEventListener("keyup", () => {
    console.log("Filtro activado:", selectInstructor.value);
    renderChecklistInstructores()
  })
}

inputBuscar.addEventListener("input", () => {
  currentPageTable = 1
  currentPageCards = 1
  renderTable()
})

selectFiltroEstado.addEventListener("change", () => {
  currentPageTable = 1
  currentPageCards = 1
  renderTable()
})

btnNuevaFicha.addEventListener("click", () => openModalFicha(null))
btnCerrarModalFicha.addEventListener("click", closeModalFicha)
btnCancelarModalFicha.addEventListener("click", closeModalFicha)

btnCerrarModalVerFicha.addEventListener("click", closeModalVerFicha)

btnVistaTabla.addEventListener("click", setVistaTabla)
btnVistaTarjetas.addEventListener("click", setVistaTarjetas)

// ================================
// FORM VALIDATION AND SUBMISSION
// ================================

function validarPaso1() {
  const numeroRegex = /^[0-9]+$/

  if (!inputNumeroFicha.value.trim()) {
    toastError("El número de ficha es obligatorio.")
    inputNumeroFicha.focus()
    return false
  }

  if (!numeroRegex.test(inputNumeroFicha.value.trim())) {
    toastError("El número de ficha solo puede contener números.")
    inputNumeroFicha.focus()
    return false
  }

  const numeroFichaLength = inputNumeroFicha.value.trim().length
  if (numeroFichaLength < 7 || numeroFichaLength > 10) {
    toastError("El número de ficha debe tener entre 7 y 10 caracteres.")
    inputNumeroFicha.focus()
    return false
  }

  if (!inputPrograma.value) {
    toastError("Debe seleccionar un programa de formación.")
    inputPrograma.focus()
    return false
  }

  if (!inputJornada.value) {
    toastError("Debe seleccionar una jornada.")
    inputJornada.focus()
    return false
  }

  if (!inputModalidad.value) {
    toastError("Debe seleccionar una modalidad.")
    inputModalidad.focus()
    return false
  }

  if (!inputFechaInicio.value) {
    toastError("Debe seleccionar la fecha de inicio.")
    inputFechaInicio.focus()
    return false
  }

  if (!inputFechaFin.value) {
    toastError("Debe seleccionar la fecha de fin.")
    inputFechaFin.focus()
    return false
  }

  if (inputFechaFin.value < inputFechaInicio.value) {
    toastError("La fecha fin no puede ser menor que la fecha inicio.")
    inputFechaFin.focus()
    return false
  }

  return true
}

// ================================
// FORM VALIDATION AND SUBMISSION
// ================================

formFicha.addEventListener("submit", async (e) => {
    e.preventDefault();

    // Validar paso 1
    if (!validarPaso1()) {
        return
    }

    // Verify that a group leader has been selected if there are instructors
    if (instructoresSeleccionados.length > 0 && !jefeGrupoSeleccionado) {
        toastError("Debe seleccionar un jefe de grupo entre los instructores.");
        return;
    }

    // Determine if it is editing or creation
    const isEdit = hiddenFichaId.value !== "" && hiddenFichaId.value !== null && hiddenFichaId.value !== undefined;

    const payload = {
        numero_ficha: inputNumeroFicha.value.trim(),
        id_programa: inputPrograma.value || null,
        jornada: inputJornada.value || null,
        modalidad: inputModalidad.value || null,
        fecha_inicio: inputFechaInicio.value || null,
        fecha_fin: inputFechaFin.value || null,
        estado: "Activa" // It is always created as "Activa"
    };

    // Add ID if it's an edit
    if (isEdit) {
        payload.id_ficha = hiddenFichaId.value;
    }

    try {
        const data = isEdit ? await actualizarFicha(payload) : await crearFicha(payload);

        if (data.error) {
            toastError(data.error || "Ocurrió un error al procesar la solicitud.");
            return;
        }

        if (!data.success) {
            toastError(data.message || "Error al procesar la solicitud.");
            return;
        }

        // Get the ID of the record (new or existing)
        const idFicha = isEdit ? hiddenFichaId.value : data.id_ficha;

        // Final consolidated messsage 
        let mensajeFinal = isEdit ? "Ficha actualizada correctamente." : "Ficha creada correctamente.";
        let errores = [];

        // If there are selected apprentices, add them
        if (estudiantesSeleccionados.length > 0) {
            const estudiantesPayload = {
                id_ficha: idFicha,
                estudiantes: estudiantesSeleccionados.map(e => e.id_usuario)
            }

            const resEstudiantes = await fetch(`${API_URL}?accion=agregarEstudiantes`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(estudiantesPayload)
            })

            const dataEstudiantes = await resEstudiantes.json();
            
            if (dataEstudiantes.success) {
                mensajeFinal += ` ${estudiantesSeleccionados.length} estudiante(s) agregado(s).`;
            } else {
              errores.push(dataEstudiantes.error || "Hubo un problema al agregar aprendices.");
            }
        }

        // If there are selected instructors, add them
        if (instructoresSeleccionados.length > 0) {
            // First assign all instrcutors (without the group leader)
            const instructoresPayload = {
                id_ficha: idFicha,
                instructores: instructoresSeleccionados.map(i => i.id_usuario)
            }

            const resInstructores = await fetch(`${API_URL}?accion=asignarInstructores`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(instructoresPayload)
            })

            const dataInstructores = await resInstructores.json();
            
            if (dataInstructores.success) {
                mensajeFinal += ` ${instructoresSeleccionados.length} instructor(es) asignado(s).`;
                
                // Assign group leader if one exists
                if (jefeGrupoSeleccionado) {
                    // Verify that the group leader is on the list of selected instructors
                    const jefeEstaEnLista = instructoresSeleccionados.some(
                        i => i.id_usuario == jefeGrupoSeleccionado.id_usuario
                    );
                    
                    if (jefeEstaEnLista) {
                        const jefePayload = {
                            id_ficha: idFicha,
                            id_usuario: jefeGrupoSeleccionado.id_usuario
                        };
                        
                        const resJefe = await fetch(`${API_URL}?accion=asignarJefeFicha`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(jefePayload)
                        });
                        
                        const dataJefe = await resJefe.json();
                        if (dataJefe.success) {
                            mensajeFinal += ` Jefe de grupo: ${jefeGrupoSeleccionado.nombre_completo}`;
                        } else {
                            errores.push("Instructores asignados, pero hubo problema asignando el jefe de grupo.");
                        }
                    } else {
                        errores.push("El jefe de grupo seleccionado no está en la lista de instructores.");
                    }
                }
            } else {
                errores.push("Hubo un problema al asignar instructores.");
            }
        }

        // Show final message
        if (errores.length > 0) {
            toastInfo(mensajeFinal + " " + errores.join(" "));
        } else {
            toastSuccess(mensajeFinal);
        }

        await cargarAprendices();
        closeModalFicha();
        await cargarFichas();
    } catch (error) {
        console.error("Error de red al guardar ficha:", error);
        toastError("Ocurrió un error al guardar la ficha (red/servidor).");
    }
});

// ================================
// KEYBOARD SHORTCUTS: CLOSE MODALS WITH ESC
// ================================
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" || e.key === "Esc" || e.keyCode === 27) {
    if (modalFicha && modalFicha.classList.contains("active")) {
      closeModalFicha()
    }

    if (modalVerFicha && modalVerFicha.classList.contains("active")) {
      closeModalVerFicha()
    }
  }
})

// =========================
function renderOpcionesJefeGrupo() {
  if (!listaJefeGrupo) return;

  if (instructoresSeleccionados.length === 0) {
    listaJefeGrupo.innerHTML = `
      <div class="text-center text-muted-foreground py-8">
        <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <p class="text-sm">Primero debe seleccionar instructores en el paso anterior</p>
      </div>
    `;
    return;
  }

  let html = `
    <div class="space-y-3">
      <div class="text-xs font-medium text-gray-500 pb-2 border-b">
        Seleccione un instructor como jefe de grupo:
      </div>
  `;

  instructoresSeleccionados.forEach((instructor, index) => {
    const isSelected = jefeGrupoSeleccionado && jefeGrupoSeleccionado.id_usuario == instructor.id_usuario;
    html += `
      <div class="flex items-center gap-3 p-3 rounded-lg transition-colors cursor-pointer bg-muted hover:bg-secondary-13 ${isSelected ? 'ring-2 ring-custom' : ''}"
           onclick="seleccionarJefeGrupo(${index})">
        <div class="flex-shrink-0">
          <div class="w-10 h-10 rounded-full bg-avatar-primary-39 flex items-center justify-center">
            <span class="font-semibold">${instructor.nombre_completo.charAt(0)}</span>
          </div>
        </div>
        <div class="flex-1">
          <div class="font-medium text-sm">${instructor.nombre_completo}</div>
          <div class="text-xs text-gray-500">${instructor.numero_documento}</div>
          ${instructor.correo ? `<div class="text-xs text-gray-500">${instructor.correo}</div>` : ''}
        </div>
        <div class="flex-shrink-0">
          <input type="radio" name="jefeGrupo" ${isSelected ? 'checked' : ''} 
                 class="w-4 h-4 text-foreground bg-input border-border focus:ring-custom"
                 onclick="seleccionarJefeGrupo(${index})">
        </div>
      </div>
    `;
  });

  html += `
      <div class="pt-3 border-t">
        <div class="text-sm text-gray-600">
          <p><strong>Nota:</strong> Solo puede seleccionar un instructor como jefe de grupo.</p>
        </div>
        ${jefeGrupoSeleccionado ? `
        <div class="mt-2 p-2 rounded-lg border border-border"
            style="background-color: color-mix(in srgb, var(--foreground) 6%, transparent);">
          
          <div class="text-sm font-medium text-foreground">
            Jefe de grupo seleccionado:
          </div>

          <div class="text-sm"
              style="color: color-mix(in srgb, var(--foreground) 70%, transparent);">
            ${jefeGrupoSeleccionado.nombre_completo}
          </div>
        </div>
        ` : ''}
      </div>
    </div>
  `;

  listaJefeGrupo.innerHTML = html;
}

function seleccionarJefeGrupo(index) {
  if (index >= 0 && index < instructoresSeleccionados.length) {
    jefeGrupoSeleccionado = { ...instructoresSeleccionados[index] };
    renderOpcionesJefeGrupo();
  }
}

/*INITIAL LOAD*/

async function inicializar() {
  try {
    console.log("Iniciando carga de datos...");
    
    console.log("Cargando programas...");
    await cargarProgramas();
    
    console.log("Cargando aprendices e instructores...");
    await Promise.all([
        cargarAprendices(),
        cargarInstructores()
    ]);
    
    console.log("Cargando fichas...");
    await cargarFichas();
    
    console.log("Renderizando vista...");
    setVistaTabla();
    
    console.log("Inicialización completada");
  } catch (error) {
    console.error("Error en inicialización:", error);
    toastError("Error al cargar los datos iniciales");
  }
}

// Start
inicializar();

// =========================
// CHAR COUNTERS INITIALIZATION
// =========================
function initCharCounters() {
  const fields = document.querySelectorAll("input[maxlength], textarea[maxlength]")

  fields.forEach((el) => {
    if (!el || el.dataset.noCounter === "1") return

    const max = parseInt(el.getAttribute("maxlength"), 10)
    const min = parseInt(el.getAttribute("minlength"), 10) || 0
    if (!max || max <= 0) return

    const wrapper = el.closest("[data-char-wrap]") || el.parentElement
    if (!wrapper) return

    const key = el.id || el.name || "field"

    let msg = wrapper.querySelector(`[data-char-limit-msg-for="${key}"]`)
    if (!msg) {
      msg = document.createElement("p")
      msg.setAttribute("data-char-limit-msg-for", key)
      msg.className = "mt-1 text-[11px] text-muted-foreground select-none hidden"
      msg.setAttribute("aria-live", "polite")
      wrapper.appendChild(msg)
    }

    const update = () => {
      const len = (el.value || "").length
      
      if (min > 0 && len > 0 && len < min) {
        msg.textContent = `Mínimo ${min} caracteres requeridos`
        msg.classList.remove("hidden")
      } else if (len >= max) {
        msg.textContent = "Limite de caracteres alcanzados"
        msg.classList.remove("hidden")
      } else {
        msg.classList.add("hidden")
      }
    }

    el.addEventListener("input", update)
    update()
  })
}

function onlyDigits(value) {
  return (value || "").replace(/\D+/g, "")
}

function bindOnlyNumbers(inputEl) {
  if (!inputEl) return

  inputEl.setAttribute("inputmode", "numeric")

  inputEl.addEventListener("keydown", function (e) {
    const allowedKeys = [
      "Backspace", "Delete", "Tab", "Escape", "Enter",
      "ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown",
      "Home", "End"
    ]

    if ((e.ctrlKey || e.metaKey) && ["a", "c", "v", "x", "z", "y"].includes((e.key || "").toLowerCase())) {
      return
    }

    if (allowedKeys.includes(e.key)) return

    if (/^\d$/.test(e.key)) return

    e.preventDefault()
  })

  inputEl.addEventListener("input", function () {
    const cleaned = onlyDigits(inputEl.value)
    if (inputEl.value !== cleaned) {
      const pos = inputEl.selectionStart || cleaned.length
      inputEl.value = cleaned
      try { inputEl.setSelectionRange(pos, pos) } catch (e) {}
    }
  })

  inputEl.addEventListener("paste", function (e) {
    e.preventDefault()
    const text = (e.clipboardData || window.clipboardData).getData("text") || ""
    const cleaned = onlyDigits(text)
    const max = parseInt(inputEl.getAttribute("maxlength") || "9999", 10)
    const current = inputEl.value || ""
    const start = inputEl.selectionStart ?? current.length
    const end = inputEl.selectionEnd ?? current.length
    const before = current.slice(0, start)
    const after = current.slice(end)
    let next = (before + cleaned + after)
    if (next.length > max) next = next.slice(0, max)
    inputEl.value = next
    inputEl.dispatchEvent(new Event("input", { bubbles: true }))
  })
}

// Initialize char counters and number validation for ficha number
document.addEventListener("DOMContentLoaded", function () {
  initCharCounters()
  bindOnlyNumbers(inputNumeroFicha)
})

// If already loaded (scripts at bottom), init immediately
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", function () {
    initCharCounters()
    bindOnlyNumbers(inputNumeroFicha)
  })
} else {
  initCharCounters()
  bindOnlyNumbers(inputNumeroFicha)
}

// Expose necessary functions to global functions
window.eliminarEstudiante = eliminarEstudiante;
window.seleccionarTodosVisibles = seleccionarTodosVisibles;
window.toggleEstudiante = toggleEstudiante;
window.seleccionarTodosInstructoresVisibles = seleccionarTodosInstructoresVisibles;
window.toggleInstructor = toggleInstructor;
window.seleccionarJefeGrupo = seleccionarJefeGrupo;