<?php
// Genera el recibo de un pedido como PDF real (tamaño carta completo, sin
// elementos del navegador). Se abre desde el botón "Imprimir recibo" en
// pedido_cobro.php.
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$idPedido = $_GET["id"] ?? "";
if (!$idPedido) { die("Falta el número de pedido."); }

$pedidoStmt = $pdo->prepare("SELECT * FROM pedido WHERE ID_Pedido = ?");
$pedidoStmt->execute([$idPedido]);
$pedido = $pedidoStmt->fetch(PDO::FETCH_ASSOC);
if (!$pedido) { die("Pedido no encontrado."); }

$detStmt = $pdo->prepare("SELECT d.cantidad, d.precio, m.nombre
                           FROM pedido_detalle d JOIN menu m ON m.ID_Menu = d.ID_Menu
                           WHERE d.ID_Pedido = ?");
$detStmt->execute([$idPedido]);
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
    <p>RECIBO</p>
  </div>

  <table class="info">
    <tr>
      <td width="50%"><strong>Pedido:</strong> <?php echo htmlspecialchars($idPedido); ?></td>
      <td width="50%"><strong>Fecha:</strong> <?php echo htmlspecialchars($pedido["fecha"]); ?></td>
    </tr>
    <tr>
      <td><strong>Tipo:</strong> <?php echo htmlspecialchars($pedido["tipo_ped"]); ?><?php echo $pedido["num_mesa"] ? " (Mesa " . htmlspecialchars($pedido["num_mesa"]) . ")" : ""; ?></td>
      <td><strong>Cajero:</strong> <?php echo htmlspecialchars($pedido["cod_empleado"] ?? ""); ?></td>
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
    <tr><td>Subtotal</td><td align="right">L. <?php echo number_format((float) $pedido["subtotal"], 2); ?></td></tr>
    <tr><td>Impuesto (15%)</td><td align="right">L. <?php echo number_format((float) $pedido["impuesto"], 2); ?></td></tr>
    <tr class="total-final"><td>TOTAL</td><td align="right">L. <?php echo number_format((float) $pedido["total"], 2); ?></td></tr>
    <tr><td>Recibido</td><td align="right">L. <?php echo number_format((float) ($pedido["monto_recibido"] ?? 0), 2); ?></td></tr>
    <tr><td>Cambio</td><td align="right">L. <?php echo number_format((float) ($pedido["cambio"] ?? 0), 2); ?></td></tr>
  </table>

  <p class="footer">¡Gracias por su visita!</p>
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
$dompdf->stream("recibo_" . $idPedido . ".pdf", ["Attachment" => false]);