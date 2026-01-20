<?php
class devolucion {
    private $conn;
    private $table = "devoluciones_material";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Obtener solicitudes con movimientos de salida disponibles para devolución
     */
    public function getSolicitudesConSalida() {
        $sql = "SELECT DISTINCT
                    s.id_solicitud,
                    s.fecha_solicitud,
                    s.observaciones,
                    mm.id_movimiento,
                    mm.fecha_hora as fecha_salida,
                    u.nombre_completo as usuario_solicitante
                FROM solicitudes_material s
                INNER JOIN movimientos_material mm ON mm.id_solicitud = s.id_solicitud
                LEFT JOIN usuarios u ON u.id_usuario = s.id_usuario_solicitante
                WHERE mm.tipo_movimiento = 'Salida'
                  AND s.estado = 'Aprobada'
                ORDER BY mm.fecha_hora DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener materiales de un movimiento de salida con cantidades devueltas
     */
    public function getMaterialesMovimiento($idMovimiento) {
        $sql = "SELECT 
                    mm.id_material,
                    mm.cantidad as cantidad_salida,
                    mf.nombre,
                    mf.unidad_medida,
                    mm.id_bodega,
                    mm.id_subbodega,
                    COALESCE(SUM(dm.cantidad_devuelta), 0) as cantidad_devuelta_total,
                    (mm.cantidad - COALESCE(SUM(dm.cantidad_devuelta), 0)) as cantidad_pendiente
                FROM movimientos_material mm
                INNER JOIN material_formacion mf ON mf.id_material = mm.id_material
                LEFT JOIN devoluciones_material dm ON dm.id_movimiento_salida = mm.id_movimiento 
                    AND dm.id_material = mm.id_material
                WHERE mm.id_movimiento = ?
                GROUP BY mm.id_material, mm.cantidad, mf.nombre, mf.unidad_medida, mm.id_bodega, mm.id_subbodega
                HAVING cantidad_pendiente > 0";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idMovimiento]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registrar devolución y actualizar stock
     */
    public function registrar($data) {
        try {
            $this->conn->beginTransaction();

            // Insertar devolución (sin id_devolucion, es autoincremental)
            $sql = "INSERT INTO {$this->table}
            (id_movimiento_salida, id_usuario, id_material, id_bodega, id_subbodega, cantidad_devuelta, estado_material, fecha_hora, observaciones)
            VALUES (:id_movimiento_salida, :id_usuario, :id_material, :id_bodega, :id_subbodega, :cantidad_devuelta, :estado_material, NOW(), :observaciones)";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id_movimiento_salida", $data['id_movimiento_salida']);
            $stmt->bindParam(":id_usuario", $data['id_usuario']);
            $stmt->bindParam(":id_material", $data['id_material']);
            $stmt->bindParam(":id_bodega", $data['id_bodega']);
            $stmt->bindParam(":id_subbodega", $data['id_subbodega']);
            $stmt->bindParam(":cantidad_devuelta", $data['cantidad_devuelta']);
            $stmt->bindParam(":estado_material", $data['estado_material']);
            $stmt->bindParam(":observaciones", $data['observaciones']);

            if (!$stmt->execute()) {
                throw new Exception("Error al insertar devolución");
            }

            // Actualizar stock en bodega
            $this->actualizarStock(
                $data['id_bodega'],
                $data['id_subbodega'] ?? null,
                $data['id_material'],
                $data['cantidad_devuelta']
            );

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error registrar devolución: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar stock al devolver material
     */
    private function actualizarStock($idBodega, $idSubbodega, $idMaterial, $cantidad) {
        // Actualizar en bodega
        $sqlBodega = "INSERT INTO stock_bodega (id_bodega, id_material, stock_actual)
                      VALUES (?, ?, ?)
                      ON DUPLICATE KEY UPDATE stock_actual = stock_actual + ?";
        
        $stmtBodega = $this->conn->prepare($sqlBodega);
        $stmtBodega->execute([$idBodega, $idMaterial, $cantidad, $cantidad]);

        // Si hay subbodega, también actualizar ahí
        if ($idSubbodega && $idSubbodega > 0) {
            $sqlSub = "INSERT INTO stock_subbodega (id_subbodega, id_material, stock_actual)
                       VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE stock_actual = stock_actual + ?";
            
            $stmtSub = $this->conn->prepare($sqlSub);
            $stmtSub->execute([$idSubbodega, $idMaterial, $cantidad, $cantidad]);
        }
    }

    public function listar() {
        $sql = "SELECT 
                    dm.id_devolucion,
                    dm.fecha_hora,
                    dm.cantidad_devuelta,
                    dm.estado_material,
                    dm.observaciones,
                    mf.nombre as material,
                    u.nombre_completo as usuario,
                    b.nombre as bodega,
                    mm.id_movimiento as movimiento_salida
                FROM {$this->table} dm
                INNER JOIN material_formacion mf ON mf.id_material = dm.id_material
                INNER JOIN usuarios u ON u.id_usuario = dm.id_usuario
                INNER JOIN bodegas b ON b.id_bodega = dm.id_bodega
                INNER JOIN movimientos_material mm ON mm.id_movimiento = dm.id_movimiento_salida
                ORDER BY dm.fecha_hora DESC
                LIMIT 50";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorMovimiento($id_movimiento) {
        $sql = "SELECT * FROM {$this->table} WHERE id_movimiento_salida = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id", $id_movimiento);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener bodegas disponibles
     */
    public function getBodegas() {
        $sql = "SELECT id_bodega, codigo_bodega, nombre FROM bodegas ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener subbodegas de una bodega
     */
    public function getSubbodegas($idBodega) {
        $sql = "SELECT id_subbodega, codigo_subbodega, nombre_subbodega 
                FROM subbodegas 
                WHERE id_bodega = ? 
                ORDER BY nombre_subbodega";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idBodega]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
