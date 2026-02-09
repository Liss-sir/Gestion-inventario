<?php

class MovimientoModel {

    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /* ============================================================
       ✅ NUEVO: ACTUALIZAR STOCK EN BODEGA (SIN REQUERIR UNIQUE)
       - Si existe el registro: suma cantidad
       - Si no existe: lo crea
    ============================================================ */
    private function upsertStockBodega(int $idBodega, int $idMaterial, int $cantidad): void
    {
        try {
            // Verificar si ya existe registro
            $check = $this->conn->prepare("
                SELECT stock_actual
                FROM stock_bodega
                WHERE id_bodega = ? AND id_material = ?
                LIMIT 1
            ");
            $check->execute([$idBodega, $idMaterial]);
            $row = $check->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Existe -> sumar
                $upd = $this->conn->prepare("
                    UPDATE stock_bodega
                    SET stock_actual = GREATEST(stock_actual + ?, 0)
                    WHERE id_bodega = ? AND id_material = ?
                ");
                $upd->execute([$cantidad, $idBodega, $idMaterial]);
                error_log("[STOCK_BODEGA] UPDATE OK -> Bodega=$idBodega Material=$idMaterial +$cantidad");
            } else {
                // No existe -> insertar
                $ins = $this->conn->prepare("
                    INSERT INTO stock_bodega (id_bodega, id_material, stock_actual)
                    VALUES (?, ?, GREATEST(?, 0))
                ");
                $ins->execute([$idBodega, $idMaterial, $cantidad]);
                error_log("[STOCK_BODEGA] INSERT OK -> Bodega=$idBodega Material=$idMaterial = $cantidad");
            }
        } catch (Throwable $e) {
            error_log("[STOCK_BODEGA] ERROR: " . $e->getMessage());
            // No lanzamos excepción aquí para no romper tu flujo completo,
            // pero si quieres que sea crítico, puedes lanzar Exception.
        }
    }

    /* ============================================================
       ✅ NUEVO: ACTUALIZAR STOCK EN SUBBODEGA (SIN REQUERIR UNIQUE)
       - Si existe el registro: suma cantidad
       - Si no existe: lo crea
    ============================================================ */
    private function upsertStockSubBodega(int $idSubBodega, int $idMaterial, int $cantidad): void
    {
        try {
            // Verificar si ya existe registro
            $check = $this->conn->prepare("
                SELECT stock_actual
                FROM stock_subbodega
                WHERE id_subbodega = ? AND id_material = ?
                LIMIT 1
            ");
            $check->execute([$idSubBodega, $idMaterial]);
            $row = $check->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Existe -> sumar
                $upd = $this->conn->prepare("
                    UPDATE stock_subbodega
                    SET stock_actual = GREATEST(stock_actual + ?, 0)
                    WHERE id_subbodega = ? AND id_material = ?
                ");
                $upd->execute([$cantidad, $idSubBodega, $idMaterial]);
                error_log("[STOCK_SUBBODEGA] UPDATE OK -> Sub=$idSubBodega Material=$idMaterial +$cantidad");
            } else {
                // No existe -> insertar
                $ins = $this->conn->prepare("
                    INSERT INTO stock_subbodega (id_subbodega, id_material, stock_actual)
                    VALUES (?, ?, GREATEST(?, 0))
                ");
                $ins->execute([$idSubBodega, $idMaterial, $cantidad]);
                error_log("[STOCK_SUBBODEGA] INSERT OK -> Sub=$idSubBodega Material=$idMaterial = $cantidad");
            }
        } catch (Throwable $e) {
            error_log("[STOCK_SUBBODEGA] ERROR: " . $e->getMessage());
        }
    }

    /* ============================================================
       ✅ NUEVO: ACTUALIZAR STOCK DE ENTRADA (BODEGA / SUBBODEGA)
       - Siempre actualiza stock_bodega (TOTAL)
       - Si existe subbodega: también actualiza stock_subbodega
    ============================================================ */
    private function actualizarStockEntrada(array $data, array $materiales): void
    {
        $idBodega = (int)($data['id_bodega'] ?? 0);
        $idSub    = (int)($data['id_subbodega'] ?? 0);

        if ($idBodega <= 0) {
            error_log("[STOCK] No se actualiza stock: id_bodega inválido");
            return;
        }

        // ✅ Calcular el delta (positivo para entrada, negativo para salida)
        $tipo = $data['tipo_movimiento'] ?? 'entrada';
        $delta = (strtolower($tipo) === 'entrada') ? 1 : -1;

        foreach ($materiales as $m) {
            $idMaterial = (int)($m['id_material'] ?? 0);
            $cantidad   = (int)($m['cantidad'] ?? 0);

            if ($idMaterial <= 0 || $cantidad <= 0) {
                continue;
            }

            // ✅ Multiplicar por delta: entrada suma, salida resta
            $cantidadAjustada = $cantidad * $delta;

            // ✅ Actualizar stock total de bodega
            $this->upsertStockBodega($idBodega, $idMaterial, $cantidadAjustada);

            // ✅ Si hay subbodega, también actualizar stock en subbodega
            if ($idSub > 0) {
                $this->upsertStockSubBodega($idSub, $idMaterial, $cantidadAjustada);
            }
        }
    }

/* ===============================
   REGISTRAR ENTRADA (MULTI MATERIAL)
=============================== */
public function registrarEntrada(array $data): string
{
    error_log("=== REGISTRAR ENTRADA ===");
    error_log("Datos recibidos: " . json_encode($data));
    error_log("Total materiales: " . count($data['materiales'] ?? []));

    $transaccionNuestra = false;
    if (!$this->conn->inTransaction()) {
        $this->conn->beginTransaction();
        $transaccionNuestra = true;
        error_log("⚙️ Iniciando nueva transacción en registrarEntrada()");
    } else {
        error_log("⚙️ Usando transacción existente en registrarEntrada()");
    }

    try {

        if (empty($data['materiales'])) {
            throw new Exception("Debe agregar al menos un material");
        }

        /* ============================================================
           ✅ VALIDACIÓN CONTRA STOCK_MAXIMO
           - No permite solicitar más del stock_maximo
        ============================================================ */
        foreach ($data['materiales'] as $m) {

            $idMaterial = (int)$m['id_material'];
            $cantidad   = (int)$m['cantidad'];

            $sql = "SELECT stock_maximo 
                    FROM material_formacion 
                    WHERE id_material = ? 
                    LIMIT 1";

            $stmtVal = $this->conn->prepare($sql);
            $stmtVal->execute([$idMaterial]);
            $stockMaximo = $stmtVal->fetchColumn();

            if ($stockMaximo === false) {
                throw new Exception("El material no existe.");
            }

            if ($cantidad > (int)$stockMaximo) {
                throw new Exception("No puede solicitar más de $stockMaximo unidades para este material.");
            }
        }

        // Insertar UNA SOLA fila en movimientos_material con el PRIMER material
        $primerMaterial = $data['materiales'][0];
        error_log("Primer material: " . json_encode($primerMaterial));

        $stmtMov = $this->conn->prepare("
            INSERT INTO movimientos_material (
                tipo_movimiento, id_usuario,
                id_bodega, id_subbodega,
                id_programa, id_ficha, id_rae,
                observaciones, id_solicitud, fecha_hora,
                id_material, cantidad
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?
            )
        ");

        $stmtMov->execute([
            $data['tipo_movimiento'] ?? 'entrada',
            $data['id_usuario'],
            $data['id_bodega'],
            $data['id_subbodega'] ?? null,
            $data['id_programa'] ?? null,
            $data['id_ficha'] ?? null,
            $data['id_rae'] ?? null,
            $data['observaciones'] ?? null,
            $data['id_solicitud'] ?? null,
            $primerMaterial['id_material'],
            $primerMaterial['cantidad']
        ]);

        $idMovimiento = $this->conn->lastInsertId();
        error_log("ID movimiento creado: " . $idMovimiento);

        if (count($data['materiales']) > 1) {

            $stmtMat = $this->conn->prepare("
                INSERT INTO movimientos_detalle
                (id_movimiento, id_material, cantidad)
                VALUES (?, ?, ?)
            ");

            for ($i = 1; $i < count($data['materiales']); $i++) {
                $mat = $data['materiales'][$i];

                $stmtMat->execute([
                    $idMovimiento,
                    $mat['id_material'],
                    $mat['cantidad']
                ]);
            }
        }

        $this->actualizarStockEntrada($data, $data['materiales']);

        if ($transaccionNuestra) {
            $this->conn->commit();
        }

        $codigo = 'MOV-' . date('Y') . '-' . str_pad($idMovimiento, 5, '0', STR_PAD_LEFT);

        return $codigo;

    } catch (Exception $e) {

        if ($transaccionNuestra) {
            $this->conn->rollBack();
        }

        throw new Exception("Error al registrar movimiento: " . $e->getMessage());
    }
}


    public function listarMovimientos()
    {
        // Combinar movimientos regulares con devoluciones usando UNION
        $sql = "
            SELECT 
                m.id_movimiento,
                m.tipo_movimiento,
                m.fecha_hora,
                m.observaciones,
                m.id_bodega,
                b.nombre AS bodega,
                m.id_subbodega,
                sb.nombre_subbodega AS subbodega,
                m.id_programa,
                COALESCE(pr.nombre_programa, 'N/A') AS nombre_programa,
                m.id_ficha,
                COALESCE(f.numero_ficha, 'N/A') AS numero_ficha,
                m.id_rae,
                COALESCE(r.codigo_rae, 'N/A') AS codigo_rae,
                m.id_usuario,
                m.id_solicitud,
                m.id_material
            FROM movimientos_material m
            LEFT JOIN bodegas b ON b.id_bodega = m.id_bodega
            LEFT JOIN subbodegas sb ON sb.id_subbodega = m.id_subbodega
            LEFT JOIN programas_formacion pr ON pr.id_programa = m.id_programa
            LEFT JOIN fichas f ON f.id_ficha = m.id_ficha
            LEFT JOIN raes r ON r.id_rae = m.id_rae
            
            UNION ALL
            
            SELECT 
                CONCAT('DEV-', d.id_devolucion) as id_movimiento,
                'devolucion' as tipo_movimiento,
                d.fecha_hora,
                CONCAT('Devolución - ', d.estado_material, 
                       CASE WHEN d.observaciones != '' THEN CONCAT(' - ', d.observaciones) ELSE '' END) as observaciones,
                d.id_bodega,
                b.nombre AS bodega,
                d.id_subbodega,
                sb.nombre_subbodega AS subbodega,
                NULL as id_programa,
                'N/A' as nombre_programa,
                NULL as id_ficha,
                'N/A' as numero_ficha,
                NULL as id_rae,
                'N/A' as codigo_rae,
                d.id_usuario,
                mm.id_solicitud,
                d.id_material
            FROM devoluciones_material d
            LEFT JOIN bodegas b ON b.id_bodega = d.id_bodega
            LEFT JOIN subbodegas sb ON sb.id_subbodega = d.id_subbodega
            LEFT JOIN movimientos_material mm ON mm.id_movimiento = d.id_movimiento_salida
            
            ORDER BY fecha_hora DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agregar materiales a cada movimiento
        foreach ($movimientos as &$mov) {
            $materiales = [];

            // Si es una devolución (id empieza con DEV-)
            if (strpos($mov['id_movimiento'], 'DEV-') === 0) {
                // Para devoluciones, el material ya está en id_material
                if ($mov['id_material']) {
                    $sqlMat = "SELECT nombre, unidad_medida FROM material_formacion WHERE id_material = ?";
                    $stmtMat = $this->conn->prepare($sqlMat);
                    $stmtMat->execute([$mov['id_material']]);
                    $matInfo = $stmtMat->fetch(PDO::FETCH_ASSOC);

                    if ($matInfo) {
                        // Obtener cantidad de la devolución
                        $idDev = str_replace('DEV-', '', $mov['id_movimiento']);
                        $sqlCant = "SELECT cantidad_devuelta FROM devoluciones_material WHERE id_devolucion = ?";
                        $stmtCant = $this->conn->prepare($sqlCant);
                        $stmtCant->execute([$idDev]);
                        $cantidad = $stmtCant->fetchColumn() ?: 0;

                        $materiales[] = [
                            'id_material' => $mov['id_material'],
                            'nombre' => $matInfo['nombre'],
                            'cantidad' => $cantidad,
                            'unidad_medida' => $matInfo['unidad_medida']
                        ];
                    }
                }
            } else {
                // Para movimientos regulares, el material principal está en movimientos_material
                $sqlPrincipal = "
                    SELECT id_material, cantidad
                    FROM movimientos_material
                    WHERE id_movimiento = ?
                ";
                $stmtPrincipal = $this->conn->prepare($sqlPrincipal);
                $stmtPrincipal->execute([$mov['id_movimiento']]);
                $principal = $stmtPrincipal->fetch(PDO::FETCH_ASSOC);

                if ($principal) {
                    // Obtener info del material
                    $sqlMat = "SELECT nombre, unidad_medida FROM material_formacion WHERE id_material = ?";
                    $stmtMat = $this->conn->prepare($sqlMat);
                    $stmtMat->execute([$principal['id_material']]);
                    $matInfo = $stmtMat->fetch(PDO::FETCH_ASSOC);

                    if ($matInfo) {
                        $materiales[] = [
                            'id_material' => $principal['id_material'],
                            'nombre' => $matInfo['nombre'],
                            'cantidad' => $principal['cantidad'],
                            'unidad_medida' => $matInfo['unidad_medida']
                        ];
                    }
                }

                // Los materiales adicionales están en movimientos_detalle
                $sqlMat = "
                    SELECT md.id_material, mf.nombre, md.cantidad, mf.unidad_medida
                    FROM movimientos_detalle md
                    INNER JOIN material_formacion mf ON mf.id_material = md.id_material
                    WHERE md.id_movimiento = ?
                ";
                $stmtMat = $this->conn->prepare($sqlMat);
                $stmtMat->execute([$mov['id_movimiento']]);
                $detalles = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

                $materiales = array_merge($materiales, $detalles);
            }

            $mov['materiales'] = $materiales;
        }

        return $movimientos;
    }

    public function obtenerMovimiento(int $id)
    {
        $sql = "SELECT * FROM movimientos_material WHERE id_movimiento = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        $mov = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mov) return null;

        $sqlMat = "
            SELECT m.*, mf.nombre
            FROM movimientos_material m
            INNER JOIN material_formacion mf ON mf.id_material = m.id_material
            WHERE m.id_movimiento = ?
        ";

        $stmtMat = $this->conn->prepare($sqlMat);
        $stmtMat->execute([$id]);
        $mov['materiales'] = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

        return $mov;
    }

    public function eliminarMovimiento(int $id): bool
    {
        $this->conn->beginTransaction();

        try {
            // Eliminar todos los registros del movimiento en movimientos_material
            $this->conn->prepare(
                "DELETE FROM movimientos_material WHERE id_movimiento=?"
            )->execute([$id]);

            $this->conn->commit();
            return true;

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
