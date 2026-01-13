<?php

class NotificacionModel {

    private $db;

    public function __construct(PDO $conn)
    {
        $this->db = $conn;
    }

    /* 
       CREATE NOTIFICATION
       Used by system (automatic)
        */
    public function crear($data)
    {
        $sql = "INSERT INTO notificaciones (
                    id_usuario,
                    tipo,
                    titulo,
                    mensaje,
                    referencia_tipo,
                    referencia_id
                ) VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $data['id_usuario'] ?? null,        // null = global
            $data['tipo'],
            $data['titulo'],
            $data['mensaje'],
            $data['referencia_tipo'] ?? null,
            $data['referencia_id'] ?? null
        ]);
    }

    /* 
       GET USER NOTIFICATIONS
        */
    public function getByUsuario($id_usuario)
    {
        $sql = "SELECT *
                FROM notificaciones
                WHERE id_usuario = ? OR id_usuario IS NULL
                ORDER BY fecha_creacion DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_usuario]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* 
       GET UNREAD COUNT
        */
    public function contarNoLeidas($id_usuario)
    {
        $sql = "SELECT COUNT(*) AS total
                FROM notificaciones
                WHERE (id_usuario = ? OR id_usuario IS NULL)
                  AND leida = 0";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_usuario]);

        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /* 
       MARK AS READ
        */
    public function marcarLeida($id_notificacion, $id_usuario)
    {
        $sql = "UPDATE notificaciones
                SET leida = 1
                WHERE id_notificacion = ?
                  AND (id_usuario = ? OR id_usuario IS NULL)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id_notificacion, $id_usuario]);
    }

    /* 
       MARK ALL AS READ
        */
    public function marcarTodasLeidas($id_usuario)
    {
        $sql = "UPDATE notificaciones
                SET leida = 1
                WHERE id_usuario = ? OR id_usuario IS NULL";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id_usuario]);
    }
}
