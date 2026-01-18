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
        // Escribir en archivo de debug
        file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " [RESPONDER] Iniciando responderSolicitud($idSolicitud, $estado, $idAprobador)\n", FILE_APPEND);
        
        // Normalizar el estado (aceptar mayúscula o minúscula)
        $estadoNormalizado = ucfirst(strtolower($estado));
        
        // Only valid states
        if (!in_array($estadoNormalizado, ['Aprobada', 'Rechazada'])) {
            file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " ❌ Estado no válido: $estado\n", FILE_APPEND);
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
            $estadoNormalizado,
            $idAprobador,
            $observaciones,
            $idSolicitud
        ]);
        
        if ($result) {
            $rows = $stmt->rowCount();
            file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " ✅ Solicitud $idSolicitud actualizada a $estadoNormalizado. Filas: $rows\n", FILE_APPEND);
            
            // ✅ SI FUE APROBADA, crear movimiento de tipo "salida"
            if ($estadoNormalizado === 'Aprobada' && $rows > 0) {
                file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " 🔵 Llamando crearMovimientoSalidaDeSolicitud($idSolicitud, $idAprobador)\n", FILE_APPEND);
                $this->crearMovimientoSalidaDeSolicitud($idSolicitud, $idAprobador);
            }
            
            return $rows > 0;
        } else {
            $errorInfo = $stmt->errorInfo();
            file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " ❌ Error en BD: " . json_encode($errorInfo) . "\n", FILE_APPEND);
            return false;
        }
    }

    /* ===============================
       CREAR MOVIMIENTO DE SALIDA
       Cuando se aprueba una solicitud
    =============================== */
    private function crearMovimientoSalidaDeSolicitud($idSolicitud, $idUsuario)
    {
        try {
            file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " 🔵 [SALIDA] Iniciando crearMovimientoSalidaDeSolicitud($idSolicitud, $idUsuario)\n", FILE_APPEND);
            
            // Obtener la solicitud completa con sus materiales
            $solicitud = $this->getSolicitudCompleta($idSolicitud);
            
            file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " 📋 [SALIDA] Solicitud: " . json_encode($solicitud) . "\n", FILE_APPEND);
            
            if (!$solicitud) {
                file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " ❌ [SALIDA] Solicitud no encontrada\n", FILE_APPEND);
                return false;
            }
            
            if (empty($solicitud['materiales'])) {
                file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " ❌ [SALIDA] Sin materiales\n", FILE_APPEND);
                return false;
            }

            file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " 📦 [SALIDA] " . count($solicitud['materiales']) . " materiales encontrados\n", FILE_APPEND);
            
            // ⭐ Obtener la bodega del primer material (todos deben estar en la misma bodega)
            $idBodega = $this->obtenerBodegaDeMaterial($solicitud['materiales'][0]['id_material']);
            file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " 🏪 [SALIDA] Bodega obtenida: $idBodega\n", FILE_APPEND);
            
            if (!$idBodega) {
                file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " ❌ [SALIDA] No se encontró bodega para el material\n", FILE_APPEND);
                return false;
            }
            
            // Preparar datos para el movimiento
            $datosMovimiento = [
                'tipo_movimiento' => 'salida',
                'id_usuario' => $idUsuario,
                'id_bodega' => $idBodega,  // ⭐ USAR LA BODEGA DEL MATERIAL
                'id_subbodega' => $solicitud['id_subbodega'] ?? null,
                'id_programa' => $solicitud['id_programa'] ?? null,
                'id_ficha' => $solicitud['id_ficha'] ?? null,
                'id_rae' => $solicitud['id_rae'] ?? null,
                // ⭐ INCLUIR LAS OBSERVACIONES ORIGINALES DE LA SOLICITUD
                'observaciones' => ($solicitud['observaciones'] 
                    ? $solicitud['observaciones'] . " | Solicitud #" . $idSolicitud
                    : "Solicitud #" . $idSolicitud),
                'id_solicitud' => $idSolicitud,
                'materiales' => []
            ];

            // Convertir materiales de solicitud al formato de movimiento
            foreach ($solicitud['materiales'] as $material) {
                $datosMovimiento['materiales'][] = [
                    'id_material' => $material['id_material'],
                    'nombre' => $material['material'] ?? 'Material',
                    'cantidad' => $material['cantidad'],
                    'unidad' => $material['unidad_medida'] ?? ''
                ];
                file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . "   📌 {$material['material']} (ID: {$material['id_material']}, Cant: {$material['cantidad']})\n", FILE_APPEND);
            }

            file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " 🔧 [SALIDA] Datos movimiento: " . json_encode($datosMovimiento) . "\n", FILE_APPEND);

            // Usar el modelo de movimientos para registrar la salida
            require_once __DIR__ . '/movimiento.php';
            $movimientoModel = new MovimientoModel($this->db);
            
            $codigoMovimiento = $movimientoModel->registrarEntrada($datosMovimiento);
            file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " ✅ [SALIDA] Movimiento creado: $codigoMovimiento\n", FILE_APPEND);
            
            return true;

        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/../../debug_solicitud.log', date('Y-m-d H:i:s') . " ❌ [SALIDA] Exception: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    /* ===============================
       OBTENER BODEGA DE UN MATERIAL
       Busca dónde está almacenado el material
    =============================== */
    private function obtenerBodegaDeMaterial($idMaterial)
    {
        // Buscar en movimientos_material la bodega donde está almacenado este material
        // (el más reciente)
        $sql = "SELECT id_bodega 
                FROM movimientos_material 
                WHERE id_material = ? 
                AND tipo_movimiento = 'entrada'
                ORDER BY fecha_hora DESC 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idMaterial]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['id_bodega'];
        }
        
        // Si no hay movimiento de entrada, retorna null
        return null;
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
