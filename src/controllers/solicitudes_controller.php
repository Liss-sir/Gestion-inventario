<?php
require_once "../Gestion-inventario/Config/database.php";
require_once "../models/solicitudes.php";

class SolicitudMaterialController {

    private $model;

    public function __construct($conn)
    {
        $this->model = new SolicitudMaterialModel($conn);
    }

    // Create request with details
    <?php
require_once "../Gestion-inventario/Config/database.php";
require_once "../models/solicitudes.php";
// Agregar el modelo de notificaciones
require_once "../models/notificacion.php";

class SolicitudMaterialController {

    private $model;
    private $notificacionModel;

    public function __construct($conn)
    {
        $this->model = new SolicitudMaterialModel($conn);
        $this->notificacionModel = new NotificacionModel($conn);
    }

    // Create request with details AND SEND NOTIFICATION
    public function crear($data, $id_usuario_creador = null, $rol_creador = null)
    {
        // Basic validation (business rule)
        if (empty($data['materiales']) || !is_array($data['materiales'])) {
            return [
                "status" => "error",
                "message" => "La solicitud debe contener al menos un material."
            ];
        }

        try {
            $this->model->begin();

            // Crear la solicitud
            $idSolicitud = $this->model->createSolicitudes($data);
            $this->model->addDetalle($idSolicitud, $data['materiales']);

            // ENVIAR NOTIFICACIÓN AL COORDINADOR
            $this->enviarNotificacionCoordinador($idSolicitud, $data, $id_usuario_creador, $rol_creador);

            $this->model->commit();

            return [
                "status" => "success",
                "message" => "Solicitud creada correctamente.",
                "id_solicitud" => $idSolicitud
            ];

        } catch (Exception $e) {
            $this->model->rollback();

            return [
                "status" => "error",
                "message" => "Error al crear la solicitud: " . $e->getMessage()
            ];
        }
    }

    // Método para enviar notificación al coordinador
    private function enviarNotificacionCoordinador($idSolicitud, $dataSolicitud, $id_usuario_creador, $rol_creador)
    {
        try {
            // 1. Obtener ID del coordinador
            $id_coordinador = $this->notificacionModel->obtenerIdCoordinador();
            
            if (!$id_coordinador) {
                error_log("No se encontró coordinador para notificación");
                return;
            }

            // 2. Crear título y mensaje
            $titulo = "Nueva Solicitud de Material #" . $idSolicitud;
            
            // 3. Construir mensaje detallado
            $mensaje = "Se ha creado una nueva solicitud de material.\n\n";
            
            // Información de la solicitud
            if (isset($dataSolicitud['programa'])) {
                $mensaje .= "• Programa: " . $dataSolicitud['programa'] . "\n";
            }
            if (isset($dataSolicitud['ficha'])) {
                $mensaje .= "• Ficha: " . $dataSolicitud['ficha'] . "\n";
            }
            if (isset($dataSolicitud['rae'])) {
                $mensaje .= "• RAE: " . $dataSolicitud['rae'] . "\n";
            }
            
            // Materiales solicitados
            $mensaje .= "\n📦 Materiales solicitados:\n";
            foreach ($dataSolicitud['materiales'] as $index => $material) {
                $mensaje .= "  " . ($index + 1) . ". " . ($material['nombre'] ?? 'Material') . 
                          " - Cantidad: " . ($material['cantidad'] ?? 1) . "\n";
            }
            
            if (isset($dataSolicitud['observaciones']) && !empty($dataSolicitud['observaciones'])) {
                $mensaje .= "\n📝 Observaciones:\n" . $dataSolicitud['observaciones'];
            }

            // 4. Preparar datos para la notificación
            $datosNotificacion = [
                'id_usuario' => $id_coordinador, // Destinatario: coordinador
                'id_usuario_remitente' => $id_usuario_creador, // Quién creó la solicitud
                'tipo' => 'solicitud_material',
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'referencia_tipo' => 'solicitud_material',
                'referencia_id' => $idSolicitud,
                'rol_remitente' => $rol_creador,
                'datos_adicionales' => json_encode([
                    'id_solicitud' => $idSolicitud,
                    'programa' => $dataSolicitud['programa'] ?? '',
                    'ficha' => $dataSolicitud['ficha'] ?? '',
                    'rae' => $dataSolicitud['rae'] ?? '',
                    'materiales' => $dataSolicitud['materiales'],
                    'observaciones' => $dataSolicitud['observaciones'] ?? '',
                    'fecha_solicitud' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE)
            ];

            // 5. Crear la notificación
            $creado = $this->notificacionModel->crear($datosNotificacion);
            
            if ($creado) {
                error_log("✅ Notificación enviada al coordinador para solicitud #" . $idSolicitud);
                
                // 6. También crear confirmación para el usuario que envió
                if ($id_usuario_creador) {
                    $this->notificacionModel->crearConfirmacionUsuario(
                        $id_usuario_creador,
                        "Solicitud de material #" . $idSolicitud . " enviada",
                        "Tu solicitud ha sido enviada al coordinador para revisión"
                    );
                }
            } else {
                error_log("❌ Error al crear notificación para solicitud #" . $idSolicitud);
            }

        } catch (Exception $e) {
            error_log("Error en enviarNotificacionCoordinador: " . $e->getMessage());
        }
    }

    // También modificar el método responder para notificar al usuario
    public function responder($data)
    {
        $ok = $this->model->responderSolicitud(
            $data['id_solicitud'],
            $data['estado'],
            $data['id_usuario_aprobador'],
            $data['observaciones'] ?? null
        );
    
        if (!$ok) {
            return [
                "status" => "error",
                "message" => "No se pudo responder la solicitud. Verifique su estado."
            ];
        }
        
        // OBTENER INFO DE LA SOLICITUD PARA NOTIFICAR AL USUARIO
        $solicitud = $this->model->getById($data['id_solicitud']);
        if ($solicitud && isset($solicitud['id_usuario'])) {
            $this->enviarNotificacionRespuesta(
                $data['id_solicitud'],
                $solicitud['id_usuario'],
                $data['estado'],
                $data['observaciones'] ?? null
            );
        }
    
        return [
            "status" => "success",
            "message" => "La solicitud fue actualizada correctamente."
        ];
    }
    
    private function enviarNotificacionRespuesta($idSolicitud, $idUsuarioDestino, $estado, $observaciones = null)
    {
        try {
            $estadoTexto = ($estado === 'aprobada') ? 'APROBADA' : 'RECHAZADA';
            $colorEstado = ($estado === 'aprobada') ? '✅' : '❌';
            
            $titulo = "Respuesta a tu solicitud #" . $idSolicitud;
            $mensaje = $colorEstado . " Tu solicitud de material #" . $idSolicitud . 
                      " ha sido " . $estadoTexto . ".\n";
            
            if ($observaciones) {
                $mensaje .= "\n📝 Comentarios:\n" . $observaciones;
            }
            
            $datosNotificacion = [
                'id_usuario' => $idUsuarioDestino,
                'tipo' => 'respuesta_solicitud',
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'referencia_tipo' => 'solicitud_material',
                'referencia_id' => $idSolicitud,
                'datos_adicionales' => json_encode([
                    'id_solicitud' => $idSolicitud,
                    'estado' => $estado,
                    'fecha_respuesta' => date('Y-m-d H:i:s')
                ])
            ];
            
            $this->notificacionModel->crear($datosNotificacion);
            
        } catch (Exception $e) {
            error_log("Error enviando notificación de respuesta: " . $e->getMessage());
        }
    }
    public function crear($data)
    {
        // Basic validation (business rule)
        if (empty($data['materiales']) || !is_array($data['materiales'])) {
            return [
                "status" => "error",
                "message" => "La solicitud debe contener al menos un material."
            ];
        }

        try {
            $this->model->begin();

            $idSolicitud = $this->model->createSolicitudes($data);
            $this->model->addDetalle($idSolicitud, $data['materiales']);

            $this->model->commit();

            return [
                "status" => "success",
                "message" => "Solicitud creada correctamente.",
                "id_solicitud" => $idSolicitud
            ];

        } catch (Exception $e) {
            $this->model->rollback();

            return [
                "status" => "error",
                "message" => "Error al crear la solicitud."
            ];
        }
    }


    // Approve or reject request
    public function responder($data)
    {
        $ok = $this->model->responderSolicitud(
            $data['id_solicitud'],
            $data['estado'],
            $data['id_usuario_aprobador'],
            $data['observaciones'] ?? null
        );
    
        if (!$ok) {
            return [
                "status" => "error",
                "message" => "No se pudo responder la solicitud. Verifique su estado."
            ];
        }
    
        return [
            "status" => "success",
            "message" => "La solicitud fue actualizada correctamente."
        ];
    }


    // Mark request as delivered
    public function entregar($data)
    {
        $ok = $this->model->marcarEntregada(
            $data['id_solicitud'],
            $data['id_usuario']
        );
    
        if (!$ok) {
            return [
                "status" => "error",
                "message" => "La solicitud no puede marcarse como entregada."
            ];
        }
    
        return [
            "status" => "success",
            "message" => "La solicitud fue marcada como entregada correctamente."
        ];
    }



    public function obtener($id)
    {
        return $this->model->getById($id);
    }

    public function obtenerCompleta($id)
    {
        return $this->model->getSolicitudCompleta($id);
    }

    // List all requests
    public function listar()
    {
        return $this->model->getAll();
    }




}

<?php
// ====================
// INICIAR SESIÓN
// ====================
session_start();

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['id_usuario'])) {
    sendJSON([
        "status" => "error",
        "message" => "No autorizado. Inicie sesión."
    ]);
}

// Obtener datos del usuario desde la sesión
$id_usuario = $_SESSION['id_usuario'];
$rol_usuario = $_SESSION['rol'] ?? null;
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

// ====================
// INSTANCIAR CONTROLADOR
// ====================
$controller = new SolicitudMaterialController($conn);

// ====================
// MANEJAR ACCIONES
// ====================
$accion = $_GET['accion'] ?? null;

function sendJSON($data)
{
    header("Content-Type: application/json");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($accion) {

    case "crear":
        $data = json_decode(file_get_contents("php://input"), true);
        // Pasar el ID del usuario que crea la solicitud
        sendJSON($controller->crear($data, $id_usuario, $rol_usuario));
        break;

    case "obtener":
        sendJSON($controller->obtener($_GET['id']));
        break;

    case "responder":
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Verificar que sea coordinador quien responde
        if ($rol_usuario !== 'Coordinador' && $rol_usuario !== 'Administrador') {
            sendJSON([
                "status" => "error",
                "message" => "Solo coordinadores pueden responder solicitudes."
            ]);
        }
        
        // Agregar ID del aprobador desde sesión
        $data['id_usuario_aprobador'] = $id_usuario;
        
        sendJSON($controller->responder($data));
        break;
    
    case "entregar":
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Agregar ID del usuario que entrega
        $data['id_usuario'] = $id_usuario;
        
        sendJSON($controller->entregar($data));
        break;
    
    case "obtenerCompleta":
        sendJSON($controller->obtenerCompleta($_GET['id']));
        break;

    case "listar":
        // Opcional: Filtrar por usuario si no es coordinador
        $resultado = $controller->listar();
        
        // Si no es coordinador, filtrar solo sus solicitudes
        if ($rol_usuario !== 'Coordinador' && $rol_usuario !== 'Administrador') {
            if (isset($resultado['data'])) {
                $resultado['data'] = array_filter($resultado['data'], function($solicitud) use ($id_usuario) {
                    return $solicitud['id_usuario'] == $id_usuario;
                });
                $resultado['data'] = array_values($resultado['data']); // Reindexar
            }
        }
        
        sendJSON($resultado);
        break;

    default:
        sendJSON([
            "status" => "error",
            "message" => "Acción no válida."
        ]);
}