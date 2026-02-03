<?php

class RaeModel {

    public $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /* ==========================
       LISTAR RAE CON PROGRAMA
    ========================== */
    public function listar(): array {
        $sql = "
            SELECT 
                r.id_rae,
                r.codigo_rae,
                r.descripcion_rae,
                r.id_programa,
                p.nombre_programa,
                p.codigo_programa,
                p.nivel_programa,
                r.estado
            FROM raes r
            LEFT JOIN programas_formacion p ON r.id_programa = p.id_programa
            ORDER BY r.codigo_rae ASC
        ";

        try {
            $stmt = $this->conn->query($sql);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Si no hay resultados, retornar array vacío
            return $result ?: [];
            
        } catch (Exception $e) {
            error_log("Error en RaeModel->listar(): " . $e->getMessage());
            return [];
        }
    }

    /* ==========================
       OBTENER RAE POR ID
    ========================== */
    public function obtener(int $id): ?array {
        $sql = "
            SELECT 
                r.id_rae,
                r.codigo_rae,
                r.descripcion_rae,
                r.id_programa,
                p.nombre_programa,
                p.codigo_programa,
                p.nivel_programa,
                r.estado
            FROM raes r
            LEFT JOIN programas_formacion p ON r.id_programa = p.id_programa
            WHERE r.id_rae = ?
        ";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
            
        } catch (Exception $e) {
            error_log("Error en RaeModel->obtener(): " . $e->getMessage());
            return null;
        }
    }

    /* ==========================
       VERIFICAR CÓDIGO ÚNICO RAE
    ========================== */
    public function existeCodigo(string $codigo_rae, ?int $ignorar_id = null): bool {
        try {
            $sql = "SELECT id_rae FROM raes WHERE codigo_rae = ?";
            $params = [$codigo_rae];

            if ($ignorar_id !== null) {
                $sql .= " AND id_rae <> ?";
                $params[] = $ignorar_id;
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            error_log("Error en RaeModel->existeCodigo(): " . $e->getMessage());
            return false;
        }
    }

    /* ==========================
       CREAR RAE
    ========================== */
    public function crear(
        string $codigo_rae,
        string $descripcion_rae,
        int $id_programa,
        string $estado
    ): bool {
        try {
            $sql = "INSERT INTO raes
                    (codigo_rae, descripcion_rae, id_programa, estado)
                    VALUES (?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);
            
            return $stmt->execute([
                $codigo_rae,
                $descripcion_rae,
                $id_programa,
                $estado
            ]);
            
        } catch (Exception $e) {
            error_log("Error en RaeModel->crear(): " . $e->getMessage());
            return false;
        }
    }

    /* ==========================
       ACTUALIZAR RAE
    ========================== */
    public function actualizar(
        int $id_rae,
        ?string $codigo_rae,
        ?int $id_programa,
        ?string $descripcion_rae,
        ?string $estado
    ): bool {
        try {
            $campos = [];
            $params = [];

            if ($codigo_rae !== null) {
                $campos[] = "codigo_rae = ?";
                $params[] = $codigo_rae;
            }

            if ($id_programa !== null) {
                $campos[] = "id_programa = ?";
                $params[] = $id_programa;
            }

            if ($descripcion_rae !== null) {
                $campos[] = "descripcion_rae = ?";
                $params[] = $descripcion_rae;
            }

            if ($estado !== null) {
                $campos[] = "estado = ?";
                $params[] = $estado;
            }

            if (empty($campos)) {
                return false;
            }

            $params[] = $id_rae;

            $sql = "UPDATE raes SET " . implode(", ", $campos) . " WHERE id_rae = ?";
            $stmt = $this->conn->prepare($sql);

            return $stmt->execute($params);
            
        } catch (Exception $e) {
            error_log("Error en RaeModel->actualizar(): " . $e->getMessage());
            return false;
        }
    }

    /* ==========================
       CAMBIAR ESTADO RAE
    ========================== */
    public function cambiarEstado(int $id, string $estado): bool {
        try {
            $sql = "UPDATE raes SET estado = ? WHERE id_rae = ?";
            $stmt = $this->conn->prepare($sql);

            return $stmt->execute([$estado, $id]);
            
        } catch (Exception $e) {
            error_log("Error en RaeModel->cambiarEstado(): " . $e->getMessage());
            return false;
        }
    }

    /* ==========================
       VERIFICAR ACTIVIDADES ACTIVAS
    ========================== */
    public function tieneActividadesActivas(int $id_rae): bool {
        try {
            $sql = "SELECT COUNT(*) as total 
                    FROM actividades_formacion 
                    WHERE id_rae = ? 
                    AND estado = 'Activa'";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id_rae]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result && $result['total'] > 0;
            
        } catch (Exception $e) {
            error_log("Error en RaeModel->tieneActividadesActivas(): " . $e->getMessage());
            return false;
        }
    }

    /* ==========================
       OBTENER PROGRAMA DEL RAE
    ========================== */
    public function obtenerProgramaRae(int $id_rae): ?int {
        try {
            $sql = "SELECT id_programa FROM raes WHERE id_rae = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id_rae]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? (int)$result['id_programa'] : null;
            
        } catch (Exception $e) {
            error_log("Error en RaeModel->obtenerProgramaRae(): " . $e->getMessage());
            return null;
        }
    }
}