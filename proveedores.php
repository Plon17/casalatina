<?php
$modulo_actual = "prov";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auditoria.php";

$mensaje = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    if ($_POST["accion"] === "guardar") {
        $n = (int) $pdo->query("SELECT COUNT(*) AS c FROM proveedores")->fetch()["c"] + 1;
        $idProv = "PV" . str_pad($n, 4, "0", STR_PAD_LEFT);
        $stmt = $pdo->prepare("INSERT INTO proveedores (ID_prov, nom_prov, tel_prov, dir_prov) VALUES (?,?,?,?)");
        $stmt->execute([$idProv, $_POST["nombre"], $_POST["telefono"] ?: null, $_POST["direccion"] ?: null]);
        $mensaje = "Proveedor agregado ($idProv).";
    }

    if ($_POST["accion"] === "editar") {
        $stmt = $pdo->prepare("UPDATE proveedores SET nom_prov=?, tel_prov=?, dir_prov=? WHERE ID_prov=?");
        $stmt->execute([$_POST["nombre"], $_POST["telefono"] ?: null, $_POST["direccion"] ?: null, $_POST["id_prov"]]);
        $mensaje = "Proveedor actualizado.";
    }

    if ($_POST["accion"] === "eliminar") {
        // producto.ID_prov referencia esta tabla: si algún insumo todavía le compra
        // a este proveedor, lo desactivamos en vez de borrarlo (igual que en Stock/Menú).
        $stmtProds = $pdo->prepare("SELECT nombre_pro FROM producto WHERE ID_prov = ?");
        $stmtProds->execute([$_POST["id_prov"]]);
        $productos = array_column($stmtProds->fetchAll(PDO::FETCH_ASSOC), "nombre_pro");

        if ($productos) {
            $pdo->prepare("UPDATE proveedores SET activo = 0 WHERE ID_prov=?")->execute([$_POST["id_prov"]]);
            $mensaje = "Este proveedor todavía es el asignado de " . count($productos) . " producto(s) en Stock (" . implode(", ", $productos) . "). Se marcó como inactivo";
            registrarAuditoria($pdo, "proveedores", "Proveedor desactivado", $_POST["id_prov"]);
        } else {
            $pdo->prepare("DELETE FROM proveedores WHERE ID_prov=?")->execute([$_POST["id_prov"]]);
            $mensaje = "Proveedor eliminado.";
            registrarAuditoria($pdo, "proveedores", "Proveedor eliminado", $_POST["id_prov"]);
        }
    }

    if ($_POST["accion"] === "reactivar") {
        $pdo->prepare("UPDATE proveedores SET activo = 1 WHERE ID_prov=?")->execute([$_POST["id_prov"]]);
        $mensaje = "Proveedor reactivado.";
        registrarAuditoria($pdo, "proveedores", "Proveedor reactivado", $_POST["id_prov"]);
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

// Cuántos productos de Stock tiene asignados cada proveedor
$conteoStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM producto WHERE ID_prov = ?");
foreach ($proveedores as &$p) {
    $conteoStmt->execute([$p["ID_prov"]]);
    $p["num_productos"] = (int) $conteoStmt->fetch()["c"];
}
unset($p);

$titulo_pagina = "PROVEEDORES";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:160px;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:14px;}
.pd-tabla th{background:var(--color-surface-alt);}
.pd-actions{margin-top:14px;display:flex;gap:10px;}
.badge-prod{background:var(--color-info-bg);color:var(--color-info);padding:1px 8px;border-radius:10px;font-size:12px;}
.badge-sin{background:var(--color-surface-alt);color:#999;padding:1px 8px;border-radius:10px;font-size:12px;}
</style>

<p class="titulo-modulo">Proveedores</p>

<form method="GET" class="pd-row" style="margin-bottom:15px;">
    <div class="pd-field" style="flex:1; min-width:220px;">
        <label>Buscar</label>
        <input type="text" name="buscar" placeholder="Buscar por nombre o ID" value="<?php echo htmlspecialchars($buscar); ?>">
    </div>
    <button type="submit">BUSCAR</button>
    <?php if ($buscar): ?><a href="proveedores.php" style="align-self:center;">Limpiar</a><?php endif; ?>
</form>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="pd-card">
<table class="pd-tabla">
<tr><th>ID</th><th>Nombre</th><th>Teléfono</th><th>Dirección</th><th>Productos</th><th>Estado</th><th></th></tr>
<?php if (count($proveedores) === 0): ?>
<tr><td colspan="7">No hay proveedores registrados.</td></tr>
<?php endif; ?>
<?php foreach ($proveedores as $p): ?>
<tr<?php echo !$p["activo"] ? ' style="opacity:.55;"' : ''; ?>>
    <td><?php echo htmlspecialchars($p["ID_prov"]); ?></td>
    <td><?php echo htmlspecialchars($p["nom_prov"]); ?></td>
    <td><?php echo htmlspecialchars($p["tel_prov"]); ?></td>
    <td><?php echo htmlspecialchars($p["dir_prov"]); ?></td>
    <td>
        <?php if ($p["num_productos"] > 0): ?>
            <span class="badge-prod"><?php echo $p["num_productos"]; ?> producto(s)</span>
        <?php else: ?>
            <span class="badge-sin">ninguno</span>
        <?php endif; ?>
    </td>
    <td><?php echo $p["activo"] ? "Activo" : "Inactivo"; ?></td>
    <td>
        <button type="button" onclick="cargarFila(<?php echo htmlspecialchars(json_encode($p)); ?>)">EDITAR</button>
        <?php if ($p["activo"]): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este proveedor?');">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id_prov" value="<?php echo htmlspecialchars($p["ID_prov"]); ?>">
            <button type="submit">DESACTIVAR</button>
        </form>
        <?php else: ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="accion" value="reactivar">
            <input type="hidden" name="id_prov" value="<?php echo htmlspecialchars($p["ID_prov"]); ?>">
            <button type="submit">REACTIVAR</button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="pd-card">
<h3 style="margin-top:0;" id="tituloForm">Agregar proveedor nuevo</h3>
<form method="POST" id="formProv">
    <input type="hidden" name="accion" id="accion" value="guardar">
    <input type="hidden" name="id_prov" id="id_prov">

    <div class="pd-row">
        <div class="pd-field">
            <label>Nombre</label>
            <input type="text" name="nombre" id="nombre" required>
        </div>
        <div class="pd-field">
            <label>Teléfono</label>
            <input type="text" name="telefono" id="telefono">
        </div>
        <div class="pd-field" style="flex:1; min-width:220px;">
            <label>Dirección</label>
            <input type="text" name="direccion" id="direccion">
        </div>
    </div>

    <div class="pd-actions">
        <button type="submit" id="btnGuardar" onclick="document.getElementById('accion').value='guardar'">GUARDAR</button>
        <button type="submit" id="btnEditar" style="display:none;" onclick="document.getElementById('accion').value='editar'">GUARDAR EDICIÓN</button>
        <button type="button" id="btnNuevo" style="display:none;" onclick="nuevoProveedor()">+ NUEVO PROVEEDOR</button>
    </div>
</form>
</div>

<script>
function cargarFila(p) {
    document.getElementById("tituloForm").textContent = "Editando " + p.ID_prov;
    document.getElementById("id_prov").value = p.ID_prov;
    document.getElementById("nombre").value = p.nom_prov;
    document.getElementById("telefono").value = p.tel_prov;
    document.getElementById("direccion").value = p.dir_prov;
    document.getElementById("btnGuardar").style.display = "none";
    document.getElementById("btnEditar").style.display = "inline-block";
    document.getElementById("btnNuevo").style.display = "inline-block";
    document.getElementById("formProv").scrollIntoView({ behavior: "smooth", block: "center" });
}

function nuevoProveedor() {
    document.getElementById("tituloForm").textContent = "Agregar proveedor nuevo";
    document.getElementById("formProv").reset();
    document.getElementById("id_prov").value = "";
    document.getElementById("btnGuardar").style.display = "inline-block";
    document.getElementById("btnEditar").style.display = "none";
    document.getElementById("btnNuevo").style.display = "none";
}
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>