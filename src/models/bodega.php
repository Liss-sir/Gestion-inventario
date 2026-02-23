<?php

class BodegaModel {
    private $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /* ==============================
       LIST WINERIES
       ============================== */
    public function listar(): array {
        try {
            $sql = "
                SELECT 
                    id_bodega,
                    codigo_bodega,
                    nombre,
                    ubicacion,
                    estado,
                    clasificacion_bodega
                FROM bodegas
                ORDER BY nombre
            ";
            $stmt = $this->conn->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /* ==============================
       GET WINERY BY CODE
       ============================== */
    public function obtenerPorCodigo(string $codigo): ?array {
        try {
            $sql = "
                SELECT 
                    id_bodega,
                    codigo_bodega,
                    nombre,
                    ubicacion,
                    estado,
                    clasificacion_bodega
                FROM bodegas
                WHERE codigo_bodega = :codigo
                LIMIT 1
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':codigo', $codigo);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;

        } catch (PDOException $e) {
            error_log("Error obtener bodega: " . $e->getMessage());
            return null;
        }
    }

    /* ==============================
       CREATE WINERY
       ============================== */
    public function crear(
        string $codigo,
        string $nombre,
        string $ubicacion,
        string $clasificacion
    ): bool {
        try {
            $sql = "
                INSERT INTO bodegas (
                    codigo_bodega,
                    nombre,
                    ubicacion,
                    clasificacion_bodega
                ) VALUES (
                    :codigo,
                    :nombre,
                    :ubicacion,
                    :clasificacion
                )
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':codigo', $codigo);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':ubicacion', $ubicacion);
            $stmt->bindParam(':clasificacion', $clasificacion);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error crear bodega: " . $e->getMessage());
            return false;
        }
    }

    /* ==============================
       UPDATE WINERY BY ID_BODEGA
       ============================== */
    public function actualizar(
        int $id_bodega,
        string $codigo_bodega,
        string $nombre,
        string $ubicacion,
        string $clasificacion
    ): bool {
        try {
            $clasificacionesValidas = ['Insumos', 'Equipos'];
            if (!in_array($clasificacion, $clasificacionesValidas, true)) {
                throw new InvalidArgumentException('Clasificación no válida');
            }

            $sql = "
                UPDATE bodegas
                SET 
                    codigo_bodega = :codigo,
                    nombre = :nombre,
                    ubicacion = :ubicacion,
                    clasificacion_bodega = :clasificacion
                WHERE id_bodega = :id
                LIMIT 1
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id_bodega, PDO::PARAM_INT);
            $stmt->bindParam(':codigo', $codigo_bodega);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':ubicacion', $ubicacion);
            $stmt->bindParam(':clasificacion', $clasificacion);

            return $stmt->execute();

        } catch (Throwable $e) {
            error_log("Error actualizar bodega: " . $e->getMessage());
            return false;
        }
    }

    /* ==============================
       CHANGE STATE BY CODE_BODEGA
       ============================== */
    public function cambiarEstado(string $codigo_bodega, string $estado): bool {
        try {
            $sql = "
                UPDATE bodegas
                SET estado = :estado
                WHERE codigo_bodega = :codigo
                LIMIT 1
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':codigo', $codigo_bodega);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error cambiar estado bodega: " . $e->getMessage());
            return false;
        }
    }

    /* ==============================
       GET INVENTORY BY WINERY
       ============================== */
    public function obtenerInventarioPorBodega(int $idBodega): array {
        try {
            $sql = "
                SELECT 
                    m.id_material,
                    m.nombre AS nombre_material,
                    m.unidad_medida,
                    sb.stock_actual AS cantidad_total
                FROM stock_bodega sb
                INNER JOIN material_formacion m 
                    ON m.id_material = sb.id_material
                WHERE sb.id_bodega = ?
                AND sb.stock_actual > 0
                ORDER BY m.nombre ASC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$idBodega]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error inventario bodega: " . $e->getMessage());
            return [];
        }
    }



    /* ==============================
       GET INVENTORY BY SUBWAREHOUSE
       ============================== */
    public function obtenerInventarioPorSubbodega(int $idSubbodega): array {
        try {
            $sql = "
                SELECT 
                    id_material,
                    nombre_material,
                    unidad_medida,
                    SUM(cantidad_total) AS cantidad_total
                FROM (
                    -- Materiales principales en subbodega
                    SELECT 
                        mm.id_material,
                        mf.nombre AS nombre_material,
                        mf.unidad_medida,
                        SUM(CASE 
                            WHEN mm.tipo_movimiento = 'entrada' THEN mm.cantidad
                            WHEN mm.tipo_movimiento = 'salida' THEN -mm.cantidad
                            WHEN mm.tipo_movimiento = 'devolucion' THEN mm.cantidad
                            ELSE 0
                        END) AS cantidad_total
                    FROM movimientos_material mm
                    INNER JOIN material_formacion mf ON mf.id_material = mm.id_material
                    WHERE mm.id_subbodega = :id_subbodega
                    GROUP BY mm.id_material, mf.nombre, mf.unidad_medida
                    
                    UNION ALL
                    
                    -- Materiales adicionales en subbodega
                    SELECT 
                        md.id_material,
                        mf.nombre AS nombre_material,
                        mf.unidad_medida,
                        SUM(CASE 
                            WHEN mm.tipo_movimiento = 'entrada' THEN md.cantidad
                            WHEN mm.tipo_movimiento = 'salida' THEN -md.cantidad
                            WHEN mm.tipo_movimiento = 'devolucion' THEN md.cantidad
                            ELSE 0
                        END) AS cantidad_total
                    FROM movimientos_detalle md
                    INNER JOIN movimientos_material mm ON mm.id_movimiento = md.id_movimiento
                    INNER JOIN material_formacion mf ON mf.id_material = md.id_material
                    WHERE mm.id_subbodega = :id_subbodega
                    GROUP BY md.id_material, mf.nombre, mf.unidad_medida
                ) AS materiales_combinados
                GROUP BY id_material, nombre_material, unidad_medida
                HAVING cantidad_total > 0
                ORDER BY nombre_material ASC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_subbodega', $idSubbodega, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerInventarioPorSubbodega: " . $e->getMessage());
            return [];
        }
    }
}
?>