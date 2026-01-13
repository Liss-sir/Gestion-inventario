<?php

class EvidenciaModel {

    private $conn;
    private $table = "evidencias";

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /* List evidences */
    public function listar() {
        $sql = "SELECT * FROM {$this->table}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* Get evidence by ID */
    public function obtenerPorId($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id_evidencia = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* Create evidence */
    public function crear($data) {
        $sql = "INSERT INTO {$this->table}
                (id_movimiento_salida, id_usuario, foto, descripcion_obra)
                VALUES
                (:id_movimiento_salida, :id_usuario, :foto, :descripcion_obra)";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(":id_movimiento_salida", $data["id_movimiento_salida"]);
        $stmt->bindParam(":id_usuario", $data["id_usuario"]);
        $stmt->bindParam(":foto", $data["foto"]);
        $stmt->bindParam(":descripcion_obra", $data["descripcion_obra"]);

        return $stmt->execute();
    }
}
