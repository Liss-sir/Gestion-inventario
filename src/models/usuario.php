<?php

class Usuario {
    private $conn;
    private $table = "usuarios";

    public function __construct($db) {
        $this->conn = $db;
    }

    // LISTAR
    public function listar() {
        $sql = "SELECT * FROM " . $this->table;
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // OBTENER POR ID
    public function obtenerPorId($id) {
        $sql = "SELECT * FROM " . $this->table . " WHERE id_usuario = :id_usuario";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id_usuario', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // OBTENER POR CORREO
    public function obtenerPorCorreo($correo) {
        $sql = "SELECT * FROM " . $this->table . " WHERE correo = :correo";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':correo', $correo);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // OBTENER POR DOCUMENTO
    public function obtenerPorDocumento($documento) {
        $sql = "SELECT * FROM " . $this->table . " WHERE numero_documento = :documento";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':documento', $documento);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // CREAR (devuelve ID)
    public function crear(
        $nombre,
        $tipo_doc,
        $num_doc,
        $telefono,
        $cargo,
        $correo,
        $direccion,
        $password,
        $token = null,
        $id_programa = null
    ) {
        $sql = "INSERT INTO usuarios
                (nombre_completo, tipo_documento, numero_documento, telefono, cargo, correo, password, direccion, estado, id_programa)
                VALUES (:nombre, :tipo_doc, :num_doc, :telefono, :cargo, :correo, :password, :direccion, :estado, :programa)";

        $stmt = $this->conn->prepare($sql);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $estado = ($token !== null && $token !== '') ? 'inactivo' : 'activo';

        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':tipo_doc', $tipo_doc);
        $stmt->bindParam(':num_doc', $num_doc);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':cargo', $cargo);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':password', $hash);
        $stmt->bindParam(':direccion', $direccion);
        $stmt->bindParam(':estado', $estado);

        if ($id_programa === null || $id_programa === '') {
            $stmt->bindValue(':programa', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':programa', (int)$id_programa, PDO::PARAM_INT);
        }

        $ok = $stmt->execute();
        if (!$ok) return false;

        return (int)$this->conn->lastInsertId();
    }

    // ACTUALIZAR
    public function actualizar($id_usuario, $nombre, $tipo_doc, $num_doc, $telefono, $cargo, $correo, $password, $direccion, $id_programa = null) {

        if ($password !== null && $password !== "") {
            $sql = "UPDATE usuarios SET
                    nombre_completo = :nombre,
                    tipo_documento = :tipo_doc,
                    numero_documento = :num_doc,
                    telefono = :telefono,
                    cargo = :cargo,
                    correo = :correo,
                    password = :password,
                    direccion = :direccion,
                    id_programa = :programa
                WHERE id_usuario = :id_usuario";

            $stmt = $this->conn->prepare($sql);

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bindParam(':password', $hash);

        } else {
            $sql = "UPDATE usuarios SET
                    nombre_completo = :nombre,
                    tipo_documento = :tipo_doc,
                    numero_documento = :num_doc,
                    telefono = :telefono,
                    cargo = :cargo,
                    correo = :correo,
                    direccion = :direccion,
                    id_programa = :programa
                WHERE id_usuario = :id_usuario";

            $stmt = $this->conn->prepare($sql);
        }

        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':tipo_doc', $tipo_doc);
        $stmt->bindParam(':num_doc', $num_doc);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':cargo', $cargo);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':direccion', $direccion);

        if ($id_programa === null || $id_programa === '') {
            $stmt->bindValue(':programa', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':programa', (int)$id_programa, PDO::PARAM_INT);
        }

        return $stmt->execute();
    }

    // CAMBIAR ESTADO
    public function cambiarEstado($id_usuario, $estado) {
        $sql = "UPDATE usuarios SET estado = :estado WHERE id_usuario = :id_usuario";
        $stmt = $this->conn->prepare($sql);

        $estadoBD = ((int)$estado === 1) ? 'activo' : 'inactivo';

        $stmt->bindParam(':estado', $estadoBD);
        $stmt->bindParam(':id_usuario', $id_usuario);

        return $stmt->execute();
    }

    // LOGIN (solo si activo)
    public function login($correo, $password) {
        $user = $this->obtenerPorCorreo($correo);
        if (!$user) return false;

        if (isset($user['estado']) && $user['estado'] !== 'activo') {
            return false;
        }

        if (password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    // TOKEN: crear token (con debug si falla)
    public function crearTokenVerificacion(int $idUsuario, string $token): bool {

    // invalidar tokens anteriores del mismo tipo
    $sqlDisable = "UPDATE tokens_correo
                   SET usado = 1
                   WHERE id_usuario = :id_usuario AND tipo = 'verificar_correo' AND usado = 0";
    $stmtDisable = $this->conn->prepare($sqlDisable);
    $stmtDisable->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
    $stmtDisable->execute();

    // insertar token nuevo
    $sql = "INSERT INTO tokens_correo
            (id_usuario, token, tipo, fecha_creacion, fecha_expiracion, usado)
            VALUES
            (:id_usuario, :token, 'verificar_correo', NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR), 0)";

    $stmt = $this->conn->prepare($sql);
    $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
    $stmt->bindValue(':token', $token, PDO::PARAM_STR);

    $ok = $stmt->execute();
    if (!$ok) {
        $err = $stmt->errorInfo();
        throw new Exception("Error insert token: " . ($err[2] ?? 'desconocido'));
    }

    return true;
}


    // ACTIVAR CUENTA por token
    public function activarCuenta(string $token): bool {

    $sql = "SELECT id_usuario, usado, fecha_expiracion, tipo
            FROM tokens_correo
            WHERE token = :token
            LIMIT 1";

    $stmt = $this->conn->prepare($sql);
    $stmt->bindValue(':token', $token, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) return false;

    // ✅ ahora debe ser verificar_correo
    if (($row['tipo'] ?? '') !== 'verificar_correo') return false;
    if ((int)$row['usado'] === 1) return false;
    if (strtotime($row['fecha_expiracion']) < time()) return false;

    $idUsuario = (int)$row['id_usuario'];

    $sqlU = "UPDATE usuarios SET estado = 'activo' WHERE id_usuario = :id_usuario";
    $stmtU = $this->conn->prepare($sqlU);
    $stmtU->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);

    $sqlT = "UPDATE tokens_correo
             SET usado = 1
             WHERE token = :token AND tipo = 'verificar_correo'";
    $stmtT = $this->conn->prepare($sqlT);
    $stmtT->bindValue(':token', $token, PDO::PARAM_STR);

    try {
        $this->conn->beginTransaction();

        if (!$stmtU->execute()) {
            $this->conn->rollBack();
            return false;
        }

        if (!$stmtT->execute()) {
            $this->conn->rollBack();
            return false;
        }

        $this->conn->commit();
        return true;

    } catch (\Throwable $e) {
        if ($this->conn->inTransaction()) $this->conn->rollBack();
        return false;
    }
}
}
