<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('ACCESO_PERMITIDO', true);

session_start();

// Ruta base del proyecto
define('BASE_PATH', __DIR__);

// Base URL dinámica
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host       = $_SERVER['HTTP_HOST'];
$script_dir = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
define('BASE_URL', $protocol . $host . $script_dir);

// URL base para los assets
define('ASSETS_URL', BASE_URL . "src/assets/");

// 🔐 Nombre de la clave de sesión donde guardas el ID del usuario
//   AJÚSTALO al nombre REAL que uses en login.php
$SESSION_USER_KEY = 'usuario_id';  // si en tu login usaste 'id_usuario', cambia esto

// =============================
// LÓGICA DE PÁGINA ACTUAL
// =============================

// Página solicitada (por defecto 'landing')
$page = $_GET['page'] ?? 'landing';
$page = basename($page); // sanitizar

// 1) Si es la LANDING → mostrar solo landing.php sin header/sidebar/footer
if ($page === 'landing') {
    $landingFile = BASE_PATH . "/src/view/landing.php"; // ajusta ruta si tu vista está en otro lado

    if (file_exists($landingFile)) {
        include $landingFile;
    } else {
        echo "<p style='color:red; text-align:center; padding:2rem;'>
                No se encontró la vista <strong>landing.php</strong>.
            </p>";
    }
    exit; // importante: no seguir renderizando el layout
}

// 2) A PARTIR DE AQUÍ, TODAS LAS PÁGINAS SON PROTEGIDAS
//    Si NO hay sesión → mandar al login (tu login real)

if (!isset($_SESSION['usuario_id'])) {
    // ✅ CORREGIDO: tu login real es src/view/login/login.php
    header('Location: ' . BASE_URL . 'src/view/login/login.php');
    exit;
}
?>
<!DOCTYPE html> 
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestion Inventario</title>
    <link rel="icon" type="image/png" href="">
    <!-- <link rel="stylesheet" href="./public/css/output.css"> -->
</head>
<body class="flex flex-col min-h-screen font-sans bg-white text-gray-900 transition-all duration-300">
    <header>
        <?php require_once BASE_PATH . '/src/includes/header.php'; ?>
        <?php require_once BASE_PATH . '/src/includes/sidebar.php'; ?>
    </header>

    <main class="flex-grow">
        <?php require_once BASE_PATH . '/src/includes/main.php'; ?>
    </main>

    <script>
        const BASE_URL = "<?= BASE_URL ?>";
    </script>
</body>
</html>
