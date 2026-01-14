<?php

class SolicitudMaterialModel {

    // 🔥 CAMBIA ESTO: de private a public
    public $db;

    public function __construct(PDO $conn)
    {
        $this->db = $conn;
    }

    // Start transaction
    public function begin()
    {
        $this->db->beginTransaction();
    }

    // Commit transaction
    public function commit()
    {
        $this->db->commit();
    }

    // Rollback transaction
    public function rollback()
    {
        $this->db->rollBack();
    }

    // Create solicitud
    public function createSolicitudes($data)
    {
        $sql = "INSERT INTO solicitudes_material 
                (id_usuario_solicitante, id_ficha, id_actividad, id_rae, id_programa, observaciones)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        
        // Si id_actividad es 0 o no existe, usar NULL
        $id_actividad = !empty($data['id_actividad']) && $data['id_actividad'] > 0 
                        ? $data['id_actividad'] 
                        : null;
        
        $stmt->execute([
            $data['id_usuario'] ?? 1,
            $data['id_ficha'],
            $id_actividad, // ← Puede ser NULL
            $data['id_rae'],
            $data['id_programa'],
            $data['observaciones'] ?? ''
        ]);

        return $this->db->lastInsertId();
    }

    // Add details
    public function addDetalle($idSolicitud, $materiales)
    {
        $sql = "INSERT INTO solicitudes_detalle
                (id_solicitud, id_material, cantidad_solicitada)
                VALUES (?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        foreach ($materiales as $mat) {
            $stmt->execute([
                $idSolicitud,
                $mat['id_material'],
                $mat['cantidad_solicitada'] ?? $mat['cantidad']
            ]);
        }
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM solicitudes_material WHERE id_solicitud = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get all requests
    public function getAll()
    {
        $sql = "SELECT 
                    sm.id_solicitud,
                    sm.fecha_solicitud,
                    sm.estado,
                    sm.observaciones,
                    sm.id_usuario_solicitante,
                    sm.id_usuario_aprobador,
                    sm.fecha_respuesta,
                    sm.id_ficha,
                    f.numero_ficha,
                    f.jornada,
                    sm.id_rae,
                    r.codigo_rae,
                    r.descripcion_rae,
                    sm.id_programa,
                    p.codigo_programa,
                    p.nombre_programa
                FROM solicitudes_material sm
                LEFT JOIN fichas f ON sm.id_ficha = f.id_ficha
                LEFT JOIN raes r ON sm.id_rae = r.id_rae
                LEFT JOIN programas_formacion p ON sm.id_programa = p.id_programa
                ORDER BY sm.fecha_solicitud DESC";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Approve or reject request
    public function responderSolicitud($idSolicitud, $estado, $idAprobador, $observaciones = null)
{
    // Normalizar el estado (aceptar mayúscula o minúscula)
    $estadoNormalizado = ucfirst(strtolower($estado)); // Convierte "aprobada" a "Aprobada"
    
    // Only valid states
    if (!in_array($estadoNormalizado, ['Aprobada', 'Rechazada'])) {
        error_log("❌ Estado no válido recibido: $estado (normalizado a: $estadoNormalizado)");
        return false;
    }

    $sql = "UPDATE solicitudes_material
            SET estado = ?,
                id_usuario_aprobador = ?,
                fecha_respuesta = NOW(),
                observaciones = COALESCE(?, observaciones)
            WHERE id_solicitud = ?
            AND estado = 'Pendiente'";

    $stmt = $this->db->prepare($sql);

    $result = $stmt->execute([
        $estadoNormalizado, // Usar el estado normalizado
        $idAprobador,
        $observaciones,
        $idSolicitud
    ]);
    
    // Agregar logs para depuración
    if ($result) {
        $rows = $stmt->rowCount();
        error_log("✅ Solicitud $idSolicitud actualizada a $estadoNormalizado. Filas afectadas: $rows");
        return $rows > 0;
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("❌ Error al actualizar solicitud $idSolicitud: " . json_encode($errorInfo));
        return false;
    }
}

    // Mark request as delivered
    public function marcarEntregada($idSolicitud, $idUsuario)
    {
        $sql = "UPDATE solicitudes_material
                SET estado = 'Entregada',
                    fecha_respuesta = NOW(),
                    id_usuario_aprobador = ?
                WHERE id_solicitud = ?
                  AND estado = 'Aprobada'";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$idUsuario, $idSolicitud]);
    }

    // Get request details (materials)
    public function getDetalles($idSolicitud)
    {
        $sql = "SELECT 
                    sd.id_solicitud_detalle,
                    sd.id_material,
                    mf.nombre AS material,
                    sd.cantidad_solicitada as cantidad,
                    mf.unidad_medida,
                    mf.clasificacion
                FROM solicitudes_detalle sd
                INNER JOIN material_formacion mf 
                    ON mf.id_material = sd.id_material
                WHERE sd.id_solicitud = ?";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idSolicitud]);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get request with header + details
    public function getSolicitudCompleta($idSolicitud)
    {
        $sql = "SELECT * FROM solicitudes_material WHERE id_solicitud = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idSolicitud]);

        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$solicitud) {
            return null;
        }

        $solicitud['materiales'] = $this->getDetalles($idSolicitud);

        return $solicitud;
    }

    // ============================================
    // NUEVAS FUNCIONES PARA LOS SELECTORES
    // ============================================

    public function getProgramas()
    {
        $sql = "SELECT id_programa, codigo_programa, nombre_programa 
                FROM programas_formacion
                WHERE estado = 'Activo' 
                ORDER BY codigo_programa";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRaesPorPrograma($programaId)
    {
        if ($programaId <= 0) {
            return [];
        }

        $sql = "SELECT id_rae, codigo_rae, descripcion_rae
                FROM raes
                WHERE id_programa = :programa_id
                AND estado = 'Activo'
                ORDER BY codigo_rae";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':programa_id', (int)$programaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFichasPorPrograma($programaId)
    {
        if ($programaId <= 0) {
            return [];
        }

        $sql = "SELECT id_ficha, numero_ficha, jornada 
                FROM fichas 
                WHERE id_programa = :programa_id 
                AND estado = 'Activa' 
                ORDER BY numero_ficha";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':programa_id', $programaId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMateriales()
    {
        $sql = "SELECT 
                    mf.id_material, 
                    mf.nombre, 
                    mf.codigo_inventario,
                    mf.descripcion,
                    mf.unidad_medida,
                    mf.clasificacion,
                    mf.estado,
                    COALESCE(SUM(sb.stock_actual), 0) as stock_actual
                FROM material_formacion mf
                LEFT JOIN stock_bodega sb ON mf.id_material = sb.id_material
                WHERE mf.estado = 'Disponible'
                GROUP BY mf.id_material
                ORDER BY mf.nombre";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>