<?php
/**
 * Script de prueba rápida para endpoints de devoluciones
 * Ejecuta: php test_devoluciones.php
 */

// Simular sesión
session_start();
$_SESSION['id_usuario'] = 1; // Usuario de prueba

require_once __DIR__ . '/Config/database.php';
require_once __DIR__ . '/src/models/devoluciones.php';

$devolucion = new devolucion($conn);

echo "=== PRUEBA DE DEVOLUCIONES ===\n\n";

// 1. Obtener solicitudes con salida
echo "1️⃣  Solicitudes disponibles para devolución:\n";
echo str_repeat("-", 50) . "\n";
$solicitudes = $devolucion->getSolicitudesConSalida();
foreach ($solicitudes as $sol) {
    echo "  📋 Solicitud #{$sol['id_solicitud']} | Movimiento #{$sol['id_movimiento']}\n";
    echo "     Usuario: {$sol['usuario_solicitante']}\n";
    echo "     Fecha salida: {$sol['fecha_salida']}\n\n";
}

if (empty($solicitudes)) {
    echo "  ⚠️  No hay movimientos de salida disponibles\n\n";
    exit;
}

// 2. Obtener materiales del primer movimiento
$primerMovimiento = $solicitudes[0]['id_movimiento'];
echo "2️⃣  Materiales del movimiento #{$primerMovimiento}:\n";
echo str_repeat("-", 50) . "\n";
$materiales = $devolucion->getMaterialesMovimiento($primerMovimiento);
foreach ($materiales as $mat) {
    echo "  📦 {$mat['nombre']}\n";
    echo "     Salida: {$mat['cantidad_salida']} {$mat['unidad_medida']}\n";
    echo "     Devuelto: {$mat['cantidad_devuelta_total']} {$mat['unidad_medida']}\n";
    echo "     Pendiente: {$mat['cantidad_pendiente']} {$mat['unidad_medida']}\n";
    echo "     Bodega: #{$mat['id_bodega']}\n\n";
}

// 3. Obtener bodegas
echo "3️⃣  Bodegas disponibles:\n";
echo str_repeat("-", 50) . "\n";
$bodegas = $devolucion->getBodegas();
foreach ($bodegas as $bod) {
    echo "  🏪 {$bod['nombre']} (ID: {$bod['id_bodega']})\n";
    
    // Subbodegas
    $subs = $devolucion->getSubbodegas($bod['id_bodega']);
    if (!empty($subs)) {
        foreach ($subs as $sub) {
            echo "     └─ {$sub['nombre_subbodega']} (ID: {$sub['id_subbodega']})\n";
        }
    }
}

echo "\n4️⃣  Simulación de registro de devolución:\n";
echo str_repeat("-", 50) . "\n";

if (!empty($materiales)) {
    $primerMaterial = $materiales[0];
    $cantidadDevolver = min(5, $primerMaterial['cantidad_pendiente']); // Devolver máximo 5 unidades
    
    $dataPrueba = [
        'id_movimiento_salida' => $primerMovimiento,
        'id_usuario' => 1,
        'id_material' => $primerMaterial['id_material'],
        'id_bodega' => $primerMaterial['id_bodega'],
        'id_subbodega' => $primerMaterial['id_subbodega'],
        'cantidad_devuelta' => $cantidadDevolver,
        'estado_material' => 'Bueno',
        'observaciones' => 'Devolución de prueba desde test_devoluciones.php'
    ];
    
    echo "  📝 Datos a registrar:\n";
    echo "     Material: {$primerMaterial['nombre']}\n";
    echo "     Cantidad: {$cantidadDevolver} {$primerMaterial['unidad_medida']}\n";
    echo "     Estado: Bueno\n";
    echo "     Bodega: #{$dataPrueba['id_bodega']}\n\n";
    
    echo "  ⚠️  Para registrar esta devolución, descomenta las líneas al final del script\n";
    
    // Descomenta estas líneas para hacer la prueba real:
    /*
    if ($devolucion->registrar($dataPrueba)) {
        echo "  ✅ Devolución registrada exitosamente\n";
        
        // Verificar stock actualizado
        $stmt = $conn->prepare("SELECT stock_actual FROM stock_bodega WHERE id_bodega = ? AND id_material = ?");
        $stmt->execute([$dataPrueba['id_bodega'], $dataPrueba['id_material']]);
        $stock = $stmt->fetchColumn();
        echo "  📊 Nuevo stock en bodega: {$stock} unidades\n";
    } else {
        echo "  ❌ Error al registrar devolución\n";
    }
    */
}

echo "\n5️⃣  Historial de devoluciones:\n";
echo str_repeat("-", 50) . "\n";
$historial = $devolucion->listar();
if (empty($historial)) {
    echo "  📭 No hay devoluciones registradas\n";
} else {
    foreach ($historial as $dev) {
        echo "  🔄 Devolución #{$dev['id_devolucion']}\n";
        echo "     Material: {$dev['material']}\n";
        echo "     Cantidad: {$dev['cantidad_devuelta']}\n";
        echo "     Estado: {$dev['estado_material']}\n";
        echo "     Fecha: {$dev['fecha_hora']}\n";
        echo "     Usuario: {$dev['usuario']}\n\n";
    }
}

echo "\n✅ Pruebas completadas\n";
echo "\nPara probar los endpoints HTTP, abre:\n";
echo "http://localhost/Gestion-inventario/src/controllers/devolucion_controller.php?action=listarSolicitudes\n";
