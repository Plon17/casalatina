<?php
// Ticket de cocina: solo cantidades y nombres, sin precios.
// ?id=ID_Pedido            -> comanda completa, agrupada por ronda
// ?id=ID_Pedido&lote=N     -> solo esa ronda (para reenvíos a media comida)
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$idPedido = $_GET["id"] ?? "";
$lote = isset($_GET["lote"]) ? (int) $_GET["lote"] : null;
if (!$idPedido) { die("Falta el número de pedido."); }

$pedidoStmt = $pdo->prepare("SELECT * FROM pedido WHERE ID_Pedido = ?");
$pedidoStmt->execute([$idPedido]);
$pedido = $pedidoStmt->fetch(PDO::FETCH_ASSOC);
if (!$pedido) { die("Pedido no encontrado."); }

if ($lote !== null) {
    $detStmt = $pdo->prepare("SELECT d.cantidad, m.nombre, d.lote FROM pedido_detalle d
                               JOIN menu m ON m.ID_Menu = d.ID_Menu
                               WHERE d.ID_Pedido = ? AND d.lote = ?
                               ORDER BY m.nombre");
    $detStmt->execute([$idPedido, $lote]);
} else {
    $detStmt = $pdo->prepare("SELECT d.cantidad, m.nombre, d.lote FROM pedido_detalle d
                               JOIN menu m ON m.ID_Menu = d.ID_Menu
                               WHERE d.ID_Pedido = ?
                               ORDER BY d.lote, m.nombre");
    $detStmt->execute([$idPedido]);
}
$detalle = $detStmt->fetchAll(PDO::FETCH_ASSOC);

$porLote = [];
foreach ($detalle as $d) { $porLote[$d["lote"]][] = $d; }
ksort($porLote);

ob_start();
?>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Helvetica, Arial, sans-serif; color: #111; font-size: 16px; }
  .header { text-align: center; margin-bottom: 16px; border-bottom: 3px solid #333; padding-bottom: 10px; }
  .header h1 { margin: 0; font-size: 24px; letter-spacing: 1px; }
  .header p { margin: 3px 0; font-size: 14px; color: #555; }
  .info { margin-bottom: 10px; font-size: 15px; }
  .info strong { display:inline-block; width: 110px; }
  table.items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  table.items th, table.items td { border: 1px solid #999; padding: 9px; text-align: left; font-size: 17px; }
  table.items th { background: #eee; }
  table.items td.cant { text-align:center; font-weight:bold; width: 60px; }
  .ronda-label { background:#333; color:#fff; padding:4px 12px; font-weight:bold; font-size:13px; display:inline-block; margin: 14px 0 6px 0; border-radius:3px; }
</style>
</head>
<body>
  <div class="header">
    <h1>COMANDA DE COCINA</h1>
    <p>Pedido <?php echo htmlspecialchars($idPedido); ?><?php echo $lote !== null ? " — Ronda $lote" : ""; ?></p>
  </div>

  <div class="info">
    <div><strong>Mesa/Tipo:</strong> <?php echo htmlspecialchars($pedido["tipo_ped"]); ?><?php echo $pedido["num_mesa"] ? " — Mesa " . htmlspecialchars($pedido["num_mesa"]) : ""; ?></div>
    <div><strong>Hora:</strong> <?php echo date("H:i"); ?></div>
  </div>

  <?php foreach ($porLote as $numLote => $items): ?>
    <?php if ($lote === null): ?><div class="ronda-label">RONDA <?php echo $numLote; ?></div><?php endif; ?>
    <table class="items">
    <?php foreach ($items as $d): ?>
    <tr><td class="cant"><?php echo htmlspecialchars($d["cantidad"]); ?>x</td><td><?php echo htmlspecialchars($d["nombre"]); ?></td></tr>
    <?php endforeach; ?>
    </table>
  <?php endforeach; ?>
  <?php if (count($detalle) === 0): ?><p>No hay productos que mostrar.</p><?php endif; ?>

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
$dompdf->stream("comanda_" . $idPedido . ($lote !== null ? "_ronda$lote" : "") . ".pdf", ["Attachment" => false]);