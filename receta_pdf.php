<?php
// Genera la ficha de receta de un plato como PDF: datos del plato, insumos de
// stock que usa (con cantidad) y la receta de cocina en texto libre.
$modulo_actual = "menu";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/includes/tema.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$idMenu = $_GET["id"] ?? "";
if (!$idMenu) { die("Falta el ID del plato."); }

$stmt = $pdo->prepare("SELECT * FROM menu WHERE ID_Menu = ?");
$stmt->execute([$idMenu]);
$plato = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$plato) { die("Plato no encontrado."); }

$stmtIns = $pdo->prepare("SELECT mi.cantidad_necesaria, p.nombre_pro
                           FROM menu_ingredientes mi JOIN producto p ON p.ID_Producto = mi.ID_Producto
                           WHERE mi.ID_Menu = ? ORDER BY p.nombre_pro");
$stmtIns->execute([$idMenu]);
$insumos = $stmtIns->fetchAll(PDO::FETCH_ASSOC);

$stmtReceta = $pdo->prepare("SELECT * FROM menu_receta WHERE ID_Menu = ?");
$stmtReceta->execute([$idMenu]);
$receta = $stmtReceta->fetch(PDO::FETCH_ASSOC);

ob_start();
?>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Helvetica, Arial, sans-serif; color: #222; font-size: 13px; }
  .header { text-align: center; margin-bottom: 18px; border-bottom: 3px solid <?php echo COLOR_PRIMARIO; ?>; padding-bottom: 12px; }
  .header h1 { margin: 0; font-size: 22px; color: <?php echo COLOR_PRIMARIO; ?>; }
  .header p { margin: 2px 0; color: #666; }
  .plato-nombre { text-align: center; font-size: 20px; font-weight: bold; margin: 10px 0 2px 0; }
  .plato-meta { text-align: center; color: #666; margin-bottom: 18px; }
  h3 { color: <?php echo COLOR_PRIMARIO_OSCURO; ?>; border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-top: 22px; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
  table.items th, table.items td { border: 1px solid #ddd; padding: 6px 9px; text-align: left; font-size: 12px; }
  table.items th { background: <?php echo COLOR_PRIMARIO_CLARO; ?>; color: <?php echo COLOR_PRIMARIO_OSCURO; ?>; }
  .texto-libre { white-space: pre-wrap; font-size: 13px; line-height: 1.5; }
  .vacio { color: #999; font-style: italic; }
</style>
</head>
<body>
  <div class="header">
    <h1>CASA LATINA</h1>
    <p>Ficha de Receta</p>
  </div>

  <p class="plato-nombre"><?php echo htmlspecialchars($plato["nombre"]); ?></p>
  <p class="plato-meta">
    <?php echo htmlspecialchars($plato["tipo"]); ?> — L. <?php echo number_format((float) $plato["precio"], 2); ?>
    <?php if ($receta && ($receta["tiempo_preparacion"] || $receta["porciones"])): ?>
      <?php echo " · " . htmlspecialchars(trim(($receta["tiempo_preparacion"] ?? "") . " " . ($receta["porciones"] ? "· " . $receta["porciones"] : ""))); ?>
    <?php endif; ?>
  </p>
  <?php if ($plato["descripcion_men"]): ?>
  <p style="text-align:center; color:#555;"><?php echo htmlspecialchars($plato["descripcion_men"]); ?></p>
  <?php endif; ?>

  <h3>Ingredientes</h3>
  <?php if (count($insumos) === 0): ?>
    <p class="vacio">Este plato no tiene ingredientes definidos todavía.</p>
  <?php else: ?>
    <table class="items">
    <tr><th>Ingrediente</th><th>Cantidad</th></tr>
    <?php foreach ($insumos as $i): ?>
    <tr><td><?php echo htmlspecialchars($i["nombre_pro"]); ?></td><td><?php echo htmlspecialchars($i["cantidad_necesaria"]); ?></td></tr>
    <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <h3>Preparación</h3>
  <?php if ($receta && $receta["preparacion"]): ?>
    <p class="texto-libre"><?php echo htmlspecialchars($receta["preparacion"]); ?></p>
  <?php else: ?>
    <p class="vacio">Todavía no se ha definido esta parte de la receta.</p>
  <?php endif; ?>

  <?php if ($receta && $receta["notas"]): ?>
  <h3>Notas</h3>
  <p class="texto-libre"><?php echo htmlspecialchars($receta["notas"]); ?></p>
  <?php endif; ?>

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
$dompdf->stream("receta_" . $idMenu . ".pdf", ["Attachment" => false]);