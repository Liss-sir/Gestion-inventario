<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===============================
// DB + BASE_URL
// ===============================
require_once __DIR__ . '/../../../Config/database.php';

/* ================= BASE_URL AUTO ================= */
if (!defined('BASE_URL')) {

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https://'
        : 'http://';

    $host = $_SERVER['HTTP_HOST'];

    // Ruta del script actual (ej: /Gestion-inventario/src/controllers/usuario_controller.php)
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

    // Cortamos hasta la carpeta raíz del proyecto
    // Quita /src/controllers, /src/views, etc
    $project = preg_replace('#/src/.*$#', '/', $scriptDir);

    define('BASE_URL', $protocol . $host . $project);
}


// ===============================
// VALIDAR TOKEN EN URL
// ===============================
$token = trim($_GET['token'] ?? '');
if ($token === '') {
    echo "Token inválido o ausente.";
    exit;
}

// Mensaje de error opcional
$msgErr = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer contrasena - SIGA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link rel="stylesheet" href="../../assets/css/globals.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex items-center justify-center bg-background p-4 relative">

    <!-- Fondo -->
    <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-accent/5"></div>

    <!-- Card -->
    <div class="relative w-full max-w-md shadow-xl bg-white rounded-xl border border-gray-200 fade-in">

        <!-- Header -->
        <div class="space-y-4 text-center pb-2 p-6">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-secondary">
                <img src="../../assets/img/logo-sena-blanco.png"
                     alt="logo sena blanco"
                     class="h-8 w-auto object-contain">
            </div>

            <div>
                <h1 class="text-2xl font-bold">Nueva contraseña</h1>
                <p class="mt-1 text-gray-500">Crea una contraseña nueva</p>
            </div>

            <?php if ($msgErr === 'pass'): ?>
                <p class="text-sm text-red-600">La contraseña debe tener mínimo 8 caracteres.</p>
            <?php elseif ($msgErr === 'match'): ?>
                <p class="text-sm text-red-600">Las contraseñas no coinciden.</p>
            <?php elseif ($msgErr === 'invalid'): ?>
                <p class="text-sm text-red-600">El enlace es inválido o ya fue usado.</p>
            <?php elseif ($msgErr === 'expired'): ?>
                <p class="text-sm text-red-600">El enlace ha expirado.</p>
            <?php endif; ?>
        </div>

        <!-- Form -->
        <div class="pt-4 px-6 pb-6">
            <form
                method="POST"
                action="<?= BASE_URL ?>src/controllers/usuario_controller.php?accion=reset_password"
                class="space-y-4"
            >
                <!-- TOKEN -->
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <!-- PASSWORD -->
                <div class="space-y-2">
                    <label class="text-sm font-medium">Nueva contraseña</label>
                    <input
                        name="password"
                        type="password"
                        required
                        minlength="8"
                        placeholder="••••••••"
                        class="h-11 w-full border rounded-md px-3"
                    />
                </div>

                <!-- CONFIRM -->
                <div class="space-y-2">
                    <label class="text-sm font-medium">Confirmar contraseña</label>
                    <input
                        name="password2"
                        type="password"
                        required
                        minlength="8"
                        placeholder="••••••••"
                        class="h-11 w-full border rounded-md px-3"
                    />
                </div>

                <!-- BTN -->
                <button
                    type="submit"
                    class="w-full h-11 bg-secondary text-white rounded-md hover:opacity-90 transition"
                >
                    Guardar contraseña
                </button>

                <a
                    href="<?= BASE_URL ?>src/view/login/login.php"
                    class="block text-center text-xs text-secondary hover:underline"
                >
                    Volver al login
                </a>
            </form>
        </div>
    </div>
</body>
</html>
