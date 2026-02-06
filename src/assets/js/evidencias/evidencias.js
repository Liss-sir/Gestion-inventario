/* evidencias.js — Gestión de Evidencias de Formación */

/* =========================
   Variables globales
   ========================= */
let evidencesData = []

/* =========================
   Inicialización
   ========================= */
document.addEventListener("DOMContentLoaded", () => {
  // Cargar evidencias
  fetchAndRenderEvidences()
  setupUploadArea()
  setupButtonListeners()
})

/* =========================
   API & Helpers
   ========================= */

/* ============================================================
   ✅ BASE_URL GLOBAL REAL (SIN HARDCODE)
   - Si existe window.BASE_URL (recomendado desde PHP), úsala
   - Si NO existe, detecta automáticamente la raíz del proyecto
     según la ruta actual:
       ✅ /.../index.php?page=...
       ✅ /.../src/view/...
       ✅ cualquier otra ruta
============================================================ */
const EVIDENCIAS_BASE_URL = (function () {
  // ✅ 1) Si el backend ya define BASE_URL global, usarla
  if (window.BASE_URL) {
    return window.BASE_URL.endsWith("/") ? window.BASE_URL : window.BASE_URL + "/"
  }

  // ✅ 2) Detectar desde la URL actual (GLOBAL)
  const { origin, pathname } = window.location

  // Caso A: estás en index.php?page=...
  // Ej: /gestion_inventario/Gestion-inventario/index.php
  if (pathname.includes("/index.php")) {
    const basePath = pathname.split("/index.php")[0] + "/"
    return origin + basePath
  }

  // Caso B: estás dentro de /src/... (ej: /Gestion-inventario/src/view/...)
  // Recorta desde "/src/"
  if (pathname.includes("/src/")) {
    const basePath = pathname.split("/src/")[0] + "/"
    return origin + basePath
  }

  // Caso C: fallback -> usa el directorio actual
  // Ej: /Gestion-inventario/algo/archivo.php  -> /Gestion-inventario/algo/
  const basePath = pathname.replace(/\/[^/]*$/, "/")
  return origin + basePath
})()

const EVIDENCIAS_API_URL = "src/controllers/evidencia_controller.php"

/* ============================================================
   ✅ FIX GLOBAL (SIN TOCAR TU BASE)
   - Convierte "src/controllers/..." en URL absoluta usando la base real del proyecto
============================================================ */
function resolveEndpoint(endpoint) {
  if (!endpoint) return ""

  // ✅ Si ya es absoluto, no tocar
  if (/^https?:\/\//i.test(endpoint)) return endpoint

  // ✅ Si empieza con "/", se pega directo a la base (sin duplicar)
  if (endpoint.startsWith("/")) {
    return EVIDENCIAS_BASE_URL.replace(/\/+$/, "") + endpoint
  }

  // ✅ Caso normal: "src/controllers/....php"
  return new URL(endpoint, EVIDENCIAS_BASE_URL).toString()
}

function getEvidenceImageUrl(foto) {
  if (!foto) return ""
  const f = foto.toString()
  if (f.startsWith("http") || f.startsWith("data:")) return f
  // Si es un nombre/relativo, asumimos carpeta de uploads de evidencias
  return EVIDENCIAS_BASE_URL + "src/uploads/evidencias/" + f.replace(/^\/+/, "")
}

async function fetchAndRenderEvidences() {
  try {
    const res = await fetch(resolveEndpoint(EVIDENCIAS_API_URL), {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    })

    if (!res.ok) throw new Error("Error al cargar evidencias")

    const data = await res.json()

    evidencesData = (Array.isArray(data) ? data : []).map((row) => {
      // Formatear fecha
      const fecha = row.fecha ? new Date(row.fecha).toLocaleDateString("es-CO") : "-"
      
      // Crear array de materiales desde el GROUP_CONCAT
      const materiales = []
      if (row.materiales) {
        // materiales ya viene como string: "Cemento (10 KG), Arena (20 KG)"
        materiales.push(...row.materiales.split(', '))
      }

      return {
        id: Number.parseInt(row.id_evidencia ?? row.id ?? Math.floor(Math.random() * 1e9)),
        fecha: fecha,
        ficha: row.ficha ?? "-",
        obra: row.obra ?? "-",
        imagen: getEvidenceImageUrl(row.foto ?? row.imagen ?? ""),
        titulo: row.titulo ?? "Evidencia",
        descripcion: row.descripcion_obra ?? row.descripcion ?? "",
        materiales: materiales,
        usuario: row.usuario ?? "-",
      }
    })

    renderEvidenceCards()
  } catch (err) {
    console.warn("[Evidencias] No se pudo cargar desde backend:", err)
    evidencesData = []
    renderEvidenceCards()
  }
}

/* =========================
   Iconos SVG
   ========================= */
const icons = {
  calendar:
    '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18"/></svg>',
  tag: '<svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
  photo:
    '<svg class="w-6 h-6 text-primary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>',
}

/* =========================
   Render de tarjetas
   ========================= */
function renderEvidenceCards() {
  const grid = document.getElementById("evidenceGrid")
  grid.innerHTML = ""

  if (evidencesData.length === 0) {
    grid.className = "col-span-full"
    grid.innerHTML = `
      <div class="mt-10 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full">
        <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
          <svg class="h-7 w-7 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </div>
        <h3 class="text-lg font-semibold mt-4">No hay evidencias registradas</h3>
        <p class="text-sm text-muted-foreground mt-1 max-w-md">
          Una vez agregues evidencias desde el botón <strong>"Nueva Evidencia"</strong>, aparecerán listadas en esta vista.
        </p>
      </div>
    `
    return
  }

  grid.className = "grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6"

  evidencesData.forEach((evidence) => {
    const card = document.createElement("div")
    card.className =
      "rounded-2xl border border-border bg-card shadow-sm overflow-hidden cursor-pointer hover:shadow-lg hover:border-primary transition-all duration-300"
    card.onclick = () => openDetailsModal(evidence.id)

    card.innerHTML = `
      <div class="relative">
        <img src="${evidence.imagen}" alt="Evidencia" class="w-full h-72 object-cover bg-muted">
        <div class="absolute top-3 right-3 bg-secondary px-2.5 py-1 rounded-lg shadow-lg">
          <span class="text-xs font-bold text-white">Evidencia #${evidence.id}</span>
        </div>
      </div>
      <div class="p-4">
        <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-medium">Ficha ${evidence.ficha}</span>
        <div class="flex items-center gap-1">
          ${icons.calendar}
          <span class="text-xs text-muted-foreground">${evidence.fecha}</span>
        </div>
        </div>
        <p class="text-sm text-foreground line-clamp-2 mb-3">${evidence.descripcion}</p>
        <div class="flex flex-wrap gap-2">
          ${evidence.materiales
            .slice(0, 2)
            .map(
              (material) => `
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-secondary-13 text-secondary">
              ${icons.tag}
              ${material}
            </span>
          `
            )
            .join("")}
          ${evidence.materiales.length > 2 ? `
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-muted text-muted-foreground border border-border">
              +${evidence.materiales.length - 2} más
            </span>
          ` : ''}
        </div>
      </div>
    `

    grid.appendChild(card)
  })
}

/* =========================
   Modal de Detalles
   ========================= */
function openDetailsModal(id) {
  const evidence = evidencesData.find((e) => e.id === id)
  if (!evidence) return

  document.getElementById("detailImage").src = evidence.imagen
  document.getElementById("detailEvidenceId").textContent = `Evidencia #${evidence.id}`
  document.getElementById("detailObra").textContent = evidence.obra
  document.getElementById("detailFicha").textContent = evidence.ficha
  document.getElementById("detailDate").innerHTML = `
    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
    ${evidence.fecha}
  `
  document.getElementById("detailDescription").textContent = evidence.descripcion

  const materialsContainer = document.getElementById("detailMaterials")
  materialsContainer.innerHTML = evidence.materiales
    .map(
      (material) => `
    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-secondary/10 text-secondary border border-secondary/20">
      ${icons.tag}
      ${material}
    </span>
  `
    )
    .join("")

  const modal = document.getElementById("detailsModal")
  modal.classList.add("active")
  document.body.style.overflow = "hidden"
}

function closeDetailsModal() {
  const modal = document.getElementById("detailsModal")
  modal.classList.remove("active")
  document.body.style.overflow = "auto"
}

/* =========================
   Modal de Registro
   ========================= */
function openCreateModalForSalida(idMovimiento, material, bodega, cantidad, unidad) {
  document.getElementById("id_movimiento_salida").value = idMovimiento

  const salidaInfo = document.getElementById("salidaInfo")
  salidaInfo.innerHTML = `
    <p class="font-medium text-foreground">Salida #${idMovimiento}</p>
    <p><strong>Material:</strong> ${material}</p>
    <p><strong>Bodega:</strong> ${bodega}</p>
    <p><strong>Cantidad:</strong> ${cantidad} ${unidad}</p>
  `

  openCreateModal()
}

function openCreateModal() {
  const modal = document.getElementById("createModal")
  modal.classList.add("active")
  document.body.style.overflow = "hidden"
  
  // Cargar salidas pendientes
  loadPendingSalidas()
  
  // Configurar contador de caracteres
  const descripcionInput = document.getElementById("descripcion")
  descripcionInput.addEventListener("input", () => {
    const charCount = document.getElementById("charCount")
    charCount.textContent = descripcionInput.value.length
  })
}

function closeCreateModal() {
  const modal = document.getElementById("createModal")
  modal.classList.remove("active")
  document.body.style.overflow = "auto"

  // Limpiar formulario
  document.getElementById("salidaSelect").value = ""
  document.getElementById("descripcion").value = ""
  document.getElementById("photoInput").value = ""
  document.getElementById("imagePreview").classList.add("hidden")
  document.getElementById("uploadArea").style.display = "flex"
}

/* =========================
   Upload de Imagen
   ========================= */
function setupUploadArea() {
  const uploadArea = document.getElementById("uploadArea")
  const photoInput = document.getElementById("photoInput")
  const imagePreview = document.getElementById("imagePreview")
  const previewImg = document.getElementById("previewImg")

  uploadArea.addEventListener("click", () => {
    photoInput.click()
  })

  uploadArea.addEventListener("dragover", (e) => {
    e.preventDefault()
    uploadArea.style.borderColor = "var(--primary)"
    uploadArea.style.backgroundColor = "color-mix(in srgb, var(--primary) 5%, transparent)"
  })

  uploadArea.addEventListener("dragleave", () => {
    uploadArea.style.borderColor = "var(--border)"
    uploadArea.style.backgroundColor = "var(--muted)"
  })

  uploadArea.addEventListener("drop", (e) => {
    e.preventDefault()
    uploadArea.style.borderColor = "var(--border)"
    uploadArea.style.backgroundColor = "var(--muted)"

    const files = e.dataTransfer.files
    if (files.length > 0) {
      handleImageUpload(files[0])
    }
  })

  photoInput.addEventListener("change", (e) => {
    if (e.target.files.length > 0) {
      handleImageUpload(e.target.files[0])
    }
  })

  function handleImageUpload(file) {
    if (!file.type.match(/image\/(png|jpg|jpeg)/)) {
      showFlowbiteAlert("info", "Solo se permiten archivos PNG, JPG o JPEG")
      return
    }

    if (file.size > 5 * 1024 * 1024) {
      showFlowbiteAlert("info", "La imagen no debe superar los 5MB")
      return
    }

    const reader = new FileReader()
    reader.onload = (e) => {
      previewImg.src = e.target.result
      imagePreview.classList.remove("hidden")
      uploadArea.style.display = "none"
    }
    reader.readAsDataURL(file)
  }
}

function removeImage() {
  document.getElementById("photoInput").value = ""
  document.getElementById("imagePreview").classList.add("hidden")
  document.getElementById("uploadArea").style.display = "flex"
}

/* =========================
   Cargar Salidas Pendientes
   ========================= */
async function loadPendingSalidas() {
  const select = document.getElementById("salidaSelect")
  try {
    const res = await fetch(`${EVIDENCIAS_API_URL}?accion=salidas_pendientes&id_usuario=1`, {
      method: "GET"
    })
    
    if (!res.ok) throw new Error("Error al cargar salidas")
    
    const salidas = await res.json()
    
    // Limpiar select
    select.innerHTML = ""
    
    if (!Array.isArray(salidas) || salidas.length === 0) {
      select.innerHTML = '<option value="">No hay salidas pendientes</option>'
      select.disabled = true
      return
    }
    
    // Agregar opción por defecto
    const defaultOption = document.createElement("option")
    defaultOption.value = ""
    defaultOption.textContent = "Selecciona una salida..."
    select.appendChild(defaultOption)
    
    // Agregar salidas
    salidas.forEach(salida => {
      const option = document.createElement("option")
      option.value = salida.id_movimiento
      option.dataset.salida = JSON.stringify(salida)

      // Construir el texto: Ficha - Material - Obra - Fecha
      const fecha = salida.fecha_hora ? new Date(salida.fecha_hora).toLocaleDateString("es-CO") : "-"
      const obra = salida.obra && salida.obra !== '-' ? ` (${salida.obra})` : ""
      const optionText = `${salida.ficha || 'S/N'} - ${salida.material}${obra} - ${fecha}`

      option.textContent = optionText
      select.appendChild(option)
    })
    
    select.disabled = false
  } catch (error) {
    console.error("Error cargando salidas:", error)
    select.innerHTML = '<option value="">Error al cargar salidas</option>'
    select.disabled = true
  }
}

/* =========================
   Crear evidencia
   ========================= */
async function createEvidence() {
  const salidaSelect = document.getElementById("salidaSelect")
  const descripcion = document.getElementById("descripcion").value
  const photoInput = document.getElementById("photoInput")

  // Validaciones
  if (!salidaSelect.value) {
    if (salidaSelect.options.length <= 1 || salidaSelect.disabled) {
      showFlowbiteAlert("info", "No hay salidas ni obras pendientes para registrar evidencias")
    } else {
      showFlowbiteAlert("info", "Por favor selecciona una salida")
    }
    return
  }
  
  if (!descripcion || !photoInput.files.length) {
    showFlowbiteAlert("info", "Por favor complete todos los campos obligatorios")
    return
  }

  if (descripcion.length > 250) {
    showFlowbiteAlert("info", "La descripción no puede exceder 250 caracteres")
    return
  }

  try {
    const formData = new FormData()
    formData.append("id_usuario", 1) // Cambiar por el ID del usuario logueado
    formData.append("id_movimiento_salida", salidaSelect.value)
    formData.append("foto", photoInput.files[0])
    formData.append("descripcion_obra", descripcion)

    const res = await fetch(resolveEndpoint(EVIDENCIAS_API_URL), {
      method: "POST",
      body: formData,
      credentials: "same-origin",
      cache: "no-store",
    })

    const result = await res.json()

    if (res.ok && res.status === 201) {
      showFlowbiteAlert("success", "Evidencia creada exitosamente")
      closeCreateModal()
      await fetchAndRenderEvidences()
    } else {
      showFlowbiteAlert("error", result.mensaje || "Error al crear la evidencia")
    }
  } catch (error) {
    console.error("Error creando evidencia:", error)
    showFlowbiteAlert("error", "Error de conexión al crear la evidencia")
  }
}

/* =========================
   Setup Button Listeners
   ========================= */
function setupButtonListeners() {
  const btnNuevaEvidencia = document.getElementById("btnNuevaEvidencia")
  if (btnNuevaEvidencia) {
    btnNuevaEvidencia.addEventListener("click", openCreateModal)
  }
}

/* =========================
   Flowbite-style Alerts
   ========================= */
function showFlowbiteAlert(type, message) {
  const container = document.getElementById("flowbite-alert-container") || createAlertContainer()
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
        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm1 15H9v-2h2Zm0-4H9V5h2Z"/>
      </svg>
    `
  }

  if (type === "info") {
    borderColor = "border-blue-500"
    textColor = "text-blue-900"
    titleText = "Información"
    iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm0 15h-1v-4h1zm0-6h-1V5h1z"/>
      </svg>
    `
  }

  wrapper.className = `
    relative flex items-center w-full pointer-events-auto
    rounded-2xl border-l-4 ${borderColor} bg-white shadow-md
    px-4 py-3 text-sm ${textColor}
    opacity-0 -translate-y-2 transition-all duration-300 ease-out
  `

  wrapper.innerHTML = `
    <div class="flex-shrink-0 mr-3 text-current">${iconSVG}</div>
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

function createAlertContainer() {
  const container = document.createElement("div")
  container.id = "flowbite-alert-container"
  container.className = "fixed top-6 right-6 z-[9999] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none"
  document.body.appendChild(container)
  return container
}

/* =========================
   Event Listeners
   ========================= */
document.addEventListener("DOMContentLoaded", () => {
  document.getElementById("detailsModal").addEventListener("click", function (e) {
    if (e.target === this) {
      closeDetailsModal()
    }
  })

  document.getElementById("createModal").addEventListener("click", function (e) {
    if (e.target === this) {
      closeCreateModal()
    }
  })

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeDetailsModal()
      closeCreateModal()
    }
  })
})
