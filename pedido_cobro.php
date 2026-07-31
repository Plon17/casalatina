<?php
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

// El RTN hondureño son 13 o 14 dígitos (los guiones son solo de formato, no cuentan)
function rtnValido(string $rtn): bool {
    $limpio = str_replace(["-", " "], "", $rtn);
    return ctype_digit($limpio) && strlen($limpio) >= 13 && strlen($limpio) <= 14;
}

$idPedido = $_GET["id"] ?? $_POST["id_pedido"] ?? "";
$error = "";
$mensaje = "";

if (!$idPedido) {
    header("Location: pedido_paso1.php");
    exit;
}

// Cobrar: solo permitido si el pedido está en estado "EnCocina".
// El subtotal SIEMPRE se toma de la base de datos (nunca del formulario),
// así nadie puede manipular el descuento o el total desde el navegador.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "cobrar") {
    $pdo->beginTransaction();
    try {
        $stmtActual = $pdo->prepare("SELECT * FROM pedido WHERE ID_Pedido = ? AND estado = 'EnCocina'");
        $stmtActual->execute([$idPedido]);
        $pedidoActual = $stmtActual->fetch(PDO::FETCH_ASSOC);

        if (!$pedidoActual) {
            $pdo->rollBack();
            $error = "Este pedido ya no está disponible para cobro (puede que ya haya sido cobrado o cancelado).";
        } elseif (trim($_POST["rtn_cliente"] ?? "") !== "" && !rtnValido($_POST["rtn_cliente"])) {
            $pdo->rollBack();
            $error = "El RTN debe tener 13 o 14 dígitos (puedes escribirlo con o sin guiones). Corrígelo o déjalo en blanco.";
        } else {
            $subtotal = (float) $pedidoActual["subtotal"];
            $descuentoPct = max(0, min(100, (float) ($_POST["descuento_pct"] ?? 0)));
            $descuentoMonto = round($subtotal * ($descuentoPct / 100), 2);
            $subtotalConDescuento = $subtotal - $descuentoMonto;
            $impuesto = round($subtotalConDescuento * 0.15, 2);
            $total = round($subtotalConDescuento + $impuesto, 2);

            $metodoPago = ($_POST["metodo_pago"] ?? "Efectivo") === "Tarjeta" ? "Tarjeta" : "Efectivo";

            if ($metodoPago === "Tarjeta") {
                // Con tarjeta se cobra el monto exacto: no hay concepto de "cambio"
                $montoRecibido = $total;
                $cambio = 0;
            } else {
                $montoRecibido = (float) ($_POST["monto_recibido"] ?? 0);
                if ($montoRecibido < $total) {
                    throw new Exception("El monto recibido (L. " . number_format($montoRecibido, 2) . ") es menor al total (L. " . number_format($total, 2) . ").");
                }
                $cambio = round($montoRecibido - $total, 2);
            }

            $stmt = $pdo->prepare("UPDATE pedido SET impuesto=?, total=?, monto_recibido=?, cambio=?, cod_empleado=?,
                                    metodo_pago=?, descuento_pct=?, descuento_monto=?, estado='Pagado'
                                    WHERE ID_Pedido=? AND estado='EnCocina'");
            $stmt->execute([$impuesto, $total, $montoRecibido, $cambio, $_POST["cajero"],
                             $metodoPago, $descuentoPct, $descuentoMonto, $idPedido]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Este pedido ya no está disponible para cobro.");
            }

            // Factura automática (con RTN opcional del cliente)
            $n = (int) $pdo->query("SELECT COUNT(*) AS c FROM factura")->fetch()["c"] + 1;
            $idFactura = "F" . str_pad($n, 6, "0", STR_PAD_LEFT);
            $nombreCliente = trim($_POST["nombre_cliente"] ?? "") ?: "Consumidor Final";
            $rtnCliente = trim($_POST["rtn_cliente"] ?? "") ?: null;

            $stmtFac = $pdo->prepare("INSERT INTO factura (ID_Factura, nombre_cliente, cod_empleado, ID_Pedido, fecha_fac, impuesto, total, rtn_cliente)
                                       VALUES (?,?,?,?,?,?,?,?)");
            $stmtFac->execute([$idFactura, $nombreCliente, $_POST["cajero"], $idPedido, date("Y-m-d"), $impuesto, $total, $rtnCliente]);

            $pdo->commit();
            $mensaje = "Pedido #" . htmlspecialchars($idPedido) . " cobrado correctamente ($metodoPago). Factura $idFactura generada.";
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
.pd-field input,.pd-field select{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:120px;}
.pd-actions{margin-top:16px;display:flex;gap:10px;}
.metodo-pago{display:flex; gap:12px;}
.metodo-pago label{display:flex; align-items:center; gap:6px; border:1px solid #ccc; border-radius:6px; padding:8px 14px; cursor:pointer; font-size:14px;}
.metodo-pago input:checked + span{font-weight:700; color:#C0563A;}
.resumen-cobro{background:#faf5ef; border-radius:6px; padding:12px 16px; margin-top:10px; max-width:320px;}
.resumen-cobro div{display:flex; justify-content:space-between; font-size:14px; padding:2px 0;}
.resumen-cobro .total-final{font-weight:700; font-size:16px; border-top:1px solid #ddd; margin-top:6px; padding-top:6px;}
</style>

<p class="titulo-modulo">Paso 3 de 3 — Cobro</p>
<p><a href="pedidos_listado.php">← Volver a Mesas</a><?php if ($pedido["estado"] === "EnCocina"): ?> &nbsp;|&nbsp; <a href="pedido_paso2.php?id=<?php echo urlencode($idPedido); ?>">+ Agregar más productos</a><?php endif; ?></p>
<p>Pedido <strong><?php echo htmlspecialchars($idPedido); ?></strong> —
   Mesa: <?php echo htmlspecialchars($pedido["num_mesa"] ?: "N/A"); ?> —
   Estado: <?php echo htmlspecialchars($pedido["estado"]); ?></p>

<?php if (isset($_GET["comanda_lote"])): ?><p class="mensaje-ok">Pedido enviado a cocina correctamente.</p><?php endif; ?>
<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo $mensaje; ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<?php if (in_array($pedido["estado"], ["EnCocina", "Pagado"])): ?>
<p><button type="button" onclick="window.open('comanda_pdf.php?id=<?php echo urlencode($idPedido); ?>', '_blank')">Ver comanda completa</button></p>
<?php endif; ?>

<?php if (isset($_GET["comanda_lote"])): ?>
<script>
window.open('comanda_pdf.php?id=<?php echo urlencode($idPedido); ?>&lote=<?php echo (int) $_GET["comanda_lote"]; ?>', '_blank');
</script>
<?php endif; ?>

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
<h3 style="margin-top:0;">Cobro</h3>
<form method="POST" onsubmit="return validarAntesDeCobrar();">
    <input type="hidden" name="accion" value="cobrar">
    <input type="hidden" name="id_pedido" value="<?php echo htmlspecialchars($idPedido); ?>">

    <div class="pd-row">
        <div class="pd-field">
            <label>Método de pago</label>
            <div class="metodo-pago">
                <label><input type="radio" name="metodo_pago" value="Efectivo" checked onchange="actualizarResumen()"><span>💵 Efectivo</span></label>
                <label><input type="radio" name="metodo_pago" value="Tarjeta" onchange="actualizarResumen()"><span>💳 Tarjeta</span></label>
            </div>
        </div>
        <div class="pd-field">
            <label>Descuento (%)</label>
            <input type="number" step="0.01" min="0" max="100" name="descuento_pct" id="descuento_pct" value="0" oninput="actualizarResumen()">
        </div>
        <div class="pd-field"><label>Cajero</label><input type="text" name="cajero" value="<?php echo htmlspecialchars($_SESSION['cod_empleado'] ?? ''); ?>"></div>
        <div class="pd-field"><label>Nombre del Cliente (opcional)</label><input type="text" name="nombre_cliente" placeholder="Consumidor Final"></div>
        <div class="pd-field"><label>RTN del Cliente (opcional)</label><input type="text" name="rtn_cliente" id="rtn_cliente" placeholder="0801-1990-123456" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9-]/g, '')"></div>
    </div>

    <div class="pd-row" id="filaEfectivo" style="margin-top:12px;">
        <div class="pd-field"><label>Monto Recibido</label><input type="number" step="0.01" id="monto_recibido" name="monto_recibido" oninput="actualizarResumen()"></div>
        <div class="pd-field"><label>Cambio</label><input type="text" id="cambio" readonly></div>
    </div>

    <div class="resumen-cobro">
        <div><span>Subtotal</span><span id="r_subtotal">L. <?php echo number_format((float) $pedido['subtotal'], 2); ?></span></div>
        <div><span>Descuento</span><span id="r_descuento">− L. 0.00</span></div>
        <div><span>Impuesto (15%)</span><span id="r_impuesto">L. <?php echo number_format((float) $pedido['impuesto'], 2); ?></span></div>
        <div class="total-final"><span>TOTAL</span><span id="r_total">L. <?php echo number_format((float) $pedido['total'], 2); ?></span></div>
    </div>

    <div class="pd-actions">
        <button type="submit">Cobrar</button>
    </div>
</form>
</div>

<!-- Dividir cuenta: calculadora, no cambia nada en la base de datos -->
<div class="pd-card">
<h3 style="margin-top:0;">Dividir cuenta</h3>
<div class="pd-row">
    <div class="pd-field"><label>Entre cuántas personas</label><input type="number" min="1" max="20" value="2" id="personas" oninput="actualizarDivision()"></div>
</div>
<p id="divisionResultado" style="margin-top:10px;"></p>
<button type="button" onclick="imprimirDivision()">Imprimir división</button>
</div>

<form method="POST" onsubmit="return confirm('¿Seguro que deseas cancelar este pedido?');">
    <input type="hidden" name="accion" value="cancelar_pedido">
    <button type="submit">CANCELAR PEDIDO</button>
</form>

<script>
const subtotalBase = <?php echo (float) $pedido['subtotal']; ?>;

function calcularTotales() {
    const descuentoPct = Math.max(0, Math.min(100, parseFloat(document.getElementById("descuento_pct").value) || 0));
    const descuentoMonto = subtotalBase * (descuentoPct / 100);
    const subtotalConDescuento = subtotalBase - descuentoMonto;
    const impuesto = subtotalConDescuento * 0.15;
    const total = subtotalConDescuento + impuesto;
    return { descuentoMonto, impuesto, total };
}

function actualizarResumen() {
    const { descuentoMonto, impuesto, total } = calcularTotales();
    document.getElementById("r_subtotal").textContent = "L. " + subtotalBase.toFixed(2);
    document.getElementById("r_descuento").textContent = "− L. " + descuentoMonto.toFixed(2);
    document.getElementById("r_impuesto").textContent = "L. " + impuesto.toFixed(2);
    document.getElementById("r_total").textContent = "L. " + total.toFixed(2);

    const esTarjeta = document.querySelector('input[name="metodo_pago"]:checked').value === "Tarjeta";
    document.getElementById("filaEfectivo").style.display = esTarjeta ? "none" : "flex";
    document.getElementById("monto_recibido").required = !esTarjeta;

    if (!esTarjeta) {
        const recibido = parseFloat(document.getElementById("monto_recibido").value) || 0;
        document.getElementById("cambio").value = (recibido - total).toFixed(2);
    }
    actualizarDivision();
}

function validarAntesDeCobrar() {
    const rtn = document.getElementById("rtn_cliente").value.trim();
    if (rtn !== "") {
        const soloDigitos = rtn.replace(/-/g, "");
        if (!/^\d+$/.test(soloDigitos) || soloDigitos.length < 13 || soloDigitos.length > 14) {
            alert("El RTN debe tener 13 o 14 dígitos (puedes usar guiones o no).");
            return false;
        }
    }
    const esTarjeta = document.querySelector('input[name="metodo_pago"]:checked').value === "Tarjeta";
    if (esTarjeta) return true;
    const { total } = calcularTotales();
    const recibido = parseFloat(document.getElementById("monto_recibido").value) || 0;
    if (recibido < total) {
        alert("El monto recibido es menor al total.");
        return false;
    }
    return true;
}

function actualizarDivision() {
    const { total } = calcularTotales();
    const personas = Math.max(1, parseInt(document.getElementById("personas").value) || 1);
    const porPersona = total / personas;
    document.getElementById("divisionResultado").textContent =
        "Cada persona paga: L. " + porPersona.toFixed(2) + " (total L. " + total.toFixed(2) + " entre " + personas + ")";
}

function imprimirDivision() {
    const personas = Math.max(1, parseInt(document.getElementById("personas").value) || 1);
    const descuentoPct = document.getElementById("descuento_pct").value || 0;
    window.open('division_pdf.php?id=<?php echo urlencode($idPedido); ?>&personas=' + personas + '&descuento_pct=' + descuentoPct, '_blank');
}

actualizarResumen();
</script>

<?php elseif ($pedido["estado"] === "Pagado"): ?>

<div class="pd-card">
    <p>Este pedido ya fue cobrado.</p>
    <div class="resumen-cobro">
        <div><span>Subtotal</span><span>L. <?php echo number_format((float) $pedido["subtotal"], 2); ?></span></div>
        <?php if ((float) $pedido["descuento_monto"] > 0): ?>
        <div><span>Descuento (<?php echo htmlspecialchars($pedido["descuento_pct"]); ?>%)</span><span>− L. <?php echo number_format((float) $pedido["descuento_monto"], 2); ?></span></div>
        <?php endif; ?>
        <div><span>Impuesto</span><span>L. <?php echo number_format((float) $pedido["impuesto"], 2); ?></span></div>
        <div class="total-final"><span>TOTAL</span><span>L. <?php echo number_format((float) $pedido["total"], 2); ?></span></div>
        <div><span>Método de pago</span><span><?php echo htmlspecialchars($pedido["metodo_pago"] ?? "Efectivo"); ?></span></div>
        <div><span>Recibido</span><span>L. <?php echo number_format((float) $pedido["monto_recibido"], 2); ?></span></div>
        <div><span>Cambio</span><span>L. <?php echo number_format((float) $pedido["cambio"], 2); ?></span></div>
    </div>
    <p style="margin-top:14px;"><button type="button" onclick="window.open('recibo_pdf.php?id=<?php echo urlencode($idPedido); ?>', '_blank')">Imprimir recibo</button></p>
</div>

<?php else: ?>

<p>Este pedido todavía no ha sido enviado a cocina.</p>

<?php endif; ?>

<p><a href="pedido_paso1.php">+ Nuevo pedido</a></p>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>