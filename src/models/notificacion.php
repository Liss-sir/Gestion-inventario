<?php

class NotificacionModel {

    private $db;

    public function __construct(PDO $conn)
    {
        $this->db = $conn;
    }

    // =========================
    // CREAR NOTIFICACIÓN (CORREGIDO)
    // =========================
    public function crear($data)
    {
        $sql = "INSERT INTO notificaciones (
                    id_usuario,
                    tipo,
                    titulo,
                    mensaje,
                    referencia_tipo,
                    referencia_id,
                    leida,
                    fecha_creacion,
                    datos_adicionales
                ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), ?)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $data['id_usuario'] ?? null,
            $data['tipo'] ?? 'SOLICITUD_CREADA',
            $data['titulo'] ?? '',
            $data['mensaje'] ?? '',
            $data['referencia_tipo'] ?? null,
            $data['referencia_id'] ?? null,
            json_encode($data['datos_adicionales'] ?? [])
        ]);
    }

    // =========================
    // OBTENER NOTIFICACIONES DEL USUARIO (CON FILTRO DE TIPO)
    // =========================
    public function getByUsuario($id_usuario, $excluirTipos = [])
    {
        $whereClause = "WHERE n.id_usuario = ?";
        $params = [$id_usuario];
        
        if (!empty($excluirTipos)) {
            $placeholders = implode(',', array_fill(0, count($excluirTipos), '?'));
            $whereClause .= " AND n.tipo NOT IN ($placeholders)";
            $params = array_merge($params, $excluirTipos);
        }
        
        $sql = "SELECT 
                    n.*,
                    u.nombre_completo,
                    u.cargo
                FROM notificaciones n
                INNER JOIN usuarios u ON n.id_usuario = u.id_usuario
                $whereClause
                ORDER BY n.fecha_creacion DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================
    // OBTENER TODAS LAS NOTIFICACIONES (CORREGIDO)
    // =========================
    public function getTodasNotificaciones($limit = 100)
    {
        $sql = "SELECT 
                    n.*,
                    u.nombre_completo,
                    u.cargo,
                    u.foto_perfil
                FROM notificaciones n
                INNER JOIN usuarios u ON n.id_usuario = u.id_usuario
                ORDER BY n.fecha_creacion DESC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================
    // OBTENER NOTIFICACIONES PARA COORDINADOR (INCLUYE CAMBIO_DATOS)
    // =========================
    public function getParaCoordinador($limit = 100)
    {
        // Coordinador ve todas las notificaciones importantes
        $sql = "SELECT 
                    n.*,
                    u.nombre_completo,
                    u.cargo,
                    u.foto_perfil
                FROM notificaciones n
                INNER JOIN usuarios u ON n.id_usuario = u.id_usuario
                WHERE n.tipo IN ('SOLICITUD_CREADA', 'SOLICITUD_APROBADA', 'SOLICITUD_RECHAZADA', 
                               'STOCK_BAJO', 'CAMBIO_DATOS')
                ORDER BY n.fecha_creacion DESC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================
    // OBTENER NOTIFICACIONES POR TIPO (NUEVO MÉTODO)
    // =========================
    public function getByTipo($tipo, $id_usuario = null, $limit = 50)
    {
        $params = [$tipo];
        $whereClause = "WHERE n.tipo = ?";
        
        if ($id_usuario) {
            $whereClause .= " AND n.id_usuario = ?";
            $params[] = $id_usuario;
        }
        
        $sql = "SELECT 
                    n.*,
                    u.nombre_completo,
                    u.cargo
                FROM notificaciones n
                INNER JOIN usuarios u ON n.id_usuario = u.id_usuario
                $whereClause
                ORDER BY n.fecha_creacion DESC
                LIMIT ?";
        
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================
    // ELIMINAR NOTIFICACIÓN (CON VERIFICACIÓN DE TIPO)
    // =========================
    public function eliminarPorId($id_notificacion, $id_usuario, $esCoordinador = false)
    {
        // Primero verificar permisos
        $sql = "SELECT id_notificacion, tipo 
                FROM notificaciones 
                WHERE id_notificacion = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_notificacion]);
        $notificacion = $stmt->fetch();
        
        if (!$notificacion) {
            return false;
        }
        
        // Si no es coordinador, verificar que la notificación pertenece al usuario
        if (!$esCoordinador) {
            $sql = "SELECT id_notificacion 
                    FROM notificaciones 
                    WHERE id_notificacion = ? AND id_usuario = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id_notificacion, $id_usuario]);
            
            if (!$stmt->fetch()) {
                return false;
            }
            
            // Usuarios no coordinadores NO pueden eliminar notificaciones de CAMBIO_DATOS
            if ($notificacion['tipo'] === 'CAMBIO_DATOS') {
                return false;
            }
        }
        
        // Eliminar
        $sql = "DELETE FROM notificaciones WHERE id_notificacion = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id_notificacion]);
    }

    // =========================
    // CONTAR NO LEÍDAS (CON FILTRO POR ROL)
    // =========================
    public function contarNoLeidas($id_usuario = null, $esCoordinador = false)
    {
        if ($id_usuario && !$esCoordinador) {
            // Usuario normal: cuenta sus notificaciones excluyendo CAMBIO_DATOS
            $sql = "SELECT COUNT(*) AS total
                    FROM notificaciones
                    WHERE id_usuario = ?
                      AND leida = 0
                      AND tipo != 'CAMBIO_DATOS'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id_usuario]);
        } elseif ($esCoordinador) {
            // Coordinador: cuenta todas las no leídas
            $sql = "SELECT COUNT(*) AS total
                    FROM notificaciones
                    WHERE leida = 0";
            
            $stmt = $this->db->query($sql);
        } else {
            // Sin usuario: cuenta todas excluyendo CAMBIO_DATOS
            $sql = "SELECT COUNT(*) AS total
                    FROM notificaciones
                    WHERE leida = 0
                      AND tipo != 'CAMBIO_DATOS'";
            
            $stmt = $this->db->query($sql);
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['total'] : 0;
    }

    // =========================
    // MARCAR UNA COMO LEÍDA (CON VERIFICACIÓN DE PERMISOS)
    // =========================
    public function marcarLeida($id_notificacion, $id_usuario = null, $esCoordinador = false)
    {
        // Verificar permisos primero
        $sql = "SELECT id_notificacion, tipo 
                FROM notificaciones 
                WHERE id_notificacion = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_notificacion]);
        $notificacion = $stmt->fetch();
        
        if (!$notificacion) {
            return false;
        }
        
        // Si no es coordinador, verificar que la notificación pertenece al usuario
        if (!$esCoordinador) {
            $sql = "SELECT id_notificacion 
                    FROM notificaciones 
                    WHERE id_notificacion = ? AND id_usuario = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id_notificacion, $id_usuario]);
            
            if (!$stmt->fetch()) {
                return false;
            }
            
            // Usuarios no coordinadores NO pueden marcar como leídas notificaciones de CAMBIO_DATOS
            if ($notificacion['tipo'] === 'CAMBIO_DATOS') {
                return false;
            }
        }
        
        $sql = "UPDATE notificaciones SET leida = 1 WHERE id_notificacion = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id_notificacion]);
    }

    // =========================
    // MARCAR TODAS COMO LEÍDAS (CON FILTRO POR ROL)
    // =========================
    public function marcarTodasLeidas($id_usuario, $esCoordinador = false)
    {
        if ($esCoordinador) {
            $sql = "UPDATE notificaciones SET leida = 1";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute();
        } else {
            // Usuarios normales solo marcan las suyas, excluyendo CAMBIO_DATOS
            $sql = "UPDATE notificaciones 
                    SET leida = 1 
                    WHERE id_usuario = ? AND tipo != 'CAMBIO_DATOS'";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$id_usuario]);
        }
    }

    // =========================
    // OBTENER ESTADÍSTICAS (DIFERENCIADAS POR ROL)
    // =========================
    public function obtenerEstadisticas($id_usuario = null, $esCoordinador = false)
    {
        if ($esCoordinador) {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas,
                        SUM(CASE WHEN tipo = 'STOCK_BAJO' THEN 1 ELSE 0 END) as stock_bajo,
                        SUM(CASE WHEN tipo = 'CAMBIO_DATOS' THEN 1 ELSE 0 END) as cambios_datos,
                        SUM(CASE WHEN tipo LIKE 'SOLICITUD_%' THEN 1 ELSE 0 END) as solicitudes
                    FROM notificaciones";
            
            $stmt = $this->db->query($sql);
        } else {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas,
                        SUM(CASE WHEN tipo = 'SOLICITUD_APROBADA' THEN 1 ELSE 0 END) as aprobadas,
                        SUM(CASE WHEN tipo = 'SOLICITUD_RECHAZADA' THEN 1 ELSE 0 END) as rechazadas,
                        SUM(CASE WHEN tipo = 'MATERIAL_ENTREGADO' THEN 1 ELSE 0 END) as entregadas,
                        0 as cambios_datos  -- No muestra cambios para usuarios normales
                    FROM notificaciones
                    WHERE id_usuario = ? AND tipo != 'CAMBIO_DATOS'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id_usuario]);
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? [
            'total' => (int)$result['total'],
            'no_leidas' => (int)$result['no_leidas'],
            'stock_bajo' => (int)($result['stock_bajo'] ?? 0),
            'cambios_datos' => (int)($result['cambios_datos'] ?? 0),
            'solicitudes' => (int)($result['solicitudes'] ?? 0),
            'aprobadas' => (int)($result['aprobadas'] ?? 0),
            'rechazadas' => (int)($result['rechazadas'] ?? 0),
            'entregadas' => (int)($result['entregadas'] ?? 0)
        ] : [
            'total' => 0,
            'no_leidas' => 0,
            'stock_bajo' => 0,
            'cambios_datos' => 0,
            'solicitudes' => 0,
            'aprobadas' => 0,
            'rechazadas' => 0,
            'entregadas' => 0
        ];
    }

    // =========================
    // OBTENER NOTIFICACIÓN POR ID
    // =========================
    public function obtenerPorId($id_notificacion)
    {
        $sql = "SELECT 
                    n.*,
                    u.nombre_completo,
                    u.cargo,
                    u.foto_perfil
                FROM notificaciones n
                INNER JOIN usuarios u ON n.id_usuario = u.id_usuario
                WHERE n.id_notificacion = ?
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_notificacion]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // =========================
    // OBTENER ÚLTIMAS NOTIFICACIONES (CON FILTRO POR ROL)
    // =========================
    public function obtenerUltimas($limit = 10, $id_usuario = null, $esCoordinador = false)
    {
        if ($id_usuario && !$esCoordinador) {
            // Usuario normal: sus notificaciones excluyendo CAMBIO_DATOS
            $sql = "SELECT 
                        n.*,
                        u.nombre_completo,
                        u.cargo
                    FROM notificaciones n
                    INNER JOIN usuarios u ON n.id_usuario = u.id_usuario
                    WHERE n.id_usuario = ? AND n.tipo != 'CAMBIO_DATOS'
                    ORDER BY n.fecha_creacion DESC
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id_usuario, $limit]);
        } elseif ($esCoordinador) {
            // Coordinador: todas las notificaciones
            $sql = "SELECT 
                        n.*,
                        u.nombre_completo,
                        u.cargo,
                        u.foto_perfil
                    FROM notificaciones n
                    INNER JOIN usuarios u ON n.id_usuario = u.id_usuario
                    ORDER BY n.fecha_creacion DESC
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
        } else {
            // Sin usuario: todas excluyendo CAMBIO_DATOS
            $sql = "SELECT 
                        n.*,
                        u.nombre_completo,
                        u.cargo
                    FROM notificaciones n
                    INNER JOIN usuarios u ON n.id_usuario = u.id_usuario
                    WHERE n.tipo != 'CAMBIO_DATOS'
                    ORDER BY n.fecha_creacion DESC
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================
    // LIMPIAR NOTIFICACIONES ANTIGUAS
    // =========================
    public function limpiarAntiguas($dias = 30)
    {
        $sql = "DELETE FROM notificaciones
                WHERE fecha_creacion < DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND leida = 1";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$dias]);
    }

    // =========================
    // CREAR NOTIFICACIÓN DE SOLICITUD
    // =========================
    public function crearNotificacionSolicitud($id_usuario, $id_solicitud, $tipo, $titulo, $mensaje)
    {
        $data = [
            'id_usuario' => $id_usuario,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'referencia_tipo' => 'solicitud',
            'referencia_id' => $id_solicitud
        ];
           
        return $this->crear($data);
    }

    // =========================
    // CREAR NOTIFICACIÓN DE CAMBIO DE DATOS (NUEVO MÉTODO)
    // =========================
    public function crearNotificacionCambioDatos($id_usuario_solicitante, $id_usuario_afectado, $datosCambiados, $usuarioNombre = null)
    {
        $titulo = 'Solicitud de Cambio de Datos Sensibles';
        
        $mensaje = "Usuario solicitante: " . ($usuarioNombre ?: "ID $id_usuario_solicitante") . "\n";
        if ($id_usuario_solicitante != $id_usuario_afectado) {
            $mensaje .= "Usuario afectado: ID $id_usuario_afectado\n";
        }
        
        $mensaje .= "\nCambios solicitados:\n";
        foreach ($datosCambiados as $campo => $valores) {
            $campoLabel = match($campo) {
                'nombre_completo' => 'Nombre Completo',
                'tipo_documento' => 'Tipo de Documento',
                'numero_documento' => 'Número de Documento',
                'correo' => 'Correo Electrónico',
                default => ucfirst(str_replace('_', ' ', $campo))
            };
            
            $mensaje .= "• $campoLabel\n";
            $mensaje .= "  Nuevo: " . ($valores['nuevo'] ?? 'No especificado') . "\n\n";
        }
        
        $mensaje .= "Estado: Pendiente de revisión por coordinador";
        
        $data = [
            'id_usuario' => $id_usuario_solicitante,
            'tipo' => 'CAMBIO_DATOS',
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'referencia_tipo' => 'cambio_datos',
            'referencia_id' => $id_usuario_afectado,
            'datos_adicionales' => [
                'usuario_solicitante_id' => $id_usuario_solicitante,
                'usuario_afectado_id' => $id_usuario_afectado,
                'datos_cambiados' => $datosCambiados,
                'usuario_nombre' => $usuarioNombre,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        return $this->crear($data);
    }
}