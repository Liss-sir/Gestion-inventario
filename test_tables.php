<?php
require_once 'Config/database.php';

header('Content-Type: application/json');

try {
    $result = $conn->query('SHOW TABLES');
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'tables' => $tables
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
