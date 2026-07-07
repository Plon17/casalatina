<?php
$modulo_actual = "stock";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    if ($_POST["accion"] === "guardar") {
        $stmt = $pdo->prepare("INSERT INTO producto (ID_Producto, nombre_pro, cantidad_pro, precio_pro, categoria_pro, ID_prov)
                                VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $_POST["id_producto"], $_POST["nombre"], $_POST["cantidad"],
            $_POST["precio"], $_POST["categoria"], $_POST["id_proveedor"]
        ]);
        $mensaje = "Producto agregado.";
    }

    if ($_POST["accion"] === "editar") {
        $stmt = $pdo->prepare("UPDATE producto SET nombre_pro=?, cantidad_pro=?, precio_pro=?, categoria_pro=?, ID_prov=?
                                WHERE ID_Producto=?");
        $stmt->execute([
            $_POST["nombre"], $_POST["cantidad"], $_POST["precio"],
            $_POST["categoria"], $_POST["id_proveedor"], $_POST["id_producto"]
        ]);
        $mensaje = "Producto actualizado.";
    }

    if ($_POST["accion"] === "eliminar") {
        $stmt = $pdo->prepare("DELETE FROM producto WHERE ID_Producto=?");
        $stmt->execute([$_POST["id_producto"]]);
        $mensaje = "Producto eliminado.";
    }
}

$buscar = trim($_GET["buscar"] ?? "");
if ($buscar !== "") {
    $stmt = $pdo->prepare("SELECT * FROM producto WHERE nombre_pro LIKE ? OR ID_Producto LIKE ?");
    $stmt->execute(["%$buscar%", "%$buscar%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM producto");
}
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Productos con cantidad baja (para el listado de "compras a realizar")
$bajos = $pdo->query("SELECT * FROM producto WHERE cantidad_pro <= 5")->fetchAll(PDO::FETCH_ASSOC);

$proveedores = $pdo->query("SELECT ID_prov, nom_prov FROM proveedores")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "STOCK";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="titulo-modulo">Inventario General</p>

<form method="GET" style="margin-bottom:15px;">
    <input type="text" name="buscar" placeholder="Buscar por nombre o ID" value="<?php echo htmlspecialchars($buscar); ?>">
    <button type="submit">BUSCAR</button>
</form>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo $mensaje; ?></p><?php endif; ?>

<div class="caja-blanca">
<table>
<tr><th>ID_Producto</th><th>nombre</th><th>cantidad_pro</th><th>precio_pro</th><th>categoria_pro</th><th></th></tr>
<?php foreach ($productos as $p): ?>
<tr>
    <td><?php echo htmlspecialchars($p["ID_Producto"]); ?></td>
    <td><?php echo htmlspecialchars($p["nombre_pro"]); ?></td>
    <td><?php echo htmlspecialchars($p["cantidad_pro"]); ?></td>
    <td><?php echo htmlspecialchars($p["precio_pro"]); ?></td>
    <td><?php echo htmlspecialchars($p["categoria_pro"]); ?></td>
    <td>
        <button type="button" onclick="cargarFila(<?php echo htmlspecialchars(json_encode($p)); ?>)">EDITAR</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este producto?');">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id_producto" value="<?php echo htmlspecialchars($p["ID_Producto"]); ?>">
            <button type="submit">ELIMINAR</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<h3>Datos del producto</h3>
<form method="POST" id="formStock">
    <input type="hidden" name="accion" id="accion" value="guardar">

    <div class="fila">
        <label>ID Producto:</label>
        <input type="text" name="id_producto" id="id_producto" required>
    </div>
    <div class="fila">
        <label>Nombre:</label>
        <input type="text" name="nombre" id="nombre" required>
    </div>
    <div class="fila">
        <label>Cantidad:</label>
        <input type="number" name="cantidad" id="cantidad" required>
    </div>
    <div class="fila">
        <label>Precio:</label>
        <input type="number" step="0.01" name="precio" id="precio" required>
    </div>
    <div class="fila">
        <label>Categoría:</label>
        <input type="text" name="categoria" id="categoria">
    </div>
    <div class="fila">
        <label>ID Proveedor:</label>
        <select name="id_proveedor" id="id_proveedor">
            <option value="">-- ninguno --</option>
            <?php foreach ($proveedores as $pr): ?>
            <option value="<?php echo htmlspecialchars($pr["ID_prov"]); ?>">
                <?php echo htmlspecialchars($pr["ID_prov"] . " - " . $pr["nom_prov"]); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" onclick="document.getElementById('accion').value='guardar'">GUARDAR</button>
    <button type="submit" onclick="document.getElementById('accion').value='editar'">GUARDAR EDICIÓN</button>
</form>

<h3>Productos con poco stock (posibles compras a realizar)</h3>
<div class="caja-blanca">
<table>
<tr><th>ID_Producto</th><th>nombre</th><th>cantidad_pro</th></tr>
<?php foreach ($bajos as $b): ?>
<tr>
    <td><?php echo htmlspecialchars($b["ID_Producto"]); ?></td>
    <td><?php echo htmlspecialchars($b["nombre_pro"]); ?></td>
    <td><?php echo htmlspecialchars($b["cantidad_pro"]); ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<script>
function cargarFila(p) {
    document.getElementById("id_producto").value = p.ID_Producto;
    document.getElementById("nombre").value = p.nombre_pro;
    document.getElementById("cantidad").value = p.cantidad_pro;
    document.getElementById("precio").value = p.precio_pro;
    document.getElementById("categoria").value = p.categoria_pro;
}
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
