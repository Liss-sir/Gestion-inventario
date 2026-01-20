<?php

class EvidenciaModel {

    private $conn;
    private $table = "evidencias";

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /* List evidences */
    public function listar() {
        $sql = "SELECT 
                    e.id_evidencia,
                    e.foto,
                    e.descripcion_obra,
                    COALESCE(m.fecha_hora, NOW()) as fecha,
                    u.nombre_completo as usuario,
                    mat.nombre as material,
                    mat.unidad_medida,
                    m.cantidad,
                    f.numero_ficha as ficha
                FROM {$this->table} e
                LEFT JOIN movimientos_material m 
                    ON e.id_movimiento_salida = m.id_movimiento
                LEFT JOIN usuarios u 
                    ON e.id_usuario = u.id_usuario
                LEFT JOIN material_formacion mat 
                    ON m.id_material = mat.id_material
                LEFT JOIN fichas f
                    ON m.id_ficha = f.id_ficha
                ORDER BY m.fecha_hora DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* Get evidence by ID */
    public function obtenerPorId($id) {
        $sql = "SELECT 
                    e.id_evidencia,
                    e.foto,
                    e.descripcion_obra,
                    COALESCE(m.fecha_hora, NOW()) as fecha,
                    u.nombre_completo as usuario,
                    mat.nombre as material,
                    mat.unidad_medida,
                    m.cantidad,
                    f.numero_ficha as ficha
                FROM {$this->table} e
                LEFT JOIN movimientos_material m 
                    ON e.id_movimiento_salida = m.id_movimiento
                LEFT JOIN usuarios u 
                    ON e.id_usuario = u.id_usuario
                LEFT JOIN material_formacion mat 
                    ON m.id_material = mat.id_material
                LEFT JOIN fichas f
                    ON m.id_ficha = f.id_ficha
                WHERE e.id_evidencia = :id";

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

    /* Get salidas without evidence */
    public function obtenerSalidasSinEvidencia() {
        $sql = "SELECT 
                    m.id_movimiento,
                    m.fecha_hora,
                    m.cantidad,
                    m.observaciones,
                    mat.nombre as material,
                    mat.unidad_medida,
                    b.nombre as bodega,
                    COALESCE(sb.nombre_subbodega, 'N/A') as subbodega,
                    u.nombre_completo as usuario,
                    f.numero_ficha as ficha
                FROM movimientos_material m
                LEFT JOIN material_formacion mat 
                    ON m.id_material = mat.id_material
                LEFT JOIN bodegas b 
                    ON m.id_bodega = b.id_bodega
                LEFT JOIN subbodegas sb 
                    ON m.id_subbodega = sb.id_subbodega
                LEFT JOIN usuarios u 
                    ON m.id_usuario = u.id_usuario
                LEFT JOIN fichas f
                    ON m.id_ficha = f.id_ficha
                WHERE m.tipo_movimiento = 'Salida'
                AND m.id_movimiento NOT IN (
                    SELECT id_movimiento_salida FROM {$this->table}
                )
                ORDER BY m.fecha_hora DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* Get most recent salida pending for specific user */
    public function obtenerSalidaPendientePorUsuario($id_usuario) {
        $sql = "SELECT 
                    m.id_movimiento,
                    m.fecha_hora,
                    m.cantidad,
                    m.observaciones,
                    mat.nombre as material,
                    mat.unidad_medida,
                    f.numero_ficha as ficha
                FROM movimientos_material m
                LEFT JOIN material_formacion mat 
                    ON m.id_material = mat.id_material
                LEFT JOIN fichas f
                    ON m.id_ficha = f.id_ficha
                WHERE m.tipo_movimiento = 'Salida'
                AND m.id_usuario = :id_usuario
                AND m.id_movimiento NOT IN (
                    SELECT id_movimiento_salida FROM {$this->table}
                )
                ORDER BY m.fecha_hora DESC
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_usuario", $id_usuario);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
