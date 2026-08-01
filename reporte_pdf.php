<?php
// Genera el PDF del reporte elegido en reportes.php
$modulo_actual = "reporte";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$tipo = $_GET["tipo"] ?? "";
$desde = $_GET["fecha_inicio"] ?? "";
$hasta = $_GET["fecha_final"] ?? "";

if (!$tipo || !$desde || !$hasta) { die("Faltan datos para generar el reporte."); }

$titulos = [
    "ventas"      => "Reporte de Ventas",
    "gastos"      => "Reporte de Gastos",
    "compras"     => "Reporte de Compras",
    "resumen"     => "Resumen Financiero",
    "top_platos"  => "Platos Más Vendidos",
    "metodo_pago" => "Cierre de Caja — Efectivo vs. Tarjeta",
];
$tituloReporte = $titulos[$tipo] ?? "Reporte";

// ---------- Datos según el tipo de reporte ----------
$filas = [];
$totalGeneral = 0;

if ($tipo === "ventas") {
    $stmt = $pdo->prepare("SELECT * FROM factura WHERE fecha_fac BETWEEN ? AND ? ORDER BY fecha_fac");
    $stmt->execute([$desde, $hasta]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($filas as $f) { $totalGeneral += (float) $f["total"]; }
}

if ($tipo === "gastos") {
    $stmt = $pdo->prepare("SELECT gd.*, g.nombre AS categoria, g.tipo
                            FROM gastos_detalles gd JOIN gastos g ON g.ID_gastos = gd.ID_gastos
                            WHERE gd.fecha BETWEEN ? AND ? ORDER BY gd.fecha");
    $stmt->execute([$desde, $hasta]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($filas as $f) { $totalGeneral += (float) $f["monto"]; }
}

if ($tipo === "compras") {
    $stmt = $pdo->prepare("SELECT c.*, p.nombre_pro FROM compras c
                            LEFT JOIN producto p ON p.ID_Producto = c.ID_Producto
                            WHERE c.fecha BETWEEN ? AND ? ORDER BY c.fecha");
    $stmt->execute([$desde, $hasta]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($filas as $f) { $totalGeneral += (float) $f["monto_total"]; }
}

if ($tipo === "resumen") {
    $stmtV = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS t FROM factura WHERE fecha_fac BETWEEN ? AND ?");
    $stmtV->execute([$desde, $hasta]);
    $ventas = (float) $stmtV->fetch()["t"];

    $stmtG = $pdo->prepare("SELECT COALESCE(SUM(monto),0) AS t FROM gastos_detalles WHERE fecha BETWEEN ? AND ?");
    $stmtG->execute([$desde, $hasta]);
    $gastos = (float) $stmtG->fetch()["t"];

    $stmtC = $pdo->prepare("SELECT COALESCE(SUM(monto_total),0) AS t FROM compras WHERE fecha BETWEEN ? AND ?");
    $stmtC->execute([$desde, $hasta]);
    $compras = (float) $stmtC->fetch()["t"];

    $utilidad = $ventas - $gastos - $compras;
}

if ($tipo === "top_platos") {
    $stmt = $pdo->prepare("SELECT m.nombre, SUM(d.cantidad) AS cantidad_total, SUM(d.cantidad * d.precio) AS ingreso_total
                            FROM pedido_detalle d
                            JOIN menu m ON m.ID_Menu = d.ID_Menu
                            JOIN pedido p ON p.ID_Pedido = d.ID_Pedido
                            WHERE p.fecha BETWEEN ? AND ? AND p.estado = 'Pagado'
                            GROUP BY m.ID_Menu
                            ORDER BY cantidad_total DESC");
    $stmt->execute([$desde, $hasta]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($filas as $f) { $totalGeneral += (float) $f["ingreso_total"]; }
}

if ($tipo === "metodo_pago") {
    $stmt = $pdo->prepare("SELECT f.ID_Factura, f.fecha_fac, f.nombre_cliente, f.total, p.metodo_pago
                            FROM factura f JOIN pedido p ON p.ID_Pedido = f.ID_Pedido
                            WHERE f.fecha_fac BETWEEN ? AND ?
                            ORDER BY p.metodo_pago, f.fecha_fac");
    $stmt->execute([$desde, $hasta]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalEfectivo = 0; $totalTarjeta = 0; $countEfectivo = 0; $countTarjeta = 0;
    foreach ($filas as $f) {
        if (($f["metodo_pago"] ?? "Efectivo") === "Tarjeta") {
            $totalTarjeta += (float) $f["total"];
            $countTarjeta++;
        } else {
            $totalEfectivo += (float) $f["total"];
            $countEfectivo++;
        }
    }
    $totalGeneral = $totalEfectivo + $totalTarjeta;
}

// ---------- HTML del reporte ----------
ob_start();
?>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Helvetica, Arial, sans-serif; color: #222; font-size: 12px; }
  .header { text-align: center; margin-bottom: 18px; border-bottom: 3px solid #C0563A; padding-bottom: 10px; }
  .header h1 { margin: 0; font-size: 22px; color: #C0563A; }
  .header p { margin: 2px 0; color: #666; }
  .header .titulo-reporte { margin-top: 8px; font-weight: bold; font-size: 16px; color: #333; }
  .rango { text-align:center; color:#666; margin-bottom:18px; }
  table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  table.items th, table.items td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 11px; }
  table.items th { background: #f7ead9; color: #7a5230; }
  .resumen-tabla { width: 320px; margin: 0 auto; }
  .resumen-tabla td { padding: 6px 4px; font-size: 13px; }
  .resumen-tabla .utilidad td { font-weight: bold; font-size: 16px; border-top: 2px solid #333; padding-top: 10px; }
  .total-final { text-align: right; font-weight: bold; font-size: 14px; margin-top: 10px; }
  .footer { text-align: center; margin-top: 30px; color: #888; font-size: 11px; }
</style>
</head>
<body>
  <div class="header">
    <h1>CASA LATINA</h1>
    <p>Comida típica hondureña — Tel: 0000-0000</p>
    <p class="titulo-reporte"><?php echo htmlspecialchars($tituloReporte); ?></p>
  </div>
  <p class="rango">Del <?php echo htmlspecialchars($desde); ?> al <?php echo htmlspecialchars($hasta); ?></p>

  <?php if ($tipo === "ventas"): ?>
    <table class="items">
    <tr><th>ID Factura</th><th>Cliente</th><th>Pedido</th><th>Fecha</th><th>Total</th></tr>
    <?php if (count($filas) === 0): ?><tr><td colspan="5">No hay ventas en este período.</td></tr><?php endif; ?>
    <?php foreach ($filas as $f): ?>
    <tr>
        <td><?php echo htmlspecialchars($f["ID_Factura"]); ?></td>
        <td><?php echo htmlspecialchars($f["nombre_cliente"]); ?></td>
        <td><?php echo htmlspecialchars($f["ID_Pedido"]); ?></td>
        <td><?php echo htmlspecialchars($f["fecha_fac"]); ?></td>
        <td>L. <?php echo number_format((float) $f["total"], 2); ?></td>
    </tr>
    <?php endforeach; ?>
    </table>
    <p class="total-final">Total de ventas: L. <?php echo number_format($totalGeneral, 2); ?> (<?php echo count($filas); ?> factura(s))</p>

  <?php elseif ($tipo === "gastos"): ?>
    <table class="items">
    <tr><th>Fecha</th><th>Categoría</th><th>Tipo</th><th>Monto</th></tr>
    <?php if (count($filas) === 0): ?><tr><td colspan="4">No hay gastos en este período.</td></tr><?php endif; ?>
    <?php foreach ($filas as $f): ?>
    <tr>
        <td><?php echo htmlspecialchars($f["fecha"]); ?></td>
        <td><?php echo htmlspecialchars($f["categoria"]); ?></td>
        <td><?php echo htmlspecialchars($f["tipo"]); ?></td>
        <td>L. <?php echo number_format((float) $f["monto"], 2); ?></td>
    </tr>
    <?php endforeach; ?>
    </table>
    <p class="total-final">Total de gastos: L. <?php echo number_format($totalGeneral, 2); ?></p>

  <?php elseif ($tipo === "compras"): ?>
    <table class="items">
    <tr><th>Fecha</th><th>Producto</th><th>Cantidad</th><th>Monto</th></tr>
    <?php if (count($filas) === 0): ?><tr><td colspan="4">No hay compras en este período.</td></tr><?php endif; ?>
    <?php foreach ($filas as $f): ?>
    <tr>
        <td><?php echo htmlspecialchars($f["fecha"]); ?></td>
        <td><?php echo htmlspecialchars($f["nombre_pro"] ?? $f["ID_Producto"]); ?></td>
        <td><?php echo htmlspecialchars($f["cantidad"]); ?></td>
        <td>L. <?php echo number_format((float) $f["monto_total"], 2); ?></td>
    </tr>
    <?php endforeach; ?>
    </table>
    <p class="total-final">Total en compras: L. <?php echo number_format($totalGeneral, 2); ?></p>

  <?php elseif ($tipo === "resumen"): ?>
    <table class="resumen-tabla">
        <tr><td>Ventas (facturación)</td><td align="right">L. <?php echo number_format($ventas, 2); ?></td></tr>
        <tr><td>Gastos</td><td align="right">− L. <?php echo number_format($gastos, 2); ?></td></tr>
        <tr><td>Compras a proveedores</td><td align="right">− L. <?php echo number_format($compras, 2); ?></td></tr>
        <tr class="utilidad"><td>Utilidad neta del período</td><td align="right">L. <?php echo number_format($utilidad, 2); ?></td></tr>
    </table>

  <?php elseif ($tipo === "top_platos"): ?>
    <table class="items">
    <tr><th>#</th><th>Plato</th><th>Cantidad vendida</th><th>Ingreso generado</th></tr>
    <?php if (count($filas) === 0): ?><tr><td colspan="4">No hay pedidos pagados en este período.</td></tr><?php endif; ?>
    <?php foreach ($filas as $i => $f): ?>
    <tr>
        <td><?php echo $i + 1; ?></td>
        <td><?php echo htmlspecialchars($f["nombre"]); ?></td>
        <td><?php echo htmlspecialchars($f["cantidad_total"]); ?></td>
        <td>L. <?php echo number_format((float) $f["ingreso_total"], 2); ?></td>
    </tr>
    <?php endforeach; ?>
    </table>
    <p class="total-final">Ingreso total generado por estos platos: L. <?php echo number_format($totalGeneral, 2); ?></p>

  <?php elseif ($tipo === "metodo_pago"): ?>
    <table class="resumen-tabla" style="width:360px;">
        <tr><td>💵 Efectivo</td><td align="right">L. <?php echo number_format($totalEfectivo, 2); ?></td></tr>
        <tr><td colspan="2" style="color:#888; font-size:11px; padding-top:0;"><?php echo $countEfectivo; ?> factura(s)</td></tr>
        <tr><td>💳 Tarjeta</td><td align="right">L. <?php echo number_format($totalTarjeta, 2); ?></td></tr>
        <tr><td colspan="2" style="color:#888; font-size:11px; padding-top:0;"><?php echo $countTarjeta; ?> factura(s)</td></tr>
        <tr class="utilidad"><td>Total ventas</td><td align="right">L. <?php echo number_format($totalGeneral, 2); ?></td></tr>
    </table>
    <p style="text-align:center; color:#666; margin-top:10px;">El efectivo esperado en caja al cierre es <strong>L. <?php echo number_format($totalEfectivo, 2); ?></strong> (sin contar el fondo inicial de caja).</p>

    <table class="items" style="margin-top:18px;">
    <tr><th>ID Factura</th><th>Fecha</th><th>Cliente</th><th>Método</th><th>Total</th></tr>
    <?php if (count($filas) === 0): ?><tr><td colspan="5">No hay ventas en este período.</td></tr><?php endif; ?>
    <?php foreach ($filas as $f): ?>
    <tr>
        <td><?php echo htmlspecialchars($f["ID_Factura"]); ?></td>
        <td><?php echo htmlspecialchars($f["fecha_fac"]); ?></td>
        <td><?php echo htmlspecialchars($f["nombre_cliente"]); ?></td>
        <td><?php echo htmlspecialchars($f["metodo_pago"] ?? "Efectivo"); ?></td>
        <td>L. <?php echo number_format((float) $f["total"], 2); ?></td>
    </tr>
    <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <p class="footer">Generado el <?php echo date("Y-m-d H:i"); ?></p>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream("reporte_" . $tipo . "_" . $desde . "_" . $hasta . ".pdf", ["Attachment" => false]);