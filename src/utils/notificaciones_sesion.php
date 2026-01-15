<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Forzar salida JSON limpia
ob_clean(); 
header('Content-Type: application/json');

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if ($accion === 'contar') {
    $no_leidas = $_SESSION['notificaciones_no_leidas'] ?? 0;
    echo json_encode(['success' => true, 'no_leidas' => $no_leidas]);
    exit;
}
class NotificacionSesion {
    
    private static function inicializarSesion() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['notificaciones'])) {
            $_SESSION['notificaciones'] = [];
        }
        
        if (!isset($_SESSION['notificaciones_no_leidas'])) {
            $_SESSION['notificaciones_no_leidas'] = 0;
        }
        
        if (!isset($_SESSION['usuarios_administradores'])) {
            // Definir qué usuarios son administradores
            $_SESSION['usuarios_administradores'] = [2, 4]; // IDs de coordinadores
        }
    }
    
    public static function agregarCambioDatosSensibles($usuarioId, $usuarioAfectadoId, $datosCambiados, $usuarioNombre = null) {
        self::inicializarSesion();
        
        $usuarioNombre = $usuarioNombre ?: "Usuario ID $usuarioId";
        
        $detallesTexto = "";
        $detallesTexto .= " <strong>Usuario Solicitante:</strong> $usuarioNombre \n";
        
        if ($usuarioId != $usuarioAfectadoId) {
            $detallesTexto .= " <strong>Usuario Afectado:</strong> ID $usuarioAfectadoId\n";
        }
        
        $detallesTexto .= "\n <strong>Cambios Solicitados:</strong>\n";
        
        foreach ($datosCambiados as $campo => $valores) {
            $campoLabel = match($campo) {
                'nombre_completo' => 'Nombre Completo',
                'tipo_documento' => 'Tipo de Documento',
                'numero_documento' => 'Número de Documento',
                'correo' => 'Correo Electrónico',
                default => ucfirst(str_replace('_', ' ', $campo))
            };
            
            $detallesTexto .= "• <strong>$campoLabel</strong>\n";
            $detallesTexto .= "  Nuevo: " . htmlspecialchars($valores['nuevo'] ?? 'No especificado') . "\n\n";
        }
        
        $detallesTexto .= " <strong>Estado:</strong> Pendiente de revisión";
        
        $notificacion = [
            'id' => uniqid('notif_', true),
            'tipo' => 'cambio_datos_sensibles',
            'titulo' => 'Solicitud de Cambio de Datos Sensibles',
            'descripcion' => $detallesTexto,
            'usuario_id' => $usuarioId,
            'usuario_nombre' => $usuarioNombre,
            'usuario_afectado_id' => $usuarioAfectadoId,
            'fecha' => date('Y-m-d H:i:s'),
            'leido' => false,
            'icono' => 'shield-alert',
            'color' => 'warning',
            'importante' => true
        ];
        
        // Agregar una única notificación a la sesión
        $_SESSION['notificaciones'][] = $notificacion;
        $_SESSION['notificaciones_no_leidas']++;
        
        return $notificacion['id'];
    }
    
    public static function obtenerNotificaciones($filtroTipo = null, $limite = 20) {
        self::inicializarSesion();
        
        $notificaciones = $_SESSION['notificaciones'] ?? [];
        
        if ($filtroTipo) {
            $notificaciones = array_filter($notificaciones, function($notif) use ($filtroTipo) {
                return $notif['tipo'] === $filtroTipo;
            });
        }
        
        // Ordenar por fecha (más reciente primero)
        usort($notificaciones, function($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });
        
        return array_slice($notificaciones, 0, $limite);
    }
    
    public static function contarNoLeidas() {
        self::inicializarSesion();
        return $_SESSION['notificaciones_no_leidas'] ?? 0;
    }
    
    public static function marcarComoLeido($notificacionId) {
        self::inicializarSesion();
        
        $marcadas = 0;
        foreach ($_SESSION['notificaciones'] as &$notif) {
            if ($notif['id'] === $notificacionId && !$notif['leido']) {
                $notif['leido'] = true;
                $marcadas++;
            }
        }
        
        if ($marcadas > 0) {
            $_SESSION['notificaciones_no_leidas'] = max(0, $_SESSION['notificaciones_no_leidas'] - $marcadas);
        }
        
        return $marcadas > 0;
    }
    
    public static function marcarTodasComoLeidas() {
        self::inicializarSesion();
        
        $marcadas = 0;
        foreach ($_SESSION['notificaciones'] as &$notif) {
            if (!$notif['leido']) {
                $notif['leido'] = true;
                $marcadas++;
            }
        }
        
        $_SESSION['notificaciones_no_leidas'] = 0;
        return $marcadas;
    }
    
    public static function eliminarNotificacion($notificacionId) {
        self::inicializarSesion();
        
        $countBefore = count($_SESSION['notificaciones'] ?? []);
        
        $_SESSION['notificaciones'] = array_filter(
            $_SESSION['notificaciones'],
            function($notif) use ($notificacionId) {
                return $notif['id'] !== $notificacionId;
            }
        );
        
        $countAfter = count($_SESSION['notificaciones']);
        $eliminadas = $countBefore - $countAfter;
        
        // Recalcular no leídas
        $_SESSION['notificaciones_no_leidas'] = array_reduce(
            $_SESSION['notificaciones'],
            function($carry, $item) {
                return $carry + ($item['leido'] ? 0 : 1);
            },
            0
        );
        
        return $eliminadas > 0;
    }
    
    public static function obtenerResumen() {
        self::inicializarSesion();
        
        $notificaciones = $_SESSION['notificaciones'] ?? [];
        $total = count($notificaciones);
        $noLeidas = self::contarNoLeidas();
        
        $porTipo = [];
        foreach ($notificaciones as $notif) {
            $tipo = $notif['tipo'];
            $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + 1;
        }
        
        return [
            'total' => $total,
            'no_leidas' => $noLeidas,
            'por_tipo' => $porTipo
        ];
    }
}
?>