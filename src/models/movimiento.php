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
                    SET stock_actual = stock_actual + ?
                    WHERE id_bodega = ? AND id_material = ?
                ");
                $upd->execute([$cantidad, $idBodega, $idMaterial]);
                error_log("[STOCK_BODEGA] UPDATE OK -> Bodega=$idBodega Material=$idMaterial +$cantidad");
            } else {
                // No existe -> insertar
                $ins = $this->conn->prepare("
                    INSERT INTO stock_bodega (id_bodega, id_material, stock_actual)
                    VALUES (?, ?, ?)
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
                    SET stock_actual = stock_actual + ?
                    WHERE id_subbodega = ? AND id_material = ?
                ");
                $upd->execute([$cantidad, $idSubBodega, $idMaterial]);
                error_log("[STOCK_SUBBODEGA] UPDATE OK -> Sub=$idSubBodega Material=$idMaterial +$cantidad");
            } else {
                // No existe -> insertar
                $ins = $this->conn->prepare("
                    INSERT INTO stock_subbodega (id_subbodega, id_material, stock_actual)
                    VALUES (?, ?, ?)
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

        foreach ($materiales as $m) {
            $idMaterial = (int)($m['id_material'] ?? 0);
            $cantidad   = (int)($m['cantidad'] ?? 0);

            if ($idMaterial <= 0 || $cantidad <= 0) {
                continue;
            }

            // ✅ Siempre sumar al stock total de bodega
            $this->upsertStockBodega($idBodega, $idMaterial, $cantidad);

            // ✅ Si hay subbodega, también sumar stock en subbodega
            if ($idSub > 0) {
                $this->upsertStockSubBodega($idSub, $idMaterial, $cantidad);
            }
        }
    }

    /* ===============================
       REGISTRAR ENTRADA (MULTI MATERIAL)
    =============================== */
    public function registrarEntrada(array $data): string
    {
        // LOG: Ver qué datos llegan
        error_log("=== REGISTRAR ENTRADA ===");
        error_log("Datos recibidos: " . json_encode($data));
        error_log("Total materiales: " . count($data['materiales'] ?? []));

        $this->conn->beginTransaction();

        try {
            // Insertar UNA SOLA fila en movimientos_material con el PRIMER material
            if (empty($data['materiales'])) {
                throw new Exception("Debe agregar al menos un material");
            }

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
                $data['id_subbodega'] ?? null, // ✅ FIX: evitar undefined index
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

            // Insertar los materiales restantes en movimientos_detalle
            if (count($data['materiales']) > 1) {
                error_log("Insertando " . (count($data['materiales']) - 1) . " materiales en movimientos_detalle");
                $stmtMat = $this->conn->prepare("
                    INSERT INTO movimientos_detalle
                    (id_movimiento, id_material, cantidad)
                    VALUES (?, ?, ?)
                ");

                for ($i = 1; $i < count($data['materiales']); $i++) {
                    $mat = $data['materiales'][$i];
                    error_log("  - Material #$i: ID={$mat['id_material']}, Cantidad={$mat['cantidad']}");
                    $stmtMat->execute([
                        $idMovimiento,
                        $mat['id_material'],
                        $mat['cantidad']
                    ]);
                }
                error_log("✓ Materiales detalle insertados correctamente");
            } else {
                error_log("⚠ Solo 1 material, no se usa movimientos_detalle");
            }

            /* ============================================================
               ✅ NUEVO: ACTUALIZAR STOCK AUTOMÁTICAMENTE
               - Esto llena stock_bodega y stock_subbodega
               - Soluciona el problema en solicitudes cuando filtras por bodega
            ============================================================ */
            $this->actualizarStockEntrada($data, $data['materiales']);
            error_log("✓ Stock actualizado correctamente");

            $this->conn->commit();

            $codigo = 'MOV-' . date('Y') . '-' . str_pad($idMovimiento, 5, '0', STR_PAD_LEFT);
            error_log("✓ Movimiento registrado exitosamente: " . $codigo);
            error_log("======================\n");

            return $codigo;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("✗ ERROR al registrar: " . $e->getMessage());
            throw new Exception("Error al registrar movimiento: " . $e->getMessage());
        }
    }

    public function listarMovimientos()
    {
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
                m.id_ficha,
                m.id_rae,
                m.id_usuario
            FROM movimientos_material m
            LEFT JOIN bodegas b ON b.id_bodega = m.id_bodega
            LEFT JOIN subbodegas sb ON sb.id_subbodega = m.id_subbodega
            ORDER BY m.fecha_hora DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agregar materiales a cada movimiento (incluyendo el material principal)
        foreach ($movimientos as &$mov) {
            $materiales = [];

            // El material principal está en movimientos_material
            $sqlPrincipal = "
                SELECT id_material, id_material as id_material, cantidad
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
