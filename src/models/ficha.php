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

    public function obtenerAprendices($id_ficha = null) {
        try {
            $sql = "SELECT u.id_usuario, u.nombre_completo, u.numero_documento, u.correo
                    FROM usuarios u
                    WHERE u.cargo = 'Aprendiz' AND u.estado = 'activo'";

            if ($id_ficha) {
                $sql .= " AND NOT EXISTS (
                            SELECT 1
                            FROM fichas_aprendices fa
                            WHERE fa.id_usuario = u.id_usuario
                            AND fa.id_ficha <> ?
                          )";
            } else {
                $sql .= " AND NOT EXISTS (
                            SELECT 1
                            FROM fichas_aprendices fa
                            WHERE fa.id_usuario = u.id_usuario
                          )";
            }

            $sql .= " ORDER BY u.nombre_completo";

            $stmt = $this->conn->prepare($sql);
            $id_ficha ? $stmt->execute([$id_ficha]) : $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }

    public function agregarEstudiantes($id_ficha, $estudiantes) {
        try {
            if (empty($estudiantes) || !is_array($estudiantes)) {
                $this->conn->prepare(
                    "DELETE FROM fichas_aprendices WHERE id_ficha = ?"
                )->execute([$id_ficha]);

                return ["success" => true];
            }

            $placeholders = implode(",", array_fill(0, count($estudiantes), "?"));
            $params = array_values($estudiantes);
            $params[] = $id_ficha;

            $sqlConflicto = "SELECT DISTINCT id_usuario
                            FROM fichas_aprendices
                            WHERE id_usuario IN ($placeholders)
                            AND id_ficha <> ?";

            $stmtConflicto = $this->conn->prepare($sqlConflicto);
            $stmtConflicto->execute($params);
            $conflictos = $stmtConflicto->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($conflictos)) {
                return [
                    "success" => false,
                    "error" => "Hay aprendices que ya pertenecen a otra ficha."
                ];
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

            return ["success" => true];

        } catch (Exception $e) {
            return ["success" => false, "error" => "Error al asignar aprendices."];
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

    public function obtenerInstructoresPorPrograma($id_programa) {
        try { 
            $sql = "SELECT DISTINCT u.id_usuario, u.nombre_completo, u.numero_documento, u.correo
                    FROM usuarios u
                    INNER JOIN instructores_programas ip ON u.id_usuario = ip.id_usuario
                    WHERE u.cargo = 'Instructor' 
                    AND u.estado = 'activo'
                    AND ip.id_programa = ?
                    ORDER BY u.nombre_completo";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id_programa]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function obtenerInstructoresDeFicha($id_ficha) {
        try {
            $sql = "SELECT 
                        u.id_usuario, 
                        u.nombre_completo, 
                        u.numero_documento, 
                        u.correo,
                        fi.es_jefe_grupo
                    FROM fichas_instructores fi
                    INNER JOIN usuarios u ON fi.id_usuario = u.id_usuario
                    WHERE fi.id_ficha = ? AND fi.estado = 'Activo'
                    ORDER BY u.nombre_completo";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id_ficha]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }

    public function asignarInstructoresFicha($id_ficha, $instructores) {
        try {
            // Eliminar instructores anteriores
            $this->conn->prepare(
                "DELETE FROM fichas_instructores WHERE id_ficha = ?"
            )->execute([$id_ficha]);

            // Insertar todos los instructores seleccionados
            $stmt = $this->conn->prepare(
                "INSERT INTO fichas_instructores 
                 (id_ficha, id_usuario, es_jefe_grupo, estado, fecha_asignacion) 
                 VALUES (?, ?, 0, 'Activo', NOW())"
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

            // Validar que sea instructor y esté asignado a la ficha
            $stmt = $this->conn->prepare(
                "SELECT fi.id_ficha_instructor 
                 FROM fichas_instructores fi
                 INNER JOIN usuarios u ON fi.id_usuario = u.id_usuario
                 WHERE fi.id_ficha = ? AND fi.id_usuario = ? 
                 AND u.cargo = 'Instructor' AND u.estado = 'activo'"
            );
            $stmt->execute([$id_ficha, $id_usuario]);

            // Si el instructor no está asignado a la ficha, primero lo asignamos
            if (!$stmt->fetch()) {
                $stmtInsert = $this->conn->prepare(
                    "INSERT INTO fichas_instructores 
                     (id_ficha, id_usuario, es_jefe_grupo, estado, fecha_asignacion)
                     VALUES (?, ?, 1, 'Activo', NOW())"
                );
                $stmtInsert->execute([$id_ficha, $id_usuario]);
            } else {
                // Quitar jefe actual
                $this->conn->prepare(
                    "UPDATE fichas_instructores
                     SET es_jefe_grupo = 0
                     WHERE id_ficha = ?"
                )->execute([$id_ficha]);

                // Establecer nuevo jefe
                $this->conn->prepare(
                    "UPDATE fichas_instructores
                     SET es_jefe_grupo = 1
                     WHERE id_ficha = ? AND id_usuario = ?"
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