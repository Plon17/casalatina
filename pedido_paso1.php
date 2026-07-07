<?php
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$error = "";

// Si viene ?id=..., estamos volviendo a un pedido "Abierto" para seguir agregando productos
$idEditar = $_GET["id"] ?? null;
$pedidoExistente = null;
$itemsExistentes = [];

if ($idEditar) {
    $stmt = $pdo->prepare("SELECT * FROM pedido WHERE ID_Pedido = ? AND estado = 'Abierto'");
    $stmt->execute([$idEditar]);
    $pedidoExistente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pedidoExistente) {
        $detStmt = $pdo->prepare("SELECT d.ID_Menu, d.cantidad, d.precio, m.nombre
                                   FROM pedido_detalle d JOIN menu m ON m.ID_Menu = d.ID_Menu
                                   WHERE d.ID_Pedido = ?");
        $detStmt->execute([$idEditar]);
        $itemsExistentes = $detStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $idEditar = null; // no existe o ya no está "Abierto"
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "guardar_paso1") {

    $items = json_decode($_POST["detalle_json"] ?? "", true);
    $idPedidoExistente = $_POST["id_pedido_existente"] ?? "";

    if (!$items || count($items) === 0) {
        $error = "El pedido no tiene productos agregados.";
    } elseif (($_POST["tipo_ped"] ?? "") === "Mesa" && empty($_POST["num_mesa"])) {
        $error = "Selecciona un número de mesa.";
    } elseif ($idPedidoExistente) {
        // Actualizar un pedido "Abierto" existente (venimos del botón Volver en el Paso 2)
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE pedido SET num_mesa=?, cod_empleado=?, tipo_ped=?
                                    WHERE ID_Pedido=? AND estado='Abierto'");
            $stmt->execute([$_POST["num_mesa"] ?: null, $_POST["cajero"], $_POST["tipo_ped"], $idPedidoExistente]);

            $pdo->prepare("DELETE FROM pedido_detalle WHERE ID_Pedido = ?")->execute([$idPedidoExistente]);

            $d = (int) $pdo->query("SELECT COUNT(*) AS c FROM pedido_detalle")->fetch()["c"] + 1;
            $stmtDet = $pdo->prepare("INSERT INTO pedido_detalle (ID_detped, ID_Pedido, ID_Menu, cantidad, precio)
                                       VALUES (?,?,?,?,?)");
            foreach ($items as $it) {
                $idDet = "D" . str_pad($d, 6, "0", STR_PAD_LEFT);
                $stmtDet->execute([$idDet, $idPedidoExistente, $it["id_menu"], $it["cantidad"], $it["precio"]]);
                $d++;
            }

            $pdo->commit();
            header("Location: pedido_paso2.php?id=" . urlencode($idPedidoExistente));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al actualizar el pedido: " . $e->getMessage();
        }
    } else {
        // Pedido nuevo. Los totales se calculan y guardan en el Paso 2.
        $pdo->beginTransaction();
        try {
            $n = (int) $pdo->query("SELECT COUNT(*) AS c FROM pedido")->fetch()["c"] + 1;
            $idPedido = "P" . str_pad($n, 6, "0", STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO pedido
                (ID_Pedido, num_mesa, cod_empleado, tipo_ped, fecha, subtotal, impuesto, total, estado)
                VALUES (?,?,?,?,?,0,0,0,'Abierto')");
            $stmt->execute([
                $idPedido, $_POST["num_mesa"] ?: null, $_POST["cajero"], $_POST["tipo_ped"], date("Y-m-d")
            ]);

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

$menu = $pdo->query("SELECT ID_Menu, nombre, precio, descripcion_men FROM menu")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "PEDIDO - PASO 1: MESA Y PRODUCTOS";
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
.pd-tabla th{background:#f5f5f5;}
.pd-resultados{max-height:150px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;}
.pd-actions{margin-top:14px;display:flex;gap:10px;}
</style>

<p class="titulo-modulo">Paso 1 de 3 — Seleccionar mesa y agregar productos</p>

<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
<?php if ($idEditar): ?><p>Editando pedido <strong><?php echo htmlspecialchars($idEditar); ?></strong> (aún no enviado a cocina).</p><?php endif; ?>

<div class="pd-card">
    <div class="pd-row">
        <div class="pd-field">
            <label>Tipo de pedido</label>
            <select id="tipo_ped" onchange="toggleMesa()">
                <option value="Mesa" <?php echo (!$pedidoExistente || $pedidoExistente["tipo_ped"] === "Mesa") ? "selected" : ""; ?>>Mesa</option>
                <option value="Envio" <?php echo ($pedidoExistente && $pedidoExistente["tipo_ped"] === "Envio") ? "selected" : ""; ?>>Envío</option>
            </select>
        </div>
        <div class="pd-field chico" id="fila_mesa">
            <label>N° Mesa</label>
            <input type="text" id="num_mesa" value="<?php echo htmlspecialchars($pedidoExistente["num_mesa"] ?? ""); ?>">
        </div>
        <div class="pd-field">
            <label>Cajero</label>
            <input type="text" id="cajero" value="<?php echo htmlspecialchars($_SESSION['cod_empleado'] ?? ''); ?>">
        </div>
    </div>
</div>

<div class="pd-card">
    <div class="pd-row">
        <div class="pd-field" style="flex:1; min-width:220px;">
            <label>Buscar producto</label>
            <input type="text" id="buscar_menu" placeholder="Nombre o ID">
        </div>
        <div class="pd-field chico">
            <label>Cantidad</label>
            <input type="number" id="cantidad_item" value="1" min="1">
        </div>
        <button type="button" onclick="agregarItem()">Agregar</button>
    </div>

    <div class="pd-resultados" style="margin-top:10px;">
        <table class="pd-tabla" id="tablaResultados">
        <tr><th>ID_Menu</th><th>Nombre</th><th>Precio</th></tr>
        </table>
    </div>

    <div class="pd-row" style="margin-top:10px;">
        <div class="pd-field"><label>Cod prod</label><input type="text" id="cod_prod" readonly></div>
        <div class="pd-field"><label>Nombre</label><input type="text" id="nombre_item" readonly></div>
        <div class="pd-field" style="flex:1;"><label>Descripción</label><input type="text" id="descripcion_item" readonly></div>
        <div class="pd-field chico"><label>Precio</label><input type="text" id="precio_item" readonly></div>
    </div>
</div>

<div class="pd-card">
    <h3 style="margin-top:0;">Productos del pedido</h3>
    <table class="pd-tabla" id="tablaPedido">
    <tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th><th></th></tr>
    </table>
</div>

<div class="pd-actions">
    <button type="button" onclick="continuarPaso2()">Continuar a Revisión →</button>
</div>

<form method="POST" id="formGuardar" style="display:none;">
    <input type="hidden" name="accion" value="guardar_paso1">
    <input type="hidden" name="id_pedido_existente" value="<?php echo htmlspecialchars($idEditar ?? ""); ?>">
    <input type="hidden" name="num_mesa" id="f_num_mesa">
    <input type="hidden" name="cajero" id="f_cajero">
    <input type="hidden" name="tipo_ped" id="f_tipo_ped">
    <input type="hidden" name="detalle_json" id="f_detalle_json">
</form>

<script>
const menu = <?php echo json_encode($menu); ?>;
let itemsPedido = <?php echo json_encode(array_map(function ($d) {
    return [
        "id_menu" => $d["ID_Menu"],
        "nombre" => $d["nombre"],
        "precio" => (float) $d["precio"],
        "cantidad" => (int) $d["cantidad"]
    ];
}, $itemsExistentes)); ?>;

function toggleMesa() {
    const tipo = document.getElementById("tipo_ped").value;
    document.getElementById("fila_mesa").style.display = (tipo === "Mesa") ? "flex" : "none";
}

document.getElementById("buscar_menu").addEventListener("keyup", function () {
    const texto = this.value.toLowerCase();
    const tabla = document.getElementById("tablaResultados");
    tabla.innerHTML = "<tr><th>ID_Menu</th><th>Nombre</th><th>Precio</th></tr>";
    if (texto === "") return;

    menu.filter(m => m.nombre.toLowerCase().includes(texto) || m.ID_Menu.toLowerCase().includes(texto))
        .forEach(m => {
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
}

function quitarItem(idx) {
    itemsPedido.splice(idx, 1);
    renderTablaPedido();
}

function renderTablaPedido() {
    const tabla = document.getElementById("tablaPedido");
    tabla.innerHTML = "<tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th><th></th></tr>";
    itemsPedido.forEach((it, idx) => {
        const fila = tabla.insertRow();
        const subLinea = (it.precio * it.cantidad).toFixed(2);
        fila.innerHTML = `<td>${it.id_menu}</td><td>${it.nombre}</td><td>${it.precio}</td><td>${it.cantidad}</td><td>${subLinea}</td><td><button type="button" onclick="quitarItem(${idx})">Quitar</button></td>`;
    });
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
    document.getElementById("f_detalle_json").value = JSON.stringify(itemsPedido);
    document.getElementById("formGuardar").submit();
}

toggleMesa();
renderTablaPedido();
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>