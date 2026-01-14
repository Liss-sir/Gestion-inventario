<?php
class MovimientoModel {

    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /* ===============================
       REGISTRAR ENTRADA (MULTI MATERIAL)
    =============================== */
    public function registrarEntrada(array $data): string
{
    $this->conn->beginTransaction();

    try {
        $codigoMovimiento = 'MOV-' . date('Y') . '-' . str_pad(
            random_int(1, 99999), 5, '0', STR_PAD_LEFT
        );

        /* 1️⃣ Insert movimiento */
        $stmtMov = $this->conn->prepare("
            INSERT INTO movimientos (
                codigo_movimiento, tipo_movimiento, id_usuario,
                id_bodega, id_subbodega,
                id_programa, id_ficha, id_rae, id_instructor,
                observaciones
            ) VALUES (
                ?, 'entrada', ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $stmtMov->execute([
            $codigoMovimiento,
            $data['id_usuario'],
            $data['id_bodega'],
            $data['id_subbodega'],
            $data['id_programa'] ?? null,
            $data['id_ficha'] ?? null,
            $data['id_rae'] ?? null,
            $data['id_instructor'] ?? null,
            $data['observaciones'] ?? null
        ]);

        $idMovimiento = $this->conn->lastInsertId();

        /* 2️⃣ Insert materiales */
        $stmtMat = $this->conn->prepare("
            INSERT INTO movimiento_materiales
            (id_movimiento, id_material, cantidad, estado)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($data['materiales'] as $mat) {
            $stmtMat->execute([
                $idMovimiento,
                $mat['id_material'],
                $mat['cantidad'],
                $mat['estado']
            ]);
        }

        $this->conn->commit();
        return $codigoMovimiento;

    } catch (Exception $e) {
        $this->conn->rollBack();
        throw new Exception("Error al registrar movimiento");
    }
}


    /* ===============================
       LISTAR MOVIMIENTOS
    =============================== */
    public function listarMovimientos()
{
    $sql = "
        SELECT 
            m.id_movimiento,
            m.codigo_movimiento,
            m.tipo_movimiento,
            m.fecha_hora,
            m.observaciones,
            m.id_bodega,
            b.nombre AS bodega,
            m.id_subbodega,
            sb.nombre_subbodega AS subbodega,
            m.id_programa,
            m.id_ficha,
            m.id_rae,
            m.id_instructor
        FROM movimientos m
        LEFT JOIN bodegas b ON b.id_bodega = m.id_bodega
        LEFT JOIN subbodegas sb ON sb.id_subbodega = m.id_subbodega
        ORDER BY m.fecha_hora DESC
    ";

    $stmt = $this->conn->prepare($sql);
    $stmt->execute();
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar materiales a cada movimiento
    foreach ($movimientos as &$mov) {
        $sqlMat = "
            SELECT mm.id_material, mf.nombre, mm.cantidad, mf.unidad_medida, mm.estado
            FROM movimiento_materiales mm
            INNER JOIN material_formacion mf ON mf.id_material = mm.id_material
            WHERE mm.id_movimiento = ?
        ";
        $stmtMat = $this->conn->prepare($sqlMat);
        $stmtMat->execute([$mov['id_movimiento']]);
        $mov['materiales'] = $stmtMat->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $movimientos;
}



    /* ===============================
       OBTENER MOVIMIENTO + MATERIALES
    =============================== */
    public function obtenerMovimiento(int $id)
    {
        $sql = "SELECT * FROM movimientos WHERE id_movimiento = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        $mov = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mov) return null;

        $sqlMat = "
            SELECT mm.*, m.nombre
            FROM movimiento_materiales mm
            INNER JOIN materiales m ON m.id_material = mm.id_material
            WHERE mm.id_movimiento = ?
        ";

        $stmtMat = $this->conn->prepare($sqlMat);
        $stmtMat->execute([$id]);
        $mov['materiales'] = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

        return $mov;
    }

    /* ===============================
       ELIMINAR MOVIMIENTO
    =============================== */
    public function eliminarMovimiento(int $id): bool
    {
        $this->conn->beginTransaction();

        try {
            $this->conn->prepare(
                "DELETE FROM movimiento_materiales WHERE id_movimiento=?"
            )->execute([$id]);

            $this->conn->prepare(
                "DELETE FROM movimientos WHERE id_movimiento=?"
            )->execute([$id]);

            $this->conn->commit();
            return true;

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
