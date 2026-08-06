<?php
$modulo_actual = "compras";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auditoria.php";

$mensaje = "";
$error = "";

// Mensaje de éxito que quedó guardado en sesión tras el redirect tras registrar una compra
if (!empty($_SESSION["flash_mensaje"])) {
    $mensaje = $_SESSION["flash_mensaje"];
    unset($_SESSION["flash_mensaje"]);
}

const CANTIDAD_MAX = 500;
const PRECIO_MAX = 100000;

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar") {

    $productoNuevo = ($_POST["producto_nuevo"] ?? "0") === "1";
    $cantidad = (float) ($_POST["cantidad"] ?? 0);
    $monto = $_POST["monto"] ?? "";
    $idProv = $_POST["id_prov"] ?: null;
    $idProducto = "";
    $nombreNuevo = "";
    $precioNuevo = null;
    $categoriaNueva = "";

    if ($cantidad <= 0) {
        $error = "Pon una cantidad válida.";
    } elseif ($cantidad > CANTIDAD_MAX) {
        $error = "La cantidad (" . $cantidad . ") supera el máximo permitido por compra (" . CANTIDAD_MAX . " unidades). Si necesitas más, regístrala en varias compras.";
    } elseif ($productoNuevo) {
        // ---------- Producto nuevo: se crea en Stock aquí mismo antes de registrar la compra ----------
        $idProducto = trim($_POST["nuevo_id_producto"] ?? "");
        $nombreNuevo = trim($_POST["nuevo_nombre"] ?? "");
        $precioNuevo = $_POST["nuevo_precio"] ?? "";
        $categoriaNueva = ($_POST["nuevo_categoria"] === "Otros" && trim($_POST["nuevo_categoria_otro"] ?? "") !== "")
            ? trim($_POST["nuevo_categoria_otro"]) : ($_POST["nuevo_categoria"] ?? "");

        if ($idProducto === "" || $nombreNuevo === "") {
            $error = "Completa el ID y el nombre del producto nuevo.";
        } elseif (!is_numeric($precioNuevo) || (float) $precioNuevo < 0 || (float) $precioNuevo > PRECIO_MAX) {
            $error = "El precio del producto nuevo debe ser un número entre 0 y " . number_format(PRECIO_MAX, 0) . ".";
        } else {
            $stmtExiste = $pdo->prepare("SELECT 1 FROM producto WHERE ID_Producto = ?");
            $stmtExiste->execute([$idProducto]);
            if ($stmtExiste->fetch()) {
                $error = "Ya existe un producto con el ID \"" . htmlspecialchars($idProducto) . "\". Usa uno distinto, o búscalo en \"Producto existente\" si ya está en Stock.";
            }
        }
    } else {
        // ---------- Producto que ya existe en Stock (flujo de siempre) ----------
        $idProducto = $_POST["id_producto"] ?? "";
        if (!$idProducto) {
            $error = "Selecciona un producto (usando el buscador) o registra uno nuevo.";
        }
    }

    if ($error === "") {
        $pdo->beginTransaction();
        try {
            if ($productoNuevo) {
                $stmtProd = $pdo->prepare("INSERT INTO producto (ID_Producto, nombre_pro, cantidad_pro, precio_pro, categoria_pro, ID_prov)
                                            VALUES (?,?,0,?,?,?)");
                $stmtProd->execute([$idProducto, $nombreNuevo, $precioNuevo, $categoriaNueva, $idProv]);
                registrarAuditoria($pdo, "stock", "Producto agregado (desde Compras)", "$idProducto - $nombreNuevo");
            }

            $n = (int) $pdo->query("SELECT COUNT(*) AS c FROM compras")->fetch()["c"] + 1;
            $idCompra = "C" . str_pad($n, 6, "0", STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO compras (ID_compras, ID_Producto, fecha, cantidad, monto_total, ID_prov)
                                    VALUES (?,?,?,?,?,?)");
            $stmt->execute([$idCompra, $idProducto, date("Y-m-d"), $cantidad, $monto, $idProv]);

            // Conectamos automáticamente la compra con el stock
            $stmt2 = $pdo->prepare("UPDATE producto SET cantidad_pro = cantidad_pro + ? WHERE ID_Producto = ?");
            $stmt2->execute([$cantidad, $idProducto]);

            $pdo->commit();
            $_SESSION["flash_mensaje"] = "Compra #$idCompra registrada."
                . ($productoNuevo ? " Producto \"$nombreNuevo\" ($idProducto) creado en Stock." : "")
                . " Stock actualizado (+$cantidad unidades).";
            registrarAuditoria($pdo, "compras", "Compra registrada", "$idCompra — $idProducto x$cantidad (L. $monto)" . ($idProv ? " — proveedor: $idProv" : ""));

            // Redirigimos a la URL limpia (sin ?id_producto=...): si no, al recargar la
            // misma página el producto se preselecciona otra vez y es fácil registrar
            // la compra dos veces sin querer.
            header("Location: compras.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al registrar la compra: " . $e->getMessage();
        }
    }
}

// Si viene ?id_producto=... (por ejemplo desde el aviso de stock bajo), lo dejamos
// listo para que el JS lo preseleccione al cargar la página.
$idProductoPrefill = trim($_GET["id_producto"] ?? "");

$productos = $pdo->query("SELECT ID_Producto, nombre_pro, precio_pro, cantidad_pro, ID_prov FROM producto WHERE activo = 1")->fetchAll(PDO::FETCH_ASSOC);
$proveedores = $pdo->query("SELECT ID_prov, nom_prov FROM proveedores WHERE activo = 1 ORDER BY nom_prov")->fetchAll(PDO::FETCH_ASSOC);

$compras = $pdo->query("SELECT c.*, p.nombre_pro, pv.nom_prov
                         FROM compras c
                         LEFT JOIN producto p ON p.ID_Producto = c.ID_Producto
                         LEFT JOIN proveedores pv ON pv.ID_prov = c.ID_prov
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
.pd-tabla th{background:var(--color-surface-alt);}
.pd-resultados{max-height:150px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;}
.pd-actions{margin-top:14px;display:flex;gap:10px;}
.bloque-nuevo{background:var(--color-primary-light);border:1px dashed var(--color-primary);border-radius:8px;padding:14px 16px;margin-top:10px;}
.link-toggle{background:none;border:none;color:var(--color-primary-dark);text-decoration:underline;cursor:pointer;font-size:13px;font-weight:bold;padding:0;}
</style>

<p class="titulo-modulo">Compras</p>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="pd-card">
<h3 style="margin-top:0;">Registrar compra</h3>
<form method="POST" id="formCompra">
    <input type="hidden" name="accion" value="registrar">
    <input type="hidden" name="id_producto" id="id_producto">
    <input type="hidden" name="producto_nuevo" id="producto_nuevo" value="0">

    <!-- ---------- Producto existente (flujo de siempre) ---------- -->
    <div id="bloqueExistente">
        <div class="pd-row">
            <div class="pd-field" style="flex:1; min-width:220px;">
                <label>Buscar producto</label>
                <input type="text" id="buscar_producto" placeholder="Nombre o ID" autocomplete="off">
            </div>
            <div class="pd-field">
                <label>Fecha</label>
                <input type="date" value="<?php echo date('Y-m-d'); ?>" readonly>
            </div>
        </div>

        <div class="pd-resultados" style="margin-top:10px;">
            <table class="pd-tabla" id="tablaResultados">
            <tr><th>ID</th><th>Nombre</th><th>Precio ref.</th><th>Stock actual</th></tr>
            </table>
        </div>

        <div class="pd-row" style="margin-top:10px;">
            <div class="pd-field" style="flex:1;"><label>Producto seleccionado</label><input type="text" id="nombre_producto" readonly></div>
        </div>

        <p style="margin:10px 0 0 0;">
            ¿No está en la lista?
            <button type="button" class="link-toggle" onclick="activarModoNuevo()">+ Nuevo producto</button>
        </p>
    </div>

    <!-- ---------- Producto nuevo (se crea en Stock al registrar la compra) ---------- -->
    <div id="bloqueNuevo" class="bloque-nuevo" style="display:none;">
        <p style="margin:0 0 10px 0; font-weight:bold; color:var(--color-primary-dark);">Producto nuevo (se agregará a Stock)</p>
        <div class="pd-row">
            <div class="pd-field">
                <label>ID Producto</label>
                <input type="text" id="nuevo_id_producto" name="nuevo_id_producto" autocomplete="off">
            </div>
            <div class="pd-field" style="flex:1; min-width:180px;">
                <label>Nombre</label>
                <input type="text" id="nuevo_nombre" name="nuevo_nombre">
            </div>
            <div class="pd-field chico">
                <label>Precio ref. (máx. <?php echo number_format(PRECIO_MAX, 0); ?>)</label>
                <input type="number" step="0.01" id="nuevo_precio" name="nuevo_precio" min="0" max="<?php echo PRECIO_MAX; ?>" oninput="usarPrecioNuevo()">
            </div>
            <div class="pd-field">
                <label>Categoría</label>
                <select id="nuevo_categoria" name="nuevo_categoria" onchange="toggleCategoriaOtro()">
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
                <input type="text" id="nuevo_categoria_otro" name="nuevo_categoria_otro" placeholder="Escribe la categoría">
            </div>
        </div>
        <p style="margin:10px 0 0 0;">
            <button type="button" class="link-toggle" onclick="activarModoExistente()">← Buscar producto existente en su lugar</button>
        </p>
    </div>

    <div class="pd-row" style="margin-top:14px;">
        <div class="pd-field chico">
            <label>Cantidad (máx. <?php echo CANTIDAD_MAX; ?>)</label>
            <input type="number" step="0.01" name="cantidad" id="cantidad" min="0.01" max="<?php echo CANTIDAD_MAX; ?>" value="1" oninput="calcularMonto()" required>
        </div>
        <div class="pd-field"><label>Monto total</label><input type="number" step="0.01" name="monto" id="monto" required></div>
        <div class="pd-field">
            <label>Proveedor de esta compra</label>
            <select name="id_prov" id="id_prov">
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
        <button type="submit">REGISTRAR COMPRA</button>
    </div>
</form>
</div>

<div class="pd-card">
<h3 style="margin-top:0;">Historial de compras</h3>
<table class="pd-tabla">
<tr><th>ID Compra</th><th>Producto</th><th>Proveedor</th><th>Fecha</th><th>Cantidad</th><th>Monto</th></tr>
<?php if (count($compras) === 0): ?>
<tr><td colspan="6">Todavía no hay compras registradas.</td></tr>
<?php endif; ?>
<?php foreach ($compras as $c): ?>
<tr>
    <td><?php echo htmlspecialchars($c["ID_compras"]); ?></td>
    <td><?php echo htmlspecialchars(($c["nombre_pro"] ?? "(producto eliminado)") . " — " . $c["ID_Producto"]); ?></td>
    <td><?php echo htmlspecialchars($c["nom_prov"] ?? "—"); ?></td>
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

    // Autocompleta el proveedor asignado a este producto (se puede cambiar si compraste a otro)
    const selectProv = document.getElementById("id_prov");
    selectProv.value = p.ID_prov || "";
}

function calcularMonto() {
    const cantidad = parseFloat(document.getElementById("cantidad").value) || 0;
    if (precioSeleccionado > 0) {
        document.getElementById("monto").value = (precioSeleccionado * cantidad).toFixed(2);
    }
}

// El precio de referencia del producto nuevo también sirve para calcular el monto total, igual que con uno existente.
function usarPrecioNuevo() {
    precioSeleccionado = parseFloat(document.getElementById("nuevo_precio").value) || 0;
    calcularMonto();
}

const CATEGORIAS_ESTANDAR = [
    "Carnes y Embutidos", "Lácteos", "Verduras y Vegetales", "Frutas",
    "Granos, Cereales y Harinas", "Condimentos y Especias", "Bebidas",
    "Panadería y Tortillas", "Enlatados y Conservas", "Desechables y Empaques"
];
function toggleCategoriaOtro() {
    const esOtro = document.getElementById("nuevo_categoria").value === "Otros";
    document.getElementById("fila_categoria_otro").style.display = esOtro ? "flex" : "none";
}

// Alterna entre "producto existente" (buscador) y "producto nuevo" (mini-formulario).
// Todo pasa dentro del mismo módulo, sin salirse a Stock.
function activarModoNuevo() {
    document.getElementById("bloqueExistente").style.display = "none";
    document.getElementById("bloqueNuevo").style.display = "block";
    document.getElementById("producto_nuevo").value = "1";

    document.getElementById("id_producto").value = "";
    document.getElementById("nombre_producto").value = "";
    precioSeleccionado = parseFloat(document.getElementById("nuevo_precio").value) || 0;
    calcularMonto();
}

function activarModoExistente() {
    document.getElementById("bloqueNuevo").style.display = "none";
    document.getElementById("bloqueExistente").style.display = "block";
    document.getElementById("producto_nuevo").value = "0";

    document.getElementById("nuevo_id_producto").value = "";
    document.getElementById("nuevo_nombre").value = "";
    document.getElementById("nuevo_precio").value = "";
    document.getElementById("nuevo_categoria_otro").value = "";
    precioSeleccionado = 0;
}

document.getElementById("formCompra").addEventListener("submit", function (e) {
    const cantidad = parseFloat(document.getElementById("cantidad").value) || 0;
    if (cantidad > <?php echo CANTIDAD_MAX; ?>) {
        e.preventDefault();
        alert("La cantidad máxima permitida por compra es <?php echo CANTIDAD_MAX; ?> unidades. Si necesitas más, regístrala en varias compras.");
        return;
    }

    if (document.getElementById("producto_nuevo").value === "1") {
        if (!document.getElementById("nuevo_id_producto").value.trim() || !document.getElementById("nuevo_nombre").value.trim()) {
            e.preventDefault();
            alert("Completa el ID y el nombre del producto nuevo.");
            return;
        }
        if (!document.getElementById("nuevo_precio").value) {
            e.preventDefault();
            alert("Pon el precio de referencia del producto nuevo.");
            return;
        }
    } else if (!document.getElementById("id_producto").value) {
        e.preventDefault();
        alert("Selecciona un producto de la lista de búsqueda, o usa \"+ Nuevo producto\".");
    }
});

// Si llegamos desde el aviso de stock bajo (o cualquier link con ?id_producto=...),
// preseleccionamos ese producto para no tener que buscarlo de nuevo.
const idProductoPrefill = <?php echo json_encode($idProductoPrefill); ?>;
if (idProductoPrefill) {
    const productoPrefill = productos.find(p => p.ID_Producto === idProductoPrefill);
    if (productoPrefill) {
        seleccionarProducto(productoPrefill);
        document.getElementById("cantidad").focus();
    }
}
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>