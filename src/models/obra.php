<?php

class ObraModel {

    private $conn;
    private $table = "actividades_formacion";

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
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* OBTENER RAES ACTIVOS */
    public function obtenerRaesActivos() {
        $sql = "SELECT id_rae, descripcion_rae 
                FROM raes 
                WHERE estado = 'Activo' 
                ORDER BY descripcion_rae";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* OBTENER INSTRUCTORES ACTIVOS */
    public function obtenerInstructoresActivos() {
        $sql = "SELECT id_usuario, nombre_completo
                FROM usuarios 
                WHERE cargo = 'instructor' AND estado = 'Activo' 
                ORDER BY nombre_completo";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

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

        return $stmt->execute([
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
}