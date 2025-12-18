<?php
$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados de Aprendizaje (RAE) - SENA</title>
    
    <!-- Configuración de Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        // Colores del tema SENA desde global.css
                        background: 'var(--background)',
                        foreground: 'var(--foreground)',
                        card: 'var(--card)',
                        'card-foreground': 'var(--card-foreground)',
                        popover: 'var(--popover)',
                        'popover-foreground': 'var(--popover-foreground)',
                        primary: 'var(--primary)',
                        'primary-foreground': 'var(--primary-foreground)',
                        secondary: 'var(--secondary)',
                        'secondary-foreground': 'var(--secondary-foreground)',
                        muted: 'var(--muted)',
                        'muted-foreground': 'var(--muted-foreground)',
                        accent: 'var(--accent)',
                        'accent-foreground': 'var(--accent-foreground)',
                        destructive: 'var(--destructive)',
                        'destructive-foreground': 'var(--destructive-foreground)',
                        border: 'var(--border)',
                        input: 'var(--input)',
                        ring: 'var(--ring)',
                        success: 'var(--success)',
                        warning: 'var(--warning)',
                        info: 'var(--info)',
                    }
                }
            }
        }
    </script>
    
    <!-- Solo importamos global.css del SENA -->
    <link rel="stylesheet" href="<?= BASE_URL ?>src/assets/css/globals.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Reduciendo significativamente los márgenes laterales: 80px cuando está colapsado y 270px cuando está expandido -->
    <main class="p-6 transition-all duration-300"
      style="margin-left: <?= $collapsed ? '70px' : '260px' ?>;">
        
        <!-- Título de la página -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold">Resultados de Aprendizaje (RAE)</h1>
                <p class="text-muted-foreground mt-1">Administra los RAEs asociados a los programas de formación</p>
            </div>

            <!-- Botones de acción (frente al título) -->
            <div class="flex items-center gap-3">
                <!-- Botones de vista (tabla/grid) -->
                <div class="inline-flex rounded-lg border border-border bg-card shadow-sm overflow-hidden">
                    <!-- Vista Tabla -->
                    <button
                        type="button"
                        id="viewTableBtn"
                        onclick="toggleView('table')"
                        class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 bg-muted text-foreground"
                        title="Vista tabla"
                    >
                        <!-- Icono lista -->
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Vista Tarjetas -->
                    <button
                        type="button"
                        id="viewGridBtn"
                        onclick="toggleView('grid')"
                        class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 text-muted-foreground"
                        title="Vista tarjetas"
                    >
                        <!-- Icono grid -->
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <rect x="4" y="4" width="7" height="7" rx="1"></rect>
                            <rect x="13" y="4" width="7" height="7" rx="1"></rect>
                            <rect x="4" y="13" width="7" height="7" rx="1"></rect>
                            <rect x="13" y="13" width="7" height="7" rx="1"></rect>
                        </svg>
                    </button>
                </div>
                
                <!-- Botón nuevo RAE -->
                <button onclick="openCreateModal()" class="inline-flex items-center justify-center rounded-sm bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2">
                    <i class="fas fa-plus"></i>
                    Nuevo RAE
                </button>
            </div>
        </div>

        <!-- Contenedor principal con bordes y sombra -->
        <div class="bg-card rounded-lg shadow-sm">
            
            <!-- Filtro por nivel de programa -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
                <!-- Buscador -->
                <div class="relative w-full sm:max-w-xs">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground"></i>
                    <input id="searchRae" type="text" placeholder="Buscar rae..." class="w-full rounded-md border border-input bg-background pl-9 pr-3 py-2 text-sm">
                </div>
                            
                <!-- Filtro por nivel de programa (a la derecha) -->
                <div class="flex items-center gap-2">
                    <svg
                        class="h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <path
                            d="M5 5h14a1 1 0 0 1 .8 1.6L15 12v4.5a1 1 0 0 1-.553.894l-3 1.5A1 1 0 0 1 10 18v-6L4.2 6.6A1 1 0 0 1 5 5z"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <select id="selectFiltroNivel" class="rounded-md border border-input bg-background px-3 py-2 w-40 text-sm">
                        <option value="">Todos los niveles</option>
                        <option value="Técnico">Técnico</option>
                        <option value="Tecnólogo">Tecnólogo</option>
                    </select>
                </div>
            </div>

            <!-- Aviso de sin RAEs en el sistema -->
            <div id="emptyStateRaes" class="hidden overflow-visible rounded-lg border border-border bg-card relative p-6 mb-6">
                <div class="flex flex-col items-center justify-center py-12 px-4">
                    <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-foreground mb-2">No hay RAEs registrados</h3>
                    <p class="text-sm text-muted-foreground text-center max-w-md">
                        Comienza creando un nuevo RAE usando el botón "Nuevo RAE".
                    </p>
                </div>
            </div>

            <!-- Aviso de búsqueda sin resultados (YA EXISTE - mantener) -->
            <div id="emptySearchRaes" class="hidden overflow-visible rounded-lg border border-border bg-card relative p-6 mb-6">
                <div class="flex flex-col items-center justify-center py-12 px-4">
                    <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <circle cx="11" cy="11" r="6" stroke-linecap="round" stroke-linejoin="round"></circle>
                            <line x1="16" y1="16" x2="20" y2="20" stroke-linecap="round" stroke-linejoin="round"></line>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-foreground mb-2">No se encontraron resultados</h3>
                    <p class="text-sm text-muted-foreground text-center max-w-md">
                        No se encontraron RAEs que coincidan con la descripción ingresada.
                    </p>
                </div>
            </div>

            <!-- VISTA DE TABLA -->
            <div id="tableView" class="border border-border rounded-lg">
                <table class="w-full border-collapse">
                    
                    <!-- Encabezados de la tabla -->
                    <thead>
                        <tr class="border-b border-border bg-muted">
                            <th class="text-left py-3 px-4 font-medium text-muted-foreground text-sm">ID</th>
                            <th class="text-left py-3 px-4 font-medium text-muted-foreground text-sm">Descripción</th>
                            <th class="text-left py-3 px-4 font-medium text-muted-foreground text-sm">Programa</th>
                            <th class="text-left py-3 px-4 font-medium text-muted-foreground text-sm">Estado</th>
                            <th class="text-left py-3 px-4 font-medium text-muted-foreground text-sm">Acciones</th>
                        </tr>
                    </thead>
                    
                    <!-- Filas de datos: cargadas dinámicamente desde la API -->
                    <tbody id="raesTableBody">
                        <!-- contenido generado por JS -->
                    </tbody>
                </table>
            </div>

            <!-- Aviso de búsqueda sin resultados -->
            <div id="emptySearchRaes" class="hidden mt-10 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full">
                <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
                    <svg class="h-7 w-7 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <circle cx="11" cy="11" r="6" stroke-linecap="round" stroke-linejoin="round"></circle>
                        <line x1="16" y1="16" x2="20" y2="20" stroke-linecap="round" stroke-linejoin="round"></line>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold mt-4">No se encontraron resultados</h3>
                <p class="text-sm text-muted-foreground mt-1 max-w-md">
                    No se encontraron RAEs que coincidan con la descripción ingresada.
                </p>
            </div>

            <!-- VISTA DE GRID (Bloques) -->
            <div id="gridView" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                <div id="gridViewContainer" class="col-span-1 sm:col-span-2 lg:col-span-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- tarjetas generadas por JS -->
                </div>
            </div>
        </div>
    </main>

    <!-- ========================================= -->
    <!-- ALERT CONTAINER (FLOWBITE-LIKE TOASTS)    -->
    <!-- ========================================= -->
    <div
        id="flowbite-alert-container"
        class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 w-full max-w-md"
    ></div>

    <!-- Modal de Detalles del RAE -->
    <div id="detailsModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-card rounded-2xl shadow-2xl max-w-md w-full border border-border overflow-hidden">
            <!-- Header del modal -->
            <div class="bg-card pl-6 pr-6 pt-6 flex items-start justify-between rounded-t-2xl">
                <div class="flex-1">
                    <h2 class="text-2xl font-bold">Detalles del RAE</h2>
                </div>
                <button onclick="closeDetailsModal()" class="text-muted-foreground hover:text-foreground transition flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Contenido del modal -->
            <div class="p-6 pt-3 space-y-4">
                <!-- Icono + Código + Estado -->
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-avatar-secondary-39 rounded-md flex items-center justify-center flex-shrink-0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#007832" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open">
                            <path d="M12 7v14"/>
                            <path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 id="detailsRaeCode" class="text-lg font-semibold">RAE #001</h3>
                        <span id="detailsRaeStatus" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#22c55e26] text-success">Activo</span>
                    </div>
                </div>

                <!-- Descripción -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Descripción:</label>
                    <p id="detailsRaeDescription" class="text-sm font-medium leading-relaxed"></p>
                </div>

                <!-- Programa -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Programa:</label>
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-graduation-cap">
                            <path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"></path>
                            <path d="M22 10v6"></path>
                            <path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"></path>
                        </svg>
                        <span id="detailsPrograma" class="text-sm"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Edición del RAE -->
    <div id="editModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-card rounded-2xl shadow-2xl max-w-2xl w-full border border-border overflow-hidden">
            <!-- Header del modal -->
            <div class="bg-card border-border pt-6 pl-6 pr-6 flex items-start justify-between rounded-t-2xl">
                <div class="bg-card border-border pr-6 flex items-start justify-between rounded-t-2xl flex-col">
                    <h2 class="text-3xl font-bold">Editar RAE</h2>
                    <b class="text-xs text-muted-foreground js-descripcion opacity-75">Modifica la información del RAE</b>
                </div>
                <button onclick="closeEditModal()" class="text-muted-foreground hover:text-foreground transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Formulario de edición -->
            <div class="p-6 pt-0 pb-2 space-y-4">
                <!-- Hidden ID del RAE -->
                <input type="hidden" id="editRaeId" value="">

                <!-- Código RAE -->
                <div>
                    <label for="editRaeCodigo" class="block text-sm font-medium mb-2">Código RAE *</label>
                    <input id="editRaeCodigo" type="text" placeholder="Ej: 001" class="w-full px-4 py-2 border border-border rounded-lg bg-card focus:outline-none focus:ring-2 focus:ring-ring transition-all">
                </div>

                <!-- Programa -->
                <div>
                    <label for="editRaeProgram" class="block text-sm font-medium mb-2">
                        Programa de formación
                    </label>
                    <select id="editRaeProgram" class="w-full px-4 py-2 border border-border rounded-lg bg-card focus:outline-none focus:ring-2 focus:ring-ring transition-all">
                        <option value="">Selecciona un programa</option>
                        <option value="Técnico en Construcción">Técnico en Construcción</option>
                        <option value="Técnico en Instalaciones Eléctricas">Técnico en Instalaciones Eléctricas</option>
                        <option value="Técnico en Acabados de Construcción">Técnico en Acabados de Construcción</option>
                    </select>
                </div>

                <!-- Descripción -->
                <div>
                    <label for="editRaeDescription" class="block text-sm font-medium mb-2">
                        Descripción del RAE
                    </label>
                    <textarea 
                        id="editRaeDescription" 
                        rows="4" 
                        class="w-full px-4 py-2 border border-border rounded-lg bg-card focus:outline-none focus:ring-2 focus:ring-ring transition-all resize-none"
                        placeholder="Describe el resultado de aprendizaje esperado..."
                    ></textarea>
                </div>
            </div>

            <!-- Footer del modal -->
            <div class="border-border p-6 pt-0 flex gap-3 justify-end">
                <button onclick="closeEditModal()" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors font-medium">
                    Cancelar
                </button>
                <button id="editRaeSubmit" class="inline-flex items-center justify-center rounded-sm bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2">
                    Guardar cambios
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Creación del RAE -->
    <div id="createModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-card rounded-2xl shadow-2xl max-w-2xl w-full border border-border overflow-hidden">
            <!-- Header del modal (sin línea debajo) -->
            <div class="bg-card pt-6 pl-6 pr-6 flex items-start justify-between rounded-t-2xl">
                <div>
                    <h2 class="text-2xl font-bold text-foreground">Crear Nuevo RAE</h2>
                    <p class="text-sm text-muted-foreground mt-1">Complete los datos para registrar un nuevo resultado de aprendizaje</p>
                </div>
                <button onclick="closeCreateModal()" class="text-muted-foreground hover:text-foreground transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Formulario de creación (orden: código, programa, descripción) -->
            <div class="p-6 space-y-4">
                <!-- Código RAE -->
                <div>
                    <label for="createRaeCodigo" class="block text-sm font-medium text-foreground mb-2">Código RAE *</label>
                    <input id="createRaeCodigo" type="text" placeholder="Ej: 001" class="w-full px-4 py-2 border border-border rounded-lg bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring transition-all">
                </div>

                <!-- Programa -->
                <div>
                    <label for="createRaeProgram" class="block text-sm font-medium text-foreground mb-2">Programa de formación *</label>
                    <select id="createRaeProgram" class="w-full px-4 py-2 border border-border rounded-lg bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring transition-all">
                        <option value="">Selecciona un programa</option>
                        <option value="Técnico en Construcción">Técnico en Construcción</option>
                        <option value="Técnico en Instalaciones Eléctricas">Técnico en Instalaciones Eléctricas</option>
                        <option value="Técnico en Acabados de Construcción">Técnico en Acabados de Construcción</option>
                    </select>
                </div>

                <!-- Descripción -->
                <div>
                    <label for="createRaeDescription" class="block text-sm font-medium text-foreground mb-2">Descripción del RAE *</label>
                    <textarea id="createRaeDescription" rows="4" class="w-full px-4 py-2 border border-border rounded-lg bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring transition-all resize-none" placeholder="Describe el resultado de aprendizaje esperado..."></textarea>
                </div>
            </div>

            <!-- Footer del modal (sin línea separadora) -->
            <div class="pl-6 pr-6 pb-6 flex gap-3 justify-end">
                <button onclick="closeCreateModal()" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors text-foreground font-medium">Cancelar</button>
                <button id="createRaeSubmit" class="inline-flex items-center justify-center rounded-sm bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2">Crear RAE</button>
            </div>
        </div>
    </div>
    <script src="<?= ASSETS_URL ?>js/raes/raes.js"></script>
</body>
</html>