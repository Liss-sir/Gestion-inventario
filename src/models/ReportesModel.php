<?php
// src/models/ReportesModel.php

class ReportesModel
{
    private PDO $db;

    public function __construct(PDO $conn)
    {
        $this->db = $conn;
    }

    // ==========================
    // Helpers
    // ==========================
    private function normalizeDate(?string $date): ?string
    {
        if (!$date) return null;
        $d = date_create($date);
        return $d ? $d->format("Y-m-d") : null;
    }

    /**
     * ✅ buildFilters mejorado:
     * Permite controlar qué columna se usa para filtrar programa/ficha.
     * Esto evita problemas cuando m.id_programa viene NULL pero el programa real está en fichas.
     */
    private function buildFilters(array $filters, array $columns = []): array
    {
        $fechaInicio = $this->normalizeDate($filters["fecha_inicio"] ?? null);
        $fechaFin    = $this->normalizeDate($filters["fecha_fin"] ?? null);
        $programaId  = $filters["programa"] ?? "all";
        $fichaId     = $filters["ficha"] ?? "all";

        // ✅ Columnas por defecto
        $dateCol     = $columns["date"] ?? "m.fecha_hora";
        $programaCol = $columns["programa"] ?? "m.id_programa";
        $fichaCol    = $columns["ficha"] ?? "m.id_ficha";

        $where = [];
        $params = [];

        if ($fechaInicio) {
            $where[] = "DATE($dateCol) >= ?";
            $params[] = $fechaInicio;
        }
        if ($fechaFin) {
            $where[] = "DATE($dateCol) <= ?";
            $params[] = $fechaFin;
        }

        if ($programaId !== "all" && $programaId !== "" && is_numeric($programaId)) {
            $where[] = "$programaCol = ?";
            $params[] = (int)$programaId;
        }

        if ($fichaId !== "all" && $fichaId !== "" && is_numeric($fichaId)) {
            $where[] = "$fichaCol = ?";
            $params[] = (int)$fichaId;
        }

        $sqlWhere = "";
        if (!empty($where)) {
            $sqlWhere = "WHERE " . implode(" AND ", $where);
        }

        return [$sqlWhere, $params];
    }

    // =====================================================
    // ✅ Helpers avanzados (para que TODO funcione aunque cambie tu BD)
    // =====================================================
    private function columnExists(string $table, string $column): bool
    {
        try {
            $sql = "
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
            ";
            $st = $this->db->prepare($sql);
            $st->execute([$table, $column]);
            return (int)$st->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $sql = "
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
            ";
            $st = $this->db->prepare($sql);
            $st->execute([$table]);
            return (int)$st->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * ✅ Filtro robusto para programas:
     * - movimientos puede venir con m.id_programa NULL
     * - entonces tomamos el programa desde fichas: f.id_programa
     * - si el usuario filtra programa, aplicamos: (f.id_programa = ? OR m.id_programa = ?)
     */
    private function buildFiltersProgramFlexible(array $filters): array
    {
        $fechaInicio = $this->normalizeDate($filters["fecha_inicio"] ?? null);
        $fechaFin    = $this->normalizeDate($filters["fecha_fin"] ?? null);
        $programaId  = $filters["programa"] ?? "all";
        $fichaId     = $filters["ficha"] ?? "all";

        $where = [];
        $params = [];

        if ($fechaInicio) {
            $where[] = "DATE(m.fecha_hora) >= ?";
            $params[] = $fechaInicio;
        }
        if ($fechaFin) {
            $where[] = "DATE(m.fecha_hora) <= ?";
            $params[] = $fechaFin;
        }

        if ($programaId !== "all" && $programaId !== "" && is_numeric($programaId)) {
            $where[] = "(f.id_programa = ? OR m.id_programa = ?)";
            $params[] = (int)$programaId;
            $params[] = (int)$programaId;
        }

        if ($fichaId !== "all" && $fichaId !== "" && is_numeric($fichaId)) {
            $where[] = "m.id_ficha = ?";
            $params[] = (int)$fichaId;
        }

        $sqlWhere = "";
        if (!empty($where)) {
            $sqlWhere = "WHERE " . implode(" AND ", $where);
        }

        return [$sqlWhere, $params];
    }

    // ==========================
    // ✅ Combos
    // ==========================
    public function getProgramas(): array
    {
        $sql = "SELECT id_programa AS id, nombre_programa AS nombre
                FROM programas_formacion
                ORDER BY nombre_programa ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getFichas(): array
    {
        $sql = "SELECT id_ficha AS id, numero_ficha AS numero
                FROM fichas
                ORDER BY numero_ficha DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ==========================
    // ✅ Consumo vs “Devoluciones” (Entrada)
    // FIX ONLY_FULL_GROUP_BY ✅
    // ==========================
    public function getConsumoPorMes(array $filters): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = "
            SELECT 
                DATE_FORMAT(m.fecha_hora, '%Y-%m') AS ym,
                DATE_FORMAT(m.fecha_hora, '%b') AS mes,
                SUM(CASE WHEN m.tipo_movimiento = 'Salida' THEN m.cantidad ELSE 0 END) AS consumo,
                SUM(CASE WHEN m.tipo_movimiento = 'Entrada' THEN m.cantidad ELSE 0 END) AS devoluciones
            FROM movimientos_material m
            $where
            GROUP BY ym, mes
            ORDER BY ym ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [
            "Jan" => "Ene", "Feb" => "Feb", "Mar" => "Mar", "Apr" => "Abr",
            "May" => "May", "Jun" => "Jun", "Jul" => "Jul", "Aug" => "Ago",
            "Sep" => "Sep", "Oct" => "Oct", "Nov" => "Nov", "Dec" => "Dic"
        ];

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                "mes" => $map[$r["mes"]] ?? $r["mes"],
                "consumo" => (int)$r["consumo"],
                "devoluciones" => (int)$r["devoluciones"],
            ];
        }

        return $out;
    }

    // ==========================
    // ✅ Distribución por Programa (PORCENTAJE)
    // ==========================
    public function getConsumoPorPrograma(array $filters): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $extraWhere = "";
        if ($where) {
            $extraWhere = $where . " AND m.id_programa IS NOT NULL";
        } else {
            $extraWhere = "WHERE m.id_programa IS NOT NULL";
        }

        $sql = "
            SELECT 
                p.nombre_programa AS name,
                SUM(CASE WHEN m.tipo_movimiento = 'Salida' THEN m.cantidad ELSE 0 END) AS total_consumo
            FROM movimientos_material m
            INNER JOIN programas_formacion p ON p.id_programa = m.id_programa
            $extraWhere
            GROUP BY p.id_programa, p.nombre_programa
            ORDER BY total_consumo DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $total = 0;
        foreach ($rows as $r) $total += (int)$r["total_consumo"];
        if ($total <= 0) return [];

        $out = [];
        foreach ($rows as $r) {
            $percent = round(((int)$r["total_consumo"] * 100) / $total);
            $out[] = [
                "name"  => $r["name"],
                "value" => (int)$percent,
                "color" => null
            ];
        }

        // ✅ Top 3 + Otros
        if (count($out) > 4) {
            $top = array_slice($out, 0, 3);
            $rest = array_slice($out, 3);

            $otros = 0;
            foreach ($rest as $x) $otros += (int)$x["value"];

            $top[] = ["name" => "Otros", "value" => $otros, "color" => null];
            $out = $top;
        }

        return $out;
    }

    // ==========================
    // ✅ Materiales más usados
    // ==========================
    public function getMaterialesMasUsados(array $filters): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = "
            SELECT 
                mat.nombre AS nombre,
                SUM(CASE WHEN m.tipo_movimiento = 'Salida' THEN m.cantidad ELSE 0 END) AS cantidad
            FROM movimientos_material m
            INNER JOIN material_formacion mat ON mat.id_material = m.id_material
            $where
            GROUP BY mat.id_material, mat.nombre
            HAVING cantidad > 0
            ORDER BY cantidad DESC
            LIMIT 5
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) $r["cantidad"] = (int)$r["cantidad"];
        return $rows;
    }

    // ==========================
    // ✅ Consumo por Ficha (REAL)
    // ==========================
    public function getConsumoPorFicha(array $filters): array
    {
        [$where, $params] = $this->buildFilters($filters, [
            "date"     => "m.fecha_hora",
            "ficha"    => "m.id_ficha",
            "programa" => "p.id_programa"
        ]);

        $extra = $where ? " AND m.id_ficha IS NOT NULL" : "WHERE m.id_ficha IS NOT NULL";

        $sql = "
            SELECT 
                f.numero_ficha AS ficha,
                p.nombre_programa AS programa,
                SUM(m.cantidad) AS consumo,
                SUM(m.cantidad * mat.precio) AS costo
            FROM movimientos_material m
            INNER JOIN fichas f ON f.id_ficha = m.id_ficha
            INNER JOIN programas_formacion p ON p.id_programa = f.id_programa
            INNER JOIN material_formacion mat ON mat.id_material = m.id_material
            $where
            $extra
            AND m.tipo_movimiento = 'Salida'
            GROUP BY f.id_ficha, f.numero_ficha, p.id_programa, p.nombre_programa
            HAVING consumo > 0
            ORDER BY consumo DESC
            LIMIT 50
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $r["consumo"] = (int)$r["consumo"];
            $r["costo"]   = (int)$r["costo"];
        }

        return $rows;
    }

    // =====================================================
    // ✅ NUEVOS REPORTES (PDF CARDS)
    // =====================================================

    /**
     * ✅ Consumo REAL por Programa (para PDF)
     * - usa programa desde fichas si m.id_programa viene null
     */
    public function getConsumoPorProgramaDetalle(array $filters): array
    {
        [$where, $params] = $this->buildFiltersProgramFlexible($filters);

        $sql = "
            SELECT 
                COALESCE(pf.nombre_programa, pm.nombre_programa, 'Sin programa') AS programa,
                SUM(CASE WHEN m.tipo_movimiento='Salida' THEN m.cantidad ELSE 0 END) AS consumo,
                SUM(CASE WHEN m.tipo_movimiento='Salida' THEN (m.cantidad * mat.precio) ELSE 0 END) AS costo
            FROM movimientos_material m
            LEFT JOIN fichas f ON f.id_ficha = m.id_ficha
            LEFT JOIN programas_formacion pf ON pf.id_programa = f.id_programa
            LEFT JOIN programas_formacion pm ON pm.id_programa = m.id_programa
            INNER JOIN material_formacion mat ON mat.id_material = m.id_material
            $where
            GROUP BY programa
            HAVING consumo > 0
            ORDER BY consumo DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $r["consumo"] = (int)$r["consumo"];
            $r["costo"]   = (int)$r["costo"];
        }

        return $rows;
    }

    /**
     * ✅ Consumo REAL por RAE (si existe columna y tabla)
     * - si no existe, devuelve [] (el controller mostrará “no disponible”)
     */

public function getConsumoPorRAE(array $filters): array
{
    // ✅ Si la columna no existe en movimientos => no hay forma de sacar RAE
    if (!$this->columnExists("movimientos_material", "id_rae")) {
        return [];
    }

    // ✅ Tu tabla real (según captura)
    $raeTable = "raes";
    if (!$this->tableExists($raeTable)) {
        return [];
    }

    // ✅ Confirmar columnas reales
    $hasId   = $this->columnExists($raeTable, "id_rae");
    $hasCod  = $this->columnExists($raeTable, "codigo_rae");
    $hasDesc = $this->columnExists($raeTable, "descripcion_rae");

    if (!$hasId) return [];

    // ✅ Usamos filtros flexibles para que programa funcione aunque m.id_programa venga NULL
    // (porque tu programa real a veces está en fichas)
    [$where, $params] = $this->buildFiltersProgramFlexible($filters);

    // ✅ Siempre exige id_rae y consumo real (Salida)
    $extra = $where ? " AND m.id_rae IS NOT NULL" : "WHERE m.id_rae IS NOT NULL";

    // ✅ Nombre del RAE: "CODIGO - DESCRIPCION" (si existen columnas)
    $raeLabelExpr = "r.id_rae";
    if ($hasCod && $hasDesc) {
        $raeLabelExpr = "CONCAT(r.codigo_rae, ' - ', r.descripcion_rae)";
    } elseif ($hasDesc) {
        $raeLabelExpr = "r.descripcion_rae";
    } elseif ($hasCod) {
        $raeLabelExpr = "r.codigo_rae";
    }

    $sql = "
        SELECT
            $raeLabelExpr AS rae,
            SUM(CASE WHEN m.tipo_movimiento='Salida' THEN m.cantidad ELSE 0 END) AS consumo,
            SUM(CASE WHEN m.tipo_movimiento='Salida' THEN (m.cantidad * mat.precio) ELSE 0 END) AS costo
        FROM movimientos_material m
        INNER JOIN $raeTable r ON r.id_rae = m.id_rae
        INNER JOIN material_formacion mat ON mat.id_material = m.id_material
        LEFT JOIN fichas f ON f.id_ficha = m.id_ficha
        $where
        $extra
        GROUP BY r.id_rae
        HAVING consumo > 0
        ORDER BY consumo DESC
    ";

    $st = $this->db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$r) {
        $r["consumo"] = (int)$r["consumo"];
        $r["costo"]   = (int)$r["costo"];
    }

    return $rows;
}


    /**
     * ✅ Historial REAL de Movimientos
     */
    public function getMovimientosDetalle(array $filters, int $limit = 300): array
    {
        [$where, $params] = $this->buildFiltersProgramFlexible($filters);

        $sql = "
            SELECT
                m.fecha_hora AS fecha,
                m.tipo_movimiento AS tipo,
                mat.nombre AS material,
                m.cantidad AS cantidad,
                COALESCE(f.numero_ficha, 'N/A') AS ficha,
                COALESCE(pf.nombre_programa, pm.nombre_programa, 'N/A') AS programa
            FROM movimientos_material m
            INNER JOIN material_formacion mat ON mat.id_material = m.id_material
            LEFT JOIN fichas f ON f.id_ficha = m.id_ficha
            LEFT JOIN programas_formacion pf ON pf.id_programa = f.id_programa
            LEFT JOIN programas_formacion pm ON pm.id_programa = m.id_programa
            $where
            ORDER BY m.fecha_hora DESC
            LIMIT " . (int)$limit . "
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $r["cantidad"] = (int)$r["cantidad"];
        }

        return $rows;
    }

    /**
     * ✅ Material faltante (stock bajo / agotado)
     * - Si tu tabla tiene stock: la usa
     * - Si NO, calcula stock con entradas - salidas
     */
    public function getMaterialFaltante(array $filters, int $threshold = 5): array
    {
        // 1) Si existe stock en material_formacion lo usamos
        $hasStock = $this->columnExists("material_formacion", "stock") ||
                    $this->columnExists("material_formacion", "stock_actual") ||
                    $this->columnExists("material_formacion", "cantidad_disponible");

        $stockCol = null;
        if ($this->columnExists("material_formacion", "stock")) $stockCol = "stock";
        else if ($this->columnExists("material_formacion", "stock_actual")) $stockCol = "stock_actual";
        else if ($this->columnExists("material_formacion", "cantidad_disponible")) $stockCol = "cantidad_disponible";

        $minCol = null;
        if ($this->columnExists("material_formacion", "stock_minimo")) $minCol = "stock_minimo";
        else if ($this->columnExists("material_formacion", "minimo")) $minCol = "minimo";

        // ✅ Si hay stock directo
        if ($hasStock && $stockCol) {
            $sql = "
                SELECT
                    nombre AS material,
                    $stockCol AS stock,
                    " . ($minCol ? $minCol : (int)$threshold) . " AS minimo
                FROM material_formacion
                WHERE $stockCol <= " . ($minCol ? $minCol : (int)$threshold) . "
                ORDER BY $stockCol ASC
                LIMIT 100
            ";
            $st = $this->db->prepare($sql);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as &$r) {
                $r["stock"] = (int)$r["stock"];
                $r["minimo"] = (int)$r["minimo"];
            }
            return $rows;
        }

        // 2) Si NO hay stock directo: lo calculamos desde movimientos
        // stock = entradas - salidas
        [$where, $params] = $this->buildFiltersProgramFlexible($filters);

        $sql = "
            SELECT
                mat.nombre AS material,
                (
                    SUM(CASE WHEN m.tipo_movimiento='Entrada' THEN m.cantidad ELSE 0 END)
                    -
                    SUM(CASE WHEN m.tipo_movimiento='Salida' THEN m.cantidad ELSE 0 END)
                ) AS stock
            FROM movimientos_material m
            INNER JOIN material_formacion mat ON mat.id_material = m.id_material
            $where
            GROUP BY mat.id_material, mat.nombre
            HAVING stock <= ?
            ORDER BY stock ASC
            LIMIT 100
        ";

        $params[] = (int)$threshold;

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $r["stock"] = (int)$r["stock"];
            $r["minimo"] = (int)$threshold;
        }

        return $rows;
    }

    // ==========================
    // ✅ Dashboard Data
    // ==========================
    public function getDashboardData(array $filters): array
    {
        $consumoPorMes       = $this->getConsumoPorMes($filters);
        $consumoPorPrograma  = $this->getConsumoPorPrograma($filters);
        $materialesMasUsados = $this->getMaterialesMasUsados($filters);
        $consumoPorFicha     = $this->getConsumoPorFicha($filters);

        $totalConsumo = 0;
        $totalCosto = 0;

        foreach ($consumoPorFicha as $row) {
            $totalConsumo += (int)$row["consumo"];
            $totalCosto   += (int)$row["costo"];
        }

        return [
            "consumoPorMes"       => $consumoPorMes,
            "consumoPorPrograma"  => $consumoPorPrograma,
            "materialesMasUsados" => $materialesMasUsados,
            "consumoPorFicha"     => $consumoPorFicha,
            "totalConsumo"        => $totalConsumo,
            "totalCosto"          => $totalCosto,
        ];
    }

    
}
