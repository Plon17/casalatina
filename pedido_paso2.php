<?php
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auditoria.php";

$idPedido = $_GET["id"] ?? $_POST["id_pedido"] ?? "";
$error = "";

if (!$idPedido) {
    header("Location: pedido_paso1.php");
    exit;
}

// Revisa stock suficiente para una lista de items (según receta de menu_ingredientes)
// y lo descuenta. Lanza una excepción si algo no alcanza (no descuenta nada en ese caso).
function descontarStockPorReceta(PDO $pdo, array $items): void {
    $necesario = [];
    $stmtIng = $pdo->prepare("SELECT ID_Producto, cantidad_necesaria FROM menu_ingredientes WHERE ID_Menu = ?");
    foreach ($items as $it) {
        $stmtIng->execute([$it["id_menu"]]);
        foreach ($stmtIng->fetchAll(PDO::FETCH_ASSOC) as $ing) {
            $necesario[$ing["ID_Producto"]] = ($necesario[$ing["ID_Producto"]] ?? 0)
                + ($ing["cantidad_necesaria"] * $it["cantidad"]);
        }
    }
    if (!$necesario) return;

    $faltantes = [];
    $stmtStock = $pdo->prepare("SELECT nombre_pro, cantidad_pro FROM producto WHERE ID_Producto = ?");
    foreach ($necesario as $idProd => $cantNecesaria) {
        $stmtStock->execute([$idProd]);
        $prod = $stmtStock->fetch(PDO::FETCH_ASSOC);
        if (!$prod || $prod["cantidad_pro"] < $cantNecesaria) {
            $disponible = $prod["cantidad_pro"] ?? 0;
            $faltantes[] = ($prod["nombre_pro"] ?? $idProd) . " (disponible: $disponible, necesario: $cantNecesaria)";
        }
    }
    if ($faltantes) {
        throw new Exception("Stock insuficiente para: " . implode(", ", $faltantes));
    }

    $stmtDescontar = $pdo->prepare("UPDATE producto SET cantidad_pro = cantidad_pro - ? WHERE ID_Producto = ?");
    foreach ($necesario as $idProd => $cantNecesaria) {
        $stmtDescontar->execute([$cantNecesaria, $idProd]);
    }
}

// Recalcula subtotal/impuesto/total del pedido a partir de TODO lo que tenga en pedido_detalle
function recalcularTotalesPedido(PDO $pdo, string $idPedido): void {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(cantidad * precio), 0) AS subtotal FROM pedido_detalle WHERE ID_Pedido = ?");
    $stmt->execute([$idPedido]);
    $subtotal = (float) $stmt->fetch()["subtotal"];
    $impuesto = $subtotal * 0.15;
    $total = $subtotal + $impuesto;
    $pdo->prepare("UPDATE pedido SET subtotal=?, impuesto=?, total=? WHERE ID_Pedido=?")
        ->execute([$subtotal, $impuesto, $total, $idPedido]);
}

// ---------- Primer envío a cocina (pedido todavía "Abierto") ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "enviar_cocina") {
    $items = json_decode($_POST["detalle_json"] ?? "", true);

    if (!$items || count($items) === 0) {
        $error = "El pedido no puede quedar sin productos.";
    } else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM pedido_detalle WHERE ID_Pedido = ?")->execute([$idPedido]);

            $d = (int) $pdo->query("SELECT COUNT(*) AS c FROM pedido_detalle")->fetch()["c"] + 1;
            $stmtDet = $pdo->prepare("INSERT INTO pedido_detalle (ID_detped, ID_Pedido, ID_Menu, cantidad, precio, lote)
                                       VALUES (?,?,?,?,?,1)");
            foreach ($items as $it) {
                $idDet = "D" . str_pad($d, 6, "0", STR_PAD_LEFT);
                $stmtDet->execute([$idDet, $idPedido, $it["id_menu"], $it["cantidad"], $it["precio"]]);
                $d++;
            }

            descontarStockPorReceta($pdo, $items);
            recalcularTotalesPedido($pdo, $idPedido);
            $pdo->prepare("UPDATE pedido SET estado='EnCocina' WHERE ID_Pedido=?")->execute([$idPedido]);

            $pdo->commit();
            header("Location: pedido_cobro.php?id=" . urlencode($idPedido) . "&comanda_lote=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al enviar el pedido a cocina: " . $e->getMessage();
        }
    }
}

// ---------- Tanda adicional (pedido ya estaba "EnCocina") ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "agregar_cocina") {
    $items = json_decode($_POST["detalle_json"] ?? "", true);

    if (!$items || count($items) === 0) {
        $error = "Agrega al menos un producto antes de enviar.";
    } else {
        $pdo->beginTransaction();
        try {
            $stmtLote = $pdo->prepare("SELECT COALESCE(MAX(lote), 0) AS m FROM pedido_detalle WHERE ID_Pedido = ?");
            $stmtLote->execute([$idPedido]);
            $nuevoLote = (int) $stmtLote->fetch()["m"] + 1;

            $d = (int) $pdo->query("SELECT COUNT(*) AS c FROM pedido_detalle")->fetch()["c"] + 1;
            $stmtDet = $pdo->prepare("INSERT INTO pedido_detalle (ID_detped, ID_Pedido, ID_Menu, cantidad, precio, lote)
                                       VALUES (?,?,?,?,?,?)");
            foreach ($items as $it) {
                $idDet = "D" . str_pad($d, 6, "0", STR_PAD_LEFT);
                $stmtDet->execute([$idDet, $idPedido, $it["id_menu"], $it["cantidad"], $it["precio"], $nuevoLote]);
                $d++;
            }

            descontarStockPorReceta($pdo, $items);
            recalcularTotalesPedido($pdo, $idPedido);

            $pdo->commit();
            header("Location: pedido_cobro.php?id=" . urlencode($idPedido) . "&comanda_lote=" . $nuevoLote);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al enviar la tanda adicional: " . $e->getMessage();
        }
    }
}

// Cancelar el pedido desde aquí también
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "cancelar_pedido") {
    $pdo->prepare("UPDATE pedido SET estado='Cancelado' WHERE ID_Pedido=?")->execute([$idPedido]);
    registrarAuditoria($pdo, "pedido", "Pedido cancelado", $idPedido);
    header("Location: pedidos_listado.php");
    exit;
}

$pedidoStmt = $pdo->prepare("SELECT * FROM pedido WHERE ID_Pedido = ?");
$pedidoStmt->execute([$idPedido]);
$pedido = $pedidoStmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    header("Location: pedido_paso1.php");
    exit;
}

if (!in_array($pedido["estado"], ["Abierto", "EnCocina"])) {
    header("Location: pedido_cobro.php?id=" . urlencode($idPedido));
    exit;
}

$detalleStmt = $pdo->prepare("SELECT d.ID_Menu, d.cantidad, d.precio, d.lote, m.nombre
                               FROM pedido_detalle d
                               JOIN menu m ON m.ID_Menu = d.ID_Menu
                               WHERE d.ID_Pedido = ?
                               ORDER BY d.lote, m.nombre");
$detalleStmt->execute([$idPedido]);
$detalleExistente = $detalleStmt->fetchAll(PDO::FETCH_ASSOC);

$menu = $pdo->query("SELECT ID_Menu, nombre, precio, descripcion_men FROM menu WHERE activo = 1")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "PEDIDO - PASO 2: REVISIÓN";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:14px;}
.pd-tabla th{background:var(--color-surface-alt);}
.pd-totales{display:flex;gap:30px;flex-wrap:wrap;align-items:flex-end;margin-top:14px;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:120px;}
.pd-actions{margin-top:16px;display:flex;gap:10px;}
.btn-link{display:inline-block; padding:6px 14px; border:1px solid var(--color-primary); border-radius:5px;
    color:var(--color-primary); text-decoration:none; font-size:13px; font-weight:600;}
.btn-link:hover{background:var(--color-primary); color:#fff;}
.pd-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;}
.pd-resultados{max-height:150px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;}
.ronda-tag{background:var(--color-info-bg);color:var(--color-info);padding:1px 8px;border-radius:10px;font-size:12px;}
</style>

<p class="titulo-modulo">Paso 2 de 3 — Revisar y enviar a cocina</p>
<p><a class="btn-link" href="pedidos_listado.php">← Volver a Mesas</a></p>
<p>Pedido <strong><?php echo htmlspecialchars($idPedido); ?></strong> —
   Mesa: <?php echo htmlspecialchars($pedido["num_mesa"] ?: "N/A"); ?> —
   Tipo: <?php echo htmlspecialchars($pedido["tipo_ped"]); ?> —
   Estado: <?php echo htmlspecialchars($pedido["estado"]); ?></p>

<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<?php if ($pedido["estado"] === "Abierto"): ?>

    <!-- Primer envío: se puede editar libremente todo el pedido -->
    <div class="pd-card">
        <h3 style="margin-top:0;">Productos del pedido</h3>
        <table class="pd-tabla" id="tablaPedido">
        <tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th><th></th></tr>
        </table>

        <div class="pd-totales">
            <div class="pd-field"><label>Sub Total</label><input type="text" id="subtotal" readonly></div>
            <div class="pd-field"><label>Impuesto (15%)</label><input type="text" id="impuesto" readonly></div>
            <div class="pd-field"><label>Total</label><input type="text" id="total" readonly></div>
        </div>

        <div class="pd-actions">
            <button type="button" onclick="volverPaso1()">← Volver (agregar más productos)</button>
            <button type="button" onclick="enviarCocina()">Enviar a Cocina →</button>
        </div>
    </div>

    <form method="POST" onsubmit="return confirm('¿Seguro que deseas cancelar este pedido?');">
        <input type="hidden" name="accion" value="cancelar_pedido">
        <input type="hidden" name="id_pedido" value="<?php echo htmlspecialchars($idPedido); ?>">
        <button type="submit" style="background:var(--color-danger); color:#fff;">Cancelar este pedido</button>
    </form>

    <form method="POST" id="formEnviar" style="display:none;">
        <input type="hidden" name="accion" value="enviar_cocina">
        <input type="hidden" name="id_pedido" value="<?php echo htmlspecialchars($idPedido); ?>">
        <input type="hidden" name="detalle_json" id="f_detalle_json">
    </form>

    <script>
    let itemsPedido = <?php echo json_encode(array_map(function ($d) {
        return ["id_menu" => $d["ID_Menu"], "nombre" => $d["nombre"], "precio" => (float) $d["precio"], "cantidad" => (int) $d["cantidad"]];
    }, $detalleExistente)); ?>;

    function quitarItem(idx) { itemsPedido.splice(idx, 1); renderTablaPedido(); recalcularTotales(); }

    function renderTablaPedido() {
        const tabla = document.getElementById("tablaPedido");
        tabla.innerHTML = "<tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th><th></th></tr>";
        itemsPedido.forEach((it, idx) => {
            const fila = tabla.insertRow();
            const subLinea = (it.precio * it.cantidad).toFixed(2);
            fila.innerHTML = `<td>${it.id_menu}</td><td>${it.nombre}</td><td>${it.precio}</td><td>${it.cantidad}</td><td>${subLinea}</td><td><button type="button" onclick="quitarItem(${idx})">Quitar</button></td>`;
        });
    }

    function recalcularTotales() {
        const subtotal = itemsPedido.reduce((acc, it) => acc + (it.precio * it.cantidad), 0);
        const impuesto = subtotal * 0.15;
        document.getElementById("subtotal").value = subtotal.toFixed(2);
        document.getElementById("impuesto").value = impuesto.toFixed(2);
        document.getElementById("total").value = (subtotal + impuesto).toFixed(2);
    }

    function volverPaso1() { window.location.href = "pedido_paso1.php?id=<?php echo urlencode($idPedido); ?>"; }

    function enviarCocina() {
        if (itemsPedido.length === 0) { alert("El pedido no puede quedar vacío."); return; }
        document.getElementById("f_detalle_json").value = JSON.stringify(itemsPedido);
        document.getElementById("formEnviar").submit();
    }

    renderTablaPedido();
    recalcularTotales();
    </script>

<?php else: /* estado === 'EnCocina' */ ?>

    <!-- Ya enviado a cocina: lo ya enviado queda fijo, solo se puede agregar una tanda nueva -->
    <div class="pd-card">
        <h3 style="margin-top:0;">Ya enviado a cocina</h3>
        <table class="pd-tabla">
        <tr><th>Ronda</th><th>Producto</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th></tr>
        <?php foreach ($detalleExistente as $d): ?>
        <tr>
            <td><span class="ronda-tag">Ronda <?php echo (int) $d["lote"]; ?></span></td>
            <td><?php echo htmlspecialchars($d["nombre"]); ?></td>
            <td><?php echo number_format((float) $d["precio"], 2); ?></td>
            <td><?php echo htmlspecialchars($d["cantidad"]); ?></td>
            <td><?php echo number_format((float) $d["precio"] * $d["cantidad"], 2); ?></td>
        </tr>
        <?php endforeach; ?>
        </table>
        <p style="color:#777; font-size:13px; margin-top:8px;">Esto ya está en preparación — no se puede modificar ni quitar desde aquí.</p>
    </div>

    <div class="pd-card">
        <h3 style="margin-top:0;">Agregar más productos (nueva ronda)</h3>
        <div class="pd-row">
            <div class="pd-field" style="flex:1; min-width:220px;">
                <label>Buscar producto</label>
                <input type="text" id="buscar_menu" placeholder="Nombre o ID" autocomplete="off">
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

        <table class="pd-tabla" id="tablaNuevos" style="margin-top:14px;">
        <tr><th>Producto</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th><th></th></tr>
        </table>

        <div class="pd-totales">
            <div class="pd-field"><label>Subtotal de esta ronda</label><input type="text" id="subtotalNuevo" readonly></div>
        </div>

        <div class="pd-actions">
            <button type="button" onclick="enviarRondaExtra()">Enviar esta ronda a Cocina →</button>
        </div>
    </div>

    <form method="POST" onsubmit="return confirm('¿Seguro que deseas cancelar este pedido?');">
        <input type="hidden" name="accion" value="cancelar_pedido">
        <input type="hidden" name="id_pedido" value="<?php echo htmlspecialchars($idPedido); ?>">
        <button type="submit" style="background:var(--color-danger); color:#fff;">Cancelar este pedido</button>
    </form>

    <form method="POST" id="formAgregar" style="display:none;">
        <input type="hidden" name="accion" value="agregar_cocina">
        <input type="hidden" name="id_pedido" value="<?php echo htmlspecialchars($idPedido); ?>">
        <input type="hidden" name="detalle_json" id="f_detalle_json">
    </form>

    <script>
    const menu = <?php echo json_encode($menu); ?>;
    let itemsNuevos = [];

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

    let seleccionado = null;
    function seleccionarItem(m) {
        seleccionado = m;
        document.getElementById("buscar_menu").value = m.nombre + " (" + m.ID_Menu + ")";
        document.getElementById("tablaResultados").innerHTML = "<tr><th>ID_Menu</th><th>Nombre</th><th>Precio</th></tr>";
    }

    function agregarItem() {
        const cantidad = parseInt(document.getElementById("cantidad_item").value);
        if (!seleccionado || isNaN(cantidad) || cantidad <= 0) {
            alert("Busca y selecciona un producto del menú, y pon una cantidad válida.");
            return;
        }
        itemsNuevos.push({ id_menu: seleccionado.ID_Menu, nombre: seleccionado.nombre, precio: parseFloat(seleccionado.precio), cantidad: cantidad });
        seleccionado = null;
        document.getElementById("buscar_menu").value = "";
        document.getElementById("cantidad_item").value = 1;
        renderTablaNuevos();
    }

    function quitarNuevo(idx) { itemsNuevos.splice(idx, 1); renderTablaNuevos(); }

    function renderTablaNuevos() {
        const tabla = document.getElementById("tablaNuevos");
        tabla.innerHTML = "<tr><th>Producto</th><th>Precio</th><th>Cantidad</th><th>Subtotal línea</th><th></th></tr>";
        let subtotal = 0;
        itemsNuevos.forEach((it, idx) => {
            const subLinea = it.precio * it.cantidad;
            subtotal += subLinea;
            const fila = tabla.insertRow();
            fila.innerHTML = `<td>${it.nombre}</td><td>${it.precio}</td><td>${it.cantidad}</td><td>${subLinea.toFixed(2)}</td><td><button type="button" onclick="quitarNuevo(${idx})">Quitar</button></td>`;
        });
        document.getElementById("subtotalNuevo").value = subtotal.toFixed(2);
    }

    function enviarRondaExtra() {
        if (itemsNuevos.length === 0) { alert("Agrega al menos un producto para esta ronda."); return; }
        document.getElementById("f_detalle_json").value = JSON.stringify(itemsNuevos);
        document.getElementById("formAgregar").submit();
    }

    renderTablaNuevos();
    </script>

<?php endif; ?>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>