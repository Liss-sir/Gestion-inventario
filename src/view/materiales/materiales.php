<?php
// =====================================
// USER MANAGEMENT – PHP VIEW
// =====================================

$collapsed = isset($_GET["coll"]) && $_GET["coll"] == "1";
$sidebarWidth = $collapsed ? "70px" : "260px";
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Materiales</title>

  <!-- Tailwind desde CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/flowbite@2.5.1/dist/flowbite.min.js"></script>

  <!-- CSS global con paleta y utilidades -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/src/assets/css/globals.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/src/assets/css/materiales/materiales.css">

  <!-- Exponer BASE_URL al JS para construir API_URL cuando no se use override -->
  <script>
    window.BASE_URL = "<?= BASE_URL ?>";
    // Si necesitas apuntar al backend actualizado en otra URL sin editar código:
    // window.MATERIALES_API_URL = "https://tu-servidor.com/Gestion-inventario/src/controllers/material_formacion_controller.php";
  </script>

</head>
<body
  class="min-h-screen bg-background text-foreground transition-all duration-300
    <?php echo $collapsed ? 'lg:pl-[70px]' : 'lg:pl-[260px]'; ?>"
>

      <main class="p-6 transition-all duration-300"
      style="margin-left: <?= $sidebarWidth ?>;">
      <div class="space-y-6 animate-fade-in-up">

        <!-- HEADER -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 class="text-2xl font-bold tracking-tight">Gestión de Materiales</h1>
            <p class="text-muted-foreground">Gestiona el inventario de materiales y herramientas</p>
          </div>

          <!-- Updated right controls with consistent spacing and styling -->
          <div class="flex items-center gap-3">
            <!-- View toggle buttons -->
            <div class="inline-flex rounded-lg border border-border bg-card shadow-sm overflow-hidden">
              <!-- Table view -->
              <button
                id="tableViewBtn"
                type="button"
                class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 bg-muted text-foreground"
                title="Vista de tabla"
              >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
              </button>

              <!-- Card view -->
              <button
                id="cardViewBtn"
                type="button"
                class="px-3 py-2 text-xs sm:text-sm flex items-center gap-1 text-muted-foreground"
                title="Vista de tarjetas"
              >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <rect x="4" y="4" width="7" height="7" rx="1"></rect>
                  <rect x="13" y="4" width="7" height="7" rx="1"></rect>
                  <rect x="4" y="13" width="7" height="7" rx="1"></rect>
                  <rect x="13" y="13" width="7" height="7" rx="1"></rect>
                </svg>
              </button>
            </div>

            <!-- New material button -->
            <button
              id="btnNuevoMaterial"
              type="button"
              onclick="openCreateModal()"
              class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2"
            >
              <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Nuevo Material
            </button>
          </div>
        </div>

<!-- Contenedor principal -->
<div class="flex justify-between items-center w-full">

  <!-- BUSCADOR A LA IZQUIERDA -->
  <div class="relative w-full max-w-xs">
    <input
      type="text"
      id="inputBuscar"
      placeholder="Buscar por nombre o descripción..."
      class="w-full rounded-md border border-input bg-background px-3 py-2 pl-10 text-sm"
    />
    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <circle cx="11" cy="11" r="8"></circle>
      <path d="m21 21-4.35-4.35"></path>
    </svg>
  </div>

  <!-- FILTRO A LA DERECHA -->
  <div class="flex items-center gap-2">
    <!-- Ícono de filtro -->
    <svg class="h-4 w-4 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
      <path
        d="M5 5h14a1 1 0 0 1 .8 1.6L15 12v4.5a1 1 0 0 1-.553.894l-3 1.5A1 1 0 0 1 10 18v-6L4.2 6.6A1 1 0 0 1 5 5z"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </svg>

    <!-- Select -->
    <select
      id="selectFiltroRol"
      class="rounded-md border border-input bg-background px-3 pr-10 py-2 text-sm"
    >
      <option value="">Todos</option>
      <option value="Inventariado">Inventariado</option>
      <option value="Consumible">Consumible</option>
    </select>
  </div>

</div>

        <!-- Updated materials table with consistent styling -->
        <div id="tableView">
          <div class="overflow-hidden rounded-xl border border-border bg-card">
            <table class="min-w-full divide-y divide-border text-sm">
              <thead class="bg-gray-100">
                <tr>
                  <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground">Código</th>
                  <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground">Nombre</th>
                  <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground">Descripción</th>
                  <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground">Clasificación</th>
                  <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground">Unidad</th>
                  <th class="px-4 py-3 text-left font-medium text-xs text-muted-foreground">Estado</th>
                  <th class="px-4 py-3 text-right font-medium text-xs text-muted-foreground">Acciones</th>
                </tr>
              </thead>
              <tbody id="tableBody" class="divide-y divide-border bg-card">
                <!-- Se llena dinámicamente con JS -->
              </tbody>
            </table>
          </div>
          
          <!-- Moved table pagination outside the card but inside tableView -->
          <div class="flex justify-end mt-4">
            <div class="pagination" id="pagination"></div>
          </div>
        </div>

        <!-- Card view container -->
        <div id="cardView" class="hidden">
          <div
            id="cardsContainer"
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
          >
            <!-- Se llena dinámicamente con JS -->
          </div>
          <!-- Moved pagination to bottom right corner -->
          <div class="flex justify-end mt-4">
            <div class="pagination" id="cardPagination"></div>
          </div>
        </div>

      </div>
    </main>

    <!-- CREATE MODAL -->
    <!-- Updated modal for new material structure -->
    <div id="createModal" class="modal-overlay">
        <div class="relative w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-lg">
            <div class="flex items-start justify-between gap-4 mb-3">
                <div>
                    <h2 class="text-lg font-semibold">Crear Nuevo Material</h2>
                    <p class="text-xs text-muted-foreground">
                        Complete los datos para registrar un nuevo material de formación
                    </p>
                </div>
                <button
                    type="button"
                    onclick="closeCreateModal()"
                    class="rounded-full p-1 hover:bg-muted"
                >
                    <span class="sr-only">Cerrar</span>
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <form class="space-y-3" onsubmit="event.preventDefault(); createMaterial();">
                <!-- Nombre -->
                <div class="space-y-2">
                    <label class="text-sm font-medium">Nombre *</label>
                    <input id="nombre" type="text" placeholder="Ej: Cemento gris" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                </div>
                
                <!-- Descripción -->
                <div class="space-y-2">
                    <label class="text-sm font-medium">Descripción *</label>
                    <textarea id="descripcion" placeholder="Descripción del material" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm h-16"></textarea>
                </div>
                
                <!-- Clasificación y Unidad -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Clasificación *</label>
                        <select id="clasificacion" class="w-full rounded-md border border-input bg-background px-3 pr-10 py-2 text-sm input" onchange="toggleCodigoField()">
                            <option value="">Seleccione...</option>
                            <option value="Inventariado">Inventariado</option>
                            <option value="Consumible">Consumible</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Unidad de medida *</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="unidad" 
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input" 
                                placeholder="Buscar unidad..."
                                autocomplete="off"
                            />
                            <svg class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                            <div id="unidadDropdown" class="hidden absolute z-50 w-full mt-1 max-h-60 overflow-auto rounded-md border border-border bg-card shadow-lg">
                                <div class="py-1">
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="AMP">AMP</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="BARRA">BARRA</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="BIDÓN">BIDÓN</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="BOLSA">BOLSA</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="BULTO">BULTO</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="CABLE">CABLE</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="CAJA">CAJA</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="CAÑUELA">CAÑUELA</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="CM">CM</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="CM2">CM2</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="CM3">CM3</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="DISP">DISP</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="G">G</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="GL">GL</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="JGO">JGO</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="KG">KG</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="KIT">KIT</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="KW">KW</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="L">L</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="LÁMINA">LÁMINA</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="M">M</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="M2">M2</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="M3">M3</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="ML">ML</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="MM">MM</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="PAQUETE">PAQUETE</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="PAR">PAR</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="PIE">PIE</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="PULG">PULG</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="ROLLO">ROLLO</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="SACO">SACO</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="TON">TON</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="TUBO">TUBO</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="UND">UNIDAD</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="V">V</div>
                                    <div class="unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="W">W</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Precio y Código en grid -->
                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <label class="text-sm font-medium">Precio *</label>
                    <input id="precio" type="text" inputmode="decimal" placeholder="$ 0" min="100" class="w-full rounded-md border border-input px-3 py-2 text-sm bg-transparent">
                  </div>
                  
                  <div class="space-y-2">
                    <label class="text-sm font-medium">Stock Máximo *</label>
                    <input id="stock_maximo" type="text" inputmode="numeric" placeholder="0" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                  </div>
                </div>

                <div id="codigoContainer" class="space-y-2" style="display: none;">
                  <label class="text-sm font-medium">Código *</label>
                  <input id="codigo" type="text" placeholder="EJ: 1234" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                </div>
                
                <div id="codigoHelpText" class="text-xs text-muted-foreground" style="display: none;">
                  Este campo es obligatorio solo para materiales inventariados
                </div>

                <!-- Imagen en bloque completo para mantener orden visual -->
                <div class="space-y-2">
                  <label class="text-sm font-medium">Imagen del material *</label>
                  <div id="dropzoneImagen" class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-border bg-background px-3 py-3 text-center cursor-pointer hover:bg-muted transition-colors overflow-hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-muted-foreground mb-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                      <polyline points="7 10 12 5 17 10" />
                      <line x1="12" y1="5" x2="12" y2="19" />
                    </svg>
                    <p class="text-xs text-muted-foreground">Arrastra una imagen o haz clic para seleccionar</p>
                    <p class="text-[10px] text-muted-foreground">PNG, JPG hasta 2MB</p>
                    <input id="imagen" type="file" accept="image/png,image/jpeg" class="sr-only" />
                    <img id="previewImagen" alt="Vista previa" class="absolute inset-0 h-full w-full object-cover hidden" />
                  </div>
                </div>
                
                <!-- Buttons -->
                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="closeCreateModal()" class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-muted">
                        Cancelar
                    </button>
                    <button type="submit" class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:opacity-90">
                        Crear Material
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DETAILS MODAL -->
    <div id="detailsModal" class="modal-overlay">
        <div class="relative w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-lg">
            <div class="flex items-start justify-between gap-4 mb-4">
                <h2 class="text-lg font-semibold">Detalles del Material</h2>
                <button
                    type="button"
                    onclick="closeDetailsModal()"
                    class="rounded-full p-1 hover:bg-muted"
                >
                    <span class="sr-only">Cerrar</span>
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <div id="detailsContent" class="space-y-4">
                <!-- Se llena dinámicamente con JS -->
            </div>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <!-- Updated edit modal for new structure -->
    <div id="editModal" class="modal-overlay">
        <div class="relative w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-lg">
            <div class="flex items-start justify-between gap-4 mb-3">
                <div>
                    <h2 class="text-lg font-semibold">Editar Material</h2>
                    <p class="text-xs text-muted-foreground">
                        Modifica la información del material
                    </p>
                </div>
                <button
                    type="button"
                    onclick="closeEditModal()"
                    class="rounded-full p-1 hover:bg-muted"
                >
                    <span class="sr-only">Cerrar</span>
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form class="space-y-3" onsubmit="event.preventDefault(); updateMaterial();">
                <input type="hidden" id="editId">
                
                <!-- Nombre -->
                <div class="space-y-2">
                    <label class="text-sm font-medium">Nombre *</label>
                    <input id="editNombre" type="text" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                </div>
                
                <!-- Descripción -->
                <div class="space-y-2">
                    <label class="text-sm font-medium">Descripción *</label>
                    <textarea id="editDescripcion" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm h-16"></textarea>
                </div>
                
                <!-- Clasificación y Unidad -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Clasificación *</label>
                        <select id="editClasificacion" class="w-full rounded-md border border-input bg-background px-3 pr-10 py-2 text-sm input" onchange="toggleEditCodigoField()">
                            <option value="Inventariado">Inventariado</option>
                            <option value="Consumible">Consumible</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Unidad de medida *</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="editUnidad" 
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm input" 
                                placeholder="Buscar unidad..."
                                autocomplete="off"
                            />
                            <svg class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                            <div id="editUnidadDropdown" class="hidden absolute z-50 w-full mt-1 max-h-60 overflow-auto rounded-md border border-border bg-card shadow-lg">
                                <div class="py-1">
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="AMP">AMP</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="BARRA">BARRA</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="BIDÓN">BIDÓN</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="BOLSA">BOLSA</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="BULTO">BULTO</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="CABLE">CABLE</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="CAJA">CAJA</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="CAÑUELA">CAÑUELA</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="CM">CM</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="CM2">CM2</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="CM3">CM3</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="DISP">DISP</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="G">G</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="GL">GL</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="JGO">JGO</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="KG">KG</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="KIT">KIT</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="KW">KW</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="L">L</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="LÁMINA">LÁMINA</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="M">M</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="M2">M2</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="M3">M3</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="ML">ML</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="MM">MM</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="PAQUETE">PAQUETE</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="PAR">PAR</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="PIE">PIE</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="PULG">PULG</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="ROLLO">ROLLO</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="SACO">SACO</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="TON">TON</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="TUBO">TUBO</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="UND">UNIDAD</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="V">V</div>
                                    <div class="edit-unidad-option px-3 py-2 text-sm cursor-pointer hover:bg-muted" data-value="W">W</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Precio y Código en grid -->
                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <label class="text-sm font-medium">Precio *</label>
                    <input id="editPrecio" type="text" inputmode="decimal" placeholder="$ 0" min="100" class="w-full rounded-md border border-input px-3 py-2 text-sm bg-transparent">
                  </div>
                  
                  <div class="space-y-2">
                    <label class="text-sm font-medium">Stock Máximo *</label>
                    <input id="editStockMaximo" type="text" inputmode="numeric" placeholder="0" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                  </div>
                </div>

                <div id="editCodigoContainer" class="space-y-2">
                  <label class="text-sm font-medium">Código <span id="editCodigoRequired">*</span></label>
                  <input id="editCodigo" type="text" placeholder="EJ: 1234" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                </div>
                
                <p class="text-xs text-muted-foreground">Este campo es obligatorio solo para materiales inventariados</p>

                <!-- Imagen en bloque completo (edición) -->
                <div class="space-y-2">
                  <label class="text-sm font-medium">Imagen del material *</label>
                  <div id="editDropzoneImagen" class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-border bg-background px-3 py-3 text-center cursor-pointer hover:bg-muted transition-colors overflow-hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-muted-foreground mb-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                      <polyline points="7 10 12 5 17 10" />
                      <line x1="12" y1="5" x2="12" y2="19" />
                    </svg>
                    <p class="text-xs text-muted-foreground">Arrastra una imagen o haz clic para seleccionar</p>
                    <p class="text-[10px] text-muted-foreground">PNG, JPG hasta 2MB</p>
                    <input id="editImagen" type="file" accept="image/png,image/jpeg" class="sr-only" />
                    <img id="editPreviewImagen" alt="Vista previa" class="absolute inset-0 h-full w-full object-cover hidden" />
                  </div>
                </div>
                
                <!-- Buttons -->
                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="closeEditModal()" class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-muted">
                        Cancelar
                    </button>
                    <button type="submit" class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:opacity-90">
                        Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Contenedor para las alertas tipo Flowbite -->
    <div
      id="flowbite-alert-container"
      class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 w-full max-w-md"
    ></div>

    <!-- JavaScript al final del body -->
    <script src="<?= BASE_URL ?>/src/assets/js/materiales/materiales.js"></script>

</body>
</html>
