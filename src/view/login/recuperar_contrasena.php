<?php
session_start();

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

// ✅ status esperado: sent | not_found | error
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar contraseña</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">

<div class="bg-white p-6 rounded shadow w-full max-w-md">
    <h2 class="text-xl font-bold mb-4">Olvidé mi contraseña</h2>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'sent'): ?>
    <p class="text-green-700 bg-green-50 border border-green-200 px-3 py-2 rounded text-sm mb-3">
        Hemos enviado un enlace para restablecer tu contraseña. Por favor revisa tu bandeja de entrada y la carpeta de spam/no deseado.
    </p>
    <?php endif; ?>

    <?php if (isset($_GET['err']) && $_GET['err'] === 'not_registered'): ?>
        <p class="text-yellow-700 bg-yellow-50 border border-yellow-200 px-3 py-2 rounded text-sm mb-3">
            El correo ingresado no se encuentra registrado en el sistema. Verifica la dirección e inténtalo nuevamente.
        </p>
    <?php endif; ?>

    <?php if (isset($_GET['err']) && $_GET['err'] === 'correo'): ?>
        <p class="text-red-700 bg-red-50 border border-red-200 px-3 py-2 rounded text-sm mb-3">
            Por favor ingresa un correo electrónico válido.
        </p>
    <?php endif; ?>

    <?php if (isset($_GET['err']) && $_GET['err'] === 'send'): ?>
        <p class="text-red-700 bg-red-50 border border-red-200 px-3 py-2 rounded text-sm mb-3">
            No fue posible enviar el correo de recuperación en este momento. Por favor, inténtalo nuevamente más tarde.
        </p>
    <?php endif; ?>


    <!-- 🔥 FORM CORRECTO -->
    <form method="POST" action="<?= BASE_URL ?>src/controllers/usuario_controller.php?accion=request_reset_password">
        <label class="block text-sm mb-1">Correo electrónico</label>
        <input
            type="email"
            name="correo"
            required
            class="w-full border px-3 py-2 rounded mb-4"
            placeholder="usuario@sena.edu.co">

        <button
            type="submit"
            class="w-full bg-green-700 text-white py-2 rounded">
            Enviar enlace
        </button>
    </form>

    <a href="<?= BASE_URL ?>src/view/login/login.php"
       class="block text-center text-sm text-green-700 mt-4">
        Volver al login
    </a>
</div>

</body>
</html>
