/* evidencias.js — Gestión de Evidencias de Formación */

/* =========================
   Variables globales
   ========================= */
let evidencesData = []

// Datos de ejemplo (simula backend)
const mockEvidences = [
  {
    id: 1,
    fecha: "2024-04-06",
    ficha: "2896441",
    imagen: "src/uploads/evidencias/prueba.jpg",
    titulo: "Técnico en Instalaciones Eléctricas Residenciales",
    descripcion: "Trabajo de cimentación realizado por los aprendices de la ficha 2567890",
    materiales: ["Cemento Gris", "Arena de Río"],
  }
]

/* =========================
   Inicialización
   ========================= */
document.addEventListener("DOMContentLoaded", () => {
  evidencesData = [...mockEvidences]
  renderEvidenceCards()
  setupUploadArea()
  setupButtonListeners()
})

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

  evidencesData.forEach((evidence) => {
    const card = document.createElement("div")
    card.className =
      "rounded-2xl border border-border bg-card shadow-sm overflow-hidden cursor-pointer hover:shadow-lg hover:border-primary transition-all duration-300"
    card.onclick = () => openDetailsModal(evidence.id)

    card.innerHTML = `
      <div class="relative">
        <img src="${evidence.imagen}" alt="Evidencia" class="w-full h-72 object-cover bg-muted">
        <div class="absolute top-3 right-3 bg-card/90 backdrop-blur-sm px-3 py-1.5 rounded-lg flex items-center gap-2 shadow-sm">
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
            .map(
              (material) => `
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-secondary-13 text-secondary">
              ${icons.tag}
              ${material}
            </span>
          `,
            )
            .join("")}
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

  // Actualizar contenido del modal
  document.getElementById("detailImage").src = evidence.imagen
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
  `,
    )
    .join("")

  // Mostrar modal
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
function openCreateModal() {
  const modal = document.getElementById("createModal")
  modal.classList.add("active")
  document.body.style.overflow = "hidden"
}

function closeCreateModal() {
  const modal = document.getElementById("createModal")
  modal.classList.remove("active")
  document.body.style.overflow = "auto"

  // Limpiar formulario
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

  // Click en el área de upload
  uploadArea.addEventListener("click", () => {
    photoInput.click()
  })

  // Drag and drop
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

  // Cambio de archivo
  photoInput.addEventListener("change", (e) => {
    if (e.target.files.length > 0) {
      handleImageUpload(e.target.files[0])
    }
  })

  function handleImageUpload(file) {
    // Validar tipo de archivo
    if (!file.type.match(/image\/(png|jpg|jpeg)/)) {
      showFlowbiteAlert("error", "Solo se permiten archivos PNG, JPG o JPEG")
      return
    }

    // Validar tamaño (5MB)
    if (file.size > 5 * 1024 * 1024) {
      showFlowbiteAlert("error", "La imagen no debe superar los 5MB")
      return
    }

    // Mostrar preview
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
   Crear evidencia
   ========================= */
function createEvidence() {
  const descripcion = document.getElementById("descripcion").value
  const photoInput = document.getElementById("photoInput")

  // Validaciones
  if (!descripcion || !photoInput.files.length) {
    showFlowbiteAlert("error", "Por favor complete todos los campos obligatorios")
    return
  }

  // Obtener la imagen
  const reader = new FileReader()
  reader.onload = (e) => {
    // Simulación de creación
    const newEvidence = {
      id: evidencesData.length + 1,
      fecha: new Date().toISOString().split('T')[0],
      ficha: "2896441", // Por defecto
      imagen: e.target.result,
      titulo: `Evidencia ${new Date().toLocaleDateString()}`,
      descripcion: descripcion,
      materiales: ["Material"],
    }

    evidencesData.push(newEvidence)
    renderEvidenceCards()
    closeCreateModal()

    showFlowbiteAlert("success", "Evidencia creada exitosamente")
  }
  reader.readAsDataURL(photoInput.files[0])
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
   Helper: Alertas (Flowbite style)
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

  wrapper.className = `
    relative flex items-center w-full mx-auto pointer-events-auto
    rounded-2xl border-l-4 ${borderColor} bg-white shadow-md
    px-4 py-3 text-sm ${textColor}
    opacity-0 -translate-y-2
    transition-all duration-300 ease-out
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

/* =========================
   Event Listeners
   ========================= */
// Cerrar modales al hacer clic en el overlay
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

  // Cerrar con tecla Escape
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeDetailsModal()
      closeCreateModal()
    }
  })
})
