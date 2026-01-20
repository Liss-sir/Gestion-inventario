<?php
require_once 'Config/database.php';
$stmt = $conn->query('DESCRIBE usuarios');
echo "Columnas de tabla usuarios:\n";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  - " . $row['Field'] . "\n";
}
