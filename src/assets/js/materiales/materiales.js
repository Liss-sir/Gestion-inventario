/* materiales.js — Integración con backend PHP */

/* =========================
   Variables globales
   ========================= */
let materialsData = []
let filteredData = []
let currentPage = 1
let currentCardPage = 1
const itemsPerPage = 10
const cardsPerPage = 9
let currentView = "table"

// API endpoints
const API_URL = `${window.BASE_URL}src/controllers/material_formacion_controller.php`;

function getMaterialesBaseUrl() {
  if (window.MATERIALES_BASE_URL) return window.MATERIALES_BASE_URL
  if (window.BASE_URL) return window.BASE_URL
  return window.location.origin + "/Gestion-inventario"
}

function getMaterialImageUrl(foto) {
  if (!foto) return ""
  if (foto.startsWith("http")) return foto
  return `${getMaterialesBaseUrl()}/src/uploads/materiales/${foto}`
}

function parsePriceValue(raw) {
  if (raw === undefined || raw === null) return ""
  const cleaned = raw.toString().replace(/[^0-9,.,-]/g, "").trim()
  if (!cleaned) return ""

  const hasComma = cleaned.includes(",")
  const hasDot = cleaned.includes(".")
  let decimalSep = null

  if (hasComma && hasDot) {
    decimalSep = cleaned.lastIndexOf(",") > cleaned.lastIndexOf(".") ? "," : "."
  } else if (hasComma) {
    decimalSep = ","
  } else if (hasDot) {
    decimalSep = "."
  }

  let normalized = cleaned
  if (decimalSep) {
    const parts = normalized.split(decimalSep)
    const intPart = parts.slice(0, -1).join("").replace(/[.,]/g, "")
    const decPart = parts.slice(-1)[0].replace(/[.,]/g, "")
    normalized = decPart ? `${intPart}.${decPart}` : intPart
  } else {
    normalized = cleaned.replace(/[.,]/g, "")
  }

  const num = Number.parseFloat(normalized)
  return Number.isFinite(num) ? num : ""
}

function formatCOPValue(value) {
  const num = parsePriceValue(value)
  if (num === "") return ""
  return new Intl.NumberFormat("es-CO", { style: "currency", currency: "COP", minimumFractionDigits: 0 }).format(num)
}

function attachCurrencyMask(inputId) {
  const input = document.getElementById(inputId)
  if (!input) return

  input.inputMode = "decimal"
  input.placeholder = "$ 0"

  input.addEventListener("focus", () => {
    const raw = input.dataset.rawPrice ?? ""
    input.value = raw
    if (raw) input.setSelectionRange(raw.length, raw.length)
  })

  input.addEventListener("blur", () => {
    const rawTyped = (input.value ?? "").toString().trim()

    // ✅ PRIMERO: no permitir puntos ni comas (ALERTA TIPO INFO)
    if (rawTyped && /[.,]/.test(rawTyped)) {
      input.dataset.rawPrice = ""
      input.value = ""
      showAlert("No se admiten puntos ni comas en el precio. Escríbelo sin separadores (ej: 2000).", "info")
      return
    }

    const num = parsePriceValue(rawTyped)
    if (num === "") {
      input.dataset.rawPrice = ""
      input.value = ""
      return
    }

    // Validar mínimo de 100
    if (num < 100) {
      input.dataset.rawPrice = ""
      input.value = ""
      showAlert("El precio debe ser mínimo 100", "error")
      return
    }

    input.dataset.rawPrice = num.toString()
    input.value = formatCOPValue(num)
  })
}

/* =========================
   API Functions
   ========================= */
async function fetchMaterials() {
  try {
    const response = await fetch(`${API_URL}?accion=listar`)
    if (!response.ok) throw new Error("Error al cargar materiales")
    const data = await response.json()
    materialsData = data.map((material) => ({
      id: Number.parseInt(material.id_material),
      name: material.nombre,
      description: material.descripcion,
      clasificacion: material.clasificacion,
      codigo: material.codigo_inventario,
      unit: material.unidad_medida,
      precio: material.precio,
      foto: material.foto,
      enabled: material.estado === "Disponible",
    }))
    filteredData = [...materialsData]
    if (currentView === "table") {
      renderTable()
    } else {
      renderCards()
    }
  } catch (error) {
    console.error("Error:", error)
    showAlert("Error al cargar los materiales", "error")
  }
}

async function createMaterialAPI(formData) {
  try {
    const response = await fetch(`${API_URL}?accion=crear`, {
      method: "POST",
      body: formData,
    })
    const result = await response.json()
    return result
  } catch (error) {
    console.error("Error:", error)
    return { success: false, message: "Error de conexión" }
  }
}

async function updateMaterialAPI(id, formData) {
  try {
    const response = await fetch(`${API_URL}?accion=actualizar&id=${id}`, {
      method: "POST",
      body: formData,
    })
    const result = await response.json()
    return result
  } catch (error) {
    console.error("Error:", error)
    return { success: false, message: "Error de conexión" }
  }
}

/* ✅ CORREGIDA PARA TU BACKEND:
   - TU CONTROLLER NO TIENE accion=toggleEstado
   - Se debe hacer toggle usando accion=actualizar&id=...
   - El modelo update() exige TODOS los campos (nombre, descripcion, unidad_medida, clasificacion, codigo_inventario, precio, foto, estado)
   - Aquí enviamos TODO desde materialsData (sin subir foto nueva)
*/
async function toggleMaterialStatusAPI(id) {
  const safeReadJson = async (res) => {
    const txt = await res.text()
    try {
      return { ok: res.ok, status: res.status, data: JSON.parse(txt), raw: txt }
    } catch {
      return { ok: res.ok, status: res.status, data: null, raw: txt }
    }
  }

  // ✅ construye una URL REAL del controller (evita undefined, dobles rutas, etc.)
  const resolveControllerUrl = () => {
    const fromWindow = (window.BASE_URL || window.MATERIALES_BASE_URL || "").toString().trim()

    // si BASE_URL existe, asegurar slash final
    if (fromWindow) {
      const base = fromWindow.endsWith("/") ? fromWindow : fromWindow + "/"
      return `${base}src/controllers/material_formacion_controller.php`
    }

    // fallback: inferir desde la URL actual
    const origin = window.location.origin
    const pathname = window.location.pathname // ej: /gestion_inventario/Gestion-inventario/src/view/...
    const idx = pathname.toLowerCase().indexOf("/src/")
    const root = idx !== -1 ? pathname.slice(0, idx + 1) : pathname.replace(/\/[^/]*$/, "/")
    return `${origin}${root}src/controllers/material_formacion_controller.php`
  }

  try {
    const controllerUrl = resolveControllerUrl()

    // ✅ material desde memoria (ya está cargado por fetchMaterials)
    let mat = materialsData.find((m) => Number(m.id) === Number(id))

    // fallback (solo si por alguna razón no está en memoria)
    if (!mat) {
      const getUrl = `${controllerUrl}?accion=obtener&id=${encodeURIComponent(String(id))}`
      const resGet = await fetch(getUrl, { method: "GET", credentials: "include", cache: "no-store" })
      const parsedGet = await safeReadJson(resGet)
      if (parsedGet.data && parsedGet.ok) {
        mat = {
          id: Number.parseInt(parsedGet.data.id_material),
          name: parsedGet.data.nombre,
          description: parsedGet.data.descripcion,
          clasificacion: parsedGet.data.clasificacion,
          codigo: parsedGet.data.codigo_inventario,
          unit: parsedGet.data.unidad_medida,
          precio: parsedGet.data.precio,
          foto: parsedGet.data.foto,
          enabled: parsedGet.data.estado === "Disponible",
        }
      }
    }

    if (!mat) {
      return { status: "error", success: false, message: "No se encontró el material para cambiar estado." }
    }

    const currentEstado = mat.enabled ? "Disponible" : "Agotado"
    const nextEstado = currentEstado === "Disponible" ? "Agotado" : "Disponible"

    // ✅ TU CONTROLLER: case "actualizar" -> $data = $_POST; sendJSON($controller->actualizar($id, $data));
    const postUrl = `${controllerUrl}?accion=actualizar&id=${encodeURIComponent(String(id))}`

    // ✅ enviar TODOS los campos que tu modelo update() espera
    const fd = new FormData()
    fd.append("nombre", (mat.name ?? "").toString())
    fd.append("descripcion", (mat.description ?? "").toString())
    fd.append("clasificacion", (mat.clasificacion ?? "").toString())
    fd.append("codigo_inventario", (mat.codigo ?? "").toString())
    fd.append("unidad_medida", (mat.unit ?? "").toString())

    // precio: tu modelo lo requiere, enviamos num limpio
    const precioNum = parsePriceValue(mat.precio)
    fd.append("precio", precioNum === "" ? "" : String(precioNum))

    // estado nuevo
    fd.append("estado", nextEstado)

    // ❌ NO enviamos foto: tu controller conservará la actual (porque ya hace getById y la mantiene)
    const res = await fetch(postUrl, {
      method: "POST",
      body: fd,
      credentials: "include",
      cache: "no-store",
      redirect: "follow",
    })

    const parsed = await safeReadJson(res)
    if (parsed.data) return parsed.data

    return {
      success: false,
      status: "error",
      message:
        parsed.status === 400
          ? "Bad Request (400). El backend rechazó los parámetros en actualizar."
          : "El servidor devolvió una respuesta inválida (no JSON).",
      http_status: parsed.status,
      raw: parsed.raw,
      debug: { controllerUrl, postUrl, id: String(id), nextEstado },
    }
  } catch (error) {
    console.error("toggleEstado via actualizar error:", error)
    return {
      success: false,
      status: "error",
      message: "No se pudo conectar con el controller (fetch falló).",
      debug: {
        id: String(id),
        API_URL,
        BASE_URL: window.BASE_URL,
        MATERIALES_BASE_URL: window.MATERIALES_BASE_URL,
      },
    }
  }
}

/* =========================
  Helper Functions
  ========================= */

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
    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
      <path d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.59A1.75 1.75 0 0 1 16.768 17H3.232a1.75 1.75 0 0 1-1.492-2.311L8.257 3.1z"/>
      <path d="M11 13H9V9h2zm0 3H9v-2h2z" fill="#fff"/>
    </svg>
  `

  if (type === "success") {
    borderColor = "border-emerald-500"
    textColor = "text-emerald-900"
    titleText = "Éxito"
    iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm-1 15-4-4 1.414-1.414L9 12.172l4.586-4.586L15 9z"/>
      </svg>
    `
  }

  if (type === "error") {
    borderColor = "border-red-500"
    textColor = "text-red-900"
    titleText = "Error"
    iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm3.707 12.293-1.414 1.414L10 11.414l-2.293 2.293-1.414-1.414L8.586 10 6.293 7.707l1.414-1.414L10 8.586l2.293-2.293 1.414 1.414-2.293 2.293 2.293 2.293z"/>
      </svg>
    `
  }

  if (type === "info") {
    borderColor = "border-blue-500"
    textColor = "text-blue-900"
    titleText = "Información"
    iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
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

function showAlert(message, type = "success") {
  showFlowbiteAlert(type, message)
}

function validateMaterialPayload(data, { isEdit = false, id = null } = {}) {
  const nameRegex = /^[A-Za-z0-9ÁÉÍÓÚÜÑñáéíóúüñ\s\-.]{3,80}$/
  const codeRegex = /^\d{3,30}$/

  if (!data.nombre) {
    showAlert("El nombre es obligatorio", "warning")
    document.getElementById(isEdit ? "editNombre" : "nombre")?.focus()
    return false
  }

  if (!nameRegex.test(data.nombre)) {
    showAlert("El nombre solo puede tener letras/números y 3-80 caracteres", "warning")
    document.getElementById(isEdit ? "editNombre" : "nombre")?.focus()
    return false
  }

  if (!data.descripcion || data.descripcion.length < 5) {
    showAlert("La descripción debe tener al menos 5 caracteres", "warning")
    document.getElementById(isEdit ? "editDescripcion" : "descripcion")?.focus()
    return false
  }

  if (!data.clasificacion) {
    showAlert("Seleccione la clasificación", "warning")
    document.getElementById(isEdit ? "editClasificacion" : "clasificacion")?.focus()
    return false
  }

  if (!data.unidad_medida) {
    showAlert("Seleccione la unidad de medida", "warning")
    document.getElementById(isEdit ? "editUnidad" : "unidad")?.focus()
    return false
  }

  if (data.precio === "" || data.precio === null || data.precio === undefined || Number.isNaN(Number(data.precio))) {
    showAlert("Ingrese un precio válido", "warning")
    document.getElementById(isEdit ? "editPrecio" : "precio")?.focus()
    return false
  }

  if (data.clasificacion === "Inventariado") {
    if (!data.codigo_inventario) {
      showAlert("El código es obligatorio para inventariados", "warning")
      document.getElementById(isEdit ? "editCodigo" : "codigo")?.focus()
      return false
    }
    if (!codeRegex.test(data.codigo_inventario)) {
      showAlert("Código inválido: solo números (3-30 dígitos)", "warning")
      document.getElementById(isEdit ? "editCodigo" : "codigo")?.focus()
      return false
    }
  }

  const nombreDuplicado = materialsData.some(
    (m) => m.name.trim().toLowerCase() === data.nombre.trim().toLowerCase() && (!isEdit || m.id !== id),
  )

  if (nombreDuplicado) {
    showAlert("Ya existe un material con ese nombre", "warning")
    return false
  }

  if (data.codigo_inventario) {
    const codigoDuplicado = materialsData.some(
      (m) => m.codigo && m.codigo.toLowerCase() === data.codigo_inventario.toLowerCase() && (!isEdit || m.id !== id),
    )
    if (codigoDuplicado) {
      showAlert("Ya existe un material con ese código", "warning")
      return false
    }
  }

  return true
}

function getDataToRender() {
  return filteredData
}

/* =========================
   Empty States (fichas-style)
   ========================= */
let emptyStateTable = null
let emptySearchTable = null
let emptyStateCards = null
let emptySearchCards = null

function initEmptyStates() {
  const tableView = document.getElementById("tableView")
  const tableWrapper = tableView ? tableView.querySelector(":scope > div") : null

  if (!emptyStateTable && tableWrapper && tableWrapper.parentNode) {
    emptyStateTable = document.createElement("div")
    emptyStateTable.id = "emptyStateMaterialesTable"
    emptyStateTable.className =
      "hidden mt-10 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full"
    emptyStateTable.innerHTML = `
      <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
        <svg class="h-7 w-7 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
          <line x1="12" y1="22.08" x2="12" y2="12"></line>
        </svg>
      </div>
      <h3 class="text-lg font-semibold mt-4">Aún no hay materiales registrados</h3>
      <p class="text-sm text-muted-foreground mt-1 max-w-md">
        Crea un material para empezar a gestionar tu inventario.
      </p>
    `
    tableWrapper.parentNode.insertBefore(emptyStateTable, tableWrapper)
  }

  if (!emptySearchTable && tableWrapper && tableWrapper.parentNode) {
    emptySearchTable = document.createElement("div")
    emptySearchTable.id = "emptySearchMaterialesTable"
    emptySearchTable.className =
      "hidden mt-10 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full"
    emptySearchTable.innerHTML = `
      <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
        <svg class="h-7 w-7 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <circle cx="11" cy="11" r="6" stroke-linecap="round" stroke-linejoin="round"></circle>
          <line x1="16" y1="16" x2="20" y2="20" stroke-linecap="round" stroke-linejoin="round"></line>
        </svg>
      </div>
      <h3 class="text-lg font-semibold mt-4">No se encontraron resultados</h3>
      <p class="text-sm text-muted-foreground mt-1 max-w-md">
        No se encontraron materiales que coincidan con los criterios de búsqueda actuales.
      </p>
    `
    tableWrapper.parentNode.insertBefore(emptySearchTable, tableWrapper)
  }

  const cardView = document.getElementById("cardView")
  const cardsContainerEl = document.getElementById("cardsContainer")

  if (!emptyStateCards && cardsContainerEl && cardView) {
    emptyStateCards = document.createElement("div")
    emptyStateCards.id = "emptyStateMaterialesCards"
    emptyStateCards.className =
      "hidden mt-10 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full"
    emptyStateCards.innerHTML = `
      <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
        <svg class="h-7 w-7 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
          <line x1="12" y1="22.08" x2="12" y2="12"></line>
        </svg>
      </div>
      <h3 class="text-lg font-semibold mt-4">Aún no hay materiales registrados</h3>
      <p class="text-sm text-muted-foreground mt-1 max-w-md">
        Crea un material para empezar a gestionar tu inventario.
      </p>
    `
    cardView.insertBefore(emptyStateCards, cardsContainerEl)
  }

  if (!emptySearchCards && cardsContainerEl && cardView) {
    emptySearchCards = document.createElement("div")
    emptySearchCards.id = "emptySearchMaterialesCards"
    emptySearchCards.className =
      "hidden mt-10 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full"
    emptySearchCards.innerHTML = `
      <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
        <svg class="h-7 w-7 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <circle cx="11" cy="11" r="6" stroke-linecap="round" stroke-linejoin="round"></circle>
          <line x1="16" y1="16" x2="20" y2="20" stroke-linecap="round" stroke-linejoin="round"></line>
        </svg>
      </div>
      <h3 class="text-lg font-semibold mt-4">No se encontraron resultados</h3>
      <p class="text-sm text-muted-foreground mt-1 max-w-md">
        No se encontraron materiales que coincidan con los criterios de búsqueda actuales.
      </p>
    `
    cardView.insertBefore(emptySearchCards, cardsContainerEl)
  }
}

/* =========================
   Íconos SVG
   ========================= */
const icons = {
  eye: '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
  edit: '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>',
  check: '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>',
  x: '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
  trash:
    '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>',
  menu: '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.5"></circle><circle cx="12" cy="12" r="1.5"></circle><circle cx="19" cy="12" r="1.5"></circle></svg>',
  package:
    '<svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#007832" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
  email:
    '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4z"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 6l8 6 8-6"/></svg>',
  ruler:
    '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><rect x="2" y="7" width="20" height="10" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 12h.01M10 12h.01M14 12h.01M18 12h.01"/></svg>',
}

/* =================================================
   RENDER TABLE / CARDS
   ================================================= */
function renderTable() {
  const dataToRender = getDataToRender()
  const searchTerm = document.getElementById("inputBuscar")?.value.trim()
  const filterValue = document.getElementById("selectFiltroRol")?.value
  const start = (currentPage - 1) * itemsPerPage
  const end = start + itemsPerPage
  const paginatedData = dataToRender.slice(start, end)

  const tableBody = document.getElementById("tableBody")
  tableBody.innerHTML = ""
  const tableView = document.getElementById("tableView")
  let tableWrapper = tableView ? tableView.querySelector(":scope > div") : null
  const tableEl = tableView ? tableView.querySelector(":scope > div > table") : null
  if (!tableWrapper && tableEl) {
    tableWrapper = tableEl.parentElement
  }
  const paginationEl = document.getElementById("pagination")

  const hasAnyMaterial = materialsData.length > 0

  if (!dataToRender.length) {
    if (tableWrapper) tableWrapper.classList.add("hidden")
    if (tableEl) tableEl.classList.add("hidden")
    if (paginationEl) {
      paginationEl.innerHTML = ""
      paginationEl.parentElement?.classList.add("hidden")
    }

    const hasFilters = Boolean(searchTerm || filterValue)
    if (!hasAnyMaterial) {
      // No hay datos en absoluto, mostrar el estado vacío base aunque haya filtros
      if (emptyStateTable) emptyStateTable.classList.remove("hidden")
      if (emptySearchTable) emptySearchTable.classList.add("hidden")
    } else if (hasFilters) {
      if (emptySearchTable) emptySearchTable.classList.remove("hidden")
      if (emptyStateTable) emptyStateTable.classList.add("hidden")
    } else {
      if (emptyStateTable) emptyStateTable.classList.remove("hidden")
      if (emptySearchTable) emptySearchTable.classList.add("hidden")
    }

    return
  } else {
    if (tableWrapper) tableWrapper.classList.remove("hidden")
    if (tableEl) tableEl.classList.remove("hidden")
    if (emptyStateTable) emptyStateTable.classList.add("hidden")
    if (emptySearchTable) emptySearchTable.classList.add("hidden")
    if (paginationEl) paginationEl.parentElement?.classList.remove("hidden")
  }

  paginatedData.forEach((material) => {
    const statusText = material.enabled ? "Disponible" : "Agotado"
    const codigoDisplay = material.codigo || (material.clasificacion === "Consumible" ? "N/C" : "-")

    const row = document.createElement("tr")            
    row.className = "hover:bg-muted/40"
    row.dataset.materialId = material.id

    row.innerHTML = `
      <td class="px-4 py-3 align-middle text-sm font-medium">${codigoDisplay}</td>
      <td class="px-4 py-3 align-middle">
        <div class="flex items-center gap-3">
          <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-avatar-secondary-39 text-secondary flex-shrink-0">
            <i data-lucide="box" class="lucide lucide-box h-4 w-4 text-[#007832]"></i>
          </div>
          <div>
            <p class="font-medium text-sm">${material.name}</p>
          </div>
        </div>
      </td>
      <td class="px-4 py-3 align-middle text-sm max-w-xs truncate" title="${material.description}">${material.description}</td>
      <td class="px-4 py-3 align-middle">
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-clasificacion-base ${material.clasificacion === "Inventariado" ? "badge-clasificacion-inventariado" : "badge-clasificacion-consumible"}">
          ${material.clasificacion}
        </span>
      </td>
      <td class="px-4 py-3 align-middle text-sm">${material.unit}</td>
      <td class="px-4 py-3 align-middle">
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full status-badge ${material.enabled ? "active" : "inactive"}">
          ${statusText}
        </span>
      </td>
      <td class="px-4 py-3 align-middle text-right">
        <div class="relative inline-block text-left">
          <button 
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-muted text-slate-800"
            onclick="toggleMenu(event)"
          >
            ${icons.menu}
          </button>
          
          <div 
            class="dropdown-menu hidden absolute right-0 mt-2 w-48 rounded-xl border border-border bg-popover shadow-md py-1 z-50"
          >
            <button 
              type="button"
              class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted"
              onclick="openDetailsModal(${material.id})"
            >
              ${icons.eye}
              <span class="ml-2">Ver detalles</span>
            </button>
            <button 
              type="button"
              class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted"
              onclick="openEditModal(${material.id})"
            >
              ${icons.edit}
              <span class="ml-2">Editar</span>
            </button>
            <hr class="border-border my-1">
            <button 
              type="button"
              class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted rounded-b-xl"
              onclick="toggleMaterialStatus(${material.id}, event)"
            >
              ${!material.enabled ? icons.check : icons.x}
              <span class="ml-2">${!material.enabled ? "Disponible" : "Agotado"}</span>
            </button>
          </div>
        </div>
      </td>
    `

    tableBody.appendChild(row)
  })

  renderPagination()
  if (window.lucide && typeof lucide.createIcons === "function") {
    lucide.createIcons(tableBody)
  }
}

function renderCards() {
  const dataToRender = getDataToRender()
  const searchTerm = document.getElementById("inputBuscar")?.value.trim()
  const filterValue = document.getElementById("selectFiltroRol")?.value
  const start = (currentCardPage - 1) * cardsPerPage
  const end = start + cardsPerPage
  const paginatedData = dataToRender.slice(start, end)

  const cardsContainer = document.getElementById("cardsContainer")
  cardsContainer.innerHTML = ""
  const cardPaginationEl = document.getElementById("cardPagination")

  const hasAnyMaterial = materialsData.length > 0

  if (!dataToRender.length) {
    cardsContainer.classList.add("hidden")
    if (cardPaginationEl) {
      cardPaginationEl.innerHTML = ""
      cardPaginationEl.parentElement?.classList.add("hidden")
    }

    const hasFilters = Boolean(searchTerm || filterValue)
    if (!hasAnyMaterial) {
      // No hay datos en absoluto, mostrar el estado vacío base aunque haya filtros
      if (emptyStateCards) emptyStateCards.classList.remove("hidden")
      if (emptySearchCards) emptySearchCards.classList.add("hidden")
    } else if (hasFilters) {
      if (emptySearchCards) emptySearchCards.classList.remove("hidden")
      if (emptyStateCards) emptyStateCards.classList.add("hidden")
    } else {
      if (emptyStateCards) emptyStateCards.classList.remove("hidden")
      if (emptySearchCards) emptySearchCards.classList.add("hidden")
    }
    return
  } else {
    cardsContainer.classList.remove("hidden")
    if (emptyStateCards) emptyStateCards.classList.add("hidden")
    if (emptySearchCards) emptySearchCards.classList.add("hidden")
    if (cardPaginationEl) cardPaginationEl.parentElement?.classList.remove("hidden")
  }

  paginatedData.forEach((material) => {
    const statusText = material.enabled ? "Disponible" : "Agotado"
    const codigoDisplay = material.codigo || (material.clasificacion === "Consumible" ? "N/C" : "Sin código")

    const card = document.createElement("div")
    card.className = "rounded-2xl border border-border bg-card p-2.5 shadow-sm flex flex-col gap-1.5"
    card.dataset.materialId = material.id

    card.innerHTML = `
      <div class="flex items-start justify-between gap-2">
        <div class="flex items-center gap-2">
          <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-avatar-secondary-39 text-secondary flex-shrink-0">
            ${icons.package}
          </div>
          <div class="space-y-0.5">
            <p class="font-semibold text-xs sm:text-sm leading-snug">${material.name}</p>
            <p class="text-[11px] sm:text-xs text-muted-foreground">${codigoDisplay}</p>
          </div>
        </div>

        <div class="relative inline-block text-left">
          <button
            type="button"
            class="inline-flex h-6 w-6 items-center justify-center rounded-md hover:bg-muted text-slate-800"
            onclick="toggleCardMenu(event)"
          >
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
              <circle cx="5" cy="12" r="1.5"></circle>
              <circle cx="12" cy="12" r="1.5"></circle>
              <circle cx="19" cy="12" r="1.5"></circle>
            </svg>
          </button>
          <div
            class="dropdown-menu hidden absolute right-0 mt-2 w-40 rounded-xl border border-border bg-popover shadow-md py-1 z-50"
          >
            <button
              type="button"
              class="flex w-full items-center px-3 py-2 text-xs text-slate-700 hover:bg-muted"
              onclick="event.stopPropagation(); openDetailsModal(${material.id})"
            >
              ${icons.eye}
              <span class="ml-2">Ver detalles</span>
            </button>
            <button
              type="button"
              class="flex w-full items-center px-3 py-2 text-xs text-slate-700 hover:bg-muted"
              onclick="event.stopPropagation(); openEditModal(${material.id})"
            >
              ${icons.edit}
              <span class="ml-2">Editar</span>
            </button>
            <hr class="border-border my-1">
            <button
              type="button"
              class="flex w-full items-center px-3 py-2 text-xs text-slate-700 hover:bg-muted rounded-b-xl"
              onclick="event.stopPropagation(); toggleMaterialStatus(${material.id}, event)"
            >
              ${material.enabled ? icons.x : icons.check}
              <span class="ml-2">${material.enabled ? "Agotado" : "Disponible"}</span>
            </button>
          </div>
        </div>
      </div>

      <div class="space-y-0.5 text-[11px] sm:text-xs text-muted-foreground">
        <div class="flex items-center gap-2">
          ${icons.email}
          <span class="truncate">${material.description}</span>
        </div>
        <div class="flex items-center gap-2">
          ${icons.ruler}
          <span>${material.unit}</span>
        </div>
      </div>

      <div class="flex items-center justify-between">
        <div class="flex flex-wrap gap-1.5">
          <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium badge-clasificacion-base ${material.clasificacion === "Inventariado" ? "badge-clasificacion-inventariado" : "badge-clasificacion-consumible"}">
            ${material.clasificacion}
          </span>
          <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium status-badge ${material.enabled ? "active" : "inactive"}">
            ${statusText}
          </span>
        </div>
      </div>

      <hr class="border-border my-0.5" />

      <div class="flex justify-end">
        <button
          type="button"
          class="switch-siga ${material.enabled ? "on" : "off"}"
          onclick="toggleMaterialStatus(${material.id}, event)"
        >
          <span class="slider-siga"></span>
        </button>
      </div>
    `

    cardsContainer.appendChild(card)
  })

  renderCardPagination()
}

/* =========================
   PAGINATION
   ========================= */
function renderPagination() {
  const dataToRender = getDataToRender()
  const totalPages = Math.ceil(dataToRender.length / itemsPerPage)
  const paginationContainer = document.getElementById("pagination")
  paginationContainer.innerHTML = ""

  if (totalPages <= 1) return

  const prevBtn = document.createElement("button")
  prevBtn.textContent = "Anterior"
  prevBtn.disabled = currentPage === 1
  prevBtn.onclick = () => {
    if (currentPage > 1) {
      currentPage--
      renderTable()
    }
  }
  paginationContainer.appendChild(prevBtn)

  for (let i = 1; i <= totalPages; i++) {
    const pageBtn = document.createElement("button")
    pageBtn.textContent = i
    if (i === currentPage) pageBtn.classList.add("active")
    pageBtn.onclick = () => {
      currentPage = i
      renderTable()
    }
    paginationContainer.appendChild(pageBtn)
  }

  const nextBtn = document.createElement("button")
  nextBtn.textContent = "Siguiente"
  nextBtn.disabled = currentPage === totalPages
  nextBtn.onclick = () => {
    if (currentPage < totalPages) {
      currentPage++
      renderTable()
    }
  }
  paginationContainer.appendChild(nextBtn)
}

function renderCardPagination() {
  const dataToRender = getDataToRender()
  const totalPages = Math.ceil(dataToRender.length / cardsPerPage)
  const paginationContainer = document.getElementById("cardPagination")
  paginationContainer.innerHTML = ""

  if (totalPages <= 1) return

  const prevBtn = document.createElement("button")
  prevBtn.textContent = "Anterior"
  prevBtn.disabled = currentCardPage === 1
  prevBtn.onclick = () => {
    if (currentCardPage > 1) {
      currentCardPage--
      renderCards()
    }
  }
  paginationContainer.appendChild(prevBtn)

  for (let i = 1; i <= totalPages; i++) {
    const pageBtn = document.createElement("button")
    pageBtn.textContent = i
    if (i === currentCardPage) pageBtn.classList.add("active")
    pageBtn.onclick = () => {
      currentCardPage = i
      renderCards()
    }
    paginationContainer.appendChild(pageBtn)
  }

  const nextBtn = document.createElement("button")
  nextBtn.textContent = "Siguiente"
  nextBtn.disabled = currentCardPage === totalPages
  nextBtn.onclick = () => {
    if (currentCardPage < totalPages) {
      currentCardPage++
      renderCards()
    }
  }
  paginationContainer.appendChild(nextBtn)
}

/* =========================
   MODALS
   ========================= */
function openCreateModal() {
  document.getElementById("createModal").classList.add("active")
}

function closeCreateModal() {
  document.getElementById("createModal").classList.remove("active")
  document.getElementById("nombre").value = ""
  document.getElementById("descripcion").value = ""
  document.getElementById("clasificacion").value = ""
  document.getElementById("codigo").value = ""
  document.getElementById("unidad").value = ""
  document.getElementById("precio").value = ""
  const precioInput = document.getElementById("precio")
  if (precioInput) precioInput.dataset.rawPrice = ""
  document.getElementById("imagen").value = ""
  document.getElementById("codigoContainer").style.display = "none"

  const previewImagen = document.getElementById("previewImagen")
  if (previewImagen) {
    previewImagen.src = ""
    previewImagen.classList.add("hidden")
  }
}

function openDetailsModal(id) {
  const material = materialsData.find((m) => m.id === id)
  if (!material) return

  const estadoBadgeClass = material.enabled ? "badge-estado-activo" : "badge-estado-inactivo"
  const clasificacionBadgeClass =
    material.clasificacion === "Inventariado" ? "badge-clasificacion-inventariado" : "badge-clasificacion-consumible"
  const fotoUrl = getMaterialImageUrl(material.foto)

  const detailsContent = document.getElementById("detailsContent")
  detailsContent.innerHTML = `
    <div class="flex items-start gap-4 pb-4 border-b border-border">
      <div class="flex h-20 w-20 items-center justify-center rounded-xl overflow-hidden bg-avatar-secondary-39 text-secondary flex-shrink-0">
        ${
          fotoUrl
            ? `<img src="${fotoUrl}" alt="${material.name}" class="h-full w-full object-cover" />`
            : `<i data-lucide="box" class="lucide lucide-box h-8 w-8 text-[#007832]"></i>`
        }
      </div>
      <div class="flex-1">
        <h3 class="font-semibold text-lg">${material.name}</h3>
        <div class="flex items-center gap-2 mt-1">
          <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-clasificacion-base ${clasificacionBadgeClass}">
            ${material.clasificacion}
          </span>
          <span class="badge-estado-base ${estadoBadgeClass}">
            ${material.enabled ? "Disponible" : "Agotado"}
          </span>
        </div>
      </div>
    </div>
    
    <div class="grid gap-3 text-sm">
      <div class="grid grid-cols-3 gap-2">
        <span class="text-muted-foreground">Código:</span>
        <span class="col-span-2 font-medium">${material.codigo || (material.clasificacion === "Consumible" ? "N/C" : "Sin código")}</span>
      </div>
      <div class="grid grid-cols-3 gap-2">
        <span class="text-muted-foreground">Descripción:</span>
        <span class="col-span-2">${material.description}</span>
      </div>
      <div class="grid grid-cols-3 gap-2">
        <span class="text-muted-foreground">Unidad:</span>
        <span class="col-span-2">${material.unit}</span>
      </div>
      <div class="grid grid-cols-3 gap-2">
        <span class="text-muted-foreground">Precio:</span>
        <span class="col-span-2 font-medium">${material.precio ? formatCOPValue(material.precio) : "Sin precio"}</span>
      </div>
    </div>
  `

  document.getElementById("detailsModal").classList.add("active")
  if (window.lucide && typeof lucide.createIcons === "function") {
    lucide.createIcons(detailsContent)
  }
}

function closeDetailsModal() {
  document.getElementById("detailsModal").classList.remove("active")
}

function openEditModal(id) {
  const material = materialsData.find((m) => m.id === id)
  if (!material) return

  document.getElementById("editId").value = material.id
  document.getElementById("editNombre").value = material.name
  document.getElementById("editDescripcion").value = material.description
  document.getElementById("editClasificacion").value = material.clasificacion
  document.getElementById("editCodigo").value = material.codigo || ""
  document.getElementById("editUnidad").value = material.unit
  const editPrecioInput = document.getElementById("editPrecio")
  if (editPrecioInput) {
    editPrecioInput.dataset.rawPrice = material.precio ?? ""
    editPrecioInput.value = material.precio ? formatCOPValue(material.precio) : ""
  }

  const editImagenInput = document.getElementById("editImagen")
  if (editImagenInput) editImagenInput.value = ""

  const editPreviewImagen = document.getElementById("editPreviewImagen")
  if (editPreviewImagen) {
    const fotoUrl = getMaterialImageUrl(material.foto)
    if (fotoUrl) {
      editPreviewImagen.src = fotoUrl
      editPreviewImagen.classList.remove("hidden")
    } else {
      editPreviewImagen.src = ""
      editPreviewImagen.classList.add("hidden")
    }
  }

  toggleEditCodigoField()
  document.getElementById("editModal").classList.add("active")
}

function closeEditModal() {
  document.getElementById("editModal").classList.remove("active")
  const editImagenInput = document.getElementById("editImagen")
  if (editImagenInput) editImagenInput.value = ""
  const editPreviewImagen = document.getElementById("editPreviewImagen")
  if (editPreviewImagen) {
    editPreviewImagen.src = ""
    editPreviewImagen.classList.add("hidden")
  }
  const editPrecioInput = document.getElementById("editPrecio")
  if (editPrecioInput) {
    editPrecioInput.value = ""
    editPrecioInput.dataset.rawPrice = ""
  }
}

function toggleCodigoField() {
  const clasificacion = document.getElementById("clasificacion").value
  const codigoContainer = document.getElementById("codigoContainer")
  const codigoHelpText = document.getElementById("codigoHelpText")
  const codigoInput = document.getElementById("codigo")
  const precioCodigoGrid = document.getElementById("precioCodigoGrid")

  if (clasificacion === "Inventariado") {
    codigoContainer.style.display = "block"
    codigoHelpText.style.display = "block"
    precioCodigoGrid.style.gridTemplateColumns = "1fr 1fr"
  } else {
    codigoContainer.style.display = "none"
    codigoHelpText.style.display = "none"
    codigoInput.value = ""
    precioCodigoGrid.style.gridTemplateColumns = "1fr"
  }
}

/* ✅ CORREGIDA: no rompe si falta el grid o algún id (evita classList of null) */
function toggleEditCodigoField() {
  const clasificacionEl = document.getElementById("editClasificacion")
  const codigoContainer = document.getElementById("editCodigoContainer")
  const codigoInput = document.getElementById("editCodigo")
  const editPrecioCodigoGrid = document.getElementById("editPrecioCodigoGrid")

  if (!clasificacionEl || !codigoContainer || !codigoInput) return

  const clasificacion = clasificacionEl.value

  if (clasificacion === "Inventariado") {
    codigoContainer.style.display = "block"

    if (editPrecioCodigoGrid && editPrecioCodigoGrid.classList) {
      editPrecioCodigoGrid.classList.remove("grid-cols-1")
      editPrecioCodigoGrid.classList.add("grid-cols-2")
    }
  } else {
    codigoContainer.style.display = "none"
    codigoInput.value = ""

    if (editPrecioCodigoGrid && editPrecioCodigoGrid.classList) {
      editPrecioCodigoGrid.classList.remove("grid-cols-2")
      editPrecioCodigoGrid.classList.add("grid-cols-1")
    }
  }
}

/* =========================
   CRUD OPERATIONS
   ========================= */
async function createMaterial() {
  const precioEl = document.getElementById("precio")
  const precioTyped = (precioEl?.value ?? "").toString().trim()
  const precioRaw = (precioEl?.dataset.rawPrice ?? "").toString().trim()

  if (!precioRaw && precioTyped && /[.,]/.test(precioTyped)) {
    showAlert("No se admiten puntos ni comas en el precio. Escríbelo sin separadores (ej: 2000).", "error")
    precioEl?.focus()
    return
  }

  const materialData = {
    nombre: document.getElementById("nombre").value.trim(),
    descripcion: document.getElementById("descripcion").value.trim(),
    clasificacion: document.getElementById("clasificacion").value,
    codigo_inventario: document.getElementById("codigo").value.trim() || null,
    unidad_medida: document.getElementById("unidad").value,
    precio: parsePriceValue(precioRaw || precioTyped),
  }

  if (!validateMaterialPayload(materialData)) return

  const imagenInput = document.getElementById("imagen")
  if (!imagenInput || imagenInput.files.length === 0) {
    showAlert("Debes seleccionar una imagen del material", "error")
    document.getElementById("dropzoneImagen")?.scrollIntoView({ behavior: "smooth", block: "center" })
    return
  }

  const formData = new FormData()
  formData.append("nombre", materialData.nombre)
  formData.append("descripcion", materialData.descripcion)
  formData.append("clasificacion", materialData.clasificacion)
  formData.append("codigo_inventario", materialData.codigo_inventario || "")
  formData.append("unidad_medida", materialData.unidad_medida)
  formData.append("precio", materialData.precio)
  formData.append("foto", imagenInput.files[0])

  const result = await createMaterialAPI(formData)

  if (result.status === "success") {
    showAlert(result.message || "Material creado exitosamente", "success")
    closeCreateModal()
    await fetchMaterials()
  } else {
    showAlert(result.message || "Error al crear el material", "error")
  }
}

async function updateMaterial() {
  const id = Number.parseInt(document.getElementById("editId").value)

  const editPrecioEl = document.getElementById("editPrecio")
  const precioTyped = (editPrecioEl?.value ?? "").toString().trim()
  const precioRaw = (editPrecioEl?.dataset.rawPrice ?? "").toString().trim()

  if (!precioRaw && precioTyped && /[.,]/.test(precioTyped)) {
    showAlert("No se admiten puntos ni comas en el precio. Escríbelo sin separadores (ej: 2000).", "error")
    editPrecioEl?.focus()
    return
  }

  const materialData = {
    nombre: document.getElementById("editNombre").value.trim(),
    descripcion: document.getElementById("editDescripcion").value.trim(),
    clasificacion: document.getElementById("editClasificacion").value,
    codigo_inventario: document.getElementById("editCodigo").value.trim() || null,
    unidad_medida: document.getElementById("editUnidad").value,
    precio: parsePriceValue(precioRaw || precioTyped),
    estado: materialsData.find((m) => m.id === id)?.enabled ? "Disponible" : "Agotado",
  }

  const editImagenInput = document.getElementById("editImagen")
  const hasNewPhoto = editImagenInput && editImagenInput.files.length > 0
  const original = materialsData.find((m) => m.id === id)

  if (!hasNewPhoto && (!original || !original.foto)) {
    showAlert("Debes seleccionar una imagen del material", "error")
    document.getElementById("editDropzoneImagen")?.scrollIntoView({ behavior: "smooth", block: "center" })
    return
  }

  if (original) {
    const norm = (v) => (v ?? "").toString().trim()
    const samePrecio = Number(parsePriceValue(original.precio)) === Number(parsePriceValue(materialData.precio))

    const noChanges =
      norm(original.name) === norm(materialData.nombre) &&
      norm(original.description) === norm(materialData.descripcion) &&
      norm(original.clasificacion) === norm(materialData.clasificacion) &&
      norm(original.codigo) === norm(materialData.codigo_inventario) &&
      norm(original.unit) === norm(materialData.unidad_medida) &&
      samePrecio &&
      !hasNewPhoto

    if (noChanges) {
      showAlert("No realizaste cambios. Usa Cancelar o cierra el modal.", "warning")
      return
    }
  }

  if (!validateMaterialPayload(materialData, { isEdit: true, id })) return

  const formData = new FormData()
  formData.append("nombre", materialData.nombre)
  formData.append("descripcion", materialData.descripcion)
  formData.append("clasificacion", materialData.clasificacion)
  formData.append("codigo_inventario", materialData.codigo_inventario || "")
  formData.append("unidad_medida", materialData.unidad_medida)
  formData.append("precio", materialData.precio || "")
  formData.append("estado", materialData.estado)

  if (editImagenInput && editImagenInput.files.length > 0) {
    formData.append("foto", editImagenInput.files[0])
  }

  const result = await updateMaterialAPI(id, formData)

  if (result.status === "success") {
    showAlert(result.message || "Material actualizado exitosamente", "success")
    closeEditModal()
    await fetchMaterials()
  } else {
    showAlert(result.message || "Error al actualizar el material", "error")
  }
}

/* ✅ CORREGIDA: ahora usa actualizar (porque tu backend no tiene toggleEstado) */
async function toggleMaterialStatus(id, event) {
  event?.stopPropagation()

  const result = await toggleMaterialStatusAPI(id)
  const ok = result?.success === true || result?.status === "success"

  if (ok) {
    showAlert(result.message || "Estado actualizado", "success")
    await fetchMaterials()
  } else {
    showAlert(result?.message || "Error al cambiar el estado", "error")
    // console.log("toggleEstado RAW:", result?.raw)
  }
}

/* =========================
   MENU TOGGLES - Sistema simplificado basado en el módulo de usuarios
   ========================= */

function toggleMenu(event) {
  event.stopPropagation()
  const button = event.currentTarget
  const dropdown = button.nextElementSibling

  document.querySelectorAll(".dropdown-menu").forEach((menu) => {
    if (menu !== dropdown) menu.classList.add("hidden")
  })

  dropdown.classList.toggle("hidden")
  if (!dropdown.classList.contains("hidden")) {
    dropdown.classList.add("show")
  } else {
    dropdown.classList.remove("show")
  }
}

function toggleCardMenu(event) {
  event.stopPropagation()
  const button = event.currentTarget
  const dropdown = button.nextElementSibling

  document.querySelectorAll(".dropdown-menu").forEach((menu) => {
    if (menu !== dropdown) menu.classList.add("hidden")
  })

  dropdown.classList.toggle("hidden")
  if (!dropdown.classList.contains("hidden")) {
    dropdown.classList.add("show")
  } else {
    dropdown.classList.remove("show")
  }
}

document.addEventListener("click", (event) => {
  if (
    !event.target.closest(".dropdown-menu") &&
    !event.target.closest("button[onclick*='toggleMenu']") &&
    !event.target.closest("button[onclick*='toggleCardMenu']")
  ) {
    document.querySelectorAll(".dropdown-menu").forEach((menu) => {
      menu.classList.add("hidden")
      menu.classList.remove("show")
    })
  }
})

/* =========================
   VIEW TOGGLES
   ========================= */
document.getElementById("tableViewBtn")?.addEventListener("click", () => {
  currentView = "table"
  document.getElementById("tableView").classList.remove("hidden")
  document.getElementById("cardView").classList.add("hidden")

  document.getElementById("tableViewBtn").classList.add("bg-muted", "text-foreground")
  document.getElementById("tableViewBtn").classList.remove("text-muted-foreground")

  document.getElementById("cardViewBtn").classList.remove("bg-muted", "text-foreground")
  document.getElementById("cardViewBtn").classList.add("text-muted-foreground")

  renderTable()
})

document.getElementById("cardViewBtn")?.addEventListener("click", () => {
  currentView = "card"
  document.getElementById("cardView").classList.remove("hidden")
  document.getElementById("tableView").classList.add("hidden")

  document.getElementById("cardViewBtn").classList.add("bg-muted", "text-foreground")
  document.getElementById("cardViewBtn").classList.remove("text-muted-foreground")

  document.getElementById("tableViewBtn").classList.remove("bg-muted", "text-foreground")
  document.getElementById("tableViewBtn").classList.add("text-muted-foreground")

  renderCards()
})

/* =========================
   SEARCH & FILTER
   ========================= */
function applyFilters() {
  const searchTerm = document.getElementById("inputBuscar").value.toLowerCase()
  const filterClasificacion = document.getElementById("selectFiltroRol").value

  filteredData = materialsData.filter((material) => {
    const matchesSearch =
      material.name.toLowerCase().includes(searchTerm) || material.description.toLowerCase().includes(searchTerm)

    const matchesClasificacion = !filterClasificacion || material.clasificacion === filterClasificacion

    return matchesSearch && matchesClasificacion
  })

  currentPage = 1
  currentCardPage = 1

  if (currentView === "table") {
    renderTable()
  } else {
    renderCards()
  }
}

/* =========================
   INIT
   ========================= */
document.addEventListener("DOMContentLoaded", async () => {
  initEmptyStates()
  await fetchMaterials()
  setupEventListeners()
  // await cargarProgramas()
})

function setupEventListeners() {
  document.getElementById("inputBuscar")?.addEventListener("input", applyFilters)
  document.getElementById("selectFiltroRol")?.addEventListener("change", applyFilters)

  attachCurrencyMask("precio")
  attachCurrencyMask("editPrecio")

  const imagenInput = document.getElementById("imagen")
  const dropzoneImagen = document.getElementById("dropzoneImagen")
  const previewImagen = document.getElementById("previewImagen")

  if (imagenInput && dropzoneImagen && previewImagen) {
    dropzoneImagen.addEventListener("click", () => {
      imagenInput.click()
    })

    dropzoneImagen.addEventListener("dragover", (e) => {
      e.preventDefault()
      dropzoneImagen.classList.add("bg-muted")
    })

    dropzoneImagen.addEventListener("dragleave", () => {
      dropzoneImagen.classList.remove("bg-muted")
    })

    dropzoneImagen.addEventListener("drop", (e) => {
      e.preventDefault()
      dropzoneImagen.classList.remove("bg-muted")

      const files = e.dataTransfer.files
      if (files.length > 0) {
        if (validarImagen(files[0])) {
          imagenInput.files = files
          mostrarVistaPrevia(files[0])
        }
      }
    })

    imagenInput.addEventListener("change", (e) => {
      if (e.target.files.length > 0) {
        if (validarImagen(e.target.files[0])) {
          mostrarVistaPrevia(e.target.files[0])
        } else {
          e.target.value = ""
        }
      }
    })
  }

  const editImagenInput = document.getElementById("editImagen")
  const editDropzoneImagen = document.getElementById("editDropzoneImagen")
  const editPreviewImagen = document.getElementById("editPreviewImagen")

  if (editImagenInput && editDropzoneImagen && editPreviewImagen) {
    editDropzoneImagen.addEventListener("click", () => {
      editImagenInput.click()
    })

    editDropzoneImagen.addEventListener("dragover", (e) => {
      e.preventDefault()
      editDropzoneImagen.classList.add("bg-muted")
    })

    editDropzoneImagen.addEventListener("dragleave", () => {
      editDropzoneImagen.classList.remove("bg-muted")
    })

    editDropzoneImagen.addEventListener("drop", (e) => {
      e.preventDefault()
      editDropzoneImagen.classList.remove("bg-muted")

      const files = e.dataTransfer.files
      if (files.length > 0) {
        if (validarImagen(files[0])) {
          editImagenInput.files = files
          mostrarVistaPrevia(files[0], "editPreviewImagen")
        }
      }
    })

    editImagenInput.addEventListener("change", (e) => {
      if (e.target.files.length > 0) {
        if (validarImagen(e.target.files[0])) {
          mostrarVistaPrevia(e.target.files[0], "editPreviewImagen")
        } else {
          e.target.value = ""
        }
      }
    })
  }
}

function validarImagen(file) {
  const maxSize = 2 * 1024 * 1024
  const allowedTypes = ["image/jpeg", "image/jpg", "image/png"]

  if (!file) {
    showAlert("No se seleccionó ningún archivo", "error")
    return false
  }

  if (!allowedTypes.includes(file.type)) {
    showAlert("Solo se permiten archivos JPG, JPEG o PNG", "error")
    return false
  }

  if (file.size > maxSize) {
    showAlert("La imagen no puede superar los 2MB", "error")
    return false
  }

  return true
}

function mostrarVistaPrevia(file, previewId = "previewImagen") {
  const previewImagen = document.getElementById(previewId)
  if (!previewImagen || !file || !file.type.startsWith("image/")) return

  const reader = new FileReader()

  reader.onload = (e) => {
    previewImagen.src = e.target.result
    previewImagen.classList.remove("hidden")
  }

  reader.readAsDataURL(file)
}
