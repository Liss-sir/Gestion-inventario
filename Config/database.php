<?php
$host = 'localhost';
$dbname = 'gestion_inventario';
$user = 'root';
$pass = '123456'; 

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 🔥 SOLO muestra el mensaje si accedes directamente a database.php
    if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
        echo "conexion exitosa a la base de datos";
    }

} catch (PDOException $e) {

    // Solo muestra JSON si este archivo se ejecuta directamente
    if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
        header('Content-Type: application/json');
        echo json_encode([
            "status" => "error",
            "mensaje" => "Error al conectar con la base de datos: " . $e->getMessage()
        ]);
    }

    exit;
}
?>
