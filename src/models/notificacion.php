<?php

class NotificacionModel {

    private $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    // CREAR NOTIFICACIÓN
    public function crear($id_usuario, $tipo, $titulo, $mensaje, $referencia_tipo = null, $referencia_id = null) {
        $sql = "
            INSERT INTO notificaciones (
                id_usuario,
                tipo,
                titulo,
                mensaje,
                referencia_tipo,
                referencia_id,
                leida,
                fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $id_usuario,
            $tipo,
            $titulo,
            $mensaje,
            $referencia_tipo,
            $referencia_id
        ]);
    }

    // LISTAR NOTIFICACIONES DEL USUARIO
    public function listarPorUsuario($id_usuario) {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM notificaciones
            WHERE id_usuario = ?
            ORDER BY fecha_creacion DESC
        ");
        $stmt->execute([$id_usuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // CONTAR NO LEÍDAS
    public function contarNoLeidas($id_usuario) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) 
            FROM notificaciones
            WHERE id_usuario = ? AND leida = 0
        ");
        $stmt->execute([$id_usuario]);
        return (int) $stmt->fetchColumn();
    }

    // MARCAR COMO LEÍDA
    public function marcarLeida($id_notificacion, $id_usuario) {
        $stmt = $this->conn->prepare("
            UPDATE notificaciones
            SET leida = 1
            WHERE id_notificacion = ? AND id_usuario = ?
        ");
        return $stmt->execute([$id_notificacion, $id_usuario]);
    }

    // MARCAR TODAS COMO LEÍDAS
    public function marcarTodasLeidas($id_usuario) {
        $stmt = $this->conn->prepare("
            UPDATE notificaciones
            SET leida = 1
            WHERE id_usuario = ?
        ");
        return $stmt->execute([$id_usuario]);
    }
}
