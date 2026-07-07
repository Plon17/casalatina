<?php
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$mensaje = "";
$error = "";

// Guardar el pedido completo (cabecera + detalle) que llega como JSON desde el JS
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "guardar_pedido") {

    $items = json_decode($_POST["detalle_json"], true);

    if (!$items || count($items) === 0) {
        $error = "El pedido no tiene productos agregados.";
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO pedido
                (ID_Pedido, num_mesa, cod_empleado, tipo_ped, fecha, subtotal, impuesto, total, monto_recibido, cambio)
                VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $_POST["id_pedido"], $_POST["num_mesa"], $_POST["cajero"], $_POST["tipo_ped"],
                date("Y-m-d"), $_POST["subtotal"], $_POST["impuesto"], $_POST["total"],
                $_POST["monto_recibido"], $_POST["cambio"]
            ]);

            $stmtDet = $pdo->prepare("INSERT INTO pedido_detalle (ID_detped, ID_Pedido, ID_Menu, cantidad, precio)
                                       VALUES (?,?,?,?,?)");
            $i = 1;
            foreach ($items as $it) {
                $idDet = $_POST["id_pedido"] . "-" . $i;
                $stmtDet->execute([$idDet, $_POST["id_pedido"], $it["id_menu"], $it["cantidad"], $it["precio"]]);
                $i++;
            }

            $pdo->commit();
            $mensaje = "Pedido #" . htmlspecialchars($_POST["id_pedido"]) . " guardado correctamente.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al guardar el pedido: " . $e->getMessage();
        }
    }
}

// Cancelar un pedido existente
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "cancelar_pedido") {
    $stmt = $pdo->prepare("UPDATE pedido SET estado='Cancelado' WHERE ID_Pedido=?");
    $stmt->execute([$_POST["id_pedido_cancelar"]]);
    $mensaje = "Pedido cancelado.";
}

// Traemos el menu para la búsqueda del lado del cliente (JS)
$menu = $pdo->query("SELECT ID_Menu, nombre, precio, descripcion_men FROM menu")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "PEDIDO";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="titulo-modulo">Pedido</p>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo $mensaje; ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo $error; ?></p><?php endif; ?>

<div style="display:flex; gap:40px; flex-wrap:wrap;">

<div>
    <div class="fila"><label>N° Pedido:</label><input type="text" id="id_pedido" required></div>
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
    <div class="fila"><label>Cajero:</label><input type="text" id="cajero" value="<?php echo htmlspecialchars($_SESSION['ID_usuario'] ?? ''); ?>"></div>

    <button type="button" onclick="agregarItem()">Agregar</button>
    <button type="button" onclick="eliminarUltimo()">Eliminar</button>
</div>

<div>
    <div class="fila"><label>N° Mesa:</label><input type="text" id="num_mesa"></div>
    <div class="fila"><label>Sub Total:</label><input type="text" id="subtotal" readonly></div>
    <div class="fila"><label>Impuesto (15%):</label><input type="text" id="impuesto" readonly></div>
    <div class="fila"><label>Total:</label><input type="text" id="total" readonly></div>
    <div class="fila"><label>Monto Recibido:</label><input type="number" step="0.01" id="monto_recibido" onkeyup="calcularCambio()"></div>
    <div class="fila"><label>Cambio:</label><input type="text" id="cambio" readonly></div>

    <br>
    <button type="button" onclick="guardarPedido('Mesa')">Enviar (Mesa)</button>
    <button type="button" onclick="guardarPedido('Envio')">Envío</button>
    <button type="button" onclick="window.print()">Imprimir</button>
    <button type="button" onclick="nuevoPedido()">Nuevo</button>
</div>

</div>

<h3>Productos del pedido</h3>
<div class="caja-blanca">
<table id="tablaPedido">
<tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th></tr>
</table>
</div>

<hr>
<h3>Cancelar un pedido existente</h3>
<form method="POST" onsubmit="return confirm('¿Seguro que deseas cancelar este pedido?');">
    <input type="hidden" name="accion" value="cancelar_pedido">
    <label>N° Pedido a cancelar:</label>
    <input type="text" name="id_pedido_cancelar" required>
    <button type="submit">CANCELACIÓN PEDIDO</button>
</form>

<!-- Formulario oculto que realmente se envía a PHP para guardar el pedido -->
<form method="POST" id="formGuardar" style="display:none;">
    <input type="hidden" name="accion" value="guardar_pedido">
    <input type="hidden" name="id_pedido" id="f_id_pedido">
    <input type="hidden" name="num_mesa" id="f_num_mesa">
    <input type="hidden" name="cajero" id="f_cajero">
    <input type="hidden" name="tipo_ped" id="f_tipo_ped">
    <input type="hidden" name="subtotal" id="f_subtotal">
    <input type="hidden" name="impuesto" id="f_impuesto">
    <input type="hidden" name="total" id="f_total">
    <input type="hidden" name="monto_recibido" id="f_monto_recibido">
    <input type="hidden" name="cambio" id="f_cambio">
    <input type="hidden" name="detalle_json" id="f_detalle_json">
</form>

<script>
// Menu completo cargado desde PHP para poder buscar sin recargar la pagina
const menu = <?php echo json_encode($menu); ?>;
let itemsPedido = []; // productos agregados al pedido actual

// Buscar en el menu mientras el usuario escribe
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
    calcularCambio();
}

function calcularCambio() {
    const total = parseFloat(document.getElementById("total").value) || 0;
    const recibido = parseFloat(document.getElementById("monto_recibido").value) || 0;
    document.getElementById("cambio").value = (recibido - total).toFixed(2);
}

function guardarPedido(tipoPedido) {
    if (itemsPedido.length === 0) {
        alert("Agrega al menos un producto antes de guardar el pedido.");
        return;
    }
    const idPedido = document.getElementById("id_pedido").value;
    if (!idPedido) {
        alert("Ingresa el número de pedido.");
        return;
    }

    document.getElementById("f_id_pedido").value = idPedido;
    document.getElementById("f_num_mesa").value = document.getElementById("num_mesa").value;
    document.getElementById("f_cajero").value = document.getElementById("cajero").value;
    document.getElementById("f_tipo_ped").value = tipoPedido;
    document.getElementById("f_subtotal").value = document.getElementById("subtotal").value;
    document.getElementById("f_impuesto").value = document.getElementById("impuesto").value;
    document.getElementById("f_total").value = document.getElementById("total").value;
    document.getElementById("f_monto_recibido").value = document.getElementById("monto_recibido").value || 0;
    document.getElementById("f_cambio").value = document.getElementById("cambio").value || 0;
    document.getElementById("f_detalle_json").value = JSON.stringify(itemsPedido);

    document.getElementById("formGuardar").submit();
}

function nuevoPedido() {
    itemsPedido = [];
    document.getElementById("id_pedido").value = "";
    document.getElementById("num_mesa").value = "";
    document.getElementById("monto_recibido").value = "";
    document.getElementById("cambio").value = "";
    renderTablaPedido();
    recalcularTotales();
}
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
