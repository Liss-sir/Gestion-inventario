<?php
// =====================================================
// ✅ REPORTES CONTROLLER (FUNCIONAL)
// - consumo-ficha ✅ (ya lo tenías)
// - consumo-programa ✅ REAL
// - consumo-rae ✅ REAL si existe en tu BD
// - movimientos ✅ REAL
// - material-faltante ✅ REAL (stock real o calculado)
// =====================================================

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../Config/database.php";
require_once __DIR__ . "/../models/ReportesModel.php";

// ============================
// ✅ Helpers
// ============================
function jsonResponse($ok, $message = "", $data = null, $code = 200)
{
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
    }
    http_response_code($code);
    echo json_encode([
        "ok" => $ok,
        "message" => $message,
        "data" => $data
    ]);
    exit;
}

function formatCOP($number)
{
    return '$' . number_format((float)$number, 0, ',', '.');
}

function e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, "UTF-8");
}

function renderPDF($html, $filename = "reporte.pdf")
{
    $autoload = __DIR__ . "/../../vendor/autoload.php";
    if (!file_exists($autoload)) {
        jsonResponse(false, "No se encontró Dompdf. Instala con: composer require dompdf/dompdf", null, 500);
    }

    require_once $autoload;

    $options = new Dompdf\Options();
    $options->set("isRemoteEnabled", true);
    $options->set("defaultFont", "DejaVu Sans");

    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();

    if (ob_get_length()) ob_end_clean();

    header("Content-Type: application/pdf");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    echo $dompdf->output();
    exit;
}

function renderPrintView($html)
{
    if (ob_get_length()) ob_end_clean();

    header("Content-Type: text/html; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    echo $html;
    exit;
}

/**
 * ✅ Header HTML (bonito y consistente)
 */
function buildPdfHeaderHtml($title, $subtitle, $filters)
{
    date_default_timezone_set("America/Bogota");

    $fechaDescarga = date("d/m/Y");
    $horaDescarga  = date("h:i A");

    $fechaInicioLabel = !empty($filters["fecha_inicio"])
        ? date("d/m/Y", strtotime($filters["fecha_inicio"]))
        : "N/A";

    $fechaFinLabel = !empty($filters["fecha_fin"])
        ? date("d/m/Y", strtotime($filters["fecha_fin"]))
        : "N/A";

    $programaLabel = ($filters["programa"] ?? "all") === "all" ? "Todos" : (string)($filters["programa"] ?? "Todos");
    $fichaLabel = ($filters["ficha"] ?? "all") === "all" ? "Todas" : (string)($filters["ficha"] ?? "Todas");

    $logoCandidates = [
        __DIR__ . "/../assets/img/logo-sena-negro.png",
        __DIR__ . "/../assets/img/sena-negro.png",
        __DIR__ . "/../assets/img/logo_sena_negro.png",
        __DIR__ . "/../assets/img/logo-sena.png",
        __DIR__ . "/../assets/img/sena.png",
    ];

    $logoBase64 = "";
    foreach ($logoCandidates as $p) {
        if (file_exists($p)) {
            $ext = pathinfo($p, PATHINFO_EXTENSION);
            $mime = ($ext === "jpg" || $ext === "jpeg") ? "jpeg" : "png";
            $logoBase64 = "data:image/$mime;base64," . base64_encode(file_get_contents($p));
            break;
        }
    }

    $avoidCache = time();

    return "
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#0f172a; }
        .header { width: 100%; margin-bottom: 14px; border-bottom: 2px solid #0f172a; padding-bottom: 10px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-left { vertical-align: top; }
        .header-right { text-align: right; vertical-align: top; }
        .title { font-size: 18px; font-weight: 700; margin: 0 0 4px 0; }
        .subtitle { font-size: 12px; color:#475569; margin: 0; }
        .meta { margin-top: 8px; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; font-size: 11px; color: #334155; line-height: 1.5; }
        .logo { width: 70px; height: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 9px; border: 1px solid #e2e8f0; }
        th { background: #f1f5f9; text-align: left; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: 700; background: #f8fafc; }
        .badge { display:inline-block; padding:4px 10px; border:1px solid #cbd5e1; border-radius: 999px; font-size: 11px; }
        .muted { color:#64748b; font-size: 11px; }
    </style>

    <div class='header'>
        <table class='header-table'>
            <tr>
                <td class='header-left'>
                    <div class='title'>" . e($title) . "</div>
                    <div class='subtitle'>" . e($subtitle) . "</div>
                </td>
                <td class='header-right'>
                    " . (!empty($logoBase64) ? "<img class='logo' src='{$logoBase64}?v={$avoidCache}' />" : "") . "
                </td>
            </tr>
        </table>

        <div class='meta'>
            <div><strong>Fecha descarga:</strong> " . e($fechaDescarga) . "</div>
            <div><strong>Hora descarga:</strong> " . e($horaDescarga) . "</div>
            <div style='margin-top:6px;'>
                <strong>Filtros:</strong><br>
                Fecha inicio: " . e($fechaInicioLabel) . " |
                Fecha fin: " . e($fechaFinLabel) . " |
                Programa: " . e($programaLabel) . " |
                Ficha: " . e($fichaLabel) . "
            </div>
        </div>
    </div>
    ";
}

function getActionName()
{
    $action = $_GET["action"] ?? "";
    if (!$action && isset($_GET["type"])) $action = "generate_pdf";
    return $action;
}

try {
    if (!isset($_SESSION["usuario_id"])) {
        jsonResponse(false, "No autenticado", null, 401);
    }

    if (!isset($conn) || !($conn instanceof PDO)) {
        jsonResponse(false, "Conexión BD inválida (\$conn no es PDO)", null, 500);
    }

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $model  = new ReportesModel($conn);
    $action = getActionName();

    $filters = [
        "fecha_inicio" => $_GET["fecha_inicio"] ?? null,
        "fecha_fin"    => $_GET["fecha_fin"] ?? null,
        "programa"     => $_GET["programa"] ?? "all",
        "ficha"        => $_GET["ficha"] ?? "all",
    ];

    $type = $_GET["type"] ?? ($_GET["report_id"] ?? "");

    // =====================================================
    // ✅ EXPORTAR CONSUMO FICHA (ya funciona)
    // =====================================================
    if ($action === "export_consumo_ficha") {
        $rows = $model->getConsumoPorFicha($filters);

        $totalConsumo = 0;
        $totalCosto   = 0;
        foreach ($rows as $r) {
            $totalConsumo += (int)($r["consumo"] ?? 0);
            $totalCosto   += (int)($r["costo"] ?? 0);
        }

        $file = "Consumo_por_Ficha_" . date("Y-m-d_H-i") . ".pdf";
        $header = buildPdfHeaderHtml("Reporte: Consumo por Ficha", "Detalle de consumo y costos por ficha", $filters);

        $html = "<html><head><meta charset='UTF-8'></head><body>$header
            <table>
                <thead>
                    <tr>
                        <th>Ficha</th>
                        <th>Programa</th>
                        <th class='num'>Consumo</th>
                        <th class='num'>Costo</th>
                    </tr>
                </thead>
                <tbody>";

        if (empty($rows)) {
            $html .= "<tr><td colspan='4'>No hay datos con los filtros seleccionados.</td></tr>";
        } else {
            foreach ($rows as $r) {
                $html .= "
                <tr>
                    <td><span class='badge'>" . e($r["ficha"]) . "</span></td>
                    <td>" . e($r["programa"]) . "</td>
                    <td class='num'>" . e((int)$r["consumo"]) . " uds</td>
                    <td class='num'>" . e(formatCOP((int)$r["costo"])) . "</td>
                </tr>";
            }
        }

        $html .= "</tbody>
                <tfoot>
                    <tr>
                        <td colspan='2'>Total</td>
                        <td class='num'>" . e($totalConsumo) . " uds</td>
                        <td class='num'>" . e(formatCOP($totalCosto)) . "</td>
                    </tr>
                </tfoot>
            </table>
        </body></html>";

        renderPDF($html, $file);
    }

    // =====================================================
    // ✅ CARDS: GENERATE PDF / GENERAR PDF (FUNCIONALES)
    // =====================================================
    if ($action === "generate_pdf" || $action === "generar_pdf") {

        $type = trim((string)$type);

        // ✅ 1) consumo-ficha => reusa export real
        if ($type === "consumo-ficha") {
            $qs = $_GET;
            $qs["action"] = "export_consumo_ficha";
            header("Location: reportes_controller.php?" . http_build_query($qs));
            exit;
        }

        // ✅ 2) consumo-programa ✅ REAL
        if ($type === "consumo-programa") {
            $rows = $model->getConsumoPorProgramaDetalle($filters);

            $file = "Consumo_por_Programa_" . date("Y-m-d_H-i") . ".pdf";
            $header = buildPdfHeaderHtml("Reporte: Consumo por Programa", "Consumo y costo agrupado por programa", $filters);

            $html = "<html><head><meta charset='UTF-8'></head><body>$header
                <table>
                    <thead>
                        <tr>
                            <th>Programa</th>
                            <th class='num'>Consumo</th>
                            <th class='num'>Costo</th>
                        </tr>
                    </thead>
                    <tbody>";

            $totalConsumo = 0;
            $totalCosto = 0;

            if (empty($rows)) {
                $html .= "<tr><td colspan='3'>No hay datos con los filtros seleccionados.</td></tr>";
            } else {
                foreach ($rows as $r) {
                    $totalConsumo += (int)$r["consumo"];
                    $totalCosto   += (int)$r["costo"];

                    $html .= "
                    <tr>
                        <td>" . e($r["programa"]) . "</td>
                        <td class='num'>" . e((int)$r["consumo"]) . " uds</td>
                        <td class='num'>" . e(formatCOP((int)$r["costo"])) . "</td>
                    </tr>";
                }
            }

            $html .= "</tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class='num'>" . e($totalConsumo) . " uds</td>
                        <td class='num'>" . e(formatCOP($totalCosto)) . "</td>
                    </tr>
                </tfoot>
                </table>
            </body></html>";

            renderPDF($html, $file);
        }

        // ✅ 3) consumo-rae ✅ REAL si existe id_rae y tabla rae
        if ($type === "consumo-rae") {
            $rows = $model->getConsumoPorRAE($filters);

            $file = "Consumo_por_RAE_" . date("Y-m-d_H-i") . ".pdf";
            $header = buildPdfHeaderHtml("Reporte: Consumo por RAE", "Consumo y costo agrupado por RAE", $filters);

            $html = "<html><head><meta charset='UTF-8'></head><body>$header
                <table>
                    <thead>
                        <tr>
                            <th>RAE</th>
                            <th class='num'>Consumo</th>
                            <th class='num'>Costo</th>
                        </tr>
                    </thead>
                    <tbody>";

            $totalConsumo = 0;
            $totalCosto = 0;

            if (empty($rows)) {
                $html .= "
                <tr>
                    <td colspan='3'>
                        No se encontraron datos para RAE.<br>
                        <span class='muted'>Nota: este reporte requiere que exista <strong>movimientos_material.id_rae</strong> y una tabla de RAEs.</span>
                    </td>
                </tr>";
            } else {
                foreach ($rows as $r) {
                    $totalConsumo += (int)$r["consumo"];
                    $totalCosto   += (int)$r["costo"];

                    $html .= "
                    <tr>
                        <td>" . e($r["rae"]) . "</td>
                        <td class='num'>" . e((int)$r["consumo"]) . " uds</td>
                        <td class='num'>" . e(formatCOP((int)$r["costo"])) . "</td>
                    </tr>";
                }
            }

            $html .= "</tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class='num'>" . e($totalConsumo) . " uds</td>
                        <td class='num'>" . e(formatCOP($totalCosto)) . "</td>
                    </tr>
                </tfoot>
                </table>
            </body></html>";

            renderPDF($html, $file);
        }

        // ✅ 4) movimientos ✅ REAL
        if ($type === "movimientos") {
            $rows = $model->getMovimientosDetalle($filters, 300);

            $file = "Historial_Movimientos_" . date("Y-m-d_H-i") . ".pdf";
            $header = buildPdfHeaderHtml("Reporte: Movimientos", "Historial completo de movimientos", $filters);

            $html = "<html><head><meta charset='UTF-8'></head><body>$header
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Material</th>
                            <th class='num'>Cantidad</th>
                            <th>Ficha</th>
                            <th>Programa</th>
                        </tr>
                    </thead>
                    <tbody>";

            if (empty($rows)) {
                $html .= "<tr><td colspan='6'>No hay movimientos con los filtros seleccionados.</td></tr>";
            } else {
                foreach ($rows as $r) {
                    $fecha = !empty($r["fecha"]) ? date("d/m/Y H:i", strtotime($r["fecha"])) : "N/A";
                    $html .= "
                    <tr>
                        <td>" . e($fecha) . "</td>
                        <td>" . e($r["tipo"]) . "</td>
                        <td>" . e($r["material"]) . "</td>
                        <td class='num'>" . e((int)$r["cantidad"]) . "</td>
                        <td>" . e($r["ficha"]) . "</td>
                        <td>" . e($r["programa"]) . "</td>
                    </tr>";
                }
            }

            $html .= "</tbody></table>
                <p class='muted' style='margin-top:8px;'>Mostrando máximo 300 registros (más recientes).</p>
            </body></html>";

            renderPDF($html, $file);
        }

        // ✅ 5) material-faltante ✅ REAL
        if ($type === "material-faltante") {
            $threshold = isset($_GET["threshold"]) ? (int)$_GET["threshold"] : 5;
            $rows = $model->getMaterialFaltante($filters, $threshold);

            $file = "Material_Faltante_" . date("Y-m-d_H-i") . ".pdf";
            $header = buildPdfHeaderHtml("Reporte: Material Faltante", "Stock bajo o agotado", $filters);

            $html = "<html><head><meta charset='UTF-8'></head><body>$header
                <table>
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th class='num'>Stock</th>
                            <th class='num'>Mínimo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>";

            if (empty($rows)) {
                $html .= "<tr><td colspan='4'>No hay materiales en stock bajo con el umbral actual.</td></tr>";
            } else {
                foreach ($rows as $r) {
                    $stock = (int)($r["stock"] ?? 0);
                    $min   = (int)($r["minimo"] ?? 5);
                    $estado = ($stock <= 0) ? "Agotado" : "Stock Bajo";

                    $html .= "
                    <tr>
                        <td>" . e($r["material"]) . "</td>
                        <td class='num'>" . e($stock) . "</td>
                        <td class='num'>" . e($min) . "</td>
                        <td>" . e($estado) . "</td>
                    </tr>";
                }
            }

            $html .= "</tbody></table>
                <p class='muted' style='margin-top:8px;'>Umbral usado: <= " . e($threshold) . "</p>
            </body></html>";

            renderPDF($html, $file);
        }

        // ✅ Si llega aquí, el type no existe
        jsonResponse(false, "Tipo de reporte no soportado aún", ["type" => $type], 400);
    }

    // =====================================================
    // ✅ PRINT VIEW (ok)
    // =====================================================
    if ($action === "print_view") {
        $type = $type ?: "reporte";

        $html = "
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <title>Vista Imprimible</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 18px; color:#0f172a; }
                .box { margin-top: 14px; padding: 12px; border:1px solid #e2e8f0; border-radius: 10px; background:#f8fafc; }
                @media print { .no-print { display: none; } }
                button { padding: 8px 12px; border-radius: 8px; border:1px solid #cbd5e1; background:#fff; cursor:pointer; }
            </style>
        </head>
        <body>
            <div class='no-print' style='margin-bottom:10px;'>
                <button onclick='window.print()'>Imprimir</button>
            </div>

            <h2>Vista Imprimible: " . e($type) . "</h2>
            <div class='box'>
                <strong>Filtros</strong><br>
                Fecha inicio: " . e($filters["fecha_inicio"] ?: "N/A") . "<br>
                Fecha fin: " . e($filters["fecha_fin"] ?: "N/A") . "<br>
                Programa: " . e($filters["programa"] ?: "all") . "<br>
                Ficha: " . e($filters["ficha"] ?: "all") . "
            </div>
        </body>
        </html>";

        renderPrintView($html);
    }

    // =====================================================
    // ✅ JSON endpoints
    // =====================================================
    if ($action === "programas") jsonResponse(true, "OK", $model->getProgramas());
    if ($action === "fichas") jsonResponse(true, "OK", $model->getFichas());
    if ($action === "dashboard") jsonResponse(true, "OK", $model->getDashboardData($filters));

    jsonResponse(false, "Acción no válida", ["action" => $action, "get" => $_GET], 400);

} catch (Throwable $e) {
    jsonResponse(false, "Error interno: " . $e->getMessage(), null, 500);
}
