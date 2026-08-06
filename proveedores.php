<?php
$modulo_actual = "prov";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auditoria.php";

$mensaje = "";
$error = "";

// Teléfono: solo dígitos, exactamente 8 (formato Honduras). Vacío es válido (es opcional).
function validarTelefono(string $tel): bool {
    $tel = trim($tel);
    return $tel === "" || preg_match('/^\d{8}$/', $tel) === 1;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    $telefono = trim($_POST["telefono"] ?? "");
    if (in_array($_POST["accion"], ["guardar", "editar"], true) && !validarTelefono($telefono)) {
        $error = "El teléfono debe tener exactamente 8 dígitos numéricos (sin espacios, guiones ni letras).";
    } else {

    if ($_POST["accion"] === "guardar") {
        $max = (int) $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTR(ID_prov,3) AS INTEGER)),0) AS m FROM proveedores")->fetch()["m"];
        $idProv = "PV" . str_pad($max + 1, 4, "0", STR_PAD_LEFT);
        $stmt = $pdo->prepare("INSERT INTO proveedores (ID_prov, nom_prov, tel_prov, dir_prov, email_prov) VALUES (?,?,?,?,?)");
        $stmt->execute([$idProv, $_POST["nombre"], $telefono ?: null, $_POST["direccion"] ?: null, $_POST["email"] ?: null]);
        $mensaje = "Proveedor agregado ($idProv).";
        registrarAuditoria($pdo, "proveedores", "Proveedor agregado", "$idProv - " . $_POST["nombre"]);
    }

    if ($_POST["accion"] === "editar") {
        $stmt = $pdo->prepare("UPDATE proveedores SET nom_prov=?, tel_prov=?, dir_prov=?, email_prov=? WHERE ID_prov=?");
        $stmt->execute([$_POST["nombre"], $telefono ?: null, $_POST["direccion"] ?: null, $_POST["email"] ?: null, $_POST["id_prov"]]);
        $mensaje = "Proveedor actualizado.";
        registrarAuditoria($pdo, "proveedores", "Proveedor editado", $_POST["id_prov"]);
    }

    if ($_POST["accion"] === "desactivar") {
        // Ya no se elimina ningún proveedor, solo se desactiva: así deja de aparecer
        // como opción al asignar proveedor a un producto en otros módulos, pero se
        // conserva la referencia histórica (compras, productos ya asignados, etc).
        $pdo->prepare("UPDATE proveedores SET activo = 0 WHERE ID_prov=?")->execute([$_POST["id_prov"]]);
        $mensaje = "Proveedor desactivado: ya no aparece como opción al asignar proveedor a un producto.";
        registrarAuditoria($pdo, "proveedores", "Proveedor desactivado", $_POST["id_prov"]);
    }

    if ($_POST["accion"] === "reactivar") {
        $pdo->prepare("UPDATE proveedores SET activo = 1 WHERE ID_prov=?")->execute([$_POST["id_prov"]]);
        $mensaje = "Proveedor reactivado.";
        registrarAuditoria($pdo, "proveedores", "Proveedor reactivado", $_POST["id_prov"]);
    }
    }
}

$buscar = trim($_GET["buscar"] ?? "");
$verInactivos = ($_GET["ver"] ?? "") === "inactivos";

if ($buscar !== "") {
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE activo = ? AND (nom_prov LIKE ? OR ID_prov LIKE ?)");
    $stmt->execute([$verInactivos ? 0 : 1, "%$buscar%", "%$buscar%"]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE activo = ?");
    $stmt->execute([$verInactivos ? 0 : 1]);
}
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cuántos productos de Stock tiene asignados cada proveedor, y cuánto se le ha comprado
$conteoStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM producto WHERE ID_prov = ?");
$comprasStmt = $pdo->prepare("SELECT COALESCE(SUM(monto_total),0) AS total, COUNT(*) AS c FROM compras WHERE ID_prov = ?");
foreach ($proveedores as &$p) {
    $conteoStmt->execute([$p["ID_prov"]]);
    $p["num_productos"] = (int) $conteoStmt->fetch()["c"];

    $comprasStmt->execute([$p["ID_prov"]]);
    $comprasFila = $comprasStmt->fetch();
    $p["total_comprado"] = (float) $comprasFila["total"];
    $p["num_compras"] = (int) $comprasFila["c"];
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
.toggle-inactivos{background:none;border:1px solid #ccc;padding:6px 12px;border-radius:4px;cursor:pointer;text-decoration:none;color:#333;font-size:13px;}
.toggle-inactivos.activo{background:var(--color-surface-alt);font-weight:bold;}
</style>

<p class="titulo-modulo">Proveedores</p>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

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
            <input type="text" name="telefono" id="telefono" inputmode="numeric" pattern="\d{8}" maxlength="8"
                   placeholder="8 dígitos" title="Debe tener exactamente 8 dígitos numéricos">
        </div>
        <div class="pd-field">
            <label>Correo electrónico</label>
            <input type="email" name="email" id="email" placeholder="proveedor@correo.com">
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

<div class="pd-card">
<form method="GET" class="pd-row" style="margin-bottom:15px;">
    <div class="pd-field" style="flex:1; min-width:220px;">
        <label>Buscar</label>
        <input type="text" name="buscar" placeholder="Buscar por nombre o ID" value="<?php echo htmlspecialchars($buscar); ?>">
    </div>
    <input type="hidden" name="ver" value="<?php echo $verInactivos ? "inactivos" : ""; ?>">
    <button type="submit">BUSCAR</button>
    <?php if ($buscar): ?><a href="proveedores.php<?php echo $verInactivos ? '?ver=inactivos' : ''; ?>" class="btn">Limpiar</a><?php endif; ?>
    <div style="flex:1;"></div>
    <a class="toggle-inactivos <?php echo !$verInactivos ? 'activo' : ''; ?>" href="proveedores.php<?php echo $buscar ? '?buscar=' . urlencode($buscar) : ''; ?>">Activos</a>
    <a class="toggle-inactivos <?php echo $verInactivos ? 'activo' : ''; ?>" href="proveedores.php?ver=inactivos<?php echo $buscar ? '&buscar=' . urlencode($buscar) : ''; ?>">Ver desactivados</a>
</form>

<table class="pd-tabla">
<tr><th>ID</th><th>Nombre</th><th>Teléfono</th><th>Correo</th><th>Dirección</th><th>Productos</th><th>Total comprado</th><th>Estado</th><th></th></tr>
<?php if (count($proveedores) === 0): ?>
<tr><td colspan="9"><?php echo $verInactivos ? "No hay proveedores desactivados." : "No hay proveedores registrados."; ?></td></tr>
<?php endif; ?>
<?php foreach ($proveedores as $p): ?>
<tr<?php echo !$p["activo"] ? ' style="opacity:.55;"' : ''; ?>>
    <td><?php echo htmlspecialchars($p["ID_prov"]); ?></td>
    <td><?php echo htmlspecialchars($p["nom_prov"]); ?></td>
    <td><?php echo htmlspecialchars($p["tel_prov"]); ?></td>
    <td><?php echo htmlspecialchars($p["email_prov"] ?? ""); ?></td>
    <td><?php echo htmlspecialchars($p["dir_prov"]); ?></td>
    <td>
        <?php if ($p["num_productos"] > 0): ?>
            <span class="badge-prod"><?php echo $p["num_productos"]; ?> producto(s)</span>
        <?php else: ?>
            <span class="badge-sin">ninguno</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($p["num_compras"] > 0): ?>
            L. <?php echo number_format($p["total_comprado"], 2); ?> <span style="color:#888; font-size:12px;">(<?php echo $p["num_compras"]; ?>)</span>
        <?php else: ?>
            <span style="color:#999;">—</span>
        <?php endif; ?>
    </td>
    <td><?php echo $p["activo"] ? "Activo" : "Inactivo"; ?></td>
    <td>
        <button type="button" onclick="cargarFila(<?php echo htmlspecialchars(json_encode($p)); ?>)">EDITAR</button>
        <?php if ($p["activo"]): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('¿Desactivar este proveedor? Ya no aparecerá como opción al asignar proveedor a un producto.');">
            <input type="hidden" name="accion" value="desactivar">
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

<script>
function cargarFila(p) {
    document.getElementById("tituloForm").textContent = "Editando " + p.ID_prov;
    document.getElementById("id_prov").value = p.ID_prov;
    document.getElementById("nombre").value = p.nom_prov;
    document.getElementById("telefono").value = p.tel_prov;
    document.getElementById("email").value = p.email_prov || "";
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

// Filtra teclas al vuelo: solo dígitos, máximo 8 caracteres
document.getElementById("telefono").addEventListener("input", function () {
    this.value = this.value.replace(/\D/g, "").slice(0, 8);
});
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>