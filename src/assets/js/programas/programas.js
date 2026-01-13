// =========================
// FLOWBITE-STYLE ALERTS (WHITE BACKGROUND, WARNING, NO PROGRESS BAR)
// =========================

/**
 * Returns the existing Flowbite-style alert container or creates it if it does not exist.
 */
function getOrCreateFlowbiteContainer() {
  let container = document.getElementById("flowbite-alert-container");

  if (!container) {
    container = document.createElement("div");
    container.id = "flowbite-alert-container";

    container.className =
      "fixed top-6 left-1/2 -translate-x-1/2 z-[9999] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none";

    document.body.appendChild(container);
  }

  return container;
}

/**
 * Generic alert renderer using a Flowbite-like appearance.
 * type: "warning" | "success" | "info"
 * message: string to be displayed to the user
 */
function showFlowbiteAlert(type, message) {
  const container = getOrCreateFlowbiteContainer();
  const wrapper = document.createElement("div");

  // Default style: warning
  let borderColor = "border-amber-500";
  let textColor = "text-amber-900";
  let titleText = "Advertencia";

  // Default icon: warning triangle
  let iconSVG = `
    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
         fill="currentColor" viewBox="0 0 20 20">
      <path d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.59A1.75 1.75 0 0 1 16.768 17H3.232a1.75 1.75 0 0 1-1.492-2.311L8.257 3.1z"/>
      <path d="M11 13H9V9h2zm0 3H9v-2h2z" fill="#fff"/>
    </svg>
  `;

  if (type === "success") {
    borderColor = "border-emerald-500";
    textColor = "text-emerald-900";
    titleText = "Éxito";
    iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
           fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm-1 15-4-4 1.414-1.414L9 12.172l4.586-4.586L15 9z"/>
      </svg>
    `;
  }

  if (type === "info") {
    borderColor = "border-blue-500";
    textColor = "text-blue-900";
    titleText = "Información";
    iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
           fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm1 15H9v-5h2Zm0-7H9V6h2Z"/>
      </svg>
    `;
  }

  // Entry animation and base visual configuration
  wrapper.className = `
    relative flex items-center w-full mx-auto pointer-events-auto
    rounded-2xl border-l-4 ${borderColor} bg-white shadow-md
    px-4 py-3 text-sm ${textColor}
    opacity-0 -translate-y-2
    transition-all duration-300 ease-out
    animate-fade-in-up
  `;

  wrapper.innerHTML = `
    <div class="flex-shrink-0 mr-3 text-current">
      ${iconSVG}
    </div>

    <div class="flex-1 min-w-0">
      <p class="font-semibold">${titleText}</p>
      <p class="mt-0.5 text-sm">${message}</p>
    </div>
  `;

  container.appendChild(wrapper);

  // Smooth fade-in using CSS transition
  requestAnimationFrame(() => {
    wrapper.classList.remove("opacity-0", "-translate-y-2");
    wrapper.classList.add("opacity-100", "translate-y-0");
  });

  // Automatic fade-out and removal
  setTimeout(() => {
    wrapper.classList.add("opacity-0", "-translate-y-2");
    wrapper.classList.remove("opacity-100", "translate-y-0");
    setTimeout(() => wrapper.remove(), 250);
  }, 4000);
}

// Public API used by the rest of the module
function toastError(message) {
  showFlowbiteAlert("warning", message);
}

function toastSuccess(message) {
  showFlowbiteAlert("success", message);
}

function toastInfo(message) {
  showFlowbiteAlert("info", message);
}

// ========== HELPER FUNCTION: Filter and show/hide empty states ==========
function applyFilterAndUpdateEmptyStates() {
  const searchInput = document.querySelector('input[placeholder="Buscar por nombre..."]')
  const searchTerm = (searchInput?.value ?? '').toLowerCase().trim()
  const filterEstado = document.getElementById('selectFiltroEstado').value
  
  // Get all table rows and grid cards
  const tableRows = document.querySelectorAll('#tableView tbody tr[data-index]')
  const gridCards = document.querySelectorAll('#gridView [data-index]')
  const tableView = document.getElementById('tableView')
  const gridView = document.getElementById('gridView')
  
  let visibleRowCount = 0
  let visibleCardCount = 0
  
  // Filter table rows
  tableRows.forEach(row => {
    const nombre = row.dataset.nombre?.toLowerCase() ?? ''
    const estado = String(row.dataset.estado ?? '')
    
    const matchesSearch = searchTerm === '' || nombre.includes(searchTerm)
    const matchesFilter = filterEstado === '' || estado === filterEstado
    
    if (matchesSearch && matchesFilter) {
      row.classList.remove('hidden')
      visibleRowCount++
    } else {
      row.classList.add('hidden')
    }
  })
  
  // Filter grid cards
  gridCards.forEach(card => {
    const nombre = card.dataset.nombre?.toLowerCase() ?? ''
    const estado = String(card.dataset.estado ?? '')
    
    const matchesSearch = searchTerm === '' || nombre.includes(searchTerm)
    const matchesFilter = filterEstado === '' || estado === filterEstado
    
    if (matchesSearch && matchesFilter) {
      card.classList.remove('hidden')
      visibleCardCount++
    } else {
      card.classList.add('hidden')
    }
  })
  
  // Show/hide empty states and tables
  const emptyState = document.getElementById('emptyStateProgramas')
  const emptySearch = document.getElementById('emptySearchProgramas')
  
  const totalRows = tableRows.length
  const totalCards = gridCards.length
  const totalProgramas = totalRows + totalCards > 0 ? totalRows : totalCards
  
  if (totalProgramas === 0) {
    // No programas in system
    emptyState?.classList.remove('hidden')
    emptySearch?.classList.add('hidden')
    tableView?.classList.add('hidden')
    gridView?.classList.add('hidden')
  } else if (visibleRowCount === 0 && visibleCardCount === 0) {
    // Programas exist but no results for current search/filter
    emptyState?.classList.add('hidden')
    emptySearch?.classList.remove('hidden')
    tableView?.classList.add('hidden')
    gridView?.classList.add('hidden')
  } else {
    // Results found
    emptyState?.classList.add('hidden')
    emptySearch?.classList.add('hidden')
    tableView?.classList.remove('hidden')
    // Nota: gridView será mostrado/ocultado por toggleView()
    if (!gridView?.classList.contains('hidden')) {
      gridView?.classList.remove('hidden')
    }
  }
}

// programs.js - Modifica la función toggleView()

// Function to switch between table and grid view
function toggleView(view) {
  const tableView = document.getElementById("tableView")
  const gridView = document.getElementById("gridView")
  const tableBtn = document.getElementById("viewTableBtn")
  const gridBtn = document.getElementById("viewGridBtn")
  const emptyState = document.getElementById("emptyStateProgramas")
  const emptySearch = document.getElementById("emptySearchProgramas")

  // Close all open menus when changing view
  closeAllMenus()

  if (view === "table") {
    // Show table view
    tableView.classList.remove("hidden")
    gridView.classList.add("hidden")
    tableBtn.classList.add("bg-muted", "text-foreground")
    gridBtn.classList.remove("bg-muted", "text-foreground")
    gridBtn.classList.add("text-muted-foreground")
  } else {
    // Show grid view
    tableView.classList.add("hidden")
    gridView.classList.remove("hidden")
    gridBtn.classList.add("bg-muted", "text-foreground")
    tableBtn.classList.remove("bg-muted", "text-foreground")
    tableBtn.classList.add("text-muted-foreground")
  }

  // After changing view, check if we should show empty states
  checkAndShowEmptyStates(view)
}

// Nueva función para verificar y mostrar estados vacíos según la vista actual
function checkAndShowEmptyStates(currentView) {
  const tableView = document.getElementById("tableView")
  const gridView = document.getElementById("gridView")
  const emptyState = document.getElementById("emptyStateProgramas")
  const emptySearch = document.getElementById("emptySearchProgramas")
  
  // Verificar si hay filas o tarjetas visibles
  let hasVisibleItems = false
  
  if (currentView === "table") {
    // Contar filas visibles en la tabla
    const visibleRows = document.querySelectorAll('#tableView tbody tr:not(.hidden)')
    hasVisibleItems = visibleRows.length > 0
  } else {
    // Contar tarjetas visibles en la vista de cuadrícula
    const visibleCards = document.querySelectorAll('#gridView [data-index]:not(.hidden)')
    hasVisibleItems = visibleCards.length > 0
  }
  
  // Verificar si hay programas en total en el sistema
  const totalRows = document.querySelectorAll('#tableView tbody tr[data-index]').length
  const totalCards = document.querySelectorAll('#gridView [data-index]').length
  const hasAnyPrograms = totalRows > 0 || totalCards > 0
  
  // Determinar qué mostrar
  if (!hasAnyPrograms) {
    // No hay programas en el sistema
    emptyState?.classList.remove('hidden')
    emptySearch?.classList.add('hidden')
    tableView?.classList.add('hidden')
    gridView?.classList.add('hidden')
  } else if (!hasVisibleItems) {
    // Hay programas pero no coinciden con los filtros/búsqueda
    emptyState?.classList.add('hidden')
    emptySearch?.classList.remove('hidden')
    tableView?.classList.add('hidden')
    gridView?.classList.add('hidden')
  } else {
    // Hay programas visibles
    emptyState?.classList.add('hidden')
    emptySearch?.classList.add('hidden')
    
    // Mostrar la vista activa
    if (currentView === "table") {
      tableView?.classList.remove('hidden')
      gridView?.classList.add('hidden')
    } else {
      tableView?.classList.add('hidden')
      gridView?.classList.remove('hidden')
    }
  }
}

// También modifica applyFilterAndUpdateEmptyStates para usar la nueva función
function applyFilterAndUpdateEmptyStates() {
  const searchInput = document.querySelector('input[placeholder="Buscar por nombre..."]')
  const searchTerm = (searchInput?.value ?? '').toLowerCase().trim()
  const filterEstado = document.getElementById('selectFiltroEstado').value
  
  // Get all table rows and grid cards
  const tableRows = document.querySelectorAll('#tableView tbody tr[data-index]')
  const gridCards = document.querySelectorAll('#gridView [data-index]')
  
  // Filter table rows
  tableRows.forEach(row => {
    const nombre = row.dataset.nombre?.toLowerCase() ?? ''
    const estado = String(row.dataset.estado ?? '')
    
    const matchesSearch = searchTerm === '' || nombre.includes(searchTerm)
    const matchesFilter = filterEstado === '' || estado === filterEstado
    
    if (matchesSearch && matchesFilter) {
      row.classList.remove('hidden')
    } else {
      row.classList.add('hidden')
    }
  })
  
  // Filter grid cards
  gridCards.forEach(card => {
    const nombre = card.dataset.nombre?.toLowerCase() ?? ''
    const estado = String(card.dataset.estado ?? '')
    
    const matchesSearch = searchTerm === '' || nombre.includes(searchTerm)
    const matchesFilter = filterEstado === '' || estado === filterEstado
    
    if (matchesSearch && matchesFilter) {
      card.classList.remove('hidden')
    } else {
      card.classList.add('hidden')
    }
  })
  
  // Determinar qué vista está activa actualmente
  const tableView = document.getElementById("tableView")
  const currentView = tableView.classList.contains("hidden") ? "grid" : "table"
  
  // Verificar y mostrar estados vacíos según la vista actual
  checkAndShowEmptyStates(currentView)
}

// En el evento DOMContentLoaded, añade esta inicialización:
document.addEventListener("DOMContentLoaded", () => {
  
  // Inicializar el estado correcto al cargar la página
  const tableView = document.getElementById("tableView")
  const initialView = tableView.classList.contains("hidden") ? "grid" : "table"
  checkAndShowEmptyStates(initialView)
})

// Function to toggle action menu
function toggleActionMenu(index) {
  const menu = document.getElementById("actionMenu" + index)
  const isHidden = menu.classList.contains("hidden")

  // Close all other menus
  closeAllMenus()

  // Toggle current menu
  if (isHidden) {
    menu.classList.remove("hidden")
  }
}

// Function to close all menus
function closeAllMenus() {
  const allMenus = document.querySelectorAll('[id^="actionMenu"]')
  allMenus.forEach((menu) => {
    menu.classList.add("hidden")
  })
}

// Close menus when clicking outside
document.addEventListener("click", (event) => {
  const isMenuButton = event.target.closest('button[onclick^="toggleActionMenu"]')
  const isMenuContent = event.target.closest('[id^="actionMenu"]')

  if (!isMenuButton && !isMenuContent) {
    closeAllMenus()
  }
})

// ========== MODAL FUNCTIONS ==========

// Open edit program modal
function openEditModal(index) {
  const modal = document.getElementById("editProgramModal")
  const row =
    document.querySelector(`tr[data-index="${index}"]`) || document.querySelector(`div[data-index="${index}"]`)

  if (row) {
    document.getElementById("edit_index").value = index
    document.getElementById("edit_codigo").value = row.dataset.codigo
    document.getElementById("edit_nombre").value = row.dataset.nombre
    document.getElementById("edit_descripcion").value = row.dataset.descripcion
    document.getElementById("edit_nivel").value = row.dataset.nivel
    document.getElementById("edit_duracion").value = row.dataset.duracion.replace(/[^\d]/g, '')
  }

  modal.classList.remove("hidden")
  modal.classList.add("flex")
}

// Close edit program modal
function closeEditModal() {
  const modal = document.getElementById("editProgramModal")
  modal.classList.add("hidden")
  modal.classList.remove("flex")
}

// ========== FUNCIÓN PARA ASIGNAR COLORES SEGÚN NIVEL ==========
function getLevelStyles(nivel) {
    const nivelLower = nivel.toLowerCase();
    
    if (nivelLower.includes('técnico') || nivelLower.includes('tecnico')) {
        return {
            bgColor: 'bg-[#007832]',
            textColor: 'text-primary',
            badgeClass: 'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-estado-activo'
        };
    } else if (nivelLower.includes('tecnólogo') || nivelLower.includes('tecnologo')) {
        return {
            bgColor: 'bg-[#00304D]',
            textColor: 'text-info',
            badgeClass: 'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-role-parendiz'
        };
    } else {
        return {
            bgColor: 'bg-muted',
            textColor: 'text-muted-foreground',
            badgeClass: 'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-500/20 text-gray-400'
        };
    }
}

// Modifica la función openViewModal para usar los estilos según nivel
function openViewModal(index) {
    const modal = document.getElementById("viewProgramModal");
    const row =
        document.querySelector(`tr[data-index="${index}"]`) || document.querySelector(`div[data-index="${index}"]`);

    if (row) {
        document.getElementById("view_name").textContent = row.dataset.nombre;
        document.getElementById("view_code").textContent = row.dataset.codigo;
        document.getElementById("view_description").textContent = row.dataset.descripcion;
        document.getElementById("view_nivel").textContent = row.dataset.nivel;
        document.getElementById("view_duracion").textContent = row.dataset.duracion;

        // Normalize state and display human-friendly badge (Activo / Inactivo)
        const estadoAttrView = String(row.dataset.estado ?? '').trim();
        const estadoHuman = (estadoAttrView === '1' || estadoAttrView === '0')
            ? (estadoAttrView === '1' ? 'Activo' : 'Inactivo')
            : (estadoAttrView.toLowerCase() === 'activo' ? 'Activo' : 'Inactivo');

        const viewEstadoEl = document.getElementById('view_estado');
        viewEstadoEl.textContent = estadoHuman;
        if (estadoHuman === 'Activo') {
            viewEstadoEl.className = 'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-estado-activo';
        } else {
            viewEstadoEl.className = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-500/20 text-gray-400';
        }

        // ========== APLICAR ESTILOS SEGÚN NIVEL ==========
        const nivel = row.dataset.nivel;
        const levelStyles = getLevelStyles(nivel);
        
        // 1. Cambiar fondo del icono circular
        const iconContainer = modal.querySelector('.w-12.h-12');
        if (iconContainer) {
            // Remover clases anteriores de color
            iconContainer.className = iconContainer.className.replace(/bg-\[[^\]]*\]/g, '').replace(/bg-[a-z-]+/g, '');
            // Agregar nueva clase
            iconContainer.classList.add(levelStyles.bgColor);
        }
        
        // 2. Cambiar color del icono
        const icon = modal.querySelector('.fa-graduation-cap');
        if (icon) {
            // Remover clases anteriores de color
            icon.className = icon.className.replace(/text-[a-z-]+/g, '');
            // Agregar nueva clase según nivel
            if (nivel.toLowerCase().includes('técnico') || nivel.toLowerCase().includes('tecnico')) {
                icon.classList.add('text-primary');
            } else {
                icon.classList.add('text-info');
            }
        }
        
        // 3. Cambiar estilo del badge de nivel
        const nivelBadge = document.getElementById('view_nivel');
        if (nivelBadge) {
            nivelBadge.className = levelStyles.badgeClass;
        }
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

// Close view program details modal
function closeViewModal() {
  const modal = document.getElementById("viewProgramModal")
  modal.classList.add("hidden")
  modal.classList.remove("flex")
}

// Open create program modal
function openCreateModal() {
  const modal = document.getElementById("createProgramModal")
  modal.classList.remove("hidden")
  modal.classList.add("flex")
}

// Close create program modal
function closeCreateModal() {
  const modal = document.getElementById("createProgramModal")
  modal.classList.add("hidden")
  modal.classList.remove("flex")
}

// =========================
// VALIDATION FUNCTIONS
// =========================

/**
 * Validates program data before sending to server
 */
function validateProgramData(data, isEdit = false) {
  // Check required fields
  if (!data.codigo_programa || !data.nombre_programa || !data.nivel_programa || 
      !data.descripcion_programa || !data.duracion_horas) {
    toastError("Todos los campos marcados con * son obligatorios.");
    return false;
  }

  // Validate duration (must be a positive number)
  if (isNaN(data.duracion_horas) || data.duracion_horas <= 0) {
    toastError("La duración debe ser un número positivo de horas.");
    return false;
  }

  // Validate code format (optional: can be customized)
  if (data.codigo_programa.trim().length < 3) {
    toastError("El código del programa debe tener al menos 3 caracteres.");
    return false;
  }

  // Validate name length
  if (data.nombre_programa.trim().length < 5) {
    toastError("El nombre del programa debe tener al menos 5 caracteres.");
    return false;
  }

  // Validate description length
  if (data.descripcion_programa.trim().length < 10) {
    toastError("La descripción debe tener al menos 10 caracteres.");
    return false;
  }

  // Validate level - acepta versiones con y sin acentos
  const nivelNormalizado = data.nivel_programa.toLowerCase();
  const esValido = nivelNormalizado.includes('técnico') || 
                   nivelNormalizado.includes('tecnico') ||
                   nivelNormalizado.includes('tecnólogo') || 
                   nivelNormalizado.includes('tecnologo');
  
  if (!esValido) {
    toastError("El nivel debe ser 'Técnico' o 'Tecnólogo'.");
    return false;
  }

  return true;
}

/**
 * Check if there are any changes between original and current data (for edit mode)
 */
function hasChanges(originalData, currentData) {
  // Normalizar datos para comparación
  const normalize = (obj) => {
    return {
      codigo: (obj.codigo || '').trim(),
      nombre: (obj.nombre || '').trim(),
      descripcion: (obj.descripcion || '').trim(),
      nivel: (obj.nivel || '').trim(),
      duracion: (obj.duracion || '').trim()
    };
  };

  const originalNormalized = normalize(originalData);
  const currentNormalized = normalize(currentData);

  return JSON.stringify(originalNormalized) !== JSON.stringify(currentNormalized);
}

// ************************************** Programs Creation ***********************************************

document.addEventListener("DOMContentLoaded", () => {
  const pathParts = window.location.pathname.split("/")
  const basePath =
    pathParts.slice(0, pathParts.findIndex((p) => p === "views" || p === "programas.php") || -1).join("/") || ""
  const BASE_URL = window.location.origin + basePath + "/"

  console.log("[v0] BASE_URL configured as:", BASE_URL)

  // Variable para almacenar datos originales en edición
  let originalEditData = null;

  // Evento para capturar datos originales al abrir modal de edición
  document.addEventListener('click', (e) => {
    if (e.target.closest('button[onclick^="openEditModal"]') || 
        e.target.closest('button[onclick^="toggleActionMenu"] + [data-action="editar"]') ||
        e.target.closest('button[data-action="editar"]')) {
      const row = e.target.closest('tr') || e.target.closest('div[data-index]');
      if (row) {
        originalEditData = {
          codigo: row.dataset.codigo,
          nombre: row.dataset.nombre,
          descripcion: row.dataset.descripcion,
          nivel: row.dataset.nivel,
          duracion: row.dataset.duracion.replace(/[^\d]/g, '')
        };
      }
    }
  });

  // Create Program Form
  const createForm = document.getElementById("createProgramForm")
  if (createForm) {
    // Solo permitir números en el campo de duración
    const duracionInput = document.getElementById("create_duracion");
    if (duracionInput) {
      duracionInput.addEventListener("input", function(e) {
        this.value = this.value.replace(/[^\d]/g, '');
      });
    }

    createForm.addEventListener("submit", async (e) => {
      e.preventDefault()

      // Get form data
      const codigo = document.getElementById("create_codigo").value.trim();
      const nombre = document.getElementById("create_nombre").value.trim();
      const nivel = document.getElementById("create_nivel").value;
      const descripcion = document.getElementById("create_descripcion").value.trim();
      const duracionText = document.getElementById("create_duracion").value.trim();
      
      // Extract hours number from text
      const duracionHoras = Number.parseInt(duracionText.replace(/[^\d]/g, "")) || 0;
      
      const data = {
        codigo_programa: codigo,
        nombre_programa: nombre,
        nivel_programa: nivel,
        descripcion_programa: descripcion,
        duracion_horas: duracionHoras,
        estado: 1,
      }

      console.log("[v0] Creating program with data:", data)

      // Validate data
      if (!validateProgramData(data, false)) {
        return;
      }

      try {
        const response = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=crear`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(data),
        })

        const result = await response.json()
        console.log("[v0] Create response:", result)

        if (result.mensaje) {
          toastSuccess("Programa creado correctamente");
          closeCreateModal();
          setTimeout(() => {
            location.reload();
          }, 1500);
        } else {
          toastError("Error: " + (result.error || "No se pudo crear el programa"));
        }
      } catch (error) {
        console.error("[v0] Error creating program:", error);
        toastError("Error de conexión al crear el programa");
      }
    })
  }

  // ************************************** Programs Update ***********************************************

  // Edit Program Form
  const editForm = document.getElementById("editProgramForm")
  if (editForm) {
    // Solo permitir números en el campo de duración
    const editDuracionInput = document.getElementById("edit_duracion");
    if (editDuracionInput) {
      editDuracionInput.addEventListener("input", function(e) {
        this.value = this.value.replace(/[^\d]/g, '');
      });
    }

    editForm.addEventListener("submit", async (e) => {
      e.preventDefault()

      const index = document.getElementById("edit_index").value
      const row =
        document.querySelector(`tr[data-index="${index}"]`) || document.querySelector(`div[data-index="${index}"]`)

      if (!row) {
        toastError("No se encontró el programa para editar");
        return
      }

      const idPrograma = row.dataset.idPrograma
      if (!idPrograma) {
        console.error("[v0] No id_programa found in row:", row)
        toastError("No se pudo obtener el ID del programa");
        return
      }

      // Get form values
      const codigo = document.getElementById("edit_codigo").value.trim();
      const nombre = document.getElementById("edit_nombre").value.trim();
      const nivelSelect = document.getElementById("edit_nivel").value;
      const descripcion = document.getElementById("edit_descripcion").value.trim();
      const duracionText = document.getElementById("edit_duracion").value.trim();
      
      // Normalize level
      const nivelNormalized = document.getElementById("edit_nivel").value;
      
      // Extract hours number from text
      const duracionHoras = Number.parseInt(duracionText.replace(/[^\d]/g, "")) || 0;

      // Actual State from the dataset (supports '1'/'0' or 'Active'/'Inactive')
      const estadoAttrEdit = String(row.dataset.estado ?? '').trim()
      const estadoValue = (estadoAttrEdit === '1' || estadoAttrEdit === '0')
        ? Number(estadoAttrEdit)
        : (estadoAttrEdit.toLowerCase() === 'activo' ? 1 : 0)

      const currentData = {
        codigo_programa: codigo,
        nombre_programa: nombre,
        nivel_programa: nivelNormalized,
        descripcion_programa: descripcion,
        duracion_horas: duracionHoras,
      }

      // Validate data
      if (!validateProgramData({...currentData, estado: estadoValue}, true)) {
        return;
      }

      // Check if there are any changes (only for editing)
      if (originalEditData) {
        const currentDataForComparison = {
          codigo: codigo,
          nombre: nombre,
          descripcion: descripcion,
          nivel: nivelNormalized,
          duracion: duracionText
        };

        if (!hasChanges(originalEditData, currentDataForComparison)) {
          toastInfo("Para actualizar el programa es necesario modificar al menos un dato.");
          return;
        }
      }

      const data = {
        id_programa: idPrograma,
        ...currentData,
        estado: estadoValue
      }

      console.log("[v0] Updating program with data:", data)

      try {
        const response = await fetch(
          `${BASE_URL}src/controllers/programa_controller.php?accion=actualizar&id_programa=${idPrograma}`,
          {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data),
          },
        )

        const result = await response.json()
        console.log("[v0] Update response:", result)

        if (result.mensaje) {
          toastSuccess("Programa actualizado correctamente");
          closeEditModal();
          setTimeout(() => {
            location.reload();
          }, 1500);
        } else {
          toastError("Error: " + (result.error || "No se pudo actualizar el programa"));
        }
      } catch (error) {
        console.error("[v0] Error updating program:", error);
        toastError("Error de conexión al actualizar el programa");
      }
    })
  }

  // State toggle buttons (use data-action="toggle-state")
  document.querySelectorAll('[id^="actionMenu"] button[data-action="toggle-estado"]').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const row = e.target.closest('tr') || e.target.closest('div[data-index]')
      const idPrograma = row.dataset.idPrograma

      // Current state: supports '1'/'0' or 'Active'/'Inactive'
      const estadoAttr = String(row.dataset.estado ?? '').trim()
      const estadoActual = (estadoAttr === '1' || estadoAttr === '0')
        ? Number(estadoAttr)
        : (estadoAttr.toLowerCase() === 'activo' ? 1 : 0)
      const nuevoEstado = estadoActual ? 0 : 1

      const actionText = nuevoEstado ? "activar" : "desactivar";

      try {
        const res = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=cambiar_estado`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id_programa: idPrograma, estado: nuevoEstado })
        })
        const result = await res.json()
        if(result.mensaje) {
          toastSuccess(nuevoEstado ? "Programa activado correctamente." : "Programa desactivado correctamente.");
          setTimeout(() => {
            location.reload();
          }, 1500);
        } else {
          toastError(result.error || 'No se pudo cambiar el estado del programa');
        }
      } catch(err) {
        console.error(err);
        toastError('Error de conexión al cambiar el estado');
      }
    })
  })

  // State filter (table + grid)
  const selectFiltroEstado = document.getElementById('selectFiltroEstado')
  if (selectFiltroEstado) {
    const applyFilter = () => {
      const val = selectFiltroEstado.value // '' | '1' | '0'

      // Table rows
      document.querySelectorAll('#tableView tbody tr[data-index]').forEach(row => {
        const estado = String(row.dataset.estado ?? '').trim()
        if (val === '') {
          row.style.display = ''
        } else if (estado === val) {
          row.style.display = ''
        } else {
          row.style.display = 'none'
        }
      })

      // Grid cards
      document.querySelectorAll('#gridView [data-index]').forEach(card => {
        const estado = String(card.dataset.estado ?? '').trim()
        if (val === '') {
          card.style.display = ''
        } else if (estado === val) {
          card.style.display = ''
        } else {
          card.style.display = 'none'
        }
      })
    }

    selectFiltroEstado.addEventListener('change', applyFilter)
  }

  // Checkboxes in grid view
  document.querySelectorAll('#gridView input[type="checkbox"]').forEach(chk => {
    chk.addEventListener('change', async (e) => {
      const card = e.target.closest('div[data-index]')
      const idPrograma = card.dataset.idPrograma
      const nuevoEstado = e.target.checked ? 1 : 0
      
      const actionText = nuevoEstado ? "activar" : "desactivar";

      try {
        const res = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=cambiar_estado`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id_programa: idPrograma, estado: nuevoEstado })
        })
        const result = await res.json()
        if(result.mensaje) {
          toastSuccess(nuevoEstado ? "Programa activado correctamente." : "Programa desactivado correctamente.");
          setTimeout(() => {
            location.reload();
          }, 1500);
        } else {
          toastError(result.error || 'No se pudo cambiar el estado del programa');
          // Revert checkbox on error
          e.target.checked = !e.target.checked;
        }
      } catch(err) {
        console.error(err);
        toastError('Error de conexión al cambiar el estado');
        // Revert checkbox on error
        e.target.checked = !e.target.checked;
      }
    })
  })

  // ========== SEARCH AND FILTER EVENT LISTENERS ==========
  // Search input listener
  const searchInput = document.querySelector('input[placeholder="Buscar por nombre..."]')
  if (searchInput) {
    searchInput.addEventListener('input', applyFilterAndUpdateEmptyStates)
  }

  // State filter listener (already exists but enhance it)
  const filterSelect = document.getElementById('selectFiltroEstado')
  if (filterSelect) {
    filterSelect.addEventListener('change', applyFilterAndUpdateEmptyStates)
  }

  // Initial call to check empty states on page load
  applyFilterAndUpdateEmptyStates()
  
  // ========== ESC KEY TO CLOSE MODALS ==========
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" || e.key === "Esc" || e.keyCode === 27) {
      const editModal = document.getElementById("editProgramModal");
      const viewModal = document.getElementById("viewProgramModal");
      const createModal = document.getElementById("createProgramModal");
      
      if (editModal && !editModal.classList.contains("hidden")) {
        closeEditModal();
      }
      
      if (viewModal && !viewModal.classList.contains("hidden")) {
        closeViewModal();
      }
      
      if (createModal && !createModal.classList.contains("hidden")) {
        closeCreateModal();
      }
    }
  });
})