<?php
// obtener_detalle_notificacion.php
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Incluir conexión a la base de datos
require_once __DIR__ . '/../../../Config/database.php';

header('Content-Type: application/json');

$id_notificacion = $_GET['id'] ?? 0;

if (empty($id_notificacion)) {
    echo json_encode(['success' => false, 'error' => 'ID de notificación no proporcionado']);
    exit;
}

try {
    // 1. Obtener información básica de la notificación
    $stmtNotif = $conn->prepare("
        SELECT 
            n.*,
            u.nombre_completo as usuario_nombre
        FROM notificaciones n
        LEFT JOIN usuarios u ON n.referencia_id = u.id_usuario
        WHERE n.id_notificacion = ?
        LIMIT 1
    ");
    
    $stmtNotif->execute([$id_notificacion]);
    $notificacion = $stmtNotif->fetch(PDO::FETCH_ASSOC);
    
    if (!$notificacion) {
        echo json_encode(['success' => false, 'error' => 'Notificación no encontrada']);
        exit;
    }
    
    $datos_detallados = [];
    $old_values = null;
    $new_values = null;
    $tabla_audit = null;
    
    // 2. Si es un cambio de datos, buscar en audit_log
    if ($notificacion['tipo'] === 'CAMBIO_DATOS' && !empty($notificacion['referencia_id'])) {
        
        // Buscar en audit_log usando el id_audit
        $stmtAudit = $conn->prepare("
            SELECT al.*
            FROM audit_log al
            WHERE al.id_audit = ? 
            LIMIT 1
        ");
        
        $stmtAudit->execute([$notificacion['referencia_id']]);
        $auditData = $stmtAudit->fetch(PDO::FETCH_ASSOC);
        
        if ($auditData) {
            $old_values = $auditData['old_values'];
            $new_values = $auditData['new_values'];
            $tabla_audit = $auditData['tabla_nombre'];
            
            // Procesar los valores antiguos y nuevos
            if (!empty($old_values) && !empty($new_values)) {
                try {
                    $oldData = json_decode($old_values, true);
                    $newData = json_decode($new_values, true);
                    
                    if (is_array($oldData) && is_array($newData)) {
                        foreach ($oldData as $campo => $valor_anterior) {
                            if (isset($newData[$campo])) {
                                $valor_nuevo = $newData[$campo];
                                
                                // Solo mostrar si hay cambio real
                                if ($valor_anterior != $valor_nuevo) {
                                    $nombreCampo = '';
                                    
                                    // Mapear nombres de campos más legibles
                                    $nombresCampos = [
                                        'id_usuario' => 'ID Usuario',
                                        'nombre_completo' => 'Nombre Completo',
                                        'tipo_documento' => 'Tipo de Documento',
                                        'numero_documento' => 'Número de Documento',
                                        'telefono' => 'Teléfono',
                                        'cargo' => 'Cargo',
                                        'correo' => 'Correo Electrónico',
                                        'foto_perfil' => 'Foto de Perfil',
                                        'direccion' => 'Dirección',
                                        'estado' => 'Estado',
                                        'id_programa' => 'Programa de Formación',
                                        'es_sistema' => 'Es Sistema',
                                        'correo_verificado' => 'Correo Verificado'
                                    ];
                                    
                                    $nombreCampo = $nombresCampos[$campo] ?? ucwords(str_replace('_', ' ', $campo));
                                    
                                    $datos_detallados[$campo] = [
                                        'anterior' => $valor_anterior,
                                        'nuevo' => $valor_nuevo,
                                        'campo_nombre' => $nombreCampo,
                                        'cambio' => true
                                    ];
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error procesando JSON en obtener_detalle: " . $e->getMessage());
                }
            }
            
            // Si también hay detalles en la columna detalle
            if (!empty($auditData['detalle'])) {
                try {
                    $detalleData = json_decode($auditData['detalle'], true);
                    if (is_array($detalleData)) {
                        $datos_detallados = array_merge($datos_detallados, $detalleData);
                    }
                } catch (Exception $e) {
                    // Ignorar si no es JSON válido
                }
            }
        }
        
        // 3. Si no hay datos en audit_log, intentar del mensaje de la notificación
        if (empty($datos_detallados) && !empty($notificacion['mensaje'])) {
            try {
                $mensajeData = json_decode($notificacion['mensaje'], true);
                if (is_array($mensajeData)) {
                    $datos_detallados = $mensajeData;
                } else {
                    $datos_detallados = ['mensaje' => $notificacion['mensaje']];
                }
            } catch (Exception $e) {
                $datos_detallados = ['mensaje' => $notificacion['mensaje']];
            }
        }
    }
    
    // 4. Preparar respuesta JSON
    $response = [
        'success' => true,
        'id' => $notificacion['id_notificacion'],
        'titulo' => $notificacion['titulo'],
        'tipo' => $notificacion['tipo'],
        'usuario_nombre' => $notificacion['usuario_nombre'] ?? 'Sistema',
        'referencia_id' => $notificacion['referencia_id'],
        'referencia_tipo' => $notificacion['referencia_tipo'],
        'descripcion' => $notificacion['mensaje'],
        'fecha_creacion' => $notificacion['fecha_creacion'],
        'fecha_formateada' => date('d/m/Y h:i a', strtotime($notificacion['fecha_creacion'])),
        'leida' => (bool)$notificacion['leida'],
        'datos_detallados' => $datos_detallados,
        'raw_data' => [
            'old_values' => $old_values,
            'new_values' => $new_values,
            'tabla' => $tabla_audit
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error en obtener_detalle_notificacion.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Error interno del servidor'
    ]);
}
?>