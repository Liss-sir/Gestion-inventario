<?php

class RaeModel {

    private $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /* ==========================
       LISTAR
    ========================== */
    public function listar(): array {
        $sql = "SELECT * FROM raes ORDER BY id_rae DESC";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==========================
       OBTENER POR ID
    ========================== */
    public function obtener(int $id): ?array {
        $sql = "SELECT * FROM raes WHERE id_rae = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /* ==========================
       VERIFICAR CÓDIGO ÚNICO
    ========================== */
    public function existeCodigo(string $codigo_rae, ?int $ignorar_id = null): bool {

        $sql = "SELECT id_rae FROM raes WHERE codigo_rae = ?";
        $params = [$codigo_rae];

        if ($ignorar_id !== null) {
            $sql .= " AND id_rae <> ?";
            $params[] = $ignorar_id;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /* ==========================
       CREAR
    ========================== */
    public function crear(
        string $codigo_rae,
        string $descripcion_rae,
        int $id_programa,
        string $estado
    ): bool {

        $sql = "INSERT INTO raes
                (codigo_rae, descripcion_rae, id_programa, estado)
                VALUES (?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $codigo_rae,
            $descripcion_rae,
            $id_programa,
            $estado
        ]);
    }

    /* ==========================
       ACTUALIZAR
    ========================== */
    public function actualizar(
        int $id_rae,
        ?string $codigo_rae,
        ?int $id_programa,
        ?string $descripcion_rae,
        ?string $estado
    ): bool {

        $campos = [];
        $params = [];

        if ($codigo_rae !== null) {
            $campos[] = "codigo_rae = ?";
            $params[] = $codigo_rae;
        }

        if ($id_programa !== null) {
            $campos[] = "id_programa = ?";
            $params[] = $id_programa;
        }

        if ($descripcion_rae !== null) {
            $campos[] = "descripcion_rae = ?";
            $params[] = $descripcion_rae;
        }

        if ($estado !== null) {
            $campos[] = "estado = ?";
            $params[] = $estado;
        }

        if (empty($campos)) {
            return false;
        }

        $params[] = $id_rae;

        $sql = "UPDATE raes SET " . implode(", ", $campos) . " WHERE id_rae = ?";
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute($params);
    }

    /* ==========================
       CAMBIAR ESTADO
    ========================== */
    public function cambiarEstado(int $id, string $estado): bool {

        $sql = "UPDATE raes SET estado = ? WHERE id_rae = ?";
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$estado, $id]);
    }
}
