// Global variables
let obras = [];
let fichas = [];
let raes = [];
let instructores = [];

let aprendicesFicha = [];
let obraCreadaId = null;
let obraCreadaData = null;
let tipoTrabajoActual = '';
let fichaSeleccionadaId = null;
let aprendicesSeleccionados = [];

// ==============================
// API CONFIGURATION - FIXED URL
// ==============================

const API_URL = 'src/controllers/obra_controller.php';

// For debugging
console.log('API URL configured:', API_URL);

// ==============================
// SIDEBAR DETECTION
// ==============================

// Function to check and apply sidebar state
function setupSidebarDetection() {
    // Check initial state
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('coll') === '1') {
        document.body.classList.add('sidebar-collapsed');
    }
    
    // Watch for future changes
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
// API FUNCTIONS
// ==============================

// Function to make requests to the API
async function fetchAPI(params = {}) {
    try {
        // Build URL with parameters
        let url = API_URL;
        
        // Add parameters if they exist
        if (Object.keys(params).length > 0) {
            const queryParams = new URLSearchParams(params).toString();
            url += `?${queryParams}`;
        }
        
        console.log('Fetching:', url); // For debugging
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Verify that the response is JSON
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            const text = await response.text();
            console.error('Non-JSON response:', text.substring(0, 500));
            throw new Error('The server did not respond with JSON. Check the route.');
        }
        
        const data = await response.json();
        return data;
        
    } catch (error) {
        console.error('Error in fetchAPI:', error);
        throw error;
    }
}

// Load works from API
async function cargarObras() {
    try {
        console.log('Loading works...');
        const data = await fetchAPI({ accion: 'listar' });
        
        console.log('Data received:', data);
        
        if (data && data.error) {
            mostrarError(`Server error: ${data.error}`);
            return;
        }
        
        obras = data || [];
        console.log(`${obras.length} works loaded`);
        updateEstadisticas();
        renderObras(obras);
        
        // Hide loading
        const loadingElement = document.getElementById('loading');
        if (loadingElement) {
            loadingElement.style.display = 'none';
        }
        
    } catch (error) {
        console.error('Complete error when loading works:', error);
        
        let errorMsg = 'Could not load works. ';
        errorMsg += `URL attempted: ${API_URL}?accion=listar\n`;
        errorMsg += `Error: ${error.message}`;
        
        mostrarError(errorMsg);
    }
}

// Load master data (records, raes, instructors)
async function cargarDatosMaestros() {
    try {
        console.log('Loading master data...');
        
        // Load records
        const fichasData = await fetchAPI({ accion: 'obtener_fichas' });
        fichas = fichasData || [];
        console.log(`${fichas.length} records loaded`);
        
        // Load RAEs
        const raesData = await fetchAPI({ accion: 'obtener_raes' });
        raes = raesData || [];
        console.log(`${raes.length} RAEs loaded`);
        
        // Load instructors
        const instructoresData = await fetchAPI({ accion: 'obtener_instructores' });
        instructores = instructoresData || [];
        console.log(`${instructores.length} instructors loaded`);
        
        // Fill modal creation selects
        llenarSelectFichas();
        llenarSelectRaes();
        llenarSelectInstructores();
        
    } catch (error) {
        console.error('Error loading master data:', error);
        mostrarErrorSelects('Error loading options');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('Initializing works module...');
    setupSidebarDetection();
    cargarObras();
    
    // Add listener for type change
    document.getElementById('create_tipo')?.addEventListener('change', function() {
        const container = document.getElementById('containerAprendizIndividual');
        if (this.value === 'Individual') {
            container.classList.remove('hidden');
            // Load learners if there is already a record selected
            const fichaId = document.getElementById('create_ficha').value;
            if (fichaId) {
                cargarAprendicesParaSelect(fichaId);
            }
        } else {
            container.classList.add('hidden');
        }
    });
    
    // Also when the record changes
    document.getElementById('create_ficha')?.addEventListener('change', function() {
        const tipo = document.getElementById('create_tipo').value;
        if (tipo === 'Individual' && this.value) {
            cargarAprendicesParaSelect(this.value);
        }
    });
});

// Fill records select
function llenarSelectFichas() {
    const select = document.getElementById('create_ficha');
    if (!select) return;
    
    if (fichas.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No records available</option>';
        return;
    }
    
    select.innerHTML = '<option value="" disabled selected class="text-gray-500">Select a Record</option>';
    
    fichas.forEach(ficha => {
        const option = document.createElement('option');
        option.value = ficha.id_ficha;
        option.textContent = ficha.numero_ficha;
        select.appendChild(option);
    });
}

// Fill RAEs select
function llenarSelectRaes() {
    const select = document.getElementById('create_rae');
    if (!select) return;
    
    if (raes.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No RAEs available</option>';
        return;
    }
    
    select.innerHTML = '<option value="" disabled selected class="text-gray-500">Select an RAE</option>';
    
    raes.forEach(rae => {
        const option = document.createElement('option');
        option.value = rae.id_rae;
        option.textContent = rae.descripcion_rae;
        select.appendChild(option);
    });
}

// Fill instructors select
function llenarSelectInstructores() {
    const select = document.getElementById('create_instructor');
    if (!select) return;
    
    if (instructores.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No instructors available</option>';
        return;
    }
    
    select.innerHTML = '<option value="" disabled selected class="text-gray-500">Select an instructor</option>';
    
    instructores.forEach(instructor => {
        const option = document.createElement('option');
        option.value = instructor.id_usuario;
        option.textContent = instructor.nombre_completo;
        select.appendChild(option);
    });
}

// Show error in selects
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
// INITIALIZATION
// ==============================

// Load data on startup
document.addEventListener('DOMContentLoaded', () => {
    console.log('Initializing works module...');
    setupSidebarDetection();
    cargarObras();
});

// ==============================
// INTERFACE FUNCTIONS
// ==============================

// Update statistics
function updateEstadisticas() {
    const total = obras.length;
    const activas = obras.filter(o => o.estado === 'Activa').length;
    const finalizadas = obras.filter(o => o.estado === 'Finalizada').length;

    document.getElementById('totalObras').textContent = total;
    document.getElementById('obrasActivas').textContent = activas;
    document.getElementById('obrasFinalizadas').textContent = finalizadas;
}

// RenderObras function
function renderObras(obrasData) {
    const container = document.getElementById('obrasContainer');

    if (obrasData.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-folder-open text-4xl mb-3"></i>
                <p>No works found</p>
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
                            
                            <!-- Sliding button -->
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

// Format date
function formatDate(dateString) {
    if (!dateString) return 'Not defined';
    
    const date = new Date(dateString + 'T00:00:00');
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

function formatFullDate(dateString) {
    if (!dateString) return 'Not defined';
    
    const date = new Date(dateString + 'T00:00:00');
    const months = ['january', 'february', 'march', 'april', 'may', 'june', 
                    'july', 'august', 'september', 'october', 'november', 'december'];
    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear();
    return `${day} of ${month} of ${year}`;
}

// Search works
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

// Toggle work status
async function toggleEstado(id, estado) {
    const accion = estado ? 'activar' : 'finalizar';
    
    try {
        const result = await fetchAPI({ 
            accion: accion, 
            id_actividad: id 
        });
        
        if (result.success) {
            // Reload works
            await cargarObras();
            toastSuccess('Status updated successfully');
        } else {
            toastError('Error updating status');
            const checkbox = document.querySelector(`input[onchange="toggleEstado(${id}, this.checked)"]`);
            if (checkbox) {
                checkbox.checked = !estado;
            }
        }
    } catch (error) {
        console.error('Error changing status:', error);
        toastError('Error changing status');

        const checkbox = document.querySelector(`input[onchange="toggleEstado(${id}, this.checked)"]`);
        if (checkbox) {
            checkbox.checked = !estado;
        }
    }
}

// Function to show custom confirmation dialog
function showConfirmationDialog(title, message) {
  return new Promise((resolve) => {
    // Create overlay
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000]';
    
    // Create modal
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
            Cancel
          </button>
          <button 
            type="button"
            id="confirmAccept"
            class="px-4 py-2 bg-secondary text-white rounded-lg hover:opacity-90 transition-colors"
          >
            Accept
          </button>
        </div>
      </div>
    `;
    
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    
    // Handle events
    document.getElementById('confirmCancel').addEventListener('click', () => {
      document.body.removeChild(overlay);
      resolve(false);
    });
    
    document.getElementById('confirmAccept').addEventListener('click', () => {
      document.body.removeChild(overlay);
      resolve(true);
    });
    
    // Close when clicking outside the modal
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        document.body.removeChild(overlay);
        resolve(false);
      }
    });
    
    // Close with ESC
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
// MODALS
// ==============================

// Create work modal
async function openCreateModal() {
    // Ensure that master data is loaded
    if (fichas.length === 0) {
        await cargarDatosMaestros();
    }
    
    document.getElementById('modalCreate').classList.remove('hidden');
    document.getElementById('formCreate').reset();
}

function closeCreateModal() {
    document.getElementById('modalCreate').classList.add('hidden');
}

// Create work
// Find the handleCreateObra function and replace it with:
async function handleCreateObra(e) {
    e.preventDefault();
    
    // Get form data
    const obraData = {
        id_ficha: document.getElementById('create_ficha').value,
        id_rae: document.getElementById('create_rae').value,
        id_instructor: document.getElementById('create_instructor').value,
        nombre_actividad: document.getElementById('create_nombre').value.trim(),
        descripcion: document.getElementById('create_descripcion').value.trim(),
        tipo_trabajo: document.getElementById('create_tipo').value,
        fecha_inicio: document.getElementById('create_fecha_inicio').value,
        fecha_fin: document.getElementById('create_fecha_fin').value,
        estado: 'Activa'
    };

    // If it is Individual, get the learner from the select
    if (obraData.tipo_trabajo === 'Individual') {
        const aprendizId = document.getElementById('create_aprendiz_individual').value;
        if (!aprendizId) {
            toastError('You must select a learner for the individual work');
            return;
        }
        obraData.aprendiz_seleccionado = aprendizId;
    }

    // Save type and record for later use
    tipoTrabajoActual = obraData.tipo_trabajo;
    fichaSeleccionadaId = obraData.id_ficha;

    // Validations (the ones you already have)
    if (!validateObraData(obraData, false)) {
        return;
    }

    const btnCreate = document.getElementById('btnCreate');
    const btnCreateText = document.getElementById('btnCreateText');
    const btnCreateLoading = document.getElementById('btnCreateLoading');
    
    // Show loading
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
            obraCreadaId = result.id_actividad;
            obraCreadaData = obraData;
            
            if (tipoTrabajoActual === 'Individual') {
                // Assign individual learner
                await asignarAprendizIndividual(obraData.aprendiz_seleccionado);
            } else if (tipoTrabajoActual === 'Grupal') {
                // Load learners from record and show modal group
                await cargarAprendicesFicha(fichaSeleccionadaId);
                openAsignarModal();
            } else {
                // If there is no specific type, just create
                toastSuccess('Work created successfully');
                closeCreateModal();
                await cargarObras();
            }
        } else {
            toastError(result.error || 'Error creating work');
        }
    } catch (error) {
        console.error('Error creating work:', error);
        toastError('Error creating work');
    } finally {
        // Restore button
        btnCreate.disabled = false;
        btnCreateText.classList.remove('hidden');
        btnCreateLoading.classList.add('hidden');
    }
}


// Edit work modal
async function openEditModal(id) {
    try {
        // Ensure that master data is loaded
        if (fichas.length === 0) {
            await cargarDatosMaestros();
        }
        
        const obra = await fetchAPI({ 
            accion: 'obtener', 
            id_actividad: id 
        });
        
        if (!obra || obra.error) {
            toastError('Could not load work');
            console.error('Work not found with ID:', id);
            return;
        }

        console.log('Data of work to edit:', obra);

        // SAVE ORIGINAL DATA FOR VALIDATION
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
        
        // SAVE COMPLETE WORK TO MAINTAIN STATUS
        obraOriginal = obra;

        // Fill selects with master data and select the correct one
        llenarSelectFichasEdit(obra.id_ficha);
        llenarSelectRaesEdit(obra.id_rae);
        llenarSelectInstructoresEdit(obra.id_instructor);

        // Fill other form fields
        document.getElementById('edit_id').value = obra.id_actividad;
        document.getElementById('edit_nombre').value = obra.nombre_actividad;
        document.getElementById('edit_descripcion').value = obra.descripcion || '';
        document.getElementById('edit_tipo').value = obra.tipo_trabajo;
        document.getElementById('edit_fecha_inicio').value = obra.fecha_inicio;
        document.getElementById('edit_fecha_fin').value = obra.fecha_fin;

        // Show the modal
        document.getElementById('modalEdit').classList.remove('hidden');
        
    } catch (error) {
        console.error('Error loading work:', error);
        toastError('Error loading work: ' + error.message);
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
    
    // Collect data first
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

    // VALIDATE BEFORE CONTINUING
    // 1. Validate required fields
    const requiredFields = {
        id_ficha: 'Record',
        id_rae: 'RAE', 
        id_instructor: 'Instructor',
        nombre_actividad: 'Activity name',
        descripcion: 'Description',
        tipo_trabajo: 'Work type',
        fecha_inicio: 'Start date',
        fecha_fin: 'End date'
    };

    for (const [field, name] of Object.entries(requiredFields)) {
        if (!currentData[field] || currentData[field].toString().trim() === '') {
            toastError(`The field "${name}" is required.`);
            return;
        }
    }

    // 2. Validate name
    if (currentData.nombre_actividad.length < 3) {
        toastError("The activity name must be at least 3 characters.");
        return;
    }

    // 3. Validate description (MINIMUM 10 CHARACTERS)
    if (!validarDescripcion(currentData.descripcion)) {
        return; // The function already shows the error
    }

    // 4. Validate dates
    if (!validarFechas(currentData.fecha_inicio, currentData.fecha_fin, false)) {
        return; // The function already shows the error
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
            toastInfo("To update the work you need to modify at least one piece of data.");
            return;
        }
    }

    const obraData = {
        id_actividad: id,
        ...currentData,
        estado: obraOriginal ? obraOriginal.estado : 'Activa' // Maintain original status
    };

    console.log('Data to update:', obraData);

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
            toastSuccess('Work updated successfully');
            closeEditModal();
            await cargarObras();
        } else {
            toastError(result.error || 'Error updating work');
        }
    } catch (error) {
        console.error('Error updating work:', error);
        toastError('Error updating work: ' + error.message);
    }
}

// Details modal
async function openDetailsModal(id) {
    try {
        const obra = await fetchAPI({ 
            accion: 'obtener', 
            id_actividad: id 
        });
        
        if (!obra || obra.error) {
            toastError('Could not load work');
            return;
        }

        document.getElementById('details_nombre').textContent = obra.nombre_actividad;
        document.getElementById('details_badge_tipo').textContent = obra.estado === 'Activa' ? 'Active' : 'Completed';
        document.getElementById('details_badge_tipo').className = obra.estado === 'Activa'
            ? 'inline-block px-3 py-1 bg-secondary text-white text-xs font-semibold rounded-full'
            : 'inline-block px-3 py-1 bg-gray-500 text-white text-xs font-semibold rounded-full';
        document.getElementById('details_descripcion').textContent = obra.descripcion || 'No description';
        document.getElementById('details_ficha').textContent = obra.numero_ficha || 'N/A';
        document.getElementById('details_tipo').textContent = obra.tipo_trabajo;
        document.getElementById('details_instructor').textContent = obra.nombre_instructor || 'Not assigned';
        document.getElementById('details_rae').textContent = obra.descripcion_rae || 'Not assigned';
        document.getElementById('details_fecha_inicio').textContent = formatFullDate(obra.fecha_inicio);
        document.getElementById('details_fecha_fin').textContent = formatFullDate(obra.fecha_fin);

        document.getElementById('modalDetails').classList.remove('hidden');
    } catch (error) {
        console.error('Error loading details:', error);
        toastError('Error loading details');
    }
}

function closeDetailsModal() {
    document.getElementById('modalDetails').classList.add('hidden');
}

// ==============================
// UTILITY FUNCTIONS
// ==============================

// Function to show errors
function mostrarError(mensaje) {
    const container = document.getElementById('obrasContainer');
    if (!container) return;
    
    container.innerHTML = `
        <div class="text-center py-12 text-red-500">
            <i class="fas fa-exclamation-triangle text-4xl mb-3"></i>
            <p class="mb-2 font-medium">Error</p>
            <p class="text-sm mb-4 whitespace-pre-line">${mensaje}</p>
            <button onclick="cargarObras()" class="mt-4 px-4 py-2 bg-secondary text-white rounded hover:opacity-90">
                Retry
            </button>
        </div>
    `;
}

// Fill records select for EDIT
function llenarSelectFichasEdit(selectedId = null) {
    const select = document.getElementById('edit_ficha');
    if (!select) return;
    
    if (fichas.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No records available</option>';
        return;
    }
    
    select.innerHTML = '<option value="" disabled class="text-gray-500">Select a Record</option>';
    
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

// Fill RAEs select for EDIT
function llenarSelectRaesEdit(selectedId = null) {
    const select = document.getElementById('edit_rae');
    if (!select) return;
    
    if (raes.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No RAEs available</option>';
        return;
    }
    
    select.innerHTML = '<option value="" disabled class="text-gray-500">Select an RAE</option>';
    
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

// Fill instructors select for EDIT
function llenarSelectInstructoresEdit(selectedId = null) {
    const select = document.getElementById('edit_instructor');
    if (!select) return;
    
    if (instructores.length === 0) {
        select.innerHTML = '<option value="" disabled selected class="text-red-500">No instructors available</option>';
        return;
    }
    
    select.innerHTML = '<option value="" disabled class="text-gray-500">Select an instructor</option>';
    
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

// Close modals with ESC key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeCreateModal();
    closeEditModal();
    closeDetailsModal();
    // Clean confirmation data if it exists
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
// VALIDATION OF CHANGES IN EDITING
// =========================

let originalEditData = null; // Variable to store original data

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
 * Validates work data before sending to server
 */
function validateObraData(data, isEdit = false) {
  // Check required fields
  const requiredFields = {
    id_ficha: 'Record',
    id_rae: 'RAE', 
    id_instructor: 'Instructor',
    nombre_actividad: 'Activity name',
    descripcion: 'Description',
    tipo_trabajo: 'Work type',
    fecha_inicio: 'Start date',
    fecha_fin: 'End date'
  };

  for (const [field, name] of Object.entries(requiredFields)) {
    if (!data[field]) {
      toastError(`The field "${name}" is required.`);
      return false;
    }
  }

  // Validate name length
  if (data.nombre_actividad.trim().length < 3) {
    toastError("The activity name must be at least 3 characters.");
    return false;
  }

  // Validate description length
  if (data.descripcion.trim().length < 10) {
    toastError("The description must be at least 10 characters.");
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
 * Improved to handle different types of data
 */
function hasChanges(originalData, currentData) {
  // Convert everything to string for exact comparison
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
// DATE VALIDATIONS
// ==============================

// Function to validate that start date is not greater than end date
function validarFechas(fechaInicio, fechaFin, esCreacion = false) {
    const inicio = new Date(fechaInicio);
    const fin = new Date(fechaFin);
    
    // Validate that the dates are valid
    if (isNaN(inicio.getTime())) {
        toastError("Start date is not valid.");
        return false;
    }
    
    if (isNaN(fin.getTime())) {
        toastError("End date is not valid.");
        return false;
    }
    
    // Validate that start date is not greater than end date
    if (inicio > fin) {
        toastError("Start date cannot be later than end date.");
        return false;
    }
    
    // Validate that it is not a future date for creation (optional)
    if (esCreacion) {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0); // Remove the time part to compare only dates
        
        if (inicio > hoy) {
            toastError("Start date cannot be a future date.");
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
        toastError("The start date is not valid.");
        return false;
    }
    
    if (isNaN(fin.getTime())) {
        toastError("The end date is not valid.");
        return false;
    }
    
    // Validar que fecha de inicio no sea mayor a fecha de fin
    if (inicio > fin) {
        toastError("The start date cannot be later than the end date.");
        return false;
    }
    
    return true;
}

// Función para validar longitud mínima de descripción
function validarDescripcion(descripcion) {
    if (descripcion.trim().length < 10) {
        toastError("Description must be at least 10 characters.");
        return false;
    }
    return true;
}

// ==============================
// FUNCTIONS FOR LEARNERS
// ==============================

// Function to load learners for individual select
async function cargarAprendicesParaSelect(idFicha) {
    try {
        const select = document.getElementById('create_aprendiz_individual');
        select.innerHTML = '<option value="" disabled selected>Loading learners...</option>';
        
        const response = await fetch(API_URL + '?accion=obtener_aprendices_ficha&id_ficha=' + idFicha);
        const data = await response.json();
        
        if (data && !data.error) {
            if (data.length === 0) {
                select.innerHTML = '<option value="" disabled selected>No learners in this record</option>';
            } else {
                select.innerHTML = '<option value="" disabled selected>Select a learner</option>';
                data.forEach(aprendiz => {
                    const option = document.createElement('option');
                    option.value = aprendiz.id_usuario;
                    option.textContent = `${aprendiz.nombre_completo} (${aprendiz.documento || 'No document'})`;
                    select.appendChild(option);
                });
            }
        } else {
            select.innerHTML = '<option value="" disabled selected>Error loading learners</option>';
            toastError('Error loading learners');
        }
    } catch (error) {
        console.error('Error loading learners:', error);
        toastError('Error loading learners');
    }
}

// New function to load learners from a record
async function cargarAprendicesFicha(idFicha) {
    try {
        const response = await fetch(API_URL + '?accion=obtener_aprendices_ficha&id_ficha=' + idFicha);
        const data = await response.json();
        
        if (data && !data.error) {
            aprendicesFicha = data;
            return true;
        } else {
            toastError('Error loading learners: ' + (data.error || 'Unknown'));
            return false;
        }
    } catch (error) {
        console.error('Error loading learners:', error);
        toastError('Error loading learners');
        return false;
    }
}

// Function to assign individual learner
async function asignarAprendizIndividual(idAprendiz) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                accion: 'asignar_aprendices',
                id_actividad: obraCreadaId,
                aprendices: [idAprendiz]
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            toastSuccess('Individual work created and learner assigned successfully');
            closeCreateModal();
            await cargarObras();
        } else {
            toastError('Error assigning learner: ' + (result.error || 'Unknown'));
        }
    } catch (error) {
        console.error('Error assigning learner:', error);
        toastError('Error assigning learner');
    }
}

// Function to open individual selection modal
function openSeleccionarModal() {
    const select = document.getElementById('selectAprendizIndividual');
    
    if (aprendicesFicha.length === 0) {
        select.innerHTML = '<option value="" disabled selected>No learners in this record</option>';
    } else {
        select.innerHTML = '<option value="" disabled selected>Select a learner</option>';
        aprendicesFicha.forEach(aprendiz => {
            const option = document.createElement('option');
            option.value = aprendiz.id_usuario;
            option.textContent = `${aprendiz.nombre_completo} (${aprendiz.documento || 'No document'})`;
            select.appendChild(option);
        });
    }
    
    document.getElementById('modalSeleccionarAprendiz').classList.remove('hidden');
}

function closeSeleccionarModal() {
    document.getElementById('modalSeleccionarAprendiz').classList.add('hidden');
    resetearCreacion();
}

// Function to open group assignment modal
function openAsignarModal() {
    // Update work information
    document.getElementById('infoObraCreada').textContent = 
        `${obraCreadaData.nombre_actividad} - Record: ${fichaSeleccionadaId}`;
    
    // Fill learners select
    const select = document.getElementById('selectAprendiz');
    
    if (aprendicesFicha.length === 0) {
        select.innerHTML = '<option value="" disabled selected>No learners in this record</option>';
        select.disabled = true;
    } else {
        select.innerHTML = '<option value="" selected disabled>Select a learner</option>';
        aprendicesFicha.forEach(aprendiz => {
            const option = document.createElement('option');
            option.value = aprendiz.id_usuario;
            option.textContent = `${aprendiz.nombre_completo} (${aprendiz.documento || 'No document'})`;
            option.setAttribute('data-nombre', aprendiz.nombre_completo);
            option.setAttribute('data-documento', aprendiz.documento || '');
            select.appendChild(option);
        });
        select.disabled = false;
    }
    
    // Clear list of selected learners
    aprendicesSeleccionados = [];
    actualizarListaAprendicesSeleccionados();
    
    document.getElementById('modalAsignarAprendices').classList.remove('hidden');
}

function closeAsignarModal() {
    document.getElementById('modalAsignarAprendices').classList.add('hidden');
    resetearCreacion();
}

// Function to add selected learner (group)
function agregarAprendizSeleccionado() {
    const select = document.getElementById('selectAprendiz');
    const idAprendiz = select.value;
    
    if (!idAprendiz) return;
    
    // Verify that it is not already selected
    if (aprendicesSeleccionados.some(a => a.id_usuario == idAprendiz)) {
        toastInfo('This learner has already been selected');
        select.value = '';
        return;
    }
    
    // Get learner data
    const option = select.options[select.selectedIndex];
    const aprendiz = {
        id_usuario: idAprendiz,
        nombre_completo: option.getAttribute('data-nombre'),
        documento: option.getAttribute('data-documento')
    };
    
    // Add to the list
    aprendicesSeleccionados.push(aprendiz);
    
    // Clear select
    select.value = '';
    
    // Update visual list
    actualizarListaAprendicesSeleccionados();
}

// Function to filter learners in the group modal
function filtrarAprendices() {
    const searchTerm = document.getElementById('searchAprendiz').value.toLowerCase();
    const select = document.getElementById('selectAprendiz');
    
    // Filter available learners (not selected)
    const aprendicesDisponibles = aprendicesFicha.filter(aprendiz => 
        !aprendicesSeleccionados.some(a => a.id_usuario == aprendiz.id_usuario)
    );
    
    const aprendicesFiltrados = aprendicesDisponibles.filter(aprendiz => 
        aprendiz.nombre_completo.toLowerCase().includes(searchTerm) ||
        (aprendiz.documento && aprendiz.documento.toLowerCase().includes(searchTerm))
    );
    
    // Update select
    select.innerHTML = '<option value="" selected disabled>Select a learner</option>';
    
    if (aprendicesFiltrados.length === 0) {
        select.innerHTML += '<option value="" disabled>No learners found</option>';
    } else {
        aprendicesFiltrados.forEach(aprendiz => {
            const option = document.createElement('option');
            option.value = aprendiz.id_usuario;
            option.textContent = `${aprendiz.nombre_completo} (${aprendiz.documento || 'No document'})`;
            option.setAttribute('data-nombre', aprendiz.nombre_completo);
            option.setAttribute('data-documento', aprendiz.documento || '');
            select.appendChild(option);
        });
    }
}

// Function to update the visual list of selected learners
function actualizarListaAprendicesSeleccionados() {
    const container = document.getElementById('listaAprendicesSeleccionados');
    
    if (aprendicesSeleccionados.length === 0) {
        container.innerHTML = '<p class="text-sm text-muted-foreground text-center py-4">No learners selected</p>';
        return;
    }
    
    container.innerHTML = aprendicesSeleccionados.map((aprendiz, index) => `
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
            <div>
                <p class="text-sm font-medium text-gray-900">${aprendiz.nombre_completo}</p>
                ${aprendiz.documento ? `<p class="text-xs text-gray-500">Document: ${aprendiz.documento}</p>` : ''}
            </div>
            <button 
                type="button" 
                onclick="removerAprendiz(${index})"
                class="text-red-600 hover:text-red-800 p-1"
                title="Remove"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
}

// Function to remove learner from the list
function removerAprendiz(index) {
    aprendicesSeleccionados.splice(index, 1);
    actualizarListaAprendicesSeleccionados();
    // Update the filter as well
    filtrarAprendices();
}

// Function to finish individual creation (modal version)
async function finalizarCreacionIndividual() {
    const select = document.getElementById('selectAprendizIndividual');
    const idAprendiz = select.value;
    
    if (!idAprendiz) {
        toastError('You must select a learner');
        return;
    }
    
    try {
        // Assign learner to activity
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                accion: 'asignar_aprendices',
                id_actividad: obraCreadaId,
                aprendices: [idAprendiz]
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            toastSuccess('Individual work created and learner assigned successfully');
            closeSeleccionarModal();
            closeCreateModal();
            await cargarObras();
        } else {
            toastError('Error assigning learner: ' + (result.error || 'Unknown'));
        }
    } catch (error) {
        console.error('Error assigning learner:', error);
        toastError('Error assigning learner');
    }
}

// Function to finish group creation
async function finalizarCreacionGrupal() {
    if (aprendicesSeleccionados.length === 0) {
        toastError('You must select at least one learner');
        return;
    }
    
    const btn = document.getElementById('btnFinalizarGrupal');
    const btnText = document.getElementById('btnFinalizarText');
    const btnLoading = document.getElementById('btnFinalizarLoading');
    
    // Show loading
    btn.disabled = true;
    btnText.classList.add('hidden');
    btnLoading.classList.remove('hidden');
    
    try {
        // Get only the IDs of the learners
        const idsAprendices = aprendicesSeleccionados.map(a => a.id_usuario);
        
        // Assign learners to activity
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                accion: 'asignar_aprendices',
                id_actividad: obraCreadaId,
                aprendices: idsAprendices
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            toastSuccess(`Group work created and ${aprendicesSeleccionados.length} learners assigned successfully`);
            closeAsignarModal();
            closeCreateModal();
            await cargarObras();
        } else {
            toastError('Error assigning learners: ' + (result.error || 'Unknown'));
        }
    } catch (error) {
        console.error('Error assigning learners:', error);
        toastError('Error assigning learners');
    } finally {
        // Restore button
        btn.disabled = false;
        btnText.classList.remove('hidden');
        btnLoading.classList.add('hidden');
    }
}

// Function to reset the creation process
function resetearCreacion() {
    obraCreadaId = null;
    obraCreadaData = null;
    tipoTrabajoActual = '';
    fichaSeleccionadaId = null;
    aprendicesSeleccionados = [];
    aprendicesFicha = [];
}

// Add to ESC key listener to close modals
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeCreateModal();
    closeEditModal();
    closeDetailsModal();
    closeSeleccionarModal();
    closeAsignarModal();
    
    // Clean confirmation data if it exists
    const confirmModal = document.querySelector('.fixed.inset-0.bg-black.bg-opacity-50');
    if (confirmModal && !confirmModal.id) {
      document.body.removeChild(confirmModal);
    }
  }
});