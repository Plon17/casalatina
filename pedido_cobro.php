<?php
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$idPedido = $_GET["id"] ?? $_POST["id_pedido"] ?? "";
$error = "";
$mensaje = "";

if (!$idPedido) {
    header("Location: pedido_paso1.php");
    exit;
}

// Cobrar: solo permitido si el pedido está en estado "EnCocina"
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "cobrar") {
    $stmt = $pdo->prepare("UPDATE pedido SET monto_recibido=?, cambio=?, cod_empleado=?, estado='Pagado'
                            WHERE ID_Pedido=? AND estado='EnCocina'");
    $stmt->execute([$_POST["monto_recibido"], $_POST["cambio"], $_POST["cajero"], $idPedido]);

    if ($stmt->rowCount() === 0) {
        $error = "Este pedido ya no está disponible para cobro (puede que ya haya sido cobrado o cancelado).";
    } else {
        $mensaje = "Pedido #" . htmlspecialchars($idPedido) . " cobrado correctamente.";
    }
}

// Cancelar desde esta pantalla también
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "cancelar_pedido") {
    $pdo->prepare("UPDATE pedido SET estado='Cancelado' WHERE ID_Pedido=?")->execute([$idPedido]);
    header("Location: pedido_paso1.php");
    exit;
}

$pedidoStmt = $pdo->prepare("SELECT * FROM pedido WHERE ID_Pedido = ?");
$pedidoStmt->execute([$idPedido]);
$pedido = $pedidoStmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    header("Location: pedido_paso1.php");
    exit;
}

// Volver a leer el estado actualizado tras el cobro
if ($mensaje) {
    $pedidoStmt->execute([$idPedido]);
    $pedido = $pedidoStmt->fetch(PDO::FETCH_ASSOC);
}

$detalleStmt = $pdo->prepare("SELECT d.ID_Menu, d.cantidad, d.precio, m.nombre
                               FROM pedido_detalle d
                               JOIN menu m ON m.ID_Menu = d.ID_Menu
                               WHERE d.ID_Pedido = ?");
$detalleStmt->execute([$idPedido]);
$detalle = $detalleStmt->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "PEDIDO - PASO 3: COBRO";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="titulo-modulo">Paso 3 de 3 — Cobro</p>
<p>Pedido <strong><?php echo htmlspecialchars($idPedido); ?></strong> —
   Mesa: <?php echo htmlspecialchars($pedido["num_mesa"] ?: "N/A"); ?> —
   Estado: <?php echo htmlspecialchars($pedido["estado"]); ?></p>

<?php if (isset($_GET["enviado"])): ?><p class="mensaje-ok">Pedido enviado a cocina correctamente.</p><?php endif; ?>
<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo $mensaje; ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<h3>Detalle del pedido</h3>
<div class="caja-blanca">
<table>
<tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th></tr>
<?php foreach ($detalle as $d): ?>
<tr>
    <td><?php echo htmlspecialchars($d["ID_Menu"]); ?></td>
    <td><?php echo htmlspecialchars($d["nombre"]); ?></td>
    <td><?php echo htmlspecialchars($d["precio"]); ?></td>
    <td><?php echo htmlspecialchars($d["cantidad"]); ?></td>
    <td><?php echo number_format($d["precio"] * $d["cantidad"], 2); ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<?php if ($pedido["estado"] === "EnCocina"): ?>

<form method="POST" onsubmit="return calcularCambioValido();">
    <input type="hidden" name="accion" value="cobrar">
    <input type="hidden" name="id_pedido" value="<?php echo htmlspecialchars($idPedido); ?>">

    <div class="fila"><label>Sub Total:</label><input type="text" value="<?php echo htmlspecialchars($pedido['subtotal']); ?>" readonly></div>
    <div class="fila"><label>Impuesto (15%):</label><input type="text" value="<?php echo htmlspecialchars($pedido['impuesto']); ?>" readonly></div>
    <div class="fila"><label>Total:</label><input type="text" id="total" value="<?php echo htmlspecialchars($pedido['total']); ?>" readonly></div>
    <div class="fila"><label>Cajero:</label><input type="text" name="cajero" value="<?php echo htmlspecialchars($_SESSION['cod_empleado'] ?? ''); ?>"></div>
    <div class="fila"><label>Monto Recibido:</label><input type="number" step="0.01" id="monto_recibido" name="monto_recibido" onkeyup="calcularCambio()" required></div>
    <div class="fila"><label>Cambio:</label><input type="text" id="cambio" name="cambio" readonly></div>

    <br>
    <button type="submit">Cobrar</button>
</form>

<form method="POST" onsubmit="return confirm('¿Seguro que deseas cancelar este pedido?');">
    <input type="hidden" name="accion" value="cancelar_pedido">
    <button type="submit">CANCELAR PEDIDO</button>
</form>

<script>
function calcularCambio() {
    const total = parseFloat(document.getElementById("total").value) || 0;
    const recibido = parseFloat(document.getElementById("monto_recibido").value) || 0;
    document.getElementById("cambio").value = (recibido - total).toFixed(2);
}
function calcularCambioValido() {
    calcularCambio();
    if (parseFloat(document.getElementById("cambio").value) < 0) {
        alert("El monto recibido es menor al total.");
        return false;
    }
    return true;
}
</script>

<?php elseif ($pedido["estado"] === "Pagado"): ?>

<p>Este pedido ya fue cobrado. Monto recibido: <?php echo htmlspecialchars($pedido["monto_recibido"]); ?>,
   Cambio: <?php echo htmlspecialchars($pedido["cambio"]); ?></p>
<button type="button" onclick="window.print()">Imprimir recibo</button>

<?php else: ?>

<p>Este pedido todavía no ha sido enviado a cocina.</p>

<?php endif; ?>

<p><a href="pedido_paso1.php">+ Nuevo pedido</a></p>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>