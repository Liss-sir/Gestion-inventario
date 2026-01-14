<?php
class MovimientoModel {
    private $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

public function obtenerStockActual($id_material, $id_bodega, $id_subbodega = null)
{
    if ($id_subbodega) {
        $sql = "SELECT stock_actual 
                FROM stock_subbodega 
                WHERE id_material = ? AND id_subbodega = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_material, $id_subbodega]);
    } else {
        $sql = "SELECT stock_actual 
                FROM stock_bodega 
                WHERE id_material = ? AND id_bodega = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_material, $id_bodega]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['stock_actual'] : 0;
}



    /* CREATE MOVEMENT */
    public function crearMovimiento($data) {

    $sql = "INSERT INTO movimientos_material
        (tipo_movimiento, fecha_hora, id_usuario, id_material, id_bodega, id_subbodega,
         cantidad, id_programa, id_ficha, id_rae, observaciones)
        VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $this->conn->prepare($sql);

    if ($stmt->execute([
        $data['tipo_movimiento'],
        $data['id_usuario'],
        $data['id_material'],
        $data['id_bodega'],
        $data['id_subbodega'] ?? null,
        $data['cantidad'],
        $data['id_programa'] ?? null,
        $data['id_ficha'] ?? null,
        $data['id_rae'] ?? null,
        $data['observaciones'] ?? null])) {
        return $this->conn->lastInsertId();
    }
    return false;

}

    /* LIST MOVEMENTS */
public function listarMovimientos() {
    $sql = "SELECT m.*,
            u.nombre_completo AS usuario,
            mat.nombre AS material,
            b.nombre AS bodega,
            f.numero_ficha,
            p.nombre_programa,
            r.descripcion_rae
            FROM movimientos_material m
            INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
            INNER JOIN material_formacion mat ON m.id_material = mat.id_material
            INNER JOIN bodegas b ON m.id_bodega = b.id_bodega
            LEFT JOIN fichas f ON m.id_ficha = f.id_ficha
            LEFT JOIN programas_formacion p ON m.id_programa = p.id_programa
            LEFT JOIN raes r ON m.id_rae = r.id_rae
            ORDER BY m.fecha_hora DESC";

    $stmt = $this->conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


    /* GET ONE MOVEMENT*/
    public function obtenerMovimiento($id) {
        $sql = "SELECT * FROM movimientos_material WHERE id_movimiento = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* UPDATE MOVEMENT */
    public function actualizarMovimiento($id, $data) {

        $sql = "UPDATE movimientos_material SET
                id_material=?, id_bodega=?, id_subbodega=?, cantidad=?,
                id_programa=?, id_ficha=?, id_rae=?, observaciones=?
                WHERE id_movimiento=?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            $data['id_material'],
            $data['id_bodega'],
            $data['id_subbodega'] ?? null,
            $data['cantidad'],
            $data['id_programa'] ?? null,
            $data['id_ficha'] ?? null,
            $data['id_rae'] ?? null,
            $data['observaciones'] ?? null,
            $id
        ]);
    }


    /* DELETE MOVEMENT */
    public function eliminarMovimiento($id) {
        $sql = "DELETE FROM movimientos_material WHERE id_movimiento = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$id]);
    }

}
