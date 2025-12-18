<?php
require_once __DIR__ . '../../../../Config/database.php';

// Final array that will be used by the HTML
$programas = [];

try {
    $sql = "SELECT 
                id_programa, 
                codigo_programa, 
                nombre_programa, 
                nivel_programa, 
                descripcion_programa, 
                duracion_horas, 
                estado 
            FROM programas_formacion";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    // Fetch raw DB results
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map DB fields → HTML expected fields
    foreach ($raw as $r) {
        $programas[] = [
            'id_programa' => $r['id_programa'],  // Added id_programa
            'codigo'      => $r['codigo_programa'],
            'nombre'      => $r['nombre_programa'],
            'descripcion' => $r['descripcion_programa'],
            'nivel'       => $r['nivel_programa'],
            'duracion'    => $r['duracion_horas'] . ' horas',
            'instructores'=> 0,
            'estado'      => $r['estado']
        ];
    }

} catch (PDOException $e) {
    die("Error al cargar programas: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Programas de Formación - SENA</title>
        <!-- Tailwind CSS -->
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
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
    <!-- Import SENA global.css only, without custom styles.css -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/globals.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-background text-foreground min-h-screen flex flex-col">
    
    <!-- MAIN CONTENT - Without header or sidebar (separate components) -->
    <main class="p-6 transition-all duration-300"
      style="margin-left: <?= $collapsed ? '70px' : '260px' ?>;">
        <?php
            // include_once __DIR__ . '/../../includes/footer.php';
        ?>
        <!-- Page title + controls (aligned) -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold">Programas de Formación</h1>
                <p class="text-muted-foreground">Gestiona los programas técnicos y tecnológicos</p>
            </div>

            <!-- Right-side controls: view switch and "Nuevo Programa" -->
            <div class="flex items-center gap-3">
                <div class="inline-flex rounded-lg border border-border bg-card shadow-sm overflow-hidden">
                    <button 
                        type="button" 
                        id="viewTableBtn" 
                        onclick="toggleView('table')" 
                        class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 bg-muted text-foreground" 
                        title="Vista tabla"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    
                    <button
                        type="button"
                        id="viewGridBtn"
                        onclick="toggleView('grid')"
                        class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 text-muted-foreground"
                        title="Vista tarjetas"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <rect x="4" y="4" width="7" height="7" rx="1"></rect>
                            <rect x="13" y="4" width="7" height="7" rx="1"></rect>
                            <rect x="4" y="13" width="7" height="7" rx="1"></rect>
                            <rect x="13" y="13" width="7" height="7" rx="1"></rect>
                        </svg>
                    </button>
                </div>

                <button onclick="openCreateModal()" id="btnNewProgram" class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2">
                    <i class="fas fa-plus"></i>
                    Nuevo Programa
                </button>
            </div>
        </div>

        <!-- Main container with borders and shadow -->
        <div class="bg-card rounded-lg shadow-sm">

            <!-- Filter row (Row 2: Search left + State filter right) -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between my-6">
                <!-- Search bar (left) -->
                <div class="relative w-full sm:max-w-xs">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground"></i>
                    <input 
                        type="text" 
                        placeholder="Buscar por nombre..." 
                        class="w-full rounded-md border border-input bg-background pl-9 pr-3 py-2 text-sm"
                    >
                </div>

                <!-- State filter on right (icon matches Usuarios module) -->
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

                    <select id="selectFiltroEstado" class="rounded-md border border-input bg-background px-3 py-2 w-40 text-sm">
                        <option value="">Todos</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </select>
                </div>
            </div>

            <!-- Empty State: No programas in system -->
            <div id="emptyStateProgramas" class="hidden overflow-visible rounded-lg border border-border bg-card relative p-6 mb-6">
                <div class="flex flex-col items-center justify-center py-12 px-4">
                    <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-inbox text-2xl text-muted-foreground"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-foreground mb-2">No hay programas registrados</h3>
                    <p class="text-sm text-muted-foreground text-center max-w-md">
                        Comienza creando un nuevo programa de formación usando el botón "Nuevo Programa".
                    </p>
                </div>
            </div>

            <!-- Empty State: Search results empty -->
            <div id="emptySearchProgramas" class="hidden overflow-visible rounded-lg border border-border bg-card relative p-6 mb-6">
                <div class="flex flex-col items-center justify-center py-12 px-4">
                    <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-search text-2xl text-muted-foreground"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-foreground mb-2">No se encontraron programas</h3>
                    <p class="text-sm text-muted-foreground text-center max-w-md">
                        No hay resultados que coincidan con tu búsqueda o filtros. Intenta con otros criterios.
                    </p>
                </div>
            </div>

            <!-- TABLE VIEW -->
            <div id="tableView" class="border border-border rounded-lg">
                <table class="w-full border-collapse">
                    
                    <!-- Table headers -->
                    <thead>
                        <tr class="border-b border-border bg-muted">
                            <th class="text-left py-3 px-4 font-medium text-muted-foreground text-sm">Código</th>
                            <th class="text-left py-3 px-4 font-medium text-muted-foreground text-sm">Programas de Formación</th>
                            <th class="text-left py-3 px-4 font-medium text-muted-foreground text-sm">Nivel</th>
                            <th class="text-left py-3 px-4 font-medium text-muted-foreground text-sm">Duración</th>
                            <th class="text-left py-3 px-4 font-medium text-muted-foreground text-sm">Instructores</th>
                            <th class="text-left py-3 px-4 font-medium text-muted-foreground text-sm">Estado</th>
                            <th class="text-left py-3 px-4 font-medium text-muted-foreground text-sm">Acciones</th>
                        </tr>
                    </thead>
                    
                    <!-- Data rows -->
                    <tbody>
                        <?php foreach ($programas as $index => $programa): ?>
                        <?php $isActive = (isset($programa['estado']) && (strtolower(trim((string)$programa['estado'])) === 'activo' || (string)$programa['estado'] === '1' || $programa['estado'] == 1)); ?>
                        <tr 
                            class="border-b border-border hover:bg-muted transition-colors"
                            data-index="<?php echo $index; ?>"
                            data-id-programa="<?php echo htmlspecialchars($programa['id_programa']); ?>"
                            data-codigo="<?php echo htmlspecialchars($programa['codigo']); ?>"
                            data-nombre="<?php echo htmlspecialchars($programa['nombre']); ?>"
                            data-descripcion="<?php echo htmlspecialchars($programa['descripcion']); ?>"
                            data-nivel="<?php echo htmlspecialchars($programa['nivel']); ?>"
                            data-duracion="<?php echo htmlspecialchars($programa['duracion']); ?>"
                            data-instructores="<?php echo htmlspecialchars($programa['instructores']); ?>"
                            data-estado="<?php echo $isActive ? 1 : 0; ?>"
                        >
                            <!-- Program code -->
                            <td class="py-4 px-4 text-sm font-medium text-foreground">
                                <span class="js-code"><?php echo $programa['codigo']; ?></span>
                            </td>
                            
                            <!-- Program information with icon -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-3">
                                    <!-- Circular icon -->
                                    <div class="w-10 h-10 bg-avatar-secondary-39 rounded-md flex items-center justify-center flex-shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#007832" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-graduation-cap-icon lucide-graduation-cap"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-foreground js-name truncate max-w-[200px]" title="<?php echo htmlspecialchars($programa['nombre']); ?>">
                                            <?php echo $programa['nombre']; ?>
                                        </div>
                                        <div class="text-xs text-muted-foreground js-descripcion opacity-75 truncate max-w-[200px]" title="<?php echo htmlspecialchars($programa['descripcion']); ?>">
                                            <?php echo $programa['descripcion']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Level badge (Técnico/Tecnólogo) -->
                            <td class="py-4 px-4">
                                <span class="js-nivel">
                                <?php if (strtolower($programa['nivel']) === 'técnico'): ?>
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-estado-activo">
                                        <?php echo $programa['nivel']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-role-parendiz">
                                        <?php echo $programa['nivel']; ?>
                                    </span>
                                <?php endif; ?>
                                </span>
                            </td>
                            
                            <!-- Duration with clock icon -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground opacity-75">
                                    <i class="far fa-clock"></i>
                                    <span class="js-duracion"><?php echo $programa['duracion']; ?></span>
                                </div>
                            </td>
                            
                            <!-- Number of instructors -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground opacity-75">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
                                    <span class="js-instructores"><?php echo $programa['instructores']; ?></span>
                                </div>
                            </td>

                            
                            <!-- Status badge (Active/Inactive) -->
                            <td class="py-4 px-4">
                                <span class="js-estado">
                                <?php if ($isActive): ?>
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-estado-activo">
                                        Activo
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-500/20 text-gray-400">
                                        Inactivo
                                    </span>
                                <?php endif; ?>
                                </span>
                            </td>
                            
                            <!-- Actions menu -->
                            <td class="py-4 px-4">
                                <div class="relative inline-block text-left">
                                    <button 
                                        onclick="toggleActionMenu(<?php echo $index; ?>)" 
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-muted text-slate-800 transition-colors"
                                    >
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                            <circle cx="5" cy="12" r="1.5"></circle>
                                            <circle cx="12" cy="12" r="1.5"></circle>
                                            <circle cx="19" cy="12" r="1.5"></circle>
                                        </svg>
                                    </button>
                                    
                                    <div id="actionMenu<?php echo $index; ?>" class="hidden absolute right-0 mt-2 w-48 rounded-xl border border-border bg-popover shadow-md py-1 z-50">

                                        <button onclick="openViewModal(<?php echo $index; ?>)" class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted transition-colors">
                                            <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M1 12S4.5 5 12 5s11 7 11 7-3.5 7-11 7S1 12 1 12z"/>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                            Ver detalles
                                        </button>

                                        <button onclick="openEditModal(<?php echo $index; ?>)" class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted transition-colors">
                                            <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.121 2.121 0 0 1 3 3L9 17l-4 1 1-4 10.5-10.5z"/>
                                            </svg>
                                            Editar
                                        </button>
                                        
                                        <hr class="border-border my-1">
                                        
                                        <button data-action="toggle-estado" class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted transition-colors">
                                            <?php if($isActive): ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-power-icon lucide-power mr-2 h-4 w-4"><path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/></svg>
                                                Deshabilitar
                                            <?php else: ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-power-icon lucide-power mr-2 h-4 w-4"><path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/></svg>
                                                Habilitar
                                            <?php endif; ?>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- GRID VIEW -->
            <div id="gridView" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">

                <?php foreach ($programas as $index => $programa): ?>
                <?php $isActive = (isset($programa['estado']) && (strtolower(trim((string)$programa['estado'])) === 'activo' || (string)$programa['estado'] === '1' || $programa['estado'] == 1)); ?>
                <div class="bg-card border border-border rounded-lg p-6 hover:shadow-md transition-all hover:-translate-y-1 h-full flex flex-col"
                    data-index="<?php echo $index; ?>"
                    data-id-programa="<?php echo htmlspecialchars($programa['id_programa']); ?>"
                    data-codigo="<?php echo htmlspecialchars($programa['codigo']); ?>"
                    data-nombre="<?php echo htmlspecialchars($programa['nombre']); ?>"
                    data-descripcion="<?php echo htmlspecialchars($programa['descripcion']); ?>"
                    data-nivel="<?php echo htmlspecialchars($programa['nivel']); ?>"
                    data-duracion="<?php echo htmlspecialchars($programa['duracion']); ?>"
                    data-instructores="<?php echo htmlspecialchars($programa['instructores']); ?>"
                    data-estado="<?php echo $isActive ? 1 : 0; ?>">
                    
                    <!-- ICONO + TÍTULO + EDIT -->
                    <div class="flex justify-between items-start mb-3 flex-shrink-0">
                        <!-- Info + Icon -->
                        <div class="flex items-start gap-3 flex-1 min-w-0"> <!-- Cambios aquí -->
                            <div class="w-12 h-12 bg-avatar-secondary-39 rounded-md flex items-center justify-center flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#007832" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-graduation-cap-icon lucide-graduation-cap"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>
                            </div>

                            <div class="min-w-0 flex-1"> <!-- Cambios aquí -->
                                <h3 class="font-semibold text-foreground js-name truncate" title="<?php echo htmlspecialchars($programa['nombre']); ?>">
                                    <?php echo $programa['nombre']; ?>
                                </h3>
                                <p class="text-sm text-muted-foreground js-descripcion opacity-75 line-clamp-2" title="<?php echo htmlspecialchars($programa['descripcion']); ?>">
                                    <?php echo $programa['descripcion']; ?>
                                </p>
                                <p class="text-xs text-muted-foreground js-code truncate" title="<?php echo htmlspecialchars($programa['codigo']); ?>">
                                    <?php echo $programa['codigo']; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Changed link to button that opens edit modal -->
                        <button onclick="openEditModal(<?php echo $index; ?>)" 
                        class="text-muted-foreground hover:text-foreground transition flex-shrink-0 ml-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                        </button>
                    </div>

                    <!-- Level + Duration -->
                    <div class="flex items-center justify-between mb-3 flex-shrink-0">
                        <!-- Nivel -->
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="js-nivel flex-shrink-0">
                                <?php if (strtolower($programa['nivel']) === 'técnico'): ?>
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-estado-activo truncate max-w-[100px]">
                                        <?php echo $programa['nivel']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-role-parendiz truncate max-w-[100px]">
                                        <?php echo $programa['nivel']; ?>
                                    </span>
                                <?php endif; ?>
                            </span>

                            <!-- State (badge igual same at the table) -->
                            <?php if ($isActive): ?>
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium badge-estado-activo truncate max-w-[80px]">
                                    Activo
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-500/20 text-gray-400 js-estado truncate max-w-[80px]">
                                    Inactivo
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Duration -->
                        <div class="flex items-center gap-2 text-sm text-muted-foreground opacity-75 flex-shrink-0">
                            <i class="far fa-clock"></i>
                            <span class="js-duracion truncate max-w-[80px]">
                                <?php echo $programa['duracion']; ?>
                            </span>
                        </div>
                    </div>

                    <hr class="border-border mb-3 flex-shrink-0">
                    <!-- Instructors + Toggle -->
                    <div class="flex items-center justify-between mt-auto flex-shrink-0"> <!-- Cambios aquí -->
                        <div class="flex items-center gap-2 text-sm text-muted-foreground opacity-75">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
                            <span class="js-instructores truncate max-w-[80px]">
                                <?php echo $programa['instructores']; ?>
                            </span>
                        </div>

                        <div class="flex flex-col items-end flex-shrink-0">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" <?php echo $isActive ? 'checked' : ''; ?>>

                                <div class="w-11 h-6 bg-gray-500/20 rounded-full peer-checked:bg-secondary transition-all"></div>

                                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></div>
                            </label>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Program edit modal -->
        <div id="editProgramModal" class="fixed inset-0 z-50 hidden items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/40" onclick="closeEditModal()"></div>
            <div class="relative max-w-lg w-full bg-card rounded-lg shadow-lg border border-border p-6">
                <div class="flex items-start justify-between">
                    <div class="flex items-start justify-between flex-col">
                        <h3 class="text-2xl font-bold tracking-tight">Editar Programa</h3>
                        <b class="text-xs text-muted-foreground js-descripcion opacity-75">Modifica la información del programa</b>
                    </div>
                    <button onclick="closeEditModal()" class="text-muted-foreground hover:text-foreground"><i class="fas fa-times"></i></button>
                </div>
                <form id="editProgramForm" class="space-y-3">
                    <input type="hidden" id="edit_index">
                    <div>
                        <label class="block text-xs text-muted-foreground mb-1">Código *</label>
                        <input id="edit_codigo" type="text" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" required>
                    </div>
                    <div class="relative">
                        <label class="block text-xs text-muted-foreground mb-1">Nivel *</label>
                        <select id="edit_nivel" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga">
                            <option value="Técnico">Técnico</option>
                            <option value="Tecnólogo">Tecnólogo</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-muted-foreground mb-1">Nombre del programa *</label>
                        <input id="edit_nombre" type="text" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" required>
                    </div>
                    <div>
                        <label class="block text-xs text-muted-foreground mb-1">Descripción *</label>
                        <textarea id="edit_descripcion" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-muted-foreground mb-1">Duración *</label>
                            <input id="edit_duracion" type="text" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga">
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 mt-4">
                        <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-border rounded-lg">Cancelar</button>
                        <button type="submit" class="inline-flex items-center justify-center rounded-sm bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- View details modal -->
        <div id="viewProgramModal" class="fixed inset-0 z-50 hidden items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/40" onclick="closeViewModal()"></div>
            <div class="relative max-w-md w-full bg-card rounded-lg shadow-lg border border-border p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold" id="view_title">Detalles del Programa</h3>
                        <p class="text-xs text-muted-foreground">Modifica la información del programa</p>
                    </div>
                    <button onclick="closeViewModal()" class="text-muted-foreground hover:text-foreground"><i class="fas fa-times"></i></button>
                </div>

                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-avatar-secondary-39 rounded-md flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#007832" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-graduation-cap-icon lucide-graduation-cap"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>
                    </div>
                    <div>
                        <div class="font-semibold text-foreground" id="view_name">Nombre Programa</div>
                        <div class="text-xs text-muted-foreground" id="view_code">COD-000</div>
                    </div>
                </div>

                <p class="text-sm text-muted-foreground mb-4" id="view_description">Descripción del programa</p>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Nivel:</span>
                        <span id="view_nivel" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#39A93526] text-primary">Técnico</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Duración:</span>
                        <span id="view_duracion" class="text-sm font-medium text-foreground">0 Horas</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Instructor:</span>
                        <span id="view_instructor" class="text-sm font-medium text-foreground">Juan Guillermo Crespo</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Estado:</span>
                        <span id="view_estado" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#22c55e26] text-success">Activo</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create New Program Modal (UI only) -->
        <div id="createProgramModal" class="fixed inset-0 z-50 hidden items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/40" onclick="closeCreateModal()"></div>
            <div class="relative max-w-lg w-full bg-card rounded-lg shadow-lg border border-border p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold">Crear Nuevo Programa</h3>
                        <p class="text-xs text-muted-foreground">Complete los datos para registrar un nuevo programa de formación</p>
                    </div>
                    <button onclick="closeCreateModal()" class="text-muted-foreground hover:text-foreground"><i class="fas fa-times"></i></button>
                </div>

                <form id="createProgramForm" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-muted-foreground mb-1">Código *</label>
                            <input id="create_codigo" type="text" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" placeholder="TEC-001">
                        </div>
                        <div>
                            <label class="block text-xs text-muted-foreground mb-1">Nivel *</label>
                            <select id="create_nivel" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga">
                                <option value="Técnico">Técnico</option>
                                <option value="Tecnólogo">Tecnólogo</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-muted-foreground mb-1">Nombre del programa *</label>
                        <input id="create_nombre" type="text" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" placeholder="Técnico en Construcción">
                    </div>

                    <div>
                        <label class="block text-xs text-muted-foreground mb-1">Descripción *</label>
                        <textarea id="create_descripcion" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" placeholder="Formación técnica de procesos constructivos"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs text-muted-foreground mb-1">Duración *</label>
                        <input id="create_duracion" type="text" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input-siga" placeholder="X Horas">
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-4">
                        <button type="button" onclick="closeCreateModal()" class="px-4 py-2 border border-border rounded-lg">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-secondary text-primary-foreground rounded-lg">Crear Programa</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <!-- Changed script src from toggle-view.js to programas.js -->
    <script src="<?= ASSETS_URL ?>js/programas/programas.js"></script>
    <!-- ========================================= -->
    <!-- ALERT CONTAINER (FLOWBITE-LIKE TOASTS)    -->
    <!-- ========================================= -->
    <div id="flowbite-alert-container" class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 w-full max-w-md"></div>
</body>
</html>