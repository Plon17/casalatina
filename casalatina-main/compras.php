<?php
$modulo_actual = "compras";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$mensaje = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar") {

    $idProducto = $_POST["id_producto"] ?? "";
    $cantidad = (int) ($_POST["cantidad"] ?? 0);
    $monto = $_POST["monto"] ?? "";

    if (!$idProducto || $cantidad <= 0) {
        $error = "Selecciona un producto (usando el buscador) y una cantidad válida.";
    } else {
        $pdo->beginTransaction();
        try {
            $n = (int) $pdo->query("SELECT COUNT(*) AS c FROM compras")->fetch()["c"] + 1;
            $idCompra = "C" . str_pad($n, 6, "0", STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO compras (ID_compras, ID_Producto, fecha, cantidad, monto_total)
                                    VALUES (?,?,?,?,?)");
            $stmt->execute([$idCompra, $idProducto, $_POST["fecha"], $cantidad, $monto]);

            // Conectamos automáticamente la compra con el stock
            $stmt2 = $pdo->prepare("UPDATE producto SET cantidad_pro = cantidad_pro + ? WHERE ID_Producto = ?");
            $stmt2->execute([$cantidad, $idProducto]);

            $pdo->commit();
            $mensaje = "Compra #$idCompra registrada. Stock actualizado (+$cantidad unidades).";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al registrar la compra: " . $e->getMessage();
        }
    }
}

$productos = $pdo->query("SELECT ID_Producto, nombre_pro, precio_pro, cantidad_pro FROM producto")->fetchAll(PDO::FETCH_ASSOC);

$compras = $pdo->query("SELECT c.*, p.nombre_pro
                         FROM compras c
                         LEFT JOIN producto p ON p.ID_Producto = c.ID_Producto
                         ORDER BY c.fecha DESC, c.ID_compras DESC")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "COMPRAS";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input,.pd-field select{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:160px;}
.pd-field.chico input{min-width:70px;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:14px;}
.pd-tabla th{background:#f5f5f5;}
.pd-resultados{max-height:150px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;}
.pd-actions{margin-top:14px;display:flex;gap:10px;}
</style>

<p class="titulo-modulo">Compras</p>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="pd-card">
<h3 style="margin-top:0;">Registrar compra</h3>
<form method="POST" id="formCompra">
    <input type="hidden" name="accion" value="registrar">
    <input type="hidden" name="id_producto" id="id_producto">

    <div class="pd-row">
        <div class="pd-field" style="flex:1; min-width:220px;">
            <label>Buscar producto</label>
            <input type="text" id="buscar_producto" placeholder="Nombre o ID" autocomplete="off">
        </div>
        <div class="pd-field">
            <label>Fecha</label>
            <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
    </div>

    <div class="pd-resultados" style="margin-top:10px;">
        <table class="pd-tabla" id="tablaResultados">
        <tr><th>ID</th><th>Nombre</th><th>Precio ref.</th><th>Stock actual</th></tr>
        </table>
    </div>

    <div class="pd-row" style="margin-top:10px;">
        <div class="pd-field" style="flex:1;"><label>Producto seleccionado</label><input type="text" id="nombre_producto" readonly></div>
        <div class="pd-field chico"><label>Cantidad</label><input type="number" name="cantidad" id="cantidad" min="1" value="1" oninput="calcularMonto()" required></div>
        <div class="pd-field"><label>Monto total</label><input type="number" step="0.01" name="monto" id="monto" required></div>
    </div>

    <div class="pd-actions">
        <button type="submit">REGISTRAR COMPRA</button>
    </div>
</form>
</div>

<div class="pd-card">
<h3 style="margin-top:0;">Historial de compras</h3>
<table class="pd-tabla">
<tr><th>ID Compra</th><th>Producto</th><th>Fecha</th><th>Cantidad</th><th>Monto</th></tr>
<?php if (count($compras) === 0): ?>
<tr><td colspan="5">Todavía no hay compras registradas.</td></tr>
<?php endif; ?>
<?php foreach ($compras as $c): ?>
<tr>
    <td><?php echo htmlspecialchars($c["ID_compras"]); ?></td>
    <td><?php echo htmlspecialchars(($c["nombre_pro"] ?? "(producto eliminado)") . " — " . $c["ID_Producto"]); ?></td>
    <td><?php echo htmlspecialchars($c["fecha"]); ?></td>
    <td><?php echo htmlspecialchars($c["cantidad"]); ?></td>
    <td><?php echo number_format((float) $c["monto_total"], 2); ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<script>
const productos = <?php echo json_encode($productos); ?>;
let precioSeleccionado = 0;

document.getElementById("buscar_producto").addEventListener("keyup", function () {
    const texto = this.value.toLowerCase();
    const tabla = document.getElementById("tablaResultados");
    tabla.innerHTML = "<tr><th>ID</th><th>Nombre</th><th>Precio ref.</th><th>Stock actual</th></tr>";
    if (texto === "") return;

    productos.filter(p => p.nombre_pro.toLowerCase().includes(texto) || p.ID_Producto.toLowerCase().includes(texto))
        .forEach(p => {
            const fila = tabla.insertRow();
            fila.style.cursor = "pointer";
            fila.innerHTML = `<td>${p.ID_Producto}</td><td>${p.nombre_pro}</td><td>${p.precio_pro}</td><td>${p.cantidad_pro}</td>`;
            fila.onclick = () => seleccionarProducto(p);
        });
});

function seleccionarProducto(p) {
    document.getElementById("id_producto").value = p.ID_Producto;
    document.getElementById("nombre_producto").value = p.nombre_pro + " (" + p.ID_Producto + ")";
    document.getElementById("buscar_producto").value = "";
    document.getElementById("tablaResultados").innerHTML = "<tr><th>ID</th><th>Nombre</th><th>Precio ref.</th><th>Stock actual</th></tr>";
    precioSeleccionado = parseFloat(p.precio_pro) || 0;
    calcularMonto();
}

function calcularMonto() {
    const cantidad = parseInt(document.getElementById("cantidad").value) || 0;
    if (precioSeleccionado > 0) {
        document.getElementById("monto").value = (precioSeleccionado * cantidad).toFixed(2);
    }
}

document.getElementById("formCompra").addEventListener("submit", function (e) {
    if (!document.getElementById("id_producto").value) {
        e.preventDefault();
        alert("Selecciona un producto de la lista de búsqueda.");
    }
});
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>