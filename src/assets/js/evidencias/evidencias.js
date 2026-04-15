/* evidencias.js — Gestión de Evidencias de Formación */

/* =========================
   Variables globales
   ========================= */
let evidencesData = []
let selectedEvidenceFile = null

/* =========================
   Inicialización
   ========================= */
document.addEventListener("DOMContentLoaded", () => {
  // Dump de depuración: mostrar qué sesión llegó al cliente
  try { console.debug('[Evidencias] window.CURRENT_USER', window.CURRENT_USER) } catch (e) {}

  // Cargar filtros y evidencias
  loadPrograms().finally(() => fetchAndRenderEvidences())
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
    // Leer filtros desde la UI
    const params = new URLSearchParams()
    const progSel = document.getElementById('filterPrograma')
    const fichaSel = document.getElementById('filterFicha')
    if (progSel && progSel.value) params.append('id_programa', progSel.value)
    if (fichaSel && fichaSel.value) params.append('id_ficha', fichaSel.value)

    // Construir endpoint
    const query = params.toString()
    const endpoint = resolveEndpoint(`${EVIDENCIAS_API_URL}?accion=listar${query ? '&' + query : ''}`)

    const res = await fetch(endpoint, { method: 'GET' })
    if (!res.ok) throw new Error('Error al obtener evidencias')
    const data = await res.json()

    if (!Array.isArray(data)) {
      evidencesData = []
      renderEvidenceCards()
      return
    }

    evidencesData = data.map(row => {
      const fecha = row.fecha || row.fecha_creacion || row.created_at || ''
      let materiales = []
      if (Array.isArray(row.materiales)) materiales = row.materiales
      else if (typeof row.materiales === 'string' && row.materiales.trim() !== '') materiales = row.materiales.split(',').map(s => s.trim()).filter(Boolean)

      return {
        id: Number.parseInt(row.id_evidencia ?? row.id ?? row.id_movimiento ?? Math.floor(Math.random() * 1e9)),
        fecha: fecha,
        ficha: row.ficha ?? row.numero_ficha ?? row.id_ficha ?? '-',
        obra: row.obra ?? row.nombre_obra ?? '-',
        imagen: getEvidenceImageUrl(row.foto ?? row.imagen ?? ''),
        titulo: row.titulo ?? 'Evidencia',
        descripcion: row.descripcion_obra ?? row.descripcion ?? '',
        materiales: materiales,
        usuario: row.usuario ?? row.nombre_usuario ?? '-',
      }
    })

    renderEvidenceCards()
  } catch (err) {
    console.warn('[Evidencias] No se pudo cargar desde backend:', err)
    evidencesData = []
    renderEvidenceCards()
  }
}

/* =========================
   Cargar fichas para un programa
   ========================= */
async function loadFichasForProgram(id_programa) {
  const select = document.getElementById('filterFicha')
  if (!select) return
  select.disabled = true
  select.innerHTML = ''
  // Si el usuario es Instructor y tenemos fichas en sesión, filtrar desde sesión
  const isInstructor = window.CURRENT_USER && String(window.CURRENT_USER.cargo || '').toLowerCase() === 'instructor'
  let userFichas = []
  if (window.CURRENT_USER && window.CURRENT_USER.fichas !== undefined && window.CURRENT_USER.fichas !== null) {
    const rawF = window.CURRENT_USER.fichas
    if (Array.isArray(rawF)) userFichas = rawF
    else if (typeof rawF === 'string') {
      try {
        const parsedF = JSON.parse(rawF)
        if (Array.isArray(parsedF)) userFichas = parsedF
        else if (typeof parsedF === 'object') userFichas = Object.values(parsedF)
      } catch (e) { userFichas = [] }
    } else if (typeof rawF === 'object') {
      userFichas = Object.values(rawF)
    }
  }
  try { console.debug('[Evidencias] userFichas', userFichas) } catch (e) {}

    try {
      // Si es Instructor y no tiene fichas relacionadas, dejar vacío y deshabilitado
      if (isInstructor && userFichas.length === 0) {
        const optEmpty = document.createElement('option')
        optEmpty.value = ''
        optEmpty.textContent = 'Sin fichas asignadas'
        select.appendChild(optEmpty)
        select.disabled = true
        return
      }

      const optAll = document.createElement('option')
      optAll.value = ''
      optAll.textContent = 'Todas las fichas'
      select.appendChild(optAll)

      if (isInstructor && userFichas.length > 0) {
        const filtered = id_programa ? userFichas.filter(f => String(f.id_programa) === String(id_programa)) : userFichas
        if (filtered.length === 0) {
          // No fichas relacionadas con el programa seleccionado
          select.innerHTML = ''
          const opt = document.createElement('option')
          opt.value = ''
          opt.textContent = 'Sin fichas asignadas'
          select.appendChild(opt)
          select.disabled = true
          return
        }
        filtered.forEach(f => {
          const opt = document.createElement('option')
          opt.value = f.id_ficha
          opt.textContent = f.numero_ficha
          select.appendChild(opt)
        })
        select.disabled = false
        select.onchange = () => fetchAndRenderEvidences()
        return
      }
    // Fallback: cargar todas las fichas desde backend y filtrar por programa
    const res = await fetch(resolveEndpoint('src/controllers/ficha_controller.php?accion=listar'), { method: 'GET' })
    if (!res.ok) throw new Error('Error al cargar fichas')
    const data = await res.json()

    if (Array.isArray(data)) {
      const filtered = id_programa ? data.filter(f => String(f.id_programa) === String(id_programa)) : data
      filtered.forEach(f => {
        const opt = document.createElement('option')
        opt.value = f.id_ficha || f.id_ficha
        opt.textContent = f.numero_ficha || f.numero_ficha
        select.appendChild(opt)
      })
    }

    select.disabled = false
    select.onchange = () => fetchAndRenderEvidences()

  } catch (err) {
    console.warn('No se pudieron cargar fichas:', err)
    select.innerHTML = '<option value="">Error al cargar fichas</option>'
    select.disabled = true
  }
}

/* =========================
   Cargar programas para filtro
   ========================= */
async function loadPrograms() {
  const select = document.getElementById('filterPrograma')
  if (!select) return
  try {
    // Si el usuario es Instructor y hay programas en sesión, usar esos
    const isInstructor = window.CURRENT_USER && String(window.CURRENT_USER.cargo || '').toLowerCase() === 'instructor'
    let userPrograms = []
    if (window.CURRENT_USER && window.CURRENT_USER.programas !== undefined && window.CURRENT_USER.programas !== null) {
      const raw = window.CURRENT_USER.programas
      if (Array.isArray(raw)) userPrograms = raw
      else if (typeof raw === 'string') {
        try {
          const parsed = JSON.parse(raw)
          if (Array.isArray(parsed)) userPrograms = parsed
          else if (typeof parsed === 'object') userPrograms = Object.values(parsed)
        } catch (e) { userPrograms = [] }
      } else if (typeof raw === 'object') {
        userPrograms = Object.values(raw)
      }
    }
    try { console.debug('[Evidencias] userPrograms', userPrograms) } catch (e) {}

      // Limpiar
      select.innerHTML = ''

      // Si es Instructor y no tiene programas relacionados, intentar derivarlos desde sus fichas
      if (isInstructor && userPrograms.length === 0) {
        // Parsear fichas desde session si existen
        let derivedFichas = []
        if (window.CURRENT_USER && window.CURRENT_USER.fichas !== undefined && window.CURRENT_USER.fichas !== null) {
          const rawF = window.CURRENT_USER.fichas
          if (Array.isArray(rawF)) derivedFichas = rawF
          else if (typeof rawF === 'string') {
            try { const parsedF = JSON.parse(rawF); if (Array.isArray(parsedF)) derivedFichas = parsedF; else if (typeof parsedF === 'object') derivedFichas = Object.values(parsedF) } catch (e) { derivedFichas = [] }
          } else if (typeof rawF === 'object') derivedFichas = Object.values(rawF)
        }

        const derivedProgIds = Array.from(new Set(derivedFichas.map(f => Number(f.id_programa)).filter(Boolean)))
        try { console.debug('[Evidencias] derivedProgIds from fichas', derivedProgIds) } catch (e) {}

        if (derivedProgIds.length > 0) {
          // Cargar programas desde backend y filtrar por los ids derivados
          const resAll = await fetch(resolveEndpoint('src/controllers/programa_controller.php?accion=listar'), { method: 'GET' })
          if (!resAll.ok) throw new Error('Error al cargar programas')
          const allProgs = await resAll.json()
          const filteredProgs = Array.isArray(allProgs) ? allProgs.filter(p => derivedProgIds.includes(Number(p.id_programa))) : []

          // Agregar opción por defecto
          const optAllInner = document.createElement('option')
          optAllInner.value = ''
          optAllInner.textContent = 'Todos los programas'
          select.appendChild(optAllInner)

          filteredProgs.forEach(p => {
            const opt = document.createElement('option')
            opt.value = p.id_programa || p.id_programa
            opt.textContent = p.nombre || p.nombre_programa || p.nombre_programa
            select.appendChild(opt)
          })
          select.disabled = false

          // Añadir listener para mantener comportamiento del filtro
          select.addEventListener('change', () => {
            const fichaSel = document.getElementById('filterFicha')
            if (select.value) {
              loadFichasForProgram(select.value)
            } else {
              if (fichaSel) {
                fichaSel.innerHTML = ''
                const opt = document.createElement('option')
                opt.value = ''
                opt.textContent = 'Todas las fichas'
                fichaSel.appendChild(opt)
                fichaSel.disabled = true
              }
            }
            fetchAndRenderEvidences()
          })

          // Inicial: dejar ficha vacía y deshabilitada
          const initialFicha = document.getElementById('filterFicha')
          if (initialFicha) {
            initialFicha.innerHTML = ''
            const opt0 = document.createElement('option')
            opt0.value = ''
            opt0.textContent = 'Todas las fichas'
            initialFicha.appendChild(opt0)
            initialFicha.disabled = true
          }
          return
        } else {
          const optEmpty = document.createElement('option')
          optEmpty.value = ''
          optEmpty.textContent = 'Sin programas asignados'
          select.appendChild(optEmpty)
          select.disabled = true
          // Mantener ficha también vacía y deshabilitada
          const fichaSel = document.getElementById('filterFicha')
          if (fichaSel) {
            fichaSel.innerHTML = ''
            const optF = document.createElement('option')
            optF.value = ''
            optF.textContent = 'Sin fichas asignadas'
            fichaSel.appendChild(optF)
            fichaSel.disabled = true
          }
          return
        }
      }

      // Agregar opción por defecto
      const optAll = document.createElement('option')
      optAll.value = ''
      optAll.textContent = 'Todos los programas'
      select.appendChild(optAll)

      if (isInstructor && userPrograms.length > 0) {
      userPrograms.forEach(p => {
        const opt = document.createElement('option')
        opt.value = p.id_programa
        opt.textContent = p.nombre_programa || p.nombre || p.nombre_programa
        select.appendChild(opt)
      })
      select.disabled = false
    } else {
      const res = await fetch(resolveEndpoint('src/controllers/programa_controller.php?accion=listar'), { method: 'GET' })
      if (!res.ok) throw new Error('Error al cargar programas')
      const data = await res.json()

      if (Array.isArray(data)) {
        data.forEach(p => {
          const opt = document.createElement('option')
          opt.value = p.id_programa || p.id_programa
          opt.textContent = p.nombre || p.nombre_programa || p.nombre_programa
          select.appendChild(opt)
        })
      }
    }

    // Al cambiar programa, cargar fichas relacionadas y recargar evidencias
    select.addEventListener('change', () => {
      const fichaSel = document.getElementById('filterFicha')
      if (select.value) {
        loadFichasForProgram(select.value)
      } else {
        // limpiar y deshabilitar ficha cuando no hay programa seleccionado
        if (fichaSel) {
          fichaSel.innerHTML = ''
          const opt = document.createElement('option')
          opt.value = ''
          opt.textContent = 'Todas las fichas'
          fichaSel.appendChild(opt)
          fichaSel.disabled = true
        }
      }
      fetchAndRenderEvidences()
    })

    // Inicial: dejar ficha vacía y deshabilitada
    const initialFicha = document.getElementById('filterFicha')
    if (initialFicha) {
      initialFicha.innerHTML = ''
      const opt0 = document.createElement('option')
      opt0.value = ''
      opt0.textContent = 'Todas las fichas'
      initialFicha.appendChild(opt0)
      initialFicha.disabled = true
    }

  } catch (err) {
    console.warn('No se pudieron cargar programas:', err)
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
function setFiltersDisabledByEmptyState(shouldDisable) {
  const progSel = document.getElementById('filterPrograma')
  const fichaSel = document.getElementById('filterFicha')

  ;[progSel, fichaSel].forEach((sel) => {
    if (!sel) return
    if (shouldDisable) {
      if (!sel.disabled) {
        const firstOpt = sel.options && sel.options[0] ? sel.options[0] : null
        if (firstOpt) {
          sel.dataset.emptyOriginalText = firstOpt.textContent
          sel.dataset.emptyOriginalValue = firstOpt.value
          firstOpt.textContent = 'Sin evidencias para filtrar'
          firstOpt.value = ''
        } else {
          const opt = document.createElement('option')
          opt.value = ''
          opt.textContent = 'Sin evidencias para filtrar'
          sel.appendChild(opt)
        }
        sel.selectedIndex = 0
        sel.dataset.disabledByEmpty = '1'
        sel.disabled = true
      }
    } else if (sel.dataset.disabledByEmpty === '1') {
      if (sel.dataset.emptyOriginalText !== undefined) {
        const firstOpt = sel.options && sel.options[0] ? sel.options[0] : null
        if (firstOpt) {
          firstOpt.textContent = sel.dataset.emptyOriginalText
          firstOpt.value = sel.dataset.emptyOriginalValue || ''
        }
        delete sel.dataset.emptyOriginalText
        delete sel.dataset.emptyOriginalValue
      }
      delete sel.dataset.disabledByEmpty
      sel.disabled = false
    }
  })
}

function renderEvidenceCards() {
  const grid = document.getElementById("evidenceGrid")
  grid.innerHTML = ""

  if (evidencesData.length === 0) {
    grid.className = "col-span-full"

    // Detectar si hay filtros activos
    const progSel = document.getElementById('filterPrograma')
    const fichaSel = document.getElementById('filterFicha')
    const hasFilters = (progSel && progSel.value) || (fichaSel && fichaSel.value)

    // Si no hay evidencias y no hay filtros activos, bloquear filtros
    setFiltersDisabledByEmptyState(!hasFilters)

    if (hasFilters) {
      grid.innerHTML = `
        <div class="mt-10 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full">
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
          <p class="text-sm text-muted-foreground mt-1 max-w-md">No se encontraron evidencias que coincidan con los criterios de búsqueda actuales.</p>
        </div>
      `
    } else {
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
    }
    return
  }

  setFiltersDisabledByEmptyState(false)

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
        <p class="text-sm text-foreground line-clamp-2 mb-2">${evidence.descripcion}</p>
        <p class="text-xs text-muted-foreground mb-3">Creado por: <span class="text-foreground font-semibold">${evidence.usuario}</span></p>
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
  document.getElementById("detailCreator").textContent = evidence.usuario
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
  selectedEvidenceFile = null
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
      const file = files[0]
      // Sync dropped file into the input so validation sees it
      try {
        const dt = new DataTransfer()
        dt.items.add(file)
        photoInput.files = dt.files
      } catch (err) {
        console.warn("[Evidencias] No se pudo sincronizar el input de archivo:", err)
      }
      handleImageUpload(file)
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

    selectedEvidenceFile = file

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
    const res = await fetch(`${resolveEndpoint(EVIDENCIAS_API_URL)}?accion=salidas_pendientes`, {
      method: "GET",
      credentials: "same-origin",
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
  const file = (photoInput && photoInput.files && photoInput.files[0]) || selectedEvidenceFile

  // Validaciones
  if (!salidaSelect.value) {
    if (salidaSelect.options.length <= 1 || salidaSelect.disabled) {
      showFlowbiteAlert("info", "No hay salidas ni obras pendientes para registrar evidencias")
    } else {
      showFlowbiteAlert("info", "Por favor selecciona una salida")
    }
    return
  }
  
  if (!descripcion || !file) {
    showFlowbiteAlert("info", "Por favor complete todos los campos obligatorios")
    return
  }

  if (descripcion.length > 250) {
    showFlowbiteAlert("info", "La descripción no puede exceder 250 caracteres")
    return
  }

  try {
    const formData = new FormData()
    formData.append("id_movimiento_salida", salidaSelect.value)
    formData.append("foto", file)
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
