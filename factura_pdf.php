<?php
// Genera una factura como PDF real. Se abre desde factura.php.
$modulo_actual = "factura";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$idFactura = $_GET["id"] ?? "";
if (!$idFactura) { die("Falta el número de factura."); }

$stmt = $pdo->prepare("SELECT f.*, p.subtotal, p.num_mesa, p.tipo_ped
                        FROM factura f JOIN pedido p ON p.ID_Pedido = f.ID_Pedido
                        WHERE f.ID_Factura = ?");
$stmt->execute([$idFactura]);
$factura = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$factura) { die("Factura no encontrada."); }

$detStmt = $pdo->prepare("SELECT d.cantidad, d.precio, m.nombre
                           FROM pedido_detalle d JOIN menu m ON m.ID_Menu = d.ID_Menu
                           WHERE d.ID_Pedido = ?");
$detStmt->execute([$factura["ID_Pedido"]]);
$detalle = $detStmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Helvetica, Arial, sans-serif; color: #222; font-size: 13px; }
  .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #C0563A; padding-bottom: 12px; }
  .header h1 { margin: 0; font-size: 26px; color: #C0563A; letter-spacing: 1px; }
  .header p { margin: 2px 0; color: #666; }
  .header .folio { margin-top: 8px; font-weight: bold; font-size: 15px; color: #333; }
  table.info { width: 100%; margin-bottom: 16px; }
  table.info td { padding: 3px 0; vertical-align: top; font-size: 13px; }
  table.items { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
  table.items th, table.items td { border: 1px solid #ddd; padding: 7px 9px; text-align: left; font-size: 12px; }
  table.items th { background: #f7ead9; color: #7a5230; }
  table.totales { width: 280px; margin-left: auto; margin-bottom: 20px; }
  table.totales td { padding: 4px 0; font-size: 13px; }
  table.totales .total-final td { font-weight: bold; font-size: 16px; border-top: 2px solid #333; padding-top: 8px; }
  .footer { text-align: center; margin-top: 40px; color: #888; font-size: 12px; }
</style>
</head>
<body>
  <div class="header">
    <h1>CASA LATINA</h1>
    <p>Comida típica hondureña — Tel: 0000-0000</p>
    <p class="folio">FACTURA <?php echo htmlspecialchars($factura["ID_Factura"]); ?></p>
  </div>

  <table class="info">
    <tr>
      <td width="50%"><strong>Cliente:</strong> <?php echo htmlspecialchars($factura["nombre_cliente"]); ?></td>
      <td width="50%"><strong>Fecha:</strong> <?php echo htmlspecialchars($factura["fecha_fac"]); ?></td>
    </tr>
    <tr>
      <td><strong>Pedido:</strong> <?php echo htmlspecialchars($factura["ID_Pedido"]); ?>
        <?php echo $factura["num_mesa"] ? " (Mesa " . htmlspecialchars($factura["num_mesa"]) . ")" : " (" . htmlspecialchars($factura["tipo_ped"]) . ")"; ?></td>
      <td><strong>Atendido por:</strong> <?php echo htmlspecialchars($factura["cod_empleado"]); ?></td>
    </tr>
  </table>

  <table class="items">
    <tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr>
    <?php foreach ($detalle as $d): ?>
    <tr>
      <td><?php echo htmlspecialchars($d["nombre"]); ?></td>
      <td><?php echo htmlspecialchars($d["cantidad"]); ?></td>
      <td>L. <?php echo number_format((float) $d["precio"], 2); ?></td>
      <td>L. <?php echo number_format((float) $d["precio"] * $d["cantidad"], 2); ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <table class="totales">
    <tr><td>Subtotal</td><td align="right">L. <?php echo number_format((float) $factura["subtotal"], 2); ?></td></tr>
    <tr><td>Impuesto (15%)</td><td align="right">L. <?php echo number_format((float) $factura["impuesto"], 2); ?></td></tr>
    <tr class="total-final"><td>TOTAL</td><td align="right">L. <?php echo number_format((float) $factura["total"], 2); ?></td></tr>
  </table>

  <p class="footer">¡Gracias por su preferencia!</p>
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
$dompdf->stream("factura_" . $idFactura . ".pdf", ["Attachment" => false]);