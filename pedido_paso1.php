<?php
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$error = "";

// Guarda la cabecera + detalle del pedido y lo deja en estado "Abierto"
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "guardar_paso1") {

    $items = json_decode($_POST["detalle_json"] ?? "", true);

    if (!$items || count($items) === 0) {
        $error = "El pedido no tiene productos agregados.";
    } elseif (($_POST["tipo_ped"] ?? "") === "Mesa" && empty($_POST["num_mesa"])) {
        $error = "Selecciona un número de mesa.";
    } else {
        $pdo->beginTransaction();
        try {
            // ID_Pedido es varchar(10), así que generamos algo corto tipo P000001
            $n = (int) $pdo->query("SELECT COUNT(*) AS c FROM pedido")->fetch()["c"] + 1;
            $idPedido = "P" . str_pad($n, 6, "0", STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO pedido
                (ID_Pedido, num_mesa, cod_empleado, tipo_ped, fecha, subtotal, impuesto, total, estado)
                VALUES (?,?,?,?,?,?,?,?, 'Abierto')");
            $stmt->execute([
                $idPedido, $_POST["num_mesa"], $_POST["cajero"], $_POST["tipo_ped"],
                date("Y-m-d"), $_POST["subtotal"], $_POST["impuesto"], $_POST["total"]
            ]);

            // ID_detped también es varchar(10): usamos un contador global corto tipo D000001
            $d = (int) $pdo->query("SELECT COUNT(*) AS c FROM pedido_detalle")->fetch()["c"] + 1;
            $stmtDet = $pdo->prepare("INSERT INTO pedido_detalle (ID_detped, ID_Pedido, ID_Menu, cantidad, precio)
                                       VALUES (?,?,?,?,?)");
            foreach ($items as $it) {
                $idDet = "D" . str_pad($d, 6, "0", STR_PAD_LEFT);
                $stmtDet->execute([$idDet, $idPedido, $it["id_menu"], $it["cantidad"], $it["precio"]]);
                $d++;
            }

            $pdo->commit();
            header("Location: pedido_paso2.php?id=" . urlencode($idPedido));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al guardar el pedido: " . $e->getMessage();
        }
    }
}

// Menu completo cargado desde PHP para poder buscar sin recargar la pagina
$menu = $pdo->query("SELECT ID_Menu, nombre, precio, descripcion_men FROM menu")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "PEDIDO - PASO 1: MESA Y PRODUCTOS";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="titulo-modulo">Paso 1 de 3 — Seleccionar mesa y agregar productos</p>

<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div style="display:flex; gap:40px; flex-wrap:wrap;">

<div>
    <div class="fila">
        <label>Tipo de pedido:</label>
        <select id="tipo_ped" onchange="toggleMesa()">
            <option value="Mesa">Mesa</option>
            <option value="Envio">Envío</option>
        </select>
    </div>
    <div class="fila" id="fila_mesa"><label>N° Mesa:</label><input type="text" id="num_mesa"></div>
    <div class="fila"><label>Cajero:</label><input type="text" id="cajero" value="<?php echo htmlspecialchars($_SESSION['cod_empleado'] ?? ''); ?>"></div>
    <div class="fila"><label>Buscar:</label><input type="text" id="buscar_menu" placeholder="Nombre o ID"></div>

    <div class="caja-blanca" style="width:320px; height:150px;">
        <table id="tablaResultados">
        <tr><th>ID_Menu</th><th>Nombre</th><th>Precio</th></tr>
        </table>
    </div>

    <div class="fila"><label>Cod prod:</label><input type="text" id="cod_prod" readonly></div>
    <div class="fila"><label>Nombre:</label><input type="text" id="nombre_item" readonly></div>
    <div class="fila"><label>Descripción:</label><input type="text" id="descripcion_item" readonly></div>
    <div class="fila"><label>Precio:</label><input type="text" id="precio_item" readonly></div>
    <div class="fila"><label>Cantidad:</label><input type="number" id="cantidad_item" value="1" min="1"></div>

    <button type="button" onclick="agregarItem()">Agregar</button>
    <button type="button" onclick="eliminarUltimo()">Eliminar</button>
</div>

<div>
    <div class="fila"><label>Sub Total:</label><input type="text" id="subtotal" readonly></div>
    <div class="fila"><label>Impuesto (15%):</label><input type="text" id="impuesto" readonly></div>
    <div class="fila"><label>Total:</label><input type="text" id="total" readonly></div>
    <br>
    <button type="button" onclick="continuarPaso2()">Continuar a Revisión →</button>
</div>

</div>

<h3>Productos del pedido</h3>
<div class="caja-blanca">
<table id="tablaPedido">
<tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th></tr>
</table>
</div>

<form method="POST" id="formGuardar" style="display:none;">
    <input type="hidden" name="accion" value="guardar_paso1">
    <input type="hidden" name="num_mesa" id="f_num_mesa">
    <input type="hidden" name="cajero" id="f_cajero">
    <input type="hidden" name="tipo_ped" id="f_tipo_ped">
    <input type="hidden" name="subtotal" id="f_subtotal">
    <input type="hidden" name="impuesto" id="f_impuesto">
    <input type="hidden" name="total" id="f_total">
    <input type="hidden" name="detalle_json" id="f_detalle_json">
</form>

<script>
const menu = <?php echo json_encode($menu); ?>;
let itemsPedido = [];

function toggleMesa() {
    const tipo = document.getElementById("tipo_ped").value;
    document.getElementById("fila_mesa").style.display = (tipo === "Mesa") ? "block" : "none";
}

document.getElementById("buscar_menu").addEventListener("keyup", function () {
    const texto = this.value.toLowerCase();
    const tabla = document.getElementById("tablaResultados");
    tabla.innerHTML = "<tr><th>ID_Menu</th><th>Nombre</th><th>Precio</th></tr>";
    if (texto === "") return;

    const resultados = menu.filter(m =>
        m.nombre.toLowerCase().includes(texto) || m.ID_Menu.toLowerCase().includes(texto)
    );
    resultados.forEach(m => {
        const fila = tabla.insertRow();
        fila.style.cursor = "pointer";
        fila.innerHTML = `<td>${m.ID_Menu}</td><td>${m.nombre}</td><td>${m.precio}</td>`;
        fila.onclick = () => seleccionarItem(m);
    });
});

function seleccionarItem(m) {
    document.getElementById("cod_prod").value = m.ID_Menu;
    document.getElementById("nombre_item").value = m.nombre;
    document.getElementById("descripcion_item").value = m.descripcion_men || "";
    document.getElementById("precio_item").value = m.precio;
}

function agregarItem() {
    const idMenu = document.getElementById("cod_prod").value;
    const nombre = document.getElementById("nombre_item").value;
    const precio = parseFloat(document.getElementById("precio_item").value);
    const cantidad = parseInt(document.getElementById("cantidad_item").value);

    if (!idMenu || isNaN(precio) || isNaN(cantidad) || cantidad <= 0) {
        alert("Selecciona un producto del menú y una cantidad válida.");
        return;
    }
    itemsPedido.push({ id_menu: idMenu, nombre: nombre, precio: precio, cantidad: cantidad });
    renderTablaPedido();
    recalcularTotales();
}

function eliminarUltimo() {
    itemsPedido.pop();
    renderTablaPedido();
    recalcularTotales();
}

function renderTablaPedido() {
    const tabla = document.getElementById("tablaPedido");
    tabla.innerHTML = "<tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th></tr>";
    itemsPedido.forEach(it => {
        const fila = tabla.insertRow();
        const subLinea = (it.precio * it.cantidad).toFixed(2);
        fila.innerHTML = `<td>${it.id_menu}</td><td>${it.nombre}</td><td>${it.precio}</td><td>${it.cantidad}</td><td>${subLinea}</td>`;
    });
}

function recalcularTotales() {
    const subtotal = itemsPedido.reduce((acc, it) => acc + (it.precio * it.cantidad), 0);
    const impuesto = subtotal * 0.15;
    const total = subtotal + impuesto;
    document.getElementById("subtotal").value = subtotal.toFixed(2);
    document.getElementById("impuesto").value = impuesto.toFixed(2);
    document.getElementById("total").value = total.toFixed(2);
}

function continuarPaso2() {
    if (itemsPedido.length === 0) {
        alert("Agrega al menos un producto antes de continuar.");
        return;
    }
    const tipoPed = document.getElementById("tipo_ped").value;
    if (tipoPed === "Mesa" && !document.getElementById("num_mesa").value) {
        alert("Ingresa el número de mesa.");
        return;
    }
    document.getElementById("f_num_mesa").value = document.getElementById("num_mesa").value;
    document.getElementById("f_cajero").value = document.getElementById("cajero").value;
    document.getElementById("f_tipo_ped").value = tipoPed;
    document.getElementById("f_subtotal").value = document.getElementById("subtotal").value;
    document.getElementById("f_impuesto").value = document.getElementById("impuesto").value;
    document.getElementById("f_total").value = document.getElementById("total").value;
    document.getElementById("f_detalle_json").value = JSON.stringify(itemsPedido);
    document.getElementById("formGuardar").submit();
}

toggleMesa();
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>