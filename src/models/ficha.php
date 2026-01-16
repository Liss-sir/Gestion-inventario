<?php

class FichaModel {

    private $conn;
    private $table = "fichas";

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /*List all FICHAS*/
    public function listar() {
        try {
            $sql = "SELECT * FROM " . $this->table;
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /*Get FICHA for ID*/
    public function obtener($id) {
        try {
            $sql = "SELECT * FROM " . $this->table . " WHERE id_ficha = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /*Create FICHA*/
    public function crear($data) {
        try {
            $sql = "INSERT INTO " . $this->table . "
                (numero_ficha, id_programa, jornada, modalidad, fecha_inicio, fecha_fin, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);

            $result = $stmt->execute([
                $data['numero_ficha'],
                $data['id_programa'],
                $data['jornada'],
                $data['modalidad'],
                $data['fecha_inicio'],
                $data['fecha_fin'],
                isset($data['estado']) ? $data['estado'] : "Activa"
            ]);

            if ($result) {
                return (int)$this->conn->lastInsertId();
            }
            
            return false;

        } catch (Exception $e) {
            return false;
        }
    }

    /*Update FICHA*/
    public function actualizar($data) {
        try {
            $sql = "UPDATE " . $this->table . "
                SET numero_ficha = ?, 
                    id_programa = ?, 
                    jornada = ?, 
                    modalidad = ?, 
                    fecha_inicio = ?, 
                    fecha_fin = ?, 
                    estado = ?
                WHERE id_ficha = ?";

            $stmt = $this->conn->prepare($sql);

            return $stmt->execute([
                $data['numero_ficha'],
                $data['id_programa'],
                $data['jornada'],
                $data['modalidad'],
                $data['fecha_inicio'],
                $data['fecha_fin'],
                $data['estado'],
                $data['id_ficha']
            ]);

        } catch (Exception $e) {
            return false;
        }
    }

    public function cambiarEstado($id, $estado) {
        try {
            $sql = "UPDATE " . $this->table . " SET estado = ? WHERE id_ficha = ?";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$estado, $id]);

        } catch (Exception $e) {
            return false;
        }
    }

    /*Get APRENDICES (students)*/
    public function obtenerAprendices() {
        try {
            $sql = "SELECT id_usuario, nombre_completo, numero_documento, correo 
                    FROM usuarios 
                    WHERE cargo = 'Aprendiz' AND estado = 'activo' 
                    ORDER BY nombre_completo";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }

    /*Add STUDENTS to FICHA*/
    public function agregarEstudiantes($id_ficha, $estudiantes) {
        try {
            if (empty($estudiantes) || !is_array($estudiantes)) {
                return true; // No hay estudiantes que agregar
            }

            $sqlDelete = "DELETE FROM fichas_aprendices WHERE id_ficha = ?";
            $stmtDelete = $this->conn->prepare($sqlDelete);
            $stmtDelete->execute([$id_ficha]);

            $sql = "INSERT INTO fichas_aprendices (id_ficha, id_usuario) VALUES (?, ?)";
            $stmt = $this->conn->prepare($sql);

            foreach ($estudiantes as $id_estudiante) {
                $stmt->execute([$id_ficha, $id_estudiante]);
            }

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /*Get STUDENTS of a FICHA*/
    public function obtenerEstudiantesDeFicha($id_ficha) {
        try {
            $sql = "SELECT u.id_usuario, u.nombre_completo, u.numero_documento, u.correo
                    FROM fichas_aprendices fe
                    INNER JOIN usuarios u ON fe.id_usuario = u.id_usuario
                    WHERE fe.id_ficha = ?
                    ORDER BY u.nombre_completo";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id_ficha]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }

}