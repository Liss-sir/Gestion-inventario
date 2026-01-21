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
     * =====================================================
     * ✅ RESOLVER COLUMNAS DE UBICACIÓN EN movimientos_material
     * - Detecta bodega/subbodega aunque tu BD use distintos nombres
     * - Retorna columnas listas para usar con alias (por defecto m.)
     * =====================================================
     */
    private function resolveMovimientosLocationColumns(string $alias = "m"): array
    {
        $table = "movimientos_material";

        $bodegaCol = null;
        $subCol = null;

        // ✅ Posibles columnas para BODEGA
        $bodegaCandidates = ["id_bodega", "bodega_id"];
        foreach ($bodegaCandidates as $c) {
            if ($this->columnExists($table, $c)) {
                $bodegaCol = $alias . "." . $c;
                break;
            }
        }

        // ✅ Posibles columnas para SUBBODEGA
        $subCandidates = ["id_subbodega", "subbodega_id", "id_sub_bodega", "sub_bodega_id"];
        foreach ($subCandidates as $c) {
            if ($this->columnExists($table, $c)) {
                $subCol = $alias . "." . $c;
                break;
            }
        }

        return [$bodegaCol, $subCol];
    }

    /**
     * ✅ buildFilters mejorado:
     * - Permite controlar qué columna se usa para filtrar programa/ficha.
     * - ✅ Ahora incluye filtros de Bodega/Subbodega con prioridad correcta:
     *   Si hay subbodega -> ignora bodega
     */
    private function buildFilters(array $filters, array $columns = []): array
    {
        $fechaInicio = $this->normalizeDate($filters["fecha_inicio"] ?? null);
        $fechaFin    = $this->normalizeDate($filters["fecha_fin"] ?? null);
        $programaId  = $filters["programa"] ?? "all";
        $fichaId     = $filters["ficha"] ?? "all";

        // ✅ Nuevos filtros ubicación
        $bodegaId    = $filters["bodega"] ?? "all";
        $subBodegaId = $filters["subbodega"] ?? "all";

        // ✅ Columnas por defecto
        $dateCol     = $columns["date"] ?? "m.fecha_hora";
        $programaCol = $columns["programa"] ?? "m.id_programa";
        $fichaCol    = $columns["ficha"] ?? "m.id_ficha";

        // ✅ Ubicación: si no vienen, intentamos detectar en movimientos_material
        $bodegaCol   = $columns["bodega"] ?? null;
        $subBodegaCol = $columns["subbodega"] ?? null;

        if (!$bodegaCol || !$subBodegaCol) {
            [$autoBodegaCol, $autoSubCol] = $this->resolveMovimientosLocationColumns("m");
            if (!$bodegaCol) $bodegaCol = $autoBodegaCol;
            if (!$subBodegaCol) $subBodegaCol = $autoSubCol;
        }

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

        // =====================================================
        // ✅ UBICACIÓN (BODEGA / SUBBODEGA) CON PRIORIDAD
        // - Si hay subbodega válida -> filtrar subbodega
        // - Si no existe columna subbodega pero se solicitó -> 1=0 (sin resultados)
        // - Si no, si hay bodega -> filtrar bodega
        // =====================================================
        $hasBodegaFilter    = ($bodegaId !== "all" && $bodegaId !== "" && is_numeric($bodegaId));
        $hasSubBodegaFilter = ($subBodegaId !== "all" && $subBodegaId !== "" && is_numeric($subBodegaId));

        // ✅ FIX DEFINITIVO: NO fallback a bodega si user eligió subbodega
        if ($hasSubBodegaFilter) {
            if ($subBodegaCol) {
                $where[] = "$subBodegaCol = ?";
                $params[] = (int)$subBodegaId;
            } else {
                // ✅ si el user filtró subbodega pero la tabla no tiene columna → NO hay resultados
                $where[] = "1=0";
            }
        } elseif ($hasBodegaFilter) {
            if ($bodegaCol) {
                $where[] = "$bodegaCol = ?";
                $params[] = (int)$bodegaId;
            }
        }

        $sqlWhere = "";
        if (!empty($where)) {
            $sqlWhere = "WHERE " . implode(" AND ", $where);
        }

        return [$sqlWhere, $params];
    }

    /**
     * ✅ Filtro robusto para programas:
     * - movimientos puede venir con m.id_programa NULL
     * - entonces tomamos el programa desde fichas: f.id_programa
     * - ✅ Ahora también incluye filtros bodega/subbodega con prioridad
     */
    private function buildFiltersProgramFlexible(array $filters): array
    {
        $fechaInicio = $this->normalizeDate($filters["fecha_inicio"] ?? null);
        $fechaFin    = $this->normalizeDate($filters["fecha_fin"] ?? null);
        $programaId  = $filters["programa"] ?? "all";
        $fichaId     = $filters["ficha"] ?? "all";

        // ✅ Ubicación
        $bodegaId    = $filters["bodega"] ?? "all";
        $subBodegaId = $filters["subbodega"] ?? "all";

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

        // ✅ ubicación columnas detectadas automáticamente
        [$bodegaCol, $subCol] = $this->resolveMovimientosLocationColumns("m");

        $hasBodegaFilter    = ($bodegaId !== "all" && $bodegaId !== "" && is_numeric($bodegaId));
        $hasSubBodegaFilter = ($subBodegaId !== "all" && $subBodegaId !== "" && is_numeric($subBodegaId));

        // ✅ FIX DEFINITIVO: si hay subbodega y no existe columna → 1=0 (NO fallback a bodega)
        if ($hasSubBodegaFilter) {
            if ($subCol) {
                $where[] = "$subCol = ?";
                $params[] = (int)$subBodegaId;
            } else {
                $where[] = "1=0";
            }
        } elseif ($hasBodegaFilter) {
            if ($bodegaCol) {
                $where[] = "$bodegaCol = ?";
                $params[] = (int)$bodegaId;
            }
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

    /**
     * ✅ Bodegas (opcional)
     * - devuelve [] si no existe tabla
     */
    public function getBodegas(): array
    {
        $candidates = ["bodegas", "bodegas_formacion", "bodega", "bodegas_siga"];
        $table = null;

        foreach ($candidates as $t) {
            if ($this->tableExists($t)) {
                $table = $t;
                break;
            }
        }
        if (!$table) return [];

        // Buscar columnas comunes
        $idCol = $this->columnExists($table, "id_bodega") ? "id_bodega" : ($this->columnExists($table, "id") ? "id" : null);
        $nameCol = $this->columnExists($table, "nombre_bodega") ? "nombre_bodega" : ($this->columnExists($table, "nombre") ? "nombre" : null);

        if (!$idCol || !$nameCol) return [];

        $sql = "SELECT $idCol AS id, $nameCol AS nombre FROM $table ORDER BY $nameCol ASC";
        $st = $this->db->prepare($sql);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ==========================
    // ✅ Consumo vs Devoluciones por Mes
    // ==========================
    public function getConsumoPorMes(array $filters): array
    {
        // ✅ Ahora buildFilters incluye bodega/subbodega automáticamente si existen columnas
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
    // ✅ Distribución por Programa (PORCENTAJE) - FIX ✅
    // - funciona si m.id_programa viene NULL
    // - ✅ ahora también filtra por bodega/subbodega
    // ==========================
    public function getConsumoPorPrograma(array $filters): array
    {
        [$where, $params] = $this->buildFiltersProgramFlexible($filters);

        // ✅ Solo salidas para consumo
        $extra = $where ? " AND m.tipo_movimiento='Salida'" : "WHERE m.tipo_movimiento='Salida'";

        $sql = "
            SELECT 
                COALESCE(pf.nombre_programa, pm.nombre_programa, 'Sin programa') AS name,
                SUM(m.cantidad) AS total_consumo
            FROM movimientos_material m
            LEFT JOIN fichas f ON f.id_ficha = m.id_ficha
            LEFT JOIN programas_formacion pf ON pf.id_programa = f.id_programa
            LEFT JOIN programas_formacion pm ON pm.id_programa = m.id_programa
            $where
            $extra
            GROUP BY name
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

    public function getConsumoPorRAE(array $filters): array
    {
        if (!$this->columnExists("movimientos_material", "id_rae")) {
            return [];
        }

        $raeTable = "raes";
        if (!$this->tableExists($raeTable)) {
            return [];
        }

        $hasId   = $this->columnExists($raeTable, "id_rae");
        $hasCod  = $this->columnExists($raeTable, "codigo_rae");
        $hasDesc = $this->columnExists($raeTable, "descripcion_rae");

        if (!$hasId) return [];

        [$where, $params] = $this->buildFiltersProgramFlexible($filters);
        $extra = $where ? " AND m.id_rae IS NOT NULL" : "WHERE m.id_rae IS NOT NULL";

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

    public function getMaterialFaltante(array $filters, int $threshold = 5): array
    {
        // ==========================
        // ✅ Filtros del usuario
        // ==========================
        $bodegaId    = $filters["bodega"] ?? "all";
        $subBodegaId = $filters["subbodega"] ?? "all";

        $hasBodegaFilter    = ($bodegaId !== "all" && $bodegaId !== "" && is_numeric($bodegaId));
        $hasSubBodegaFilter = ($subBodegaId !== "all" && $subBodegaId !== "" && is_numeric($subBodegaId));

        // ✅ mínimo real (si existe en material_formacion)
        $minCol = null;
        if ($this->columnExists("material_formacion", "stock_minimo")) $minCol = "stock_minimo";
        else if ($this->columnExists("material_formacion", "minimo")) $minCol = "minimo";

        // ✅ Expr segura: evita mat.0
        $minExpr = $minCol ? "COALESCE(mat.$minCol, ?)" : "?";

        // =====================================================
        // ✅ Detectar tablas de stock por ubicación
        // =====================================================
        $stockBodegaTableCandidates = ["stock_bodega"];
        $stockSubTableCandidates    = ["stock_subbodega", "stock_sub_bodega", "stock_sub_bodegas"];

        $stockBodegaTable = null;
        foreach ($stockBodegaTableCandidates as $t) {
            if ($this->tableExists($t)) { $stockBodegaTable = $t; break; }
        }

        $stockSubTable = null;
        foreach ($stockSubTableCandidates as $t) {
            if ($this->tableExists($t)) { $stockSubTable = $t; break; }
        }

        $hasStockBodega = $stockBodegaTable
            && $this->columnExists($stockBodegaTable, "id_bodega")
            && $this->columnExists($stockBodegaTable, "id_material")
            && $this->columnExists($stockBodegaTable, "stock_actual");

        $hasStockSub = $stockSubTable
            && $this->columnExists($stockSubTable, "id_subbodega")
            && $this->columnExists($stockSubTable, "id_material")
            && $this->columnExists($stockSubTable, "stock_actual");

        // =====================================================
        // ✅ Detectar tabla BODEGAS para nombre real
        // =====================================================
        $bodegaTableCandidates = ["bodegas", "bodegas_formacion", "bodega", "bodegas_siga"];
        $bodegaTable = null;

        foreach ($bodegaTableCandidates as $t) {
            if ($this->tableExists($t)) { $bodegaTable = $t; break; }
        }

        $bodegaIdCol   = ($bodegaTable && $this->columnExists($bodegaTable, "id_bodega")) ? "id_bodega" : (($bodegaTable && $this->columnExists($bodegaTable, "id")) ? "id" : null);
        $bodegaNameCol = ($bodegaTable && $this->columnExists($bodegaTable, "nombre_bodega")) ? "nombre_bodega" : (($bodegaTable && $this->columnExists($bodegaTable, "nombre")) ? "nombre" : null);

        // =====================================================
        // ✅ Detectar tabla SUBBODEGAS para nombre real
        // =====================================================
        $subBodegaTableCandidates = ["subbodegas", "sub_bodegas", "sub_bodega", "subbodega", "subbodegas_formacion"];
        $subBodegaTable = null;

        foreach ($subBodegaTableCandidates as $t) {
            if ($this->tableExists($t)) { $subBodegaTable = $t; break; }
        }

        $subBodegaIdCol = ($subBodegaTable && $this->columnExists($subBodegaTable, "id_subbodega")) ? "id_subbodega"
            : (($subBodegaTable && $this->columnExists($subBodegaTable, "id")) ? "id" : null);

        $subBodegaNameCol = ($subBodegaTable && $this->columnExists($subBodegaTable, "nombre_subbodega")) ? "nombre_subbodega"
            : (($subBodegaTable && $this->columnExists($subBodegaTable, "nombre")) ? "nombre" : null);

        // =====================================================
        // ✅ 1) SUBBODEGA (si viene filtrada)
        // =====================================================
        if ($hasSubBodegaFilter && $hasStockSub) {

            $joinSub = "";
            $ubicacionExpr = "CONCAT('Subbodega #', ss.id_subbodega)";

            if ($subBodegaTable && $subBodegaIdCol && $subBodegaNameCol) {
                $joinSub = "LEFT JOIN {$subBodegaTable} sbb ON sbb.{$subBodegaIdCol} = ss.id_subbodega";
                $ubicacionExpr = "COALESCE(sbb.{$subBodegaNameCol}, CONCAT('Subbodega #', ss.id_subbodega))";
            }

            $sql = "
                SELECT
                    mat.nombre AS material,
                    ss.stock_actual AS stock,
                    $minExpr AS minimo,
                    $ubicacionExpr AS ubicacion
                FROM {$stockSubTable} ss
                INNER JOIN material_formacion mat ON mat.id_material = ss.id_material
                $joinSub
                WHERE ss.id_subbodega = ?
                  AND ss.stock_actual <= $minExpr
                ORDER BY ss.stock_actual ASC
                LIMIT 100
            ";

            $st = $this->db->prepare($sql);
            $st->execute([(int)$threshold, (int)$subBodegaId, (int)$threshold]);

            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as &$r) {
                $r["stock"]     = (int)$r["stock"];
                $r["minimo"]    = (int)$r["minimo"];
                $r["ubicacion"] = $r["ubicacion"] ?? "Subbodega";
            }

            return $rows;
        }

        // =====================================================
        // ✅ 2) BODEGA (si viene filtrada)
        // =====================================================
        if ($hasBodegaFilter && $hasStockBodega) {

            $joinBod = "";
            $ubicacionExpr = "CONCAT('Bodega #', sb.id_bodega)";

            if ($bodegaTable && $bodegaIdCol && $bodegaNameCol) {
                $joinBod = "LEFT JOIN {$bodegaTable} b ON b.{$bodegaIdCol} = sb.id_bodega";
                $ubicacionExpr = "COALESCE(b.{$bodegaNameCol}, CONCAT('Bodega #', sb.id_bodega))";
            }

            $sql = "
                SELECT
                    mat.nombre AS material,
                    sb.stock_actual AS stock,
                    $minExpr AS minimo,
                    $ubicacionExpr AS ubicacion
                FROM {$stockBodegaTable} sb
                INNER JOIN material_formacion mat ON mat.id_material = sb.id_material
                $joinBod
                WHERE sb.id_bodega = ?
                  AND sb.stock_actual <= $minExpr
                ORDER BY sb.stock_actual ASC
                LIMIT 100
            ";

            $st = $this->db->prepare($sql);
            $st->execute([(int)$threshold, (int)$bodegaId, (int)$threshold]);

            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as &$r) {
                $r["stock"]     = (int)$r["stock"];
                $r["minimo"]    = (int)$r["minimo"];
                $r["ubicacion"] = $r["ubicacion"] ?? "Bodega";
            }

            return $rows;
        }

        // =====================================================
        // ✅ 3) Sin filtro → combinar Bodega + Subbodega (UNION)
        // =====================================================
        if ($hasStockBodega || $hasStockSub) {

            $parts  = [];
            $params = [];

            // ✅ BODEGAS
            if ($hasStockBodega) {
                $joinBod = "";
                $ubicacionExpr = "CONCAT('Bodega #', sb.id_bodega)";

                if ($bodegaTable && $bodegaIdCol && $bodegaNameCol) {
                    $joinBod = "LEFT JOIN {$bodegaTable} b ON b.{$bodegaIdCol} = sb.id_bodega";
                    $ubicacionExpr = "COALESCE(b.{$bodegaNameCol}, CONCAT('Bodega #', sb.id_bodega))";
                }

                $parts[] = "
                    SELECT
                        mat.nombre AS material,
                        sb.stock_actual AS stock,
                        $minExpr AS minimo,
                        $ubicacionExpr AS ubicacion
                    FROM {$stockBodegaTable} sb
                    INNER JOIN material_formacion mat ON mat.id_material = sb.id_material
                    $joinBod
                    WHERE sb.stock_actual <= $minExpr
                ";
                $params[] = (int)$threshold;
                $params[] = (int)$threshold;
            }

            // ✅ SUBBODEGAS
            if ($hasStockSub) {
                $joinSub = "";
                $ubicacionExpr = "CONCAT('Subbodega #', ss.id_subbodega)";

                if ($subBodegaTable && $subBodegaIdCol && $subBodegaNameCol) {
                    $joinSub = "LEFT JOIN {$subBodegaTable} sbb ON sbb.{$subBodegaIdCol} = ss.id_subbodega";
                    $ubicacionExpr = "COALESCE(sbb.{$subBodegaNameCol}, CONCAT('Subbodega #', ss.id_subbodega))";
                }

                $parts[] = "
                    SELECT
                        mat.nombre AS material,
                        ss.stock_actual AS stock,
                        $minExpr AS minimo,
                        $ubicacionExpr AS ubicacion
                    FROM {$stockSubTable} ss
                    INNER JOIN material_formacion mat ON mat.id_material = ss.id_material
                    $joinSub
                    WHERE ss.stock_actual <= $minExpr
                ";
                $params[] = (int)$threshold;
                $params[] = (int)$threshold;
            }

            $sql = implode(" UNION ALL ", $parts) . " ORDER BY stock ASC LIMIT 150";

            $st = $this->db->prepare($sql);
            $st->execute($params);

            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as &$r) {
                $r["stock"]     = (int)$r["stock"];
                $r["minimo"]    = (int)$r["minimo"];
                $r["ubicacion"] = $r["ubicacion"] ?? "N/A";
            }

            return $rows;
        }

        // =====================================================
        // ✅ 4) FALLBACK: si no hay tablas stock_xxx → movimientos
        // =====================================================
        [$where, $params] = $this->buildFiltersProgramFlexible($filters);

        $sql = "
            SELECT
                mat.nombre AS material,
                (
                    SUM(CASE WHEN m.tipo_movimiento='Entrada' THEN m.cantidad ELSE 0 END)
                    -
                    SUM(CASE WHEN m.tipo_movimiento='Salida' THEN m.cantidad ELSE 0 END)
                ) AS stock,
                ? AS minimo,
                'Movimientos' AS ubicacion
            FROM movimientos_material m
            INNER JOIN material_formacion mat ON mat.id_material = m.id_material
            LEFT JOIN fichas f ON f.id_ficha = m.id_ficha
            $where
            GROUP BY mat.id_material, mat.nombre
            HAVING stock <= ?
            ORDER BY stock ASC
            LIMIT 100
        ";

        $params[] = (int)$threshold; // minimo
        $params[] = (int)$threshold; // HAVING

        $st = $this->db->prepare($sql);
        $st->execute($params);

        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $r["stock"]     = (int)$r["stock"];
            $r["minimo"]    = (int)$r["minimo"];
            $r["ubicacion"] = $r["ubicacion"] ?? "Movimientos";
        }

        return $rows;
    }

    /**
     * ✅ Consumo REAL por Material (para Reporte Personalizado)
     * ✅ Ahora soporta filtros por Bodega/Subbodega también (prioridad subbodega)
     */
    public function getConsumoPorMaterial(array $filters): array
    {
        [$where, $params] = $this->buildFiltersProgramFlexible($filters);

        $sql = "
            SELECT
                mat.nombre AS material,
                SUM(CASE WHEN m.tipo_movimiento='Salida' THEN m.cantidad ELSE 0 END) AS consumo,
                SUM(CASE WHEN m.tipo_movimiento='Salida' THEN (m.cantidad * mat.precio) ELSE 0 END) AS costo
            FROM movimientos_material m
            INNER JOIN material_formacion mat ON mat.id_material = m.id_material
            LEFT JOIN fichas f ON f.id_ficha = m.id_ficha
            $where
            GROUP BY mat.id_material, mat.nombre
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

    // =====================================================
    // ✅ DASHBOARD DATA (para Estadísticas)
    // =====================================================
    public function getDashboardData(array $filters): array
    {
        $consumoPorMes       = $this->getConsumoPorMes($filters);
        $consumoPorPrograma  = $this->getConsumoPorPrograma($filters);
        $materialesMasUsados = $this->getMaterialesMasUsados($filters);
        $consumoPorFicha     = $this->getConsumoPorFicha($filters);

        $totalConsumo = 0;
        $totalCosto   = 0;

        foreach ($consumoPorFicha as $r) {
            $totalConsumo += (int)($r["consumo"] ?? 0);
            $totalCosto   += (int)($r["costo"] ?? 0);
        }

        return [
            "consumoPorMes"       => $consumoPorMes,
            "consumoPorPrograma"  => $consumoPorPrograma,
            "materialesMasUsados" => $materialesMasUsados,
            "consumoPorFicha"     => $consumoPorFicha,
            "totalConsumo"        => $totalConsumo,
            "totalCosto"          => $totalCosto
        ];
    }

    // ✅ Obtener subbodegas por bodega (ROBUSTO)
    public function getSubBodegas($idBodega): array
    {
        $candidates = ["subbodegas", "sub_bodegas", "sub_bodega", "subbodega", "subbodegas_formacion"];
        $table = null;

        foreach ($candidates as $t) {
            if ($this->tableExists($t)) {
                $table = $t;
                break;
            }
        }

        if (!$table) return [];

        $idCol = $this->columnExists($table, "id_subbodega") ? "id_subbodega"
            : ($this->columnExists($table, "id") ? "id" : null);

        $nameCol = $this->columnExists($table, "nombre_subbodega") ? "nombre_subbodega"
            : ($this->columnExists($table, "nombre") ? "nombre" : null);

        $bodegaCol = $this->columnExists($table, "id_bodega") ? "id_bodega"
            : ($this->columnExists($table, "bodega_id") ? "bodega_id" : null);

        if (!$idCol || !$nameCol || !$bodegaCol) return [];

        $sql = "
            SELECT 
                $idCol AS id,
                $nameCol AS nombre
            FROM $table
            WHERE $bodegaCol = ?
            ORDER BY $nameCol ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$idBodega]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
