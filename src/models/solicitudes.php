<?php

class SolicitudMaterialModel
{

    public $db;

    public function __construct(PDO $conn)
    {
        $this->db = $conn;
    }

    public function begin()
    {
        $this->db->beginTransaction();
    }

    public function commit()
    {
        $this->db->commit();
    }

    public function rollback()
    {
        $this->db->rollBack();
    }

    public function createSolicitudes($data)
    {
        $sql = "INSERT INTO solicitudes_material 
                (id_usuario_solicitante, id_ficha, id_actividad, id_rae, id_programa, observaciones)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        $id_actividad = !empty($data['id_actividad']) && $data['id_actividad'] > 0
            ? $data['id_actividad']
            : null;

        $stmt->execute([
            $data['id_usuario'] ?? 1,
            $data['id_ficha'],
            $id_actividad,
            $data['id_rae'],
            $data['id_programa'],
            $data['observaciones'] ?? ''
        ]);

        return $this->db->lastInsertId();
    }

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

    // ================= RESPONDER (YA NO DESCUENTA STOCK) =================
    public function responderSolicitud($idSolicitud, $estado, $idAprobador, $observaciones = null)
    {

        $estadoNormalizado = ucfirst(strtolower($estado));

        if (!in_array($estadoNormalizado, ['Aprobada', 'Rechazada'])) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $sql = "UPDATE solicitudes_material
                    SET estado = ?,
                        id_usuario_aprobador = ?,
                        fecha_respuesta = NOW(),
                        observaciones = COALESCE(?, observaciones)
                    WHERE id_solicitud = ?
                      AND estado = 'Pendiente'";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $estadoNormalizado,
                $idAprobador,
                $observaciones,
                $idSolicitud
            ]);

            $rows = $stmt->rowCount();

            // ❌ YA NO CREA MOVIMIENTO AQUÍ

            $this->db->commit();
            return $rows > 0;

        }
        catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /* ===============================
     CREAR MOVIMIENTO DE SALIDA
     AHORA SOLO SE USA AL ENTREGAR
     =============================== */
    private function crearMovimientoSalidaDeSolicitud($idSolicitud, $idUsuario)
    {
        try {
            $solicitud = $this->getSolicitudCompleta($idSolicitud);

            if (!$solicitud || empty($solicitud['materiales'])) {
                return false;
            }

            $ubicacion = $this->obtenerBodegaDeMaterial($solicitud['materiales'][0]['id_material']);

            if (!$ubicacion || !isset($ubicacion['id_bodega'])) {
                return false;
            }

            $datosMovimiento = [
                'tipo_movimiento' => 'Salida',
                'id_usuario' => $idUsuario,
                'id_bodega' => $ubicacion['id_bodega'],
                'id_subbodega' => $ubicacion['id_subbodega'],
                'id_programa' => $solicitud['id_programa'] ?? null,
                'id_ficha' => $solicitud['id_ficha'] ?? null,
                'id_rae' => $solicitud['id_rae'] ?? null,
                'observaciones' => ($solicitud['observaciones']),
                'id_solicitud' => $idSolicitud,
                'materiales' => []
            ];

            foreach ($solicitud['materiales'] as $material) {
                $datosMovimiento['materiales'][] = [
                    'id_material' => $material['id_material'],
                    'nombre' => $material['material'] ?? 'Material',
                    'cantidad' => $material['cantidad'],
                    'unidad' => $material['unidad_medida'] ?? ''
                ];
            }

            require_once __DIR__ . '/movimiento.php';
            $movimientoModel = new MovimientoModel($this->db);
            $movimientoModel->registrarSalida($datosMovimiento);


            return true;

        }
        catch (Exception $e) {
            throw $e;
        }
    }

    private function obtenerBodegaDeMaterial($idMaterial)
    {
        $sql = "SELECT id_bodega, id_subbodega
            FROM movimientos_material 
            WHERE id_material = ? 
            AND tipo_movimiento = 'Entrada'
            ORDER BY fecha_hora DESC 
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idMaterial]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return [
                'id_bodega' => $result['id_bodega'],
                'id_subbodega' => $result['id_subbodega']
            ];
        }

        $sqlSubStock = "SELECT ss.id_subbodega, sb.id_bodega
                        FROM stock_subbodega ss
                        INNER JOIN subbodegas sb ON sb.id_subbodega = ss.id_subbodega
                        WHERE ss.id_material = ? AND ss.stock_actual > 0 
                        ORDER BY ss.stock_actual DESC LIMIT 1";
        $stSubStock = $this->db->prepare($sqlSubStock);
        $stSubStock->execute([$idMaterial]);
        $rowSubStock = $stSubStock->fetch(PDO::FETCH_ASSOC);
        if ($rowSubStock) {
            return [
                'id_bodega' => $rowSubStock['id_bodega'],
                'id_subbodega' => $rowSubStock['id_subbodega']
            ];
        }

        $sqlStock = "SELECT id_bodega FROM stock_bodega WHERE id_material = ? AND stock_actual > 0 ORDER BY stock_actual DESC LIMIT 1";
        $stStock = $this->db->prepare($sqlStock);
        $stStock->execute([$idMaterial]);
        $rowStock = $stStock->fetch(PDO::FETCH_ASSOC);
        if ($rowStock) {
            return [
                'id_bodega' => $rowStock['id_bodega'],
                'id_subbodega' => null
            ];
        }

        return null;
    }

    // ================= ENTREGAR (AHORA DESCUENTA STOCK) =================
    public function marcarEntregada($idSolicitud, $idUsuario)
{
    try {
        $this->db->beginTransaction();

        // 1) Primero crear la SALIDA (aquí se valida stock por trigger)
        $okMov = $this->crearMovimientoSalidaDeSolicitud($idSolicitud, $idUsuario);
        if ($okMov === false) {
            $this->db->rollBack();
            return false;
        }

        // 2) Luego marcar la solicitud como entregada
        $sql = "UPDATE solicitudes_material
                SET estado = 'Entregada',
                    fecha_respuesta = NOW(),
                    id_usuario_aprobador = ?
                WHERE id_solicitud = ?
                  AND estado = 'Aprobada'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario, $idSolicitud]);

        if ($stmt->rowCount() === 0) {
            $this->db->rollBack();
            return false;
        }

        $this->db->commit();
        return true;

    } catch (Exception $e) {
        $this->db->rollBack();
        // deja rastro real del error
        error_log("❌ marcarEntregada error: " . $e->getMessage());
        return false;
    }
}


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
        if ($programaId <= 0)
            return [];

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
        if ($programaId <= 0)
            return [];

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
    public function getProgramasUsuario($id_usuario)
{
    $sql = "SELECT 
                p.id_programa, 
                p.codigo_programa, 
                p.nombre_programa
            FROM programas_formacion p
            INNER JOIN instructores_programas ip 
                ON ip.id_programa = p.id_programa
            WHERE p.estado = 'Activo'
              AND ip.id_usuario = ?
            ORDER BY p.codigo_programa";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([(int)$id_usuario]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

}
?>
