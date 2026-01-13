<?php
class MovimientoModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function registrarEntrada($data)
{
    $this->conn->beginTransaction();

    try {

        $codigoMovimiento = 'MOV-' . date('Y') . '-' . str_pad(
            rand(1, 99999),
            5,
            '0',
            STR_PAD_LEFT
        );

        $sql = "
            INSERT INTO movimientos_material
            (codigo_movimiento, tipo_movimiento, id_usuario, id_material,
             id_bodega, id_subbodega, cantidad, observaciones)
            VALUES (?, 'Entrada', ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $this->conn->prepare($sql);

        foreach ($data['materiales'] as $mat) {
            $stmt->execute([
                $codigoMovimiento,
                $data['id_usuario'],
                $mat['id_material'],
                $data['id_bodega'],
                $data['id_subbodega'],
                $mat['cantidad'],
                $data['observaciones']
            ]);
        }

        $this->conn->commit();
        return $codigoMovimiento;

    } catch (Exception $e) {
        $this->conn->rollBack();
        throw $e;
    }
}


    /* CREATE MOVEMENT */
    public function crearMovimiento($data) {

    $sql = "INSERT INTO movimientos_material
        (tipo_movimiento, fecha_hora, id_usuario, id_bodega, id_subbodega, observaciones)
        VALUES (:tipo, NOW(), :usuario, :bodega, :subbodega, :obs)";

    $stmt = $this->conn->prepare($sql);

    $stmt->execute([
        ':tipo'       => $data['tipo_movimiento'],
        ':usuario'    => $data['id_usuario'],
        ':bodega'     => $data['id_bodega'],
        ':subbodega'  => $data['id_subbodega'],
        ':obs'        => $data['observaciones']
    ]);

    return $this->conn->lastInsertId();
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

        return $this->conn->query($sql);
    }

    /* GET ONE MOVEMENT*/
    public function obtenerMovimiento($id) {
        $sql = "SELECT * FROM movimientos_material WHERE id_movimiento = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /* UPDATE MOVEMENT */
    public function actualizarMovimiento($id, $data) {

        $sql = "UPDATE movimientos_material SET 
                id_material=?, id_bodega=?, id_subbodega=?, cantidad=?, 
                id_programa=?, id_ficha=?, id_rae=?, observaciones=?
                WHERE id_movimiento=?";

        $stmt = $this->conn->prepare($sql);

        // Handle NULL values
        $id_subbodega = !empty($data['id_subbodega']) ? $data['id_subbodega'] : null;
        $id_programa  = !empty($data['id_programa'])  ? $data['id_programa']  : null;
        $id_ficha     = !empty($data['id_ficha'])     ? $data['id_ficha']     : null;
        $id_rae       = !empty($data['id_rae'])       ? $data['id_rae']       : null;
        $obs          = !empty($data['observaciones']) ? $data['observaciones'] : null;

        // i = integer, s = string
        $stmt->bind_param(
            "iiiiiiisi",
            $data['id_material'],
            $data['id_bodega'],
            $id_subbodega,
            $data['cantidad'],
            $id_programa,
            $id_ficha,
            $id_rae,
            $obs,
            $id
        );

        return $stmt->execute();
    }

    /* DELETE MOVEMENT */
    public function eliminarMovimiento($id) {
        $sql = "DELETE FROM movimientos_material WHERE id_movimiento=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
