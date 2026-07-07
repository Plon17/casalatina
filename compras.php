<?php
$modulo_actual = "compras";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar") {
    $stmt = $pdo->prepare("INSERT INTO compras (ID_compras, ID_Producto, fecha, cantidad, monto_total)
                            VALUES (?,?,?,?,?)");
    $stmt->execute([
        $_POST["id_compra"], $_POST["id_producto"], $_POST["fecha"],
        $_POST["cantidad"], $_POST["monto"]
    ]);

    // Aumentamos el stock del producto comprado
    $stmt2 = $pdo->prepare("UPDATE producto SET cantidad_pro = cantidad_pro + ? WHERE ID_Producto = ?");
    $stmt2->execute([$_POST["cantidad"], $_POST["id_producto"]]);

    $mensaje = "Compra registrada correctamente.";
}

$productos = $pdo->query("SELECT ID_Producto, nombre_pro FROM producto")->fetchAll(PDO::FETCH_ASSOC);
$compras = $pdo->query("SELECT * FROM compras ORDER BY fecha DESC")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "COMPRAS";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="titulo-modulo">Compras</p>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo $mensaje; ?></p><?php endif; ?>

<form method="POST">
    <input type="hidden" name="accion" value="registrar">

    <div class="fila">
        <label>ID Compra:</label>
        <input type="text" name="id_compra" required>
    </div>
    <div class="fila">
        <label>ID Producto:</label>
        <select name="id_producto" required>
            <?php foreach ($productos as $p): ?>
            <option value="<?php echo htmlspecialchars($p["ID_Producto"]); ?>">
                <?php echo htmlspecialchars($p["ID_Producto"] . " - " . $p["nombre_pro"]); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="fila">
        <label>Fecha:</label>
        <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
    </div>
    <div class="fila">
        <label>Cantidad:</label>
        <input type="number" name="cantidad" required>
    </div>
    <div class="fila">
        <label>Monto:</label>
        <input type="number" step="0.01" name="monto" required>
    </div>

    <button type="submit">REGISTRAR</button>
</form>

<h3>Tabla de compras</h3>
<div class="caja-blanca">
<table>
<tr><th>ID Compra</th><th>ID Producto</th><th>Fecha</th><th>Cantidad</th><th>Monto</th></tr>
<?php foreach ($compras as $c): ?>
<tr>
    <td><?php echo htmlspecialchars($c["ID_compras"]); ?></td>
    <td><?php echo htmlspecialchars($c["ID_Producto"]); ?></td>
    <td><?php echo htmlspecialchars($c["fecha"]); ?></td>
    <td><?php echo htmlspecialchars($c["cantidad"]); ?></td>
    <td><?php echo htmlspecialchars($c["monto_total"]); ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
