<?php

class FichaModel {

    private $conn;
    private $table = "fichas";

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /* =========================
       FICHAS
    ========================== */

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

    /* =========================
       INSTRUCTORES
    ========================== */

    /* Obtener instructores disponibles */
    public function obtenerInstructores() {
        try {
            $sql = "SELECT id_usuario, nombre_completo, correo
                    FROM usuarios
                    WHERE cargo = 'Instructor'
                      AND estado = 'activo'
                    ORDER BY nombre_completo";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }

    /* Asignar instructores a ficha */
    public function asignarInstructores($id_ficha, $instructores) {

        try {
            $this->conn->beginTransaction();

            /* Eliminar instructores actuales */
            $stmt = $this->conn->prepare(
                "DELETE FROM ficha_instructor WHERE id_ficha = ?"
            );
            $stmt->execute([$id_ficha]);

            /* Insertar nuevos instructores */
            $sqlInsert = "INSERT INTO ficha_instructor
                          (id_ficha, id_usuario, es_jefe_grupo, estado)
                          VALUES (?, ?, 0, 'Activo')";

            $stmtInsert = $this->conn->prepare($sqlInsert);

            foreach ($instructores as $id_usuario) {
                $stmtInsert->execute([$id_ficha, $id_usuario]);
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    /* Obtener instructores de una ficha */
    public function obtenerInstructoresFicha($id_ficha) {
        try {
            $sql = "SELECT 
                        fi.id_ficha_instructor,
                        u.id_usuario,
                        u.nombre_completo,
                        u.correo,
                        fi.es_jefe_grupo,
                        fi.estado,
                        fi.fecha_asignacion
                    FROM ficha_instructor fi
                    INNER JOIN usuarios u ON fi.id_usuario = u.id_usuario
                    WHERE fi.id_ficha = ?
                    ORDER BY fi.es_jefe_grupo DESC, u.nombre_completo";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id_ficha]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }

    /* Asignar JEFE DE FICHA */
    public function asignarJefeFicha($id_ficha, $id_usuario) {

        try {
            $this->conn->beginTransaction();

            /* Validar que sea instructor activo */
            $sqlValidar = "SELECT id_usuario
                           FROM usuarios
                           WHERE id_usuario = ?
                             AND cargo = 'Instructor'
                             AND estado = 'activo'";

            $stmt = $this->conn->prepare($sqlValidar);
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

            /* Verificar si ya existe */
            $sqlExiste = "SELECT id_ficha_instructor
                          FROM ficha_instructor
                          WHERE id_ficha = ? AND id_usuario = ?";

            $stmt = $this->conn->prepare($sqlExiste);
            $stmt->execute([$id_ficha, $id_usuario]);

            if ($stmt->fetch()) {

                /* Actualizar como jefe */
                $this->conn->prepare(
                    "UPDATE ficha_instructor
                     SET es_jefe_grupo = 1
                     WHERE id_ficha = ? AND id_usuario = ?"
                )->execute([$id_ficha, $id_usuario]);

            } else {

                /* Insertar como jefe */
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
