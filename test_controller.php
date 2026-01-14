<?php
// Simular la llamada al controlador
ob_start();
include 'src/controllers/movimiento_controller.php?accion=listar';
$output = ob_get_clean();

echo "OUTPUT RAW:\n";
echo var_dump($output);
echo "\n\nFIRST 500 CHARS:\n";
echo substr($output, 0, 500);
?>
