<?php

class BodegaModel {
    private $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /* ==============================
       LISTAR BODEGAS
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
       OBTENER BODEGA POR CODIGO
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
       CREAR BODEGA
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
       ACTUALIZAR BODEGA
       POR ID_BODEGA (CORRECTO)
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
       CAMBIAR ESTADO
       POR CODIGO_BODEGA
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
}
?>