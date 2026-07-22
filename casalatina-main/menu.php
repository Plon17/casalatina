<?php
$modulo_actual = "menu";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$mensaje = "";

// AGREGAR / GUARDAR
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    if ($_POST["accion"] === "guardar") {
        $stmt = $pdo->prepare("INSERT INTO menu (ID_Menu, nombre, precio, tipo, descripcion_men, ID_Producto)
                                VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $_POST["id_menu"], $_POST["nombre"], $_POST["precio"],
            $_POST["tipo"], $_POST["descripcion"], $_POST["id_producto"]
        ]);
        $mensaje = "Item agregado correctamente.";
    }

    if ($_POST["accion"] === "editar") {
        $stmt = $pdo->prepare("UPDATE menu SET nombre=?, precio=?, tipo=?, descripcion_men=?, ID_Producto=?
                                WHERE ID_Menu=?");
        $stmt->execute([
            $_POST["nombre"], $_POST["precio"], $_POST["tipo"],
            $_POST["descripcion"], $_POST["id_producto"], $_POST["id_menu"]
        ]);
        $mensaje = "Item actualizado correctamente.";
    }

    if ($_POST["accion"] === "eliminar") {
        $stmt = $pdo->prepare("DELETE FROM menu WHERE ID_Menu=?");
        $stmt->execute([$_POST["id_menu"]]);
        $mensaje = "Item eliminado.";
    }
}

// BUSCAR / LISTAR
$buscar = trim($_GET["buscar"] ?? "");
if ($buscar !== "") {
    $stmt = $pdo->prepare("SELECT * FROM menu WHERE nombre LIKE ? OR ID_Menu LIKE ?");
    $stmt->execute(["%$buscar%", "%$buscar%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM menu");
}
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tabla de productos para referencia (ID_Producto)
$productos = $pdo->query("SELECT ID_Producto, nombre_pro FROM producto")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "MENÚ";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="titulo-modulo">Menú</p>

<div class="caja-blanca">
<form method="GET" class="form-filtro">
    <div class="fila full">
        <label for="buscar">Buscar producto</label>
        <input type="text" id="buscar" name="buscar" placeholder="Buscar por nombre o ID" value="<?php echo htmlspecialchars($buscar); ?>">
    </div>
    <div class="botones-grupo">
        <button type="submit">BUSCAR</button>
    </div>
</form>
<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo $mensaje; ?></p><?php endif; ?>
</div>

<div class="caja-blanca">
<table>
<tr><th>ID_Menu</th><th>Nombre</th><th>Precio</th><th>Tipo</th><th>Descripción</th><th>ID_Producto</th><th></th></tr>
<?php foreach ($items as $it): ?>
<tr>
    <td><?php echo htmlspecialchars($it["ID_Menu"]); ?></td>
    <td><?php echo htmlspecialchars($it["nombre"]); ?></td>
    <td><?php echo htmlspecialchars($it["precio"]); ?></td>
    <td><?php echo htmlspecialchars($it["tipo"]); ?></td>
    <td><?php echo htmlspecialchars($it["descripcion_men"]); ?></td>
    <td><?php echo htmlspecialchars($it["ID_Producto"]); ?></td>
    <td>
        <button type="button" onclick="cargarFila(<?php echo htmlspecialchars(json_encode($it)); ?>)">EDITAR</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este item?');">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id_menu" value="<?php echo htmlspecialchars($it["ID_Menu"]); ?>">
            <button type="submit">ELIMINAR</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="caja-blanca">
<h3>Datos del item</h3>
<form method="POST" id="formMenu">
    <input type="hidden" name="accion" id="accion" value="guardar">

    <div class="fila">
        <label>ID Menú:</label>
        <input type="text" name="id_menu" id="id_menu" required>
    </div>
    <div class="fila">
        <label>Nombre:</label>
        <input type="text" name="nombre" id="nombre" required>
    </div>
    <div class="fila">
        <label>Precio:</label>
        <input type="number" step="0.01" name="precio" id="precio" required>
    </div>
    <div class="fila">
        <label>Tipo:</label>
        <select name="tipo" id="tipo" required>
            <option value="Platillo">Platillo</option>
            <option value="Bebida">Bebida</option>
        </select>
    </div>
    <div class="fila">
        <label>Descripción:</label>
        <input type="text" name="descripcion" id="descripcion">
    </div>
    <div class="fila">
        <label>ID Producto:</label>
        <select name="id_producto" id="id_producto">
            <option value="">-- ninguno --</option>
            <?php foreach ($productos as $p): ?>
            <option value="<?php echo htmlspecialchars($p["ID_Producto"]); ?>">
                <?php echo htmlspecialchars($p["ID_Producto"] . " - " . $p["nombre_pro"]); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="botones-grupo">
        <button type="submit" onclick="document.getElementById('accion').value='guardar'">GUARDAR</button>
        <button type="submit" onclick="document.getElementById('accion').value='editar'">GUARDAR EDICIÓN</button>
        <button type="button" onclick="document.getElementById('formMenu').reset()">LIMPIAR</button>
    </div>
</form>
</div>

<script>
// Carga los datos de la fila seleccionada en el formulario para poder editarla
function cargarFila(item) {
    document.getElementById("id_menu").value = item.ID_Menu;
    document.getElementById("nombre").value = item.nombre;
    document.getElementById("precio").value = item.precio;
    document.getElementById("tipo").value = item.tipo;
    document.getElementById("descripcion").value = item.descripcion_men;
    document.getElementById("id_producto").value = item.ID_Producto;
}
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
