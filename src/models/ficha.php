<?php

class FichaModel {

    private $conn;
    private $table = "fichas";

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /* List all FICHAS */
    public function listar() {
        try {
            $sql = "SELECT * FROM " . $this->table;
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /* Get FICHA by ID */
    public function obtener($id) {
        try {
            $sql = "SELECT * FROM " . $this->table . " WHERE id_ficha = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    /* Create FICHA */
    public function crear($data) {
        try {
            $sql = "INSERT INTO " . $this->table . "
                (numero_ficha, id_programa, jornada, modalidad, fecha_inicio, fecha_fin, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);

            $ok = $stmt->execute([
                $data['numero_ficha'],
                $data['id_programa'],
                $data['jornada'],
                $data['modalidad'],
                $data['fecha_inicio'],
                $data['fecha_fin'],
                isset($data['estado']) ? $data['estado'] : "Activa"
            ]);

            return $ok ? (int)$this->conn->lastInsertId() : false;

        } catch (Exception $e) {
            return false;
        }
    }

    /* Update FICHA */
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

    /* Change FICHA state */
    public function cambiarEstado($id, $estado) {
        try {
            $sql = "UPDATE " . $this->table . " SET estado = ? WHERE id_ficha = ?";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$estado, $id]);
        } catch (Exception $e) {
            return false;
        }
    }

    /* Get APRENDICES (students) */
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

    /* Add students to FICHA */
    public function agregarEstudiantes($id_ficha, $estudiantes) {
        try {
            if (empty($estudiantes) || !is_array($estudiantes)) {
                return true;
            }

            $stmtDelete = $this->conn->prepare(
                "DELETE FROM fichas_aprendices WHERE id_ficha = ?"
            );
            $stmtDelete->execute([$id_ficha]);

            $stmtInsert = $this->conn->prepare(
                "INSERT INTO fichas_aprendices (id_ficha, id_usuario) VALUES (?, ?)"
            );

            foreach ($estudiantes as $id_estudiante) {
                $stmtInsert->execute([$id_ficha, $id_estudiante]);
            }

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /* Get students of a FICHA */
    public function obtenerEstudiantesDeFicha($id_ficha) {
        try {
            $sql = "SELECT u.id_usuario, u.nombre_completo, u.numero_documento, u.correo
                    FROM fichas_aprendices fa
                    INNER JOIN usuarios u ON fa.id_usuario = u.id_usuario
                    WHERE fa.id_ficha = ?
                    ORDER BY u.nombre_completo";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id_ficha]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }

    /* Get INSTRUCTORS */
    public function obtenerInstructores() {
        try {
            $sql = "SELECT id_usuario, nombre_completo, numero_documento, correo
                    FROM usuarios
                    WHERE cargo = 'Instructor' AND estado = 'activo'
                    ORDER BY nombre_completo";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }

    /* Assign INSTRUCTORES to FICHA */
    public function asignarInstructoresFicha($id_ficha, $instructores) {
        try {
            if (empty($instructores) || !is_array($instructores)) {
                return true;
            }

            $stmtDelete = $this->conn->prepare(
                "DELETE FROM ficha_instructores WHERE id_ficha = ?"
            );
            $stmtDelete->execute([$id_ficha]);

            $stmtInsert = $this->conn->prepare(
                "INSERT INTO ficha_instructores (id_ficha, id_instructor)
                 VALUES (?, ?)"
            );

            foreach ($instructores as $id_instructor) {
                $stmtInsert->execute([$id_ficha, $id_instructor]);
            }

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

}
