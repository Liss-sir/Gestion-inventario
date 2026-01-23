<?php
if (!defined('ACCESO_PERMITIDO')) {
    http_response_code(403);
    exit('Acceso directo no permitido');
}

$page = $_GET['page'] ?? 'dashboard';
$page = basename($page);

// Mapeamos las páginas a sus archivos reales
switch ($page) {
    case 'dashboard':
        $viewFile = BASE_PATH . '/src/view/dashboard/dashboard.php';
        break;

    case 'usuarios':
        $viewFile = BASE_PATH . '/src/view/usuarios/usuarios.php'; // o el nombre que tengas
        break;

    case 'bodegas':
        $viewFile = BASE_PATH . '/src/view/bodegas/bodegas.php';  // ajusta al archivo real
        break;

    case 'notificaciones':
        $viewFile = BASE_PATH . '/src/view/notificaciones/notificaciones.php';  // ajusta al archivo real
        break;
    case 'movimientos':
        $viewFile = BASE_PATH . '/src/view/movimientos/movimientos.php';  // ajusta al archivo real
        break;
        
    case 'programas':
    $viewFile = BASE_PATH . '/src/view/programas/programas.php';  // ajusta al archivo real
    break;

    case 'raes':
    $viewFile = BASE_PATH . '/src/view/raes/raes.php';  // ajusta al archivo real
    break;

    case 'reportes':
        $viewFile = BASE_PATH . '/src/view/reportes/reportes.php';  // ajusta al archivo real
    break;

    case 'fichas':
        $viewFile = BASE_PATH . '/src/view/fichas/fichas.php';  // ajusta al archivo real
        break;        
    case 'rae':
        $viewFile = BASE_PATH . '/src/view/raes/raes.php';  // ajusta al archivo real
        break;        

    case 'solicitudes':
        $viewFile = BASE_PATH . '/src/view/solicitudes/solicitudes.php';  // ajusta al archivo real
        break;

    case 'obras':
        $viewFile = BASE_PATH . '/src/view/obras/obras.php';  // ajusta al archivo real
        break;

    case 'materiales':
            $viewFile = BASE_PATH . '/src/view/materiales/materiales.php';  // ajusta al archivo real
            break;
    case 'historial':
            $viewFile = BASE_PATH . '/src/view/historial/historial.php';  // ajusta al archivo real
            break;
    // ...agrega aquí más casos según tus carpetas/vistas...
    case 'evidencias':
            $viewFile = BASE_PATH . '/src/view/evidencias/evidencias.php';  // ajusta al archivo real
            break;
    default:
        // Por defecto intentamos src/view/$page.php (para cosas como landing, etc.)
        $viewFile = BASE_PATH . "/src/view/$page.php";
        break;
}

if (file_exists($viewFile)) {
    include $viewFile;
} else {
    echo "<p style='color:red; text-align:center; padding:2rem;'>
            La vista <strong>$page</strong> no existe.<br>
            Archivo buscado: <code>$viewFile</code>
          </p>";
}
