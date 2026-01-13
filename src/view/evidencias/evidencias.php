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
  <title>Evidencias de Formación - SIGA</title>

  <!-- Tailwind desde CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/flowbite@2.5.1/dist/flowbite.min.js"></script>

  <!-- CSS global con paleta y utilidades -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/src/assets/css/globals.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/src/assets/css/evidencias/evidencias.css">

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
            <h1 class="text-2xl font-bold tracking-tight">Evidencias de Formación</h1>
            <p class="text-muted-foreground">Registro de evidencias de uso de materiales de formación</p>
          </div>

          <!-- Botón Nueva Evidencia -->
          <button
            id="btnNuevaEvidencia"
            type="button"
            class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 gap-2"
          >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nueva Evidencia
          </button>
        </div>

        <!-- Evidence Grid -->
        <div id="evidenceGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <!-- Evidence Card 1 -->
            <div class="evidence-card bg-card rounded-xl border border-border overflow-hidden cursor-pointer hover:shadow-lg transition-all" onclick="openDetailModal(1)">
                <div class="relative">
                    <img src="<?= BASE_URL ?>/uploads/evidencias/prueba.jpg" alt="Evidencia" class="w-full h-48 object-cover">
                    <div class="absolute top-3 right-3 bg-card/90 backdrop-blur-sm px-3 py-1.5 rounded-lg flex items-center gap-2">
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-2">
                            
                        <span class="text-xs text-muted-foreground">2024-04-06</span>
                        <span class="text-xs font-medium">Ficha 2896441</span>
                    </div>
                    <p class="text-sm text-foreground line-clamp-2 mb-3">Trabajo de cimentación realizado por los aprendices de la ficha 2567890</p>
                    <div class="flex flex-wrap gap-2">
                        <span class="badge-material">
                            <img src="tag-icon.png" alt="material" class="w-3 h-3">
                            Cemento Gris
                        </span>
                        <span class="badge-material">
                            <img src="tag-icon.png" alt="material" class="w-3 h-3">
                            Arena de Río
                        </span>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </main>

    <!-- CREATE MODAL -->
    <div id="createModal" class="modal-overlay">
        <div class="relative w-full max-w-md rounded-xl border border-border bg-card p-5 shadow-lg">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                    <h2 class="text-base font-semibold">Registrar Evidencia</h2>
                    <p class="text-xs text-muted-foreground">
                        Cargue una evidencia de uso de materiales de formación
                    </p>
                </div>
                <button
                    type="button"
                    onclick="closeCreateModal()"
                    class="rounded-full p-1 hover:bg-muted flex-shrink-0"
                >
                    <span class="sr-only">Cerrar</span>
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <form class="space-y-3" onsubmit="event.preventDefault(); createEvidence();">
                <!-- Fotografía de evidencia -->
                <div class="space-y-1.5">
                    <label class="text-xs font-medium">Fotografía de evidencia *</label>
                    <div class="upload-area" id="uploadArea">
                        <svg class="w-8 h-8 text-muted-foreground mb-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-xs text-foreground mb-0.5">
                            <span class="font-medium text-secondary cursor-pointer">Arrastra una imagen o haz clic para seleccionar</span>
                        </p>
                        <p class="text-xs text-muted-foreground">PNG, JPG, hasta 5MB</p>
                        <input type="file" id="photoInput" accept="image/png,image/jpeg,image/jpg" class="hidden" required>
                    </div>
                    <div id="imagePreview" class="hidden mt-1.5">
                        <img id="previewImg" src="" alt="Preview" class="w-full h-24 object-cover rounded-lg border border-border">
                        <button type="button" onclick="removeImage()" class="mt-1 text-xs text-red-500 hover:text-red-700">Eliminar imagen</button>
                    </div>
                </div>
                
                <!-- Descripción -->
                <div class="space-y-1.5">
                    <label class="text-xs font-medium">Descripción de la evidencia *</label>
                    <textarea id="descripcion" placeholder="Describe la evidencia..." class="w-full rounded-md border border-input px-2.5 py-1.5 text-xs h-14 resize-none" required></textarea>
                </div>
                
                <!-- Buttons -->
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeCreateModal()" class="inline-flex items-center justify-center rounded-md border border-input bg-background px-3 py-1.5 text-xs font-medium hover:bg-muted">
                        Cancelar
                    </button>
                    <button type="submit" class="inline-flex items-center justify-center rounded-md bg-secondary px-3 py-1.5 text-xs font-medium text-primary-foreground shadow hover:opacity-90">
                        Guardar cambio
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DETAILS MODAL -->
    <div id="detailsModal" class="modal-overlay">
        <div class="relative w-full max-w-md rounded-xl border border-border bg-card shadow-lg overflow-hidden">
            <div class="flex items-start justify-between gap-3 p-5 pb-0">
                <h2 class="text-lg font-semibold">Detalle de Evidencia</h2>
                <button
                    type="button"
                    onclick="closeDetailsModal()"
                    class="rounded-full p-1 hover:bg-muted flex-shrink-0"
                >
                    <span class="sr-only">Cerrar</span>
                    <svg class="h-4 w-4" xm lns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <div class="p-5 space-y-3">
                <div>
                    <img id="detailImage" src="" alt="Evidencia" class="w-full h-80 object-cover rounded-xl border border-border">
                </div>
                
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="text-xs text-muted-foreground mb-1">Ficha</p>
                        <p id="detailFicha" class="font-medium">-</p>
                    </div>
                    
                    <div>
                        <p class="text-xs text-muted-foreground mb-1">Fecha</p>
                        <p id="detailDate" class="font-medium flex items-center gap-1">
                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
                            -
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-xs text-muted-foreground mb-1">Descripción</p>
                        <p id="detailDescription" class="text-sm text-foreground leading-relaxed">-</p>
                    </div>
                    
                    <div>
                        <p class="text-xs text-muted-foreground mb-2">Materiales</p>
                        <div id="detailMaterials" class="flex flex-wrap gap-2">
                            <!-- Se llena dinámicamente -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
       
    <script src="<?= BASE_URL ?>/src/assets/js/evidencias/evidencias.js"></script>
</body>
</html>
