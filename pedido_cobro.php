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
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE pedido SET monto_recibido=?, cambio=?, cod_empleado=?, estado='Pagado'
                                WHERE ID_Pedido=? AND estado='EnCocina'");
        $stmt->execute([$_POST["monto_recibido"], $_POST["cambio"], $_POST["cajero"], $idPedido]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            $error = "Este pedido ya no está disponible para cobro (puede que ya haya sido cobrado o cancelado).";
        } else {
            // Generamos la factura automáticamente, para no depender de que alguien
            // la escriba a mano después (y para que nunca falte ni se duplique).
            $pedidoActual = $pdo->prepare("SELECT * FROM pedido WHERE ID_Pedido = ?");
            $pedidoActual->execute([$idPedido]);
            $pedidoActual = $pedidoActual->fetch(PDO::FETCH_ASSOC);

            $n = (int) $pdo->query("SELECT COUNT(*) AS c FROM factura")->fetch()["c"] + 1;
            $idFactura = "F" . str_pad($n, 6, "0", STR_PAD_LEFT);
            $nombreCliente = trim($_POST["nombre_cliente"] ?? "") ?: "Consumidor Final";

            $stmtFac = $pdo->prepare("INSERT INTO factura (ID_Factura, nombre_cliente, cod_empleado, ID_Pedido, fecha_fac, impuesto, total)
                                       VALUES (?,?,?,?,?,?,?)");
            $stmtFac->execute([
                $idFactura, $nombreCliente, $_POST["cajero"], $idPedido,
                date("Y-m-d"), $pedidoActual["impuesto"], $pedidoActual["total"]
            ]);

            $pdo->commit();
            $mensaje = "Pedido #" . htmlspecialchars($idPedido) . " cobrado correctamente. Factura $idFactura generada.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al cobrar el pedido: " . $e->getMessage();
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

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:14px;}
.pd-tabla th{background:#f5f5f5;}
.pd-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:120px;}
.pd-actions{margin-top:16px;display:flex;gap:10px;}

/* Recibo: oculto en pantalla, solo aparece al imprimir */
.recibo-print{display:none;}

@media print {
    body * { visibility: hidden; }
    .recibo-print, .recibo-print * { visibility: visible; }
    .recibo-print {
        display: block;
        position: absolute;
        top: 0; left: 0;
        width: 300px;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        color: #000;
    }
    .recibo-print .recibo-linea{ border-top: 1px dashed #000; margin: 6px 0; }
    .recibo-print .recibo-centro{ text-align: center; }
    .recibo-print .recibo-fila{ display: flex; justify-content: space-between; }
    .recibo-print table{ width: 100%; border-collapse: collapse; margin: 4px 0; }
    .recibo-print td{ padding: 1px 0; vertical-align: top; }
    .recibo-print .recibo-total{ font-weight: bold; font-size: 13px; }
    @page { margin: 8mm; }
}
</style>

<!-- Recibo imprimible: solo visible con Ctrl+P / botón Imprimir (ver CSS @media print arriba) -->
<div class="recibo-print">
    <p class="recibo-centro" style="font-weight:bold; font-size:15px; margin:0;">CASA LATINA</p>
    <p class="recibo-centro" style="margin:2px 0;">Comida típica hondureña</p>
    <p class="recibo-centro" style="margin:0;">Tel: 0000-0000</p>
    <div class="recibo-linea"></div>

    <div class="recibo-fila"><span>Pedido:</span><span><?php echo htmlspecialchars($idPedido); ?></span></div>
    <div class="recibo-fila"><span>Fecha:</span><span><?php echo htmlspecialchars($pedido["fecha"]); ?></span></div>
    <div class="recibo-fila"><span>Tipo:</span><span><?php echo htmlspecialchars($pedido["tipo_ped"]); ?><?php echo $pedido["num_mesa"] ? " (Mesa " . htmlspecialchars($pedido["num_mesa"]) . ")" : ""; ?></span></div>
    <div class="recibo-fila"><span>Cajero:</span><span><?php echo htmlspecialchars($pedido["cod_empleado"] ?? ''); ?></span></div>
    <div class="recibo-linea"></div>

    <table>
        <?php foreach ($detalle as $d): ?>
        <tr>
            <td colspan="2"><?php echo htmlspecialchars($d["cantidad"] . "x " . $d["nombre"]); ?></td>
        </tr>
        <tr>
            <td style="color:#555;">&nbsp;&nbsp;@ <?php echo number_format((float) $d["precio"], 2); ?></td>
            <td style="text-align:right;"><?php echo number_format((float) $d["precio"] * $d["cantidad"], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <div class="recibo-linea"></div>

    <div class="recibo-fila"><span>Subtotal:</span><span>L. <?php echo number_format((float) $pedido["subtotal"], 2); ?></span></div>
    <div class="recibo-fila"><span>Impuesto (15%):</span><span>L. <?php echo number_format((float) $pedido["impuesto"], 2); ?></span></div>
    <div class="recibo-fila recibo-total"><span>TOTAL:</span><span>L. <?php echo number_format((float) $pedido["total"], 2); ?></span></div>
    <div class="recibo-linea"></div>

    <div class="recibo-fila"><span>Recibido:</span><span>L. <?php echo number_format((float) ($pedido["monto_recibido"] ?? 0), 2); ?></span></div>
    <div class="recibo-fila"><span>Cambio:</span><span>L. <?php echo number_format((float) ($pedido["cambio"] ?? 0), 2); ?></span></div>
    <div class="recibo-linea"></div>

    <p class="recibo-centro" style="margin-top:10px;">¡Gracias por su visita!</p>
</div>

<p class="titulo-modulo">Paso 3 de 3 — Cobro</p>
<p><a href="pedidos_listado.php">← Volver a Mesas</a></p>
<p>Pedido <strong><?php echo htmlspecialchars($idPedido); ?></strong> —
   Mesa: <?php echo htmlspecialchars($pedido["num_mesa"] ?: "N/A"); ?> —
   Estado: <?php echo htmlspecialchars($pedido["estado"]); ?></p>

<?php if (isset($_GET["enviado"])): ?><p class="mensaje-ok">Pedido enviado a cocina correctamente.</p><?php endif; ?>
<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo $mensaje; ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="pd-card">
<h3 style="margin-top:0;">Detalle del pedido</h3>
<table class="pd-tabla">
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

<div class="pd-card">
<form method="POST" onsubmit="return calcularCambioValido();">
    <input type="hidden" name="accion" value="cobrar">
    <input type="hidden" name="id_pedido" value="<?php echo htmlspecialchars($idPedido); ?>">

    <div class="pd-row">
        <div class="pd-field"><label>Sub Total</label><input type="text" value="<?php echo htmlspecialchars($pedido['subtotal']); ?>" readonly></div>
        <div class="pd-field"><label>Impuesto (15%)</label><input type="text" value="<?php echo htmlspecialchars($pedido['impuesto']); ?>" readonly></div>
        <div class="pd-field"><label>Total</label><input type="text" id="total" value="<?php echo htmlspecialchars($pedido['total']); ?>" readonly></div>
        <div class="pd-field"><label>Cajero</label><input type="text" name="cajero" value="<?php echo htmlspecialchars($_SESSION['cod_empleado'] ?? ''); ?>"></div>
        <div class="pd-field"><label>Nombre del Cliente</label><input type="text" name="nombre_cliente" placeholder="Consumidor Final"></div>
        <div class="pd-field"><label>Monto Recibido</label><input type="number" step="0.01" id="monto_recibido" name="monto_recibido" onkeyup="calcularCambio()" required></div>
        <div class="pd-field"><label>Cambio</label><input type="text" id="cambio" name="cambio" readonly></div>
    </div>

    <div class="pd-actions">
        <button type="submit">Cobrar</button>
    </div>
</form>
</div>

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