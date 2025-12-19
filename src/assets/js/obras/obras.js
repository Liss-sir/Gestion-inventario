// Variables globales
let obras = [];
let fichas = [];
let raes = [];
let instructores = [];

// ==============================
// CONFIGURACIÓN DE API - URL FIJA
// ==============================

// URL COMPLETA DE LA API (usa esta)
const API_URL = 'src/controllers/obra_controller.php';

// Para debugging
console.log('API URL configurada:', API_URL);

// ==============================
// DETECCIÓN DEL SIDEBAR
// ==============================

// Función para verificar y aplicar estado del sidebar
function setupSidebarDetection() {
    // Verificar estado inicial
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('coll') === '1') {
        document.body.classList.add('sidebar-collapsed');
    }
    
    // Observar cambios futuros
    document.addEventListener('click', function(e) {
        if (e.target.closest('a[href*="coll="]')) {
            setTimeout(() => {
                const newParams = new URLSearchParams(window.location.search);
                if (newParams.get('coll') === '1') {
                    document.body.classList.add('sidebar-collapsed');
                } else {
                    document.body.classList.remove('sidebar-collapsed');
                }
            }, 50);
        }
    });
}

// ==============================
// FUNCIONES DE API
// ==============================

// Función para hacer peticiones a la API
async function fetchAPI(params = {}) {
    try {
        // Construir URL con parámetros
        let url = API_URL;
        
        // Agregar parámetros si existen
        if (Object.keys(params).length > 0) {
            const queryParams = new URLSearchParams(params).toString();
            url += `?${queryParams}`;
        }
        
        console.log('Fetching:', url); // Para debugging
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Verificar que la respuesta sea JSON
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            const text = await response.text();
            console.error('Respuesta no JSON:', text.substring(0, 500));
            throw new Error('El servidor no respondió con JSON. Verifica la ruta.');
        }
        
        const data = await response.json();
        return data;
        
    } catch (error) {
        console.error('Error en fetchAPI:', error);
        throw error;
    }
}

// Cargar obras desde API
async function cargarObras() {
    try {
        console.log('Cargando obras...');
        const data = await fetchAPI({ accion: 'listar' });
        
        console.log('Datos recibidos:', data);
        
        if (data && data.error) {
            mostrarError(`Error del servidor: ${data.error}`);
            return;
        }
        
        obras = data || [];
        console.log(`${obras.length} obras cargadas`);
        updateEstadisticas();
        renderObras(obras);
        
        // Ocultar loading
        const loadingElement = document.getElementById('loading');
        if (loadingElement) {
            loadingElement.style.display = 'none';
        }
        
    } catch (error) {
        console.error('Error completo al cargar obras:', error);
        
        let errorMsg = 'No se pudieron cargar las obras. ';
        errorMsg += `URL intentada: ${API_URL}?accion=listar\n`;
        errorMsg += `Error: ${error.message}`;
        
        mostrarError(errorMsg);
    }
}

// Cargar datos maestros (fichas, raes, instructores)
async function cargarDatosMaestros() {
    try {
        console.log('Cargando datos maestros...');
        
        // Cargar fichas
        const fichasData = await fetchAPI({ accion: 'obtener_fichas' });
        fichas = fichasData || [];
        console.log(`${fichas.length} fichas cargadas`);
        
        // Cargar RAEs
        const raesData = await fetchAPI({ accion: 'obtener_raes' });
        raes = raesData || [];
        console.log(`${raes.length} RAEs cargados`);
        
        // Cargar instructores
        const instructoresData = await fetchAPI({ accion: 'obtener_instructores' });
        instructores = instructoresData || [];
        console.log(`${instructores.length} instructores cargados`);
        
        // Llenar selects del modal de creación
        llenarSelectFichas();
        llenarSelectRaes();
        llenarSelectInstructores();
        
    } catch (error) {
        console.error('Error cargando datos maestros:', error);
        mostrarErrorSelects('Error al cargar opciones');
    }
}

// Llenar select de fichas
function llenarSelectFichas() {
    const select = document.getElementById('create_ficha');
    if (!select) return;
    
    if (fichas.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No hay fichas disponibles</option>';
        return;
    }
    
    select.innerHTML = '<option value="" disabled selected class="text-gray-500">Selecciona una Ficha</option>';
    
    fichas.forEach(ficha => {
        const option = document.createElement('option');
        option.value = ficha.id_ficha;
        option.textContent = ficha.numero_ficha;
        select.appendChild(option);
    });
}

// Llenar select de RAEs
function llenarSelectRaes() {
    const select = document.getElementById('create_rae');
    if (!select) return;
    
    if (raes.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No hay RAEs disponibles</option>';
        return;
    }
    
    select.innerHTML = '<option value="" disabled selected class="text-gray-500">Selecciona un RAE</option>';
    
    raes.forEach(rae => {
        const option = document.createElement('option');
        option.value = rae.id_rae;
        option.textContent = rae.descripcion_rae;
        select.appendChild(option);
    });
}

// Llenar select de instructores
function llenarSelectInstructores() {
    const select = document.getElementById('create_instructor');
    if (!select) return;
    
    if (instructores.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No hay instructores disponibles</option>';
        return;
    }
    
    select.innerHTML = '<option value="" disabled selected class="text-gray-500">Selecciona un instructor</option>';
    
    instructores.forEach(instructor => {
        const option = document.createElement('option');
        option.value = instructor.id_usuario;
        option.textContent = instructor.nombre_completo;
        select.appendChild(option);
    });
}

// Mostrar error en selects
function mostrarErrorSelects(mensaje) {
  const selects = ['create_ficha', 'create_rae', 'create_instructor'];
  selects.forEach(selectId => {
    const select = document.getElementById(selectId);
    if (select) {
      select.innerHTML = `<option value="" disabled selected class="text-red-500">${mensaje}</option>`;
    }
  });
  toastError(mensaje);
}

// ==============================
// INICIALIZACIÓN
// ==============================

// Cargar datos al iniciar
document.addEventListener('DOMContentLoaded', () => {
    console.log('Inicializando módulo de obras...');
    setupSidebarDetection();
    cargarObras();
});

// ==============================
// FUNCIONES DE INTERFAZ
// ==============================

// Actualizar estadísticas
function updateEstadisticas() {
    const total = obras.length;
    const activas = obras.filter(o => o.estado === 'Activa').length;
    const finalizadas = obras.filter(o => o.estado === 'Finalizada').length;

    document.getElementById('totalObras').textContent = total;
    document.getElementById('obrasActivas').textContent = activas;
    document.getElementById('obrasFinalizadas').textContent = finalizadas;
}

// Función renderObras
function renderObras(obrasData) {
    const container = document.getElementById('obrasContainer');

    if (obrasData.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-folder-open text-4xl mb-3"></i>
                <p>No se encontraron obras</p>
            </div>
        `;
        return;
    }

    container.innerHTML = obrasData.map(obra => `
        <div class="border border-l-4 ${obra.estado === 'Activa' ? 'border-l-[#007832]' : 'border-l-[#64748b]'} rounded-lg p-5 mb-4 hover:shadow-md transition-shadow bg-white">
            <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">${obra.nombre_actividad}</h3>
                    <p class="text-sm text-gray-600 mb-4 line-clamp-2">${obra.descripcion || 'Sin descripción'}</p>
                    
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                        <div>
                            <p class="flex text-sm font-medium js-name gap-2 items-center pb-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-puzzle-icon lucide-puzzle"><path d="M15.39 4.39a1 1 0 0 0 1.68-.474 2.5 2.5 0 1 1 3.014 3.015 1 1 0 0 0-.474 1.68l1.683 1.682a2.414 2.414 0 0 1 0 3.414L19.61 15.39a1 1 0 0 1-1.68-.474 2.5 2.5 0 1 0-3.014 3.015 1 1 0 0 1 .474 1.68l-1.683 1.682a2.414 2.414 0 0 1-3.414 0L8.61 19.61a1 1 0 0 0-1.68.474 2.5 2.5 0 1 1-3.014-3.015 1 1 0 0 0 .474-1.68l-1.683-1.682a2.414 2.414 0 0 1 0-3.414L4.39 8.61a1 1 0 0 1 1.68.474 2.5 2.5 0 1 0 3.014-3.015 1 1 0 0 1-.474-1.68l1.683-1.682a2.414 2.414 0 0 1 3.414 0z"/></svg> Ficha *
                            </p>
                            <p class="text-sm font-medium text-gray-900">${obra.numero_ficha || 'N/A'}</p>
                        </div>
                        <div>
                            <p class="flex text-sm font-medium js-name gap-2 items-center pb-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg> Tipo *
                            </p>
                            <span class="inline-block px-2 py-1 bg-secondary text-white text-xs font-semibold rounded-full">${obra.tipo_trabajo}</span>
                        </div>
                        <div>
                            <p class="flex text-sm font-medium js-name gap-2 items-center pb-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar-icon lucide-calendar"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg> Inicio *
                            </p>
                            <p class="text-sm font-medium text-gray-900">${formatDate(obra.fecha_inicio)}</p>
                        </div>
                        <div>
                            <p class="flex text-sm font-medium js-name gap-2 items-center pb-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar-icon lucide-calendar"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg> Fin *
                            </p>
                            <p class="text-sm font-medium text-gray-900">${formatDate(obra.fecha_fin)}</p>
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-600">
                        <span class="font-medium">Instructor:</span> ${obra.nombre_instructor || 'No asignado'}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        <span class="font-medium">RAE:</span> ${obra.descripcion_rae || 'No asignado'}
                    </div>
                </div>
                
                <div class="flex flex-col items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="relative inline-flex items-center cursor-pointer">

                            <input 
                            type="checkbox" 
                            class="sr-only peer"
                            ${obra.estado === 'Activa' ? 'checked' : ''}
                            onchange="toggleEstado(${obra.id_actividad}, this.checked)"
                            data-estado-original="${obra.estado === 'Activa'}"
                            >

                            <!-- Fondo del switch -->
                            <div class="w-11 h-6 bg-[#64748b] rounded-full transition-all peer-checked:bg-[var(--secondary)]"></div>
                            
                            <!-- Botón deslizante -->
                            <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></div>

                        </label>

                        <!-- Estado -->
                        <p class="flex text-sm font-medium js-name gap-2 items-center">
                            ${obra.estado === 'Activa' ? 'Activa' : 'Finalizada'}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            onclick="openDetailsModal(${obra.id_actividad})"
                            class="text-gray-600 hover:text-gray-900 p-2"
                            title="Ver detalles"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye-icon lucide-eye"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        
                        <button 
                            onclick="openEditModal(${obra.id_actividad})"
                            class="text-blue-600 hover:text-blue-800 p-2"
                            title="Editar"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen-icon lucide-square-pen"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

// Formatear fecha
function formatDate(dateString) {
    if (!dateString) return 'No definida';
    
    const date = new Date(dateString + 'T00:00:00');
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

// Formatear fecha completa
function formatFullDate(dateString) {
    if (!dateString) return 'No definida';
    
    const date = new Date(dateString + 'T00:00:00');
    const months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 
                    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear();
    return `${day} de ${month} de ${year}`;
}

// Buscar obras
function searchObras() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();

    if (searchTerm.trim() === '') {
        renderObras(obras);
        return;
    }

    const results = obras.filter(obra => 
        (obra.nombre_actividad && obra.nombre_actividad.toLowerCase().includes(searchTerm)) ||
        (obra.numero_ficha && obra.numero_ficha.toLowerCase().includes(searchTerm)) ||
        (obra.nombre_instructor && obra.nombre_instructor.toLowerCase().includes(searchTerm)) ||
        (obra.descripcion && obra.descripcion.toLowerCase().includes(searchTerm)) ||
        (obra.descripcion_rae && obra.descripcion_rae.toLowerCase().includes(searchTerm))
    );

    renderObras(results);
}

// Alternar estado de obra
async function toggleEstado(id, estado) {
    const accion = estado ? 'activar' : 'finalizar';
    
    try {
        const result = await fetchAPI({ 
            accion: accion, 
            id_actividad: id 
        });
        
        if (result.success) {
            // Recargar obras
            await cargarObras();
            toastSuccess('Estado actualizado exitosamente');
        } else {
            toastError('Error al actualizar estado');
            const checkbox = document.querySelector(`input[onchange="toggleEstado(${id}, this.checked)"]`);
            if (checkbox) {
                checkbox.checked = !estado;
            }
        }
    } catch (error) {
        console.error('Error al cambiar estado:', error);
        toastError('Error al cambiar estado');

        const checkbox = document.querySelector(`input[onchange="toggleEstado(${id}, this.checked)"]`);
        if (checkbox) {
            checkbox.checked = !estado;
        }
    }
}

// Función para mostrar diálogo de confirmación personalizado
function showConfirmationDialog(title, message) {
  return new Promise((resolve) => {
    // Crear overlay
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000]';
    
    // Crear modal
    const modal = document.createElement('div');
    modal.className = 'bg-white rounded-lg shadow-xl w-full max-w-md mx-4';
    
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
    
    // Manejar eventos
    document.getElementById('confirmCancel').addEventListener('click', () => {
      document.body.removeChild(overlay);
      resolve(false);
    });
    
    document.getElementById('confirmAccept').addEventListener('click', () => {
      document.body.removeChild(overlay);
      resolve(true);
    });
    
    // Cerrar al hacer clic fuera del modal
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        document.body.removeChild(overlay);
        resolve(false);
      }
    });
    
    // Cerrar con ESC
    const handleEsc = (e) => {
      if (e.key === 'Escape') {
        document.body.removeChild(overlay);
        document.removeEventListener('keydown', handleEsc);
        resolve(false);
      }
    };
    document.addEventListener('keydown', handleEsc);
  });
}

// ==============================
// MODALES
// ==============================

// Modal crear obra
async function openCreateModal() {
    // Asegurar que los datos maestros estén cargados
    if (fichas.length === 0) {
        await cargarDatosMaestros();
    }
    
    document.getElementById('modalCreate').classList.remove('hidden');
    document.getElementById('formCreate').reset();
}

function closeCreateModal() {
    document.getElementById('modalCreate').classList.add('hidden');
}

// Crear obra
async function handleCreateObra(e) {
    e.preventDefault();
    
    // Recopilar datos primero
    const obraData = {
        id_ficha: document.getElementById('create_ficha').value,
        id_rae: document.getElementById('create_rae').value,
        id_instructor: document.getElementById('create_instructor').value,
        nombre_actividad: document.getElementById('create_nombre').value,
        descripcion: document.getElementById('create_descripcion').value,
        tipo_trabajo: document.getElementById('create_tipo').value,
        fecha_inicio: document.getElementById('create_fecha_inicio').value,
        fecha_fin: document.getElementById('create_fecha_fin').value,
        estado: 'Activa'
    };
    
    // VALIDAR ANTES DE CONTINUAR
    // 1. Validar campos requeridos
    const requiredFields = {
        id_ficha: 'Ficha',
        id_rae: 'RAE', 
        id_instructor: 'Instructor',
        nombre_actividad: 'Nombre de la actividad',
        descripcion: 'Descripción',
        tipo_trabajo: 'Tipo de trabajo',
        fecha_inicio: 'Fecha de inicio',
        fecha_fin: 'Fecha de fin'
    };

    for (const [field, name] of Object.entries(requiredFields)) {
        if (!obraData[field] || obraData[field].toString().trim() === '') {
            toastError(`El campo "${name}" es obligatorio.`);
            return;
        }
    }

    // 2. Validar nombre
    if (obraData.nombre_actividad.trim().length < 3) {
        toastError("El nombre de la actividad debe tener al menos 3 caracteres.");
        return;
    }

    // 3. Validar descripción (MÍNIMO 10 CARACTERES)
    if (!validarDescripcion(obraData.descripcion)) {
        return; // La función ya muestra el error
    }

    // 4. Validar fechas
    if (!validarFechas(obraData.fecha_inicio, obraData.fecha_fin, true)) {
        return; // La función ya muestra el error
    }
    
    // Si todas las validaciones pasan, proceder
    const btnCreate = document.getElementById('btnCreate');
    const btnCreateText = document.getElementById('btnCreateText');
    const btnCreateLoading = document.getElementById('btnCreateLoading');
    
    // Mostrar loading
    btnCreate.disabled = true;
    btnCreateText.classList.add('hidden');
    btnCreateLoading.classList.remove('hidden');
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ...obraData, accion: 'crear' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            toastSuccess('Obra creada exitosamente');
            closeCreateModal();
            await cargarObras(); // Recargar lista
        } else {
            toastError(result.error || 'Error al crear la obra');
        }
    } catch (error) {
        console.error('Error creando obra:', error);
        toastError('Error al crear la obra');
    } finally {
        // Restaurar botón
        btnCreate.disabled = false;
        btnCreateText.classList.remove('hidden');
        btnCreateLoading.classList.add('hidden');
    }
}


// Modal editar obra
async function openEditModal(id) {
    try {
        // Asegurar que los datos maestros estén cargados
        if (fichas.length === 0) {
            await cargarDatosMaestros();
        }
        
        const obra = await fetchAPI({ 
            accion: 'obtener', 
            id_actividad: id 
        });
        
        if (!obra || obra.error) {
            console.error('No se encontró obra con ID:', id);
            toastError('No se pudo cargar la obra');
            return;
        }

        console.log('Datos de obra para editar:', obra);

        // GUARDAR DATOS ORIGINALES PARA VALIDACIÓN
        originalEditData = {
            id_ficha: obra.id_ficha,
            id_rae: obra.id_rae,
            id_instructor: obra.id_instructor,
            nombre_actividad: obra.nombre_actividad,
            descripcion: obra.descripcion || '',
            tipo_trabajo: obra.tipo_trabajo,
            fecha_inicio: obra.fecha_inicio,
            fecha_fin: obra.fecha_fin
        };
        
        // GUARDAR OBRA COMPLETA PARA MANTENER EL ESTADO
        obraOriginal = obra;

        // Llenar los selects con los datos maestros y seleccionar el correcto
        llenarSelectFichasEdit(obra.id_ficha);
        llenarSelectRaesEdit(obra.id_rae);
        llenarSelectInstructoresEdit(obra.id_instructor);

        // Llenar los otros campos del formulario
        document.getElementById('edit_id').value = obra.id_actividad;
        document.getElementById('edit_nombre').value = obra.nombre_actividad;
        document.getElementById('edit_descripcion').value = obra.descripcion || '';
        document.getElementById('edit_tipo').value = obra.tipo_trabajo;
        document.getElementById('edit_fecha_inicio').value = obra.fecha_inicio;
        document.getElementById('edit_fecha_fin').value = obra.fecha_fin;

        // Mostrar el modal
        document.getElementById('modalEdit').classList.remove('hidden');
        
    } catch (error) {
        console.error('Error cargando obra:', error);
        toastError('Error al cargar la obra: ' + error.message);
    }
}

function closeEditModal() {
    document.getElementById('modalEdit').classList.add('hidden');
    originalEditData = null;
    obraOriginal = null;
}

async function handleEditObra(e) {
    e.preventDefault();

    const id = parseInt(document.getElementById('edit_id').value);
    
    // Recopilar datos primero
    const currentData = {
        id_ficha: parseInt(document.getElementById('edit_ficha').value),
        id_rae: parseInt(document.getElementById('edit_rae').value),
        id_instructor: parseInt(document.getElementById('edit_instructor').value),
        nombre_actividad: document.getElementById('edit_nombre').value.trim(),
        descripcion: document.getElementById('edit_descripcion').value.trim(),
        tipo_trabajo: document.getElementById('edit_tipo').value,
        fecha_inicio: document.getElementById('edit_fecha_inicio').value,
        fecha_fin: document.getElementById('edit_fecha_fin').value
    };

    // VALIDAR ANTES DE CONTINUAR
    // 1. Validar campos requeridos
    const requiredFields = {
        id_ficha: 'Ficha',
        id_rae: 'RAE', 
        id_instructor: 'Instructor',
        nombre_actividad: 'Nombre de la actividad',
        descripcion: 'Descripción',
        tipo_trabajo: 'Tipo de trabajo',
        fecha_inicio: 'Fecha de inicio',
        fecha_fin: 'Fecha de fin'
    };

    for (const [field, name] of Object.entries(requiredFields)) {
        if (!currentData[field] || currentData[field].toString().trim() === '') {
            toastError(`El campo "${name}" es obligatorio.`);
            return;
        }
    }

    // 2. Validar nombre
    if (currentData.nombre_actividad.length < 3) {
        toastError("El nombre de la actividad debe tener al menos 3 caracteres.");
        return;
    }

    // 3. Validar descripción (MÍNIMO 10 CARACTERES)
    if (!validarDescripcion(currentData.descripcion)) {
        return; // La función ya muestra el error
    }

    // 4. Validar fechas
    if (!validarFechas(currentData.fecha_inicio, currentData.fecha_fin, false)) {
        return; // La función ya muestra el error
    }

    // Check if there are any changes (only for editing)
    if (originalEditData) {
        const originalDataForComparison = {
            ...originalEditData,
            id_ficha: parseInt(originalEditData.id_ficha),
            id_rae: parseInt(originalEditData.id_rae),
            id_instructor: parseInt(originalEditData.id_instructor),
            nombre_actividad: originalEditData.nombre_actividad.trim(),
            descripcion: (originalEditData.descripcion || '').trim(),
            fecha_inicio: originalEditData.fecha_inicio,
            fecha_fin: originalEditData.fecha_fin
        };

        if (!hasChanges(originalEditData, currentData)) {
            toastInfo("Para actualizar la obra es necesario modificar al menos un dato.");
            return;
        }
    }

    const obraData = {
        id_actividad: id,
        ...currentData,
        estado: obraOriginal ? obraOriginal.estado : 'Activa' // Mantener el estado original
    };

    console.log('Datos a actualizar:', obraData);

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                ...obraData,
                accion: 'actualizar'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            toastSuccess('Obra actualizada exitosamente');
            closeEditModal();
            await cargarObras();
        } else {
            toastError(result.error || 'Error al actualizar la obra');
        }
    } catch (error) {
        console.error('Error actualizando obra:', error);
        toastError('Error al actualizar la obra: ' + error.message);
    }
}

// Modal detalles
async function openDetailsModal(id) {
    try {
        const obra = await fetchAPI({ 
            accion: 'obtener', 
            id_actividad: id 
        });
        
        if (!obra || obra.error) {
            toastError('No se pudo cargar la obra');
            return;
        }

        document.getElementById('details_nombre').textContent = obra.nombre_actividad;
        document.getElementById('details_badge_tipo').textContent = obra.estado === 'Activa' ? 'Activa' : 'Finalizada';
        document.getElementById('details_badge_tipo').className = obra.estado === 'Activa'
            ? 'inline-block px-3 py-1 bg-secondary text-white text-xs font-semibold rounded-full'
            : 'inline-block px-3 py-1 bg-gray-500 text-white text-xs font-semibold rounded-full';
        document.getElementById('details_descripcion').textContent = obra.descripcion || 'Sin descripción';
        document.getElementById('details_ficha').textContent = obra.numero_ficha || 'N/A';
        document.getElementById('details_tipo').textContent = obra.tipo_trabajo;
        document.getElementById('details_instructor').textContent = obra.nombre_instructor || 'No asignado';
        document.getElementById('details_rae').textContent = obra.descripcion_rae || 'No asignado';
        document.getElementById('details_fecha_inicio').textContent = formatFullDate(obra.fecha_inicio);
        document.getElementById('details_fecha_fin').textContent = formatFullDate(obra.fecha_fin);

        document.getElementById('modalDetails').classList.remove('hidden');
    } catch (error) {
        console.error('Error cargando detalles:', error);
        toastError('Error al cargar los detalles');
    }
}

function closeDetailsModal() {
    document.getElementById('modalDetails').classList.add('hidden');
}

// ==============================
// FUNCIONES UTILITARIAS
// ==============================

// Función para mostrar errores
function mostrarError(mensaje) {
    const container = document.getElementById('obrasContainer');
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

// Llenar select de fichas para EDITAR
function llenarSelectFichasEdit(selectedId = null) {
    const select = document.getElementById('edit_ficha');
    if (!select) return;
    
    if (fichas.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No hay fichas disponibles</option>';
        return;
    }
    
    select.innerHTML = '<option value="" disabled class="text-gray-500">Selecciona una Ficha</option>';
    
    fichas.forEach(ficha => {
        const option = document.createElement('option');
        option.value = ficha.id_ficha;
        option.textContent = ficha.numero_ficha;
        if (selectedId && ficha.id_ficha == selectedId) {
            option.selected = true;
        }
        select.appendChild(option);
    });
}

// Llenar select de RAEs para EDITAR
function llenarSelectRaesEdit(selectedId = null) {
    const select = document.getElementById('edit_rae');
    if (!select) return;
    
    if (raes.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No hay RAEs disponibles</option>';
        return;
    }
    
    select.innerHTML = '<option value="" disabled class="text-gray-500">Selecciona un RAE</option>';
    
    raes.forEach(rae => {
        const option = document.createElement('option');
        option.value = rae.id_rae;
        option.textContent = rae.descripcion_rae;
        if (selectedId && rae.id_rae == selectedId) {
            option.selected = true;
        }
        select.appendChild(option);
    });
}

// Llenar select de instructores para EDITAR
function llenarSelectInstructoresEdit(selectedId = null) {
    const select = document.getElementById('edit_instructor');
    if (!select) return;
    
    if (instructores.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No hay instructores disponibles</option>';
        return;
    }
    
    select.innerHTML = '<option value="" disabled class="text-gray-500">Selecciona un instructor</option>';
    
    instructores.forEach(instructor => {
        const option = document.createElement('option');
        option.value = instructor.id_usuario;
        option.textContent = instructor.nombre_completo;
        if (selectedId && instructor.id_usuario == selectedId) {
            option.selected = true;
        }
        select.appendChild(option);
    });
}

// Cerrar modales con tecla ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeCreateModal();
    closeEditModal();
    closeDetailsModal();
    // Limpiar datos de confirmación si existe
    const confirmModal = document.querySelector('.fixed.inset-0.bg-black.bg-opacity-50');
    if (confirmModal) {
      document.body.removeChild(confirmModal);
    }
  }
});

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
// VALIDACIÓN DE CAMBIOS EN EDICIÓN
// =========================

let originalEditData = null; // Variable para almacenar datos originales

/**
 * Check if there are any changes between original and current data
 */
function hasChanges(originalData, currentData) {
  return JSON.stringify(originalData) !== JSON.stringify(currentData);
}

/**
 * Validates obra data before sending to server
 */
/**
 * Validates obra data before sending to server
 */
function validateObraData(data, isEdit = false) {
  // Check required fields
  const requiredFields = {
    id_ficha: 'Ficha',
    id_rae: 'RAE', 
    id_instructor: 'Instructor',
    nombre_actividad: 'Nombre de la actividad',
    descripcion: 'Descripción',
    tipo_trabajo: 'Tipo de trabajo',
    fecha_inicio: 'Fecha de inicio',
    fecha_fin: 'Fecha de fin'
  };

  for (const [field, name] of Object.entries(requiredFields)) {
    if (!data[field]) {
      toastError(`El campo "${name}" es obligatorio.`);
      return false;
    }
  }

  // Validate name length
  if (data.nombre_actividad.trim().length < 3) {
    toastError("El nombre de la actividad debe tener al menos 3 caracteres.");
    return false;
  }

  // Validate description length
  if (data.descripcion.trim().length < 10) {
    toastError("La descripción debe tener al menos 10 caracteres.");
    return false;
  }

  // Validate dates using the new function
  if (!validarFechas(data.fecha_inicio, data.fecha_fin, !isEdit)) {
    return false;
  }

  return true;
}

/**
 * Check if there are any changes between original and current data
 * Mejorada para manejar diferentes tipos de datos
 */
function hasChanges(originalData, currentData) {
  // Convertir todo a string para comparación exacta
  const normalize = (obj) => {
    return {
      id_ficha: parseInt(obj.id_ficha) || 0,
      id_rae: parseInt(obj.id_rae) || 0,
      id_instructor: parseInt(obj.id_instructor) || 0,
      nombre_actividad: (obj.nombre_actividad || '').trim().toLowerCase(),
      descripcion: (obj.descripcion || '').trim().toLowerCase(),
      tipo_trabajo: (obj.tipo_trabajo || '').trim(),
      fecha_inicio: obj.fecha_inicio || '',
      fecha_fin: obj.fecha_fin || ''
    };
  };

  const orig = normalize(originalData);
  const curr = normalize(currentData);

  return JSON.stringify(orig) !== JSON.stringify(curr);
}

// ==============================
// VALIDACIONES DE FECHAS
// ==============================

// Función para validar que fecha de inicio no sea mayor que fecha de fin
function validarFechas(fechaInicio, fechaFin, esCreacion = false) {
    const inicio = new Date(fechaInicio);
    const fin = new Date(fechaFin);
    
    // Validar que las fechas sean válidas
    if (isNaN(inicio.getTime())) {
        toastError("La fecha de inicio no es válida.");
        return false;
    }
    
    if (isNaN(fin.getTime())) {
        toastError("La fecha de fin no es válida.");
        return false;
    }
    
    // Validar que fecha de inicio no sea mayor a fecha de fin
    if (inicio > fin) {
        toastError("La fecha de inicio no puede ser posterior a la fecha de fin.");
        return false;
    }
    
    // Validar que no sea una fecha futura para creación (opcional)
    if (esCreacion) {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0); // Eliminar la parte de tiempo para comparar solo fechas
        
        if (inicio > hoy) {
            toastError("La fecha de inicio no puede ser una fecha futura.");
            return false;
        }
    }
    
    return true;
}

// Función para validar que fecha de inicio no sea mayor que fecha de fin
function validarFechas(fechaInicio, fechaFin, esCreacion = false) {
    const inicio = new Date(fechaInicio);
    const fin = new Date(fechaFin);
    
    // Validar que las fechas sean válidas
    if (isNaN(inicio.getTime())) {
        toastError("La fecha de inicio no es válida.");
        return false;
    }
    
    if (isNaN(fin.getTime())) {
        toastError("La fecha de fin no es válida.");
        return false;
    }
    
    // Validar que fecha de inicio no sea mayor a fecha de fin
    if (inicio > fin) {
        toastError("La fecha de inicio no puede ser posterior a la fecha de fin.");
        return false;
    }
    
    return true;
}

// Función para validar longitud mínima de descripción
function validarDescripcion(descripcion) {
    if (descripcion.trim().length < 10) {
        toastError("La descripción debe tener al menos 10 caracteres.");
        return false;
    }
    return true;
}