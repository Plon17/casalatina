<?php
$modulo_actual = "factura";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$mensaje = "";
$error = "";

// Las facturas ya no se escriben a mano: se generan solas al cobrar un pedido
// (ver pedido_cobro.php). Esta pantalla es el historial: buscar, ver e imprimir.

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "borrar") {
    $pdo->prepare("DELETE FROM factura WHERE ID_Factura = ?")->execute([$_POST["id_factura"]]);
    $mensaje = "Factura eliminada.";
}

$verId = $_GET["ver"] ?? null;

$titulo_pagina = "FACTURA";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:150px;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:14px;}
.pd-tabla th{background:#f5f5f5;}
.pd-actions{margin-top:14px;display:flex;gap:10px;}

.factura-header{text-align:center;margin-bottom:14px;}
.factura-linea{border-top:1px dashed #999;margin:10px 0;}
.factura-fila{display:flex;justify-content:space-between;padding:2px 0;}
.factura-total{font-weight:bold;font-size:16px;}
</style>

<p class="titulo-modulo">Factura</p>

<?php if ($mensaje): ?><p class="mensaje-ok no-print"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error no-print"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<?php if ($verId): ?>
    <?php
    $stmt = $pdo->prepare("SELECT f.*, p.subtotal, p.num_mesa, p.tipo_ped, p.descuento_pct, p.descuento_monto, p.metodo_pago
                            FROM factura f JOIN pedido p ON p.ID_Pedido = f.ID_Pedido
                            WHERE f.ID_Factura = ?");
    $stmt->execute([$verId]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>
    <?php if (!$factura): ?>
        <p class="mensaje-error">No se encontró esa factura.</p>
        <p class="no-print"><a href="factura.php">← Volver al historial</a></p>
    <?php else: ?>
        <?php
        $detStmt = $pdo->prepare("SELECT d.cantidad, d.precio, m.nombre
                                   FROM pedido_detalle d JOIN menu m ON m.ID_Menu = d.ID_Menu
                                   WHERE d.ID_Pedido = ?");
        $detStmt->execute([$factura["ID_Pedido"]]);
        $detalle = $detStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <p class="no-print">
            <a href="factura.php">← Volver al historial</a> &nbsp;|&nbsp;
            <a href="factura_pdf.php?id=<?php echo urlencode($verId); ?>" target="_blank"><button type="button">Imprimir / Descargar PDF</button></a>
        </p>

        <div class="pd-card" style="max-width:500px;">
            <div class="factura-header">
                <p style="font-weight:bold; font-size:18px; margin:0;">CASA LATINA</p>
                <p style="margin:2px 0; color:#666;">Comida típica hondureña — Tel: 0000-0000</p>
                <p style="margin:8px 0 0 0; font-weight:bold;">FACTURA <?php echo htmlspecialchars($factura["ID_Factura"]); ?></p>
            </div>
            <div class="factura-linea"></div>

            <div class="factura-fila"><span>Cliente:</span><span><?php echo htmlspecialchars($factura["nombre_cliente"]); ?></span></div>
            <?php if (!empty($factura["rtn_cliente"])): ?>
            <div class="factura-fila"><span>RTN:</span><span><?php echo htmlspecialchars($factura["rtn_cliente"]); ?></span></div>
            <?php endif; ?>
            <div class="factura-fila"><span>Fecha:</span><span><?php echo htmlspecialchars($factura["fecha_fac"]); ?></span></div>
            <div class="factura-fila"><span>Pedido:</span><span><?php echo htmlspecialchars($factura["ID_Pedido"]); ?>
                <?php echo $factura["num_mesa"] ? " (Mesa " . htmlspecialchars($factura["num_mesa"]) . ")" : " (" . htmlspecialchars($factura["tipo_ped"]) . ")"; ?></span></div>
            <div class="factura-fila"><span>Atendido por:</span><span><?php echo htmlspecialchars($factura["cod_empleado"]); ?></span></div>
            <div class="factura-linea"></div>

            <table class="pd-tabla">
            <tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr>
            <?php foreach ($detalle as $d): ?>
            <tr>
                <td><?php echo htmlspecialchars($d["nombre"]); ?></td>
                <td><?php echo htmlspecialchars($d["cantidad"]); ?></td>
                <td><?php echo number_format((float) $d["precio"], 2); ?></td>
                <td><?php echo number_format((float) $d["precio"] * $d["cantidad"], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            </table>
            <div class="factura-linea"></div>

            <div class="factura-fila"><span>Subtotal:</span><span>L. <?php echo number_format((float) $factura["subtotal"], 2); ?></span></div>
            <?php if ((float) ($factura["descuento_monto"] ?? 0) > 0): ?>
            <div class="factura-fila"><span>Descuento (<?php echo htmlspecialchars($factura["descuento_pct"]); ?>%):</span><span>− L. <?php echo number_format((float) $factura["descuento_monto"], 2); ?></span></div>
            <?php endif; ?>
            <div class="factura-fila"><span>Impuesto (15%):</span><span>L. <?php echo number_format((float) $factura["impuesto"], 2); ?></span></div>
            <div class="factura-fila factura-total"><span>TOTAL:</span><span>L. <?php echo number_format((float) $factura["total"], 2); ?></span></div>
            <div class="factura-fila"><span>Método de pago:</span><span><?php echo htmlspecialchars($factura["metodo_pago"] ?? "Efectivo"); ?></span></div>
            <div class="factura-linea"></div>
            <p style="text-align:center; color:#666;">¡Gracias por su preferencia!</p>
        </div>
    <?php endif; ?>

<?php else: ?>

    <?php
    $cliente = trim($_GET["cliente"] ?? "");
    $idPedidoBuscar = trim($_GET["pedido"] ?? "");
    $desde = $_GET["desde"] ?? "";
    $hasta = $_GET["hasta"] ?? "";

    $condiciones = [];
    $params = [];
    if ($cliente !== "") { $condiciones[] = "nombre_cliente LIKE ?"; $params[] = "%$cliente%"; }
    if ($idPedidoBuscar !== "") { $condiciones[] = "ID_Pedido LIKE ?"; $params[] = "%$idPedidoBuscar%"; }
    if ($desde !== "") { $condiciones[] = "fecha_fac >= ?"; $params[] = $desde; }
    if ($hasta !== "") { $condiciones[] = "fecha_fac <= ?"; $params[] = $hasta; }

    $sql = "SELECT * FROM factura" . ($condiciones ? " WHERE " . implode(" AND ", $condiciones) : "") . " ORDER BY fecha_fac DESC, ID_Factura DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="pd-card no-print">
    <form method="GET" class="pd-row">
        <div class="pd-field"><label>Cliente</label><input type="text" name="cliente" value="<?php echo htmlspecialchars($cliente); ?>"></div>
        <div class="pd-field"><label>N° Pedido</label><input type="text" name="pedido" value="<?php echo htmlspecialchars($idPedidoBuscar); ?>"></div>
        <div class="pd-field"><label>Desde</label><input type="date" name="desde" value="<?php echo htmlspecialchars($desde); ?>"></div>
        <div class="pd-field"><label>Hasta</label><input type="date" name="hasta" value="<?php echo htmlspecialchars($hasta); ?>"></div>
        <button type="submit">BUSCAR</button>
        <?php if ($cliente || $idPedidoBuscar || $desde || $hasta): ?><a href="factura.php" style="align-self:center;">Limpiar</a><?php endif; ?>
    </form>
    </div>

    <div class="pd-card">
    <table class="pd-tabla">
    <tr><th>ID Factura</th><th>Cliente</th><th>Pedido</th><th>Fecha</th><th>Total</th><th></th></tr>
    <?php if (count($facturas) === 0): ?>
    <tr><td colspan="6">No hay facturas que coincidan con la búsqueda.</td></tr>
    <?php endif; ?>
    <?php foreach ($facturas as $f): ?>
    <tr>
        <td><?php echo htmlspecialchars($f["ID_Factura"]); ?></td>
        <td><?php echo htmlspecialchars($f["nombre_cliente"]); ?></td>
        <td><?php echo htmlspecialchars($f["ID_Pedido"]); ?></td>
        <td><?php echo htmlspecialchars($f["fecha_fac"]); ?></td>
        <td><?php echo number_format((float) $f["total"], 2); ?></td>
        <td>
            <a href="factura.php?ver=<?php echo urlencode($f['ID_Factura']); ?>">Ver</a> ·
            <a href="factura_pdf.php?id=<?php echo urlencode($f['ID_Factura']); ?>" target="_blank">PDF</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar esta factura? Esto no cancela ni afecta el pedido.');">
                <input type="hidden" name="accion" value="borrar">
                <input type="hidden" name="id_factura" value="<?php echo htmlspecialchars($f["ID_Factura"]); ?>">
                <button type="submit">Borrar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>