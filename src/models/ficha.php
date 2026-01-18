<?php

class FichaModel {

    private $conn;
    private $table = "fichas";

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /* ================= FICHAS ================= */

    public function listar() {
        try {
            $sql = "SELECT * FROM fichas";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function obtener($id) {
        try {
            $sql = "SELECT * FROM fichas WHERE id_ficha = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    public function crear($data) {
        try {
            $sql = "INSERT INTO fichas
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
                $data['estado'] ?? 'Activa'
            ]);

            return $ok ? (int)$this->conn->lastInsertId() : false;

        } catch (Exception $e) {
            return false;
        }
    }

    public function actualizar($data) {
        try {
            $sql = "UPDATE fichas SET
                    numero_ficha = ?,
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
            $sql = "UPDATE fichas SET estado = ? WHERE id_ficha = ?";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$estado, $id]);
        } catch (Exception $e) {
            return false;
        }
    }

    /* ================= APRENDICES ================= */

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

    public function agregarEstudiantes($id_ficha, $estudiantes) {
        try {
            if (empty($estudiantes) || !is_array($estudiantes)) {
                return true;
            }

            $this->conn->prepare(
                "DELETE FROM fichas_aprendices WHERE id_ficha = ?"
            )->execute([$id_ficha]);

            $stmt = $this->conn->prepare(
                "INSERT INTO fichas_aprendices (id_ficha, id_usuario) VALUES (?, ?)"
            );

            foreach ($estudiantes as $id_estudiante) {
                $stmt->execute([$id_ficha, $id_estudiante]);
            }

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

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

    /* ================= INSTRUCTORES ================= */

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

    public function asignarInstructoresFicha($id_ficha, $instructores) {
        try {
            if (empty($instructores) || !is_array($instructores)) {
                return true;
            }

            $this->conn->prepare(
                "DELETE FROM ficha_instructor WHERE id_ficha = ?"
            )->execute([$id_ficha]);

            $stmt = $this->conn->prepare(
                "INSERT INTO ficha_instructor
                 (id_ficha, id_usuario, es_jefe_grupo, estado)
                 VALUES (?, ?, 0, 'Activo')"
            );

            foreach ($instructores as $id_usuario) {
                $stmt->execute([$id_ficha, $id_usuario]);
            }

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /* ================= JEFE DE FICHA ================= */

    public function asignarJefeFicha($id_ficha, $id_usuario) {

        try {
            $this->conn->beginTransaction();

            /* Validar que sea instructor */
            $stmt = $this->conn->prepare(
                "SELECT id_usuario FROM usuarios
                 WHERE id_usuario = ? AND cargo = 'Instructor' AND estado = 'activo'"
            );
            $stmt->execute([$id_usuario]);

            if (!$stmt->fetch()) {
                $this->conn->rollBack();
                return false;
            }

            /* Quitar jefe actual */
            $this->conn->prepare(
                "UPDATE ficha_instructor
                 SET es_jefe_grupo = 0
                 WHERE id_ficha = ?"
            )->execute([$id_ficha]);

            /* Verificar si ya está asignado */
            $stmt = $this->conn->prepare(
                "SELECT id_ficha_instructor
                 FROM ficha_instructor
                 WHERE id_ficha = ? AND id_usuario = ?"
            );
            $stmt->execute([$id_ficha, $id_usuario]);

            if ($stmt->fetch()) {
                $this->conn->prepare(
                    "UPDATE ficha_instructor
                     SET es_jefe_grupo = 1
                     WHERE id_ficha = ? AND id_usuario = ?"
                )->execute([$id_ficha, $id_usuario]);
            } else {
                $this->conn->prepare(
                    "INSERT INTO ficha_instructor
                     (id_ficha, id_usuario, es_jefe_grupo, estado)
                     VALUES (?, ?, 1, 'Activo')"
                )->execute([$id_ficha, $id_usuario]);
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
