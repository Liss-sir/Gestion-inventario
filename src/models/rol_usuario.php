<?php
class UsuarioRolesFuncionales {
    private $conn;
    private $table = "usuario_roles_funcionales"; 

    public function __construct($db) {
        $this->conn = $db;
    }

    //Function to assign roles to users
    public function asignarRol($id_usuario, $id_rol, $asignado_por) {
        $sql = "INSERT INTO {$this->table} (id_usuario, id_rol, asignado_por) VALUES (:u, :r, :a)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":u", $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(":r", $id_rol, PDO::PARAM_INT);
        $stmt->bindValue(":a", $asignado_por, PDO::PARAM_INT);
        return $stmt->execute();
    }

    //Function to list role assignments to users
    public function listarAsignaciones() {
        $sql = "SELECT * FROM {$this->table}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Function to delete role assignment from user
    public function eliminarAsignacion($id_usuario_rol) {
        $sql = "DELETE FROM {$this->table} WHERE id_usuario_rol = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":id", $id_usuario_rol, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>
