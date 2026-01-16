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

// Estudiantes (aprendices)
let aprendices = [];
let estudiantesSeleccionados = [];

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

// Pasos del formulario
const paso1Ficha = document.getElementById("paso1Ficha");
const paso2Ficha = document.getElementById("paso2Ficha");
const btnIrPaso2 = document.getElementById("btnIrPaso2");
const btnVolverPaso1 = document.getElementById("btnVolverPaso1");

// Estudiantes
const selectEstudiante = document.getElementById("selectEstudiante");
// const btnAgregarEstudiante = document.getElementById("btnAgregarEstudiante");
const listaEstudiantesSeleccionados = document.getElementById("listaEstudiantesSeleccionados");

const modalVerFicha = document.getElementById("modalVerFicha")
const btnCerrarModalVerFicha = document.getElementById("btnCerrarModalVerFicha")
const detalleFichaContent = document.getElementById("detalleFichaContent")

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
// CARGAR APRENDICES
// =========================
async function cargarAprendices() {
  try {
    const res = await fetch(`${API_URL}?accion=aprendices`)
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

    renderOpcionesAprendices()
  } catch (error) {
    console.error("Error al cargar aprendices:", error)
    aprendices = []
    renderOpcionesAprendices()
  }
}

function renderOpcionesAprendices() {
  if (!selectEstudiante) return

  selectEstudiante.innerHTML = ""

  if (!Array.isArray(aprendices) || aprendices.length === 0) {
    selectEstudiante.innerHTML = `<option value="">No hay aprendices disponibles</option>`
    selectEstudiante.disabled = true
    return
  }

  selectEstudiante.disabled = false
  selectEstudiante.innerHTML = `<option value="">Seleccione un estudiante...</option>`

  // Filtrar solo los aprendices que no están seleccionados
  const aprendicesDisponibles = aprendices.filter(a => 
    !estudiantesSeleccionados.some(e => e.id_usuario === a.id_usuario)
  )

  // Agregar opción para seleccionar todos si hay disponibles
  if (aprendicesDisponibles.length > 0) {
    const optAll = document.createElement("option")
    optAll.value = "all"
    optAll.textContent = "Seleccionar todos los aprendices"
    selectEstudiante.appendChild(optAll)
  }

  // No agregar opciones individuales de aprendices
}

function agregarEstudiante() {
  const estudianteId = selectEstudiante.value
  
  if (!estudianteId) {
    toastError("Seleccione un estudiante")
    return
  }

  if (estudianteId === "all") {
    // Agregar todos los aprendices disponibles
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
  
  renderOpcionesAprendices() // Actualizar lista excluyendo los agregados
  renderChecklistAprendices()
}

function seleccionarTodosVisibles() {
  const searchTerm = (selectEstudiante && selectEstudiante.value ? selectEstudiante.value.toLowerCase() : '')
  const aprendicesFiltrados = aprendices.filter(a => 
    (a.nombre_completo && a.nombre_completo.toLowerCase().includes(searchTerm)) ||
    (a.numero_documento && a.numero_documento.toString().toLowerCase().includes(searchTerm)) ||
    (a.correo && a.correo.toLowerCase().includes(searchTerm))
  )

  // Verificar si todos los filtrados están seleccionados
  const todosSeleccionados = aprendicesFiltrados.every(a => 
    estudiantesSeleccionados.some(e => e.id_usuario == a.id_usuario)
  )

  if (todosSeleccionados) {
    // Quitar todos los filtrados de estudiantesSeleccionados
    estudiantesSeleccionados = estudiantesSeleccionados.filter(e => 
      !aprendicesFiltrados.some(a => a.id_usuario == e.id_usuario)
    )
  } else {
    // Agregar los que no están
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

function renderChecklistAprendices() {
  if (!listaEstudiantesSeleccionados) return

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

  // Filtrar por búsqueda
  const searchTerm = (selectEstudiante && selectEstudiante.value ? selectEstudiante.value.toLowerCase() : '')
  const aprendicesFiltrados = aprendices.filter(a => 
    (a.nombre_completo && a.nombre_completo.toLowerCase().includes(searchTerm)) ||
    (a.numero_documento && a.numero_documento.toString().toLowerCase().includes(searchTerm)) ||
    (a.correo && a.correo.toLowerCase().includes(searchTerm))
  )

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
        <div class="flex-1">
          <div class="font-medium text-sm">${aprendiz.nombre_completo}</div>
          ${aprendiz.correo ? `<div class="text-xs text-gray-500">${aprendiz.correo}</div>` : ''}
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
          <a href="#" onclick="seleccionarTodosVisibles(); return false;" class="text-blue-600 hover:text-blue-800 underline text-sm">
            Seleccionar todos los visibles
          </a>
        </div>
      </div>
    </div>
  `

  listaEstudiantesSeleccionados.innerHTML = html
}

function openModalFicha(editFicha = null) {
  selectedFicha = editFicha
  modalFicha.classList.add("active")

  // Limpiar estudiantes seleccionados
  estudiantesSeleccionados = []

  // Mostrar paso 1 y ocultar paso 2
  if (paso1Ficha) paso1Ficha.classList.remove("hidden")
  if (paso2Ficha) paso2Ficha.classList.add("hidden")

  if (editFicha) {
    modalFichaTitulo.textContent = "Editar Ficha"
    modalFichaDescripcion.textContent = "Modifica la información de la ficha"
    hiddenFichaId.value = editFicha.id

    // Upload data to the form
    inputNumeroFicha.value = editFicha.numero_ficha || ""
    inputPrograma.value = editFicha.id_programa || ""
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

    // Cargar estudiantes de esta ficha si está editando
    cargarEstudiantesDeFicha(editFicha.id)

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

  renderOpcionesPrograma()
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
    console.error("Error al cargar estudiantes de la ficha:", error)
  }
}

function closeModalFicha() {
  modalFicha.classList.remove("active")
  selectedFicha = null
  hiddenFichaId.value = ""
  originalEditData = null
  estudiantesSeleccionados = []
  
  // Volver al paso 1
  if (paso1Ficha) paso1Ficha.classList.remove("hidden")
  if (paso2Ficha) paso2Ficha.classList.add("hidden")
}

function openModalVerFicha(ficha) {
  selectedFicha = ficha
  modalVerFicha.classList.add("active")

  const programaNombre = ficha.id_programa
    ? programasMap[String(ficha.id_programa)] || "Sin programa asignado"
    : "Sin programa asignado"

  const nivelNombre = ficha.nivel || "N/A"

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
  `
}

function closeModalVerFicha() {
  modalVerFicha.classList.remove("active")
  selectedFicha = null
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
  attachActionEvents()

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

function attachMenuEvents() {
  document.addEventListener("click", (e) => {
    if (!e.target.closest("[data-menu-trigger]") && !e.target.closest("[data-menu]")) {
      document.querySelectorAll("[data-menu]").forEach((el) => {
        el.classList.add("hidden")
        el.classList.remove("show")
      })
    }
  })

  document.querySelectorAll("[data-menu-trigger]").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.stopPropagation()

      const wrapper = btn.closest(".relative, .inline-block, td, div")
      if (!wrapper) return

      const menu = wrapper.querySelector("[data-menu]")
      if (!menu) return

      const isHidden = menu.classList.contains("hidden")

      document.querySelectorAll("[data-menu]").forEach((el) => {
        el.classList.add("hidden")
        el.classList.remove("show")
      })

      if (isHidden) {
        menu.classList.remove("hidden")
        requestAnimationFrame(() => {
          menu.classList.add("show")
        })
      } else {
        menu.classList.remove("show")
        setTimeout(() => {
          menu.classList.add("hidden")
        }, 150)
      }
    })
  })
}

function attachActionEvents() {
  document.querySelectorAll("[data-menu] [data-action]").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      e.stopPropagation()

      const action = btn.getAttribute("data-action")
      const id = btn.getAttribute("data-id")
      
      if (!id || !action) return

      // Close menu
      const menu = btn.closest("[data-menu]")
      if (menu) {
        menu.classList.add("hidden")
        menu.classList.remove("show")
      }

      // Manage different actions
      if (action === "ver") {
        const ficha = fichas.find((f) => String(f.id) === String(id))
        if (ficha) openModalVerFicha(ficha)
      } else if (action === "editar") {
        const ficha = fichas.find((f) => String(f.id) === String(id))
        if (ficha) openModalFicha(ficha)
      } else if (['activar', 'finalizar', 'cancelar'].includes(action)) {
        // Use the new unified feature
        await cambiarEstadoFicha(id, action)
      }
    })
  })
}

// =========================
// GLOBAL EVENT LISTENERS
// =========================

// Navegación entre pasos
if (btnIrPaso2) {
  btnIrPaso2.addEventListener("click", function() {
    if (validarPaso1()) {
      paso1Ficha.classList.add("hidden")
      paso2Ficha.classList.remove("hidden")
    }
  })
}

if (btnVolverPaso1) {
  btnVolverPaso1.addEventListener("click", function() {
    paso2Ficha.classList.add("hidden")
    paso1Ficha.classList.remove("hidden")
  })
}

// Agregar estudiante
// if (btnAgregarEstudiante) {
//   btnAgregarEstudiante.addEventListener("click", agregarEstudiante)
// }

// Agregar estudiante con Enter
if (selectEstudiante) {
  selectEstudiante.addEventListener("keyup", () => {
    console.log("Filtro activado:", selectEstudiante.value);
    renderChecklistAprendices()
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

formFicha.addEventListener("submit", async (e) => {
    e.preventDefault();

    // Validar paso 1
    if (!validarPaso1()) {
        return
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

        // Si hay estudiantes seleccionados, agregarlos
        if (estudiantesSeleccionados.length > 0) {
            const idFicha = isEdit ? hiddenFichaId.value : data.id_ficha

            if (idFicha) {
                const estudiantesPayload = {
                    id_ficha: idFicha,
                    estudiantes: estudiantesSeleccionados.map(e => e.id_usuario)
                }

                const resEstudiantes = await fetch(`${API_URL}?accion=agregarEstudiantes`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(estudiantesPayload)
                })

                const dataEstudiantes = await resEstudiantes.json()
                
                if (dataEstudiantes.success) {
                    toastSuccess(data.message + ". Estudiantes agregados correctamente.");
                } else {
                    toastInfo(data.message + ". Pero hubo un problema al agregar estudiantes.");
                }
            }
        } else {
            toastSuccess(data.message || (isEdit ? "Ficha actualizada correctamente." : "Ficha creada correctamente."));
        }

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

/*INITIAL LOAD*/

async function inicializar() {
  await Promise.all([
      cargarProgramas(),  // Load programs first
      cargarAprendices(), // Load aprendices
      cargarFichas()      // Load chips in parallel
  ]);
  setVistaTabla();        // Render after both have loaded
}

// Start
inicializar();

// Exponer funciones globales necesarias
window.eliminarEstudiante = eliminarEstudiante;
window.seleccionarTodosVisibles = seleccionarTodosVisibles;