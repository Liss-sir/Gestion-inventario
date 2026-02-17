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
  let titleText = "Warning";

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
    titleText = "Success";
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
    titleText = "Information";
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
    // Note: gridView will be shown/hidden by toggleView()
    if (!gridView?.classList.contains('hidden')) {
      gridView?.classList.remove('hidden')
    }
  }
}

// ========== VARIABLES GLOBALES PARA EL MODAL DE CREACIÓN ==========
let currentStep = 1;
let selectedInstructors = []; // Array para almacenar ID de instructores seleccionados
let allInstructors = []; // Array con todos los instructores disponibles

// ========== FUNCIONES PARA EL MODAL DE 2 PASOS ==========

// Función para obtener todos los instructores
async function loadAllInstructors() {
    try {
        const response = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=obtener_instructores`);
        const result = await response.json();
        
        if (result.error) {
            toastError("Error al cargar instructores: " + result.error);
            return [];
        }
        
        // Filtrar solo instructores (cargo === 'Instructor')
        const instructors = Array.isArray(result) ? result.filter(user => 
            user.cargo === 'Instructor' && user.estado === 'activo'
        ) : [];
        
        allInstructors = instructors;
        return instructors;
    } catch (error) {
        console.error("Error loading instructors:", error);
        toastError("Error de conexión al cargar instructores");
        return [];
    }
}

// Función para renderizar la lista de instructores
function renderInstructorsList(instructors) {
    const container = document.getElementById('instructorsListContainer');
    if (!container) return;
    
    if (!instructors || instructors.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-muted-foreground">
                <i class="fas fa-users-slash text-lg mb-2"></i>
                <p>No hay instructores disponibles</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    instructors.forEach(instructor => {
        const isSelected = selectedInstructors.includes(instructor.id_usuario);
        html += `
            <div class="flex items-center justify-between p-2 hover:bg-muted rounded-md transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-primary text-sm"></i>
                    </div>
                    <div>
                        <div class="text-sm font-medium">${instructor.nombre_completo}</div>
                        <div class="text-xs text-muted-foreground">${instructor.correo}</div>
                    </div>
                </div>
                <button type="button" 
                    onclick="toggleInstructorSelection(${instructor.id_usuario})"
                    class="w-5 h-5 rounded border ${isSelected ? 'bg-secondary border-primary' : 'border-gray-400 border-2'} flex items-center justify-center">
                    ${isSelected ? '<i class="fas fa-check text-white text-xs"></i>' : ''}
                </button>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Función para renderizar la lista de instructores seleccionados
function renderSelectedInstructorsList() {
    const container = document.getElementById('selectedInstructorsList');
    const countElement = document.getElementById('selectedCount');
    
    if (!container || !countElement) return;
    
    countElement.textContent = selectedInstructors.length;
    
    if (selectedInstructors.length === 0) {
        container.innerHTML = '<p class="text-sm text-muted-foreground text-center py-4">No hay instructores seleccionados</p>';
        return;
    }
    
    let html = '<div class="space-y-2">';
    selectedInstructors.forEach(instructorId => {
        const instructor = allInstructors.find(inst => inst.id_usuario == instructorId);
        if (instructor) {
            html += `
                <div class="flex items-center justify-between p-2 bg-primary/5 rounded-md">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-user text-primary text-sm"></i>
                        <span class="text-sm">${instructor.nombre_completo}</span>
                    </div>
                    <button type="button" 
                        onclick="toggleInstructorSelection(${instructor.id_usuario})"
                        class="text-muted-foreground hover:text-destructive">
                        <span class="sr-only">Cerrar</span>
                        <svg
                            class="h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            `;
        }
    });
    html += '</div>';
    
    container.innerHTML = html;
}

// Función para alternar la selección de un instructor
function toggleInstructorSelection(instructorId) {
    const index = selectedInstructors.indexOf(instructorId);
    
    if (index === -1) {
        // Agregar instructor
        selectedInstructors.push(instructorId);
    } else {
        // Remover instructor
        selectedInstructors.splice(index, 1);
    }
    
    // Re-renderizar ambas listas
    renderInstructorsList(allInstructors);
    renderSelectedInstructorsList();
}

// ========== FUNCIONES DE VALIDACIÓN MEJORADAS ==========

/**
 * Validates that code contains at least one letter and one number
 */
function isValidCodeFormat(codigo) {
    const hasLetter = /[a-zA-Z]/g.test(codigo);
    const hasNumber = /[0-9]/g.test(codigo);
    return hasLetter && hasNumber;
}

/**
 * Checks if a code already exists in the current programs table/grid
 * excludeIndex: if provided, exclude this program from the check
 */
async function codeAlreadyExistsAPI(codigo, excludeId = null) {
    try {
        const response = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=obtener_por_codigo&codigo=${encodeURIComponent(codigo)}`);
        const result = await response.json();
        
        // Si hay un error (como que no se encontró), devolver falso
        if (result.error) {
            return false;
        }
        
        // Si se encontró un programa, verificar si es el mismo que estamos editando
        if (excludeId && result.id_programa == excludeId) {
            return false;
        }
        
        return true; // Código existe
    } catch (error) {
        console.error("Error checking code existence:", error);
        return false; // En caso de error, asumir que no existe
    }
}

/**
 * Validates program data before sending to server - for step 1 validation
 */
function validateStep1Data(data, isEdit = false, excludeId = null) {
    // Check required fields
    if (!data.codigo_programa || !data.nombre_programa || !data.nivel_programa || 
        !data.descripcion_programa || !data.duracion_horas) {
        toastError("Todos los campos marcados con * son obligatorios.");
        return false;
    }

    // Validate code length (max 10 characters)
    if (data.codigo_programa.trim().length > 10) {
        toastError("El código del programa no puede exceder los 10 caracteres.");
        return false;
    }

    // Validate code format (at least 3 characters)
    if (data.codigo_programa.trim().length < 3) {
        toastError("El código del programa debe tener al menos 3 caracteres.");
        return false;
    }

    // Validate code contains at least one letter and one number
    if (!isValidCodeFormat(data.codigo_programa)) {
        toastError("El código debe contener al menos una letra y un número.");
        return false;
    }

    // Validate name length (max 25 characters)
    if (data.nombre_programa.trim().length > 25) {
        toastError("El nombre del programa no puede exceder los 25 caracteres.");
        return false;
    }

    // Validate name length (min 5 characters)
    if (data.nombre_programa.trim().length < 5) {
        toastError("El nombre del programa debe tener al menos 5 caracteres.");
        return false;
    }

    // Validate description length (max 200 characters)
    if (data.descripcion_programa.trim().length > 200) {
        toastError("La descripción no puede exceder los 200 caracteres.");
        return false;
    }

    // Validate description length (min 10 characters)
    if (data.descripcion_programa.trim().length < 10) {
        toastError("La descripción debe tener al menos 10 caracteres.");
        return false;
    }

    // Validate duration (must be a positive number)
    if (isNaN(data.duracion_horas) || data.duracion_horas <= 0) {
        toastError("La duración debe ser un número positivo de horas.");
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

// Función para validar datos del paso 1 (síncrona para uso inmediato)
async function validateStep1(codigo, nombre, nivel, descripcion, duracionHoras, isEdit = false, excludeId = null) {
    // Crear objeto de datos para validación
    const data = {
        codigo_programa: codigo,
        nombre_programa: nombre,
        nivel_programa: nivel,
        descripcion_programa: descripcion,
        duracion_horas: duracionHoras
    };
    
    // Validar formato básico
    if (!validateStep1Data(data, isEdit, excludeId)) {
        return false;
    }
    
    // Verificar si el código ya existe (llamada a API)
    const codeExists = await codeAlreadyExistsAPI(codigo, excludeId);
    if (codeExists) {
        toastError("Ya hay un programa de formación con el código ingresado");
        return false;
    }
    
    return true;
}

// Función para avanzar al siguiente paso
async function nextStep() {
    const step1 = document.getElementById('createStep1');
    const step2 = document.getElementById('createStep2');
    const btnPrev = document.getElementById('btnPrevStep');
    const btnNext = document.getElementById('btnNextStep');
    const btnCreate = document.getElementById('btnCreateProgram');
    const step1Indicators = document.querySelectorAll('.flex-1:nth-child(1) .w-8');
    const step2Indicators = document.querySelectorAll('.flex-1:nth-child(3) .w-8');
    const step1Texts = document.querySelectorAll('.flex-1:nth-child(1) .text-xs');
    const step2Texts = document.querySelectorAll('.flex-1:nth-child(3) .text-xs');
    
    if (currentStep === 1) {
        // Obtener valores del formulario
        const codigo = document.getElementById('create_codigo').value.trim();
        const nombre = document.getElementById('create_nombre').value.trim();
        const descripcion = document.getElementById('create_descripcion').value.trim();
        const duracionText = document.getElementById('create_duracion').value.trim();
        const nivel = document.getElementById('create_nivel').value;
        
        // Convertir duración a número
        const duracionHoras = parseInt(duracionText.replace(/[^\d]/g, '')) || 0;
        
        // Validar datos del paso 1
        const isValid = await validateStep1(codigo, nombre, nivel, descripcion, duracionHoras, false, null);
        
        if (!isValid) {
            // Resaltar campos con error
            if (!codigo || codigo.length < 3 || codigo.length > 10) {
                document.getElementById('create_codigo').classList.add('border-red-500');
            }
            if (!nombre || nombre.length < 5 || nombre.length > 25) {
                document.getElementById('create_nombre').classList.add('border-red-500');
            }
            if (!descripcion || descripcion.length < 10 || descripcion.length > 200) {
                document.getElementById('create_descripcion').classList.add('border-red-500');
            }
            if (!duracionText || duracionHoras <= 0) {
                document.getElementById('create_duracion').classList.add('border-red-500');
            }
            
            // Remover clase de error después de 2 segundos
            setTimeout(() => {
                document.getElementById('create_codigo').classList.remove('border-red-500');
                document.getElementById('create_nombre').classList.remove('border-red-500');
                document.getElementById('create_descripcion').classList.remove('border-red-500');
                document.getElementById('create_duracion').classList.remove('border-red-500');
            }, 2000);
            
            return;
        }
        
        // Si pasa todas las validaciones, cambiar al paso 2
        step1.classList.add('hidden');
        step2.classList.remove('hidden');
        btnPrev.classList.remove('hidden');
        btnNext.classList.add('hidden');
        btnCreate.classList.remove('hidden');
        
        // Actualizar indicadores visuales
        step1Indicators.forEach(el => {
            el.classList.remove('bg-secondary', 'text-primary-foreground');
            el.classList.add('bg-border', 'text-muted-foreground');
        });
        
        step1Texts.forEach(el => {
            el.classList.remove('text-secondary');
            el.classList.add('text-muted-foreground');
        });
        
        step2Indicators.forEach(el => {
            el.classList.remove('bg-border', 'text-muted-foreground');
            el.classList.add('bg-secondary', 'text-primary-foreground');
        });
        
        step2Texts.forEach(el => {
            el.classList.remove('text-muted-foreground');
            el.classList.add('text-secondary');
        });
        
        currentStep = 2;
        
        // Cargar instructores si no se han cargado
        if (allInstructors.length === 0) {
            loadAllInstructors().then(instructors => {
                renderInstructorsList(instructors);
                renderSelectedInstructorsList();
            });
        } else {
            renderInstructorsList(allInstructors);
            renderSelectedInstructorsList();
        }
    }
}

// Función para retroceder al paso anterior
function prevStep() {
    const step1 = document.getElementById('createStep1');
    const step2 = document.getElementById('createStep2');
    const btnPrev = document.getElementById('btnPrevStep');
    const btnNext = document.getElementById('btnNextStep');
    const btnCreate = document.getElementById('btnCreateProgram');
    const step1Indicators = document.querySelectorAll('.flex-1:nth-child(1) .w-8');
    const step2Indicators = document.querySelectorAll('.flex-1:nth-child(3) .w-8');
    const step1Texts = document.querySelectorAll('.flex-1:nth-child(1) .text-xs');
    const step2Texts = document.querySelectorAll('.flex-1:nth-child(3) .text-xs');
    
    if (currentStep === 2) {
        // Cambiar al paso 1
        step2.classList.add('hidden');
        step1.classList.remove('hidden');
        btnPrev.classList.add('hidden');
        btnNext.classList.remove('hidden');
        btnCreate.classList.add('hidden');
        
        // Actualizar indicadores
        step1Indicators.forEach(el => el.classList.replace('bg-border', 'bg-secondary'));
        step1Indicators.forEach(el => el.classList.replace('text-muted-foreground', 'text-primary-foreground'));
        step1Texts.forEach(el => el.classList.replace('text-muted-foreground', 'text-secondary'));
        
        step2Indicators.forEach(el => el.classList.replace('bg-secondary', 'bg-border'));
        step2Indicators.forEach(el => el.classList.replace('text-primary-foreground', 'text-muted-foreground'));
        step2Texts.forEach(el => el.classList.replace('text-secondary', 'text-muted-foreground'));
        
        currentStep = 1;
    }
}

// Función para crear programa con instructores
async function createProgramWithInstructors() {
    // Obtener datos del formulario
    const codigo = document.getElementById('create_codigo').value.trim();
    const nombre = document.getElementById('create_nombre').value.trim();
    const nivel = document.getElementById('create_nivel').value;
    const descripcion = document.getElementById('create_descripcion').value.trim();
    const duracionText = document.getElementById('create_duracion').value.trim();
    const duracionHoras = parseInt(duracionText.replace(/[^\d]/g, '')) || 0;
    
    const programData = {
        codigo_programa: codigo,
        nombre_programa: nombre,
        nivel_programa: nivel,
        descripcion_programa: descripcion,
        duracion_horas: duracionHoras,
        estado: 1,
    };
    
    console.log("Creando programa con datos:", programData);
    console.log("Instructores seleccionados:", selectedInstructors);
    
    // Validar datos (no validateProgramData para incluir instructores)
    if (!validateProgramData(programData, false, null)) {
        return;
    }
    
    // Validar que haya al menos un instructor seleccionado
    if (selectedInstructors.length === 0) {
        toastError("El programa de formación a crear debe de contar con al menos un instructor vinculado");
            return;
    }
    
    try {
        // 1. Crear el programa primero
        const createResponse = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=crear`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(programData),
        });
        
        const createResult = await createResponse.json();
        console.log("Resultado creación programa:", createResult);
        
        if (createResult.error) {
            toastError("Error al crear programa: " + createResult.error);
            return;
        }
        
        // Obtener el ID del programa creado (necesitamos ajustar el controlador para devolverlo)
        if (!createResult.id_programa) {
            // Si el controlador no devuelve el ID, necesitamos buscarlo por código
            try {
                const searchResponse = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=obtener_por_codigo&codigo=${encodeURIComponent(codigo)}`);
                const searchResult = await searchResponse.json();
                
                if (searchResult.id_programa) {
                    createResult.id_programa = searchResult.id_programa;
                } else {
                    // Fallback: asumir que fue creado y hacer reload
                    toastSuccess("Programa creado correctamente");
                    closeCreateModal();
                    setTimeout(() => location.reload(), 1500);
                    return;
                }
            } catch (searchError) {
                console.error("Error buscando programa:", searchError);
                toastSuccess("Programa creado correctamente");
                closeCreateModal();
                setTimeout(() => location.reload(), 1500);
                return;
            }
        }
        
        const programId = createResult.id_programa;
        
        // 2. Asociar instructores al programa (si hay instructores seleccionados)
        if (selectedInstructors.length > 0) {
            try {
                const instructorsData = {
                    id_programa: programId,
                    instructores_ids: selectedInstructors
                };
                
                const instructorsResponse = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=asignar_instructores`, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(instructorsData),
                });
                
                const instructorsResult = await instructorsResponse.json();
                console.log("Resultado asignación instructores:", instructorsResult);
                
                if (instructorsResult.error) {
                    toastError("Programa creado pero error al asignar instructores: " + instructorsResult.error);
                } else {
                    toastSuccess("Programa creado e instructores asignados correctamente");
                }
            } catch (instructorError) {
                console.error("Error asignando instructores:", instructorError);
                toastError("Programa creado pero error al asignar instructores");
            }
        } else {
            toastSuccess("Programa creado correctamente");
        }
        
        // 3. Cerrar modal y recargar
        closeCreateModal();
        setTimeout(() => {
            location.reload();
        }, 1500);
        
    } catch (error) {
        console.error("[v0] Error creando programa:", error);
        toastError("Error de conexión al crear el programa");
    }
}

// ========== VARIABLES GLOBALES PARA EL MODAL DE EDICIÓN ==========
let currentEditStep = 1;
let selectedEditInstructors = [];
let allEditInstructors = [];
let editingProgramId = null;
let originalInstructorCount = 0; // Guardar cantidad original de instructores

// ========== FUNCIONES PARA EL MODAL DE EDICIÓN (2 PASOS) ==========

// Función para abrir el modal de edición con 2 pasos
async function openEditModal(index) {
    const row = document.querySelector(`tr[data-index="${index}"]`) || document.querySelector(`div[data-index="${index}"]`);
    
    if (!row) {
        toastError("No se encontró el programa para editar");
        return;
    }

    // Obtener datos del programa
    const idPrograma = row.dataset.idPrograma;
    const codigo = row.dataset.codigo;
    const nombre = row.dataset.nombre;
    const descripcion = row.dataset.descripcion;
    const nivel = row.dataset.nivel;
    const duracion = row.dataset.duracion.replace(/[^\d]/g, '');
    const estado = row.dataset.estado;

    // Guardar ID del programa que estamos editando
    editingProgramId = idPrograma;

    // Resetear estado del modal
    currentEditStep = 1;
    selectedEditInstructors = [];
    allEditInstructors = [];
    originalInstructorCount = 0;

    // Llenar campos del formulario
    document.getElementById("edit_id_programa").value = idPrograma;
    document.getElementById("edit_index").value = index;
    document.getElementById("edit_codigo").value = codigo;
    document.getElementById("edit_nombre").value = nombre;
    document.getElementById("edit_descripcion").value = descripcion;
    document.getElementById("edit_nivel").value = nivel;
    document.getElementById("edit_duracion").value = duracion;

    // Actualizar UI del paso 1
    document.getElementById('editStep1').classList.remove('hidden');
    document.getElementById('editStep2').classList.add('hidden');
    document.getElementById('editBtnPrevStep').classList.add('hidden');
    document.getElementById('editBtnNextStep').classList.remove('hidden');
    document.getElementById('editBtnSaveProgram').classList.add('hidden');

    // Actualizar indicadores
    document.getElementById('editStep1Indicator').classList.replace('bg-border', 'bg-secondary');
    document.getElementById('editStep1Indicator').classList.replace('text-muted-foreground', 'text-primary-foreground');
    document.getElementById('editStep2Indicator').classList.replace('bg-secondary', 'bg-border');
    document.getElementById('editStep2Indicator').classList.replace('text-primary-foreground', 'text-muted-foreground');

    // Cargar instructores existentes del programa
    await loadProgramInstructors(idPrograma);

    // Mostrar modal
    const modal = document.getElementById("editProgramModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

// Función para cargar instructores del programa
async function loadProgramInstructors(programId) {
    try {
        // Obtener instructores actuales del programa
        const programInstructorsResponse = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=obtener_instructores_programa&id_programa=${programId}`);
        const programInstructors = await programInstructorsResponse.json();

        // Obtener todos los instructores disponibles
        const allInstructorsResponse = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=obtener_instructores`);
        const allInstructors = await allInstructorsResponse.json();

        if (programInstructors.error || allInstructors.error) {
            toastError("Error al cargar instructores: " + (programInstructors.error || allInstructors.error));
            return;
        }

        // Guardar instructores
        allEditInstructors = Array.isArray(allInstructors) ? allInstructors : [];
        
        // Establecer instructores seleccionados
        selectedEditInstructors = Array.isArray(programInstructors) 
            ? programInstructors.map(instructor => instructor.id_usuario)
            : [];
        
        // Guardar cantidad original de instructores
        originalInstructorCount = selectedEditInstructors.length;

        // Renderizar listas
        renderEditInstructorsList(allEditInstructors);
        renderEditSelectedInstructorsList();
        
    } catch (error) {
        console.error("Error loading program instructors:", error);
        toastError("Error de conexión al cargar instructores");
    }
}

// Función para renderizar la lista de instructores en edición
function renderEditInstructorsList(instructors) {
    const container = document.getElementById('editInstructorsListContainer');
    if (!container) return;
    
    if (!instructors || instructors.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-muted-foreground">
                <i class="fas fa-users-slash text-lg mb-2"></i>
                <p>No hay instructores disponibles</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    instructors.forEach(instructor => {
        const isSelected = selectedEditInstructors.includes(instructor.id_usuario);
        // Deshabilitar botón si solo queda 1 instructor seleccionado y este es el último
        const isDisabled = selectedEditInstructors.length === 1 && isSelected;
        
        html += `
            <div class="flex items-center justify-between p-2 hover:bg-muted rounded-md transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-primary text-sm"></i>
                    </div>
                    <div>
                        <div class="text-sm font-medium">${instructor.nombre_completo}</div>
                        <div class="text-xs text-muted-foreground">${instructor.correo}</div>
                    </div>
                </div>
                <button type="button" 
                    onclick="toggleEditInstructorSelection(${instructor.id_usuario})"
                    class="w-5 h-5 rounded border ${isSelected ? 'bg-secondary border-primary' : 'border-gray-400 border-2'} flex items-center justify-center ${isDisabled ? 'opacity-50 cursor-not-allowed' : ''}"
                    ${isDisabled ? 'disabled' : ''}>
                    ${isSelected ? '<i class="fas fa-check text-white text-xs"></i>' : ''}
                </button>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Función para renderizar la lista de instructores seleccionados en edición
function renderEditSelectedInstructorsList() {
    const container = document.getElementById('editSelectedInstructorsList');
    const countElement = document.getElementById('editSelectedCount');
    
    if (!container || !countElement) return;
    
    countElement.textContent = selectedEditInstructors.length;
    
    if (selectedEditInstructors.length === 0) {
        container.innerHTML = '<p class="text-sm text-muted-foreground text-center py-4">No hay instructores seleccionados</p>';
        return;
    }
    
    let html = '<div class="space-y-2">';
    selectedEditInstructors.forEach(instructorId => {
        const instructor = allEditInstructors.find(inst => inst.id_usuario == instructorId);
        if (instructor) {
            // Verificar si es el último instructor (no se puede eliminar)
            const isLastInstructor = selectedEditInstructors.length === 1;
            
            html += `
                <div class="flex items-center justify-between p-2 ${isLastInstructor ? 'bg-warning/10 border border-warning/20' : 'bg-primary/5'} rounded-md">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-user ${isLastInstructor ? 'text-warning' : 'text-primary'} text-sm"></i>
                        <span class="text-sm ${isLastInstructor ? 'text-warning font-medium' : ''}">${instructor.nombre_completo}</span>
                        ${isLastInstructor ? '<span class="text-xs bg-warning/20 text-warning px-2 py-0.5 rounded">Último</span>' : ''}
                    </div>
                    <button type="button" 
                        onclick="${isLastInstructor ? '' : `toggleEditInstructorSelection(${instructor.id_usuario})`}"
                        class="${isLastInstructor ? 'text-warning cursor-not-allowed opacity-50' : 'text-muted-foreground hover:text-destructive'}"
                        ${isLastInstructor ? 'disabled' : ''}>
                        <i class="fas ${isLastInstructor ? 'fa-info-circle' : 'fa-times'}"></i>
                    </button>
                </div>
            `;
        }
    });
    html += '</div>';
    
    container.innerHTML = html;
}

// Función para alternar la selección de un instructor en edición
function toggleEditInstructorSelection(instructorId) {
    const index = selectedEditInstructors.indexOf(instructorId);
    
    // Validar que no sea el último instructor
    if (selectedEditInstructors.length === 1 && index !== -1) {
        toastError("Debe quedar al menos un instructor vinculado al programa");
        return;
    }
    
    if (index === -1) {
        // Agregar instructor
        selectedEditInstructors.push(instructorId);
    } else {
        // Remover instructor (siempre y cuando no sea el último)
        selectedEditInstructors.splice(index, 1);
    }
    
    // Re-renderizar ambas listas
    renderEditInstructorsList(allEditInstructors);
    renderEditSelectedInstructorsList();
}

// Función para avanzar al siguiente paso en edición
async function nextEditStep() {
    if (currentEditStep === 1) {
        // Validar datos del paso 1
        const codigo = document.getElementById('edit_codigo').value.trim();
        const nombre = document.getElementById('edit_nombre').value.trim();
        const descripcion = document.getElementById('edit_descripcion').value.trim();
        const duracionText = document.getElementById('edit_duracion').value.trim();
        const nivel = document.getElementById('edit_nivel').value;
        const idPrograma = document.getElementById('edit_id_programa').value;
        
        // Convertir duración a número
        const duracionHoras = parseInt(duracionText.replace(/[^\d]/g, '')) || 0;
        
        // Validar datos del paso 1
        const isValid = await validateStep1(codigo, nombre, nivel, descripcion, duracionHoras, true, idPrograma);
        
        if (!isValid) {
            // Resaltar campos con error
            if (!codigo || codigo.length < 3 || codigo.length > 10) {
                document.getElementById('edit_codigo').classList.add('border-red-500');
            }
            if (!nombre || nombre.length < 5 || nombre.length > 25) {
                document.getElementById('edit_nombre').classList.add('border-red-500');
            }
            if (!descripcion || descripcion.length < 10 || descripcion.length > 200) {
                document.getElementById('edit_descripcion').classList.add('border-red-500');
            }
            if (!duracionText || duracionHoras <= 0) {
                document.getElementById('edit_duracion').classList.add('border-red-500');
            }
            
            // Remover clase de error después de 2 segundos
            setTimeout(() => {
                document.getElementById('edit_codigo').classList.remove('border-red-500');
                document.getElementById('edit_nombre').classList.remove('border-red-500');
                document.getElementById('edit_descripcion').classList.remove('border-red-500');
                document.getElementById('edit_duracion').classList.remove('border-red-500');
            }, 2000);
            
            return;
        }
        
        // Si pasa todas las validaciones, cambiar al paso 2
        document.getElementById('editStep1').classList.add('hidden');
        document.getElementById('editStep2').classList.remove('hidden');
        document.getElementById('editBtnPrevStep').classList.remove('hidden');
        document.getElementById('editBtnNextStep').classList.add('hidden');
        document.getElementById('editBtnSaveProgram').classList.remove('hidden');
        
        // Actualizar indicadores visuales
        document.getElementById('editStep1Indicator').classList.replace('bg-secondary', 'bg-border');
        document.getElementById('editStep1Indicator').classList.replace('text-primary-foreground', 'text-muted-foreground');
        document.getElementById('editStep2Indicator').classList.replace('bg-border', 'bg-secondary');
        document.getElementById('editStep2Indicator').classList.replace('text-muted-foreground', 'text-primary-foreground');
        
        currentEditStep = 2;
    }
}

// Función para retroceder al paso anterior en edición
function prevEditStep() {
    if (currentEditStep === 2) {
        // Cambiar al paso 1
        document.getElementById('editStep2').classList.add('hidden');
        document.getElementById('editStep1').classList.remove('hidden');
        document.getElementById('editBtnPrevStep').classList.add('hidden');
        document.getElementById('editBtnNextStep').classList.remove('hidden');
        document.getElementById('editBtnSaveProgram').classList.add('hidden');
        
        // Actualizar indicadores
        document.getElementById('editStep1Indicator').classList.replace('bg-border', 'bg-secondary');
        document.getElementById('editStep1Indicator').classList.replace('text-muted-foreground', 'text-primary-foreground');
        document.getElementById('editStep2Indicator').classList.replace('bg-secondary', 'bg-border');
        document.getElementById('editStep2Indicator').classList.replace('text-primary-foreground', 'text-muted-foreground');
        
        currentEditStep = 1;
    }
}

// Función para guardar los cambios del programa (ambos pasos)
async function saveEditedProgram() {
    const idPrograma = document.getElementById('edit_id_programa').value;
    const index = document.getElementById('edit_index').value;
    
    // Obtener datos del formulario (paso 1)
    const codigo = document.getElementById('edit_codigo').value.trim();
    const nombre = document.getElementById('edit_nombre').value.trim();
    const nivel = document.getElementById('edit_nivel').value;
    const descripcion = document.getElementById('edit_descripcion').value.trim();
    const duracionText = document.getElementById('edit_duracion').value.trim();
    const duracionHoras = parseInt(duracionText.replace(/[^\d]/g, '')) || 0;
    
    // Obtener estado actual del programa desde el dataset
    const row = document.querySelector(`tr[data-index="${index}"]`) || document.querySelector(`div[data-index="${index}"]`);
    
    if (!row) {
        toastError("No se pudo encontrar el programa para actualizar");
        return;
    }
    
    // Obtener estado del dataset
    const estadoAttr = String(row.dataset.estado ?? '').trim();
    const estado = (estadoAttr === '1' || estadoAttr === '0') 
        ? Number(estadoAttr) 
        : (estadoAttr.toLowerCase() === 'activo' ? 1 : 0);
    
    // Validar que haya al menos un instructor seleccionado
    if (selectedEditInstructors.length === 0) {
        toastError("El programa debe contar con al menos un instructor vinculado");
        return;
    }
    
    const programData = {
        id_programa: idPrograma,
        codigo_programa: codigo,
        nombre_programa: nombre,
        nivel_programa: nivel,
        descripcion_programa: descripcion,
        duracion_horas: duracionHoras,
        estado: estado, // AHORA ESTÁ DEFINIDO
    };
    
    console.log("Datos a enviar para actualizar:", programData);
    
    // Obtener datos originales para comparar cambios
    const originalData = {
        codigo: row.dataset.codigo,
        nombre: row.dataset.nombre,
        descripcion: row.dataset.descripcion,
        nivel: row.dataset.nivel,
        duracion: row.dataset.duracion.replace(/[^\d]/g, '')
    };
    
    // Verificar si hay cambios en la información básica
    const hasBasicChanges = !(
        originalData.codigo === codigo &&
        originalData.nombre === nombre &&
        originalData.descripcion === descripcion &&
        originalData.nivel === nivel &&
        originalData.duracion === duracionText
    );
    
    // Verificar cambios en instructores
    const hasInstructorChanges = await hasChangesInInstructors(row, selectedEditInstructors);
    
    // Si no hay cambios en nada, mostrar mensaje
    if (!hasBasicChanges && !hasInstructorChanges) {
        toastInfo("Para actualizar el programa es necesario modificar al menos un dato.");
        return;
    }
    
    try {
        // 1. Actualizar información básica del programa si hay cambios
        if (hasBasicChanges) {
            // Primero validar los datos
            if (!validateProgramData(programData, true, index)) {
                return;
            }
            
            const updateResponse = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=actualizar&id_programa=${idPrograma}`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(programData),
            });
            
            const updateResult = await updateResponse.json();
            
            if (updateResult.error) {
                toastError("Error al actualizar programa: " + updateResult.error);
                return;
            }
        }
        
        // 2. Actualizar instructores (siempre ejecutar para asegurar sincronización)
        const instructorsData = {
            id_programa: idPrograma,
            instructores_ids: selectedEditInstructors
        };
        
        const instructorsResponse = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=asignar_instructores`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(instructorsData),
        });
        
        const instructorsResult = await instructorsResponse.json();
        
        if (instructorsResult.error) {
            toastError("Error al actualizar instructores: " + instructorsResult.error);
        } else {
            toastSuccess("Programa actualizado correctamente");
            closeEditModal();
            setTimeout(() => {
                location.reload();
            }, 1500);
        }
        
    } catch (error) {
        console.error("Error actualizando programa:", error);
        toastError("Error de conexión al actualizar el programa");
    }
}

// Función auxiliar para verificar cambios en instructores
async function hasChangesInInstructors(row, newInstructorIds) {
    // Obtener instructores originales del programa
    const programId = row.dataset.idPrograma;
    
    try {
        const response = await fetch(`${BASE_URL}src/controllers/programa_controller.php?accion=obtener_instructores_programa&id_programa=${programId}`);
        const originalInstructors = await response.json();
        
        if (originalInstructors.error) {
            return true; // Asumir que hay cambios si hay error
        }
        
        const originalIds = Array.isArray(originalInstructors) 
            ? originalInstructors.map(instructor => instructor.id_usuario)
            : [];
        
        // Comparar arrays
        if (originalIds.length !== newInstructorIds.length) {
            return true;
        }
        
        // Ordenar y comparar
        const sortedOriginal = [...originalIds].sort();
        const sortedNew = [...newInstructorIds].sort();
        
        return JSON.stringify(sortedOriginal) !== JSON.stringify(sortedNew);
        
    } catch (error) {
        console.error("Error verificando cambios en instructores:", error);
        return true; // Asumir que hay cambios si hay error
    }
}

// Función para cerrar el modal de edición
function closeEditModal() {
    const modal = document.getElementById("editProgramModal");
    
    // Resetear estado
    currentEditStep = 1;
    selectedEditInstructors = [];
    allEditInstructors = [];
    editingProgramId = null;
    originalInstructorCount = 0;
    
    // Resetear UI
    document.getElementById('editStep1').classList.remove('hidden');
    document.getElementById('editStep2').classList.add('hidden');
    document.getElementById('editBtnPrevStep').classList.add('hidden');
    document.getElementById('editBtnNextStep').classList.remove('hidden');
    document.getElementById('editBtnSaveProgram').classList.add('hidden');
    
    // Resetear indicadores
    document.getElementById('editStep1Indicator').classList.replace('bg-border', 'bg-secondary');
    document.getElementById('editStep1Indicator').classList.replace('text-muted-foreground', 'text-primary-foreground');
    document.getElementById('editStep2Indicator').classList.replace('bg-secondary', 'bg-border');
    document.getElementById('editStep2Indicator').classList.replace('text-primary-foreground', 'text-muted-foreground');
    
    // Limpiar listas
    const instructorsList = document.getElementById('editInstructorsListContainer');
    const selectedList = document.getElementById('editSelectedInstructorsList');
    const selectedCount = document.getElementById('editSelectedCount');
    
    if (instructorsList) {
        instructorsList.innerHTML = '<div class="text-center py-4 text-muted-foreground"><i class="fas fa-spinner fa-spin"></i> Cargando instructores...</div>';
    }
    
    if (selectedList) {
        selectedList.innerHTML = '<p class="text-sm text-muted-foreground text-center py-4">No hay instructores seleccionados</p>';
    }
    
    if (selectedCount) {
        selectedCount.textContent = '0';
    }
    
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

// Función para cerrar el modal de creación
function closeCreateModal() {
    const modal = document.getElementById("createProgramModal");
    
    // Resetear estado
    currentStep = 1;
    selectedInstructors = [];
    allInstructors = [];
    
    // Resetear UI
    const step1 = document.getElementById('createStep1');
    const step2 = document.getElementById('createStep2');
    const btnPrev = document.getElementById('btnPrevStep');
    const btnNext = document.getElementById('btnNextStep');
    const btnCreate = document.getElementById('btnCreateProgram');
    
    if (step1 && step2) {
        step1.classList.remove('hidden');
        step2.classList.add('hidden');
    }
    
    if (btnPrev) btnPrev.classList.add('hidden');
    if (btnNext) btnNext.classList.remove('hidden');
    if (btnCreate) btnCreate.classList.add('hidden');
    
    // Resetear indicadores
    const step1Indicators = document.querySelectorAll('.flex-1:nth-child(1) .w-8');
    const step2Indicators = document.querySelectorAll('.flex-1:nth-child(3) .w-8');
    const step1Texts = document.querySelectorAll('.flex-1:nth-child(1) .text-xs');
    const step2Texts = document.querySelectorAll('.flex-1:nth-child(3) .text-xs');
    
    step1Indicators.forEach(el => {
        el.classList.replace('bg-border', 'bg-secondary');
        el.classList.replace('text-muted-foreground', 'text-primary-foreground');
    });
    step1Texts.forEach(el => el.classList.replace('text-muted-foreground', 'text-secondary'));
    
    step2Indicators.forEach(el => {
        el.classList.replace('bg-secondary', 'bg-border');
        el.classList.replace('text-primary-foreground', 'text-muted-foreground');
    });
    step2Texts.forEach(el => el.classList.replace('text-secondary', 'text-muted-foreground'));
    
    // Resetear formulario
    const form = document.getElementById('createProgramForm');
    if (form) form.reset();
    
    // Limpiar listas
    const instructorsList = document.getElementById('instructorsListContainer');
    const selectedList = document.getElementById('selectedInstructorsList');
    const selectedCount = document.getElementById('selectedCount');
    
    if (instructorsList) {
        instructorsList.innerHTML = '<div class="text-center py-4 text-muted-foreground"><i class="fas fa-spinner fa-spin"></i> Cargando instructores...</div>';
    }
    
    if (selectedList) {
        selectedList.innerHTML = '<p class="text-sm text-muted-foreground text-center py-4">No hay instructores seleccionados</p>';
    }
    
    if (selectedCount) {
        selectedCount.textContent = '0';
    }
    
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

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

// New function to check and show empty states according to current view
function checkAndShowEmptyStates(currentView) {
  const tableView = document.getElementById("tableView")
  const gridView = document.getElementById("gridView")
  const emptyState = document.getElementById("emptyStateProgramas")
  const emptySearch = document.getElementById("emptySearchProgramas")
  const tableBtn = document.getElementById("viewTableBtn")
  const gridBtn = document.getElementById("viewGridBtn")
  
  // Count visible elements in BOTH views
  const visibleRows = document.querySelectorAll('#tableView tbody tr[data-index]:not(.hidden)')
  const visibleCards = document.querySelectorAll('#gridView [data-index]:not(.hidden)')
  const hasVisibleItems = visibleRows.length > 0 || visibleCards.length > 0
  
  // Check if there are any programs in total in the system
  const totalRows = document.querySelectorAll('#tableView tbody tr[data-index]').length
  const totalCards = document.querySelectorAll('#gridView [data-index]').length
  const hasAnyPrograms = totalRows > 0 || totalCards > 0
  
  // Determine what to show
  if (!hasAnyPrograms) {
    // No programs in the system - show empty message
    emptyState?.classList.remove('hidden')
    emptySearch?.classList.add('hidden')
    tableView?.classList.add('hidden')
    gridView?.classList.add('hidden')
  } else if (!hasVisibleItems) {
    // There are programs but NONE match the search
    emptyState?.classList.add('hidden')
    emptySearch?.classList.remove('hidden')
    tableView?.classList.add('hidden')
    gridView?.classList.add('hidden')
  } else {
    // There are visible results - determine which view to show based on buttons
    emptyState?.classList.add('hidden')
    emptySearch?.classList.add('hidden')
    
    // Look which button is active (has bg-muted class)
    const isTableBtnActive = tableBtn?.classList.contains('bg-muted')
    const isGridBtnActive = gridBtn?.classList.contains('bg-muted')
    
    // By default, if both or none is active, show table
    if (isGridBtnActive && !isTableBtnActive) {
      // Grid button is active → show grid
      tableView?.classList.add('hidden')
      gridView?.classList.remove('hidden')
    } else {
      // Table button is active or ambiguous state → show table
      tableView?.classList.remove('hidden')
      gridView?.classList.add('hidden')
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
  
  // Determine which view is currently active
  const tableView = document.getElementById("tableView")
  const currentView = tableView.classList.contains("hidden") ? "grid" : "table"
  
  // Check and show empty states according to current view
  checkAndShowEmptyStates(currentView)
}

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

// ========== FUNCTION TO ASSIGN COLORS ACCORDING TO LEVEL ==========
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

// Busca la función openViewModal en programas.js y actualízala así:
function openViewModal(index) {
    const modal = document.getElementById("viewProgramModal");
    const row = document.querySelector(`tr[data-index="${index}"]`) || document.querySelector(`div[data-index="${index}"]`);

    if (row) {
        document.getElementById("view_name").textContent = row.dataset.nombre;
        document.getElementById("view_code").textContent = row.dataset.codigo;
        
        // Descripción con manejo de texto largo
        const descripcion = row.dataset.descripcion || 'Sin descripción';
        const descripcionElement = document.getElementById('view_description');
        descripcionElement.textContent = descripcion;
        
        // Ajustar altura automáticamente si el contenido es muy largo
        if (descripcion.length > 200) {
            descripcionElement.parentElement.classList.remove('max-h-32');
            descripcionElement.parentElement.classList.add('max-h-48');
        } else {
            descripcionElement.parentElement.classList.remove('max-h-48');
            descripcionElement.parentElement.classList.add('max-h-32');
        }
        
        document.getElementById("view_nivel").textContent = row.dataset.nivel;
        document.getElementById("view_duracion").textContent = row.dataset.duracion;

        // Display instructors in vertical list with scroll
        const instructores = row.dataset.instructores;
        const instructoresList = document.getElementById('view_instructores_list');
        const noInstructoresMsg = document.getElementById('view_no_instructores');
        const instructoresCount = document.getElementById('view_instructores_count');
        const container = document.getElementById('view_instructores_container');

        // Clear previous list
        instructoresList.innerHTML = '';

        // Obtener número de instructores del dataset
        const numInstructores = row.dataset.numInstructores || '0';
        instructoresCount.textContent = numInstructores;

        // Check if instructors data exists and is valid
        if (!instructores || 
            instructores === 'No hay instructores vinculados' || 
            instructores === '0' || 
            instructores.trim() === '' ||
            instructores === 'undefined' ||
            numInstructores === '0') {
            
            // No instructors
            noInstructoresMsg.classList.remove('hidden');
            instructoresList.classList.add('hidden');
            instructoresCount.textContent = '0';
            
        } else {
            // Try different separators since we don't know the exact format
            let instructorsArray = [];
            
            // Primero, intentar separar por coma
            if (instructores.includes(',')) {
                instructorsArray = instructores.split(',').map(instructor => instructor.trim());
            } 
            // Si no hay comas, intentar otros separadores comunes
            else if (instructores.includes(';')) {
                instructorsArray = instructores.split(';').map(instructor => instructor.trim());
            }
            // Si no hay ningún separador, tomar todo como un solo instructor
            else {
                instructorsArray = [instructores.trim()];
            }
            
            // Filtrar elementos vacíos
            instructorsArray = instructorsArray.filter(instructor => 
                instructor && 
                instructor !== 'undefined' && 
                instructor !== '0' && 
                instructor.toLowerCase() !== 'no hay instructores vinculados'
            );
            
            // Actualizar contador con el número real
            instructoresCount.textContent = instructorsArray.length.toString();
            
            // Verificar si después del filtrado quedan instructores
            if (instructorsArray.length === 0) {
                noInstructoresMsg.classList.remove('hidden');
                instructoresList.classList.add('hidden');
            } else {
                // Hide "no instructors" message
                noInstructoresMsg.classList.add('hidden');
                instructoresList.classList.remove('hidden');
                
                // Ajustar altura según cantidad de instructores
                if (instructorsArray.length > 5) {
                    container.classList.add('max-h-56');
                } else if (instructorsArray.length > 3) {
                    container.classList.add('max-h-48');
                } else {
                    container.classList.add('max-h-40');
                }
                
                // Create list items for each instructor
                instructorsArray.forEach(instructor => {
                    if (instructor && instructor.trim() !== '') {
                        const li = document.createElement('li');
                        li.className = 'flex items-center gap-2 text-foreground py-2 px-2 hover:bg-muted/30 rounded transition-colors border-b border-border/30 last:border-b-0';
                        li.innerHTML = `
                            <div class="flex-shrink-0 w-6 h-6 bg-primary/10 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                </svg>
                            </div>
                            <span class="text-sm break-words flex-1">${instructor}</span>
                        `;
                        instructoresList.appendChild(li);
                    }
                });
            }
        }

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

        // ========== APPLY STYLES ACCORDING TO LEVEL ==========
        const nivel = row.dataset.nivel;
        const levelStyles = getLevelStyles(nivel);
        
        // 1. Change circular icon background
        const iconContainer = modal.querySelector('.w-12.h-12');
        if (iconContainer) {
            // Remove previous color classes
            iconContainer.className = iconContainer.className.replace(/bg-\[[^\]]*\]/g, '').replace(/bg-[a-z-]+/g, '');
            // Add new class
            iconContainer.classList.add(levelStyles.bgColor);
        }
        
        // 2. Change icon color
        const icon = modal.querySelector('.fa-graduation-cap');
        if (icon) {
            // Remove previous color classes
            icon.className = icon.className.replace(/text-[a-z-]+/g, '');
            // Add new class according to level
            if (nivel.toLowerCase().includes('técnico') || nivel.toLowerCase().includes('tecnico')) {
                icon.classList.add('text-primary');
            } else {
                icon.classList.add('text-info');
            }
        }
        
        // 3. Change level badge style
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

// =========================
// VALIDATION FUNCTIONS (existing ones)
// =========================

/**
 * Validates that code contains at least one letter and one number
 */
function isValidCodeFormat(codigo) {
  const hasLetter = /[a-zA-Z]/g.test(codigo);
  const hasNumber = /[0-9]/g.test(codigo);
  return hasLetter && hasNumber;
}

/**
 * Checks if a code already exists in the current programs table/grid
 * excludeIndex: if provided, exclude this program from the check
 */
function codeAlreadyExists(codigo, excludeIndex = null) {
  // Get all table rows and grid cards
  const tableRows = document.querySelectorAll('#tableView tbody tr[data-index]');
  const gridCards = document.querySelectorAll('#gridView [data-index]');
  
  // Check table rows
  for (let row of tableRows) {
    const rowIndex = row.dataset.index;
    const rowCodigo = (row.dataset.codigo || '').trim();
    
    // Skip if this is the row being edited
    if (excludeIndex !== null && rowIndex === excludeIndex) {
      continue;
    }
    
    if (rowCodigo.toLowerCase() === codigo.toLowerCase()) {
      return true;
    }
  }
  
  // Check grid cards
  for (let card of gridCards) {
    const cardIndex = card.dataset.index;
    const cardCodigo = (card.dataset.codigo || '').trim();
    
    // Skip if this is the card being edited
    if (excludeIndex !== null && cardIndex === excludeIndex) {
      continue;
    }
    
    if (cardCodigo.toLowerCase() === codigo.toLowerCase()) {
      return true;
    }
  }
  
  return false;
}

/**
 * Validates program data before sending to server
 */
function validateProgramData(data, isEdit = false, excludeIndex = null) {
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

  // Validate code format (at least 3 characters)
  if (data.codigo_programa.trim().length < 3) {
    toastError("El código del programa debe tener al menos 3 caracteres.");
    return false;
  }

  // Validate code contains at least one letter and one number
  if (!isValidCodeFormat(data.codigo_programa)) {
    toastError("El código debe contener al menos una letra y un número.");
    return false;
  }

  // Check if code already exists (excluding current program in edit mode)
  if (codeAlreadyExists(data.codigo_programa, isEdit ? excludeIndex : null)) {
    toastError("Ya hay un programa de formación con el código ingresado");
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

// ************************************** Main DOMContentLoaded ***********************************************

document.addEventListener("DOMContentLoaded", () => {
  const pathParts = window.location.pathname.split("/")
  const basePath =
    pathParts.slice(0, pathParts.findIndex((p) => p === "views" || p === "programas.php") || -1).join("/") || ""
  const BASE_URL = window.location.origin + basePath + "/"

  console.log("[v0] BASE_URL configured as:", BASE_URL)

  // Variable to store original data when editing
  let originalEditData = null;

  // Event to capture original data when opening edit modal
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

  // ========== MODAL DE CREACIÓN (2 PASOS) ==========
  
  // Agregar event listeners para los botones del modal de creación
  const btnNextStep = document.getElementById('btnNextStep');
  const btnPrevStep = document.getElementById('btnPrevStep');
  const btnCreateProgram = document.getElementById('btnCreateProgram');
  
  if (btnNextStep) {
    btnNextStep.addEventListener('click', nextStep);
  }
  
  if (btnPrevStep) {
    btnPrevStep.addEventListener('click', prevStep);
  }
  
  if (btnCreateProgram) {
    btnCreateProgram.addEventListener('click', createProgramWithInstructors);
  }

  // Create Program Form (paso 1)
  const createForm = document.getElementById("createProgramForm")
  if (createForm) {
    // Only allow numbers in the duration field
    const duracionInput = document.getElementById("create_duracion");
    if (duracionInput) {
      duracionInput.addEventListener("input", function(e) {
        this.value = this.value.replace(/[^\d]/g, '');
      });
    }
    
    // Validación en tiempo real para límites de caracteres
    const codigoInput = document.getElementById("create_codigo");
    if (codigoInput) {
      codigoInput.addEventListener("input", function(e) {
        if (this.value.length > 10) {
          this.value = this.value.substring(0, 10);
          toastError("El código no puede exceder los 10 caracteres");
        }
      });
    }
    
    const nombreInput = document.getElementById("create_nombre");
    if (nombreInput) {
      nombreInput.addEventListener("input", function(e) {
        if (this.value.length > 25) {
          this.value = this.value.substring(0, 25);
          toastError("El nombre no puede exceder los 25 caracteres");
        }
      });
    }
    
    const descripcionInput = document.getElementById("create_descripcion");
    if (descripcionInput) {
      descripcionInput.addEventListener("input", function(e) {
        if (this.value.length > 200) {
          this.value = this.value.substring(0, 200);
          toastError("La descripción no puede exceder los 200 caracteres");
        }
      });
    }
  }

  // ========== MODAL DE EDICIÓN (2 PASOS) ==========
  
  // Agregar event listeners para los botones del modal de edición
  const editBtnNextStep = document.getElementById('editBtnNextStep');
  const editBtnPrevStep = document.getElementById('editBtnPrevStep');
  const editBtnSaveProgram = document.getElementById('editBtnSaveProgram');
  
  if (editBtnNextStep) {
    editBtnNextStep.addEventListener('click', nextEditStep);
  }
  
  if (editBtnPrevStep) {
    editBtnPrevStep.addEventListener('click', prevEditStep);
  }
  
  if (editBtnSaveProgram) {
    editBtnSaveProgram.addEventListener('click', saveEditedProgram);
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

  // In the DOMContentLoaded event, add this initialization:
  const tableView = document.getElementById("tableView")
  const initialView = tableView.classList.contains("hidden") ? "grid" : "table"
  checkAndShowEmptyStates(initialView)
})