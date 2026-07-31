<?php
// Desglose de "cuánto paga cada quien" cuando se divide la cuenta.
// El subtotal siempre se recalcula server-side (nunca se confía en el total del navegador).
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$idPedido = $_GET["id"] ?? "";
$personas = max(1, (int) ($_GET["personas"] ?? 1));
$descuentoPct = max(0, min(100, (float) ($_GET["descuento_pct"] ?? 0)));
if (!$idPedido) { die("Falta el número de pedido."); }

$pedidoStmt = $pdo->prepare("SELECT * FROM pedido WHERE ID_Pedido = ?");
$pedidoStmt->execute([$idPedido]);
$pedido = $pedidoStmt->fetch(PDO::FETCH_ASSOC);
if (!$pedido) { die("Pedido no encontrado."); }

$subtotal = (float) $pedido["subtotal"];
$descuentoMonto = round($subtotal * ($descuentoPct / 100), 2);
$subtotalConDescuento = $subtotal - $descuentoMonto;
$impuesto = round($subtotalConDescuento * 0.15, 2);
$total = round($subtotalConDescuento + $impuesto, 2);

$porPersona = floor(($total / $personas) * 100) / 100;
$diferencia = round($total - ($porPersona * $personas), 2); // la absorbe la Persona 1, para que la suma cuadre exacto

ob_start();
?>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Helvetica, Arial, sans-serif; color: #222; font-size: 14px; }
  .header { text-align: center; margin-bottom: 18px; border-bottom: 3px solid #C0563A; padding-bottom: 10px; }
  .header h1 { margin: 0; font-size: 22px; color: #C0563A; }
  .header p { margin: 3px 0; color: #666; }
  table { width: 100%; border-collapse: collapse; margin-top: 14px; }
  th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
  th { background: #f7ead9; color: #7a5230; }
  .resumen { margin-top: 10px; font-size: 13px; color: #555; }
</style>
</head>
<body>
  <div class="header">
    <h1>CASA LATINA</h1>
    <p>División de cuenta — Pedido <?php echo htmlspecialchars($idPedido); ?></p>
  </div>

  <div class="resumen">
    <p>Total a pagar: <strong>L. <?php echo number_format($total, 2); ?></strong>
       <?php if ($descuentoMonto > 0): ?>(incluye descuento de <?php echo $descuentoPct; ?>%)<?php endif; ?>
       — dividido entre <?php echo $personas; ?> persona(s)</p>
  </div>

  <table>
  <tr><th>Persona</th><th>Monto a pagar</th></tr>
  <?php for ($i = 1; $i <= $personas; $i++): ?>
  <tr>
    <td>Persona <?php echo $i; ?></td>
    <td>L. <?php echo number_format($porPersona + ($i === 1 ? $diferencia : 0), 2); ?></td>
  </tr>
  <?php endfor; ?>
  </table>
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
$dompdf->stream("division_" . $idPedido . ".pdf", ["Attachment" => false]);