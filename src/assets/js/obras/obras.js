﻿/* ============================================================
   GUARD GLOBAL (evita doble carga del script)
============================================================ */
if (!window.__obrasJSLoaded) {
  window.__obrasJSLoaded = true;

  // ==============================
  // VARIABLES GLOBALES
  // ==============================
  let obras = [];
  let fichas = [];
  let raes = [];
  let instructores = [];

  let aprendicesFicha = [];
  let obraCreadaId = null;
  let obraCreadaData = null;
  let tipoTrabajoActual = "";
  let fichaSeleccionadaId = null;
  let aprendicesSeleccionados = [];

  // FIX: variables que tu código usa (para NO crear globals implícitas)
  let obraOriginal = null;
  let originalEditData = null;

  // Flag para evitar creación múltiple
  let isCreatingObra = false;

  // Detectar si el usuario es instructor
  const usuarioActual = window.USUARIO_SESION || {};
  const esInstructor = usuarioActual.cargo === "Instructor";
  const fichaInstructor = (usuarioActual.fichas && usuarioActual.fichas.length > 0) 
    ? usuarioActual.fichas[0].id_ficha 
    : null;
  
  // Verificar si es instructor sin ficha
  const instructorSinFicha = window.INSTRUCTOR_SIN_FICHA || false;
  console.log("VERIFICACIÓN INSTRUCTOR:", { esInstructor, fichaInstructor, instructorSinFicha, usuarioActual });

  // ==============================
  // PERMISOS FRONT (desde PHP)
  // ==============================
  const OBRAS_PERMS = (function () {
    const p = window.OBRAS_PERMS || {};
    // IMPORTANTE: si no viene nada desde PHP, no bloqueamos por defecto
    const hasAny =
      Object.prototype.hasOwnProperty.call(p, "canCrear") ||
      Object.prototype.hasOwnProperty.call(p, "canEditar") ||
      Object.prototype.hasOwnProperty.call(p, "canCambiarEstado");

    if (!hasAny) {
      return { canCrear: true, canEditar: true, canCambiarEstado: true };
    }

    return {
      canCrear: !!p.canCrear,
      canEditar: !!p.canEditar,
      canCambiarEstado: !!p.canCambiarEstado,
    };
  })();

  console.log("PERMISOS OBRAS:", OBRAS_PERMS);

  // ==============================
  // CONFIGURACIÓN DE API - URL FIJA
  // ==============================
  const API_URL = "src/controllers/obra_controller.php";
  console.log("API URL configurada:", API_URL);

  // ==============================
  // DETECCIÓN DEL SIDEBAR
  // ==============================
  function setupSidebarDetection() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get("coll") === "1") {
      document.body.classList.add("sidebar-collapsed");
    } else {
      document.body.classList.remove("sidebar-collapsed");
    }

    document.addEventListener("click", function (e) {
      if (e.target.closest('a[href*="coll="]')) {
        setTimeout(() => {
          const newParams = new URLSearchParams(window.location.search);
          if (newParams.get("coll") === "1") {
            document.body.classList.add("sidebar-collapsed");
          } else {
            document.body.classList.remove("sidebar-collapsed");
          }
        }, 50);
      }
    });
  }

  // ==============================
  // APLICAR PERMISOS A LA UI
  // ==============================
  function aplicarPermisosUI() {
    // Ocultar botón "Nueva Obra" si no puede crear
    if (!OBRAS_PERMS.canCrear) {
      const btnNueva = document.querySelector('button[onclick="openCreateModal()"]');
      if (btnNueva) btnNueva.style.display = "none";

      const modalCreate = document.getElementById("modalCreate");
      if (modalCreate) modalCreate.classList.add("hidden");
    }

    // Si no puede editar, por seguridad ocultamos modal edit
    if (!OBRAS_PERMS.canEditar) {
      const modalEdit = document.getElementById("modalEdit");
      if (modalEdit) modalEdit.classList.add("hidden");
    }
  }

  // ==============================
  // FUNCIONES DE API
  // ==============================
  async function fetchAPI(params = {}) {
    try {
      let url = API_URL;

      if (Object.keys(params).length > 0) {
        const queryParams = new URLSearchParams(params).toString();
        url += `?${queryParams}`;
      }

      console.log("Fetching:", url);

      const response = await fetch(url);

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        const text = await response.text();
        console.error("Respuesta no JSON:", text.substring(0, 500));
        throw new Error("El servidor no respondió con JSON. Verifica la ruta.");
      }

      const data = await response.json();
      return data;
    } catch (error) {
      console.error("Error en fetchAPI:", error);
      throw error;
    }
  }

  // ==============================
  // CARGAR OBRAS
  // ==============================
  async function cargarObras() {
    try {
      // Ocultar ambos mensajes al cargar
      const emptySearchElement = document.getElementById("emptySearchObras");
      const emptyStateElement = document.getElementById("emptyStateObras");
      
      if (emptySearchElement) emptySearchElement.classList.add("hidden");
      if (emptyStateElement) emptyStateElement.classList.add("hidden");

      const container = document.getElementById("obrasContainer");
      if (container) container.classList.remove("hidden");
      
      // Si es instructor sin ficha, mostrar mensaje especial
      if (instructorSinFicha) {
        const container = document.getElementById("obrasContainer");
        if (container) {
          container.innerHTML = `
            <div class="text-center py-12 text-amber-600">
              <i class="fas fa-exclamation-circle text-5xl mb-4"></i>
              <h3 class="text-xl font-semibold mb-2">Ficha No Vinculada</h3>
              <p class="mb-4">Debe contar con una ficha vinculada para poder listar sus obras.</p>
              <p class="text-sm opacity-75">Contacte al administrador o coordinador para que le asigne una ficha.</p>
            </div>
          `;
        }
        
        // Ocultar estadísticas y otros elementos
        const statsContainer = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-3.gap-6');
        const worksListContainer = document.querySelector('.rounded-xl.border.border-border.bg-card.shadow-sm.overflow-hidden');
        
        if (statsContainer) statsContainer.style.display = 'none';
        if (worksListContainer) worksListContainer.style.display = 'none';
        
        const loadingElement = document.getElementById("loading");
        if (loadingElement) loadingElement.style.display = "none";
        
        return;
      }
      
      console.log("Cargando obras...");
      const data = await fetchAPI({ accion: "listar" });

      console.log("Datos recibidos:", data);

      if (data && data.error) {
        mostrarError(`Error del servidor: ${data.error}`);
        const loadingElement = document.getElementById("loading");
        if (loadingElement) loadingElement.style.display = "none";
        return;
      }

      let obrasData = Array.isArray(data) ? data : [];
      
      // Si es instructor, filtrar solo las obras de su ficha
      if (esInstructor && fichaInstructor) {
        obrasData = obrasData.filter(obra => obra.id_ficha === fichaInstructor);
        console.log(`Obras filtradas para instructor (ficha ${fichaInstructor}): ${obrasData.length}`);
      }
      
      obras = obrasData;
      console.log(`${obras.length} obras cargadas`);

      updateEstadisticas();
      renderObras(obras);

      const loadingElement = document.getElementById("loading");
      if (loadingElement) loadingElement.style.display = "none";
    } catch (error) {
      console.error("Error completo al cargar obras:", error);

      let errorMsg = "No se pudieron cargar las obras.\n";
      errorMsg += `URL intentada: ${API_URL}?accion=listar\n`;
      errorMsg += `Error: ${error.message}`;

      mostrarError(errorMsg);
      // Asegurarnos de ocultar el loading si ocurre un error
      const loadingElement = document.getElementById("loading");
      if (loadingElement) loadingElement.style.display = "none";
    }
  }

  // ==============================
  // DATOS MAESTROS
  // ==============================
  async function cargarDatosMaestros() {
    try {
      console.log("Cargando datos maestros...");

      const fichasData = await fetchAPI({ accion: "obtener_fichas" });
      fichas = Array.isArray(fichasData) ? fichasData : [];
      console.log(`${fichas.length} fichas cargadas`);

      const raesData = await fetchAPI({ accion: "obtener_raes" });
      raes = Array.isArray(raesData) ? raesData : [];
      console.log(`${raes.length} RAEs cargados`);

      const instructoresData = await fetchAPI({ accion: "obtener_instructores" });
      instructores = Array.isArray(instructoresData) ? instructoresData : [];
      console.log(`${instructores.length} instructores cargados`);

      llenarSelectFichas();
      llenarSelectRaes();
      llenarSelectInstructores();
    } catch (error) {
      console.error("Error cargando datos maestros:", error);
      mostrarErrorSelects("Error al cargar opciones");
    }
  }

  // ==============================
  // SELECTS CREATE
  // ==============================
  function llenarSelectFichas() {
    const select = document.getElementById("create_ficha");
    if (!select) return;

    if (fichas.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No hay fichas disponibles</option>';
        select.disabled = true;
        return;
    }

    select.innerHTML = '<option value="" disabled selected class="text-gray-500">Selecciona una Ficha</option>';

    fichas.forEach((ficha) => {
        const option = document.createElement("option");
        option.value = ficha.id_ficha;
        option.textContent = ficha.numero_ficha;
        select.appendChild(option);
    });

    // IMPORTANTE: Nunca deshabilitar para instructores
    select.disabled = false;
    
    if (esInstructor) {
        console.log("Fichas disponibles para instructor:", fichas.length);
        
        // Si solo tiene una ficha, preseleccionarla automáticamente
        if (fichas.length === 1) {
            select.value = fichas[0].id_ficha;
            // Disparar el evento change para cargar RAEs y aprendices
            setTimeout(() => {
                select.dispatchEvent(new Event('change'));
            }, 100);
        }
    }
  }

  // ==============================
  // CARGAR RAES POR FICHA (NUEVO)
  // ==============================
  async function cargarRaesPorFicha(idFicha) {
    try {
      if (!idFicha) {
        // Si no hay ficha, cargar todos los RAEs activos
        await cargarTodosRaes();
        return;
      }
      
      console.log("Cargando RAEs para ficha ID:", idFicha);
      
      const data = await fetchAPI({
        accion: "obtener_raes_por_ficha",
        id_ficha: idFicha
      });
      
      if (data && !data.error) {
        // Actualizar el select de RAEs con los datos filtrados
        // actualizar la lista global para que otras funciones usen el conjunto filtrado
        raes = Array.isArray(data) ? data : [];
        llenarSelectRaesPorFicha(data);
      } else {
        console.error("Error al cargar RAEs por ficha:", data?.error);
        // Fallback: cargar todos los RAEs
        await cargarTodosRaes();
      }
    } catch (error) {
      console.error("Error en cargarRaesPorFicha:", error);
      // Fallback: cargar todos los RAEs
      await cargarTodosRaes();
    }
  }

  async function cargarTodosRaes() {
    try {
      const data = await fetchAPI({ accion: "obtener_raes" });
      if (data && !data.error) {
        raes = Array.isArray(data) ? data : [];
        llenarSelectRaes();
      }
    } catch (error) {
      console.error("Error cargando todos los RAEs:", error);
    }
  }

  // ==============================
  // CARGAR INSTRUCTORES POR FICHA
  // ==============================
  async function cargarInstructoresPorFicha(idFicha) {
    try {
      if (!idFicha) {
        // Si no hay ficha, cargar todos los instructores activos
        await cargarTodosInstructores();
        return;
      }
      
      console.log("Cargando instructores para ficha ID:", idFicha);
      
      const data = await fetchAPI({
        accion: "obtener_instructores_por_ficha",
        id_ficha: idFicha
      });
      
      if (data && !data.error) {
        // Actualizar el select de instructores con los datos filtrados
        llenarSelectInstructoresPorFicha(data);
      } else {
        console.error("Error al cargar instructores por ficha:", data?.error);
        // Fallback: cargar todos los instructores
        await cargarTodosInstructores();
      }
    } catch (error) {
      console.error("Error en cargarInstructoresPorFicha:", error);
      // Fallback: cargar todos los instructores
      await cargarTodosInstructores();
    }
  }

  async function cargarTodosInstructores() {
    try {
      const data = await fetchAPI({ accion: "obtener_instructores" });
      if (data && !data.error) {
        instructores = Array.isArray(data) ? data : [];
        llenarSelectInstructores();
      }
    } catch (error) {
      console.error("Error cargando todos los instructores:", error);
    }
  }

  function llenarSelectRaesPorFicha(raesData) {
    const selectCreate = document.getElementById("create_rae");
    const selectEdit = document.getElementById("edit_rae");
    
    if (!raesData || raesData.length === 0) {
      const message = "No hay RAEs disponibles para esta ficha";
      if (selectCreate) {
        selectCreate.innerHTML = `<option value="" disabled selected class="text-red-500">${message}</option>`;
      }
      if (selectEdit) {
        selectEdit.innerHTML = `<option value="" disabled selected class="text-red-500">${message}</option>`;
      }
      return;
    }
    
    // Para modal crear
    if (selectCreate) {
      selectCreate.innerHTML = '<option value="" disabled selected class="text-gray-500">Selecciona un RAE</option>';
      raesData.forEach((rae) => {
        const option = document.createElement("option");
        option.value = rae.id_rae;
        option.textContent = `${rae.codigo_rae} ${rae.descripcion_rae}`;
        option.setAttribute("data-codigo", rae.codigo_rae);
        option.setAttribute("data-descripcion", rae.descripcion_rae);
        selectCreate.appendChild(option);
      });
    }
    
    // Para modal editar
    if (selectEdit) {
      selectEdit.innerHTML = '<option value="" disabled class="text-gray-500">Selecciona un RAE</option>';
      raesData.forEach((rae) => {
        const option = document.createElement("option");
        option.value = rae.id_rae;
        option.textContent = `${rae.codigo_rae} ${rae.descripcion_rae}`;
        option.setAttribute("data-codigo", rae.codigo_rae);
        option.setAttribute("data-descripcion", rae.descripcion_rae);
        selectEdit.appendChild(option);
      });
    }
  }

  function llenarSelectRaes(selectedId = null) {
    const selectCreate = document.getElementById("create_rae");
    const selectEdit = document.getElementById("edit_rae");
    
    if (raes.length === 0) {
      const message = "No hay RAEs disponibles";
      if (selectCreate) {
        selectCreate.innerHTML = `<option value="" disabled selected class="text-red-500">${message}</option>`;
      }
      if (selectEdit) {
        selectEdit.innerHTML = `<option value="" disabled selected class="text-red-500">${message}</option>`;
      }
      return;
    }
    
    // Para modal crear
    if (selectCreate) {
      selectCreate.innerHTML = '<option value="" disabled selected class="text-gray-500">Selecciona un RAE</option>';
      raes.forEach((rae) => {
        const option = document.createElement("option");
        option.value = rae.id_rae;
        option.textContent = `${rae.codigo_rae} ${rae.descripcion_rae}`;
        option.setAttribute("data-codigo", rae.codigo_rae);
        option.setAttribute("data-descripcion", rae.descripcion_rae);
        if (selectedId && rae.id_rae == selectedId) option.selected = true;
        selectCreate.appendChild(option);
      });
    }
    
    // Para modal editar
    if (selectEdit) {
      selectEdit.innerHTML = '<option value="" disabled class="text-gray-500">Selecciona un RAE</option>';
      raes.forEach((rae) => {
        const option = document.createElement("option");
        option.value = rae.id_rae;
        option.textContent = `${rae.codigo_rae} ${rae.descripcion_rae}`;
        option.setAttribute("data-codigo", rae.codigo_rae);
        option.setAttribute("data-descripcion", rae.descripcion_rae);
        if (selectedId && rae.id_rae == selectedId) option.selected = true;
        selectEdit.appendChild(option);
      });
    }
  }

  function llenarSelectInstructores() {
    const select = document.getElementById("create_instructor");
    if (!select) return;

    if (instructores.length === 0) {
      select.innerHTML =
        '<option value="" disabled selected class="text-red-500">No hay instructores disponibles</option>';
      return;
    }

    select.innerHTML =
      '<option value="" disabled selected class="text-gray-500">Selecciona un instructor</option>';

    instructores.forEach((instructor) => {
      const option = document.createElement("option");
      option.value = instructor.id_usuario;
      option.textContent = instructor.nombre_completo;
      select.appendChild(option);
    });

    // Si es instructor, preseleccionar y deshabilitar como instructor
    if (esInstructor && usuarioActual.usuarioId) {
      select.value = usuarioActual.usuarioId;
      select.disabled = true;
      console.log("✅ Instructor preseleccionado:", usuarioActual.usuarioId);
    }
  }

  function llenarSelectInstructoresPorFicha(instructoresData) {
    const select = document.getElementById("create_instructor");
    if (!select) return;

    if (!instructoresData || instructoresData.length === 0) {
      select.innerHTML =
        '<option value="" disabled selected class="text-red-500">No hay instructores disponibles para esta ficha</option>';
      return;
    }

    select.innerHTML =
      '<option value="" disabled selected class="text-gray-500">Selecciona un instructor</option>';

    instructoresData.forEach((instructor) => {
      const option = document.createElement("option");
      option.value = instructor.id_usuario;
      option.textContent = instructor.nombre_completo;
      select.appendChild(option);
    });

    // Si es instructor, preseleccionar y deshabilitar como instructor
    if (esInstructor && usuarioActual.usuarioId) {
      select.value = usuarioActual.usuarioId;
      select.disabled = true;
      console.log("✅ Instructor preseleccionado:", usuarioActual.usuarioId);
    }
  }

  function mostrarErrorSelects(mensaje) {
    const selects = ["create_ficha", "create_rae", "create_instructor"];
    selects.forEach((selectId) => {
      const select = document.getElementById(selectId);
      if (select) {
        select.innerHTML = `<option value="" disabled selected class="text-red-500">${mensaje}</option>`;
      }
    });
    toastError(mensaje);
  }

  // ==============================
  // UI RENDER
  // ==============================
  function updateEstadisticas() {
    const total = obras.length;
    const activas = obras.filter((o) => o.estado === "Activa").length;
    const finalizadas = obras.filter((o) => o.estado === "Finalizada").length;

    const elTotal = document.getElementById("totalObras");
    const elActivas = document.getElementById("obrasActivas");
    const elFinal = document.getElementById("obrasFinalizadas");

    if (elTotal) elTotal.textContent = total;
    if (elActivas) elActivas.textContent = activas;
    if (elFinal) elFinal.textContent = finalizadas;
  }

  function renderObras(obrasData) {
    const container = document.getElementById("obrasContainer");
    const emptySearchElement = document.getElementById("emptySearchObras");
    const emptyStateElement = document.getElementById("emptyStateObras");
    
    if (!container) return;

    // Ocultar el loading
    const loadingElement = document.getElementById("loading");
    if (loadingElement) loadingElement.style.display = "none";

    // Validar si hay datos para mostrar
    if (!Array.isArray(obrasData) || obrasData.length === 0) {
      // Ocultar el contenedor de obras
      container.classList.add("hidden");
      container.innerHTML = ''; // Limpiar contenido
      
      // Verificar si es por búsqueda o por falta total de datos
      const searchInput = document.getElementById("searchInput");
      const searchTerm = (searchInput?.value || "").toLowerCase().trim();
      
      if (searchTerm !== "") {
        // Hay término de búsqueda pero no resultados - mostrar emptySearch
        if (emptySearchElement) {
          emptySearchElement.classList.remove("hidden");
        }
        if (emptyStateElement) {
          emptyStateElement.classList.add("hidden");
        }
      } else {
        // No hay obras en absoluto - mostrar emptyState
        if (emptyStateElement) {
          emptyStateElement.classList.remove("hidden");
        }
        if (emptySearchElement) {
          emptySearchElement.classList.add("hidden");
        }
      }
      return;
    }

    // Hay datos, mostrar container y ocultar mensajes vacíos
    container.classList.remove("hidden");
    if (emptySearchElement) {
      emptySearchElement.classList.add("hidden");
    }
    if (emptyStateElement) {
      emptyStateElement.classList.add("hidden");
    }

    // Generar HTML de las obras
    container.innerHTML = obrasData.map((obra, index) => {
        const estadoBadge = `
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ${
                obra.estado === "Activa" ? "bg-[#39A900]/50 text-[#007832]" : "bg-[#000000]/40 text-[#000000] opacity-70"
            }">
                ${obra.estado === "Activa" ? "Activa" : "Finalizada"}
            </span>
        `;

        const actionMenu = `
            <div class="relative">
                <button 
                    onclick="toggleActionMenu(${index})"
                    class="text-muted-foreground hover:text-foreground p-2 hover:bg-muted rounded-lg transition-colors"
                    title="Más acciones"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="5" r="2"/>
                        <circle cx="12" cy="12" r="2"/>
                        <circle cx="12" cy="19" r="2"/>
                    </svg>
                </button>

                <div id="actionMenu${index}" class="hidden absolute right-0 top-full mt-1 bg-card border border-border rounded-lg shadow-lg z-50 min-w-[180px]">
                    <button 
                        onclick="openDetailsModal(${obra.id_actividad}); closeAllMenus();"
                        class="w-full text-left px-4 py-2 text-sm text-foreground flex items-center gap-2 rounded-t-lg transition-colors duration-150 cursor-pointer hover:bg-gray-100"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye">
                            <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        Visualizar
                    </button>

                    ${OBRAS_PERMS.canEditar ? `
                        <button 
                            onclick="openEditModal(${obra.id_actividad}); closeAllMenus();"
                            class="w-full text-left px-4 py-2 text-sm text-foreground flex items-center gap-2 transition-colors duration-150 cursor-pointer hover:bg-gray-100"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen">
                                <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/>
                            </svg>
                            Editar
                        </button>
                    ` : ''}

                    ${OBRAS_PERMS.canCambiarEstado ? `
                        <button 
                            onclick="toggleEstado(${obra.id_actividad}, ${obra.estado === 'Activa' ? 'false' : 'true'}); closeAllMenus();"
                            class="w-full text-left px-4 py-2 text-sm text-foreground flex items-center gap-2 rounded-b-lg transition-colors duration-150 cursor-pointer hover:bg-gray-100 border-t border-border"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-power">
                                <path d="M18.36 6.64a9 9 0 1 1-12.73 0"/>
                                <line x1="12" y1="2" x2="12" y2="12"/>
                            </svg>
                            ${obra.estado === "Activa" ? "Finalizar" : "Activar"}
                        </button>
                    ` : ''}
                </div>
            </div>
        `;

        return `
            <div class="border border-l-4 ${
                obra.estado === "Activa" ? "border-l-[#007832]" : "border-l-[#64748b]"
            } rounded-lg p-5 mb-4 hover:shadow-md transition-shadow bg-white">
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <h3 class="text-lg font-semibold text-gray-900">${obra.nombre_actividad}</h3>
                        </div>
                        <p class="text-sm text-gray-600 mb-4 line-clamp-2">${obra.descripcion || "Sin descripción"}</p>

                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                            <div>
                                <p class="flex text-sm font-medium js-name gap-2 items-center pb-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-folder-kanban h-5 w-5 shrink-0">
                                        <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"></path>
                                        <path d="M8 10v4"></path><path d="M12 10v2"></path><path d="M16 10v6"></path>
                                    </svg> Ficha
                                </p>
                                <p class="text-sm font-medium text-gray-900">${obra.numero_ficha || "N/A"}</p>
                            </div>

                            <div>
                                <p class="flex text-sm font-medium js-name gap-2 items-center pb-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                        viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-users">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <path d="M16 3.128a4 4 0 0 1 0 7.744"/>
                                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                        <circle cx="9" cy="7" r="4"/>
                                    </svg> Tipo
                                </p>
                                <span class="inline-block px-2 py-1 ${
                                    obra.tipo_trabajo === "Grupal" ? "bg-[#00304d]/30 text-[#00304d]" : "bg-[#007832]/65"
                                } text-white text-xs font-semibold rounded-full">${obra.tipo_trabajo}</span>
                            </div>

                            <div>
                                <p class="flex text-sm font-medium js-name gap-2 items-center pb-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                        viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-calendar">
                                        <path d="M8 2v4"/><path d="M16 2v4"/>
                                        <rect width="18" height="18" x="3" y="4" rx="2"/>
                                        <path d="M3 10h18"/>
                                    </svg> Inicio
                                </p>
                                <p class="text-sm font-medium text-gray-900">${formatDate(obra.fecha_inicio)}</p>
                            </div>

                            <div>
                                <p class="flex text-sm font-medium js-name gap-2 items-center pb-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                        viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-calendar">
                                        <path d="M8 2v4"/><path d="M16 2v4"/>
                                        <rect width="18" height="18" x="3" y="4" rx="2"/>
                                        <path d="M3 10h18"/>
                                    </svg> Fin
                                </p>
                                <p class="text-sm font-medium text-gray-900">${formatDate(obra.fecha_fin)}</p>
                            </div>
                        </div>

                        <div class="text-sm text-gray-600">
                            <span class="font-medium">Instructor:</span> ${
                                esInstructor ? "Tu obra" : (obra.nombre_instructor || "No asignado")
                            }
                        </div>
                        <div class="text-sm text-gray-600 mt-1">
                            <span class="font-medium">RAE:</span> ${obra.descripcion_rae || "No asignado"}
                        </div>
                    </div>

                    <div class="flex flex-row items-center gap-3">
                        ${actionMenu}
                        ${estadoBadge}
                    </div>
                </div>
            </div>
        `;
    }).join("");
  }

  function formatDate(dateString) {
    if (!dateString) return "No definida";
    const date = new Date(dateString + "T00:00:00");
    const day = date.getDate().toString().padStart(2, "0");
    const month = (date.getMonth() + 1).toString().padStart(2, "0");
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
  }

  function formatFullDate(dateString) {
    if (!dateString) return "No definida";
    const date = new Date(dateString + "T00:00:00");

    const months = [
      "enero",
      "febrero",
      "marzo",
      "abril",
      "mayo",
      "junio",
      "julio",
      "agosto",
      "septiembre",
      "octubre",
      "noviembre",
      "diciembre",
    ];

    return `${date.getDate()} de ${months[date.getMonth()]} de ${date.getFullYear()}`;
  }

  // ==============================
  // BUSCAR
  // ==============================
  function searchObras() {
    // Si es instructor sin ficha, no hacer búsqueda
    if (instructorSinFicha) return;
    
    const searchInput = document.getElementById("searchInput");
    const searchTerm = (searchInput?.value || "").toLowerCase().trim();

    // Ocultar emptyState cuando se está buscando
    const emptyStateElement = document.getElementById("emptyStateObras");
    if (emptyStateElement) {
      emptyStateElement.classList.add("hidden");
    }

    if (searchTerm === "") {
      renderObras(obras);
      return;
    }

    const results = obras.filter((obra) => {
      return (
        (obra.nombre_actividad && obra.nombre_actividad.toLowerCase().includes(searchTerm)) ||
        (obra.numero_ficha && String(obra.numero_ficha).toLowerCase().includes(searchTerm)) ||
        (obra.nombre_instructor && obra.nombre_instructor.toLowerCase().includes(searchTerm)) ||
        (obra.descripcion && obra.descripcion.toLowerCase().includes(searchTerm)) ||
        (obra.descripcion_rae && obra.descripcion_rae.toLowerCase().includes(searchTerm))
      );
    });
    renderObras(results);
  }

  // ==============================
  // CAMBIAR ESTADO
  // ==============================
  async function toggleEstado(id, estado) {
    // Verificar si es instructor sin ficha
    if (instructorSinFicha) {
      toastError("No puede cambiar el estado porque no tiene una ficha vinculada.");
      revertirSwitchEstado(id, estado);
      return;
    }
    
    if (!OBRAS_PERMS.canCambiarEstado) {
      toastError("No tienes permisos para cambiar el estado.");
      revertirSwitchEstado(id, estado);
      return;
    }

    const accion = estado ? "activar" : "finalizar";

    try {
      const result = await fetchAPI({
        accion: accion,
        id_actividad: id,
      });

      if (result && result.success) {
        await cargarObras();
        toastSuccess("Estado actualizado exitosamente");
      } else {
        toastError(result?.error || "Error al actualizar estado");
        revertirSwitchEstado(id, estado);
      }
    } catch (error) {
      console.error("Error al cambiar estado:", error);
      toastError("Error al cambiar estado");
      revertirSwitchEstado(id, estado);
    }
  }

  function revertirSwitchEstado(id, estado) {
    const checkbox = document.querySelector(`input[onchange="toggleEstado(${id}, this.checked)"]`);
    if (checkbox) checkbox.checked = !estado;
  }

  // ==============================
  // FUNCIONES PARA MENÚ DE ACCIONES
  // ==============================
  function toggleActionMenu(index) {
    // Si es instructor sin ficha, no mostrar menú
    if (instructorSinFicha) return;
    
    const menu = document.getElementById("actionMenu" + index);
    const isHidden = menu.classList.contains("hidden");

    // Cerrar todos los otros menús
    closeAllMenus();

    // Toggle menú actual
    if (isHidden) {
      menu.classList.remove("hidden");
      menu.classList.add("block");
    }
  }

  function closeAllMenus() {
    const allMenus = document.querySelectorAll('[id^="actionMenu"]');
    allMenus.forEach((menu) => {
      menu.classList.add("hidden");
      menu.classList.remove("block");
    });
  }

  // ==============================
  // CONFIRMACIÓN PERSONALIZADA (se conserva completa)
  // ==============================
  function showConfirmationDialog(title, message) {
    return new Promise((resolve) => {
      const overlay = document.createElement("div");
      overlay.className =
        "fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000]";

      const modal = document.createElement("div");
      modal.className = "bg-white rounded-lg shadow-xl w-full max-w-md mx-4";

      modal.innerHTML = `
        <div class="p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
              <i class="fas fa-exclamation-triangle text-amber-600 text-lg"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900">${title}</h3>
          </div>
          <p class="text-gray-600 mb-6">${message}</p>
          <div class="flex justify-end gap-3">
            <button 
              type="button"
              id="confirmCancel"
              class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
            >
              Cancelar
            </button>
            <button 
              type="button"
              id="confirmAccept"
              class="px-4 py-2 bg-secondary text-white rounded-lg hover:opacity-90 transition-colors"
            >
              Aceptar
            </button>
          </div>
        </div>
      `;

      overlay.appendChild(modal);
      document.body.appendChild(overlay);

      const cleanup = () => {
        if (overlay.parentNode) document.body.removeChild(overlay);
        document.removeEventListener("keydown", handleEsc);
      };

      document.getElementById("confirmCancel").addEventListener("click", () => {
        cleanup();
        resolve(false);
      });

      document.getElementById("confirmAccept").addEventListener("click", () => {
        cleanup();
        resolve(true);
      });

      overlay.addEventListener("click", (e) => {
        if (e.target === overlay) {
          cleanup();
          resolve(false);
        }
      });

      const handleEsc = (e) => {
        if (e.key === "Escape") {
          cleanup();
          resolve(false);
        }
      };
      document.addEventListener("keydown", handleEsc);
    });
  }

  // ==============================
  // MANEJAR CAMBIO DE FICHA (NUEVO)
  // ==============================
  async function handleFichaChange() {
    const fichaId = this.value;
    const raeSelect = document.getElementById("create_rae");
    const raeEditSelect = document.getElementById("edit_rae");
    const instructorSelect = document.getElementById("create_instructor");
    
    if (!fichaId) {
      if (raeSelect) {
        raeSelect.innerHTML = '<option value="" disabled selected class="text-gray-500">Selecciona primero una ficha</option>';
        raeSelect.disabled = true;
      }
      if (raeEditSelect) {
        raeEditSelect.innerHTML = '<option value="" disabled selected class="text-gray-500">Selecciona primero una ficha</option>';
        raeEditSelect.disabled = true;
      }
      if (instructorSelect) {
        instructorSelect.innerHTML = '<option value="" disabled selected class="text-gray-500">Selecciona primero una ficha</option>';
        instructorSelect.disabled = true;
      }
      return;
    }
    
    if (raeSelect) {
      raeSelect.innerHTML = '<option value="" disabled selected>Cargando RAEs...</option>';
      raeSelect.disabled = false;
    }
    
    if (raeEditSelect) {
      raeEditSelect.innerHTML = '<option value="" disabled selected>Cargando RAEs...</option>';
      raeEditSelect.disabled = false;
    }
    
    if (instructorSelect) {
      instructorSelect.innerHTML = '<option value="" disabled selected>Cargando instructores...</option>';
      instructorSelect.disabled = false;
    }
    
    await cargarRaesPorFicha(fichaId);
    await cargarInstructoresPorFicha(fichaId);
  }

  // ==============================
  // MODAL CREAR
  // ==============================
  async function openCreateModal() {
    // Verificar si es instructor sin ficha vinculada
    if (instructorSinFicha) {
      toastError("No puede crear obras porque no tiene una ficha vinculada. Contacte al administrador.");
      return;
    }
    
    if (!OBRAS_PERMS.canCrear) {
      toastError("No tienes permisos para crear obras.");
      return;
    }

    if (fichas.length === 0 || raes.length === 0 || instructores.length === 0) {
      await cargarDatosMaestros();
    }

    const modal = document.getElementById("modalCreate");
    const form = document.getElementById("formCreate");

    if (form) form.reset();
    if (modal) modal.classList.remove("hidden");

    const tipo = document.getElementById("create_tipo");
    if (tipo) tipo.value = "Individual";

    // Resetear selects
    llenarSelectFichas();
    llenarSelectRaes();
    llenarSelectInstructores();

    // Configurar para instructores
    if (esInstructor) {
        const create_ficha = document.getElementById("create_ficha");
        const create_instructor = document.getElementById("create_instructor");
        
        // SOLO el instructor debe estar bloqueado, NO la ficha
        if (create_instructor && usuarioActual.usuarioId) {
            create_instructor.value = usuarioActual.usuarioId;
            create_instructor.disabled = true; // El instructor no puede cambiarse
        }
        
        // IMPORTANTE: NO deshabilitar el select de fichas
        // El select ya está habilitado en llenarSelectFichas()
        
        // Cargar RAEs si ya tiene una ficha seleccionada automáticamente
        if (create_ficha && create_ficha.value) {
            await cargarRaesPorFicha(create_ficha.value);
            // También cargar aprendices si es individual
            if (tipo && tipo.value === "Individual") {
                cargarAprendicesParaSelect(create_ficha.value);
            }
        }
    }

    // Mostrar/ocultar container de aprendiz individual según tipo
    const container = document.getElementById("containerAprendizIndividual");
    if (container) {
        const tipoValue = document.getElementById("create_tipo")?.value;
        if (tipoValue === "Individual") {
            container.classList.remove("hidden");
        } else {
            container.classList.add("hidden");
        }
    }

    // Configurar event listener para cambio de ficha
    const fichaSelect = document.getElementById("create_ficha");
    if (fichaSelect) {
        // Remover event listeners anteriores si existen
        fichaSelect.removeEventListener("change", handleFichaChange);
        // Agregar nuevo
        fichaSelect.addEventListener("change", handleFichaChange);
    }

    // Reset flujo creación
    resetearCreacion();
  }

  function closeCreateModal() {
    const modal = document.getElementById("modalCreate");
    if (modal) modal.classList.add("hidden");
    
    // Resetear form
    const form = document.getElementById("formCreate");
    if (form) form.reset();
    
    resetearCreacion();
  }

  // ==============================
  // CREAR OBRA (con asignación individual / grupal)
  // ==============================
  async function handleCreateObra(e) {
    e.preventDefault();

    // Verificar si es instructor sin ficha
    if (instructorSinFicha) {
      toastError("No puede crear obras porque no tiene una ficha vinculada.");
      return;
    }

    if (isCreatingObra) {
      console.log("Creación ya en progreso, ignorando clic adicional");
      return;
    }

    if (!OBRAS_PERMS.canCrear) {
      toastError("No tienes permisos para crear obras.");
      return;
    }

    const obraData = {
      id_ficha: document.getElementById("create_ficha")?.value,
      id_rae: document.getElementById("create_rae")?.value,
      id_instructor: document.getElementById("create_instructor")?.value,
      nombre_actividad: document.getElementById("create_nombre")?.value?.trim(),
      descripcion: document.getElementById("create_descripcion")?.value?.trim(),
      tipo_trabajo: document.getElementById("create_tipo")?.value,
      fecha_inicio: document.getElementById("create_fecha_inicio")?.value,
      fecha_fin: document.getElementById("create_fecha_fin")?.value,
      estado: "Activa",
    };

    // Guardar tipo y ficha para flujo posterior
    tipoTrabajoActual = obraData.tipo_trabajo || "";
    fichaSeleccionadaId = obraData.id_ficha || null;

    // Si es Individual, obtener aprendiz del select
    if (obraData.tipo_trabajo === "Individual") {
      const aprendizId = document.getElementById("create_aprendiz_individual")?.value;
      if (!aprendizId) {
        toastError("Debes seleccionar un aprendiz para la obra individual");
        return;
      }
      obraData.aprendiz_seleccionado = aprendizId;
    }

    // Validaciones
    if (!validateObraData(obraData, false)) return;

    const btnCreate = document.getElementById("btnCreate");
    const btnCreateText = document.getElementById("btnCreateText");
    const btnCreateLoading = document.getElementById("btnCreateLoading");

    if (btnCreate) btnCreate.disabled = true;
    btnCreateText?.classList.add("hidden");
    btnCreateLoading?.classList.remove("hidden");

    isCreatingObra = true; // Marcar como creando

    try {
      const response = await fetch(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...obraData, accion: "crear" }),
      });

      const result = await response.json();

      if (result && result.success) {
        // Guardar obra creada
        obraCreadaId = result.id_actividad || result.id || result.insertId || null;
        obraCreadaData = { ...obraData };

        if (!obraCreadaId) {
          toastError("La obra se creó, pero no se recibió el ID para asignar aprendices.");
          closeCreateModal();
          await cargarObras();
          return;
        }

        if (tipoTrabajoActual === "Individual") {
          await asignarAprendizIndividual(obraData.aprendiz_seleccionado);
        } else if (tipoTrabajoActual === "Grupal") {
          const ok = await cargarAprendicesFicha(fichaSeleccionadaId);
          if (ok) {
            openAsignarModal();
          } else {
            toastError("La obra fue creada, pero no se pudieron cargar aprendices para asignar.");
            closeCreateModal();
            await cargarObras();
          }
        } else {
          toastSuccess("Obra creada exitosamente");
          closeCreateModal();
          await cargarObras();
        }
      } else {
        toastError(result?.error || "Error al crear la obra");
      }
    } catch (error) {
      console.error("Error creando obra:", error);
      toastError("Error al crear la obra");
    } finally {
      if (btnCreate) btnCreate.disabled = false;
      btnCreateText?.classList.remove("hidden");
      btnCreateLoading?.classList.add("hidden");
      isCreatingObra = false; // Resetear flag
    }
  }

  // ==============================
  // MODAL EDITAR
  // ==============================
  async function openEditModal(id) {
    // Verificar si es instructor sin ficha vinculada
    if (instructorSinFicha) {
      toastError("No puede editar obras porque no tiene una ficha vinculada.");
      return;
    }
    
    if (!OBRAS_PERMS.canEditar) {
      toastError("No tienes permisos para editar obras.");
      return;
    }

    try {
      if (fichas.length === 0 || raes.length === 0 || instructores.length === 0) {
        await cargarDatosMaestros();
      }

      const obra = await fetchAPI({
        accion: "obtener",
        id_actividad: id,
      });

      if (!obra || obra.error) {
        toastError("No se pudo cargar la obra");
        console.error("No se encontró obra con ID:", id);
        return;
      }

      console.log("Datos de obra para editar:", obra);

      // Guardar originales para detectar cambios
      originalEditData = {
        id_ficha: obra.id_ficha,
        id_rae: obra.id_rae,
        id_instructor: obra.id_instructor,
        nombre_actividad: obra.nombre_actividad,
        descripcion: obra.descripcion || "",
        tipo_trabajo: obra.tipo_trabajo,
        fecha_inicio: obra.fecha_inicio,
        fecha_fin: obra.fecha_fin,
      };

      obraOriginal = obra;

      // Llenar selects con las fichas del instructor
      llenarSelectFichasEdit(obra.id_ficha);
      llenarSelectRaesEdit(obra.id_rae);
      llenarSelectInstructoresEdit(obra.id_instructor);

      // Después de cargar la obra, cargar RAEs específicos de la ficha
      if (obra && obra.id_ficha) {
        await cargarRaesPorFicha(obra.id_ficha);
        
        // Establecer el RAE seleccionado después de cargar
        setTimeout(() => {
          const raeSelect = document.getElementById("edit_rae");
          if (raeSelect && obra.id_rae) {
            raeSelect.value = obra.id_rae;
          }
        }, 100);
      }

      document.getElementById("edit_id").value = obra.id_actividad;
      document.getElementById("edit_nombre").value = obra.nombre_actividad;
      document.getElementById("edit_descripcion").value = obra.descripcion || "";
      document.getElementById("edit_tipo").value = obra.tipo_trabajo;
      document.getElementById("edit_fecha_inicio").value = obra.fecha_inicio;
      document.getElementById("edit_fecha_fin").value = obra.fecha_fin;

      // CONFIGURACIÓN PARA INSTRUCTOR
      if (esInstructor) {
        const fichaSelect = document.getElementById("edit_ficha");
        const instructorSelect = document.getElementById("edit_instructor");
        const raeSelect = document.getElementById("edit_rae");
        
        // VERIFICAR: El instructor debe tener acceso a esta ficha
        const obraFichaId = obra.id_ficha;
        const tieneAcceso = fichas.some(f => f.id_ficha == obraFichaId);
        
        if (!tieneAcceso) {
          toastError("No tienes acceso a editar esta obra. La ficha no está entre tus asignaciones.");
          return;
        }
        
        // IMPORTANTE: Permitir cambiar entre fichas del instructor
        if (fichaSelect) {
          fichaSelect.disabled = false; // ← DESBLOQUEADO
        }
        
        // Instructor bloqueado (es él mismo)
        if (instructorSelect) {
          instructorSelect.disabled = true;
        }
        
        // Cargar RAEs específicos de la ficha actual
        if (obra.id_ficha) {
          await cargarRaesPorFicha(obra.id_ficha);
          
          // Asegurar que el RAE seleccionado sea visible
          setTimeout(() => {
            if (raeSelect && obra.id_rae) {
              raeSelect.value = obra.id_rae;
            }
          }, 150);
        }
      }

      document.getElementById("modalEdit").classList.remove("hidden");
    } catch (error) {
      console.error("Error cargando obra:", error);
      toastError("Error al cargar la obra: " + error.message);
    }
  }

  function closeEditModal() {
    const modal = document.getElementById("modalEdit");
    if (modal) modal.classList.add("hidden");
    
    // REACTIVAR CAMPOS AL CERRAR PARA PRÓXIMAS EDICIONES
    const editFicha = document.getElementById("edit_ficha");
    const editRae = document.getElementById("edit_rae");
    const editInstructor = document.getElementById("edit_instructor");
    const editTipo = document.getElementById("edit_tipo");
    const editFechaInicio = document.getElementById("edit_fecha_inicio");
    const editFechaFin = document.getElementById("edit_fecha_fin");
    
    if (editFicha) editFicha.disabled = false;
    if (editRae) editRae.disabled = false;
    if (editInstructor) editInstructor.disabled = esInstructor;
    if (editTipo) editTipo.disabled = false;
    if (editFechaInicio) editFechaInicio.disabled = false;
    if (editFechaFin) editFechaFin.disabled = false;
    
    originalEditData = null;
    obraOriginal = null;
  }

  async function handleEditObra(e) {
    e.preventDefault();

    if (instructorSinFicha) {
      toastError("No puede editar obras porque no tiene una ficha vinculada.");
      return;
    }

    if (!OBRAS_PERMS.canEditar) {
      toastError("No tienes permisos para editar obras.");
      return;
    }

    const id = parseInt(document.getElementById("edit_id").value, 10);
    const fichaId = parseInt(document.getElementById("edit_ficha").value, 10);

    if (esInstructor) {
      // Verificar que la ficha seleccionada esté entre las fichas del instructor
      const fichaSeleccionada = fichas.some(f => f.id_ficha == fichaId);
      if (!fichaSeleccionada) {
        toastError("No tiene acceso a esta ficha. Seleccione una ficha a la que esté asignado.");
        return;
      }
    }

    const currentData = {
      id_ficha: parseInt(document.getElementById("edit_ficha").value, 10),
      id_rae: parseInt(document.getElementById("edit_rae").value, 10),
      id_instructor: parseInt(document.getElementById("edit_instructor").value, 10),
      nombre_actividad: document.getElementById("edit_nombre").value.trim(),
      descripcion: document.getElementById("edit_descripcion").value.trim(),
      tipo_trabajo: document.getElementById("edit_tipo").value,
      fecha_inicio: document.getElementById("edit_fecha_inicio").value,
      fecha_fin: document.getElementById("edit_fecha_fin").value,
    };

    if (!validateObraData(currentData, true)) return;

    if (originalEditData && !hasChanges(originalEditData, currentData)) {
      toastInfo("Para actualizar la obra es necesario modificar al menos un dato.");
      return;
    }

    const obraData = {
      id_actividad: id,
      ...currentData,
      estado: obraOriginal ? obraOriginal.estado : "Activa",
    };

    try {
      const response = await fetch(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...obraData, accion: "actualizar" }),
      });

      const result = await response.json();

      if (result && result.success) {
        toastSuccess("Obra actualizada exitosamente");
        closeEditModal();
        await cargarObras();
      } else {
        toastError(result?.error || "Error al actualizar la obra");
      }
    } catch (error) {
      console.error("Error actualizando obra:", error);
      toastError("Error al actualizar la obra: " + error.message);
    }
  }

  // ==============================
  // MODAL DETALLES
  // ==============================
  async function openDetailsModal(id) {
    try {
      const obra = await fetchAPI({
        accion: "obtener",
        id_actividad: id,
      });

      if (!obra || obra.error) {
        toastError("No se pudo cargar la obra");
        return;
      }

      document.getElementById("details_nombre").textContent = obra.nombre_actividad;

      const badge = document.getElementById("details_badge_tipo");
      badge.textContent = obra.estado === "Activa" ? "Activa" : "Finalizada";
      badge.className =
        obra.estado === "Activa"
          ? "inline-block px-3 py-1 bg-[#007832]/65 text-white text-xs font-semibold rounded-full"
          : "inline-block px-3 py-1 bg-[#000000]/40 text-[#000000] text-xs rounded-full";

      document.getElementById("details_descripcion").textContent = obra.descripcion || "Sin descripción";
      document.getElementById("details_ficha").textContent = obra.numero_ficha || "N/A";

      const tipoElement = document.getElementById("details_tipo");
      tipoElement.textContent = obra.tipo_trabajo;
      tipoElement.className =
        obra.tipo_trabajo === "Grupal"
          ? "inline-block px-2 py-1 bg-[#00304D]/30 text-[#00304D] text-xs font-semibold rounded-full"
          : "inline-block px-2 py-1 bg-[#007832]/65 text-white text-xs font-semibold rounded-full";

      // Si es instructor, no mostrar nombre del instructor (mostrar "Tu obra" o no mostrar)
      if (esInstructor) {
        document.getElementById("details_instructor").textContent = "Tu obra";
      } else {
        document.getElementById("details_instructor").textContent = obra.nombre_instructor || "No asignado";
      }
      
      document.getElementById("details_rae").textContent = obra.descripcion_rae || "No asignado";
      document.getElementById("details_fecha_inicio").textContent = formatFullDate(obra.fecha_inicio);
      document.getElementById("details_fecha_fin").textContent = formatFullDate(obra.fecha_fin);

      document.getElementById("modalDetails").classList.remove("hidden");
    } catch (error) {
      console.error("Error cargando detalles:", error);
      toastError("Error al cargar los detalles");
    }
  }

  function closeDetailsModal() {
    const modal = document.getElementById("modalDetails");
    if (modal) modal.classList.add("hidden");
  }

  // ==============================
  // ERRORES EN PANTALLA
  // ==============================
  function mostrarError(mensaje) {
    const container = document.getElementById("obrasContainer");
    if (!container) return;

    container.innerHTML = `
      <div class="text-center py-12 text-red-500">
        <i class="fas fa-exclamation-triangle text-4xl mb-3"></i>
        <p class="mb-2 font-medium">Error</p>
        <p class="text-sm mb-4 whitespace-pre-line">${mensaje}</p>
        <button onclick="cargarObras()" class="mt-4 px-4 py-2 bg-secondary text-white rounded hover:opacity-90">
          Reintentar
        </button>
      </div>
    `;
  }

  // ==============================
  // SELECTS EDIT
  // ==============================
  function llenarSelectFichasEdit(selectedId = null) {
    const select = document.getElementById("edit_ficha");
    if (!select) return;

    if (fichas.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No hay fichas disponibles</option>';
        select.disabled = true;
        return;
    }

    select.innerHTML = '<option value="" disabled class="text-gray-500">Selecciona una Ficha</option>';

    fichas.forEach((ficha) => {
        const option = document.createElement("option");
        option.value = ficha.id_ficha;
        option.textContent = ficha.numero_ficha;
        if (selectedId && ficha.id_ficha == selectedId) option.selected = true;
        select.appendChild(option);
    });

    // IMPORTANTE: Para instructores, permitir cambiar entre sus fichas
    if (esInstructor) {
        select.disabled = false; // Permitir cambiar entre fichas
        console.log("Editando: Fichas disponibles para instructor:", fichas.length);
    }
  }

  function llenarSelectRaesEdit(selectedId = null) {
    const select = document.getElementById("edit_rae");
    if (!select) return;

    if (raes.length === 0) {
      select.innerHTML =
        '<option value="" disabled selected class="text-red-500">No hay RAEs disponibles</option>';
      return;
    }

    select.innerHTML = '<option value="" disabled class="text-gray-500">Selecciona un RAE</option>';

    raes.forEach((rae) => {
      const option = document.createElement("option");
      option.value = rae.id_rae;
      option.textContent = `${rae.codigo_rae} ${rae.descripcion_rae}`;
      option.setAttribute("data-codigo", rae.codigo_rae);
      option.setAttribute("data-descripcion", rae.descripcion_rae);
      if (selectedId && rae.id_rae == selectedId) option.selected = true;
      select.appendChild(option);
    });
  }

  function llenarSelectInstructoresEdit(selectedId = null) {
    const select = document.getElementById("edit_instructor");
    if (!select) return;

    if (instructores.length === 0) {
      select.innerHTML =
        '<option value="" disabled selected class="text-red-500">No hay instructores disponibles</option>';
      return;
    }

    select.innerHTML = '<option value="" disabled class="text-gray-500">Selecciona un instructor</option>';

    // Si es instructor, solo mostrar el instructor actual
    if (esInstructor && usuarioActual.usuarioId) {
      const instructorActual = instructores.find(i => i.id_usuario === usuarioActual.usuarioId);
      if (instructorActual) {
        const option = document.createElement("option");
        option.value = instructorActual.id_usuario;
        option.textContent = instructorActual.nombre_completo;
        if (selectedId && instructorActual.id_usuario == selectedId) option.selected = true;
        select.appendChild(option);
      }
    } else {
      instructores.forEach((instructor) => {
        const option = document.createElement("option");
        option.value = instructor.id_usuario;
        option.textContent = instructor.nombre_completo;
        if (selectedId && instructor.id_usuario == selectedId) option.selected = true;
        select.appendChild(option);
      });
    }
  }

  // =========================
  // FLOWBITE-STYLE ALERTS
  // =========================
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

  function showFlowbiteAlert(type, message) {
    const container = getOrCreateFlowbiteContainer();
    const wrapper = document.createElement("div");

    let borderColor = "border-amber-500";
    let textColor = "text-amber-900";
    let titleText = "Advertencia";

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

    wrapper.className = `
      relative flex items-center w-full mx-auto pointer-events-auto
      rounded-2xl border-l-4 ${borderColor} bg-white shadow-md
      px-4 py-3 text-sm ${textColor}
      opacity-0 -translate-y-2
      transition-all duration-300 ease-out
    `;

    wrapper.innerHTML = `
      <div class="flex-shrink-0 mr-3 text-current">${iconSVG}</div>
      <div class="flex-1 min-w-0">
        <p class="font-semibold">${titleText}</p>
        <p class="mt-0.5 text-sm">${message}</p>
      </div>
    `;

    container.appendChild(wrapper);

    requestAnimationFrame(() => {
      wrapper.classList.remove("opacity-0", "-translate-y-2");
      wrapper.classList.add("opacity-100", "translate-y-0");
    });

    setTimeout(() => {
      wrapper.classList.add("opacity-0", "-translate-y-2");
      wrapper.classList.remove("opacity-100", "translate-y-0");
      setTimeout(() => wrapper.remove(), 250);
    }, 4000);
  }

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
  // VALIDACIONES
  // =========================
  function hasChanges(originalData, currentData) {
    const normalize = (obj) => ({
      id_ficha: parseInt(obj.id_ficha, 10) || 0,
      id_rae: parseInt(obj.id_rae, 10) || 0,
      id_instructor: parseInt(obj.id_instructor, 10) || 0,
      nombre_actividad: (obj.nombre_actividad || "").trim().toLowerCase(),
      descripcion: (obj.descripcion || "").trim().toLowerCase(),
      tipo_trabajo: (obj.tipo_trabajo || "").trim(),
      fecha_inicio: obj.fecha_inicio || "",
      fecha_fin: obj.fecha_fin || "",
    });

    return JSON.stringify(normalize(originalData)) !== JSON.stringify(normalize(currentData));
  }

  function validateObraData(data, isEdit = false) {
    const requiredFields = {
      id_ficha: "Ficha",
      id_rae: "RAE",
      id_instructor: "Instructor",
      nombre_actividad: "Nombre de la actividad",
      descripcion: "Descripción",
      tipo_trabajo: "Tipo de trabajo",
      fecha_inicio: "Fecha de inicio",
      fecha_fin: "Fecha de fin",
    };

    for (const [field, name] of Object.entries(requiredFields)) {
      if (!data[field] || data[field].toString().trim() === "") {
        toastError(`El campo "${name}" es obligatorio.`);
        return false;
      }
    }

    if (data.nombre_actividad.trim().length < 3) {
      toastError("El nombre de la actividad debe tener al menos 3 caracteres.");
      return false;
    }

    if (!validarDescripcion(data.descripcion)) {
      toastError("La descripción debe tener al menos 10 caracteres.");
      return false;
    }
    
    if (!validarFechas(data.fecha_inicio, data.fecha_fin)) return false;

    // Validación extra: individual requiere aprendiz
    if (!isEdit && data.tipo_trabajo === "Individual") {
      if (!data.aprendiz_seleccionado) {
        toastError("Debes seleccionar un aprendiz para la obra individual");
        return false;
      }
    }

    return true;
  }

  function validarFechas(fechaInicio, fechaFin) {
    const inicio = new Date(fechaInicio);
    const fin = new Date(fechaFin);

    if (isNaN(inicio.getTime())) {
      toastError("La fecha de inicio no es válida.");
      return false;
    }
    if (isNaN(fin.getTime())) {
      toastError("La fecha de fin no es válida.");
      return false;
    }
    if (inicio > fin) {
      toastError("La fecha de inicio no puede ser posterior a la fecha de fin.");
      return false;
    }
    return true;
  }

  function validarDescripcion(descripcion) {
    if (!descripcion || descripcion.trim().length < 10) {
      return false;
    }
    return true;
  }

  // ==============================
  // APRENDICES (SELECT INDIVIDUAL)
  // ==============================
  async function cargarAprendicesParaSelect(idFicha) {
    try {
      const select = document.getElementById("create_aprendiz_individual");
      if (!select) return;

      if (!idFicha || idFicha === "") {
        select.innerHTML = '<option value="" disabled selected>Selecciona primero una ficha</option>';
        return;
      }

      select.innerHTML = '<option value="" disabled selected>Cargando aprendices...</option>';

      const url = API_URL + "?accion=obtener_aprendices_ficha&id_ficha=" + idFicha;

      const response = await fetch(url);
      const textResponse = await response.text();

      let data;
      try {
        data = JSON.parse(textResponse);
      } catch (jsonError) {
        console.error("No se pudo parsear como JSON:", jsonError);
        select.innerHTML = '<option value="" disabled selected>Error al cargar aprendices</option>';
        return;
      }

      if (data && !data.error) {
        if (data.length === 0) {
          select.innerHTML = '<option value="" disabled selected>No hay aprendices en esta ficha</option>';
        } else {
          select.innerHTML = '<option value="" disabled selected>Selecciona un aprendiz</option>';

          data.forEach((aprendiz) => {
            const option = document.createElement("option");
            option.value = aprendiz.id_usuario;
            option.textContent = `${aprendiz.nombre_completo} (${aprendiz.documento || "Sin documento"})`;
            select.appendChild(option);
          });
        }
      } else {
        select.innerHTML = '<option value="" disabled selected>Error al cargar aprendices</option>';
        console.error("Error del servidor:", data?.error);
      }
    } catch (error) {
      console.error("Error en cargarAprendicesParaSelect:", error);
      const select = document.getElementById("create_aprendiz_individual");
      if (select) select.innerHTML = '<option value="" disabled selected>Error de conexión</option>';
    }
  }

  // ==============================
  // APRENDICES (FLUJO GRUPAL / CARGA FICHA)
  // ==============================
  async function cargarAprendicesFicha(idFicha) {
    try {
      console.log("Cargando aprendices para ficha ID:", idFicha);

      const url = API_URL + "?accion=obtener_aprendices_ficha&id_ficha=" + idFicha;
      console.log("URL de solicitud:", url);

      const response = await fetch(url);

      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        const text = await response.text();
        console.error("Respuesta no JSON:", text.substring(0, 500));
        toastError("El servidor no respondió con JSON al cargar aprendices.");
        return false;
      }

      const data = await response.json();
      console.log("Datos recibidos:", data);

      if (data && !data.error) {
        aprendicesFicha = Array.isArray(data) ? data : [];
        console.log(`Cargados ${aprendicesFicha.length} aprendices`);
        return true;
      } else {
        const errorMsg = "Error al cargar aprendices: " + (data?.error || "Desconocido");
        console.error(errorMsg);
        toastError(errorMsg);
        return false;
      }
    } catch (error) {
      console.error("Error cargando aprendices:", error);
      toastError("Error al cargar aprendices: " + error.message);
      return false;
    }
  }

  async function asignarAprendizIndividual(idAprendiz) {
    if (instructorSinFicha) {
      toastError("No puede asignar aprendices porque no tiene una ficha vinculada.");
      return;
    }
    
    if (!OBRAS_PERMS.canCrear) {
      toastError("No tienes permisos para crear obras.");
      return;
    }

    try {
      const response = await fetch(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          accion: "asignar_aprendices",
          id_actividad: obraCreadaId,
          aprendices: [idAprendiz],
        }),
      });

      const result = await response.json();

      if (result && result.success) {
        toastSuccess("Obra individual creada y aprendiz asignado exitosamente");
        closeCreateModal();
        resetearCreacion();
        await cargarObras();
      } else {
        toastError("Error al asignar aprendiz: " + (result?.error || "Desconocido"));
      }
    } catch (error) {
      console.error("Error asignando aprendiz:", error);
      toastError("Error al asignar aprendiz");
    }
  }

  // ==============================
  // MODALES EXTRA (selección / asignación)
  // ==============================
  function openSeleccionarModal() {
    const select = document.getElementById("selectAprendizIndividual");
    if (!select) return;

    if (aprendicesFicha.length === 0) {
      select.innerHTML = '<option value="" disabled selected>No hay aprendices en esta ficha</option>';
    } else {
      select.innerHTML = '<option value="" disabled selected>Selecciona un aprendiz</option>';
      aprendicesFicha.forEach((aprendiz) => {
        const option = document.createElement("option");
        option.value = aprendiz.id_usuario;
        option.textContent = `${aprendiz.nombre_completo} (${aprendiz.documento || "Sin documento"})`;
        select.appendChild(option);
      });
    }

    document.getElementById("modalSeleccionarAprendiz")?.classList.remove("hidden");
  }

  function closeSeleccionarModal() {
    const modal = document.getElementById("modalSeleccionarAprendiz");
    if (modal) modal.classList.add("hidden");
    resetearCreacion();
  }

  function openAsignarModal() {
    const infoObra = document.getElementById("infoObraCreada");
    if (infoObra && obraCreadaData) {
        let codigoFicha = fichaSeleccionadaId; // Por defecto mostrar ID
        if (fichas && Array.isArray(fichas)) {
            const fichaEncontrada = fichas.find(f => f.id_ficha == fichaSeleccionadaId);
            if (fichaEncontrada) {
                codigoFicha = fichaEncontrada.numero_ficha || fichaEncontrada.codigo_ficha || fichaEncontradaId;
            }
        }
        
        infoObra.textContent = `${obraCreadaData?.nombre_actividad || ""} - Ficha: ${codigoFicha}`;
    }

    const select = document.getElementById("selectAprendiz");
    if (!select) return;

    if (aprendicesFicha.length === 0) {
      select.innerHTML = '<option value="" disabled selected>No hay aprendices en esta ficha</option>';
      select.disabled = true;
    } else {
      select.innerHTML = '<option value="" selected disabled>Selecciona un aprendiz</option>';
      aprendicesFicha.forEach((aprendiz) => {
        const option = document.createElement("option");
        option.value = aprendiz.id_usuario;
        option.textContent = `${aprendiz.nombre_completo} (${aprendiz.documento || "Sin documento"})`;
        option.setAttribute("data-nombre", aprendiz.nombre_completo);
        option.setAttribute("data-documento", aprendiz.documento || "");
        select.appendChild(option);
      });
      select.disabled = false;
    }

    aprendicesSeleccionados = [];
    actualizarListaAprendicesSeleccionados();

    const modal = document.getElementById("modalAsignarAprendices");
    if (modal) modal.classList.remove("hidden");
  }

  function closeAsignarModal() {
    const modal = document.getElementById("modalAsignarAprendices");
    if (modal) modal.classList.add("hidden");
    resetearCreacion();
  }

  function agregarAprendizSeleccionado() {
    const select = document.getElementById("selectAprendiz");
    if (!select) return;

    const idAprendiz = select.value;
    if (!idAprendiz) return;

    if (aprendicesSeleccionados.some((a) => a.id_usuario == idAprendiz)) {
      toastInfo("Este aprendiz ya fue seleccionado");
      select.value = "";
      return;
    }

    const option = select.options[select.selectedIndex];
    const aprendiz = {
      id_usuario: idAprendiz,
      nombre_completo: option.getAttribute("data-nombre"),
      documento: option.getAttribute("data-documento"),
    };

    aprendicesSeleccionados.push(aprendiz);
    select.value = "";
    actualizarListaAprendicesSeleccionados();
  }

  function filtrarAprendices() {
    const input = document.getElementById("searchAprendiz");
    const select = document.getElementById("selectAprendiz");
    if (!select) return;

    const searchTerm = (input?.value || "").toLowerCase();

    const aprendicesDisponibles = aprendicesFicha.filter(
      (aprendiz) => !aprendicesSeleccionados.some((a) => a.id_usuario == aprendiz.id_usuario)
    );

    const aprendicesFiltrados = aprendicesDisponibles.filter(
      (aprendiz) =>
        aprendiz.nombre_completo.toLowerCase().includes(searchTerm) ||
        (aprendiz.documento && aprendiz.documento.toLowerCase().includes(searchTerm))
    );

    select.innerHTML = '<option value="" selected disabled>Selecciona un aprendiz</option>';

    if (aprendicesFiltrados.length === 0) {
      select.innerHTML += '<option value="" disabled>No se encontraron aprendices</option>';
    } else {
      aprendicesFiltrados.forEach((aprendiz) => {
        const option = document.createElement("option");
        option.value = aprendiz.id_usuario;
        option.textContent = `${aprendiz.nombre_completo} (${aprendiz.documento || "Sin documento"})`;
        option.setAttribute("data-nombre", aprendiz.nombre_completo);
        option.setAttribute("data-documento", aprendiz.documento || "");
        select.appendChild(option);
      });
    }
  }

  function actualizarListaAprendicesSeleccionados() {
    const container = document.getElementById("listaAprendicesSeleccionados");
    if (!container) return;

    if (aprendicesSeleccionados.length === 0) {
      container.innerHTML =
        '<p class="text-sm text-muted-foreground text-center py-4">No hay aprendices seleccionados</p>';
      return;
    }

    container.innerHTML = aprendicesSeleccionados
      .map(
        (aprendiz, index) => `
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
            <div>
              <p class="text-sm font-medium text-gray-900">${aprendiz.nombre_completo}</p>
              ${aprendiz.documento ? `<p class="text-xs text-gray-500">Documento: ${aprendiz.documento}</p>` : ""}
            </div>
            <button 
              type="button" 
              onclick="removerAprendiz(${index})"
              class="text-red-600 hover:text-red-800 p-1"
              title="Remover"
            >
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
        `
      )
      .join("");
  }

  function removerAprendiz(index) {
    aprendicesSeleccionados.splice(index, 1);
    actualizarListaAprendicesSeleccionados();
    filtrarAprendices();
  }

  async function finalizarCreacionIndividual() {
    const select = document.getElementById("selectAprendizIndividual");
    const idAprendiz = select?.value;

    if (!idAprendiz) {
      toastError("Debes seleccionar un aprendiz");
      return;
    }

    try {
      const response = await fetch(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          accion: "asignar_aprendices",
          id_actividad: obraCreadaId,
          aprendices: [idAprendiz],
        }),
      });

      const result = await response.json();

      if (result && result.success) {
        toastSuccess("Obra individual creada y aprendiz asignado exitosamente");
        closeSeleccionarModal();
        closeCreateModal();
        resetearCreacion();
        await cargarObras();
      } else {
        toastError("Error al asignar aprendiz: " + (result?.error || "Desconocido"));
      }
    } catch (error) {
      console.error("Error asignando aprendiz:", error);
      toastError("Error al asignar aprendiz");
    }
  }

  async function finalizarCreacionGrupal() {
    if (aprendicesSeleccionados.length === 0) {
      toastError("Debes seleccionar al menos un aprendiz");
      return;
    }

    const btn = document.getElementById("btnFinalizarGrupal");
    const btnText = document.getElementById("btnFinalizarText");
    const btnLoading = document.getElementById("btnFinalizarLoading");

    if (btn) btn.disabled = true;
    btnText?.classList.add("hidden");
    btnLoading?.classList.remove("hidden");

    try {
      const idsAprendices = aprendicesSeleccionados.map((a) => a.id_usuario);

      const response = await fetch(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          accion: "asignar_aprendices",
          id_actividad: obraCreadaId,
          aprendices: idsAprendices,
        }),
      });

      const result = await response.json();

      if (result && result.success) {
        toastSuccess(
          `Obra grupal creada y ${aprendicesSeleccionados.length} aprendices asignados exitosamente`
        );
        closeAsignarModal();
        closeCreateModal();
        resetearCreacion();
        await cargarObras();
      } else {
        toastError("Error al asignar aprendices: " + (result?.error || "Desconocido"));
      }
    } catch (error) {
      console.error("Error asignando aprendices:", error);
      toastError("Error al asignar aprendices");
    } finally {
      if (btn) btn.disabled = false;
      btnText?.classList.remove("hidden");
      btnLoading?.classList.add("hidden");
    }
  }

  function resetearCreacion() {
    obraCreadaId = null;
    obraCreadaData = null;
    tipoTrabajoActual = "";
    fichaSeleccionadaId = null;
    aprendicesSeleccionados = [];
    aprendicesFicha = [];
  }

  // ==============================
  // INICIALIZACIÓN
  // ==============================
  document.addEventListener("DOMContentLoaded", () => {
    console.log("Inicializando módulo de obras...");

    // ==============================
    // EMPTY SEARCH CONTAINER FOR OBRAS (FUERA DEL CONTAINER)
    // ==============================
    let emptySearchObrasContainer = document.getElementById("emptySearchObras");

    if (!emptySearchObrasContainer) {
      const obrasContainer = document.getElementById("obrasContainer");
      
      if (obrasContainer) {
        emptySearchObrasContainer = document.createElement("div");
        emptySearchObrasContainer.id = "emptySearchObras";
        
        emptySearchObrasContainer.className =
          "hidden flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full";
        
        emptySearchObrasContainer.innerHTML = `
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
            No se encontraron obras que coincidan con los criterios de búsqueda actuales.
          </p>
        `;
        
        // Insertar antes del contenedor de obras
        obrasContainer.parentNode.insertBefore(emptySearchObrasContainer, obrasContainer);
      }
    }

    // Empty state para cuando no hay obras
    let emptyStateObras = document.getElementById("emptyStateObras");

    if (!emptyStateObras) {
      const obrasContainer = document.getElementById("obrasContainer");
      
      if (obrasContainer) {
        emptyStateObras = document.createElement("div");
        emptyStateObras.id = "emptyStateObras";
        
        emptyStateObras.className = "hidden overflow-visible rounded-lg border border-border bg-card relative p-6 mb-6";
        
        emptyStateObras.innerHTML = `
          <div class="flex flex-col items-center justify-center py-8 px-4">
            <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-foreground mb-2">No hay obras registradas</h3>
            <p class="text-sm text-muted-foreground text-center max-w-md">
              ${OBRAS_PERMS.canCrear ? 'Comienza creando una nueva obra usando el botón "Nueva Obra".' : 'Actualmente no hay obras registradas en el sistema.'}
            </p>
          </div>
        `;
        
        // Insertar antes del contenedor de obras
        obrasContainer.parentNode.insertBefore(emptyStateObras, obrasContainer);
      }
    }

    setupSidebarDetection();
    aplicarPermisosUI();

    // Bind submit para CREATE (formEdit ya tiene onsubmit en HTML)
    const formCreate = document.getElementById("formCreate");
    if (formCreate) {
      formCreate.addEventListener("submit", handleCreateObra);
    }

    cargarObras();

    // Listeners del modal create (tipo y ficha)
    const createTipo = document.getElementById("create_tipo");
    if (createTipo) {
      createTipo.addEventListener("change", function () {
        const container = document.getElementById("containerAprendizIndividual");
        if (!container) return;

        if (this.value === "Individual") {
          container.classList.remove("hidden");
          
          // IMPORTANTE: Verificar que el select de fichas NO esté deshabilitado
          const fichaSelect = document.getElementById("create_ficha");
          if (fichaSelect && !fichaSelect.disabled && fichaSelect.value) {
            cargarAprendicesParaSelect(fichaSelect.value);
          } else {
            const selectAprendiz = document.getElementById("create_aprendiz_individual");
            if (selectAprendiz) {
              selectAprendiz.innerHTML = '<option value="" disabled selected>Selecciona primero una ficha</option>';
            }
          }
        } else {
          container.classList.add("hidden");
        }
      });
    }

    const createFicha = document.getElementById("create_ficha");
    if (createFicha) {
      createFicha.addEventListener("change", function () {
        // IMPORTANTE: Verificar que NO esté deshabilitado
        if (this.disabled) {
          console.warn("Select de ficha está deshabilitado, no se puede cambiar");
          return;
        }
        
        const tipo = document.getElementById("create_tipo")?.value;
        if (tipo === "Individual" && this.value) {
          cargarAprendicesParaSelect(this.value);
        }
        
        // También cargar RAEs específicos de esta ficha
        if (this.value) {
          handleFichaChange.call(this);
        }
      });
    }

    const editFicha = document.getElementById("edit_ficha");
    if (editFicha) {
      editFicha.addEventListener("change", function () {
        if (this.disabled) {
          console.warn("Select de ficha (edit) está deshabilitado, no se puede cambiar");
          return;
        }

        // Llamar al manejador para cargar RAEs e instructores por ficha
        handleFichaChange.call(this);
      });
    }

    // Si tienes buscador por input
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
      searchInput.addEventListener("input", searchObras);
    }

    // Si tienes input filtro en modal grupal
    const searchAprendiz = document.getElementById("searchAprendiz");
    if (searchAprendiz) {
      searchAprendiz.addEventListener("input", filtrarAprendices);
    }

    // CERRAR MENÚS CUANDO SE HACE CLICK AFUERA (DENTRO DE DOMCONTENTLOADED)
    document.addEventListener("click", (event) => {
      const isMenuButton = event.target.closest('button[onclick^="toggleActionMenu"]');
      const isMenuContent = event.target.closest('[id^="actionMenu"]');

      if (!isMenuButton && !isMenuContent) {
        closeAllMenus();
      }
    });
  });

  // ==============================
  // ESC PARA CERRAR MODALES
  // ==============================
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeCreateModal();
      closeEditModal();
      closeDetailsModal();
      closeSeleccionarModal();
      closeAsignarModal();

      const confirmModal = document.querySelector(".fixed.inset-0.bg-black.bg-opacity-50");
      if (confirmModal && confirmModal.parentNode && !confirmModal.id) {
        document.body.removeChild(confirmModal);
      }
    }
  });

  // ==============================
  // EXPONER FUNCIONES A WINDOW (para onclick en HTML)
  // ==============================
  window.fetchAPI = fetchAPI;
  window.cargarObras = cargarObras;
  window.cargarDatosMaestros = cargarDatosMaestros;

  window.searchObras = searchObras;
  window.toggleEstado = toggleEstado;

  window.openCreateModal = openCreateModal;
  window.closeCreateModal = closeCreateModal;
  window.handleCreateObra = handleCreateObra;

  window.openEditModal = openEditModal;
  window.closeEditModal = closeEditModal;
  window.handleEditObra = handleEditObra;

  window.openDetailsModal = openDetailsModal;
  window.closeDetailsModal = closeDetailsModal;

  window.cargarAprendicesParaSelect = cargarAprendicesParaSelect;

  window.showConfirmationDialog = showConfirmationDialog;

  window.openSeleccionarModal = openSeleccionarModal;
  window.closeSeleccionarModal = closeSeleccionarModal;

  window.openAsignarModal = openAsignarModal;
  window.closeAsignarModal = closeAsignarModal;

  window.agregarAprendizSeleccionado = agregarAprendizSeleccionado;
  window.filtrarAprendices = filtrarAprendices;
  window.actualizarListaAprendicesSeleccionados = actualizarListaAprendicesSeleccionados;
  window.removerAprendiz = removerAprendiz;

  window.finalizarCreacionIndividual = finalizarCreacionIndividual;
  window.finalizarCreacionGrupal = finalizarCreacionGrupal;

  window.toastError = toastError;
  window.toastSuccess = toastSuccess;
  window.toastInfo = toastInfo;

  window.toggleActionMenu = toggleActionMenu;
  window.closeAllMenus = closeAllMenus;

  // AÑADIR estas funciones que también se usan:
  window.asignarAprendizIndividual = asignarAprendizIndividual;
  window.resetearCreacion = resetearCreacion;
  window.cargarRaesPorFicha = cargarRaesPorFicha;
  window.handleFichaChange = handleFichaChange;
  window.verificarEstadoSelects = verificarEstadoSelects;

  // Función para debug
  function verificarEstadoSelects() {
    console.log("🔍 Estado de selects:");
    
    const create_ficha = document.getElementById("create_ficha");
    const edit_ficha = document.getElementById("edit_ficha");
    const create_instructor = document.getElementById("create_instructor");
    const edit_instructor = document.getElementById("edit_instructor");
    
    if (create_ficha) console.log("create_ficha disabled:", create_ficha.disabled, "value:", create_ficha.value);
    if (edit_ficha) console.log("edit_ficha disabled:", edit_ficha.disabled, "value:", edit_ficha.value);
    if (create_instructor) console.log("create_instructor disabled:", create_instructor.disabled, "value:", create_instructor.value);
    if (edit_instructor) console.log("edit_instructor disabled:", edit_instructor.disabled, "value:", edit_instructor.value);
    
    console.log("esInstructor:", esInstructor);
    console.log("fichas disponibles:", fichas.length);
  }
}