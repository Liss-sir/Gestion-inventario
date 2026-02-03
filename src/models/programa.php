<?php
class Programa {
    private $conn;
    private $table = "programas_formacion";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Listar todos los programas con sus instructores
    public function listar() {
        try {
            $sql = "SELECT 
                        p.id_programa, 
                        p.codigo_programa, 
                        p.nombre_programa, 
                        p.nivel_programa, 
                        p.descripcion_programa, 
                        p.duracion_horas, 
                        p.estado,
                        GROUP_CONCAT(DISTINCT u.nombre_completo SEPARATOR '; ') AS instructores_nombres,
                        COUNT(DISTINCT u.id_usuario) AS instructores
                    FROM programas_formacion p
                    LEFT JOIN instructores_programas ip 
                        ON p.id_programa = ip.id_programa
                    LEFT JOIN usuarios u 
                        ON ip.id_usuario = u.id_usuario
                        AND LOWER(u.cargo) = 'instructor'
                        AND LOWER(u.estado) = 'activo'
                    GROUP BY p.id_programa
                    ORDER BY p.nombre_programa";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $programas = [];

            foreach ($raw as $r) {
                $programas[] = [
                    'id_programa' => $r['id_programa'],
                    'codigo'      => $r['codigo_programa'],
                    'nombre'      => $r['nombre_programa'],
                    'descripcion' => $r['descripcion_programa'],
                    'nivel'       => $r['nivel_programa'],
                    'duracion'    => $r['duracion_horas'] . ' horas',
                    'instructores_nombres' => $r['instructores_nombres'] ?: 'No hay instructores vinculados',
                    'instructores'=> (int)$r['instructores'],
                    'estado'      => $r['estado']
                ];
            }

            return $programas;

        } catch (PDOException $e) {
            return [
                'error' => 'Error al cargar programas: ' . $e->getMessage()
            ];
        }
    }

    // Obtener programa por ID
    public function obtenerPorId($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id_programa = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear programa
    public function crear($codigo, $nombre, $nivel, $descripcion, $duracion, $estado) {
        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table}
            (codigo_programa, nombre_programa, nivel_programa, descripcion_programa, duracion_horas, estado)
            VALUES (:codigo, :nombre, :nivel, :descripcion, :duracion, :estado)
        ");
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':nivel', $nivel);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':duracion', $duracion, PDO::PARAM_INT);
        $stmt->bindParam(':estado', $estado);
        return $stmt->execute();
    }

    // Actualizar programa
    public function actualizar($id, $codigo, $nombre, $nivel, $descripcion, $duracion, $estado) {
        $stmt = $this->conn->prepare("
            UPDATE {$this->table}
            SET codigo_programa = :codigo,
                nombre_programa = :nombre,
                nivel_programa = :nivel,
                descripcion_programa = :descripcion,
                duracion_horas = :duracion,
                estado = :estado
            WHERE id_programa = :id
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':nivel', $nivel);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':duracion', $duracion, PDO::PARAM_INT);
        $stmt->bindParam(':estado', $estado);
        return $stmt->execute();
    }

    // Eliminar programa
    public function eliminar($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id_programa = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    // Cambiar estado del programa
    public function cambiarEstado($id, $estado) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET estado = :estado WHERE id_programa = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':estado', $estado);
        return $stmt->execute();
    }
}
?>
