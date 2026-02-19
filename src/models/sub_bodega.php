<?php

class SubBodegaModel {

    private $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /* LIST*/
    public function listar(): array {
        try {
            $stmt = $this->conn->query("SELECT * FROM subbodegas ORDER BY nombre_subbodega");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("PDO list error: " . $e->getMessage());
            return [];
        }
    }

    /* GET BY ID */
    public function obtenerPorId(int $id): ?array {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM subbodegas WHERE id_subbodega = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("PDO get error: " . $e->getMessage());
            return null;
        }
    }

    /* GET BY BODEGA */
    public function obtenerPorBodega(int $idBodega): array {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM subbodegas WHERE id_bodega = ? ORDER BY nombre_subbodega");
            $stmt->execute([$idBodega]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("PDO get by bodega error: " . $e->getMessage());
            return [];
        }
    }

    /* CREATE */
    public function crear(array $data): bool {
        try {
            $sql = "INSERT INTO subbodegas (
                        id_bodega, codigo_subbodega, nombre_subbodega, 
                        descripcion, estado, clasificacion_subbodegas
                    ) VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);

            return $stmt->execute([
                $data["id_bodega"],
                $data["codigo_subbodega"],
                $data["nombre_subbodega"],
                $data["descripcion"] ?? null,
                $data["estado"] ?? "Activo",
                $data["clasificacion_subbodegas"]
            ]);
        } catch (PDOException $e) {
            error_log("PDO create error: " . $e->getMessage());
            return false;
        }
    }

   /* UPDATE (MODELO) */
    public function actualizar(int $id, array $data): bool {
        try {
            $stmt = $this->conn->prepare("
                UPDATE subbodegas
                SET
                    id_bodega = ?,
                    codigo_subbodega = ?,
                    nombre_subbodega = ?,
                    descripcion = ?,
                    estado = ?,
                    clasificacion_subbodegas = ?
                WHERE id_subbodega = ?
            ");

            return $stmt->execute([
                $data["id_bodega"],
                $data["codigo_subbodega"],
                $data["nombre_subbodega"],
                $data["descripcion"] ?? null,
                $data["estado"],
                $data["clasificacion_subbodegas"],
                $id
            ]);
        } catch (PDOException $e) {
            error_log("PDO update error: " . $e->getMessage());
            return false;
        }
    }
    
    /* CHANGE STATE */
    public function cambiarEstado(int $id, string $estado): bool {
        try {
            $stmt = $this->conn->prepare("UPDATE subbodegas SET estado = ? WHERE id_subbodega = ?");
            return $stmt->execute([$estado, $id]);
        } catch (PDOException $e) {
            error_log("PDO state error: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerInventarioPorSubbodega(int $idSubbodega): array {
        try {
            $sql = "
                SELECT 
                    m.id_material,
                    m.nombre AS nombre_material,
                    m.unidad_medida,
                    ss.stock_actual AS cantidad_total
                FROM stock_subbodega ss
                INNER JOIN material_formacion m 
                    ON m.id_material = ss.id_material
                WHERE ss.id_subbodega = ?
                AND ss.stock_actual > 0
                ORDER BY m.nombre ASC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$idSubbodega]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error inventario subbodega: " . $e->getMessage());
            return [];
        }
    }
}

