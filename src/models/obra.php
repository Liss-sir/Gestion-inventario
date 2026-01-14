<?php

class ObraModel {

    private $conn;
    private $table = "actividades_formacion";
    private $tableActividadesAprendices = "actividades_aprendices";

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /* List whin JOINs */
    public function listar() {
        $sql = "SELECT 
                    af.*,
                    f.numero_ficha,
                    r.descripcion_rae,
                    u.nombre_completo as nombre_instructor,
                    GROUP_CONCAT(DISTINCT u2.nombre_completo ORDER BY u2.nombre_completo SEPARATOR ', ') as aprendices_asignados,
                    COUNT(DISTINCT aa.id_usuario) as total_aprendices
                FROM {$this->table} af
                LEFT JOIN fichas f ON af.id_ficha = f.id_ficha
                LEFT JOIN raes r ON af.id_rae = r.id_rae
                LEFT JOIN usuarios u ON af.id_instructor = u.id_usuario
                LEFT JOIN {$this->tableActividadesAprendices} aa ON af.id_actividad = aa.id_actividad
                LEFT JOIN usuarios u2 ON aa.id_usuario = u2.id_usuario
                GROUP BY af.id_actividad
                ORDER BY af.fecha_inicio DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* GET ACTIVE FICHAS */
    public function obtenerFichasActivas() {
        $sql = "SELECT id_ficha, numero_ficha 
                FROM fichas 
                WHERE estado = 'Activa' 
                ORDER BY numero_ficha";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* GET ACTIVE RAES */
    public function obtenerRaesActivos() {
        $sql = "SELECT id_rae, descripcion_rae 
                FROM raes 
                WHERE estado = 'Activo' 
                ORDER BY descripcion_rae";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* GET ACTIVE INSTRUCTORS */
    public function obtenerInstructoresActivos() {
        $sql = "SELECT id_usuario, nombre_completo
                FROM usuarios 
                WHERE cargo = 'instructor' AND estado = 'Activo' 
                ORDER BY nombre_completo";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* GET APRENDICES FROM A FICHA */
    public function obtenerAprendicesFicha($idFicha) {
        $sql = "SELECT 
                    u.id_usuario,
                    u.nombre_completo,
                    u.documento,
                    u.estado,
                    fa.fecha_asignacion
                FROM fichas_aprendices fa
                JOIN usuarios u ON fa.id_usuario = u.id_usuario
                WHERE fa.id_ficha = ? 
                AND u.cargo = 'aprendiz'
                AND u.estado = 'Activo'
                AND fa.estado = 'Activo'
                ORDER BY u.nombre_completo";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idFicha]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* GET BY ID WITH COMPLETE INFORMATION */
    public function obtener($id) {
        // Obtener información básica de la actividad
        $sql = "SELECT 
                    af.*,
                    f.numero_ficha,
                    r.descripcion_rae,
                    u.nombre_completo as nombre_instructor
                FROM {$this->table} af
                LEFT JOIN fichas f ON af.id_ficha = f.id_ficha
                LEFT JOIN raes r ON af.id_rae = r.id_rae
                LEFT JOIN usuarios u ON af.id_instructor = u.id_usuario
                WHERE af.id_actividad = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        
        $obra = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($obra) {
            // GET APRENDICES ASSIGNED TO THIS ACTIVITY
            $sqlAprendices = "SELECT 
                                aa.*,
                                u.nombre_completo,
                                u.documento
                            FROM {$this->tableActividadesAprendices} aa
                            JOIN usuarios u ON aa.id_usuario = u.id_usuario
                            WHERE aa.id_actividad = ?
                            ORDER BY u.nombre_completo";
            
            $stmtAprendices = $this->conn->prepare($sqlAprendices);
            $stmtAprendices->execute([$id]);
            
            $obra['aprendices_asignados'] = $stmtAprendices->fetchAll(PDO::FETCH_ASSOC);
            $obra['total_aprendices'] = count($obra['aprendices_asignados']);
        }
        
        return $obra;
    }

    /* CREATE OBRA AND RETURN ID */
    public function crear($data) {
        $sql = "INSERT INTO {$this->table}
            (id_ficha, id_rae, id_instructor, nombre_actividad, descripcion,
             tipo_trabajo, fecha_inicio, fecha_fin, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);

        $success = $stmt->execute([
            $data["id_ficha"],
            $data["id_rae"],
            $data["id_instructor"],
            $data["nombre_actividad"],
            $data["descripcion"] ?? null,
            $data["tipo_trabajo"],
            $data["fecha_inicio"] ?? null,
            $data["fecha_fin"] ?? null,
            $data["estado"] ?? "Activa"
        ]);

        if ($success) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }

    /* UPDATE OBRA */
    public function actualizar($data) {
        $sql = "UPDATE {$this->table}
            SET id_ficha = ?,
                id_rae = ?,
                id_instructor = ?,
                nombre_actividad = ?,
                descripcion = ?,
                tipo_trabajo = ?,
                fecha_inicio = ?,
                fecha_fin = ?,
                estado = ?
            WHERE id_actividad = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            $data["id_ficha"],
            $data["id_rae"],
            $data["id_instructor"],
            $data["nombre_actividad"],
            $data["descripcion"],
            $data["tipo_trabajo"],
            $data["fecha_inicio"],
            $data["fecha_fin"],
            $data["estado"],
            $data["id_actividad"]
        ]);
    }

    /* CHANGE STATUS */
    public function cambiarEstado($id, $estado) {
        $sql = "UPDATE {$this->table} SET estado = ? WHERE id_actividad = ?";
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$estado, $id]);
    }

    /* ASSIGN APRENDICES TO AN ACTIVITY */
    public function asignarAprendices($idActividad, $aprendices) {
        // Eliminar asignaciones anteriores (si las hay)
        $deleteSql = "DELETE FROM {$this->tableActividadesAprendices} WHERE id_actividad = ?";
        $deleteStmt = $this->conn->prepare($deleteSql);
        $deleteStmt->execute([$idActividad]);
        
        // IF NO APRENDICES TO ASSIGN, RETURN SUCCESS
        if (empty($aprendices)) {
            return true;
        }
        
        // INSERT NEW APRENDICES
        $insertSql = "INSERT INTO {$this->tableActividadesAprendices} (id_actividad, id_usuario) VALUES (?, ?)";
        $insertStmt = $this->conn->prepare($insertSql);
        
        try {
            $this->conn->beginTransaction();
            
            foreach ($aprendices as $idAprendiz) {
                // Validar que el ID sea numérico
                if (!is_numeric($idAprendiz)) {
                    throw new Exception("ID de aprendiz inválido: " . $idAprendiz);
                }
                
                $insertStmt->execute([$idActividad, intval($idAprendiz)]);
            }
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error asignando aprendices: " . $e->getMessage());
            return false;
        }
    }

    /* GET APRENDICES ASSIGNED TO AN ACTIVITY (OPTIONAL) */
    public function obtenerAprendicesActividad($idActividad) {
        $sql = "SELECT 
                    aa.*,
                    u.nombre_completo,
                    u.documento,
                    u.correo
                FROM {$this->tableActividadesAprendices} aa
                JOIN usuarios u ON aa.id_usuario = u.id_usuario
                WHERE aa.id_actividad = ?
                ORDER BY u.nombre_completo";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idActividad]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}