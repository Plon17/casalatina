<?php
$modulo_actual = "prov";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    if ($_POST["accion"] === "guardar") {
        $stmt = $pdo->prepare("INSERT INTO proveedores (ID_prov, nom_prov, tel_prov, dir_prov) VALUES (?,?,?,?)");
        $stmt->execute([$_POST["id_prov"], $_POST["nombre"], $_POST["telefono"], $_POST["direccion"]]);
        $mensaje = "Proveedor agregado.";
    }

    if ($_POST["accion"] === "editar") {
        $stmt = $pdo->prepare("UPDATE proveedores SET nom_prov=?, tel_prov=?, dir_prov=? WHERE ID_prov=?");
        $stmt->execute([$_POST["nombre"], $_POST["telefono"], $_POST["direccion"], $_POST["id_prov"]]);
        $mensaje = "Proveedor actualizado.";
    }

    if ($_POST["accion"] === "eliminar") {
        $stmt = $pdo->prepare("DELETE FROM proveedores WHERE ID_prov=?");
        $stmt->execute([$_POST["id_prov"]]);
        $mensaje = "Proveedor eliminado.";
    }
}

$buscar = trim($_GET["buscar"] ?? "");
if ($buscar !== "") {
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE nom_prov LIKE ? OR ID_prov LIKE ?");
    $stmt->execute(["%$buscar%", "%$buscar%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM proveedores");
}
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "PROVEEDORES";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="titulo-modulo">Proveedores</p>

<form method="GET" style="margin-bottom:15px;">
    <input type="text" name="buscar" placeholder="Buscar por nombre o ID" value="<?php echo htmlspecialchars($buscar); ?>">
    <button type="submit">BUSCAR</button>
</form>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo $mensaje; ?></p><?php endif; ?>

<div class="caja-blanca">
<table>
<tr><th>ID_prov</th><th>nom_prov</th><th>tel_prov</th><th>dir_prov</th><th></th></tr>
<?php foreach ($proveedores as $p): ?>
<tr>
    <td><?php echo htmlspecialchars($p["ID_prov"]); ?></td>
    <td><?php echo htmlspecialchars($p["nom_prov"]); ?></td>
    <td><?php echo htmlspecialchars($p["tel_prov"]); ?></td>
    <td><?php echo htmlspecialchars($p["dir_prov"]); ?></td>
    <td>
        <button type="button" onclick="cargarFila(<?php echo htmlspecialchars(json_encode($p)); ?>)">EDITAR</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este proveedor?');">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id_prov" value="<?php echo htmlspecialchars($p["ID_prov"]); ?>">
            <button type="submit">ELIMINAR</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<h3>Datos del proveedor</h3>
<form method="POST" id="formProv">
    <input type="hidden" name="accion" id="accion" value="guardar">

    <div class="fila">
        <label>ID Proveedor:</label>
        <input type="text" name="id_prov" id="id_prov" required>
    </div>
    <div class="fila">
        <label>Nombre:</label>
        <input type="text" name="nombre" id="nombre" required>
    </div>
    <div class="fila">
        <label>Teléfono:</label>
        <input type="text" name="telefono" id="telefono">
    </div>
    <div class="fila">
        <label>Dirección:</label>
        <input type="text" name="direccion" id="direccion">
    </div>

    <button type="submit" onclick="document.getElementById('accion').value='guardar'">GUARDAR</button>
    <button type="submit" onclick="document.getElementById('accion').value='editar'">GUARDAR EDICIÓN</button>
</form>

<script>
function cargarFila(p) {
    document.getElementById("id_prov").value = p.ID_prov;
    document.getElementById("nombre").value = p.nom_prov;
    document.getElementById("telefono").value = p.tel_prov;
    document.getElementById("direccion").value = p.dir_prov;
}
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
