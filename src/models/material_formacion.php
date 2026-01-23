<?php

class MaterialFormacionModel {

    private $db;

    public function __construct(PDO $conn)
    {
        $this->db = $conn;
    }

    /* 
       GET ALL MATERIALS
        */
    public function getAll()
    {
        $sql = "SELECT * FROM material_formacion ORDER BY nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* 
       GET MATERIAL BY ID
        */
    public function getById($id_material)
    {
        $sql = "SELECT * FROM material_formacion WHERE id_material = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_material]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* 
       CREATE MATERIAL
        */
    public function create($data)
    {
        // Safe read: inventory code
        $codigo = (isset($data['codigo_inventario']) && $data['codigo_inventario'] !== "")
            ? $data['codigo_inventario']
            : null;

        // If Inventariado → must have inventory code
        if ($data['clasificacion'] === "Inventariado" && $codigo === null) {
            return false;
        }

        $sql = "INSERT INTO material_formacion 
                (nombre, descripcion, unidad_medida, clasificacion, 
                 codigo_inventario, precio, foto)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['unidad_medida'],
            $data['clasificacion'],
            $codigo,
            $data['precio'],              // required
            $data['foto'] ?? null          // optional
        ]);
    }

    /* 
       UPDATE MATERIAL
        */
    public function update($id_material, $data)
    {
        $codigo = (isset($data['codigo_inventario']) && $data['codigo_inventario'] !== "")
            ? $data['codigo_inventario']
            : null;

        if ($data['clasificacion'] === "Inventariado" && $codigo === null) {
            return false;
        }

        $sql = "UPDATE material_formacion
                SET nombre = ?, descripcion = ?, unidad_medida = ?, 
                    clasificacion = ?, codigo_inventario = ?, 
                    precio = ?, foto = ?, estado = ?
                WHERE id_material = ?";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['unidad_medida'],
            $data['clasificacion'],
            $codigo,
            $data['precio'],
            $data['foto'] ?? null,
            $data['estado'],
            $id_material
        ]);
        
    }

    // /* 
    //    DELETE MATERIAL (check relations)
    //     */
    // public function delete($id_material)
    // {
    //     $tables = [
    //         "movimientos_material",
    //         "devoluciones_material",
    //         "stock_bodega",
    //         "stock_subbodega",
    //         "solicitudes_material"
    //     ];

    //     foreach ($tables as $table) {

    //         $sql = "SELECT COUNT(*) FROM $table WHERE id_material = ?";
    //         $stmt = $this->db->prepare($sql);
    //         $stmt->execute([$id_material]);

    //         if ($stmt->fetchColumn() > 0) {
    //             return false;
    //         }
    //     }

    //     $sql = "DELETE FROM material_formacion WHERE id_material = ?";
    //     $stmt = $this->db->prepare($sql);

    //     return $stmt->execute([$id_material]);
    // }

    /* 
       GET TOTAL STOCK
        */
    public function getStockTotal($id_material)
    {
        $sql = "
            SELECT 
                (SELECT IFNULL(SUM(stock_actual),0)
                 FROM stock_bodega
                 WHERE id_material = ?) AS stock_bodega,

                (SELECT IFNULL(SUM(stock_actual),0)
                 FROM stock_subbodega
                 WHERE id_material = ?) AS stock_subbodega
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_material, $id_material]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* 
       SEARCH MATERIAL
        */
    public function search($term)
    {
        $like = "%".$term."%";

        $sql = "SELECT *
                FROM material_formacion
                WHERE nombre LIKE ? OR codigo_inventario LIKE ?
                ORDER BY nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$like, $like]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================
    MATERIALES POR BODEGA
    ===================================== */
    public function getByBodega(int $id_bodega): array
    {
        $sql = "
            SELECT 
                m.id_material,
                m.nombre,
                m.unidad_medida,
                m.clasificacion,
                m.codigo_inventario,
                sb.stock_actual
            FROM stock_bodega sb
            INNER JOIN material_formacion m 
                ON m.id_material = sb.id_material
            WHERE sb.id_bodega = ?
            AND sb.stock_actual > 0
            ORDER BY m.nombre ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_bodega]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================
    MATERIALES POR SUBBODEGA
    ===================================== */
    public function getBySubBodega(int $id_subbodega): array
    {
        $sql = "
            SELECT 
                m.id_material,
                m.nombre,
                m.unidad_medida,
                m.clasificacion,
                m.codigo_inventario,
                ss.stock_actual
            FROM stock_subbodega ss
            INNER JOIN material_formacion m 
                ON m.id_material = ss.id_material
            WHERE ss.id_subbodega = ?
            AND ss.stock_actual > 0
            ORDER BY m.nombre ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_subbodega]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}

?>