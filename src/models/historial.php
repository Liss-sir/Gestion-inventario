<?php
// src/models/historial.php

class Historial {
    private $conn;
    private $table = "audit_log"; // ✅ tu tabla real
    private $userCols = [];
    private $hasUsuariosTable = false;

    public function __construct($db) {
        $this->conn = $db;
        $this->initUsuariosMeta();
    }

    // =============================
    // Detecta columnas existentes en `usuarios`
    // (para no asumir u.nombre / u.apellido)
    // =============================
    private function initUsuariosMeta() {
        try {
            $stmt = $this->conn->prepare("SHOW COLUMNS FROM usuarios");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $cols = [];
            foreach ($rows as $r) {
                if (!empty($r["Field"])) $cols[] = $r["Field"];
            }

            $this->userCols = $cols;
            $this->hasUsuariosTable = true;
        } catch (Throwable $e) {
            // Si no existe usuarios o falla, no hacemos join
            $this->userCols = [];
            $this->hasUsuariosTable = false;
        }
    }

    private function userHas($col) {
        return in_array($col, $this->userCols, true);
    }

    // Devuelve una expresión SQL segura para el nombre del usuario
    // y otra para el cargo, según columnas reales existentes.
    private function getUserSelectExpr() {
        // nombre completo (MUY común)
        if ($this->userHas("nombre_completo")) {
            $nameExpr = "COALESCE(u.nombre_completo,'')";
        }
        // nombre + apellido (tu caso anterior)
        else if ($this->userHas("nombre") && $this->userHas("apellido")) {
            $nameExpr = "TRIM(CONCAT(COALESCE(u.nombre,''),' ',COALESCE(u.apellido,'')))";
        }
        // nombres + apellidos (otra variante común)
        else if ($this->userHas("nombres") && $this->userHas("apellidos")) {
            $nameExpr = "TRIM(CONCAT(COALESCE(u.nombres,''),' ',COALESCE(u.apellidos,'')))";
        }
        // name + last_name (inglés)
        else if ($this->userHas("name") && $this->userHas("last_name")) {
            $nameExpr = "TRIM(CONCAT(COALESCE(u.name,''),' ',COALESCE(u.last_name,'')))";
        }
        // fallback
        else {
            // si no hay columnas de nombre, al menos algo legible
            $nameExpr = "''";
        }

        // Cargo / rol
        $cargoExpr = "''";
        if ($this->userHas("cargo")) {
            $cargoExpr = "COALESCE(u.cargo,'')";
        } else if ($this->userHas("rol")) {
            $cargoExpr = "COALESCE(u.rol,'')";
        } else if ($this->userHas("role")) {
            $cargoExpr = "COALESCE(u.role,'')";
        }

        return [$nameExpr, $cargoExpr];
    }

    // =============================
    // Filtro por prefijo en detalle (acciones de negocio)
    // =============================
    private function buildDetalleFilterSql($accionUi, &$params) {
        $accionUi = trim((string)$accionUi);
        if ($accionUi === "") return "";

        $starts = [
            "Entrada"        => ["Entrada:", "[Entrada]", "Entrada|"],
            "Salida"         => ["Salida:", "[Salida]", "Salida|"],
            "Devolucion"     => ["Devolución:", "Devolucion:", "[Devolución]", "[Devolucion]", "Devolución|", "Devolucion|"],
            "Aprobacion"     => ["Aprobación:", "Aprobacion:", "[Aprobación]", "[Aprobacion]", "Aprobación|", "Aprobacion|"],
            "Rechazo"        => ["Rechazo:", "[Rechazo]", "Rechazo|"],
            "Desactivacion"  => ["Desactivación:", "Desactivacion:", "[Desactivación]", "[Desactivacion]", "Desactivación|", "Desactivacion|"],
        ];

        if (!isset($starts[$accionUi])) return "";

        $likes = [];
        $i = 0;
        foreach ($starts[$accionUi] as $prefix) {
            $key = ":det_" . $accionUi . "_" . $i;
            $likes[] = "h.detalle LIKE {$key}";
            $params[$key] = $prefix . "%";
            $i++;
        }

        return " AND (" . implode(" OR ", $likes) . ") ";
    }

    // =============================
    // LISTAR
    // =============================
    public function listar($search = "", $modulo = "", $accionCrud = "", $accionUi = "", $limit = 50, $offset = 0) {
        $params = [];

        $join = "";
        $userNameExpr = "''";
        $userCargoExpr = "''";
        $userWhereExpr = "''";

        if ($this->hasUsuariosTable) {
            [$userNameExpr, $userCargoExpr] = $this->getUserSelectExpr();

            // Si el nombre expr es vacío, al menos buscamos por usuario_id
            $userWhereExpr = ($userNameExpr !== "''") ? $userNameExpr : "CAST(h.usuario_id AS CHAR)";

            $join = " LEFT JOIN usuarios u ON u.id_usuario = h.usuario_id ";
        } else {
            // sin tabla usuarios
            $userWhereExpr = "CAST(h.usuario_id AS CHAR)";
        }

        $sql = "
            SELECT 
                h.id_audit,
                h.tabla_nombre,
                h.accion,
                h.pk_valor,
                h.usuario_id,
                h.old_values,
                h.new_values,
                h.detalle,
                h.fecha_hora,

                -- ✅ SI NO HAY NOMBRE, mostramos 'Sistema' o 'Usuario #ID'
                CASE
                  WHEN {$userNameExpr} <> '' THEN {$userNameExpr}
                  WHEN h.usuario_id IS NULL THEN 'Sistema'
                  ELSE CONCAT('Usuario #', h.usuario_id)
                END AS usuario_nombre,

                {$userCargoExpr} AS usuario_cargo
            FROM {$this->table} h
            {$join}
            WHERE 1=1
        ";

        // 🔎 Search
        if (!empty($search)) {
            $sql .= " AND (
                h.tabla_nombre LIKE :search
                OR h.detalle LIKE :search
                OR h.pk_valor LIKE :search
                OR {$userWhereExpr} LIKE :search
            ) ";
            $params[":search"] = "%" . $search . "%";
        }

        // 📦 Módulo (tabla_nombre)
        if (!empty($modulo)) {
            $sql .= " AND h.tabla_nombre = :modulo ";
            $params[":modulo"] = $modulo;
        }

        // 🧾 Acción CRUD (INSERT/UPDATE/DELETE)
        if (!empty($accionCrud)) {
            $sql .= " AND h.accion = :accionCrud ";
            $params[":accionCrud"] = strtoupper($accionCrud);
        }

        // ✅ Acciones de negocio por prefijo en detalle (Entrada/Salida/...)
        if (!empty($accionUi) && empty($accionCrud)) {
            $sql .= $this->buildDetalleFilterSql($accionUi, $params);
        }

        $sql .= " ORDER BY h.fecha_hora DESC LIMIT :limit OFFSET :offset ";

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(":limit", (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =============================
    // CONTAR
    // =============================
    public function contar($search = "", $modulo = "", $accionCrud = "", $accionUi = "") {
        $params = [];

        $join = "";
        $userNameExpr = "''";
        $userWhereExpr = "CAST(h.usuario_id AS CHAR)";

        if ($this->hasUsuariosTable) {
            [$userNameExpr, $userCargoExpr] = $this->getUserSelectExpr();
            $userWhereExpr = ($userNameExpr !== "''") ? $userNameExpr : "CAST(h.usuario_id AS CHAR)";
            $join = " LEFT JOIN usuarios u ON u.id_usuario = h.usuario_id ";
        }

        $sql = "
            SELECT COUNT(*) AS total
            FROM {$this->table} h
            {$join}
            WHERE 1=1
        ";

        if (!empty($search)) {
            $sql .= " AND (
                h.tabla_nombre LIKE :search
                OR h.detalle LIKE :search
                OR h.pk_valor LIKE :search
                OR {$userWhereExpr} LIKE :search
            ) ";
            $params[":search"] = "%" . $search . "%";
        }

        if (!empty($modulo)) {
            $sql .= " AND h.tabla_nombre = :modulo ";
            $params[":modulo"] = $modulo;
        }

        if (!empty($accionCrud)) {
            $sql .= " AND h.accion = :accionCrud ";
            $params[":accionCrud"] = strtoupper($accionCrud);
        }

        if (!empty($accionUi) && empty($accionCrud)) {
            $sql .= $this->buildDetalleFilterSql($accionUi, $params);
        }

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row["total"] ?? 0);
    }
}
