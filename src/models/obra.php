<?php

class ObraModel {

    private $conn;
    private $table = "actividades_formacion";
    private $tableActividadesAprendices = "actividades_aprendices";

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /* LISTAR CON JOINS */
    public function listar() {
        $sql = "SELECT 
                    af.*,
                    f.numero_ficha,
                    r.descripcion_rae,
                    u.nombre_completo as nombre_instructor
                FROM {$this->table} af
                LEFT JOIN fichas f ON af.id_ficha = f.id_ficha
                LEFT JOIN raes r ON af.id_rae = r.id_rae
                LEFT JOIN usuarios u ON af.id_instructor = u.id_usuario
                ORDER BY af.fecha_inicio DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* OBTENER FICHAS ACTIVAS */
    public function obtenerFichasActivas() {
        $sql = "SELECT id_ficha, numero_ficha 
                FROM fichas 
                WHERE estado = 'Activa' 
                ORDER BY numero_ficha";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo fichas: " . $e->getMessage());
            return [];
        }
    }

    /* OBTENER RAES ACTIVOS */
    public function obtenerRaesActivos() {
        $sql = "SELECT id_rae, codigo_rae, descripcion_rae 
            FROM raes 
            WHERE estado = 'Activo' 
            ORDER BY codigo_rae";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo RAEs: " . $e->getMessage());
            return [];
        }
    }

    /* OBTENER RAES VINCULADOS A LA FICHA (POR PROGRAMA) */
    public function obtenerRaesPorFicha($idFicha) {
        try {
            // Obtener el programa asociado a la ficha
            $sqlFicha = "SELECT id_programa FROM fichas WHERE id_ficha = ? LIMIT 1";
            $stmtFicha = $this->conn->prepare($sqlFicha);
            $stmtFicha->execute([$idFicha]);
            $ficha = $stmtFicha->fetch(PDO::FETCH_ASSOC);

            if (!$ficha || empty($ficha['id_programa'])) {
                return [];
            }

            $idPrograma = $ficha['id_programa'];

            $sql = "SELECT id_rae, codigo_rae, descripcion_rae
                    FROM raes
                    WHERE id_programa = ?
                    AND estado = 'Activo'
                    ORDER BY codigo_rae";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$idPrograma]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo RAEs por ficha: " . $e->getMessage());
            return [];
        }
    }

    /* OBTENER INSTRUCTORES ACTIVOS */
    public function obtenerInstructoresActivos() {
        $sql = "SELECT id_usuario, nombre_completo
                FROM usuarios 
                WHERE cargo = 'instructor' 
                AND estado = 'activo'
                ORDER BY nombre_completo";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo instructores: " . $e->getMessage());
            return [];
        }
    }

    /* OBTENER INSTRUCTORES POR FICHA */
    public function obtenerInstructoresPorFicha($idFicha) {
        $sql = "SELECT u.id_usuario, u.nombre_completo
                FROM usuarios u
                INNER JOIN fichas_instructores fi ON u.id_usuario = fi.id_usuario
                WHERE fi.id_ficha = ? 
                AND u.cargo = 'instructor'
                AND u.estado = 'activo'
                AND fi.estado = 'Activo'
                ORDER BY u.nombre_completo";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$idFicha]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo instructores por ficha: " . $e->getMessage());
            return [];
        }
    }

    /* OBTENER APRENDICES DE UNA FICHA */
    public function obtenerAprendicesFicha($idFicha) {
        $sql = "SELECT 
                    u.id_usuario,
                    u.nombre_completo,
                    u.numero_documento as documento,
                    u.estado
                FROM fichas_aprendices fa
                INNER JOIN usuarios u ON fa.id_usuario = u.id_usuario
                WHERE fa.id_ficha = ? 
                AND u.cargo = 'aprendiz'
                AND u.estado = 'activo'
                AND fa.estado = 'Activo'
                ORDER BY u.nombre_completo";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idFicha]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* OBTENER POR ID */
    public function obtener($id) {
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

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* CREAR */
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

    /* ACTUALIZAR */
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

    /* CAMBIAR ESTADO */
    public function cambiarEstado($id, $estado) {
        $sql = "UPDATE {$this->table} SET estado = ? WHERE id_actividad = ?";
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$estado, $id]);
    }

    /* ASIGNAR APRENDICES A UNA ACTIVIDAD */
    public function asignarAprendices($idActividad, $aprendices) {
        // Eliminar asignaciones anteriores (si las hay)
        $deleteSql = "DELETE FROM {$this->tableActividadesAprendices} WHERE id_actividad = ?";
        $deleteStmt = $this->conn->prepare($deleteSql);
        $deleteStmt->execute([$idActividad]);
        
        // Si no hay aprendices para asignar, retornar éxito
        if (empty($aprendices)) {
            return true;
        }
        
        // Insertar nuevos aprendices
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
}