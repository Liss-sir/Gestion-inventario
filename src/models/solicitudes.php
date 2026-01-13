<?php

class SolicitudMaterialModel {

    private $db;

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
                (id_usuario_solicitante, id_ficha, id_actividad, id_rae, id_programa)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['id_usuario'],
            $data['id_ficha'],
            $data['id_actividad'],
            $data['id_rae'],
            $data['id_programa']
        ]);

        return $this->db->lastInsertId();
    }

    // Add details
    public function addDetalle($idSolicitud, $materiales)
    {
        $sql = "INSERT INTO solicitudes_detalle
                (id_solicitud, id_material, cantidad)
                VALUES (?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        foreach ($materiales as $mat) {
            $stmt->execute([
                $idSolicitud,
                $mat['id_material'],
                $mat['cantidad']
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
                    sm.id_usuario_solicitante,
                    sm.id_usuario_aprobador,
                    sm.fecha_respuesta,
                    sm.id_ficha,
                    sm.id_actividad,
                    sm.id_rae,
                    sm.id_programa
                FROM solicitudes_material sm
                ORDER BY sm.fecha_solicitud DESC";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // Approve or reject request
    public function responderSolicitud($idSolicitud, $estado, $idAprobador, $observaciones = null)
    {
        // Only valid states
        if (!in_array($estado, ['Aprobada', 'Rechazada'])) {
            return false;
        }

        $sql = "UPDATE solicitudes_material
                SET estado = ?,
                    id_usuario_aprobador = ?,
                    fecha_respuesta = NOW(),
                    observaciones = ?
                WHERE id_solicitud = ?
                AND estado = 'Pendiente'";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $estado,
            $idAprobador,
            $observaciones,
            $idSolicitud
        ]);
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
                    sd.id_detalle,
                    sd.id_material,
                    mf.nombre AS material,
                    sd.cantidad,
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

}
