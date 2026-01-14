// ============================================================
//  MÓDULO SOLICITUDES – JS COMPLETO Y CORREGIDO
// ============================================================

const API = "src/controllers/solicitudes_controller.php";

// ============================================================
//  CONFIGURACIÓN
// ============================================================
const CONFIG = {
    COLUMNAS: {
        ID: "id_solicitud",
        FECHA: "fecha_solicitud",
        ESTADO: "estado",
        FICHA: "id_ficha",
        PROGRAMA: "id_programa",
        RAE: "id_rae",
        ACTIVIDAD: "id_actividad",
        SOLICITANTE: "id_usuario_solicitante",
        APROBADOR: "id_usuario_aprobador",
        OBSERVACIONES: "observaciones",
        FECHA_RESPUESTA: "fecha_respuesta"
    },
    
    LABELS: {
        pendiente: "Pendiente",
        aprobada: "Aprobada",
        rechazada: "Rechazada",
        entregada: "Entregada"
    },
    
    ICONS: {
        pendiente: "clock",
        aprobada: "check-circle",
        entregada: "package-check",
        rechazada: "x-circle"
    },
    
    PAGE_SIZE: 9
};

// ============================================================
//  ESTADO GLOBAL
// ============================================================
let estadoApp = {
    solicitudes: [],
    filtroActivo: "todas",
    paginaActual: 1,
    materialesSeleccionados: [],
    datosFormulario: {
        programa: "",
        rae: "",
        ficha: "",
        actividad: "",
        observaciones: ""
    }
};

// ============================================================
//  SELECTORES
// ============================================================
const selectores = {
    // Botones principales
    btnNueva: document.getElementById("sol-btn-nueva"),
    modal: document.getElementById("sol-modal"),
    btnCerrarModal: document.getElementById("sol-modal-cerrar"),
    btnCancelar: document.getElementById("sol-btn-cancelar"),
    
    // Pasos del modal
    paso1: document.getElementById("sol-paso-1"),
    paso2: document.getElementById("sol-paso-2"),
    btnPaso2: document.getElementById("sol-btn-ir-paso-2"),
    btnVolver: document.getElementById("sol-btn-volver"),
    btnGuardar: document.getElementById("sol-btn-guardar"),
    
    // Contenedores
    contenedorCards: document.getElementById("sol-cards"),
    paginacion: document.getElementById("sol-pagination"),
    filtros: document.querySelectorAll(".sol-filtro-btn"),
    
    // Formulario
    formNueva: document.getElementById("sol-form-nueva"),
    selectPrograma: document.getElementById("programa"),
    selectRae: document.getElementById("rae"),
    selectFichas: document.getElementById("ficha"),
    textareaObservaciones: document.getElementById("observaciones"),
    
    // Materiales
    selectMaterial: document.getElementById("material-select"),
    inputCantidad: document.getElementById("material-cantidad"),
    btnAgregarMaterial: document.getElementById("btn-agregar-material"),
    listaMateriales: document.getElementById("lista-materiales"),
    
    // Resumen
    resumenPendientes: document.getElementById("resumen-pendientes"),
    resumenAprobadas: document.getElementById("resumen-aprobadas"),
    resumenEntregadas: document.getElementById("resumen-entregadas"),
    resumenRechazadas: document.getElementById("resumen-rechazadas")
};

// ============================================================
//  FUNCIONES DE UTILIDAD
// ============================================================
const utilidades = {
    normalizarEstado(estadoBD) {
        if (!estadoBD) return "pendiente";
        const estado = String(estadoBD).toLowerCase().trim();
        return estado;
    },

    extraerDatosSolicitud(solicitudBD) {
        console.log("🔍 Procesando solicitud de BD:", solicitudBD);
        
        return {
            id: solicitudBD.id_solicitud || solicitudBD.id || "N/A",
            fecha: this.formatearFecha(solicitudBD.fecha_solicitud),
            ficha: solicitudBD.numero_ficha || solicitudBD.id_ficha || "N/A",
            estado: this.normalizarEstado(solicitudBD.estado),
            programa: solicitudBD.codigo_programa || solicitudBD.nombre_programa || solicitudBD.id_programa || "",
            rae: solicitudBD.codigo_rae || solicitudBD.descripcion_rae || solicitudBD.id_rae || "",
            actividad: solicitudBD.id_actividad || "",
            solicitante: solicitudBD.id_usuario_solicitante || "",
            aprobador: solicitudBD.id_usuario_aprobador || "",
            observaciones: solicitudBD.observaciones || "",
            fecha_respuesta: this.formatearFecha(solicitudBD.fecha_respuesta),
            jornada: solicitudBD.jornada || "",
            nombre_programa: solicitudBD.nombre_programa || "",
            descripcion_rae: solicitudBD.descripcion_rae || ""
        };
    },

    formatearFecha(fechaString) {
        if (!fechaString) return "";
        try {
            const fecha = new Date(fechaString);
            return fecha.toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        } catch (e) {
            return fechaString;
        }
    },

    mostrarError(mensaje) {
        console.error("❌ Error:", mensaje);
        alert(`Error: ${mensaje}`);
    },

    mostrarExito(mensaje) {
        console.log("✅ Éxito:", mensaje);
        alert(`Éxito: ${mensaje}`);
    }
};

// ============================================================
//  FUNCIONES DE API
// ============================================================
const api = {
    async listarSolicitudes() {
        try {
            console.log("🔍 Solicitando datos de la API:", `${API}?accion=listar`);
            
            const response = await fetch(`${API}?accion=listar`);
            
            console.log("📡 Respuesta HTTP:", {
                status: response.status,
                statusText: response.statusText,
                ok: response.ok
            });
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status} ${response.statusText}`);
            }
            
            const rawText = await response.text();
            console.log("📦 Texto crudo recibido:", rawText.substring(0, 500));
            
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (e) {
                console.error("❌ Error parseando JSON:", e);
                console.error("Texto recibido:", rawText);
                throw new Error('Respuesta del servidor no es JSON válido');
            }
            
            console.log("📦 Datos parseados:", data);
            
            if (!Array.isArray(data)) {
                console.error("❌ La respuesta no es un array:", data);
                return [];
            }
            
            const solicitudesProcesadas = data.map(s => 
                utilidades.extraerDatosSolicitud(s)
            );
            
            console.log(`✅ ${solicitudesProcesadas.length} solicitudes procesadas`);
            return solicitudesProcesadas;
            
        } catch (error) {
            console.error('❌ Error al cargar solicitudes:', error);
            utilidades.mostrarError(`No se pudieron cargar las solicitudes: ${error.message}`);
            return [];
        }
    },

    async crearSolicitud(datos) {
        try {
            console.log("📤 Enviando solicitud:", datos);
            
            const datosParaEnviar = {
                id_usuario: 1,
                id_ficha: datos.ficha,
                id_programa: datos.programa,
                id_rae: datos.rae,
                observaciones: datos.observaciones || '',
                materiales: datos.materiales
            };

            console.log("📦 Datos completos a enviar:", datosParaEnviar);

            const response = await fetch(`${API}?accion=crear`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(datosParaEnviar)
            });

            // Verificar respuesta cruda
            const rawText = await response.text();
            console.log("📥 Respuesta cruda del servidor:", rawText.substring(0, 500));
            
            let result;
            try {
                result = JSON.parse(rawText);
            } catch (parseError) {
                console.error("❌ Error parseando JSON:", parseError);
                throw new Error(`El servidor devolvió un formato inválido: ${rawText.substring(0, 100)}`);
            }

            console.log("📦 Resultado parseado:", result);
            
            if (result.success) {
                utilidades.mostrarExito('Solicitud creada correctamente');
                return result;
            } else {
                throw new Error(result.error || result.message || 'Error al crear la solicitud');
            }
        } catch (error) {
            console.error('❌ Error al crear solicitud:', error);
            utilidades.mostrarError(error.message);
            throw error;
        }
    },

    // 🎯 FUNCIÓN PARA CAMBIAR ESTADO - CORREGIDA
    async cambiarEstadoSolicitud(idSolicitud, nuevoEstado, motivo = '') {
        try {
            console.log(`📤 Enviando respuesta de solicitud:`, {
                id_solicitud: idSolicitud,
                estado: nuevoEstado,
                observaciones: motivo
            });

            const response = await fetch(`${API}?accion=responder`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id_solicitud: parseInt(idSolicitud),
                    estado: nuevoEstado,
                    id_usuario_aprobador: 1, // Cambia esto según tu sistema
                    observaciones: motivo
                })
            });

            const rawText = await response.text();
            console.log("📥 Respuesta cruda:", rawText.substring(0, 500));
            
            let result;
            try {
                result = JSON.parse(rawText);
            } catch (parseError) {
                console.error("❌ Error parseando JSON:", parseError);
                throw new Error(`El servidor devolvió un formato inválido: ${rawText.substring(0, 100)}`);
            }

            console.log('📦 Resultado del servidor:', result);
            
            return result;
            
        } catch (error) {
            console.error('❌ Error en la API:', error);
            throw error;
        }
    },

    async cargarSelectores() {
        try {
            console.log("📚 Cargando datos para selectores...");
            
            // 1. CARGAR PROGRAMAS
            console.log("🔗 Solicitando programas...");
            const responseProgramas = await fetch(`${API}?accion=programas`);
            console.log("📡 Estado programas:", responseProgramas.status, responseProgramas.ok);
            
            if (responseProgramas.ok) {
                const programas = await responseProgramas.json();
                console.log("📋 Programas cargados:", programas);
                
                selectores.selectPrograma.innerHTML = '<option value="">Seleccionar programa</option>';
                if (Array.isArray(programas) && programas.length > 0) {
                    programas.forEach(programa => {
                        const option = document.createElement('option');
                        option.value = programa.id_programa;
                        option.textContent = `${programa.codigo_programa} - ${programa.nombre_programa}`;
                        selectores.selectPrograma.appendChild(option);
                    });
                    console.log(`✅ ${programas.length} programas cargados`);
                } else {
                    console.warn("⚠️ No se encontraron programas o no es un array");
                }
            } else {
                console.error("❌ Error cargando programas:", responseProgramas.status);
            }

            // 2. LISTENER PARA CAMBIO DE PROGRAMA
            selectores.selectPrograma.addEventListener('change', async function() {
                const programaId = this.value;
                console.log("🔄 Programa seleccionado:", programaId);
                
                if (!programaId) {
                    selectores.selectRae.innerHTML = '<option value="">Seleccionar RAE</option>';
                    selectores.selectFichas.innerHTML = '<option value="">Seleccionar ficha</option>';
                    return;
                }
                
                try {
                    const raesUrl = `${API}?accion=raes&programa=${programaId}`;
                    const fichasUrl = `${API}?accion=fichas&programa=${programaId}`;
                    
                    console.log("🔗 URLs:", { raesUrl, fichasUrl });
                    
                    const [responseRaes, responseFichas] = await Promise.all([
                        fetch(raesUrl),
                        fetch(fichasUrl)
                    ]);

                    console.log("📡 Respuestas:", {
                        raes: { status: responseRaes.status, ok: responseRaes.ok },
                        fichas: { status: responseFichas.status, ok: responseFichas.ok }
                    });

                    // Cargar RAEs
                    if (responseRaes.ok) {
                        const raes = await responseRaes.json();
                        console.log("🎯 RAEs cargados:", raes);
                        selectores.selectRae.innerHTML = '<option value="">Seleccionar RAE</option>';
                        if (Array.isArray(raes) && raes.length > 0) {
                            raes.forEach(rae => {
                                const option = document.createElement('option');
                                option.value = rae.id_rae;
                                option.textContent = `${rae.codigo_rae} - ${rae.descripcion_rae}`;
                                selectores.selectRae.appendChild(option);
                            });
                            console.log(`✅ ${raes.length} RAEs cargados`);
                        } else {
                            console.warn("⚠️ No se encontraron RAEs para este programa");
                            selectores.selectRae.innerHTML = '<option value="">No hay RAEs disponibles</option>';
                        }
                    }

                    // Cargar Fichas
                    if (responseFichas.ok) {
                        const fichas = await responseFichas.json();
                        console.log("📝 Fichas cargadas:", fichas);
                        selectores.selectFichas.innerHTML = '<option value="">Seleccionar ficha</option>';
                        if (Array.isArray(fichas) && fichas.length > 0) {
                            fichas.forEach(ficha => {
                                const option = document.createElement('option');
                                option.value = ficha.id_ficha;
                                option.textContent = `${ficha.numero_ficha} - ${ficha.jornada}`;
                                selectores.selectFichas.appendChild(option);
                            });
                            console.log(`✅ ${fichas.length} fichas cargadas`);
                        } else {
                            console.warn("⚠️ No se encontraron fichas para este programa");
                            selectores.selectFichas.innerHTML = '<option value="">No hay fichas disponibles</option>';
                        }
                    }
                    
                } catch (error) {
                    console.error('❌ Error al cargar RAEs/Fichas:', error);
                }
            });

            // 3. CARGAR MATERIALES
            console.log("📦 Solicitando materiales...");
            const responseMateriales = await fetch(`${API}?accion=materiales`);
            console.log("📡 Estado materiales:", responseMateriales.status, responseMateriales.ok);
            
            if (responseMateriales.ok) {
                const materiales = await responseMateriales.json();
                console.log("📦 Respuesta materiales:", materiales);
                
                selectores.selectMaterial.innerHTML = '<option value="">Seleccionar material</option>';
                
                if (Array.isArray(materiales) && materiales.length > 0) {
                    console.log(`✅ ${materiales.length} materiales encontrados`);
                    
                    materiales.forEach((material, index) => {
                        console.log(`Material ${index + 1}:`, material);
                        
                        const option = document.createElement('option');
                        option.value = material.id_material;
                        option.textContent = `${material.nombre || 'Sin nombre'} (${material.codigo_inventario || 'Sin código'})`;
                        option.dataset.stock = material.stock_actual || 0;
                        option.dataset.unidad = material.unidad_medida || 'UND';
                        option.dataset.nombre = material.nombre || '';
                        selectores.selectMaterial.appendChild(option);
                    });
                } else {
                    console.warn("⚠️ No se encontraron materiales o no es un array");
                    selectores.selectMaterial.innerHTML = '<option value="">No hay materiales disponibles</option>';
                }
            } else {
                console.error("❌ Error cargando materiales:", responseMateriales.status);
                selectores.selectMaterial.innerHTML = '<option value="">Error cargando materiales</option>';
            }

            console.log("✅ Selectores cargados exitosamente");

        } catch (error) {
            console.error('❌ Error cargando selectores:', error);
            utilidades.mostrarError('Error al cargar los datos. Consulte la consola.');
        }
    }
};

// ============================================================
//  FUNCIONES DE RENDERIZADO
// ============================================================
const render = {
    actualizarResumen() {
        const contadores = {
            pendiente: 0,
            aprobada: 0,
            entregada: 0,
            rechazada: 0
        };

        estadoApp.solicitudes.forEach(s => {
            const estado = s.estado || 'pendiente';
            if (contadores.hasOwnProperty(estado)) {
                contadores[estado]++;
            }
        });

        console.log("📊 Contadores:", contadores);

        if (selectores.resumenPendientes) selectores.resumenPendientes.textContent = contadores.pendiente;
        if (selectores.resumenAprobadas) selectores.resumenAprobadas.textContent = contadores.aprobada;
        if (selectores.resumenEntregadas) selectores.resumenEntregadas.textContent = contadores.entregada;
        if (selectores.resumenRechazadas) selectores.resumenRechazadas.textContent = contadores.rechazada;
    },

    actualizarFiltros() {
        const contadores = {
            pendiente: 0,
            aprobada: 0,
            entregada: 0,
            rechazada: 0
        };

        estadoApp.solicitudes.forEach(s => {
            const estado = s.estado || 'pendiente';
            if (contadores.hasOwnProperty(estado)) {
                contadores[estado]++;
            }
        });

        const totalSolicitudes = estadoApp.solicitudes.length;
        
        selectores.filtros.forEach(btn => {
            const filtro = btn.dataset.filtro;
            if (filtro === 'todas') {
                btn.textContent = `Todas (${totalSolicitudes})`;
            } else {
                const count = contadores[filtro] || 0;
                btn.textContent = `${CONFIG.LABELS[filtro]}s (${count})`;
            }
        });
    },

    renderizarSolicitudes() {
        console.log("🎨 Renderizando solicitudes:", {
            total: estadoApp.solicitudes.length,
            filtro: estadoApp.filtroActivo,
            pagina: estadoApp.paginaActual
        });

        if (estadoApp.solicitudes.length === 0) {
            selectores.contenedorCards.innerHTML = `
                <div class="col-span-full py-12 text-center">
                    <i data-lucide="file-text" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-700 mb-2">No hay solicitudes registradas</h3>
                    <p class="text-gray-500">Cree una nueva solicitud para comenzar</p>
                </div>
            `;
            lucide.createIcons();
            return;
        }

        let solicitudesFiltradas;
        
        if (estadoApp.filtroActivo === 'todas') {
            solicitudesFiltradas = estadoApp.solicitudes;
        } else {
            solicitudesFiltradas = estadoApp.solicitudes.filter(
                s => (s.estado || 'pendiente') === estadoApp.filtroActivo
            );
        }

        console.log("🔍 Solicitudes filtradas:", solicitudesFiltradas.length);

        if (solicitudesFiltradas.length === 0) {
            selectores.contenedorCards.innerHTML = `
                <div class="col-span-full py-12 text-center">
                    <i data-lucide="filter" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-700 mb-2">No hay solicitudes ${CONFIG.LABELS[estadoApp.filtroActivo]}s</h3>
                    <p class="text-gray-500">Intente con otro filtro</p>
                </div>
            `;
            lucide.createIcons();
            return;
        }

        const inicio = (estadoApp.paginaActual - 1) * CONFIG.PAGE_SIZE;
        const fin = inicio + CONFIG.PAGE_SIZE;
        const paginaSolicitudes = solicitudesFiltradas.slice(inicio, fin);

        selectores.contenedorCards.innerHTML = '';

        paginaSolicitudes.forEach(solicitud => {
            const estado = solicitud.estado || 'pendiente';
            const icono = CONFIG.ICONS[estado] || 'clock';
            const label = CONFIG.LABELS[estado] || estado.charAt(0).toUpperCase() + estado.slice(1);
            
            // 🔥 Determinar si mostrar botones de acción (solo para pendientes)
            const mostrarAcciones = estado === 'pendiente';
            
            const card = document.createElement('div');
            card.className = 'sol-card';
            card.dataset.id = solicitud.id;

            card.innerHTML = `
                <div class="sol-card-header">
                    <div class="sol-card-title-wrap">
                        <div class="sol-card-icon ${estado}">
                            <i data-lucide="${icono}"></i>
                        </div>
                        <div>
                            <div class="sol-card-title">Solicitud #${solicitud.id}</div>
                            <div class="sol-card-date">${solicitud.fecha || 'Sin fecha'}</div>
                        </div>
                    </div>

                    <span class="sol-badge ${estado}">
                        ${label}
                    </span>
                </div>

                <div class="sol-card-body">
                    <div class="sol-card-row">
                        <i data-lucide="hash" class="sol-icon-muted"></i>
                        <span>Ficha: ${solicitud.ficha}</span>
                    </div>
                    
                    ${solicitud.programa ? `
                    <div class="sol-card-row">
                        <i data-lucide="book-open" class="sol-icon-muted"></i>
                        <span>Programa: ${solicitud.programa}</span>
                    </div>
                    ` : ''}
                    
                    ${solicitud.rae ? `
                    <div class="sol-card-row">
                        <i data-lucide="target" class="sol-icon-muted"></i>
                        <span>RAE: ${solicitud.rae}</span>
                    </div>
                    ` : ''}
                    
                    ${solicitud.observaciones ? `
                    <div class="sol-card-row">
                        <i data-lucide="message-square" class="sol-icon-muted"></i>
                        <span class="truncate" title="${solicitud.observaciones}">
                            ${solicitud.observaciones.substring(0, 60)}${solicitud.observaciones.length > 60 ? '...' : ''}
                        </span>
                    </div>
                    ` : ''}
                    
                    ${solicitud.fecha_respuesta ? `
                    <div class="sol-card-row">
                        <i data-lucide="calendar-check" class="sol-icon-muted"></i>
                        <span>Respuesta: ${solicitud.fecha_respuesta}</span>
                    </div>
                    ` : ''}
                </div>

                <!-- 🎯 BOTONES DE ACEPTAR/RECHAZAR (SOLO PARA PENDIENTES) -->
                ${mostrarAcciones ? `
                <div class="sol-card-footer mt-4 pt-4 border-t border-gray-200">
                    <div class="flex gap-2">
                        <button class="sol-btn-aceptar flex-1 py-2 px-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center gap-2"
                                data-id="${solicitud.id}">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                            Aceptar
                        </button>
                        <button class="sol-btn-rechazar flex-1 py-2 px-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2"
                                data-id="${solicitud.id}">
                            <i data-lucide="x-circle" class="w-4 h-4"></i>
                            Rechazar
                        </button>
                    </div>
                </div>
                ` : ''}
            `;

            selectores.contenedorCards.appendChild(card);
        });

        lucide.createIcons();

        // 🎯 AGREGAR EVENTOS A LOS BOTONES DESPUÉS DE RENDERIZAR
        setTimeout(() => {
            agregarEventosBotonesAccion();
        }, 100);

        this.renderizarPaginacion(solicitudesFiltradas.length);
    },

    renderizarPaginacion(totalItems) {
        const totalPaginas = Math.ceil(totalItems / CONFIG.PAGE_SIZE);
        
        if (totalPaginas <= 1) {
            selectores.paginacion.innerHTML = '';
            return;
        }

        let paginacionHTML = '';

        paginacionHTML += `
            <button class="sol-paginacion-btn ${estadoApp.paginaActual === 1 ? 'disabled' : ''}" 
                    ${estadoApp.paginaActual === 1 ? 'disabled' : ''}
                    onclick="paginacion.cambiarPagina(${estadoApp.paginaActual - 1})">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </button>
        `;

        for (let i = 1; i <= totalPaginas; i++) {
            if (i === 1 || i === totalPaginas || 
                (i >= estadoApp.paginaActual - 1 && i <= estadoApp.paginaActual + 1)) {
                paginacionHTML += `
                    <button class="sol-paginacion-btn ${estadoApp.paginaActual === i ? 'active' : ''}" 
                            onclick="paginacion.cambiarPagina(${i})">
                        ${i}
                    </button>
                `;
            } else if (i === estadoApp.paginaActual - 2 || i === estadoApp.paginaActual + 2) {
                paginacionHTML += '<span class="px-2 text-gray-400">...</span>';
            }
        }

        paginacionHTML += `
            <button class="sol-paginacion-btn ${estadoApp.paginaActual === totalPaginas ? 'disabled' : ''}" 
                    ${estadoApp.paginaActual === totalPaginas ? 'disabled' : ''}
                    onclick="paginacion.cambiarPagina(${estadoApp.paginaActual + 1})">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
        `;

        selectores.paginacion.innerHTML = paginacionHTML;
        lucide.createIcons();
    },

    renderizarMateriales() {
        if (estadoApp.materialesSeleccionados.length === 0) {
            selectores.listaMateriales.innerHTML = `
                <div class="text-center text-muted-foreground py-8">
                    <i data-lucide="package" class="w-8 h-8 mx-auto mb-2"></i>
                    <p>No hay materiales agregados</p>
                </div>
            `;
            return;
        }

        let html = `
            <div class="space-y-2">
                <div class="flex justify-between text-sm font-medium text-gray-500 pb-2 border-b">
                    <span class="flex-1">Material</span>
                    <span class="w-24 text-center">Cantidad</span>
                    <span class="w-16 text-center">Acciones</span>
                </div>
        `;

        estadoApp.materialesSeleccionados.forEach((material, index) => {
            html += `
                <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                    <div class="flex-1">
                        <div class="font-medium">${material.nombre}</div>
                        <div class="text-sm text-gray-500">${material.unidad} • Stock: ${material.stock}</div>
                    </div>
                    <div class="w-24 text-center">
                        <span class="font-semibold">${material.cantidad}</span>
                    </div>
                    <div class="w-16 text-center">
                        <button type="button" onclick="materiales.eliminarMaterial(${index})" 
                                class="p-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        const totalMateriales = estadoApp.materialesSeleccionados.reduce((sum, m) => sum + m.cantidad, 0);
        html += `
            <div class="pt-2 border-t">
                <div class="flex justify-between font-medium text-gray-700">
                    <span>Total materiales:</span>
                    <span>${totalMateriales} unidades</span>
                </div>
            </div>
        </div>`;

        selectores.listaMateriales.innerHTML = html;
        lucide.createIcons();
    }
};

// ============================================================
//  GESTIÓN DE MATERIALES
// ============================================================
const materiales = {
    agregarMaterial() {
        const materialId = selectores.selectMaterial.value;
        const cantidad = parseInt(selectores.inputCantidad.value);
        
        if (!materialId) {
            utilidades.mostrarError('Seleccione un material');
            return;
        }
        
        if (!cantidad || cantidad < 1) {
            utilidades.mostrarError('Ingrese una cantidad válida (mínimo 1)');
            selectores.inputCantidad.focus();
            return;
        }

        const option = selectores.selectMaterial.selectedOptions[0];
        const stock = parseInt(option.dataset.stock) || 0;
        const unidad = option.dataset.unidad || 'UND';
        
        if (cantidad > stock) {
            utilidades.mostrarError(`Stock insuficiente. Disponible: ${stock} ${unidad}`);
            selectores.inputCantidad.value = stock;
            selectores.inputCantidad.focus();
            return;
        }

        const existe = estadoApp.materialesSeleccionados.find(m => m.id == materialId);
        if (existe) {
            if (confirm('Este material ya fue agregado. ¿Desea actualizar la cantidad?')) {
                existe.cantidad = cantidad;
                render.renderizarMateriales();
            }
            return;
        }

        estadoApp.materialesSeleccionados.push({
            id: materialId,
            nombre: option.textContent,
            cantidad: cantidad,
            stock: stock,
            unidad: unidad
        });

        selectores.selectMaterial.value = '';
        selectores.inputCantidad.value = '1';
        
        render.renderizarMateriales();
        utilidades.mostrarExito('Material agregado correctamente');
    },

    eliminarMaterial(index) {
        if (confirm('¿Está seguro de eliminar este material?')) {
            estadoApp.materialesSeleccionados.splice(index, 1);
            render.renderizarMateriales();
            utilidades.mostrarExito('Material eliminado');
        }
    },

    limpiarMateriales() {
        if (estadoApp.materialesSeleccionados.length > 0) {
            if (!confirm('¿Está seguro de limpiar todos los materiales?')) {
                return;
            }
        }
        estadoApp.materialesSeleccionados = [];
        render.renderizarMateriales();
    }
};

// ============================================================
//  GESTIÓN DE PAGINACIÓN
// ============================================================
const paginacion = {
    cambiarPagina(nuevaPagina) {
        const totalSolicitudes = estadoApp.filtroActivo === 'todas' 
            ? estadoApp.solicitudes.length 
            : estadoApp.solicitudes.filter(s => (s.estado || 'pendiente') === estadoApp.filtroActivo).length;
        
        const totalPaginas = Math.ceil(totalSolicitudes / CONFIG.PAGE_SIZE);
        
        if (nuevaPagina < 1 || nuevaPagina > totalPaginas) return;
        
        estadoApp.paginaActual = nuevaPagina;
        render.renderizarSolicitudes();
        
        window.scrollTo({
            top: selectores.contenedorCards.offsetTop - 100,
            behavior: 'smooth'
        });
    }
};

// ============================================================
//  🎯 FUNCIONES PARA ACEPTAR/RECHAZAR SOLICITUDES - CORREGIDAS
// ============================================================

// Función para agregar eventos a los botones de acción
function agregarEventosBotonesAccion() {
    // Botones Aceptar
    document.querySelectorAll('.sol-btn-aceptar').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.stopPropagation();
            e.preventDefault();
            const idSolicitud = this.dataset.id;
            console.log(`✅ Intentando aceptar solicitud ${idSolicitud}`);
            
            if (confirm('¿Está seguro de ACEPTAR esta solicitud?\n\nSe cambiará el estado a "aprobada"')) {
                await cambiarEstadoSolicitud(idSolicitud, 'aprobada');
            }
        });
    });

    // Botones Rechazar
    document.querySelectorAll('.sol-btn-rechazar').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.stopPropagation();
            e.preventDefault();
            const idSolicitud = this.dataset.id;
            console.log(`❌ Intentando rechazar solicitud ${idSolicitud}`);
            
            const motivo = prompt('Ingrese el motivo del rechazo (requerido):');
            
            if (motivo !== null) {
                if (motivo.trim() === '') {
                    alert('Debe ingresar un motivo para rechazar la solicitud.');
                    return;
                }
                
                if (confirm('¿Está seguro de RECHAZAR esta solicitud?\n\nSe cambiará el estado a "rechazada"')) {
                    await cambiarEstadoSolicitud(idSolicitud, 'rechazada', motivo.trim());
                }
            }
        });
    });
}

// Función para cambiar el estado de la solicitud - CORREGIDA
async function cambiarEstadoSolicitud(idSolicitud, nuevoEstado, motivo = '') {
    try {
        console.log(`🔄 Cambiando estado: ID=${idSolicitud}, Estado=${nuevoEstado}, Motivo=${motivo}`);
        
        // Verificar que el estado sea válido
        if (!['aprobada', 'rechazada'].includes(nuevoEstado)) {
            throw new Error(`Estado "${nuevoEstado}" no es válido. Debe ser "aprobada" o "rechazada"`);
        }
        
        // Mostrar loading en los botones de ESTA solicitud
        const card = document.querySelector(`.sol-card[data-id="${idSolicitud}"]`);
        if (!card) {
            console.error(`❌ No se encontró la card con ID ${idSolicitud}`);
            utilidades.mostrarError('No se encontró la solicitud en la interfaz');
            return;
        }
        
        const btnAceptar = card.querySelector('.sol-btn-aceptar');
        const btnRechazar = card.querySelector('.sol-btn-rechazar');
        
        if (btnAceptar) {
            btnAceptar.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i>';
            btnAceptar.disabled = true;
        }
        if (btnRechazar) {
            btnRechazar.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i>';
            btnRechazar.disabled = true;
        }
        
        // Llamar a la API para cambiar el estado
        console.log('📤 Llamando a API responder...');
        const result = await api.cambiarEstadoSolicitud(idSolicitud, nuevoEstado, motivo);
        
        console.log('📦 Resultado de la API:', result);
        
        if (result && result.success) {
            // Mostrar mensaje de éxito
            const mensaje = nuevoEstado === 'aprobada' ? 
                '✅ Solicitud aceptada correctamente' : 
                '❌ Solicitud rechazada correctamente';
            
            utilidades.mostrarExito(mensaje);
            
            // Recargar las solicitudes para actualizar la vista
            await app.cargarSolicitudes();
            
        } else {
            const errorMsg = result?.error || result?.message || 'Error desconocido al cambiar estado';
            throw new Error(errorMsg);
        }
        
    } catch (error) {
        console.error('❌ Error al cambiar estado:', error);
        utilidades.mostrarError(`Error: ${error.message}`);
        
        // Restaurar botones en caso de error
        const card = document.querySelector(`.sol-card[data-id="${idSolicitud}"]`);
        if (card) {
            const btnAceptar = card.querySelector('.sol-btn-aceptar');
            const btnRechazar = card.querySelector('.sol-btn-rechazar');
            
            if (btnAceptar) {
                btnAceptar.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i> Aceptar';
                btnAceptar.disabled = false;
            }
            if (btnRechazar) {
                btnRechazar.innerHTML = '<i data-lucide="x-circle" class="w-4 h-4"></i> Rechazar';
                btnRechazar.disabled = false;
            }
            
            lucide.createIcons();
        }
    }
}

// ============================================================
//  GESTIÓN DEL MODAL
// ============================================================
const modal = {
    abrir() {
        selectores.modal.classList.add('sol-modal-show');
        selectores.paso1.classList.remove('hidden');
        selectores.paso2.classList.add('hidden');
        this.limpiarFormulario();
        
        // 🔥 Asegurar que el botón Crear Solicitud esté oculto en paso 1
        if (selectores.btnGuardar) {
            selectores.btnGuardar.style.display = 'none';
        }
        
        setTimeout(() => {
            selectores.selectPrograma.focus();
        }, 100);
    },

    cerrar() {
        if (estadoApp.materialesSeleccionados.length > 0 || 
            selectores.textareaObservaciones.value.trim() !== '' ||
            selectores.selectPrograma.value !== '') {
            
            if (!confirm('¿Está seguro de cerrar? Se perderán los datos no guardados.')) {
                return;
            }
        }
        
        selectores.modal.classList.remove('sol-modal-show');
        this.limpiarFormulario();
    },

    limpiarFormulario() {
        estadoApp.datosFormulario = {
            programa: "",
            rae: "",
            ficha: "",
            actividad: "",
            observaciones: ""
        };
        estadoApp.materialesSeleccionados = [];
        
        selectores.formNueva.reset();
        materiales.limpiarMateriales();
    },

    validarPaso1() {
        if (!selectores.selectPrograma.value) {
            utilidades.mostrarError('Seleccione un programa');
            selectores.selectPrograma.focus();
            return false;
        }
        
        if (!selectores.selectRae.value) {
            utilidades.mostrarError('Seleccione un RAE');
            selectores.selectRae.focus();
            return false;
        }
        
        if (!selectores.selectFichas.value) {
            utilidades.mostrarError('Seleccione una ficha');
            selectores.selectFichas.focus();
            return false;
        }

        estadoApp.datosFormulario = {
            programa: selectores.selectPrograma.value,
            rae: selectores.selectRae.value,
            ficha: selectores.selectFichas.value,
            observaciones: selectores.textareaObservaciones.value.trim()
        };

        return true;
    },

    irPaso2() {
        if (this.validarPaso1()) {
            selectores.paso1.classList.add('hidden');
            selectores.paso2.classList.remove('hidden');
            
            // 🔥 Mostrar el botón Crear Solicitud en paso 2
            if (selectores.btnGuardar) {
                selectores.btnGuardar.style.display = 'inline-flex';
            }
            
            selectores.selectMaterial.focus();
            return true;
        }
        return false;
    },

    volverPaso1() {
        selectores.paso2.classList.add('hidden');
        selectores.paso1.classList.remove('hidden');
        
        // 🔥 Ocultar el botón Crear Solicitud en paso 1
        if (selectores.btnGuardar) {
            selectores.btnGuardar.style.display = 'none';
        }
        
        selectores.selectPrograma.focus();
    },

    async enviarSolicitud() {
        if (estadoApp.materialesSeleccionados.length === 0) {
            utilidades.mostrarError('Debe agregar al menos un material');
            selectores.selectMaterial.focus();
            return;
        }

        if (!confirm('¿Está seguro de crear esta solicitud?')) {
            return;
        }

        try {
            selectores.btnGuardar.disabled = true;
            selectores.btnGuardar.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Procesando...';
            lucide.createIcons();

            const datosCompletos = {
                ...estadoApp.datosFormulario,
                materiales: estadoApp.materialesSeleccionados.map(m => ({
                    id_material: m.id,
                    cantidad_solicitada: m.cantidad
                }))
            };

            await api.crearSolicitud(datosCompletos);
            await app.cargarSolicitudes();
            this.cerrar();
            
        } catch (error) {
            console.error('Error al enviar solicitud:', error);
        } finally {
            selectores.btnGuardar.disabled = false;
            selectores.btnGuardar.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i> Crear Solicitud';
            lucide.createIcons();
        }
    }
};

// ============================================================
//  EVENT LISTENERS
// ============================================================
const eventos = {
    inicializar() {
        console.log("🎯 Inicializando eventos...");
        
        // Botón Nueva Solicitud
        if (selectores.btnNueva) {
            selectores.btnNueva.addEventListener('click', () => modal.abrir());
            console.log("✅ Botón Nueva inicializado");
        }
        
        // Botón Cerrar Modal (X)
        if (selectores.btnCerrarModal) {
            selectores.btnCerrarModal.addEventListener('click', () => modal.cerrar());
            console.log("✅ Botón Cerrar modal inicializado");
        }
        
        // Botón Cancelar (Paso 1)
        if (selectores.btnCancelar) {
            selectores.btnCancelar.addEventListener('click', () => modal.cerrar());
            console.log("✅ Botón Cancelar inicializado");
        }
        
        // Botón Siguiente (Paso 1 → Paso 2)
        if (selectores.btnPaso2) {
            selectores.btnPaso2.addEventListener('click', () => modal.irPaso2());
            console.log("✅ Botón Siguiente inicializado");
        }
        
        // Botón Volver (Paso 2 → Paso 1)
        if (selectores.btnVolver) {
            selectores.btnVolver.addEventListener('click', () => modal.volverPaso1());
            console.log("✅ Botón Volver inicializado");
        }
        
        // Botón Crear Solicitud (Paso 2)
        if (selectores.btnGuardar) {
            selectores.btnGuardar.addEventListener('click', async (e) => {
                e.preventDefault();
                await modal.enviarSolicitud();
            });
            console.log("✅ Botón Crear Solicitud inicializado");
        }
        
        // Botón Agregar Material
        if (selectores.btnAgregarMaterial) {
            selectores.btnAgregarMaterial.addEventListener('click', () => materiales.agregarMaterial());
            console.log("✅ Botón Agregar Material inicializado");
        }
        
        // Enter en cantidad de material
        if (selectores.inputCantidad) {
            selectores.inputCantidad.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    materiales.agregarMaterial();
                }
            });
        }
        
        // Prevenir submit del formulario
        if (selectores.formNueva) {
            selectores.formNueva.addEventListener('submit', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                await modal.enviarSolicitud();
                return false;
            });
        }
        
        // Filtros
        if (selectores.filtros.length > 0) {
            selectores.filtros.forEach(btn => {
                btn.addEventListener('click', () => {
                    selectores.filtros.forEach(b => b.classList.remove('sol-filtro-btn-activo'));
                    btn.classList.add('sol-filtro-btn-activo');
                    
                    estadoApp.filtroActivo = btn.dataset.filtro;
                    estadoApp.paginaActual = 1;
                    render.renderizarSolicitudes();
                });
            });
            console.log(`✅ ${selectores.filtros.length} filtros inicializados`);
        }

        // Cerrar modal con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && selectores.modal && selectores.modal.classList.contains('sol-modal-show')) {
                modal.cerrar();
            }
        });

        // Cerrar modal haciendo click fuera
        if (selectores.modal) {
            selectores.modal.addEventListener('click', (e) => {
                if (e.target === selectores.modal) {
                    modal.cerrar();
                }
            });
        }
        
        console.log("✅ Todos los eventos inicializados correctamente");
    }
};

// ============================================================
//  APLICACIÓN PRINCIPAL
// ============================================================
const app = {
    async inicializar() {
        console.log("🚀 Inicializando módulo de solicitudes...");
        
        // Mostrar loader
        selectores.contenedorCards.innerHTML = `
            <div class="col-span-full py-12 text-center">
                <i data-lucide="loader" class="w-12 h-12 text-blue-300 animate-spin mx-auto mb-4"></i>
                <h3 class="text-lg font-medium text-gray-700 mb-2">Cargando solicitudes</h3>
                <p class="text-gray-500">Obteniendo datos de la base de datos...</p>
            </div>
        `;
        lucide.createIcons();
        
        // Cargar datos
        await this.cargarSolicitudes();
        await api.cargarSelectores();
        
        // Inicializar eventos
        eventos.inicializar();
        
        console.log("✅ Módulo inicializado correctamente");
    },

    async cargarSolicitudes() {
        estadoApp.solicitudes = await api.listarSolicitudes();
        console.log(`✅ Cargadas ${estadoApp.solicitudes.length} solicitudes`);
        
        render.actualizarResumen();
        render.actualizarFiltros();
        render.renderizarSolicitudes();
    }
};

// ============================================================
//  INICIALIZACIÓN
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    console.log("📄 DOM cargado, inicializando app...");
    
    // Verificar que lucide esté disponible
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
        console.log("✅ Lucide icons inicializados");
    } else {
        console.error("❌ Lucide no está disponible");
    }
    
    // Inicializar la aplicación
    app.inicializar();
});

// Exportar funciones para acceso global
window.paginacion = paginacion;
window.materiales = materiales;
window.app = app;
window.agregarEventosBotonesAccion = agregarEventosBotonesAccion;
window.cambiarEstadoSolicitud = cambiarEstadoSolicitud;