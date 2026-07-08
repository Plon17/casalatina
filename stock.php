<?php
$modulo_actual = "stock";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$mensaje = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    if ($_POST["accion"] === "guardar") {
        try {
            $stmt = $pdo->prepare("INSERT INTO producto (ID_Producto, nombre_pro, cantidad_pro, precio_pro, categoria_pro, ID_prov)
                                    VALUES (?,?,?,?,?,?)");
            $stmt->execute([
                $_POST["id_producto"], $_POST["nombre"], $_POST["cantidad"],
                $_POST["precio"], $_POST["categoria"], $_POST["id_proveedor"] ?: null
            ]);
            $mensaje = "Producto agregado.";
        } catch (Exception $e) {
            $error = "Error al agregar el producto (¿el ID ya existe?): " . $e->getMessage();
        }
    }

    if ($_POST["accion"] === "editar") {
        $stmt = $pdo->prepare("UPDATE producto SET nombre_pro=?, cantidad_pro=?, precio_pro=?, categoria_pro=?, ID_prov=?
                                WHERE ID_Producto=?");
        $stmt->execute([
            $_POST["nombre"], $_POST["cantidad"], $_POST["precio"],
            $_POST["categoria"], $_POST["id_proveedor"] ?: null, $_POST["id_producto"]
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

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input,.pd-field select{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:140px;}
.pd-field.chico input{min-width:70px;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:14px;}
.pd-tabla th{background:#f5f5f5;}
.pd-actions{margin-top:14px;display:flex;gap:10px;}
.fila-bajo{background:#fdecea;}
.badge-bajo{background:#e74c3c;color:#fff;padding:1px 6px;border-radius:4px;font-size:12px;margin-left:6px;}
</style>

<p class="titulo-modulo">Inventario General</p>

<form method="GET" class="pd-row" style="margin-bottom:15px;">
    <div class="pd-field" style="flex:1; min-width:220px;">
        <label>Buscar</label>
        <input type="text" name="buscar" placeholder="Buscar por nombre o ID" value="<?php echo htmlspecialchars($buscar); ?>">
    </div>
    <button type="submit">BUSCAR</button>
    <?php if ($buscar): ?><a href="stock.php" style="align-self:center;">Limpiar</a><?php endif; ?>
</form>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="pd-card">
<table class="pd-tabla">
<tr><th>ID_Producto</th><th>Nombre</th><th>Cantidad</th><th>Precio</th><th>Categoría</th><th></th></tr>
<?php if (count($productos) === 0): ?>
<tr><td colspan="6">No se encontraron productos.</td></tr>
<?php endif; ?>
<?php foreach ($productos as $p): ?>
<tr class="<?php echo ($p["cantidad_pro"] <= 5) ? "fila-bajo" : ""; ?>">
    <td><?php echo htmlspecialchars($p["ID_Producto"]); ?></td>
    <td><?php echo htmlspecialchars($p["nombre_pro"]); ?></td>
    <td><?php echo htmlspecialchars($p["cantidad_pro"]); ?><?php if ($p["cantidad_pro"] <= 5): ?><span class="badge-bajo">bajo</span><?php endif; ?></td>
    <td><?php echo number_format((float) $p["precio_pro"], 2); ?></td>
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

<div class="pd-card">
<h3 style="margin-top:0;" id="tituloForm">Agregar producto nuevo</h3>
<form method="POST" id="formStock">
    <input type="hidden" name="accion" id="accion" value="guardar">

    <div class="pd-row">
        <div class="pd-field">
            <label>ID Producto</label>
            <input type="text" name="id_producto" id="id_producto" required>
        </div>
        <div class="pd-field" style="flex:1; min-width:180px;">
            <label>Nombre</label>
            <input type="text" name="nombre" id="nombre" required>
        </div>
        <div class="pd-field chico">
            <label>Cantidad</label>
            <input type="number" name="cantidad" id="cantidad" required>
        </div>
        <div class="pd-field chico">
            <label>Precio</label>
            <input type="number" step="0.01" name="precio" id="precio" required>
        </div>
        <div class="pd-field">
            <label>Categoría</label>
            <input type="text" name="categoria" id="categoria">
        </div>
        <div class="pd-field">
            <label>Proveedor</label>
            <select name="id_proveedor" id="id_proveedor">
                <option value="">-- ninguno --</option>
                <?php foreach ($proveedores as $pr): ?>
                <option value="<?php echo htmlspecialchars($pr["ID_prov"]); ?>">
                    <?php echo htmlspecialchars($pr["ID_prov"] . " - " . $pr["nom_prov"]); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="pd-actions">
        <button type="submit" id="btnGuardar" onclick="document.getElementById('accion').value='guardar'">GUARDAR</button>
        <button type="submit" id="btnEditar" style="display:none;" onclick="document.getElementById('accion').value='editar'">GUARDAR EDICIÓN</button>
        <button type="button" id="btnNuevo" style="display:none;" onclick="nuevoProducto()">+ NUEVO PRODUCTO</button>
    </div>
</form>
</div>

<div class="pd-card">
<h3 style="margin-top:0;">Productos con poco stock (posibles compras a realizar)</h3>
<table class="pd-tabla">
<tr><th>ID_Producto</th><th>Nombre</th><th>Cantidad</th></tr>
<?php if (count($bajos) === 0): ?>
<tr><td colspan="3">Todo el inventario está en buen nivel.</td></tr>
<?php endif; ?>
<?php foreach ($bajos as $b): ?>
<tr class="fila-bajo">
    <td><?php echo htmlspecialchars($b["ID_Producto"]); ?></td>
    <td><?php echo htmlspecialchars($b["nombre_pro"]); ?></td>
    <td><?php echo htmlspecialchars($b["cantidad_pro"]); ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<script>
function cargarFila(p) {
    document.getElementById("tituloForm").textContent = "Editando producto " + p.ID_Producto;
    document.getElementById("id_producto").value = p.ID_Producto;
    document.getElementById("id_producto").readOnly = true;
    document.getElementById("nombre").value = p.nombre_pro;
    document.getElementById("cantidad").value = p.cantidad_pro;
    document.getElementById("precio").value = p.precio_pro;
    document.getElementById("categoria").value = p.categoria_pro;
    document.getElementById("id_proveedor").value = p.ID_prov || "";
    document.getElementById("btnGuardar").style.display = "none";
    document.getElementById("btnEditar").style.display = "inline-block";
    document.getElementById("btnNuevo").style.display = "inline-block";
    document.getElementById("formStock").scrollIntoView({ behavior: "smooth", block: "center" });
}

function nuevoProducto() {
    document.getElementById("tituloForm").textContent = "Agregar producto nuevo";
    document.getElementById("formStock").reset();
    document.getElementById("id_producto").readOnly = false;
    document.getElementById("btnGuardar").style.display = "inline-block";
    document.getElementById("btnEditar").style.display = "none";
    document.getElementById("btnNuevo").style.display = "none";
}
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>