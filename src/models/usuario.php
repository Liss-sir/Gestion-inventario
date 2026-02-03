<?php

class Usuario {
    private $conn;
    private $table = "usuarios";

    // ✅ Cache interno (para no consultar SHOW COLUMNS cada vez)
    private $urfOrderCol = null;

    public function __construct($db) {
        $this->conn = $db;
    }

    /* =====================================================
       ✅ Helper: Detectar columna para ordenar roles funcionales
       - Prioriza fecha_asignacion si existe
       - Si no, intenta con id_usuario_rol o id o created_at
       - Esto evita romper si tu tabla cambia de nombre de columnas
    ===================================================== */
    private function getUrfOrderColumn(): string
    {
        if ($this->urfOrderCol !== null) {
            return $this->urfOrderCol;
        }

        try {
            $cols = [];
            $stmtCols = $this->conn->query("SHOW COLUMNS FROM usuario_roles_funcionales");
            $rowsCols = $stmtCols->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rowsCols as $r) {
                $cols[] = $r['Field'];
            }

            if (in_array('fecha_asignacion', $cols, true)) {
                $this->urfOrderCol = 'fecha_asignacion';
                return $this->urfOrderCol;
            }

            if (in_array('created_at', $cols, true)) {
                $this->urfOrderCol = 'created_at';
                return $this->urfOrderCol;
            }

            if (in_array('id_usuario_rol', $cols, true)) {
                $this->urfOrderCol = 'id_usuario_rol';
                return $this->urfOrderCol;
            }

            if (in_array('id', $cols, true)) {
                $this->urfOrderCol = 'id';
                return $this->urfOrderCol;
            }

            // ✅ Fallback seguro
            $this->urfOrderCol = 'id';
            return $this->urfOrderCol;

        } catch (\Exception $e) {
            // ✅ No romper flujo
            $this->urfOrderCol = 'id';
            return $this->urfOrderCol;
        }
    }

    // =====================================================
    // ✅ LISTAR USUARIOS (AHORA TRAE ROL FUNCIONAL)
    // =====================================================
    public function listar() {

        // ✅ Columna para ordenar el rol funcional más reciente
        $orderCol = $this->getUrfOrderColumn();

        // ✅ Traer usuarios + rol funcional asignado (último)
        $sql = "
            SELECT 
                u.*,

                (
                    SELECT urf.id_rol
                    FROM usuario_roles_funcionales urf
                    WHERE urf.id_usuario = u.id_usuario
                    ORDER BY urf.$orderCol DESC
                    LIMIT 1
                ) AS id_rol_funcional,

                (
                    SELECT rf.nombre_rol
                    FROM usuario_roles_funcionales urf2
                    INNER JOIN roles_funcionales rf ON rf.id_rol = urf2.id_rol
                    WHERE urf2.id_usuario = u.id_usuario
                    ORDER BY urf2.$orderCol DESC
                    LIMIT 1
                ) AS rol_funcional

            FROM usuarios u
            ORDER BY u.id_usuario DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        // ✅ IMPORTANTE: devuelvo el array como antes (para no romper tu controller ni frontend)
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // ✅ OBTENER USUARIO POR ID (AHORA TRAE ROL FUNCIONAL)
    // =====================================================
    public function obtenerPorId($id) {

        $orderCol = $this->getUrfOrderColumn();

        $sql = "
            SELECT 
                u.*,

                (
                    SELECT urf.id_rol
                    FROM usuario_roles_funcionales urf
                    WHERE urf.id_usuario = u.id_usuario
                    ORDER BY urf.$orderCol DESC
                    LIMIT 1
                ) AS id_rol_funcional,

                (
                    SELECT rf.nombre_rol
                    FROM usuario_roles_funcionales urf2
                    INNER JOIN roles_funcionales rf ON rf.id_rol = urf2.id_rol
                    WHERE urf2.id_usuario = u.id_usuario
                    ORDER BY urf2.$orderCol DESC
                    LIMIT 1
                ) AS rol_funcional

            FROM usuarios u
            WHERE u.id_usuario = :id_usuario
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id_usuario', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Retrieve a user by email address
    public function obtenerPorCorreo($correo) {
        $sql = "SELECT * FROM " . $this->table . " WHERE correo = :correo";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':correo', $correo);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Retrieve a user by document number
    public function obtenerPorDocumento($documento) {
        $sql = "SELECT * FROM " . $this->table . " WHERE numero_documento = :documento";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':documento', $documento);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Create a new user record and return the inserted ID
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

        // Passwords are always stored as a secure hash
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // If a verification token is provided, the account starts as inactive
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

        // Training program is optional depending on the role
        if ($id_programa === null || $id_programa === '') {
            $stmt->bindValue(':programa', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':programa', (int)$id_programa, PDO::PARAM_INT);
        }

        $ok = $stmt->execute();
        if (!$ok) return false;

        return (int)$this->conn->lastInsertId();
    }

    // Update user data (password update is optional)
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

            // Hash the new password before updating
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

        // Keep program assignment nullable when not applicable
        if ($id_programa === null || $id_programa === '') {
            $stmt->bindValue(':programa', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':programa', (int)$id_programa, PDO::PARAM_INT);
        }

        return $stmt->execute();
    }

    // Update user status (active/inactive)
    public function cambiarEstado($id_usuario, $estado) {
        $sql = "UPDATE usuarios SET estado = :estado WHERE id_usuario = :id_usuario";
        $stmt = $this->conn->prepare($sql);

        // Normalize numeric flag into the expected database value
        $estadoBD = ((int)$estado === 1) ? 'activo' : 'inactivo';

        $stmt->bindParam(':estado', $estadoBD);
        $stmt->bindParam(':id_usuario', $id_usuario);

        return $stmt->execute();
    }

    // Authenticate user credentials (only active accounts are allowed)
    public function login($correo, $password) {
        $user = $this->obtenerPorCorreo($correo);
        if (!$user) return false;

        // Reject login attempts for inactive accounts
        if (isset($user['estado']) && $user['estado'] !== 'activo') {
            return false;
        }

        // Validate password hash
        if (password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    // Verification token creation (throws detailed exception if insertion fails)
    public function crearTokenVerificacion(int $idUsuario, string $token): bool {

        // Mark previous unused verification tokens as used
        $sqlDisable = "UPDATE tokens_correo
                       SET usado = 1
                       WHERE id_usuario = :id_usuario AND tipo = 'verificar_correo' AND usado = 0";
        $stmtDisable = $this->conn->prepare($sqlDisable);
        $stmtDisable->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmtDisable->execute();

        // Insert a fresh verification token with a 24-hour validity window
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

    // Activate account using a verification token
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

        // Token must be a valid, unused email verification token
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
            // Ensure user activation and token invalidation happen atomically
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

    // Determine whether the user currently has an active session in the database
    public function hasActiveSession($idUsuario)
    {
        $sql = "SELECT 1
                FROM sesiones_usuarios
                WHERE id_usuario = ? AND activa = 1
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idUsuario]);

        return $stmt->fetch() ? true : false;
    }

    // Register a new session token for the user
    public function createSession($idUsuario, $token)
    {
        $sql = "INSERT INTO sesiones_usuarios (id_usuario, token_sesion)
                VALUES (?, ?)";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$idUsuario, $token]);
    }

    // Soft-close a session by marking it inactive
    public function closeSession($token)
    {
        $sql = "UPDATE sesiones_usuarios
                SET activa = 0
                WHERE token_sesion = ?";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$token]);
    }
}
