<?php
$modulo_actual = "menu";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$mensaje = "";
$error = "";

function guardarIngredientes(PDO $pdo, string $idMenu, string $json): void {
    $ingredientes = json_decode($json, true) ?: [];
    $pdo->prepare("DELETE FROM menu_ingredientes WHERE ID_Menu = ?")->execute([$idMenu]);
    $stmt = $pdo->prepare("INSERT INTO menu_ingredientes (ID_Menu, ID_Producto, cantidad_necesaria) VALUES (?,?,?)");
    foreach ($ingredientes as $ing) {
        if (!empty($ing["id_producto"]) && (float) ($ing["cantidad"] ?? 0) > 0) {
            $stmt->execute([$idMenu, $ing["id_producto"], $ing["cantidad"]]);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    if ($_POST["accion"] === "guardar") {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO menu (ID_Menu, nombre, precio, tipo, descripcion_men, ID_Producto)
                                    VALUES (?,?,?,?,?,NULL)");
            $stmt->execute([$_POST["id_menu"], $_POST["nombre"], $_POST["precio"], $_POST["tipo"], $_POST["descripcion"]]);
            guardarIngredientes($pdo, $_POST["id_menu"], $_POST["ingredientes_json"] ?? "[]");
            $pdo->commit();
            $mensaje = "Item agregado correctamente.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al agregar (¿el ID ya existe?): " . $e->getMessage();
        }
    }

    if ($_POST["accion"] === "editar") {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE menu SET nombre=?, precio=?, tipo=?, descripcion_men=? WHERE ID_Menu=?");
            $stmt->execute([$_POST["nombre"], $_POST["precio"], $_POST["tipo"], $_POST["descripcion"], $_POST["id_menu"]]);
            guardarIngredientes($pdo, $_POST["id_menu"], $_POST["ingredientes_json"] ?? "[]");
            $pdo->commit();
            $mensaje = "Item actualizado correctamente.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }

    if ($_POST["accion"] === "eliminar") {
        // menu_ingredientes tiene ON DELETE CASCADE, así que su receta se borra sola
        $stmt = $pdo->prepare("DELETE FROM menu WHERE ID_Menu=?");
        $stmt->execute([$_POST["id_menu"]]);
        $mensaje = "Item eliminado.";
    }
}

$buscar = trim($_GET["buscar"] ?? "");
if ($buscar !== "") {
    $stmt = $pdo->prepare("SELECT * FROM menu WHERE nombre LIKE ? OR ID_Menu LIKE ?");
    $stmt->execute(["%$buscar%", "%$buscar%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM menu");
}
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Adjuntamos la receta de cada plato (para mostrarla y para poder editarla)
$ingStmt = $pdo->prepare("SELECT i.ID_Producto, i.cantidad_necesaria, p.nombre_pro
                           FROM menu_ingredientes i JOIN producto p ON p.ID_Producto = i.ID_Producto
                           WHERE i.ID_Menu = ?");
foreach ($items as &$it) {
    $ingStmt->execute([$it["ID_Menu"]]);
    $it["ingredientes"] = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($it);

// Productos de stock disponibles para armar recetas
$productos = $pdo->query("SELECT ID_Producto, nombre_pro, cantidad_pro FROM producto")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "MENÚ";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input,.pd-field select{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:150px;}
.pd-field.chico input{min-width:70px;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:14px;}
.pd-tabla th{background:#f5f5f5;}
.pd-resultados{max-height:150px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;}
.pd-actions{margin-top:14px;display:flex;gap:10px;}
.receta-badge{background:#e8f4ea;color:#2f6b3d;padding:1px 8px;border-radius:10px;font-size:12px;}
.receta-vacia{background:#f5f5f5;color:#888;padding:1px 8px;border-radius:10px;font-size:12px;}
</style>

<p class="titulo-modulo">Menú</p>

<form method="GET" class="pd-row" style="margin-bottom:15px;">
    <div class="pd-field" style="flex:1; min-width:220px;">
        <label>Buscar</label>
        <input type="text" name="buscar" placeholder="Buscar por nombre o ID" value="<?php echo htmlspecialchars($buscar); ?>">
    </div>
    <button type="submit">BUSCAR</button>
    <?php if ($buscar): ?><a href="menu.php" style="align-self:center;">Limpiar</a><?php endif; ?>
</form>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="pd-card">
<table class="pd-tabla">
<tr><th>ID_Menu</th><th>Nombre</th><th>Precio</th><th>Tipo</th><th>Descripción</th><th>Receta</th><th></th></tr>
<?php if (count($items) === 0): ?>
<tr><td colspan="7">No se encontraron items.</td></tr>
<?php endif; ?>
<?php foreach ($items as $it): ?>
<tr>
    <td><?php echo htmlspecialchars($it["ID_Menu"]); ?></td>
    <td><?php echo htmlspecialchars($it["nombre"]); ?></td>
    <td><?php echo number_format((float) $it["precio"], 2); ?></td>
    <td><?php echo htmlspecialchars($it["tipo"]); ?></td>
    <td><?php echo htmlspecialchars($it["descripcion_men"]); ?></td>
    <td>
        <?php if (count($it["ingredientes"]) > 0): ?>
            <span class="receta-badge" title="<?php
                echo htmlspecialchars(implode(", ", array_map(
                    fn($i) => $i["nombre_pro"] . " x" . $i["cantidad_necesaria"],
                    $it["ingredientes"]
                )));
            ?>"><?php echo count($it["ingredientes"]); ?> insumo(s)</span>
        <?php else: ?>
            <span class="receta-vacia">sin receta</span>
        <?php endif; ?>
    </td>
    <td>
        <button type="button" onclick="cargarFila(<?php echo htmlspecialchars(json_encode($it)); ?>)">EDITAR</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este item? También se borra su receta.');">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id_menu" value="<?php echo htmlspecialchars($it["ID_Menu"]); ?>">
            <button type="submit">ELIMINAR</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="pd-card">
<h3 style="margin-top:0;" id="tituloForm">Agregar item nuevo</h3>
<form method="POST" id="formMenu">
    <input type="hidden" name="accion" id="accion" value="guardar">
    <input type="hidden" name="ingredientes_json" id="ingredientes_json">

    <div class="pd-row">
        <div class="pd-field">
            <label>ID Menú</label>
            <input type="text" name="id_menu" id="id_menu" required>
        </div>
        <div class="pd-field" style="flex:1; min-width:180px;">
            <label>Nombre</label>
            <input type="text" name="nombre" id="nombre" required>
        </div>
        <div class="pd-field chico">
            <label>Precio</label>
            <input type="number" step="0.01" name="precio" id="precio" required>
        </div>
        <div class="pd-field">
            <label>Tipo</label>
            <select name="tipo" id="tipo" required>
                <option value="Platillo">Platillo</option>
                <option value="Bebida">Bebida</option>
            </select>
        </div>
        <div class="pd-field" style="flex:1; min-width:200px;">
            <label>Descripción</label>
            <input type="text" name="descripcion" id="descripcion">
        </div>
    </div>

    <hr style="margin:18px 0; border:none; border-top:1px solid #eee;">

    <h4 style="margin:0 0 8px 0;">Receta (insumos de stock que usa este plato)</h4>
    <div class="pd-row">
        <div class="pd-field" style="flex:1; min-width:220px;">
            <label>Buscar insumo</label>
            <input type="text" id="buscar_insumo" placeholder="Nombre o ID" autocomplete="off">
        </div>
        <div class="pd-field chico">
            <label>Cantidad usada</label>
            <input type="number" step="0.01" id="cantidad_insumo" value="1" min="0.01">
        </div>
        <button type="button" onclick="agregarIngrediente()">Agregar a la receta</button>
    </div>

    <div class="pd-resultados" style="margin-top:10px;">
        <table class="pd-tabla" id="tablaResultadosInsumo">
        <tr><th>ID</th><th>Nombre</th><th>Stock actual</th></tr>
        </table>
    </div>

    <table class="pd-tabla" id="tablaReceta" style="margin-top:12px;">
    <tr><th>Insumo</th><th>Cantidad necesaria</th><th></th></tr>
    </table>

    <div class="pd-actions">
        <button type="submit" id="btnGuardar" onclick="return prepararEnvio('guardar')">GUARDAR</button>
        <button type="submit" id="btnEditar" style="display:none;" onclick="return prepararEnvio('editar')">GUARDAR EDICIÓN</button>
        <button type="button" id="btnNuevo" style="display:none;" onclick="nuevoItem()">+ NUEVO ITEM</button>
    </div>
</form>
</div>

<script>
const productos = <?php echo json_encode($productos); ?>;
let idInsumoSeleccionado = "";
let nombreInsumoSeleccionado = "";
let receta = [];

document.getElementById("buscar_insumo").addEventListener("keyup", function () {
    const texto = this.value.toLowerCase();
    const tabla = document.getElementById("tablaResultadosInsumo");
    tabla.innerHTML = "<tr><th>ID</th><th>Nombre</th><th>Stock actual</th></tr>";
    if (texto === "") return;

    productos.filter(p => p.nombre_pro.toLowerCase().includes(texto) || p.ID_Producto.toLowerCase().includes(texto))
        .forEach(p => {
            const fila = tabla.insertRow();
            fila.style.cursor = "pointer";
            fila.innerHTML = `<td>${p.ID_Producto}</td><td>${p.nombre_pro}</td><td>${p.cantidad_pro}</td>`;
            fila.onclick = () => {
                idInsumoSeleccionado = p.ID_Producto;
                nombreInsumoSeleccionado = p.nombre_pro;
                document.getElementById("buscar_insumo").value = p.nombre_pro + " (" + p.ID_Producto + ")";
                tabla.innerHTML = "<tr><th>ID</th><th>Nombre</th><th>Stock actual</th></tr>";
            };
        });
});

function agregarIngrediente() {
    const cantidad = parseFloat(document.getElementById("cantidad_insumo").value);
    if (!idInsumoSeleccionado || isNaN(cantidad) || cantidad <= 0) {
        alert("Busca y selecciona un insumo, y pon una cantidad válida.");
        return;
    }
    if (receta.some(r => r.id_producto === idInsumoSeleccionado)) {
        alert("Ese insumo ya está en la receta. Quítalo primero si quieres cambiar la cantidad.");
        return;
    }
    receta.push({ id_producto: idInsumoSeleccionado, nombre: nombreInsumoSeleccionado, cantidad: cantidad });
    idInsumoSeleccionado = "";
    document.getElementById("buscar_insumo").value = "";
    document.getElementById("cantidad_insumo").value = 1;
    renderReceta();
}

function quitarIngrediente(idx) {
    receta.splice(idx, 1);
    renderReceta();
}

function renderReceta() {
    const tabla = document.getElementById("tablaReceta");
    tabla.innerHTML = "<tr><th>Insumo</th><th>Cantidad necesaria</th><th></th></tr>";
    if (receta.length === 0) {
        const fila = tabla.insertRow();
        fila.innerHTML = `<td colspan="3">Sin insumos agregados (el plato no descontará stock automáticamente).</td>`;
        return;
    }
    receta.forEach((r, idx) => {
        const fila = tabla.insertRow();
        fila.innerHTML = `<td>${r.nombre}</td><td>${r.cantidad}</td><td><button type="button" onclick="quitarIngrediente(${idx})">Quitar</button></td>`;
    });
}

function prepararEnvio(accion) {
    document.getElementById("accion").value = accion;
    document.getElementById("ingredientes_json").value = JSON.stringify(receta);
    return true;
}

function cargarFila(item) {
    document.getElementById("tituloForm").textContent = "Editando " + item.ID_Menu;
    document.getElementById("id_menu").value = item.ID_Menu;
    document.getElementById("id_menu").readOnly = true;
    document.getElementById("nombre").value = item.nombre;
    document.getElementById("precio").value = item.precio;
    document.getElementById("tipo").value = item.tipo;
    document.getElementById("descripcion").value = item.descripcion_men;

    receta = (item.ingredientes || []).map(i => ({
        id_producto: i.ID_Producto, nombre: i.nombre_pro, cantidad: parseFloat(i.cantidad_necesaria)
    }));
    renderReceta();

    document.getElementById("btnGuardar").style.display = "none";
    document.getElementById("btnEditar").style.display = "inline-block";
    document.getElementById("btnNuevo").style.display = "inline-block";
    document.getElementById("formMenu").scrollIntoView({ behavior: "smooth", block: "center" });
}

function nuevoItem() {
    document.getElementById("tituloForm").textContent = "Agregar item nuevo";
    document.getElementById("formMenu").reset();
    document.getElementById("id_menu").readOnly = false;
    receta = [];
    renderReceta();
    document.getElementById("btnGuardar").style.display = "inline-block";
    document.getElementById("btnEditar").style.display = "none";
    document.getElementById("btnNuevo").style.display = "none";
}

renderReceta();
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>