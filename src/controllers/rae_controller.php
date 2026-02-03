<?php

// Establecer headers primero
header("Content-Type: application/json; charset=utf-8");

// Iniciar buffer de salida
ob_start();

try {
    require_once __DIR__ . "/../../Config/database.php";
    require_once __DIR__ . "/../models/rae.php";

    // Verificar que la conexión existe
    if (!isset($conn) || !$conn) {
        throw new Exception("Error de conexión a la base de datos");
    }

    class RaeController {

        private $model;

        public function __construct(PDO $conn) {
            $this->model = new RaeModel($conn);
        }

        private function getJson() {
            $input = file_get_contents("php://input");
            return json_decode($input, true) ?? [];
        }

        private function jsonResponse($data, int $code = 200) {
            // Limpiar buffer antes de enviar JSON
            ob_end_clean();
            
            http_response_code($code);
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }

        /* ==========================
           LISTAR RAE
        ========================== */
        public function listar() {
            try {
                $raes = $this->model->listar();
                
                // Formatear respuesta para frontend
                $formattedRaes = array_map(function($rae) {
                    return [
                        'id_rae' => $rae['id_rae'] ?? null,
                        'codigo_rae' => $rae['codigo_rae'] ?? '',
                        'descripcion_rae' => $rae['descripcion_rae'] ?? '',
                        'id_programa' => $rae['id_programa'] ?? null,
                        'programa' => $rae['nombre_programa'] ?? '',
                        'codigo_programa' => $rae['codigo_programa'] ?? '',
                        'nivel_programa' => $rae['nivel_programa'] ?? '',
                        'estado' => $rae['estado'] ?? 'Activo'
                    ];
                }, $raes);
                
                $this->jsonResponse($formattedRaes);
                
            } catch (Exception $e) {
                error_log("Error en listar: " . $e->getMessage());
                $this->jsonResponse(['error' => 'Error interno al listar RAEs'], 500);
            }
        }

        /* ==========================
           OBTENER POR ID
        ========================== */
        public function obtener($id) {
            if (!$id) {
                $this->jsonResponse(['error' => 'id_rae requerido'], 400);
            }

            try {
                $rae = $this->model->obtener((int)$id);
                
                if (!$rae) {
                    $this->jsonResponse(['error' => 'RAE no encontrado'], 404);
                }
                
                $this->jsonResponse($rae);
                
            } catch (Exception $e) {
                error_log("Error en obtener: " . $e->getMessage());
                $this->jsonResponse(['error' => 'Error interno al obtener RAE'], 500);
            }
        }

        /* ==========================
           VERIFICAR ACTIVIDADES ACTIVAS
        ========================== */
        public function verificar_actividades_activas($id_rae) {
            if (!$id_rae) {
                $this->jsonResponse(['error' => 'id_rae requerido'], 400);
            }

            try {
                $tieneActividades = $this->model->tieneActividadesActivas((int)$id_rae);
                
                $this->jsonResponse([
                    'tiene_actividades_activas' => $tieneActividades,
                    'id_rae' => (int)$id_rae
                ]);
                
            } catch (Exception $e) {
                error_log("Error en verificar_actividades_activas: " . $e->getMessage());
                $this->jsonResponse(['error' => 'Error interno al verificar actividades'], 500);
            }
        }

        /* ==========================
           CREAR RAE
        ========================== */
        public function crear() {
            $data = $this->getJson();

            if (empty($data)) {
                $this->jsonResponse(['error' => 'Datos inválidos o vacíos'], 400);
            }

            $required = ['codigo_rae', 'descripcion_rae', 'id_programa', 'estado'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $this->jsonResponse(['error' => "Falta el campo requerido: $field"], 400);
                }
            }

            try {
                // Verificar si el código ya existe
                if ($this->model->existeCodigo($data['codigo_rae'])) {
                    $this->jsonResponse(['error' => "El código RAE '{$data['codigo_rae']}' ya existe"], 400);
                }

                // Crear RAE
                $ok = $this->model->crear(
                    $data['codigo_rae'],
                    $data['descripcion_rae'],
                    (int)$data['id_programa'],
                    $data['estado']
                );

                if (!$ok) {
                    $this->jsonResponse(['error' => 'Error al crear RAE'], 500);
                }

                $idRae = $this->model->conn->lastInsertId();

                $this->jsonResponse([
                    "success" => true,
                    "message" => "RAE creado correctamente",
                    "id_rae" => $idRae
                ]);

            } catch (Exception $e) {
                error_log("Error en crear: " . $e->getMessage());
                $this->jsonResponse(['error' => 'Error interno al crear RAE'], 500);
            }
        }

        /* ==========================
           ACTUALIZAR RAE
        ========================== */
        public function actualizar() {
            $data = $this->getJson();

            if (!isset($data["id_rae"])) {
                $this->jsonResponse(["error" => "id_rae es obligatorio"], 400);
            }

            try {
                // Verificar si hay actividades activas
                $tieneActividades = $this->model->tieneActividadesActivas((int)$data["id_rae"]);
                
                // Si tiene actividades activas y está intentando cambiar el programa, bloquear
                if ($tieneActividades && isset($data["id_programa"])) {
                    // Obtener el programa actual del RAE
                    $programaActual = $this->model->obtenerProgramaRae((int)$data["id_rae"]);
                    
                    if ($programaActual !== null && $programaActual != (int)$data["id_programa"]) {
                        $this->jsonResponse([
                            "error" => "No se puede cambiar el programa de formación porque este RAE tiene actividades activas asociadas"
                        ], 400);
                    }
                }

                // Verificar código único si se está cambiando
                if (isset($data["codigo_rae"])) {
                    if ($this->model->existeCodigo($data["codigo_rae"], (int)$data["id_rae"])) {
                        $this->jsonResponse(["error" => "El código RAE ya existe en otro registro"], 400);
                    }
                }

                // Actualizar RAE
                $ok = $this->model->actualizar(
                    (int)$data["id_rae"],
                    $data["codigo_rae"] ?? null,
                    isset($data["id_programa"]) ? (int)$data["id_programa"] : null,
                    $data["descripcion_rae"] ?? null,
                    $data["estado"] ?? null
                );

                if (!$ok) {
                    $this->jsonResponse(["error" => "No hay cambios para actualizar o el RAE no existe"], 400);
                }

                $this->jsonResponse(["mensaje" => "RAE actualizado correctamente"]);

            } catch (Exception $e) {
                error_log("Error en actualizar: " . $e->getMessage());
                $this->jsonResponse(['error' => 'Error interno al actualizar RAE'], 500);
            }
        }

        /* ==========================
           CAMBIAR ESTADO
        ========================== */
        public function cambiar_estado() {
            $data = $this->getJson();

            if (!isset($data["id_rae"], $data["estado"])) {
                $this->jsonResponse(["error" => "Datos incompletos"], 400);
            }

            try {
                $ok = $this->model->cambiarEstado(
                    (int)$data["id_rae"],
                    $data["estado"]
                );

                if (!$ok) {
                    $this->jsonResponse(["error" => "No se pudo actualizar el estado"], 400);
                }

                $this->jsonResponse(["mensaje" => "Estado actualizado correctamente"]);

            } catch (Exception $e) {
                error_log("Error en cambiar_estado: " . $e->getMessage());
                $this->jsonResponse(['error' => 'Error interno al cambiar estado'], 500);
            }
        }
    }

    /* ==========================
       ROUTER con validación
    ========================== */
    $accion = $_GET['accion'] ?? null;
    $id = $_GET['id_rae'] ?? null;

    if (!$accion) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Acción no especificada'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $controller = new RaeController($conn);

    switch ($accion) {
        case "listar":
            $controller->listar();
            break;

        case "obtener":
            $controller->obtener($id);
            break;

        case "verificar_actividades":
            $controller->verificar_actividades_activas($id);
            break;

        case "crear":
            $controller->crear();
            break;

        case "actualizar":
            $controller->actualizar();
            break;

        case "cambiar_estado":
            $controller->cambiar_estado();
            break;

        default:
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida: ' . htmlspecialchars($accion)], JSON_UNESCAPED_UNICODE);
            exit;
    }

} catch (Exception $e) {
    // Manejar cualquier error no capturado
    ob_end_clean();
    header("Content-Type: application/json; charset=utf-8");
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}