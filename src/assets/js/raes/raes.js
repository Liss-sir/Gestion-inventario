// =========================
// CONFIG: CONTROLLER ENDPOINTS
// =========================
const RAE_API_URL = "src/controllers/rae_controller.php";
const PROGRAMAS_API_URL = "src/controllers/programa_controller.php";

// Variables globales
let currentRaes = [];
let originalEditData = null; // Para validación de cambios en edición

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

// =========================
// FUNCIONES EXISTENTES CON ALERTAS ACTUALIZADAS
// =========================

// Función para alternar entre vista de tabla y vista de grid
function toggleView(view) {
    const tableView = document.getElementById("tableView");
    const gridView = document.getElementById("gridView");
    const tableBtn = document.getElementById("viewTableBtn");
    const gridBtn = document.getElementById("viewGridBtn");
    const emptyState = document.getElementById("emptyStateRaes");
    const emptySearch = document.getElementById("emptySearchRaes");
    
    // Cerrar todos los menús desplegables al cambiar de vista
    const allMenus = document.querySelectorAll('[id^="actionMenu"]');
    allMenus.forEach((menu) => menu.classList.add("hidden"));
    
    // Si no hay RAEs, mantener estado vacío
    if (!window.currentRaes || window.currentRaes.length === 0) {
        emptyState?.classList.remove("hidden");
        emptySearch?.classList.add("hidden");
        tableView?.classList.add("hidden");
        gridView?.classList.add("hidden");
        return;
    }
    
    // Verificar si hay filtros activos
    const searchText = document.getElementById("searchRae").value.toLowerCase().trim();
    const nivelFilter = document.getElementById("selectFiltroNivel").value;
    const hasActiveFilters = searchText !== "" || nivelFilter !== "";
    
    if (hasActiveFilters) {
        // Si hay filtros, usar updateFilter que ya maneja estados vacíos
        updateFilter();
        return;
    }
    
    if (view === "table") {
        // Mostrar vista de tabla
        tableView.classList.remove("hidden");
        gridView.classList.add("hidden");
        tableBtn.classList.add("bg-muted");
        gridBtn.classList.remove("bg-muted");
    } else {
        // Mostrar vista de grid
        tableView.classList.add("hidden");
        gridView.classList.remove("hidden");
        tableBtn.classList.remove("bg-muted");
        gridBtn.classList.add("bg-muted");
    }
    
    // Asegurar que los estados vacíos estén ocultos cuando hay vista activa
    emptyState?.classList.add("hidden");
    emptySearch?.classList.add("hidden");
}

// Función para mostrar/ocultar el menú de acciones
function toggleActionMenu(id) {
    const menu = document.getElementById("actionMenu" + id);
    const allMenus = document.querySelectorAll('[id^="actionMenu"]');

    // Cerrar todos los demás menús
    allMenus.forEach((m) => {
        if (m.id !== "actionMenu" + id) {
            m.classList.add("hidden");
        }
    });

    // Toggle del menú actual
    menu.classList.toggle("hidden");
}

// Cerrar menús al hacer clic fuera de ellos
document.addEventListener("click", (event) => {
    const isMenuButton = event.target.closest('[onclick^="toggleActionMenu"]');
    const isInsideMenu = event.target.closest('[id^="actionMenu"]');

    if (!isMenuButton && !isInsideMenu) {
        const allMenus = document.querySelectorAll('[id^="actionMenu"]');
        allMenus.forEach((menu) => menu.classList.add("hidden"));
    }
});

function openDetailsModal(id, descripcion, programa, estado, codigo) {
    // Cerrar todos los menús desplegables
    const allMenus = document.querySelectorAll('[id^="actionMenu"]');
    allMenus.forEach((menu) => menu.classList.add("hidden"));

    // Decodificar valores en caso de que vengan codificados desde el HTML
    try {
        descripcion = decodeURIComponent(descripcion);
    } catch (e) {}
    try {
        programa = decodeURIComponent(programa);
    } catch (e) {}
    try {
        // Decodificar el código si existe
        if (codigo) {
            codigo = decodeURIComponent(codigo);
        }
    } catch (e) {}

    // Actualizar contenido del modal
    document.getElementById("detailsRaeCode").textContent = codigo ? `RAE # ${codigo}` : `RAE #${id}`;
    document.getElementById("detailsRaeDescription").textContent = descripcion;
    document.getElementById("detailsPrograma").textContent = programa;

    // Actualizar badge de estado
    const statusBadge = document.getElementById("detailsRaeStatus");
    if (estado.toLowerCase() === "activo") {
        statusBadge.className =
            "inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-estado-activo";
        statusBadge.textContent = "Activo";
    } else {
        statusBadge.className =
            "inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-500/20 text-gray-400";
        statusBadge.textContent = "Inactivo";
    }

    // Mostrar modal
    document.getElementById("detailsModal").classList.remove("hidden");
}

function closeDetailsModal() {
    document.getElementById("detailsModal").classList.add("hidden");
}

function openEditModal(id, descripcion, programa, codigo, programId) {
    // Cerrar todos los menús desplegables
    const allMenus = document.querySelectorAll('[id^="actionMenu"]');
    allMenus.forEach((menu) => menu.classList.add("hidden"));

    // Decodificar valores en caso de que vengan codificados desde el HTML
    try {
        descripcion = decodeURIComponent(descripcion);
    } catch (e) {}
    try {
        programa = decodeURIComponent(programa);
    } catch (e) {}
    try {
        codigo = decodeURIComponent(codigo);
    } catch (e) {}

    // Actualizar contenido del formulario
    const idEl = document.getElementById("editRaeId");
    const codigoEl = document.getElementById("editRaeCodigo");
    if (idEl) idEl.value = id || "";
    if (codigoEl) codigoEl.value = codigo || "";

    document.getElementById("editRaeDescription").value = descripcion;
    const programSelect = document.getElementById("editRaeProgram");
    if (programSelect) {
        // Preferir programId si viene (valor numérico que coincide con option.value)
        if (programId !== undefined && programId !== null && programId !== "") {
            programSelect.value = programId;
        } else {
            // Intentar seleccionar por texto (nombre del programa)
            const name = programa || "";
            let matched = false;
            for (const opt of Array.from(programSelect.options)) {
                if (opt.textContent.trim() === decodeURIComponent(name).trim()) {
                    programSelect.value = opt.value;
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                // dejar vacío
                programSelect.value = "";
            }
        }
    }

    // Guardar datos originales para validación de cambios
    originalEditData = {
        codigo_rae: codigo || "",
        descripcion_rae: descripcion || "",
        id_programa: programId || "",
    };

    // Mostrar modal
    document.getElementById("editModal").classList.remove("hidden");
}

function closeEditModal() {
    document.getElementById("editModal").classList.add("hidden");
    originalEditData = null; // Limpiar datos originales
}

function openCreateModal() {
    // Limpiar campos del formulario
    document.getElementById("createRaeCodigo").value = "";
    document.getElementById("createRaeProgram").value = "";
    document.getElementById("createRaeDescription").value = "";

    // Mostrar modal
    document.getElementById("createModal").classList.remove("hidden");
}

function closeCreateModal() {
    document.getElementById("createModal").classList.add("hidden");
}

// Cerrar modales al hacer clic fuera de ellos
document
    .getElementById("detailsModal")
    .addEventListener("click", function (event) {
        if (event.target === this) {
            closeDetailsModal();
        }
    });

document.getElementById("editModal").addEventListener("click", function (event) {
    if (event.target === this) {
        closeEditModal();
    }
});

document
    .getElementById("createModal")
    .addEventListener("click", function (event) {
        if (event.target === this) {
            closeCreateModal();
        }
    });

// Cerrar modales con la tecla Escape
document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeDetailsModal();
        closeEditModal();
        closeCreateModal();
    }
});

// -------------------------
// Carga dinámica de RAEs
// -------------------------

function _getField(obj, ...names) {
    for (const n of names) {
        if (obj[n] !== undefined && obj[n] !== null) return obj[n];
    }
    return "";
}

async function loadRaes() {
    try {
        const res = await fetch(`${RAE_API_URL}?accion=listar`, {
            headers: { Accept: "application/json" },
        });
        if (!res.ok) throw new Error("Error al obtener RAEs");
        const data = await res.json();
        
        // Almacenar los RAEs para búsqueda
        window.currentRaes = data;
        
        // Inicializar estados vacíos si no hay datos
        if (!data || data.length === 0) {
            const emptyState = document.getElementById("emptyStateRaes");
            const emptySearch = document.getElementById("emptySearchRaes");
            const tableView = document.getElementById("tableView");
            const gridView = document.getElementById("gridView");
            
            if (emptyState) emptyState.classList.remove("hidden");
            if (emptySearch) emptySearch.classList.add("hidden");
            if (tableView) tableView.classList.add("hidden");
            if (gridView) gridView.classList.add("hidden");
            return;
        }
        
        // Si hay datos, renderizar
        renderTable(data);
        renderGrid(data);
        
        // Aplicar filtros iniciales si existen
        updateFilter();
        
    } catch (err) {
        console.error(err);
        toastError("Error al cargar los RAEs. Intente nuevamente.");
    }
}

// Cargar programas activos para los selects (creación/edición)
async function loadPrograms() {
    try {
        const res = await fetch(`${PROGRAMAS_API_URL}?accion=listar`, {
            headers: { Accept: "application/json" },
        });
        if (!res.ok) throw new Error("Error al obtener programas");
        const data = await res.json();
        // Filtrar activos (campo puede ser 'estado')
        const activos = data.filter(
            (p) => (p.estado || "").toString().toLowerCase() === "activo"
        );

        // Guardar mapa id->nombre para uso en renderizado de RAEs
        window._programMap = {};
        activos.forEach((p) => {
            const id = p.id_programa ?? p.id ?? p.id_programa;
            const nombre = p.nombre_programa ?? p.nombre ?? p.codigo_programa ?? "";
            if (id != null) window._programMap[id] = nombre;
        });

        // Helper para poblar un select
        function populate(selectId, items) {
            const sel = document.getElementById(selectId);
            if (!sel) return;
            sel.innerHTML = '<option value="">Selecciona un programa</option>';
            items.forEach((p) => {
                const id = p.id_programa ?? p.id ?? p.id_programa;
                const nombre = p.nombre_programa ?? p.nombre ?? p.codigo_programa ?? "";
                const opt = document.createElement("option");
                opt.value = id;
                opt.textContent = nombre;
                sel.appendChild(opt);
            });
        }

        populate("createRaeProgram", activos);
        populate("editRaeProgram", activos);
    } catch (err) {
        console.error(err);
        toastError("Error al cargar los programas de formación.");
    }
}

// Crear RAE: leer inputs y enviar a la API
async function createRae() {
    const codigo = (document.getElementById("createRaeCodigo")?.value || "").trim();
    const id_programa = (document.getElementById("createRaeProgram")?.value || "").trim();
    const descripcion = (
        document.getElementById("createRaeDescription")?.value || ""
    ).trim();

    // Validación de campos obligatorios
    if (!codigo) {
        toastError("El código del RAE es obligatorio");
        document.getElementById("createRaeCodigo").focus();
        return;
    }

    if (!id_programa) {
        toastError("Selecciona un programa de formación");
        document.getElementById("createRaeProgram").focus();
        return;
    }

    if (!descripcion) {
        toastError("La descripción del RAE es obligatoria");
        document.getElementById("createRaeDescription").focus();
        return;
    }

    const payload = {
        codigo_rae: codigo,
        descripcion_rae: descripcion,
        id_programa: Number.parseInt(id_programa, 10),
        estado: "Activo",
    };

    try {
        const res = await fetch(`${RAE_API_URL}?accion=crear`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (!res.ok || data.error) {
            console.error(data);
            toastError(data.error || data || "Error al crear RAE");
            return;
        }

        // Éxito
        toastSuccess(data.message || data.mensaje || "RAE creada correctamente");
        closeCreateModal();
        loadRaes();
    } catch (err) {
        console.error(err);
        toastError("Error en la solicitud de creación");
    }
}

// Actualizar RAE (edición) - CON VALIDACIÓN DE CAMBIOS
async function updateRae() {
    const id = (document.getElementById("editRaeId")?.value || "").trim();
    const descripcion = (
        document.getElementById("editRaeDescription")?.value || ""
    ).trim();
    const codigo = (document.getElementById("editRaeCodigo")?.value || "").trim();
    const id_programa = (document.getElementById("editRaeProgram")?.value || "").trim();

    // Validación de campos obligatorios
    if (!id) {
        toastError("ID del RAE faltante");
        return;
    }

    if (!descripcion) {
        toastError("La descripción del RAE es obligatoria");
        document.getElementById("editRaeDescription").focus();
        return;
    }

    // Validación de cambios en modo edición
    if (originalEditData) {
        const currentData = {
            codigo_rae: codigo,
            descripcion_rae: descripcion,
            id_programa: id_programa,
        };

        const noHayCambios =
            JSON.stringify(currentData) === JSON.stringify(originalEditData);

        if (noHayCambios) {
            toastInfo(
                "Para actualizar el registro es necesario modificar al menos un dato del RAE."
            );
            return;
        }
    }

    const payload = {
        id_rae: Number.parseInt(id, 10),
        descripcion_rae: descripcion,
    };
    if (codigo) payload.codigo_rae = codigo;
    if (id_programa) payload.id_programa = Number.parseInt(id_programa, 10);

    try {
        const res = await fetch(`${RAE_API_URL}?accion=actualizar`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (!res.ok || data.error) {
            console.error(data);
            toastError(data.error || data || "Error al actualizar RAE");
            return;
        }

        toastSuccess(data.mensaje || data.message || "RAE actualizado correctamente");
        closeEditModal();
        loadRaes();
    } catch (err) {
        console.error(err);
        toastError("Error en la solicitud de actualización");
    }
}

// Cambiar estado de un RAE (activar / inactivar)
async function changeRaeEstado(id, estado) {
    if (!id) {
        toastError("ID del RAE faltante");
        return;
    }

    const payload = {
        id_rae: Number.parseInt(id, 10),
        estado: estado,
    };

    try {
        const res = await fetch(`${RAE_API_URL}?accion=cambiar_estado`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (!res.ok || data.error) {
            console.error(data);
            toastError(data.error || data || "Error al cambiar estado");
            return;
        }

        toastSuccess(
            estado === "Activo"
                ? "RAE activado correctamente."
                : "RAE desactivado correctamente."
        );
        // Refrescar listado para actualizar UI
        loadRaes();
    } catch (err) {
        console.error(err);
        toastError("Error en la solicitud de cambio de estado");
    }
}

function renderTable(items) {
    const tbody = document.getElementById("raesTableBody");
    if (!tbody) return;
    
    const emptyState = document.getElementById("emptyStateRaes");
    const emptySearch = document.getElementById("emptySearchRaes");
    const tableView = document.getElementById("tableView");
    const gridView = document.getElementById("gridView");
    
    // Limpiar contenido previo
    tbody.innerHTML = "";
    
    if (!items || items.length === 0) {
        // Verificar si estamos filtrando o si realmente no hay RAEs
        const searchInput = document.getElementById("searchRae");
        const levelFilter = document.getElementById("selectFiltroNivel");
        const hasActiveFilters = (searchInput && searchInput.value.trim() !== "") || 
                                (levelFilter && levelFilter.value !== "");
        
        if (hasActiveFilters) {
            // Hay filtros activos pero no resultados
            emptySearch?.classList.remove("hidden");
            emptyState?.classList.add("hidden");
        } else {
            // No hay RAEs en el sistema
            emptyState?.classList.remove("hidden");
            emptySearch?.classList.add("hidden");
        }
        
        // Ocultar vistas
        tableView?.classList.add("hidden");
        gridView?.classList.add("hidden");
        return;
    }
    
    // Hay RAEs, ocultar estados vacíos
    emptyState?.classList.add("hidden");
    emptySearch?.classList.add("hidden");

    items.forEach((r, idx) => {
        const id = _getField(r, "id", "id_rae");
        const codigo = _getField(r, "codigo_rae", "codigo");
        const descripcion = _getField(r, "descripcion", "descripcion_rae");
        let programa = _getField(r, "programa", "nombre_programa") || "";
        // id del programa (si viene)
        const pid = _getField(r, "id_programa", "id_programa", "id_programa");

        // Obtener nivel del programa
        let nivelPrograma = "";
        if (pid != null && window._programLevelMap && window._programLevelMap[pid]) {
            nivelPrograma = window._programLevelMap[pid];
        }

        // si no viene el nombre, intentar mapear por id usando _programMap
        if (!programa) {
            if (pid != null && window._programMap && window._programMap[pid]) {
                programa = window._programMap[pid];
            } else if (pid != null) {
                programa = pid;
            }
        }
        const estado = _getField(r, "estado") || "";
        const ed = encodeURIComponent(descripcion);
        const ep = encodeURIComponent(programa);

        const tr = document.createElement("tr");
        tr.className = "border-b border-border hover:bg-muted transition-colors";
        tr.setAttribute("data-pid", pid || "");
        tr.setAttribute("data-nivel", nivelPrograma || "");

        tr.innerHTML = `
            <td class="py-4 px-4 text-sm font-medium text-foreground">${
                codigo || id
            }</td>
            <td class="py-4 px-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-avatar-secondary-39 rounded-md flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#007832" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open-icon lucide-book-open"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>
                    </div>
                    <span class="text-sm font-medium">${descripcion}</span>
                </div>
            </td>
            <td class="py-4 px-4">
                <div class="flex items-center gap-2 text-sm font-medium">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-graduation-cap-icon lucide-graduation-cap"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>
                    <span>${programa}</span>
                </div>
            </td>
            <td class="py-4 px-4">
                ${
                    estado.toLowerCase() === "activo"
                        ? `<span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-estado-activo">${estado}</span>`
                        : `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-500/20 text-gray-400">${estado}</span>`
                }
            </td>
            <td class="py-4 px-4">
                <div class="relative">
                    <button onclick="toggleActionMenu(${idx})" class="text-muted-foreground hover:text-foreground transition-colors p-2 hover:bg-muted rounded">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <div id="actionMenu${idx}" class="hidden absolute right-0 mt-2 w-48 rounded-xl border border-border bg-popover shadow-md py-1 z-50">
                        <button onclick="openDetailsModal('${id}', '${ed}', '${ep}', '${estado}', '${codigo ? encodeURIComponent(codigo) : ""}')" class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted transition-colors">
                            <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M1 12S4.5 5 12 5s11 7 11 7-3.5 7-11 7S1 12 1 12z"/>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            Ver detalles
                        </button>

                        <button onclick="openEditModal('${id}', '${ed}', '${ep}', '${
            codigo ? encodeURIComponent(codigo) : ""
        }', '${pid ?? ""}')" class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted transition-colors">
                            <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.121 2.121 0 0 1 3 3L9 17l-4 1 1-4 10.5-10.5z"/>
                            </svg>
                            Editar
                        </button>
                        
                        <hr class="border-border my-1">
                        
                        <button onclick="changeRaeEstado('${id}', '${
            estado.toLowerCase() === "activo" ? "Inactivo" : "Activo"
        }')" class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="mr-2 h-4 w-4">
                                <path d="M12 2v10"/>
                                <path d="M18.4 6.6a9 9 0 1 1-12.77.04"/>
                            </svg>
                            ${
                                estado.toLowerCase() === "activo"
                                    ? "Deshabilitar"
                                    : "Habilitar"
                            }
                        </button>
                    </div>
                </div>
            </td>
        `;

        tbody.appendChild(tr);
    });
}

function renderGrid(items) {
    const container = document.getElementById("gridViewContainer");
    if (!container) return;
    
    const emptyState = document.getElementById("emptyStateRaes");
    const emptySearch = document.getElementById("emptySearchRaes");
    const tableView = document.getElementById("tableView");
    const gridView = document.getElementById("gridView");
    
    // Limpiar contenido previo
    container.innerHTML = "";
    
    if (!items || items.length === 0) {
        // Verificar si estamos filtrando o si realmente no hay RAEs
        const searchInput = document.getElementById("searchRae");
        const levelFilter = document.getElementById("selectFiltroNivel");
        const hasActiveFilters = (searchInput && searchInput.value.trim() !== "") || 
                                (levelFilter && levelFilter.value !== "");
        
        if (hasActiveFilters) {
            // Hay filtros activos pero no resultados
            emptySearch?.classList.remove("hidden");
            emptyState?.classList.add("hidden");
        } else {
            // No hay RAEs en el sistema
            emptyState?.classList.remove("hidden");
            emptySearch?.classList.add("hidden");
        }
        
        // Ocultar vistas
        tableView?.classList.add("hidden");
        gridView?.classList.add("hidden");
        return;
    }
    
    // Hay RAEs, ocultar estados vacíos
    emptyState?.classList.add("hidden");
    emptySearch?.classList.add("hidden");

    items.forEach((r, idx) => {
        const id = _getField(r, "id", "id_rae");
        const codigo = _getField(r, "codigo_rae", "codigo");
        const descripcion = _getField(r, "descripcion", "descripcion_rae");
        let programa = _getField(r, "programa", "nombre_programa") || "";
        const ed = encodeURIComponent(descripcion);
        const ep = encodeURIComponent(programa);

        // id del programa
        const pid = _getField(r, "id_programa", "id_programa", "id_programa");

        // Obtener nivel del programa
        let nivelPrograma = "";
        if (pid != null && window._programLevelMap && window._programLevelMap[pid]) {
            nivelPrograma = window._programLevelMap[pid];
        }

        if (!programa) {
            if (pid != null && window._programMap && window._programMap[pid]) {
                programa = window._programMap[pid];
            } else if (pid != null) {
                programa = pid;
            }
        }
        const estado = _getField(r, "estado") || "";

        const card = document.createElement("div");
        card.className =
            "bg-card border border-border rounded-2xl p-6 hover:shadow-lg transition-all";
        card.setAttribute("data-pid", pid || "");
        card.setAttribute("data-nivel", nivelPrograma || "");

        card.innerHTML = `
            <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 bg-avatar-secondary-39 rounded-md flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="33" height="33" viewBox="0 0 24 24" fill="none" stroke="#007832" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open-icon lucide-book-open"><path d="M12 7v14"></path><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"></path></svg>
                </div>
                <div class="flex-1"><h3 class="text-lg font-semibold text-foreground leading-tight">${descripcion}</h3></div>
                <button onclick="openEditModal('${id}', '${ed}', '${ep}', '${
            codigo ? encodeURIComponent(codigo) : ""
        }', '${pid ?? ""}')" class="text-muted-foreground hover:text-foreground transition flex-shrink-0" title="Editar RAE">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                </button>
            </div>
            <div class="border-t border-border mb-4"></div>
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-2 text-foreground mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-graduation-cap-icon lucide-graduation-cap"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>
                        <span class="text-sm">${programa}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            ${
                                estado.toLowerCase() === "activo"
                                    ? `<span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-estado-activo">Activo</span>`
                                    : `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium text-gray-400" style="background-color: color-mix(in srgb, #9ca3af 18%, transparent);">Inactivo</span>`
                            }
                        </div>
                        <div class="flex items-center">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" ${
                                    estado.toLowerCase() === "activo" ? "checked" : ""
                                }>
                                <div class="w-11 h-6 bg-gray-500/20 rounded-full peer-checked:bg-secondary transition-all"></div>
                                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.appendChild(card);

        // Conectar el switch de la tarjeta para cambiar estado
        const cb = card.querySelector('input[type="checkbox"]');
        if (cb) {
            cb.addEventListener("change", function () {
                const newState = this.checked ? "Activo" : "Inactivo";
                changeRaeEstado(id, newState);
            });
        }
    });
}

// Al cargar la página, obtener y renderizar RAEs
document.addEventListener("DOMContentLoaded", async () => {
    // Vista por defecto: tabla
    try {
        toggleView("table");
    } catch (e) {}

    await loadPrograms();
    await loadProgramLevels(); // Cargar niveles de programas
    await loadRaes();

    // Attach create handler
    const createBtn = document.getElementById("createRaeSubmit");
    if (createBtn)
        createBtn.addEventListener("click", (e) => {
            e.preventDefault();
            createRae();
        });

    // Attach edit handler
    const editBtn = document.getElementById("editRaeSubmit");
    if (editBtn)
        editBtn.addEventListener("click", (e) => {
            e.preventDefault();
            updateRae();
        });

    // Attach search handler
    const searchInput = document.getElementById("searchRae");
    if (searchInput) {
        searchInput.addEventListener("input", updateFilter);
    }

    // Attach level filter handler
    const levelFilter = document.getElementById("selectFiltroNivel");
    if (levelFilter) {
        levelFilter.addEventListener("change", updateFilter);
    }
});

// Función para cargar niveles de programas
async function loadProgramLevels() {
    try {
        const res = await fetch(`${PROGRAMAS_API_URL}?accion=listar`, {
            headers: { Accept: "application/json" },
        });
        if (!res.ok) throw new Error("Error al obtener programas");
        const data = await res.json();

        // Crear un mapa de id_programa -> nivel
        window._programLevelMap = {};
        data.forEach((p) => {
            const id = p.id_programa ?? p.id ?? p.id_programa;
            const nivel = p.nivel_programa ?? p.nivel ?? "";
            if (id != null && nivel) {
                window._programLevelMap[id] = nivel;
            }
        });

        console.log("Niveles de programas cargados:", window._programLevelMap);
    } catch (err) {
        console.error("Error al cargar niveles de programas:", err);
        toastError("Error al cargar niveles de programas");
    }
}

// Función para filtrar RAEs por nivel de programa
function filterRaesByLevel() {
    const searchText = document.getElementById("searchRae").value.toLowerCase().trim();
    const nivelFilter = document.getElementById("selectFiltroNivel").value;
    const allRaes = window.currentRaes || [];

    if (!searchText && !nivelFilter) {
        // Si no hay filtros, mostrar todos
        renderTable(allRaes);
        renderGrid(allRaes);
        hideEmptySearchMessage();
        return;
    }

    // Filtrar RAEs
    const filteredRaes = allRaes.filter((rae) => {
        const id = _getField(rae, "id", "id_rae");
        const codigo = _getField(rae, "codigo_rae", "codigo");
        const descripcion = _getField(rae, "descripcion", "descripcion_rae");
        const programa = _getField(rae, "programa", "nombre_programa") || "";
        const estado = _getField(rae, "estado") || "";

        // Obtener el ID del programa para buscar el nivel
        const pid = _getField(rae, "id_programa", "id_programa", "id_programa");
        const nivelPrograma =
            pid && window._programLevelMap ? window._programLevelMap[pid] : "";

        // Buscar en todos los campos
        const matchesSearch =
            !searchText ||
            (codigo && codigo.toLowerCase().includes(searchText)) ||
            (descripcion && descripcion.toLowerCase().includes(searchText)) ||
            (programa && programa.toLowerCase().includes(searchText)) ||
            (estado && estado.toLowerCase().includes(searchText)) ||
            (id && id.toString().includes(searchText));

        // Filtrar por nivel
        const matchesLevel = !nivelFilter || nivelPrograma === nivelFilter;

        return matchesSearch && matchesLevel;
    });

    if (filteredRaes.length === 0) {
        showEmptySearchMessage();
    } else {
        hideEmptySearchMessage();
    }

    // Renderizar los resultados filtrados
    renderTable(filteredRaes);
    renderGrid(filteredRaes);
}

// Función para actualizar el filtro (se llama desde ambos inputs)
function updateFilter() {
    const searchText = document.getElementById("searchRae").value.toLowerCase().trim();
    const nivelFilter = document.getElementById("selectFiltroNivel").value;
    const allRaes = window.currentRaes || [];
    const emptyState = document.getElementById("emptyStateRaes");
    const emptySearch = document.getElementById("emptySearchRaes");
    const tableView = document.getElementById("tableView");
    const gridView = document.getElementById("gridView");
    
    // Si no hay RAEs en absoluto
    if (!allRaes || allRaes.length === 0) {
        emptyState?.classList.remove("hidden");
        emptySearch?.classList.add("hidden");
        tableView?.classList.add("hidden");
        gridView?.classList.add("hidden");
        return;
    }
    
    // Filtrar RAEs
    const filteredRaes = allRaes.filter((rae) => {
        const id = _getField(rae, "id", "id_rae");
        const codigo = _getField(rae, "codigo_rae", "codigo");
        const descripcion = _getField(rae, "descripcion", "descripcion_rae");
        const programa = _getField(rae, "programa", "nombre_programa") || "";
        const estado = _getField(rae, "estado") || "";
        
        // Obtener el ID del programa para buscar el nivel
        const pid = _getField(rae, "id_programa", "id_programa", "id_programa");
        const nivelPrograma = pid && window._programLevelMap ? window._programLevelMap[pid] : "";
        
        // Buscar en todos los campos
        const matchesSearch = !searchText ||
            (codigo && codigo.toLowerCase().includes(searchText)) ||
            (descripcion && descripcion.toLowerCase().includes(searchText)) ||
            (programa && programa.toLowerCase().includes(searchText)) ||
            (estado && estado.toLowerCase().includes(searchText)) ||
            (id && id.toString().includes(searchText));
        
        // Filtrar por nivel
        const matchesLevel = !nivelFilter || nivelPrograma === nivelFilter;
        
        return matchesSearch && matchesLevel;
    });
    
    if (filteredRaes.length === 0) {
        // Hay RAEs pero no coinciden con los filtros
        emptyState?.classList.add("hidden");
        emptySearch?.classList.remove("hidden");
        tableView?.classList.add("hidden");
        gridView?.classList.add("hidden");
    } else {
        // Hay resultados, mostrar la vista activa
        emptyState?.classList.add("hidden");
        emptySearch?.classList.add("hidden");
        
        // Determinar qué vista está activa
        const viewTableBtn = document.getElementById("viewTableBtn");
        const isTableView = viewTableBtn && viewTableBtn.classList.contains("bg-muted");
        
        if (isTableView) {
            tableView?.classList.remove("hidden");
            gridView?.classList.add("hidden");
        } else {
            tableView?.classList.add("hidden");
            gridView?.classList.remove("hidden");
        }
        
        // Renderizar resultados
        renderTable(filteredRaes);
        renderGrid(filteredRaes);
    }
}

function showEmptySearchMessage() {
    const emptySearchContainer = document.getElementById("emptySearchRaes");
    const tableView = document.getElementById("tableView");
    const gridView = document.getElementById("gridView");
    const emptyState = document.getElementById("emptyStateRaes");

    if (emptySearchContainer) {
        emptySearchContainer.classList.remove("hidden");
    }
    
    if (emptyState) {
        emptyState.classList.add("hidden");
    }

    // Ocultar las vistas de tabla y grid
    if (tableView) {
        tableView.classList.add("hidden");
    }
    if (gridView) {
        gridView.classList.add("hidden");
    }
}

function hideEmptySearchMessage() {
    const emptySearchContainer = document.getElementById("emptySearchRaes");
    const tableView = document.getElementById("tableView");
    const gridView = document.getElementById("gridView");
    const emptyState = document.getElementById("emptyStateRaes");

    if (emptySearchContainer) {
        emptySearchContainer.classList.add("hidden");
    }
    
    if (emptyState) {
        emptyState.classList.add("hidden");
    }

    // Mostrar las vistas según el botón activo
    const viewTableBtn = document.getElementById("viewTableBtn");
    const viewGridBtn = document.getElementById("viewGridBtn");

    if (viewTableBtn && viewTableBtn.classList.contains("bg-muted")) {
        if (tableView) {
            tableView.classList.remove("hidden");
        }
    }

    if (viewGridBtn && viewGridBtn.classList.contains("bg-muted")) {
        if (gridView) {
            gridView.classList.remove("hidden");
        }
    }
}