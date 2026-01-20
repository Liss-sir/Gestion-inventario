<?php
require 'Config/database.php';

echo "=== ESTRUCTURA devoluciones_material ===\n";
$stmt = $conn->query('DESC devoluciones_material');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo $r['Field'] . " -> " . $r['Type'] . " (" . ($r['Null']=='YES' ? 'NULL' : 'NOT NULL') . ")\n";
}

echo "\n=== SOLICITUDES CON MOVIMIENTOS DE SALIDA ===\n";
$sql = "SELECT s.id_solicitud, s.estado, mm.id_movimiento, mm.fecha_hora
        FROM solicitudes_material s
        INNER JOIN movimientos_material mm ON mm.id_solicitud = s.id_solicitud
        WHERE mm.tipo_movimiento = 'Salida' AND s.estado = 'Aprobada'
        ORDER BY mm.fecha_hora DESC
        LIMIT 5";
$stmt = $conn->query($sql);
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "Solicitud {$r['id_solicitud']} | Movimiento {$r['id_movimiento']} | {$r['fecha_hora']}\n";
}
