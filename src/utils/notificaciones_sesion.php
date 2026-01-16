<?php
// src/utils/notificaciones_sesion.php

require_once 'notificaciones_sin_db.php';

session_start();

header('Content-Type: application/json');

if (isset($_GET['accion'])) {
    switch ($_GET['accion']) {
        case 'obtener':
            $tipo = $_GET['tipo'] ?? null;
            $limite = $_GET['limite'] ?? 20;
            $notificaciones = NotificacionSesion::obtenerNotificaciones($tipo, $limite);
            echo json_encode($notificaciones);
            break;
            
        case 'contar':
            $resumen = NotificacionSesion::obtenerResumen();
            echo json_encode($resumen);
            break;
            
        case 'resumen':
            echo json_encode(NotificacionSesion::obtenerResumen());
            break;
            
        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    switch ($_POST['accion']) {
        case 'marcar_leido':
            if (isset($_POST['notificacion_id'])) {
                $resultado = NotificacionSesion::marcarComoLeido($_POST['notificacion_id']);
                echo json_encode(['success' => $resultado]);
            }
            break;
            
        case 'marcar_todas_leidas':
            NotificacionSesion::marcarTodasComoLeidas();
            echo json_encode(['success' => true]);
            break;
            
        case 'eliminar':
            if (isset($_POST['notificacion_id'])) {
                NotificacionSesion::eliminarNotificacion($_POST['notificacion_id']);
                echo json_encode(['success' => true]);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
}
?>