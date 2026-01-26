<?php
// =====================================
// LOGIN PAGE — VERSIÓN PHP SIN REACT
// =====================================

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../Config/database.php';

// Si no tienes BASE_URL definida en otro sitio, la calculamos aquí
if (!defined('BASE_URL')) {
    $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host       = $_SERVER['HTTP_HOST'];
    $script_dir = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    define('BASE_URL', $protocol . $host . $script_dir); // ej: .../src/view/login/
}

/**
 * ============================================================
 * ✅ NUEVO: Helper para decidir redirección inicial
 * - Si el usuario es "Aprendiz" → obras
 * - Si no → dashboard
 * ============================================================
 */
function getRedirectPageByRole(): string
{
    $cargo = strtolower(trim((string)($_SESSION['cargo'] ?? $_SESSION['usuario_cargo'] ?? '')));
    $rolFuncional = strtolower(trim((string)($_SESSION['rol_funcional'] ?? $_SESSION['usuario_rol_funcional'] ?? '')));

    $esAprendiz = (strpos($cargo, 'aprendiz') !== false) || (strpos($rolFuncional, 'aprendiz') !== false);

    return $esAprendiz ? 'obras' : 'dashboard';
}

// Si ya está logueado, mandarlo a su inicio según rol
if (isset($_SESSION['usuario_id'])) {
    $redirectPage = getRedirectPageByRole();
    header('Location: ' . BASE_URL . '../../../index.php?page=' . $redirectPage);
    exit;
}

$loginError = "";

// ===============================
// ✅ Mostrar mensaje si viene por reason (timeout, revoked, etc.)
// ===============================
$reason = $_GET['reason'] ?? '';

if ($reason === 'idle_timeout') {
    $loginError = "Tu sesión expiró por inactividad. Inicia sesión nuevamente.";
} elseif ($reason === 'session_revoked') {
    $loginError = "Tu sesión fue cerrada porque se inició sesión desde otro dispositivo.";
} elseif ($reason === 'disabled') {
    $loginError = "Tu cuenta está desactivada. Contacta al administrador.";
} elseif ($reason === 'no_session') {
    $loginError = "Debes iniciar sesión para continuar.";
} elseif ($reason === 'no_token') {
    $loginError = "Tu sesión no es válida. Inicia sesión nuevamente.";
}

// =======================================================
// ✅ DETECTAR ROL FUNCIONAL DESDE TABLAS RELACIONADAS
// - usuario_roles_funcionales
// - roles_funcionales
// SIN asumir columnas como rf.slug (porque no existe)
// =======================================================
$rolFuncSelectSQL = "NULL AS rol_funcional"; // fallback

try {
    // 1) Verificar si existen las tablas
    $t1 = $conn->query("SHOW TABLES LIKE 'usuario_roles_funcionales'")->fetchColumn();
    $t2 = $conn->query("SHOW TABLES LIKE 'roles_funcionales'")->fetchColumn();

    if ($t1 && $t2) {
        // 2) Detectar columna correcta en roles_funcionales
        $colsStmt = $conn->query("SHOW COLUMNS FROM roles_funcionales");
        $cols = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
        $fields = array_map(fn($c) => $c['Field'], $cols);

        // ✅ posibles nombres de columna que guardan el texto del rol
        $candidatas = ['slug', 'nombre', 'rol', 'nombre_rol', 'titulo', 'descripcion'];

        $rolCol = null;
        foreach ($candidatas as $cand) {
            if (in_array($cand, $fields, true)) {
                $rolCol = $cand;
                break;
            }
        }

        if ($rolCol) {
            // ✅ usamos SOLO la columna que exista
            $rolFuncSelectSQL = "COALESCE(rf.`$rolCol`, 'Sin rol asignado') AS rol_funcional";
        } else {
            // si no hay ninguna columna usable
            $rolFuncSelectSQL = "'Sin rol asignado' AS rol_funcional";
        }
    }
} catch (Throwable $e) {
    // si algo falla, seguimos con NULL AS rol_funcional
    $rolFuncSelectSQL = "NULL AS rol_funcional";
}

// ------------------------
// PROCESAR LOGIN (POST)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $loginError = "Por favor ingresa tu correo y contraseña.";
    } else {
        try {
            // ✅ SELECT robusto: usuarios + rol funcional por relación
            $sql = "SELECT 
                        u.id_usuario,
                        u.nombre_completo,
                        u.tipo_documento,
                        u.numero_documento,
                        u.telefono,
                        u.direccion,
                        u.fecha_creacion,
                        u.cargo,
                        u.correo,
                        u.estado,
                        u.password,
                        u.foto_perfil,

                        $rolFuncSelectSQL

                    FROM usuarios u
                    LEFT JOIN usuario_roles_funcionales urf 
                        ON urf.id_usuario = u.id_usuario
                    LEFT JOIN roles_funcionales rf
                        ON rf.id_rol = urf.id_rol

                    WHERE u.correo = :correo
                    ORDER BY urf.fecha_asignacion DESC
                    LIMIT 1";

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':correo', $email, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $hash = $user['password'];

                $passwordOk = false;

                if (password_verify($password, $hash)) {
                    $passwordOk = true;
                } else {
                    if ($password === $hash) {
                        $passwordOk = true;
                    }
                }

                if ($passwordOk) {

                    // =========================================================
                    // ✅ BLOQUEAR LOGIN SI EL USUARIO ESTÁ INACTIVO
                    // =========================================================
                    $rawEstado = $user['estado'] ?? null;
                    $valEstado = strtolower(trim((string)$rawEstado));
                    $estadoActivo = ($valEstado === 'activo' || $valEstado === '1' || $valEstado === 'true');

                    if (!$estadoActivo) {
                        $loginError = "Tu cuenta está desactivada. Contacta al administrador.";
                    } else {

                        // =========================================================
                        // ✅ CERRAR SESIONES PEGADAS SIN TOCAR LA DB
                        // =========================================================
                        try {
                            $stmtCloseOld = $conn->prepare("
                                UPDATE sesiones_usuarios
                                SET activa = 0
                                WHERE id_usuario = :id
                                  AND activa = 1
                            ");
                            $stmtCloseOld->execute([
                                ':id' => (int)$user['id_usuario']
                            ]);
                        } catch (Throwable $e) {
                            // No romper el login si falla el cierre
                        }

                        // CREAR SESIÓN ÚNICA EN BD
                        $tokenSesion = bin2hex(random_bytes(32));

                        $stmtCreate = $conn->prepare("
                            INSERT INTO sesiones_usuarios (id_usuario, token_sesion)
                            VALUES (:id_usuario, :token)
                        ");
                        $stmtCreate->execute([
                            ':id_usuario' => (int)$user['id_usuario'],
                            ':token'      => $tokenSesion
                        ]);

                        $_SESSION['token_sesion'] = $tokenSesion;

                        // ============================
                        // ✅ GUARDAR TODOS LOS DATOS EN SESIÓN
                        // ============================
                        $_SESSION['usuario_id']                = $user['id_usuario'];
                        $_SESSION['usuario_nombre']            = $user['nombre_completo'];
                        $_SESSION['usuario_cargo']             = $user['cargo'];

                        // ✅ Rol funcional desde relación
                        $_SESSION['usuario_rol_funcional']     = $user['rol_funcional'] ?? 'Sin rol asignado';

                        // ✅ Alias cortos (NO rompe nada)
                        $_SESSION['cargo']                     = $user['cargo'];
                        $_SESSION['rol_funcional']             = $user['rol_funcional'] ?? 'Sin rol asignado';

                        $_SESSION['usuario_tipo_documento']    = $user['tipo_documento'];
                        $_SESSION['usuario_numero_documento']  = $user['numero_documento'];
                        $_SESSION['usuario_telefono']          = $user['telefono'];
                        $_SESSION['usuario_correo']            = $user['correo'];
                        $_SESSION['usuario_estado']            = $user['estado'];

                        $_SESSION['usuario_direccion']         = $user['direccion'];
                        $_SESSION['usuario_fecha_creacion']    = $user['fecha_creacion'];

                        $_SESSION['usuario_foto']              = $user['foto_perfil'] ?? null;

                        // =========================================================
                        // ✅ TIMEOUT 15 min
                        // =========================================================
                        $_SESSION['LAST_ACTIVITY'] = time();

                        // =========================================================
                        // ✅ DETECTAR "CAMBIO OBLIGATORIO" (FORCE_%)
                        // =========================================================
                        try {
                            $stmtForce = $conn->prepare("
                                SELECT id_token
                                FROM tokens_correo
                                WHERE id_usuario = :uid
                                  AND tipo = 'reset_password'
                                  AND token LIKE 'FORCE_%'
                                  AND usado = 0
                                  AND fecha_expiracion >= NOW()
                                ORDER BY id_token DESC
                                LIMIT 1
                            ");
                            $stmtForce->execute([':uid' => (int)$user['id_usuario']]);
                            $rowForce = $stmtForce->fetch(PDO::FETCH_ASSOC);

                            $_SESSION['force_password_change'] = $rowForce ? 1 : 0;
                        } catch (Exception $e) {
                            $_SESSION['force_password_change'] = 0;
                        }

                        if (!empty($_SESSION['force_password_change']) && (int)$_SESSION['force_password_change'] === 1) {
                            header('Location: ' . BASE_URL . '../../../index.php?page=dashboard&force_pwd=1');
                            exit;
                        }

                        /**
                         * ============================================================
                         * ✅ NUEVO: Redirección según rol (Aprendiz → obras)
                         * ============================================================
                         */
                        $redirectPage = getRedirectPageByRole();

                        header('Location: ' . BASE_URL . '../../../index.php?page=' . $redirectPage);
                        exit;

                    } // fin else estado activo

                } else {
                    $loginError = "Credenciales incorrectas. Verifica tu correo y contraseña.";
                }

            } else {
                $loginError = "Credenciales incorrectas. Verifica tu correo y contraseña.";
            }

        } catch (PDOException $e) {
            $loginError = "Error BD: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login SIGA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../../assets/css/globals.css">
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex items-center justify-center bg-background p-4 relative">
  <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-accent/5"></div>

  <div class="relative w-full max-w-md shadow-xl bg-white rounded-xl border border-gray-200 fade-in">
    <div class="space-y-4 text-center pb-2 p-6">
      <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-secondary">
        <img src="../../assets/img/logo-sena-blanco.png" alt="logo sena blanco" class="h-8 w-auto object-contain">
      </div>

      <div>
        <h1 class="text-2xl font-bold">Bienvenido a SIGA</h1>
        <p class="mt-1 text-gray-500">Sistema de Gestión de Almacén</p>
      </div>

      <?php if ($loginError !== ""): ?>
        <p class="mt-2 text-sm text-red-600">
          <?= htmlspecialchars($loginError) ?>
        </p>
      <?php endif; ?>
    </div>

    <div class="pt-4 px-6 pb-6">
      <form id="loginForm" class="space-y-4" method="POST">

        <div class="space-y-2">
          <label for="email" class="text-sm font-medium">Correo electrónico</label>
          <input
            id="email"
            name="email"
            type="email"
            placeholder="usuario@sena.edu.co"
            required
            class="h-11 w-full border rounded-md px-3"
          />
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <label for="password" class="text-sm font-medium">Contraseña</label>

            <a href="<?= BASE_URL ?>recuperar_contrasena.php" class="text-xs text-secondary hover:underline">
              ¿Olvidaste tu contraseña?
            </a>
          </div>

          <div class="relative">
            <input
              id="password"
              name="password"
              type="password"
              placeholder="••••••••"
              required
              class="h-11 pr-10 w-full border rounded-md px-3"
            />

            <button
              type="button"
              id="togglePassword"
              class="absolute right-0 top-0 h-11 w-11 flex items-center justify-center hover:bg-transparent"
            >
              <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" />
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <button
          type="submit"
          id="btnLogin"
          class="w-full h-11 bg-secondary text-white rounded-md flex items-center justify-center"
        >
          <span id="btnText">Iniciar sesión</span>

          <svg
            id="loaderIcon"
            class="hidden ml-2 h-4 w-4 animate-spin"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
            <path d="M4 12a8 8 0 018-8" />
          </svg>
        </button>

      </form>
    </div>
  </div>

  <script>
    const togglePasswordBtn = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("password");
    const eyeIcon = document.getElementById("eyeIcon");

    togglePasswordBtn.addEventListener("click", () => {
      const isText = passwordInput.type === "text";
      passwordInput.type = isText ? "password" : "text";

      eyeIcon.innerHTML = isText
        ? `<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>`
        : `<path d="M13.875 18.825A10.05 10.05 0 0112 19c-7 0-11-7-11-7a19.207 19.207 0 015.677-5.48m3.461-.762A11.413 11.413 0 0112 5c7 0 11 7 11 7a20.626 20.626 0 01-2.364 3.442M3 3l18 18"/>`;
    });

    document.getElementById("loginForm").addEventListener("submit", function () {
      const btn = document.getElementById("btnLogin");
      const loader = document.getElementById("loaderIcon");
      const text = document.getElementById("btnText");

      btn.disabled = true;
      loader.classList.remove("hidden");
      text.textContent = "Iniciando sesión...";
    });
  </script>

</body>
</html>
