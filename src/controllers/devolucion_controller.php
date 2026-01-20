<?php
session_start();
require_once __DIR__ . '/../../Config/database.php';
require_once __DIR__ . '/../models/devoluciones.php';

header('Content-Type: application/json');

$devolucion = new devolucion($conn);

$method = $_SERVER['REQUEST_METHOD'];

// Manejar solicitudes GET
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'listarSolicitudes':
            try {
                $solicitudes = $devolucion->getSolicitudesConSalida();
                echo json_encode(['success' => true, 'data' => $solicitudes]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error al obtener solicitudes: ' . $e->getMessage()]);
            }
            break;

        case 'obtenerMateriales':
            $idMovimiento = $_GET['id_movimiento'] ?? null;
            if (!$idMovimiento) {
                echo json_encode(['success' => false, 'message' => 'ID de movimiento requerido']);
                exit;
            }

            try {
                $materiales = $devolucion->getMaterialesMovimiento($idMovimiento);
                echo json_encode(['success' => true, 'data' => $materiales]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error al obtener materiales: ' . $e->getMessage()]);
            }
            break;

        case 'listarDevoluciones':
            try {
                $devoluciones = $devolucion->listar();
                echo json_encode(['success' => true, 'data' => $devoluciones]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error al listar devoluciones: ' . $e->getMessage()]);
            }
            break;

        case 'obtenerBodegas':
            try {
                $bodegas = $devolucion->getBodegas();
                echo json_encode(['success' => true, 'data' => $bodegas]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error al obtener bodegas: ' . $e->getMessage()]);
            }
            break;

        case 'obtenerSubbodegas':
            $idBodega = $_GET['id_bodega'] ?? null;
            if (!$idBodega) {
                echo json_encode(['success' => false, 'message' => 'ID de bodega requerido']);
                exit;
            }

            try {
                $subbodegas = $devolucion->getSubbodegas($idBodega);
                echo json_encode(['success' => true, 'data' => $subbodegas]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error al obtener subbodegas: ' . $e->getMessage()]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
    exit;
}

// Manejar solicitudes POST
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'registrar') {
        $id_movimiento_salida = $_POST['id_movimiento_salida'] ?? null;
        $id_usuario = $_SESSION['id_usuario'] ?? null;
        $id_material = $_POST['id_material'] ?? null;
        $id_bodega = $_POST['id_bodega'] ?? null;
        $id_subbodega = $_POST['id_subbodega'] ?? null;
        $cantidad_devuelta = $_POST['cantidad_devuelta'] ?? null;
        $estado_material = $_POST['estado_material'] ?? null;
        $observaciones = $_POST['observaciones'] ?? '';

        // Validaciones
        if (!$id_movimiento_salida || !$id_material || !$id_bodega || !$cantidad_devuelta || !$estado_material) {
            echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios deben estar completos']);
            exit;
        }

        if ($cantidad_devuelta <= 0) {
            echo json_encode(['success' => false, 'message' => 'La cantidad devuelta debe ser mayor a 0']);
            exit;
        }

        // Validar que no se devuelva más de lo prestado
        $materiales = $devolucion->getMaterialesMovimiento($id_movimiento_salida);
        $materialEncontrado = false;
        foreach ($materiales as $mat) {
            if ($mat['id_material'] == $id_material) {
                $materialEncontrado = true;
                if ($cantidad_devuelta > $mat['cantidad_pendiente']) {
                    echo json_encode([
                        'success' => false, 
                        'message' => "No puede devolver más de lo pendiente ({$mat['cantidad_pendiente']} {$mat['unidad_medida']})"
                    ]);
                    exit;
                }
                break;
            }
        }

        if (!$materialEncontrado) {
            echo json_encode(['success' => false, 'message' => 'Material no encontrado en el movimiento o ya fue devuelto completamente']);
            exit;
        }

        $data = [
            'id_movimiento_salida' => $id_movimiento_salida,
            'id_usuario' => $id_usuario,
            'id_material' => $id_material,
            'id_bodega' => $id_bodega,
            'id_subbodega' => $id_subbodega,
            'cantidad_devuelta' => $cantidad_devuelta,
            'estado_material' => $estado_material,
            'observaciones' => $observaciones
        ];

        if ($devolucion->registrar($data)) {
            echo json_encode(['success' => true, 'message' => 'Devolución registrada correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al registrar la devolución']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    exit;
}
