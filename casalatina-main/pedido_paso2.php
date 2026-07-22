<?php
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$idPedido = $_GET["id"] ?? $_POST["id_pedido"] ?? "";
$error = "";

if (!$idPedido) {
    header("Location: pedido_paso1.php");
    exit;
}

// Actualiza el detalle (pudo cambiar en pantalla) y envía el pedido a cocina
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "enviar_cocina") {
    $items = json_decode($_POST["detalle_json"] ?? "", true);

    if (!$items || count($items) === 0) {
        $error = "El pedido no puede quedar sin productos.";
    } else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM pedido_detalle WHERE ID_Pedido = ?")->execute([$idPedido]);

            $d = (int) $pdo->query("SELECT COUNT(*) AS c FROM pedido_detalle")->fetch()["c"] + 1;
            $stmtDet = $pdo->prepare("INSERT INTO pedido_detalle (ID_detped, ID_Pedido, ID_Menu, cantidad, precio)
                                       VALUES (?,?,?,?,?)");
            foreach ($items as $it) {
                $idDet = "D" . str_pad($d, 6, "0", STR_PAD_LEFT);
                $stmtDet->execute([$idDet, $idPedido, $it["id_menu"], $it["cantidad"], $it["precio"]]);
                $d++;
            }

            $stmt = $pdo->prepare("UPDATE pedido SET subtotal=?, impuesto=?, total=?, estado='EnCocina' WHERE ID_Pedido=?");
            $stmt->execute([$_POST["subtotal"], $_POST["impuesto"], $_POST["total"], $idPedido]);

            $pdo->commit();
            header("Location: pedido_cobro.php?id=" . urlencode($idPedido) . "&enviado=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al enviar el pedido a cocina: " . $e->getMessage();
        }
    }
}

$pedidoStmt = $pdo->prepare("SELECT * FROM pedido WHERE ID_Pedido = ?");
$pedidoStmt->execute([$idPedido]);
$pedido = $pedidoStmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    header("Location: pedido_paso1.php");
    exit;
}

if ($pedido["estado"] !== "Abierto") {
    header("Location: pedido_cobro.php?id=" . urlencode($idPedido));
    exit;
}

$detalleStmt = $pdo->prepare("SELECT d.ID_Menu, d.cantidad, d.precio, m.nombre
                               FROM pedido_detalle d
                               JOIN menu m ON m.ID_Menu = d.ID_Menu
                               WHERE d.ID_Pedido = ?");
$detalleStmt->execute([$idPedido]);
$detalle = $detalleStmt->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "PEDIDO - PASO 2: REVISIÓN";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:14px;}
.pd-tabla th{background:#f5f5f5;}
.pd-totales{display:flex;gap:30px;flex-wrap:wrap;align-items:flex-end;margin-top:14px;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:120px;}
.pd-actions{margin-top:16px;display:flex;gap:10px;}
</style>

<p class="titulo-modulo">Paso 2 de 3 — Revisar y enviar a cocina</p>
<p>Pedido <strong><?php echo htmlspecialchars($idPedido); ?></strong> —
   Mesa: <?php echo htmlspecialchars($pedido["num_mesa"] ?: "N/A"); ?> —
   Tipo: <?php echo htmlspecialchars($pedido["tipo_ped"]); ?></p>

<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="pd-card">
    <h3 style="margin-top:0;">Productos del pedido</h3>
    <table class="pd-tabla" id="tablaPedido">
    <tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th><th></th></tr>
    </table>

    <div class="pd-totales">
        <div class="pd-field"><label>Sub Total</label><input type="text" id="subtotal" readonly></div>
        <div class="pd-field"><label>Impuesto (15%)</label><input type="text" id="impuesto" readonly></div>
        <div class="pd-field"><label>Total</label><input type="text" id="total" readonly></div>
    </div>

    <div class="pd-actions">
        <button type="button" onclick="volverPaso1()">← Volver (agregar más productos)</button>
        <button type="button" onclick="enviarCocina()">Enviar a Cocina →</button>
    </div>
</div>

<form method="POST" id="formEnviar" style="display:none;">
    <input type="hidden" name="accion" value="enviar_cocina">
    <input type="hidden" name="id_pedido" value="<?php echo htmlspecialchars($idPedido); ?>">
    <input type="hidden" name="subtotal" id="f_subtotal">
    <input type="hidden" name="impuesto" id="f_impuesto">
    <input type="hidden" name="total" id="f_total">
    <input type="hidden" name="detalle_json" id="f_detalle_json">
</form>

<script>
const idPedido = <?php echo json_encode($idPedido); ?>;
let itemsPedido = <?php echo json_encode(array_map(function ($d) {
    return [
        "id_menu" => $d["ID_Menu"],
        "nombre" => $d["nombre"],
        "precio" => (float) $d["precio"],
        "cantidad" => (int) $d["cantidad"]
    ];
}, $detalle)); ?>;

function quitarItem(idx) {
    itemsPedido.splice(idx, 1);
    renderTablaPedido();
    recalcularTotales();
}

function renderTablaPedido() {
    const tabla = document.getElementById("tablaPedido");
    tabla.innerHTML = "<tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th><th></th></tr>";
    itemsPedido.forEach((it, idx) => {
        const fila = tabla.insertRow();
        const subLinea = (it.precio * it.cantidad).toFixed(2);
        fila.innerHTML = `<td>${it.id_menu}</td><td>${it.nombre}</td><td>${it.precio}</td><td>${it.cantidad}</td><td>${subLinea}</td><td><button type="button" onclick="quitarItem(${idx})">Quitar</button></td>`;
    });
}

function recalcularTotales() {
    const subtotal = itemsPedido.reduce((acc, it) => acc + (it.precio * it.cantidad), 0);
    const impuesto = subtotal * 0.15;
    const total = subtotal + impuesto;
    document.getElementById("subtotal").value = subtotal.toFixed(2);
    document.getElementById("impuesto").value = impuesto.toFixed(2);
    document.getElementById("total").value = total.toFixed(2);
}

function volverPaso1() {
    window.location.href = "pedido_paso1.php?id=" + encodeURIComponent(idPedido);
}

function enviarCocina() {
    if (itemsPedido.length === 0) {
        alert("El pedido no puede quedar vacío.");
        return;
    }
    document.getElementById("f_subtotal").value = document.getElementById("subtotal").value;
    document.getElementById("f_impuesto").value = document.getElementById("impuesto").value;
    document.getElementById("f_total").value = document.getElementById("total").value;
    document.getElementById("f_detalle_json").value = JSON.stringify(itemsPedido);
    document.getElementById("formEnviar").submit();
}

renderTablaPedido();
recalcularTotales();
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>