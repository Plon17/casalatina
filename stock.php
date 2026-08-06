<?php
$modulo_actual = "stock";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auditoria.php";

$mensaje = "";
$error = "";
$esAdmin = ($_SESSION["rol"] ?? "") === "administrador";

const CANTIDAD_MAX = 500;
const PRECIO_MAX = 100000;

// Valida cantidad y precio dentro de rangos razonables para este negocio.
// Devuelve un mensaje de error, o "" si todo está bien.
function validarCantidadPrecio($cantidad, $precio): string {
    if (!is_numeric($cantidad) || (float) $cantidad < 0 || (float) $cantidad > CANTIDAD_MAX) {
        return "La cantidad debe ser un número entre 0 y " . CANTIDAD_MAX . ".";
    }
    if (!is_numeric($precio) || (float) $precio < 0 || (float) $precio > PRECIO_MAX) {
        return "El precio debe ser un número entre 0 y " . number_format(PRECIO_MAX, 0) . ".";
    }
    return "";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    if (!$esAdmin && in_array($_POST["accion"], ["guardar", "editar", "eliminar", "reactivar"], true)) {
        $error = "Solo un administrador puede modificar el stock.";
        registrarAuditoria($pdo, "stock", "Intento de modificación denegado", "Acción: " . $_POST["accion"] . " (rol: " . ($_SESSION["rol"] ?? "?") . ")");
    } else {

    if ($_POST["accion"] === "guardar") {
        $validacion = validarCantidadPrecio($_POST["cantidad"] ?? null, $_POST["precio"] ?? null);
        if ($validacion !== "") {
            $error = $validacion;
        } else {
            $categoriaFinal = ($_POST["categoria"] === "Otros" && trim($_POST["categoria_otro"] ?? "") !== "")
                ? trim($_POST["categoria_otro"]) : $_POST["categoria"];
            try {
                $stmt = $pdo->prepare("INSERT INTO producto (ID_Producto, nombre_pro, cantidad_pro, precio_pro, categoria_pro, ID_prov)
                                        VALUES (?,?,?,?,?,?)");
                $stmt->execute([
                    $_POST["id_producto"], $_POST["nombre"], $_POST["cantidad"],
                    $_POST["precio"], $categoriaFinal, $_POST["id_proveedor"] ?: null
                ]);
                $mensaje = "Producto agregado.";
                registrarAuditoria($pdo, "stock", "Producto agregado", $_POST["id_producto"] . " - " . $_POST["nombre"] . " (cantidad inicial: " . $_POST["cantidad"] . ")");
            } catch (Exception $e) {
                $error = "Error al agregar el producto (¿el ID ya existe?): " . $e->getMessage();
            }
        }
    }

    if ($_POST["accion"] === "editar") {
        $validacion = validarCantidadPrecio($_POST["cantidad"] ?? null, $_POST["precio"] ?? null);
        if ($validacion !== "") {
            $error = $validacion;
        } else {
            $categoriaFinal = ($_POST["categoria"] === "Otros" && trim($_POST["categoria_otro"] ?? "") !== "")
                ? trim($_POST["categoria_otro"]) : $_POST["categoria"];
            // Traemos cantidad y precio anteriores para poder marcar en la auditoría si
            // alguien los cambió a mano (fuera del flujo normal de Compras)
            $stmtAnterior = $pdo->prepare("SELECT cantidad_pro, precio_pro FROM producto WHERE ID_Producto = ?");
            $stmtAnterior->execute([$_POST["id_producto"]]);
            $anterior = $stmtAnterior->fetch();
            $cantidadAnterior = $anterior["cantidad_pro"] ?? null;
            $precioAnterior = $anterior["precio_pro"] ?? null;

            $stmt = $pdo->prepare("UPDATE producto SET nombre_pro=?, cantidad_pro=?, precio_pro=?, categoria_pro=?, ID_prov=?
                                    WHERE ID_Producto=?");
            $stmt->execute([
                $_POST["nombre"], $_POST["cantidad"], $_POST["precio"],
                $categoriaFinal, $_POST["id_proveedor"] ?: null, $_POST["id_producto"]
            ]);
            $mensaje = "Producto actualizado.";

            $detalleAudit = $_POST["id_producto"] . " - " . $_POST["nombre"];
            if ($cantidadAnterior !== null && (float) $cantidadAnterior !== (float) $_POST["cantidad"]) {
                $detalleAudit .= " — cantidad cambiada manualmente de $cantidadAnterior a " . $_POST["cantidad"];
            }
            if ($precioAnterior !== null && (float) $precioAnterior !== (float) $_POST["precio"]) {
                $detalleAudit .= " — precio cambiado de L. $precioAnterior a L. " . $_POST["precio"];
            }
            registrarAuditoria($pdo, "stock", "Producto editado", $detalleAudit);
        }
    }

    if ($_POST["accion"] === "eliminar") {
        // Ya no se elimina ningún producto, solo se desactiva: se conserva el historial
        // de compras y las recetas que ya lo usan, y deja de ofrecerse para comprar o
        // para armar recetas nuevas.
        $pdo->prepare("UPDATE producto SET activo = 0 WHERE ID_Producto=?")->execute([$_POST["id_producto"]]);

        // Avisamos si algún plato ACTIVO todavía tiene este insumo en su receta,
        // para que decidas si hace falta actualizar esa receta.
        $stmtRecetas = $pdo->prepare("SELECT m.nombre FROM menu_ingredientes mi
                                       JOIN menu m ON m.ID_Menu = mi.ID_Menu
                                       WHERE mi.ID_Producto = ? AND m.activo = 1");
        $stmtRecetas->execute([$_POST["id_producto"]]);
        $platos = array_column($stmtRecetas->fetchAll(PDO::FETCH_ASSOC), "nombre");

        $mensaje = "Producto desactivado: ya no aparece para comprar más ni para armar recetas nuevas.";
        if ($platos) {
            $mensaje .= " Todavía lo usan estos platos activos: " . implode(", ", $platos) . ".";
        }
        registrarAuditoria($pdo, "stock", "Producto desactivado", $_POST["id_producto"]);
    }

    if ($_POST["accion"] === "reactivar") {
        $pdo->prepare("UPDATE producto SET activo = 1 WHERE ID_Producto=?")->execute([$_POST["id_producto"]]);
        $mensaje = "Producto reactivado.";
        registrarAuditoria($pdo, "stock", "Producto reactivado", $_POST["id_producto"]);
    }
    }
}

// ---------- Filtros (cada campo se combina con AND, no se mezclan entre sí) ----------
$buscarId = trim($_GET["buscar_id"] ?? "");
$buscarNombre = trim($_GET["buscar_nombre"] ?? "");
$buscarCategoria = trim($_GET["buscar_categoria"] ?? "");
$filtroProveedor = trim($_GET["proveedor"] ?? "");
$verInactivos = ($_GET["ver"] ?? "") === "inactivos";

$condiciones = ["prod.activo = ?"];
$params = [$verInactivos ? 0 : 1];
if ($buscarId !== "") {
    $condiciones[] = "prod.ID_Producto LIKE ?";
    $params[] = "%$buscarId%";
}
if ($buscarNombre !== "") {
    $condiciones[] = "prod.nombre_pro LIKE ?";
    $params[] = "%$buscarNombre%";
}
if ($buscarCategoria !== "") {
    $condiciones[] = "prod.categoria_pro = ?";
    $params[] = $buscarCategoria;
}
if ($filtroProveedor !== "") {
    $condiciones[] = "prod.ID_prov = ?";
    $params[] = $filtroProveedor;
}
$sql = "SELECT prod.*, pv.nom_prov FROM producto prod LEFT JOIN proveedores pv ON pv.ID_prov = prod.ID_prov"
     . ($condiciones ? " WHERE " . implode(" AND ", $condiciones) : "");
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hayFiltros = $buscarId !== "" || $buscarNombre !== "" || $buscarCategoria !== "" || $filtroProveedor !== "";

// Arma la query string de los filtros actuales (sin "ver"), para que el toggle de
// activos/desactivados no pierda el resto de los filtros al cambiar de vista.
$queryFiltrosActuales = http_build_query(array_filter([
    "buscar_id" => $buscarId, "buscar_nombre" => $buscarNombre, "buscar_categoria" => $buscarCategoria,
    "proveedor" => $filtroProveedor,
], fn($v) => $v !== ""));

// Categorías existentes en la tabla, para el select del filtro
$categoriasExistentes = $pdo->query("SELECT DISTINCT categoria_pro FROM producto WHERE categoria_pro IS NOT NULL AND categoria_pro != '' ORDER BY categoria_pro")->fetchAll(PDO::FETCH_COLUMN);

// Productos con cantidad baja (para el listado de "compras a realizar")
// Los inactivos no entran aquí: ya no se van a comprar más.
$bajos = $pdo->query("SELECT prod.*, pv.nom_prov, pv.tel_prov
                       FROM producto prod
                       LEFT JOIN proveedores pv ON pv.ID_prov = prod.ID_prov
                       WHERE prod.cantidad_pro <= 5 AND prod.activo = 1")->fetchAll(PDO::FETCH_ASSOC);

$proveedores = $pdo->query("SELECT ID_prov, nom_prov FROM proveedores WHERE activo = 1 ORDER BY nom_prov")->fetchAll(PDO::FETCH_ASSOC);

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
.pd-tabla th{background:var(--color-surface-alt);}
.pd-actions{margin-top:14px;display:flex;gap:10px;}
.fila-bajo{background:var(--color-danger-bg);}
.badge-bajo{background:var(--color-danger);color:#fff;padding:1px 6px;border-radius:4px;font-size:12px;margin-left:6px;}
.toggle-inactivos{background:none;border:1px solid #ccc;padding:6px 12px;border-radius:4px;cursor:pointer;text-decoration:none;color:#333;font-size:13px;}
.toggle-inactivos.activo{background:var(--color-surface-alt);font-weight:bold;}
</style>

<p class="titulo-modulo">Inventario General</p>
<?php if ($esAdmin): ?><p><a class="btn" href="compras.php">+ Registrar Compra</a></p><?php endif; ?>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<?php if ($esAdmin): ?>
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
            <label>Cantidad (máx. <?php echo CANTIDAD_MAX; ?>)</label>
            <input type="number" step="0.01" name="cantidad" id="cantidad" min="0" max="<?php echo CANTIDAD_MAX; ?>" required>
        </div>
        <div class="pd-field chico">
            <label>Precio (máx. <?php echo number_format(PRECIO_MAX, 0); ?>)</label>
            <input type="number" step="0.01" name="precio" id="precio" min="0" max="<?php echo PRECIO_MAX; ?>" required>
        </div>
        <div class="pd-field">
            <label>Categoría</label>
            <select name="categoria" id="categoria" onchange="toggleCategoriaOtro()">
                <option value="Carnes y Embutidos">Carnes y Embutidos</option>
                <option value="Lácteos">Lácteos</option>
                <option value="Verduras y Vegetales">Verduras y Vegetales</option>
                <option value="Frutas">Frutas</option>
                <option value="Granos, Cereales y Harinas">Granos, Cereales y Harinas</option>
                <option value="Condimentos y Especias">Condimentos y Especias</option>
                <option value="Bebidas">Bebidas</option>
                <option value="Panadería y Tortillas">Panadería y Tortillas</option>
                <option value="Enlatados y Conservas">Enlatados y Conservas</option>
                <option value="Desechables y Empaques">Desechables y Empaques</option>
                <option value="Otros">Otros</option>
            </select>
        </div>
        <div class="pd-field" id="fila_categoria_otro" style="display:none;">
            <label>Especifica la categoría</label>
            <input type="text" name="categoria_otro" id="categoria_otro" placeholder="Escribe la categoría">
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
<?php else: ?>
<div class="pd-card">
<p style="margin:0; color:#777;">Vista de solo lectura. Solo un administrador puede crear, editar o desactivar productos.</p>
</div>
<?php endif; ?>

<div class="pd-card">
<form method="GET" class="pd-row" style="margin-bottom:15px;">
    <div class="pd-field">
        <label>ID</label>
        <input type="text" name="buscar_id" placeholder="ID exacto o parcial" value="<?php echo htmlspecialchars($buscarId); ?>">
    </div>
    <div class="pd-field">
        <label>Nombre</label>
        <input type="text" name="buscar_nombre" placeholder="Nombre exacto o parcial" value="<?php echo htmlspecialchars($buscarNombre); ?>">
    </div>
    <div class="pd-field">
        <label>Categoría</label>
        <select name="buscar_categoria">
            <option value="">-- todas --</option>
            <?php foreach ($categoriasExistentes as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $buscarCategoria === $cat ? "selected" : ""; ?>>
                <?php echo htmlspecialchars($cat); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pd-field">
        <label>Proveedor</label>
        <select name="proveedor">
            <option value="">-- todos --</option>
            <?php foreach ($proveedores as $pr): ?>
            <option value="<?php echo htmlspecialchars($pr["ID_prov"]); ?>" <?php echo $filtroProveedor === $pr["ID_prov"] ? "selected" : ""; ?>>
                <?php echo htmlspecialchars($pr["ID_prov"] . " - " . $pr["nom_prov"]); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <input type="hidden" name="ver" value="<?php echo $verInactivos ? "inactivos" : ""; ?>">
    <button type="submit">BUSCAR</button>
    <?php if ($hayFiltros): ?><a class="btn" href="stock.php<?php echo $verInactivos ? '?ver=inactivos' : ''; ?>">Limpiar</a><?php endif; ?>
</form>

<div class="pd-row" style="margin-top:2px; margin-bottom:18px; gap:10px;">
    <a class="toggle-inactivos <?php echo !$verInactivos ? 'activo' : ''; ?>" href="stock.php<?php echo $queryFiltrosActuales ? '?' . $queryFiltrosActuales : ''; ?>">Activos</a>
    <a class="toggle-inactivos <?php echo $verInactivos ? 'activo' : ''; ?>" href="stock.php?ver=inactivos<?php echo $queryFiltrosActuales ? '&' . $queryFiltrosActuales : ''; ?>">Ver desactivados</a>
</div>

<table class="pd-tabla">
<tr><th>ID_Producto</th><th>Nombre</th><th>Cantidad</th><th>Precio</th><th>Categoría</th><th>Proveedor</th><th>Estado</th><th></th></tr>
<?php if (count($productos) === 0): ?>
<tr><td colspan="8"><?php echo $verInactivos ? "No hay productos desactivados." : "No se encontraron productos."; ?></td></tr>
<?php endif; ?>
<?php foreach ($productos as $p): ?>
<tr class="<?php echo ($p["cantidad_pro"] <= 5 && $p["activo"]) ? "fila-bajo" : ""; ?>"<?php echo !$p["activo"] ? ' style="opacity:.55;"' : ''; ?>>
    <td><?php echo htmlspecialchars($p["ID_Producto"]); ?></td>
    <td><?php echo htmlspecialchars($p["nombre_pro"]); ?></td>
    <td><?php echo number_format((float) $p["cantidad_pro"], 2); ?><?php if ($p["cantidad_pro"] <= 5 && $p["activo"]): ?><span class="badge-bajo">bajo</span><?php endif; ?></td>
    <td><?php echo number_format((float) $p["precio_pro"], 2); ?></td>
    <td><?php echo htmlspecialchars($p["categoria_pro"]); ?></td>
    <td><?php echo $p["nom_prov"] ? htmlspecialchars($p["nom_prov"]) : '<span style="color:#999;">—</span>'; ?></td>
    <td><?php echo $p["activo"] ? "Activo" : "Inactivo"; ?></td>
    <td>
        <?php if ($esAdmin): ?>
        <button type="button" onclick="cargarFila(<?php echo htmlspecialchars(json_encode($p)); ?>)">EDITAR</button>
        <?php if ($p["activo"]): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Desactivar este producto?');">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id_producto" value="<?php echo htmlspecialchars($p["ID_Producto"]); ?>">
            <button type="submit">DESACTIVAR</button>
        </form>
        <?php else: ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="accion" value="reactivar">
            <input type="hidden" name="id_producto" value="<?php echo htmlspecialchars($p["ID_Producto"]); ?>">
            <button type="submit">REACTIVAR</button>
        </form>
        <?php endif; ?>
        <?php else: ?>
            <span style="color:#999;">—</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="pd-card">
<h3 style="margin-top:0;">Productos con poco stock (posibles compras a realizar)</h3>
<table class="pd-tabla">
<tr><th>ID_Producto</th><th>Nombre</th><th>Cantidad</th><th>Proveedor a contactar</th><th></th></tr>
<?php if (count($bajos) === 0): ?>
<tr><td colspan="5">Todo el inventario está en buen nivel.</td></tr>
<?php endif; ?>
<?php foreach ($bajos as $b): ?>
<tr class="fila-bajo">
    <td><?php echo htmlspecialchars($b["ID_Producto"]); ?></td>
    <td><?php echo htmlspecialchars($b["nombre_pro"]); ?></td>
    <td><?php echo htmlspecialchars($b["cantidad_pro"]); ?></td>
    <td>
        <?php if ($b["nom_prov"]): ?>
            <?php echo htmlspecialchars($b["nom_prov"]); ?><?php echo $b["tel_prov"] ? " — " . htmlspecialchars($b["tel_prov"]) : ""; ?>
        <?php else: ?>
            <span style="color:#999;">sin proveedor asignado</span>
        <?php endif; ?>
    </td>
    <td><a class="btn" href="compras.php?id_producto=<?php echo urlencode($b['ID_Producto']); ?>">Comprar</a></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<?php if ($esAdmin): ?>
<script>
const CATEGORIAS_ESTANDAR = [
    "Carnes y Embutidos", "Lácteos", "Verduras y Vegetales", "Frutas",
    "Granos, Cereales y Harinas", "Condimentos y Especias", "Bebidas",
    "Panadería y Tortillas", "Enlatados y Conservas", "Desechables y Empaques"
];

function toggleCategoriaOtro() {
    const esOtro = document.getElementById("categoria").value === "Otros";
    document.getElementById("fila_categoria_otro").style.display = esOtro ? "flex" : "none";
}

function cargarFila(p) {
    document.getElementById("tituloForm").textContent = "Editando producto " + p.ID_Producto;
    document.getElementById("id_producto").value = p.ID_Producto;
    document.getElementById("id_producto").readOnly = true;
    document.getElementById("nombre").value = p.nombre_pro;
    document.getElementById("cantidad").value = p.cantidad_pro;
    document.getElementById("precio").value = p.precio_pro;

    if (CATEGORIAS_ESTANDAR.includes(p.categoria_pro)) {
        document.getElementById("categoria").value = p.categoria_pro;
        document.getElementById("categoria_otro").value = "";
    } else {
        document.getElementById("categoria").value = "Otros";
        document.getElementById("categoria_otro").value = p.categoria_pro || "";
    }
    toggleCategoriaOtro();

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
    toggleCategoriaOtro();
    document.getElementById("btnGuardar").style.display = "inline-block";
    document.getElementById("btnEditar").style.display = "none";
    document.getElementById("btnNuevo").style.display = "none";
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>