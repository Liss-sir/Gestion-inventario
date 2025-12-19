<?php

class BodegaModel {
    private $conn; 

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /* LIST ALL BODEGAS */
    public function listar(): array {
        try {
            $sql = "SELECT * FROM bodegas ORDER BY nombre"; // Select all bodegas ordered by name
            $stmt = $this->conn->query($sql); // Direct query
            return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all rows as associative array
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()]; // Return error on failure
        }
    }

    /* GET BODEGA BY ID */
    public function obtenerPorId(int $id): ?array {
        try {
            $sql = "SELECT * FROM bodegas WHERE id_bodega = :id LIMIT 1"; // Select bodega by ID
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT); // Bind ID parameter
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch single row
            return $row ?: null; // Return row or null if not found
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /* CREATE NEW BODEGA */
    public function crear(
    string $codigo,
    string $nombre,
    string $ubicacion,
    string $clasificacion_bodega
): bool {
    try {
        $sql = "INSERT INTO bodegas (
                    codigo_bodega,
                    nombre,
                    ubicacion,
                    clasificacion_bodega
                ) VALUES (
                    :codigo,
                    :nombre,
                    :ubicacion,
                    :clasificacion
                )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':ubicacion', $ubicacion);
        $stmt->bindParam(':clasificacion', $clasificacion_bodega);

        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error crear bodega: " . $e->getMessage());
        return false;
    }
}


    /* UPDATE BODEGA */
  public function actualizar(
    int $id,
    string $codigo,
    string $nombre,
    string $ubicacion,
    string $estado,
    string $clasificacion
): bool {
    try {
        // Validaciones defensivas (ENUM)
        $estadosValidos = ['Activo', 'Inactivo'];
        $clasificacionesValidas = ['Insumos', 'Equipos'];

        if (!in_array($estado, $estadosValidos, true)) {
            throw new InvalidArgumentException('Estado no válido');
        }

        if (!in_array($clasificacion, $clasificacionesValidas, true)) {
            throw new InvalidArgumentException('Clasificación no válida');
        }

        $sql = "
            UPDATE bodegas
            SET codigo_bodega        = :codigo,
                nombre               = :nombre,
                ubicacion            = :ubicacion,
                estado               = :estado,
                clasificacion_bodega = :clasificacion
            WHERE id_bodega = :id
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
        $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindParam(':ubicacion', $ubicacion, PDO::PARAM_STR);
        $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindParam(':clasificacion', $clasificacion, PDO::PARAM_STR);

        return $stmt->execute();

    } catch (Throwable $e) {
        error_log('Error actualizar bodega: ' . $e->getMessage());
        return false;
    }
}


    /* CHANGE BODEGA STATE (Active/Inactive)*/
    public function cambiarEstado(int $id, string $estado): bool {
        try {
            $sql = "UPDATE bodegas SET estado = :estado WHERE id_bodega = :id"; // Update only estado
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error cambiar estado bodega: " . $e->getMessage());
            return false;
        }
    }
}
