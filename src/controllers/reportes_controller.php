<?php
// =====================================================
// ✅ REPORTES CONTROLLER (100% FUNCIONAL + GRÁFICAS PDF)
// - consumo-ficha ✅
// - consumo-programa ✅
// - consumo-rae ✅ (si existe en BD)
// - movimientos ✅
// - material-faltante ✅
// - reporte personalizado ✅ PDF/CSV/EXCEL + gráficas reales
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

/**
 * =====================================================
 * ✅ PRIORIDAD UBICACIÓN (FIX GLOBAL)
 * - Si hay subbodega válida => bodega se ignora automáticamente
 * =====================================================
 */
function applyLocationPriority(array &$filters): void
{
    if (!empty($filters["subbodega"]) && $filters["subbodega"] !== "all") {
        $filters["bodega"] = "all";
    }
}

/**
 * =====================================================
 * ✅ FIX CRÍTICO: Descargar imágenes remotas y volverlas Base64
 * - Dompdf falla a veces con QuickChart (https remotas)
 * - Esto hace que SIEMPRE salgan gráficas en el PDF
 * =====================================================
 */
function downloadRemoteImageAsBase64(string $url): ?string
{
    $raw = null;

    // 1) CURL (lo más robusto)
    if (function_exists("curl_init")) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);

        // ✅ Por compatibilidad (algunos hosts fallan con SSL)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$raw || $httpCode < 200 || $httpCode >= 300) {
            $raw = null;
        }
    }

    // 2) Fallback file_get_contents
    if ($raw === null) {
        $context = stream_context_create([
            "http" => ["timeout" => 15],
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if (!$raw) {
            return null;
        }
    }

    // QuickChart devuelve PNG
    $mime = "image/png";
    return "data:$mime;base64," . base64_encode($raw);
}

/**
 * ✅ PDF renderer
 */
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
    $options->set("isHtml5ParserEnabled", true);

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
 * =====================================================
 * ✅ FOOTER PDF (FIJO ABAJO)
 * - Personalizado: "GENERADO POR SIGA - Reporte Personalizado"
 * - Otros: "Reporte generado por SIGA"
 * =====================================================
 */
function buildPdfFooterHtml(bool $isPersonalizado = false): string
{
    $text = $isPersonalizado
        ? "GENERADO POR SIGA - Reporte Personalizado"
        : "Reporte generado por SIGA";

    return "<div class='pdf-footer'>" . e($text) . "</div>";
}

/**
 * ✅ Header HTML (bonito y consistente)
 * - Incluye estilos globales del PDF (incluye footer fijo)
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

    return "
    <style>
        /* ✅ Margen inferior extra para que el footer no tape contenido */
        @page { margin: 18mm 15mm 26mm 15mm; }

        body { 
            font-family: DejaVu Sans, Arial, sans-serif; 
            font-size: 12px; 
            color:#0f172a;
            margin: 0;
            padding: 0;
        }

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
        .chart-title { font-size: 12px; font-weight: 700; margin-top: 14px; }
        .chart-box { margin: 10px 0 12px 0; padding: 10px; border:1px solid #e2e8f0; border-radius: 10px; background:#ffffff; }
        .page-break { page-break-before: always; }

        /* ✅ Footer real abajo */
        .pdf-footer{
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
            padding: 6px 0;
            border-top: 1px solid #e2e8f0;
        }
    </style>

    <div class='header'>
        <table class='header-table'>
            <tr>
                <td class='header-left'>
                    <div class='title'>" . e($title) . "</div>
                    <div class='subtitle'>" . e($subtitle) . "</div>
                </td>
                <td class='header-right'>
                    " . (!empty($logoBase64) ? "<img class='logo' src='{$logoBase64}' />" : "") . "
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

/**
 * =====================================================
 * ✅ GRÁFICAS EN PDF (Dompdf)
 * - Dompdf NO pinta Canvas/SVG
 * - Solución: QuickChart => genera PNG con Chart.js
 * - ✅ FIX: Descargar imagen y usar Base64 (más estable)
 * =====================================================
 */
function buildQuickChartUrl(array $chartConfig, int $width = 900, int $height = 320): string
{
    $payload = [
        "width" => $width,
        "height" => $height,
        "format" => "png",
        "backgroundColor" => "white",
        "version" => "2.9.4",
        "c" => json_encode($chartConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ];

    return "https://quickchart.io/chart?" . http_build_query($payload);
}

function chartBlockHtml(string $title, array $chartConfig): string
{
    $url = buildQuickChartUrl($chartConfig);

    // ✅ FIX: embed base64 para que SIEMPRE salga en PDF
    $base64 = downloadRemoteImageAsBase64($url);
    $imgSrc = $base64 ?: $url;

    return "
        <div class='chart-box'>
            <div class='chart-title'>" . e($title) . "</div>
            <div style='margin-top:8px;'>
                <img src='" . e($imgSrc) . "' style='width:100%; height:auto;' alt='" . e($title) . "'>
            </div>
        </div>
    ";
}

/**
 * ✅ Chart factories from rows
 */
function chartFromRowsConsumoMaterial(array $rows): array
{
    $rows = array_slice($rows, 0, 10);
    $labels = [];
    $data = [];

    foreach ($rows as $r) {
        $labels[] = (string)($r["material"] ?? "Material");
        $data[] = (int)($r["consumo"] ?? 0);
    }

    return [
        "type" => "horizontalBar",
        "data" => [
            "labels" => $labels,
            "datasets" => [[
                "label" => "Consumo (uds)",
                "data" => $data
            ]]
        ],
        "options" => [
            "legend" => ["display" => false],
            "scales" => [
                "xAxes" => [["ticks" => ["beginAtZero" => true]]],
                "yAxes" => [["ticks" => ["fontSize" => 10]]]
            ]
        ]
    ];
}

function chartFromRowsConsumoAgrupado(array $rows, string $labelKey, string $valueKey = "consumo"): array
{
    $rows = array_slice($rows, 0, 10);
    $labels = [];
    $data = [];

    foreach ($rows as $r) {
        $labels[] = (string)($r[$labelKey] ?? "Item");
        $data[] = (int)($r[$valueKey] ?? 0);
    }

    return [
        "type" => "bar",
        "data" => [
            "labels" => $labels,
            "datasets" => [[
                "label" => "Consumo (uds)",
                "data" => $data
            ]]
        ],
        "options" => [
            "legend" => ["display" => false],
            "scales" => [
                "yAxes" => [["ticks" => ["beginAtZero" => true]]],
                "xAxes" => [["ticks" => ["fontSize" => 9]]]
            ]
        ]
    ];
}

function chartFromMovimientosTipo(array $rows): array
{
    $map = [];
    foreach ($rows as $r) {
        $tipo = (string)($r["tipo"] ?? "N/A");
        $cant = (int)($r["cantidad"] ?? 0);
        if (!isset($map[$tipo])) $map[$tipo] = 0;
        $map[$tipo] += $cant;
    }

    $labels = array_keys($map);
    $data = array_values($map);

    return [
        "type" => "pie",
        "data" => [
            "labels" => $labels,
            "datasets" => [[
                "label" => "Cantidad",
                "data" => $data
            ]]
        ],
        "options" => [
            "legend" => ["position" => "bottom"]
        ]
    ];
}

function chartFromStock(array $rows): array
{
    $rows = array_slice($rows, 0, 12);
    $labels = [];
    $stock = [];
    $min = [];

    foreach ($rows as $r) {
        $labels[] = (string)($r["material"] ?? "Material");
        $stock[] = (int)($r["stock"] ?? 0);
        $min[] = (int)($r["minimo"] ?? 0);
    }

    return [
        "type" => "bar",
        "data" => [
            "labels" => $labels,
            "datasets" => [
                ["label" => "Stock", "data" => $stock],
                ["label" => "Mínimo", "data" => $min]
            ]
        ],
        "options" => [
            "legend" => ["position" => "bottom"],
            "scales" => [
                "yAxes" => [["ticks" => ["beginAtZero" => true]]],
                "xAxes" => [["ticks" => ["fontSize" => 9]]]
            ]
        ]
    ];
}

// =====================================================
// ✅ CHECK DATA BEFORE PDF/PRINT/EXPORT (Flowbite support)
//   action=check_data&type=consumo-ficha
// =====================================================
if (isset($_GET["action"]) && $_GET["action"] === "check_data") {
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    try {
        $type = $_GET["type"] ?? "";
        if (!$type) {
            echo json_encode([
                "ok" => false,
                "hasData" => false,
                "message" => "No se recibió el tipo de reporte."
            ]);
            exit;
        }

        $filters = [
            "fecha_inicio" => $_GET["fecha_inicio"] ?? null,
            "fecha_fin"    => $_GET["fecha_fin"] ?? null,
            "programa"     => $_GET["programa"] ?? "all",
            "ficha"        => $_GET["ficha"] ?? "all",
            "bodega"       => $_GET["bodega"] ?? "all",
            "subbodega"    => $_GET["subbodega"] ?? "all",
        ];

        // ✅ PRIORIDAD (FIX GLOBAL)
        applyLocationPriority($filters);

        $model = new ReportesModel($conn);

        // ✅ Determinar si hay data según el tipo solicitado
        $hasData = true;

        switch ($type) {
            case "consumo-ficha":
                $rowsTest = $model->getConsumoPorFicha($filters);
                $hasData = is_array($rowsTest) && count($rowsTest) > 0;
                break;

            case "consumo-programa":
                $rowsTest = $model->getConsumoPorProgramaDetalle($filters);
                $hasData = is_array($rowsTest) && count($rowsTest) > 0;
                break;

            case "consumo-rae":
                $rowsTest = $model->getConsumoPorRAE($filters);
                $hasData = is_array($rowsTest) && count($rowsTest) > 0;
                break;

            case "movimientos":
                $rowsTest = $model->getMovimientosDetalle($filters, 1);
                $hasData = is_array($rowsTest) && count($rowsTest) > 0;
                break;

            case "material-faltante":
                $threshold = isset($_GET["threshold"]) ? (int)$_GET["threshold"] : 5;
                $rowsTest = $model->getMaterialFaltante($filters, $threshold);
                $hasData = is_array($rowsTest) && count($rowsTest) > 0;
                break;

            case "consumo-materiales":
                $rowsTest = $model->getConsumoPorMaterial($filters);
                $hasData = is_array($rowsTest) && count($rowsTest) > 0;
                break;

            default:
                $hasData = true;
                break;
        }

        if (!$hasData) {
            echo json_encode([
                "ok" => true,
                "hasData" => false,
                "message" =>
                    "No se encontraron resultados para generar este reporte con los filtros actuales. " .
                    "Ajusta el rango de fechas o cambia el programa/ficha y vuelve a intentarlo."
            ]);
            exit;
        }

        echo json_encode([
            "ok" => true,
            "hasData" => true,
            "message" => "Datos disponibles para generar el reporte."
        ]);
        exit;

    } catch (Throwable $e) {
        echo json_encode([
            "ok" => false,
            "hasData" => false,
            "message" => "No fue posible validar los datos del reporte. Intenta nuevamente.",
            "debug" => $e->getMessage()
        ]);
        exit;
    }
}


// ============================
// ✅ MAIN
// ============================
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
        "bodega"      => $_GET["bodega"] ?? "all",
        "subbodega"   => $_GET["subbodega"] ?? "all",
    ];

    $type = $_GET["type"] ?? ($_GET["report_id"] ?? "");

    // ✅ PRIORIDAD (FIX GLOBAL)
    applyLocationPriority($filters);

    // =====================================================
    // ✅ EXPORTAR CONSUMO FICHA
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
        $footer = buildPdfFooterHtml(false);

        $html = "<html><head><meta charset='UTF-8'></head><body>$header";

        if (!empty($rows)) {
            $html .= chartBlockHtml("Top Fichas por Consumo", chartFromRowsConsumoAgrupado($rows, "ficha", "consumo"));
        }

        $html .= "
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
            $footer
        </body></html>";

        renderPDF($html, $file);
    }

    // =====================================================
    // ✅ CARDS: GENERATE PDF / GENERAR PDF
    // =====================================================
    if ($action === "generate_pdf" || $action === "generar_pdf") {

        $type = trim((string)$type);
        $includeCharts = strtolower(trim((string)($_GET["incluir_graficas"] ?? "yes"))) === "yes";

        // ✅ 1) consumo-ficha
        if ($type === "consumo-ficha") {
            $qs = $_GET;
            $qs["action"] = "export_consumo_ficha";
            header("Location: reportes_controller.php?" . http_build_query($qs));
            exit;
        }

        // ✅ 2) consumo-programa
        if ($type === "consumo-programa") {
            $rows = $model->getConsumoPorProgramaDetalle($filters);

            $file = "Consumo_por_Programa_" . date("Y-m-d_H-i") . ".pdf";
            $header = buildPdfHeaderHtml("Reporte: Consumo por Programa", "Consumo y costo agrupado por programa", $filters);
            $footer = buildPdfFooterHtml(false);

            $html = "<html><head><meta charset='UTF-8'></head><body>$header";

            if ($includeCharts && !empty($rows)) {
                $html .= chartBlockHtml("Top Programas por Consumo", chartFromRowsConsumoAgrupado($rows, "programa", "consumo"));
            }

            $html .= "
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
                $footer
            </body></html>";

            renderPDF($html, $file);
        }

        // ✅ 3) consumo-rae
        if ($type === "consumo-rae") {
            $rows = $model->getConsumoPorRAE($filters);

            $file = "Consumo_por_RAE_" . date("Y-m-d_H-i") . ".pdf";
            $header = buildPdfHeaderHtml("Reporte: Consumo por RAE", "Consumo y costo agrupado por RAE", $filters);
            $footer = buildPdfFooterHtml(false);

            $html = "<html><head><meta charset='UTF-8'></head><body>$header";

            if ($includeCharts && !empty($rows)) {
                $html .= chartBlockHtml("Top RAEs por Consumo", chartFromRowsConsumoAgrupado($rows, "rae", "consumo"));
            }

            $html .= "
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
                $footer
            </body></html>";

            renderPDF($html, $file);
        }

        // ✅ 4) movimientos
        if ($type === "movimientos") {
            $rows = $model->getMovimientosDetalle($filters, 300);

            $file = "Historial_Movimientos_" . date("Y-m-d_H-i") . ".pdf";
            $header = buildPdfHeaderHtml("Reporte: Movimientos", "Historial completo de movimientos", $filters);
            $footer = buildPdfFooterHtml(false);

            $html = "<html><head><meta charset='UTF-8'></head><body>$header";

            if ($includeCharts && !empty($rows)) {
                $html .= chartBlockHtml("Distribución por Tipo de Movimiento", chartFromMovimientosTipo($rows));
            }

            $html .= "
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
                $footer
            </body></html>";

            renderPDF($html, $file);
        }

        // ✅ 5) material-faltante
        if ($type === "material-faltante") {
            $threshold = isset($_GET["threshold"]) ? (int)$_GET["threshold"] : 5;
            $rows = $model->getMaterialFaltante($filters, $threshold);

            $file = "Material_Faltante_" . date("Y-m-d_H-i") . ".pdf";
            $header = buildPdfHeaderHtml("Reporte: Material Faltante", "Stock bajo o agotado", $filters);
            $footer = buildPdfFooterHtml(false);

            $html = "<html><head><meta charset='UTF-8'></head><body>$header";

            if ($includeCharts && !empty($rows)) {
                $html .= chartBlockHtml("Stock vs Mínimo (Top)", chartFromStock($rows));
            }

            $html .= "
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
                $footer
            </body></html>";

            renderPDF($html, $file);
        }

        jsonResponse(false, "Tipo de reporte no soportado aún", ["type" => $type], 400);
    }

    // =====================================================
    // ✅ REPORTE PERSONALIZADO (GENERATE_CUSTOM)
    // - pdf | csv | excel
    // =====================================================
    if ($action === "generate_custom") {

        $tipoRaw   = trim((string)($_GET["tipo_reporte"] ?? "consumo"));
        $formatRaw = strtolower(trim((string)($_GET["format"] ?? "pdf")));

        $filters["bodega"] = $_GET["bodega"] ?? "all";
        $filters["subbodega"] = $_GET["subbodega"] ?? "all";

        // ✅ PRIORIDAD (FIX GLOBAL)
        applyLocationPriority($filters);

        $incluirGraficas   = strtolower(trim((string)($_GET["incluir_graficas"] ?? "yes")));

        $tipoMap = [
            "consumo"     => "consumo-materiales",
            "movimientos" => "movimientos",
            "stock"       => "material-faltante",
            "auditoria"   => "movimientos",
        ];

        $tipo = $tipoMap[$tipoRaw] ?? "consumo-materiales";

        $format = $formatRaw;
        if ($format === "xlsx") $format = "excel";

        $rows = [];
        $title = "Reporte Personalizado";
        $subtitle = "Reporte generado desde configuración personalizada";

        if ($tipo === "consumo-materiales") {
            $title = "Reporte: Consumo de Materiales";
            $subtitle = "Consumo y costo por material";
            $rows = $model->getConsumoPorMaterial($filters);
        }
        else if ($tipo === "movimientos") {
            $title = "Reporte: Movimientos";
            $subtitle = "Registro de entradas, salidas y devoluciones";
            $rows = $model->getMovimientosDetalle($filters, 300);
        }
        else if ($tipo === "material-faltante") {
            $title = "Reporte: Estado de Stock";
            $subtitle = "Materiales con stock bajo o agotado";
            $threshold = isset($_GET["threshold"]) ? (int)$_GET["threshold"] : 5;
            $rows = $model->getMaterialFaltante($filters, $threshold);
        }
        else {
            jsonResponse(false, "Tipo no soportado en personalizado: " . $tipo, null, 400);
        }

        // ✅ CSV
        if ($format === "csv") {
            if (ob_get_length()) ob_end_clean();

            header("Content-Type: text/csv; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"Reporte_" . $tipo . "_" . date("Y-m-d_H-i") . ".csv\"");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");

            $out = fopen("php://output", "w");

            if (empty($rows)) {
                fputcsv($out, ["No hay datos con los filtros seleccionados"]);
                fclose($out);
                exit;
            }

            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }

            fclose($out);
            exit;
        }

        // ✅ EXCEL
        if ($format === "excel") {
            if (ob_get_length()) ob_end_clean();

            $filename = "Reporte_" . $tipo . "_" . date("Y-m-d_H-i") . ".xls";

            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"$filename\"");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");

            echo "<meta charset='UTF-8'>";
            echo "<h3>" . e($title) . "</h3>";
            echo "<p>" . e($subtitle) . "</p>";

            if (empty($rows)) {
                echo "<p>No hay datos con los filtros seleccionados.</p>";
                exit;
            }

            echo "<table border='1' cellpadding='6' cellspacing='0'>";
            echo "<thead><tr>";
            foreach (array_keys($rows[0]) as $col) {
                echo "<th>" . e($col) . "</th>";
            }
            echo "</tr></thead><tbody>";

            foreach ($rows as $r) {
                echo "<tr>";
                foreach ($r as $v) {
                    echo "<td>" . e($v) . "</td>";
                }
                echo "</tr>";
            }

            echo "</tbody></table>";
            exit;
        }

        // ✅ PDF
        $file = "Reporte_Personalizado_" . $tipo . "_" . date("Y-m-d_H-i") . ".pdf";
        $header = buildPdfHeaderHtml($title, $subtitle, $filters);
        $footer = buildPdfFooterHtml(true); // ✅ PERSONALIZADO

        $html = "<html><head><meta charset='UTF-8'></head><body>$header";

        if ($incluirGraficas === "yes" && !empty($rows)) {
            if ($tipo === "consumo-materiales") {
                $html .= chartBlockHtml("Top Materiales Consumidos", chartFromRowsConsumoMaterial($rows));
            }
            else if ($tipo === "movimientos") {
                $html .= chartBlockHtml("Distribución por Tipo de Movimiento", chartFromMovimientosTipo($rows));
            }
            else if ($tipo === "material-faltante") {
                $html .= chartBlockHtml("Stock vs Mínimo", chartFromStock($rows));
            }
        }

        $html .= "<table><thead><tr>";

        if (!empty($rows)) {
            foreach (array_keys($rows[0]) as $col) {
                $align = in_array($col, ["consumo", "costo", "cantidad", "stock", "minimo"], true) ? " class='num'" : "";
                $html .= "<th$align>" . e(ucfirst($col)) . "</th>";
            }
        } else {
            $html .= "<th>Resultado</th>";
        }

        $html .= "</tr></thead><tbody>";

        if (empty($rows)) {
            $html .= "<tr><td>No hay datos con los filtros seleccionados.</td></tr>";
        } else {
            foreach ($rows as $r) {
                $html .= "<tr>";
                foreach ($r as $k => $v) {
                    $isNum = in_array($k, ["consumo", "costo", "cantidad", "stock", "minimo"], true);
                    $val = ($k === "costo") ? formatCOP((int)$v) : $v;

                    $html .= "<td" . ($isNum ? " class='num'" : "") . ">" . e($val) . "</td>";
                }
                $html .= "</tr>";
            }
        }

        $html .= "</tbody></table>";
        $html .= $footer . "</body></html>";

        renderPDF($html, $file);
    }

    // =====================================================
    // ✅ PRINT VIEW (IGUAL AL PDF)
    // =====================================================
    if ($action === "print_view") {

        $type = trim((string)($type ?: ($_GET["type"] ?? "consumo-ficha")));
        $auto = isset($_GET["auto"]) && $_GET["auto"] == "1";

        $includeCharts = strtolower(trim((string)($_GET["incluir_graficas"] ?? "yes"))) === "yes";

        $title = "Vista Imprimible";
        $subtitle = "Reporte generado para impresión";
        $rows = [];

        // ✅ PRIORIDAD (FIX GLOBAL) por si print_view viene directo con GET bodega/subbodega
        $filters["bodega"] = $_GET["bodega"] ?? ($filters["bodega"] ?? "all");
        $filters["subbodega"] = $_GET["subbodega"] ?? ($filters["subbodega"] ?? "all");
        applyLocationPriority($filters);

        if ($type === "consumo-ficha") {
            $title = "Reporte: Consumo por Ficha";
            $subtitle = "Detalle de consumo y costos por ficha";
            $rows = $model->getConsumoPorFicha($filters);
        }
        else if ($type === "consumo-programa") {
            $title = "Reporte: Consumo por Programa";
            $subtitle = "Consumo y costo agrupado por programa";
            $rows = $model->getConsumoPorProgramaDetalle($filters);
        }
        else if ($type === "consumo-rae") {
            $title = "Reporte: Consumo por RAE";
            $subtitle = "Consumo y costo agrupado por RAE";
            $rows = $model->getConsumoPorRAE($filters);
        }
        else if ($type === "movimientos") {
            $title = "Reporte: Movimientos";
            $subtitle = "Historial completo de movimientos";
            $rows = $model->getMovimientosDetalle($filters, 300);
        }
        else if ($type === "material-faltante") {
            $title = "Reporte: Material Faltante";
            $subtitle = "Stock bajo o agotado";
            $threshold = isset($_GET["threshold"]) ? (int)$_GET["threshold"] : 5;
            $rows = $model->getMaterialFaltante($filters, $threshold);
        }

        $totalConsumo = 0;
        $totalCosto   = 0;
        foreach ($rows as $r) {
            if (isset($r["consumo"])) $totalConsumo += (int)$r["consumo"];
            if (isset($r["costo"]))   $totalCosto   += (int)$r["costo"];
        }

        $header = buildPdfHeaderHtml($title, $subtitle, $filters);
        $footer = buildPdfFooterHtml(false);

        $html = "<html><head><meta charset='UTF-8'></head><body>";

        $html .= "
        <style>
          html, body { background:#fff !important; margin:0; padding:0; }
          .no-print { display:flex; gap:10px; margin: 0 0 10px 0; }
          .btn-print {
              padding: 8px 12px;
              border-radius: 10px;
              border: 1px solid #cbd5e1;
              background: #fff;
              cursor: pointer;
              font-size: 12px;
          }
          .hint {
              font-size: 12px;
              color: #475569;
              margin-left: 6px;
              align-self: center;
          }
          @media print {
            .no-print { display: none !important; }
          }
        </style>
        ";

        $html .= "
        <div class='no-print'>
            <button class='btn-print' onclick='window.print()'>Imprimir</button>
            <button class='btn-print' onclick='window.close()'>Cerrar</button>
            <span class='hint'>Si deseas papel físico, en <b>Destino</b> selecciona tu impresora (no “Guardar como PDF”).</span>
        </div>
        ";

        $html .= $header;

        if ($includeCharts && !empty($rows)) {
            if ($type === "consumo-ficha") {
                $html .= chartBlockHtml("Top Fichas por Consumo", chartFromRowsConsumoAgrupado($rows, "ficha", "consumo"));
            }
            else if ($type === "consumo-programa") {
                $html .= chartBlockHtml("Top Programas por Consumo", chartFromRowsConsumoAgrupado($rows, "programa", "consumo"));
            }
            else if ($type === "consumo-rae") {
                $html .= chartBlockHtml("Top RAEs por Consumo", chartFromRowsConsumoAgrupado($rows, "rae", "consumo"));
            }
            else if ($type === "movimientos") {
                $html .= chartBlockHtml("Distribución por Tipo de Movimiento", chartFromMovimientosTipo($rows));
            }
            else if ($type === "material-faltante") {
                $html .= chartBlockHtml("Stock vs Mínimo (Top)", chartFromStock($rows));
            }
        }

        $html .= "<table><thead><tr>";

        if (!empty($rows)) {
            foreach (array_keys($rows[0]) as $col) {
                $align = in_array($col, ["consumo","costo","cantidad","stock","minimo"], true) ? " class='num'" : "";
                $html .= "<th$align>" . e(ucfirst($col)) . "</th>";
            }
        } else {
            $html .= "<th>Resultado</th>";
        }

        $html .= "</tr></thead><tbody>";

        if (empty($rows)) {
            $html .= "<tr><td>No hay datos con los filtros seleccionados.</td></tr>";
        } else {
            foreach ($rows as $r) {
                $html .= "<tr>";
                foreach ($r as $k => $v) {
                    $isNum = in_array($k, ["consumo","costo","cantidad","stock","minimo"], true);
                    $val = ($k === "costo") ? formatCOP((int)$v) : $v;
                    $html .= "<td" . ($isNum ? " class='num'" : "") . ">" . e($val) . "</td>";
                }
                $html .= "</tr>";
            }
        }

        $html .= "</tbody>";

        if ($totalConsumo > 0 || $totalCosto > 0) {
            $html .= "
            <tfoot>
                <tr>
                    <td colspan='2'>Total</td>
                    <td class='num'>" . e($totalConsumo) . " uds</td>
                    <td class='num'>" . e(formatCOP($totalCosto)) . "</td>
                </tr>
            </tfoot>";
        }

        $html .= "</table>";
        $html .= $footer;

        $html .= "
        <script>
          window.addEventListener('load', function () {
            const params = new URLSearchParams(window.location.search);
            const auto = params.get('auto') === '1';

            let printingStarted = false;

            function closePopupSafe() {
              try { window.close(); } catch (e) {}
            }

            if (auto) {
              setTimeout(() => {
                printingStarted = true;
                window.focus();
                window.print();
              }, 350);
            }

            window.addEventListener('afterprint', function () {
              if (auto) closePopupSafe();
            });

            window.addEventListener('focus', function () {
              if (!auto) return;
              if (!printingStarted) return;

              setTimeout(() => {
                closePopupSafe();
              }, 250);
            });
          });
        </script>
        ";

        $html .= "</body></html>";

        renderPrintView($html);
    }

    // =====================================================
    // ✅ SUBBODEGAS (JSON)
    // action=get_subbodegas&id_bodega=ID
    // =====================================================
    if ($action === "get_subbodegas") {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");

        try {
            $idBodega = $_GET["id_bodega"] ?? "all";

            if ($idBodega === "all" || empty($idBodega)) {
                echo json_encode([
                    "ok" => true,
                    "items" => []
                ]);
                exit;
            }

            $items = $model->getSubBodegas($idBodega);

            echo json_encode([
                "ok" => true,
                "items" => $items
            ]);
            exit;

        } catch (Throwable $e) {
            echo json_encode([
                "ok" => false,
                "items" => [],
                "message" => $e->getMessage()
            ]);
            exit;
        }
    }

    // =====================================================
    // ✅ JSON endpoints
    // =====================================================
    if ($action === "programas") jsonResponse(true, "OK", $model->getProgramas());
    if ($action === "fichas") jsonResponse(true, "OK", $model->getFichas());
    if ($action === "bodegas") jsonResponse(true, "OK", $model->getBodegas());
    if ($action === "dashboard") jsonResponse(true, "OK", $model->getDashboardData($filters));

    jsonResponse(false, "Acción no válida", ["action" => $action, "get" => $_GET], 400);

} catch (Throwable $e) {
    jsonResponse(false, "Error interno: " . $e->getMessage(), null, 500);
}
